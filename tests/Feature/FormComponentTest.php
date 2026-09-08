<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Nexor\Cms\Mail\FormMessage;
use Nexor\Cms\Models\MailTemplate;
use Nexor\Cms\Models\Setting;
use Tests\TestCase;

/**
 * Приём формы обратной связи: что доезжает до письма и чего подменить нельзя.
 */
class FormComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * Настройки формы в том виде, в каком их кладёт компонент.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function config(array $overrides = []): string
    {
        return Crypt::encrypt(array_merge([
            'name' => 'feedback',
            'to' => 'sales@example.com',
            'template' => null,
            'fields' => ['name', 'email', 'message'],
            'consent' => false,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submit(array $data = [], ?string $config = null): TestResponse
    {
        return $this->from('/')->post('/nexor/form', array_merge([
            '_form' => $config ?? $this->config(),
            'name' => 'Андрей',
            'email' => 'andrey@example.com',
            'message' => 'Здравствуйте',
        ], $data));
    }

    public function test_a_filled_form_sends_a_letter(): void
    {
        $this->submit()->assertRedirect('/')->assertSessionHas('nexor.form.sent', 'feedback');

        Mail::assertSent(FormMessage::class);
    }

    public function test_required_fields_are_checked(): void
    {
        $this->submit(['name' => '', 'message' => ''])
            ->assertSessionHasErrors(['name', 'message']);

        Mail::assertNotSent(FormMessage::class);
    }

    public function test_only_the_declared_fields_are_required(): void
    {
        // Форма без сообщения не должна требовать сообщение.
        $this->submit(
            ['message' => ''],
            $this->config(['fields' => ['name', 'email']]),
        )->assertSessionHasNoErrors();

        Mail::assertSent(FormMessage::class);
    }

    public function test_the_recipient_cannot_be_swapped_from_the_browser(): void
    {
        // Открытый релей: подменённые настройки не расшифруются.
        $this->submit(['_form' => 'to=zloumyshlennik@example.com'])->assertStatus(422);

        Mail::assertNotSent(FormMessage::class);
    }

    public function test_a_tampered_config_is_refused(): void
    {
        $this->submit(['_form' => Crypt::encrypt('не массив')])->assertStatus(422);

        Mail::assertNotSent(FormMessage::class);
    }

    public function test_a_bot_filling_the_honeypot_is_answered_but_not_delivered(): void
    {
        $this->submit(['website' => 'https://spam.example'])
            ->assertRedirect('/')
            ->assertSessionHas('nexor.form.sent', 'feedback');

        // Боту нельзя показывать, что его отсеяли, но письма быть не должно.
        Mail::assertNotSent(FormMessage::class);
    }

    public function test_consent_is_required_when_the_form_asks_for_it(): void
    {
        $config = $this->config(['consent' => true]);

        $this->submit([], $config)->assertSessionHasErrors('consent');

        $this->submit(['consent' => '1'], $config)->assertSessionHasNoErrors();

        Mail::assertSent(FormMessage::class);
    }

    public function test_the_recipient_falls_back_to_the_site_settings(): void
    {
        Setting::query()->create([
            'key' => 'contacts.email',
            'name' => 'E-mail',
            'group' => 'contacts',
            'type' => 'string',
            'value' => 'info@example.com',
        ]);

        $this->submit([], $this->config(['to' => null]))->assertRedirect('/');

        Mail::assertSent(FormMessage::class);
    }

    public function test_nothing_is_sent_when_there_is_nobody_to_send_to(): void
    {
        $this->submit([], $this->config(['to' => null]))->assertRedirect('/');

        // Заявка не должна молча пропасть — в лог уходит предупреждение.
        Mail::assertNotSent(FormMessage::class);
    }

    public function test_a_mail_template_supplies_the_subject(): void
    {
        MailTemplate::query()->create([
            'code' => 'feedback',
            'name' => 'Обратная связь',
            'subject' => 'Заявка от #NAME#',
            'body' => 'Сообщение: #MESSAGE#',
            'body_type' => 'text',
            'is_active' => true,
        ]);

        $this->submit([], $this->config(['template' => 'feedback']))->assertRedirect('/');

        Mail::assertSent(fn (FormMessage $mail) => $mail->subjectLine === 'Заявка от Андрей');
    }
}
