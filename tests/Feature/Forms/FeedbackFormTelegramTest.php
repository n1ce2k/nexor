<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Nexor\Cms\Mail\FeedbackSubmissionMessage;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Support\FormTelegram;
use Tests\TestCase;

/**
 * Вкладка «Telegram»: заявка приходит сообщением от бота.
 */
class FeedbackFormTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $telegram  Как приходит из панели
     */
    protected function makeForm(array $telegram = []): FeedbackForm
    {
        $form = FeedbackForm::factory()->create([
            'name' => 'Обратный звонок',
            'telegram' => FormTelegram::fromInput([
                'enabled' => true,
                'token' => '123:SECRET',
                'chat_ids' => '111, -100222',
                ...$telegram,
            ], null),
        ]);

        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'name', 'label' => 'Имя', 'type' => 'string', 'sort' => 10]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'sort' => 20]);

        return $form->fresh();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    protected function submitForm(FeedbackForm $form, array $fields = ['name' => 'Андрей']): TestResponse
    {
        return $this->from('/contacts')->post('/nexor/form', [
            '_form' => Crypt::encrypt(['name' => 'form-'.$form->id, 'form_id' => $form->id]),
            'fields' => $fields,
        ]);
    }

    public function test_the_token_is_stored_encrypted(): void
    {
        $form = $this->makeForm();

        $this->assertNotSame('123:SECRET', $form->telegram['token']);
        $this->assertSame('123:SECRET', Crypt::decryptString($form->telegram['token']));
        $this->assertSame('111, -100222', $form->telegram['chat_ids']);
        // Текст по умолчанию не хранится.
        $this->assertNull($form->telegram['message']);
    }

    public function test_a_submission_is_sent_to_every_chat(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $form = $this->makeForm(['thread_id' => 7, 'silent' => true]);

        $this->submitForm($form)->assertSessionHasNoErrors();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/bot123:SECRET/sendMessage'
            && $request['chat_id'] === '111'
            && $request['message_thread_id'] === '7'
            && $request['disable_notification'] === 'true'
            && str_contains($request['text'], 'Новая заявка: Обратный звонок')
            && str_contains($request['text'], 'Имя: Андрей')
            && str_contains($request['text'], '/forms/'.$form->id.'/edit'));
        Http::assertSent(fn (Request $request) => $request['chat_id'] === '-100222');

        // Письмо уходит как раньше — Telegram его не заменяет.
        Mail::assertSent(FeedbackSubmissionMessage::class);
    }

    public function test_an_own_message_template_is_used(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $form = $this->makeForm(['chat_ids' => '111', 'message' => "Звонок: #NAME#\n#UNKNOWN#\n\n\n\nФорма #FORM_ID#"]);

        $this->submitForm($form);

        Http::assertSent(fn (Request $request) => $request['text'] === "Звонок: Андрей\n#UNKNOWN#\n\nФорма {$form->id}");
    }

    public function test_files_are_sent_as_documents(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $form = $this->makeForm(['chat_ids' => '111']);

        $this->submitForm($form, [
            'name' => 'Андрей',
            'resume' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sendDocument')
            && $request->isMultipart()
            && collect($request->data())->contains(fn ($part) => ($part['name'] ?? null) === 'document' && ($part['filename'] ?? null) === 'cv.pdf'));
    }

    public function test_files_can_be_left_out(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $form = $this->makeForm(['chat_ids' => '111', 'send_files' => false]);

        $this->submitForm($form, [
            'name' => 'Андрей',
            'resume' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ]);

        Http::assertSentCount(1);
    }

    public function test_a_broken_telegram_does_not_lose_the_submission(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Unauthorized'], 401)]);
        Log::spy();
        $form = $this->makeForm(['chat_ids' => '111']);

        $this->submitForm($form)->assertSessionHasNoErrors()->assertSessionHas('nexor.form.sent');

        $this->assertSame(1, FeedbackSubmission::query()->count());
        Mail::assertSent(FeedbackSubmissionMessage::class);
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => str_contains($message, 'Telegram не принял токен бота'));
    }

    public function test_a_disabled_telegram_sends_nothing(): void
    {
        $form = $this->makeForm(['enabled' => false]);

        $this->submitForm($form)->assertSessionHasNoErrors();

        Http::assertNothingSent();
    }
}
