<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Nexor\Cms\Livewire\FeedbackForm as FeedbackFormComponent;
use Nexor\Cms\Mail\FeedbackSubmissionMessage;
use Nexor\Cms\Models\Agreement;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Models\MailTemplate;
use Tests\TestCase;

/**
 * Формы из админки («Формы ОС») на сайте: вывод, приём, запись и письмо.
 */
class FeedbackFormSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Storage::fake('local');
    }

    /**
     * Форма «имя + телефон + e-mail».
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function makeForm(array $attributes = []): FeedbackForm
    {
        $form = FeedbackForm::factory()->create($attributes);

        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'name', 'label' => 'Имя', 'type' => 'string', 'is_required' => true, 'sort' => 10]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'phone', 'label' => 'Телефон', 'type' => 'phone', 'is_required' => true, 'sort' => 20]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'email', 'label' => 'E-mail', 'type' => 'email', 'sort' => 30]);

        return $form->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submitForm(FeedbackForm $form, array $data = []): TestResponse
    {
        return $this->from('/contacts')->post('/nexor/form', array_replace_recursive([
            '_form' => Crypt::encrypt(['name' => 'form-'.$form->id, 'form_id' => $form->id]),
            'fields' => ['name' => 'Андрей', 'phone' => '+7 (999) 123-45-67', 'email' => 'andrey@example.com'],
        ], $data));
    }

    public function test_the_component_renders_the_fields_of_the_form(): void
    {
        $form = $this->makeForm();

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertStringContainsString('name="fields[name]"', $html);
        $this->assertStringContainsString('type="tel"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('Заказать звонок', $html);
        $this->assertStringNotContainsString('wire:', $html);
    }

    public function test_the_form_can_be_found_by_its_code(): void
    {
        $form = $this->makeForm(['code' => 'callback']);

        $html = Blade::render('<x-nexor::form form="callback" button="Жду звонка" />');

        $this->assertStringContainsString('name="fields[phone]"', $html);
        $this->assertStringContainsString('Жду звонка', $html);
    }

    public function test_a_disabled_or_missing_form_renders_nothing(): void
    {
        $form = $this->makeForm(['is_active' => false]);

        $this->assertSame('', trim(Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id])));
        $this->assertSame('', trim(Blade::render('<x-nexor::form form="nope" />')));
    }

    public function test_a_submission_is_stored_and_mailed(): void
    {
        $form = $this->makeForm();

        $this->submitForm($form)->assertRedirect('/contacts')->assertSessionHas('nexor.form.sent', 'form-'.$form->id);

        $submission = FeedbackSubmission::query()->sole();

        $this->assertSame($form->id, $submission->form_id);
        $this->assertSame('Андрей', $submission->data['name']['value']);
        $this->assertSame('Телефон', $submission->data['phone']['label']);
        $this->assertFalse($submission->is_read);
        $this->assertStringEndsWith('/contacts', $submission->page_url);

        Mail::assertSent(FeedbackSubmissionMessage::class, function (FeedbackSubmissionMessage $mail) {
            return $mail->hasTo('manager@example.com')
                && $mail->replyToAddress === 'andrey@example.com'
                && str_contains($mail->body, 'Андрей');
        });
    }

    public function test_fields_are_validated_by_their_type(): void
    {
        $form = $this->makeForm();

        $this->submitForm($form, ['fields' => ['name' => '', 'phone' => 'позвоните мне', 'email' => 'not-an-email']])
            ->assertSessionHasErrors(['fields.name', 'fields.phone', 'fields.email']);

        $this->assertSame(0, FeedbackSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_honeypot_silently_drops_the_submission(): void
    {
        $form = $this->makeForm();

        $this->submitForm($form, ['website' => 'http://spam.example'])->assertSessionHas('nexor.form.sent');

        $this->assertSame(0, FeedbackSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_a_disabled_form_does_not_accept_submissions(): void
    {
        $form = $this->makeForm(['is_active' => false]);

        $this->submitForm($form)->assertNotFound();
    }

    public function test_the_mail_template_receives_the_field_placeholders(): void
    {
        $template = MailTemplate::query()->create([
            'code' => 'CALLBACK',
            'name' => 'Обратный звонок',
            'to' => 'boss@example.com',
            'subject' => 'Звонок от #NAME#',
            'body' => '<p>#NAME#, #PHONE#</p><a href="#SUBMISSION_URL#">запись</a>',
            'body_type' => 'html',
            'is_active' => true,
        ]);

        $form = $this->makeForm(['mail_template_id' => $template->id]);

        $this->submitForm($form, ['fields' => ['name' => '<b>Хакер</b>']]);

        Mail::assertSent(FeedbackSubmissionMessage::class, function (FeedbackSubmissionMessage $mail) {
            return $mail->hasTo('boss@example.com')
                && ! $mail->hasTo('manager@example.com')
                && $mail->subjectLine === 'Звонок от <b>Хакер</b>'
                // Значения посетителя в HTML-письме экранируются.
                && str_contains($mail->body, '&lt;b&gt;Хакер&lt;/b&gt;')
                && ! str_contains($mail->body, '<b>Хакер</b>')
                && str_contains($mail->body, '/forms/');
        });
    }

    public function test_an_uploaded_file_is_stored_privately_and_attached(): void
    {
        $form = $this->makeForm();
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'sort' => 40]);

        $this->submitForm($form->fresh(), [
            'fields' => ['resume' => UploadedFile::fake()->create('cv.pdf', 120, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $file = FeedbackSubmission::query()->sole()->files()['resume'];

        $this->assertSame('cv.pdf', $file['name']);
        $this->assertStringStartsWith('feedback/'.$form->id.'/', $file['path']);
        Storage::disk('local')->assertExists($file['path']);

        Mail::assertSent(FeedbackSubmissionMessage::class, fn (FeedbackSubmissionMessage $mail) => count($mail->attachments()) === 1);
    }

    public function test_dangerous_files_are_rejected(): void
    {
        $form = $this->makeForm();
        FeedbackFormField::factory()->for($form, 'form')->create([
            'code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'sort' => 40,
            'settings' => ['extensions' => 'pdf,html,php'],
        ]);

        $this->submitForm($form->fresh(), [
            'fields' => ['resume' => UploadedFile::fake()->create('page.html', 1, 'text/html')],
        ])->assertSessionHasErrors('fields.resume');

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_files_are_removed_when_submissions_are_not_stored(): void
    {
        $form = $this->makeForm(['store_submissions' => false]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'sort' => 40]);

        $this->submitForm($form->fresh(), [
            'fields' => ['resume' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, FeedbackSubmission::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
        Mail::assertSent(FeedbackSubmissionMessage::class);
    }

    public function test_an_active_agreement_must_be_accepted(): void
    {
        $agreement = Agreement::factory()->create();
        $form = $this->makeForm(['agreement_id' => $agreement->id]);

        $this->submitForm($form)->assertSessionHasErrors('agreement');

        $this->submitForm($form, ['agreement' => '1'])->assertSessionHasNoErrors();

        $submission = FeedbackSubmission::query()->sole();
        $this->assertSame($agreement->id, $submission->agreement_id);
        $this->assertNotNull($submission->agreed_at);
    }

    public function test_a_disabled_agreement_is_not_required(): void
    {
        $agreement = Agreement::factory()->create(['is_active' => false]);
        $form = $this->makeForm(['agreement_id' => $agreement->id]);

        $this->submitForm($form)->assertSessionHasNoErrors();

        $this->assertStringNotContainsString('name="agreement"', Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]));
    }

    public function test_the_agreement_opens_in_a_popup(): void
    {
        $agreement = Agreement::factory()->create(['text' => '<p>Полный текст соглашения</p>']);
        $form = $this->makeForm(['agreement_id' => $agreement->id, 'agreement_popup' => true]);

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertStringContainsString('name="agreement"', $html);
        $this->assertStringContainsString('Полный текст соглашения', $html);
        // Пробел перед ссылкой — снаружи label, иначе блочно-строчный label его съест.
        $this->assertStringContainsString('согласен на</label> <a', $html);
        $this->assertMatchesRegularExpression('/href="#[^"]+"[^>]*>обработку персональных данных<\/a>/u', $html);
    }

    public function test_the_agreement_links_to_its_page_without_the_popup(): void
    {
        $agreement = Agreement::factory()->create(['code' => 'personal-data', 'text' => '<p>Полный текст соглашения</p>']);
        $form = $this->makeForm(['agreement_id' => $agreement->id, 'agreement_popup' => false]);

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertStringContainsString(route('nexor.agreement', 'personal-data'), $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringNotContainsString('Полный текст соглашения', $html);

        $this->get('/agreement/personal-data')->assertOk()->assertSee('Полный текст соглашения', false);
    }

    public function test_the_page_of_a_disabled_agreement_is_not_found(): void
    {
        Agreement::factory()->create(['code' => 'old', 'is_active' => false]);

        $this->get('/agreement/old')->assertNotFound();
    }

    public function test_a_plain_text_agreement_is_escaped(): void
    {
        Agreement::factory()->create(['code' => 'plain', 'text_type' => 'text', 'text' => "<script>alert(1)</script>\nВторая строка"]);

        $this->get('/agreement/plain')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_an_ajax_form_is_rendered_through_livewire(): void
    {
        $form = $this->makeForm(['ajax' => true]);

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertStringContainsString('wire:submit="submit"', $html);
        $this->assertStringContainsString('wire:model="fields.phone"', $html);
    }

    public function test_the_livewire_form_submits_without_a_reload(): void
    {
        $form = $this->makeForm(['ajax' => true]);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])
            ->set('fields.name', 'Андрей')
            ->set('fields.phone', '+7 999 123-45-67')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true)
            ->assertSee('Спасибо! Мы перезвоним.');

        $this->assertSame('Андрей', FeedbackSubmission::query()->sole()->data['name']['value']);
        Mail::assertSent(FeedbackSubmissionMessage::class);
    }

    public function test_the_livewire_form_validates_fields_and_the_agreement(): void
    {
        $agreement = Agreement::factory()->create();
        $form = $this->makeForm(['ajax' => true, 'agreement_id' => $agreement->id]);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])
            ->assertSeeHtml('wire:model="agreement"')
            ->assertSee('обработку персональных данных')
            ->set('fields.phone', 'abc')
            ->call('submit')
            ->assertHasErrors(['fields.name', 'fields.phone', 'agreement'])
            ->assertSet('sent', false);

        $this->assertSame(0, FeedbackSubmission::query()->count());
    }

    public function test_the_livewire_form_accepts_files(): void
    {
        $form = $this->makeForm(['ajax' => true]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'is_required' => true, 'sort' => 40]);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])
            ->set('fields.name', 'Андрей')
            ->set('fields.phone', '+7 999 123-45-67')
            ->set('fields.resume', UploadedFile::fake()->create('cv.pdf', 50, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $file = FeedbackSubmission::query()->sole()->files()['resume'];

        $this->assertSame('cv.pdf', $file['name']);
        Storage::disk('local')->assertExists($file['path']);
    }

    public function test_the_form_id_of_the_livewire_form_cannot_be_changed_from_the_browser(): void
    {
        $form = $this->makeForm(['ajax' => true]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])->set('formId', 999);
    }

    public function test_the_livewire_form_is_rate_limited(): void
    {
        $form = $this->makeForm(['ajax' => true]);
        $component = Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id]);

        for ($attempt = 0; $attempt < FeedbackFormComponent::PER_MINUTE; $attempt++) {
            $component->set('fields.name', 'Андрей')->set('fields.phone', '+7 999 123-45-67')->call('submit');
        }

        $component->set('fields.name', 'Андрей')->set('fields.phone', '+7 999 123-45-67')
            ->call('submit')
            ->assertHasErrors('form');

        $this->assertSame(FeedbackFormComponent::PER_MINUTE, FeedbackSubmission::query()->count());
    }
}
