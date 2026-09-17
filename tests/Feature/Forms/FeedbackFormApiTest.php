<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Models\Agreement;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Support\FormCaptcha;
use Nexor\Cms\Support\FormTelegram;
use Nexor\Cms\Support\Secrets;
use Tests\Concerns\CreatesAdminUsers;
use Tests\TestCase;

/**
 * Экраны «Формы ОС» и «Соглашения» в админке.
 */
class FeedbackFormApiTest extends TestCase
{
    use CreatesAdminUsers, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'callback',
            'name' => 'Обратный звонок',
            'title' => 'Заказать звонок',
            'button_text' => 'Отправить',
            'success_text' => 'Спасибо!',
            'mail_template_id' => null,
            'to' => 'manager@example.com',
            'agreement_id' => null,
            'agreement_popup' => true,
            'ajax' => true,
            'store_submissions' => true,
            'is_active' => true,
            'sort' => 500,
            'fields' => [
                ['id' => null, 'code' => 'name', 'label' => 'Имя', 'type' => 'string', 'is_required' => true, 'placeholder' => 'Иван', 'settings' => []],
                ['id' => null, 'code' => 'resume', 'label' => 'Резюме', 'type' => 'file', 'is_required' => false, 'placeholder' => 'лишнее', 'settings' => ['extensions' => 'pdf', 'max_kb' => 2048, 'rows' => 5]],
            ],
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function makeSubmission(FeedbackForm $form, array $data = []): FeedbackSubmission
    {
        return FeedbackSubmission::query()->create([
            'form_id' => $form->id,
            'data' => $data ?: ['name' => ['label' => 'Имя', 'type' => 'string', 'value' => 'Андрей']],
        ]);
    }

    public function test_a_form_is_created_with_its_fields(): void
    {
        $response = $this->actingAs($this->adminWith(['forms.create']))
            ->postJson('/admin/api/forms', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'callback')
            ->assertJsonPath('data.fields.0.code', 'name')
            ->assertJsonPath('data.fields.1.type', 'file');

        $form = FeedbackForm::query()->sole();

        $this->assertSame(['name', 'resume'], $form->fields->pluck('code')->all());
        // Настройки, которые типу не нужны, не сохраняются.
        $this->assertSame(['extensions' => 'pdf', 'max_kb' => 2048], $form->fields[1]->settings);
        $this->assertNull($form->fields[1]->placeholder);
        $this->assertContains('NAME', $response->json('data.placeholders'));
        $this->assertSame('<x-nexor::form :id="'.$form->id.'" />', $response->json('data.tag'));
    }

    public function test_saving_syncs_fields_in_the_given_order(): void
    {
        $form = FeedbackForm::factory()->create(['code' => 'callback']);
        $name = FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'name', 'sort' => 10]);
        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'old', 'sort' => 20]);

        $this->actingAs($this->adminWith(['forms.update']))
            ->putJson("/admin/api/forms/{$form->id}", $this->payload(['fields' => [
                ['id' => null, 'code' => 'phone', 'label' => 'Телефон', 'type' => 'phone', 'is_required' => true],
                ['id' => $name->id, 'code' => 'name', 'label' => 'Как вас зовут', 'type' => 'string', 'is_required' => false],
            ]]))
            ->assertOk();

        $fields = $form->fresh()->fields;

        $this->assertSame(['phone', 'name'], $fields->pluck('code')->all());
        $this->assertSame($name->id, $fields[1]->id);
        $this->assertSame('Как вас зовут', $fields[1]->label);
    }

    public function test_a_removed_field_code_can_be_reused_in_the_same_save(): void
    {
        $form = FeedbackForm::factory()->create(['code' => 'callback']);
        $old = FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'name']);

        $this->actingAs($this->adminWith(['forms.update']))
            ->putJson("/admin/api/forms/{$form->id}", $this->payload(['fields' => [
                ['id' => null, 'code' => 'name', 'label' => 'Имя заново', 'type' => 'string'],
            ]]))
            ->assertOk();

        $this->assertModelMissing($old);
        $this->assertSame('Имя заново', $form->fresh()->fields->sole()->label);
    }

    public function test_field_codes_are_validated(): void
    {
        $this->actingAs($this->adminWith(['forms.create']))
            ->postJson('/admin/api/forms', $this->payload([
                'code' => '123',
                'fields' => [
                    ['code' => 'name', 'label' => 'Имя', 'type' => 'string'],
                    ['code' => 'name', 'label' => 'Ещё имя', 'type' => 'string'],
                    ['code' => '1bad', 'label' => 'Плохой', 'type' => 'select'],
                ],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'fields.0.code', 'fields.2.code', 'fields.2.type']);
    }

    public function test_forms_require_permissions(): void
    {
        $form = FeedbackForm::factory()->create();

        $viewer = $this->adminWith(['forms.view']);

        $this->actingAs($viewer)->getJson('/admin/api/forms')->assertOk();
        $this->actingAs($viewer)->getJson('/admin/api/form-meta')->assertOk()->assertJsonCount(5, 'field_types');
        $this->actingAs($viewer)->postJson('/admin/api/forms', $this->payload())->assertForbidden();
        $this->actingAs($viewer)->putJson("/admin/api/forms/{$form->id}", $this->payload())->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/admin/api/forms/{$form->id}")->assertForbidden();
        $this->actingAs($viewer)->getJson("/admin/api/forms/{$form->id}/submissions")->assertForbidden();

        $this->actingAs($this->adminWith())->getJson('/admin/api/forms')->assertForbidden();
    }

    public function test_the_list_counts_unread_submissions(): void
    {
        $form = FeedbackForm::factory()->create();
        $this->makeSubmission($form);
        $this->makeSubmission($form)->update(['is_read' => true]);

        $this->actingAs($this->adminWith(['forms.view']))
            ->getJson('/admin/api/forms')
            ->assertOk()
            ->assertJsonPath('data.0.submissions_count', 2)
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_submissions_are_listed_and_marked_read_when_opened(): void
    {
        $form = FeedbackForm::factory()->create();
        $submission = $this->makeSubmission($form);
        $admin = $this->adminWith(['forms.submissions.view']);

        $this->actingAs($admin)
            ->getJson("/admin/api/forms/{$form->id}/submissions")
            ->assertOk()
            ->assertJsonPath('data.0.preview', 'Андрей')
            ->assertJsonPath('meta.unread', 1);

        $this->actingAs($admin)
            ->getJson("/admin/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertOk()
            ->assertJsonPath('data.fields.0.value', 'Андрей');

        $this->assertTrue($submission->fresh()->is_read);
    }

    public function test_submission_fields_keep_the_order_of_the_form(): void
    {
        // MySQL переставляет ключи JSON, поэтому порядок держится на `sort`.
        $form = FeedbackForm::factory()->create();
        $submission = $this->makeSubmission($form, [
            'file' => ['label' => 'Файл', 'type' => 'file', 'value' => null, 'sort' => 2],
            'phone' => ['label' => 'Телефон', 'type' => 'phone', 'value' => '+7 999', 'sort' => 1],
            'name' => ['label' => 'Имя', 'type' => 'string', 'value' => 'Андрей', 'sort' => 0],
        ]);

        $response = $this->actingAs($this->adminWith(['forms.submissions.view']))
            ->getJson("/admin/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertOk();

        $this->assertSame(['name', 'phone', 'file'], array_column($response->json('data.fields'), 'code'));
        $this->assertSame('Андрей · +7 999', $response->json('data.preview'));
    }

    public function test_a_submission_of_another_form_is_not_reachable(): void
    {
        $form = FeedbackForm::factory()->create();
        $other = $this->makeSubmission(FeedbackForm::factory()->create());

        $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/forms/{$form->id}/submissions/{$other->id}")
            ->assertNotFound();
    }

    public function test_an_uploaded_file_is_downloaded_through_the_panel(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('feedback/1/abc.pdf', 'PDF');

        $form = FeedbackForm::factory()->create();
        $submission = $this->makeSubmission($form, [
            'resume' => ['label' => 'Резюме', 'type' => 'file', 'value' => ['path' => 'feedback/1/abc.pdf', 'name' => 'cv.pdf', 'size' => 3]],
        ]);

        $url = "/admin/api/forms/{$form->id}/submissions/{$submission->id}/files/resume";

        $this->get($url)->assertRedirect();

        $this->actingAs($this->adminWith(['forms.submissions.view']))
            ->getJson("/admin/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertJsonPath('data.fields.0.file.name', 'cv.pdf')
            ->assertJsonPath('data.fields.0.file.url', url($url));

        $this->actingAs($this->adminWith(['forms.submissions.view']))
            ->get($url)
            ->assertOk()
            ->assertDownload('cv.pdf');

        $this->actingAs($this->adminWith(['forms.submissions.view']))
            ->get("/admin/api/forms/{$form->id}/submissions/{$submission->id}/files/name")
            ->assertNotFound();
    }

    public function test_deleting_a_submission_removes_its_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('feedback/1/abc.pdf', 'PDF');

        $form = FeedbackForm::factory()->create();
        $submission = $this->makeSubmission($form, [
            'resume' => ['label' => 'Резюме', 'type' => 'file', 'value' => ['path' => 'feedback/1/abc.pdf', 'name' => 'cv.pdf', 'size' => 3]],
        ]);

        $this->actingAs($this->adminWith(['forms.submissions.view']))
            ->deleteJson("/admin/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertForbidden();

        $this->actingAs($this->adminWith(['forms.submissions.delete']))
            ->deleteJson("/admin/api/forms/{$form->id}/submissions/{$submission->id}")
            ->assertOk();

        $this->assertModelMissing($submission);
        Storage::disk('local')->assertMissing('feedback/1/abc.pdf');
    }

    public function test_deleting_a_form_removes_submissions_and_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('feedback/1/abc.pdf', 'PDF');

        $form = FeedbackForm::factory()->create();
        $this->makeSubmission($form, [
            'resume' => ['label' => 'Резюме', 'type' => 'file', 'value' => ['path' => 'feedback/1/abc.pdf', 'name' => 'cv.pdf', 'size' => 3]],
        ]);

        $this->actingAs($this->adminWith(['forms.delete']))
            ->deleteJson("/admin/api/forms/{$form->id}")
            ->assertOk();

        $this->assertModelMissing($form);
        $this->assertSame(0, FeedbackSubmission::query()->count());
        Storage::disk('local')->assertMissing('feedback/1/abc.pdf');
    }

    public function test_an_agreement_is_created_and_updated(): void
    {
        $admin = $this->adminWith(['agreements.create', 'agreements.update']);

        $id = $this->actingAs($admin)
            ->postJson('/admin/api/agreements', [
                'code' => 'personal-data',
                'name' => 'Согласие',
                'label' => 'Я даю согласие на обработку данных',
                'link_text' => 'обработку данных',
                'text' => '<p>Текст</p>',
                'text_type' => 'html',
                'is_active' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.label_parts.before', 'Я даю согласие на ')
            ->assertJsonPath('data.label_parts.link', 'обработку данных')
            ->assertJsonPath('data.url', route('nexor.agreement', 'personal-data'))
            ->json('data.id');

        $this->actingAs($admin)
            ->putJson("/admin/api/agreements/{$id}", [
                'code' => 'personal-data',
                'name' => 'Согласие',
                'label' => 'Согласен',
                'text' => 'Простой текст',
                'text_type' => 'text',
            ])
            ->assertOk()
            ->assertJsonPath('data.text_type', 'text');

        $this->actingAs($admin)
            ->postJson('/admin/api/agreements', ['code' => 'personal-data', 'name' => 'Дубль', 'label' => 'x', 'text_type' => 'rtf'])
            ->assertJsonValidationErrors(['code', 'text_type']);
    }

    public function test_deleting_an_agreement_detaches_it_from_forms(): void
    {
        $agreement = Agreement::factory()->create();
        $form = FeedbackForm::factory()->create(['agreement_id' => $agreement->id]);

        $this->actingAs($this->adminWith(['agreements.view']))
            ->getJson('/admin/api/agreements')
            ->assertOk()
            ->assertJsonPath('data.0.forms_count', 1);

        $this->actingAs($this->adminWith(['agreements.view']))
            ->deleteJson("/admin/api/agreements/{$agreement->id}")
            ->assertForbidden();

        $this->actingAs($this->adminWith(['agreements.delete']))
            ->deleteJson("/admin/api/agreements/{$agreement->id}")
            ->assertOk();

        $this->assertNull($form->fresh()->agreement_id);
    }

    public function test_secrets_are_masked_and_kept_when_the_mask_comes_back(): void
    {
        $admin = $this->adminWith(['forms.create', 'forms.update']);

        $id = $this->actingAs($admin)
            ->postJson('/admin/api/forms', $this->payload([
                'telegram' => ['enabled' => true, 'token' => '123:SECRET', 'chat_ids' => '111 ; 222', 'thread_id' => null, 'message' => '', 'send_files' => true, 'silent' => false],
                'protection' => [
                    'captcha' => 'yandex',
                    'yandex' => ['client_key' => 'client', 'server_key' => 'server-secret', 'webview' => false, 'invisible' => true, 'hide_shield' => true],
                    'google' => ['site_key' => '', 'secret_key' => '', 'version' => 'v2', 'min_score' => 0.5],
                    'nexor' => ['length' => 6, 'chars' => 'digits'],
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.telegram.token', Secrets::MASK)
            ->assertJsonPath('data.telegram.chat_ids', '111, 222')
            ->assertJsonPath('data.protection.captcha', 'yandex')
            ->assertJsonPath('data.protection.yandex.server_key', Secrets::MASK)
            ->assertJsonPath('data.protection.yandex.hide_shield', true)
            ->assertJsonPath('data.protection.nexor.length', 6)
            ->json('data.id');

        $response = $this->actingAs($admin)->getJson("/admin/api/forms/{$id}");
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        $this->assertStringNotContainsString('server-secret', $response->getContent());

        // Маска обратно — секрет не меняется; капчу выключили — её настройки остаются.
        $this->actingAs($admin)
            ->putJson("/admin/api/forms/{$id}", $this->payload([
                'telegram' => ['enabled' => true, 'token' => Secrets::MASK, 'chat_ids' => '111'],
                'protection' => ['captcha' => 'none', 'yandex' => ['client_key' => 'client', 'server_key' => Secrets::MASK]],
            ]))
            ->assertOk();

        $form = FeedbackForm::query()->find($id);
        $this->assertSame('123:SECRET', Secrets::decrypt($form->telegram['token']));
        $this->assertSame('server-secret', Secrets::decrypt($form->protection['yandex']['server_key']));
        $this->assertNull(FormCaptcha::active($form));
    }

    public function test_enabled_features_require_their_keys(): void
    {
        $this->actingAs($this->adminWith(['forms.create']))
            ->postJson('/admin/api/forms', $this->payload([
                'telegram' => ['enabled' => true, 'token' => '', 'chat_ids' => ''],
                'protection' => ['captcha' => 'yandex', 'yandex' => ['client_key' => '', 'server_key' => '']],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'telegram.token', 'telegram.chat_ids',
                'protection.yandex.client_key', 'protection.yandex.server_key',
            ]);

        $this->actingAs($this->adminWith(['forms.create']))
            ->postJson('/admin/api/forms', $this->payload([
                'protection' => ['captcha' => 'google', 'google' => ['site_key' => 'site', 'secret_key' => '', 'version' => 'v4', 'min_score' => 2]],
            ]))
            ->assertJsonValidationErrors(['protection.google.secret_key', 'protection.google.version', 'protection.google.min_score']);

        $this->actingAs($this->adminWith(['forms.create']))
            ->postJson('/admin/api/forms', $this->payload([
                'protection' => ['captcha' => 'recaptcha-v9', 'nexor' => ['length' => 20]],
            ]))
            ->assertJsonValidationErrors(['protection.captcha', 'protection.nexor.length']);
    }

    public function test_the_telegram_check_uses_the_saved_token_behind_the_mask(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $form = FeedbackForm::factory()->create([
            'telegram' => FormTelegram::fromInput(['enabled' => true, 'token' => '123:SAVED', 'chat_ids' => '111'], null),
        ]);

        $this->actingAs($this->adminWith(['forms.view', 'forms.update']))
            ->postJson('/admin/api/forms/telegram-test', ['form_id' => $form->id, 'token' => Secrets::MASK, 'chat_ids' => '555', 'thread_id' => 3])
            ->assertOk();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'bot123:SAVED/sendMessage')
            && $request['chat_id'] === '555'
            && $request['message_thread_id'] === '3');
    }

    public function test_the_telegram_check_explains_errors_and_needs_rights(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);

        $this->actingAs($this->adminWith(['forms.view', 'forms.create']))
            ->postJson('/admin/api/forms/telegram-test', ['token' => '1:X', 'chat_ids' => '1'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Чат не найден. Напишите боту /start или добавьте его в группу, затем проверьте chat id.');

        $this->actingAs($this->adminWith(['forms.view', 'forms.create']))
            ->postJson('/admin/api/forms/telegram-test', ['token' => '', 'chat_ids' => '1'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Укажите токен бота и chat id.');

        $this->actingAs($this->adminWith(['forms.view']))
            ->postJson('/admin/api/forms/telegram-test', ['token' => '1:X', 'chat_ids' => '1'])
            ->assertForbidden();
    }

    public function test_the_panel_pages_render_the_shell(): void
    {
        $admin = $this->superAdmin();

        foreach (['/admin/forms', '/admin/forms/create', '/admin/agreements'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('id="nexor-panel"', false);
        }
    }
}
