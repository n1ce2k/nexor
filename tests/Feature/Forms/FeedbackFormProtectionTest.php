<?php

namespace Tests\Feature\Forms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Nexor\Cms\Livewire\FeedbackForm as FeedbackFormComponent;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Models\FeedbackSubmission;
use Nexor\Cms\Support\Captcha\GoogleRecaptcha;
use Nexor\Cms\Support\Captcha\YandexSmartCaptcha;
use Nexor\Cms\Support\FormCaptcha;
use Tests\TestCase;

/**
 * Вкладка «Защита»: Yandex SmartCaptcha, Google reCAPTCHA и Nexor Captcha на сайте.
 */
class FeedbackFormProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $protection  Как приходит из панели
     * @param  array<string, mixed>  $attributes
     */
    protected function makeForm(array $protection, array $attributes = []): FeedbackForm
    {
        $form = FeedbackForm::factory()->create([
            ...$attributes,
            'protection' => FormCaptcha::fromInput($protection, null),
        ]);

        FeedbackFormField::factory()->for($form, 'form')->create(['code' => 'name', 'label' => 'Имя', 'type' => 'string', 'is_required' => true]);

        return $form->fresh();
    }

    protected function yandexForm(array $options = [], array $attributes = []): FeedbackForm
    {
        return $this->makeForm([
            'captcha' => 'yandex',
            'yandex' => ['client_key' => 'ysc1_client', 'server_key' => 'ysc2_secret', ...$options],
        ], $attributes);
    }

    protected function nexorForm(array $attributes = []): FeedbackForm
    {
        return $this->makeForm(['captcha' => 'nexor', 'nexor' => ['length' => 5, 'chars' => 'digits']], $attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submitForm(FeedbackForm $form, array $data = []): TestResponse
    {
        return $this->from('/contacts')->post('/nexor/form', [
            '_form' => Crypt::encrypt(['name' => 'form-'.$form->id, 'form_id' => $form->id]),
            'fields' => ['name' => 'Андрей'],
            ...$data,
        ]);
    }

    protected function code(string $id): string
    {
        return Cache::get('nexor-captcha:'.$id)['code'];
    }

    public function test_a_form_without_a_captcha_needs_no_token(): void
    {
        $form = $this->makeForm(['captcha' => 'none']);

        $this->submitForm($form)->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('data-nexor-captcha', Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]));
    }

    public function test_the_yandex_widget_gets_only_public_settings(): void
    {
        $form = $this->yandexForm(['invisible' => true, 'hide_shield' => true, 'webview' => true]);

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertStringContainsString('data-provider="yandex"', $html);
        $this->assertStringContainsString('name="captcha_token"', $html);
        $this->assertStringContainsString('smartcaptcha.cloud.yandex.ru/captcha.js', $html);
        $this->assertStringContainsString('&quot;key&quot;:&quot;ysc1_client&quot;', $html);
        $this->assertStringContainsString('&quot;hideShield&quot;:true', $html);
        $this->assertStringContainsString('&quot;webview&quot;:true', $html);
        $this->assertStringNotContainsString('ysc2_secret', $html);
    }

    public function test_the_shield_is_only_hidden_for_the_invisible_captcha(): void
    {
        $form = $this->yandexForm(['invisible' => false, 'hide_shield' => true]);

        $this->assertStringContainsString('&quot;hideShield&quot;:false', Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]));
    }

    public function test_a_yandex_token_is_checked_with_the_server_key(): void
    {
        Http::fake([YandexSmartCaptcha::VALIDATE_URL => Http::response(['status' => 'ok'])]);
        $form = $this->yandexForm();

        $this->submitForm($form, ['captcha_token' => 'token-123'])->assertSessionHasNoErrors();

        $this->assertSame(1, FeedbackSubmission::query()->count());
        Http::assertSent(fn (Request $request) => $request->url() === YandexSmartCaptcha::VALIDATE_URL
            && $request['secret'] === 'ysc2_secret'
            && $request['token'] === 'token-123'
            && $request['ip'] === '127.0.0.1');
    }

    public function test_a_missing_or_rejected_yandex_token_stops_the_submission(): void
    {
        Http::fake([YandexSmartCaptcha::VALIDATE_URL => Http::response(['status' => 'failed', 'message' => 'Token invalid or expired.'])]);
        $form = $this->yandexForm();

        $this->submitForm($form)->assertSessionHasErrors(['captcha' => 'Подтвердите, что вы не робот.']);
        $this->submitForm($form, ['captcha_token' => 'bad'])->assertSessionHasErrors('captcha');

        $this->assertSame(0, FeedbackSubmission::query()->count());
        Mail::assertNothingSent();
    }

    public function test_the_captcha_is_checked_only_after_the_fields(): void
    {
        $form = $this->yandexForm();

        // Ошибка в поле: к Яндексу не ходим, токен остаётся годным.
        $this->submitForm($form, ['fields' => ['name' => ''], 'captcha_token' => 'token-123'])
            ->assertSessionHasErrors('fields.name')
            ->assertSessionDoesntHaveErrors('captcha');

        Http::assertNothingSent();
    }

    public function test_an_unreachable_yandex_service_lets_the_submission_through(): void
    {
        Http::fake([YandexSmartCaptcha::VALIDATE_URL => Http::response('Bad gateway', 502)]);
        $form = $this->yandexForm();

        $this->submitForm($form, ['captcha_token' => 'token-123'])->assertSessionHasNoErrors();

        $this->assertSame(1, FeedbackSubmission::query()->count());
    }

    public function test_google_v2_checks_the_response(): void
    {
        Http::fake([GoogleRecaptcha::VERIFY_URL => Http::sequence()
            ->push(['success' => true])
            ->push(['success' => false, 'error-codes' => ['invalid-input-response']])]);

        $form = $this->makeForm(['captcha' => 'google', 'google' => ['site_key' => 'site', 'secret_key' => 'secret', 'version' => 'v2']]);

        $this->submitForm($form, ['captcha_token' => 'good'])->assertSessionHasNoErrors();
        $this->submitForm($form, ['captcha_token' => 'bad'])->assertSessionHasErrors('captcha');

        $this->assertSame(1, FeedbackSubmission::query()->count());
        Http::assertSent(fn (Request $request) => $request['secret'] === 'secret' && $request['response'] === 'good');
    }

    public function test_google_v3_rejects_a_low_score(): void
    {
        Http::fake([GoogleRecaptcha::VERIFY_URL => Http::sequence()
            ->push(['success' => true, 'score' => 0.9, 'action' => GoogleRecaptcha::ACTION])
            ->push(['success' => true, 'score' => 0.3, 'action' => GoogleRecaptcha::ACTION])
            ->push(['success' => true, 'score' => 0.9, 'action' => 'login'])]);

        $form = $this->makeForm(['captcha' => 'google', 'google' => ['site_key' => 'site', 'secret_key' => 'secret', 'version' => 'v3', 'min_score' => 0.5]]);

        $this->submitForm($form, ['captcha_token' => 'human'])->assertSessionHasNoErrors();
        $this->submitForm($form, ['captcha_token' => 'bot'])->assertSessionHasErrors('captcha');
        $this->submitForm($form, ['captcha_token' => 'other-action'])->assertSessionHasErrors('captcha');

        $this->assertStringContainsString('&quot;version&quot;:&quot;v3&quot;', Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]));
    }

    public function test_the_nexor_captcha_is_rendered_with_an_image(): void
    {
        $form = $this->nexorForm();

        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);

        $this->assertMatchesRegularExpression('/name="captcha_id" value="([A-Za-z0-9]{40})"/', $html);
        $this->assertStringContainsString('name="captcha_answer"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('/nexor/captcha/', $html);
    }

    public function test_the_nexor_captcha_image_is_served(): void
    {
        $form = $this->nexorForm();
        $html = Blade::render('<x-nexor::form :id="$id" />', ['id' => $form->id]);
        preg_match('/name="captcha_id" value="([A-Za-z0-9]{40})"/', $html, $match);

        $this->get('/nexor/captcha/'.$match[1])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

        $this->get('/nexor/captcha/'.str_repeat('a', 40))->assertNotFound();
        $this->assertMatchesRegularExpression('/^\d{5}$/', $this->code($match[1]));
    }

    public function test_a_right_nexor_answer_passes_once(): void
    {
        $form = $this->nexorForm();
        $id = FormCaptcha::challenge($form);
        $answer = $this->code($id);

        $this->submitForm($form, ['captcha_id' => $id, 'captcha_answer' => ' '.$answer.' '])->assertSessionHasNoErrors();

        // Второй раз тот же код не годится.
        $this->submitForm($form, ['captcha_id' => $id, 'captcha_answer' => $answer])
            ->assertSessionHasErrors(['captcha' => 'Код устарел — введите новый код с картинки.']);

        $this->assertSame(1, FeedbackSubmission::query()->count());
    }

    public function test_a_wrong_nexor_answer_burns_the_challenge(): void
    {
        $form = $this->makeForm(['captcha' => 'nexor', 'nexor' => ['chars' => 'mixed']]);
        $id = FormCaptcha::challenge($form);
        $answer = $this->code($id);

        $this->submitForm($form, ['captcha_id' => $id, 'captcha_answer' => 'nope'])->assertSessionHasErrors('captcha');
        $this->submitForm($form, ['captcha_id' => $id, 'captcha_answer' => mb_strtolower($answer)])->assertSessionHasErrors('captcha');
        $this->submitForm($form)->assertSessionHasErrors(['captcha' => 'Введите код с картинки.']);

        $this->assertSame(0, FeedbackSubmission::query()->count());
    }

    public function test_the_answer_is_case_insensitive(): void
    {
        $form = $this->makeForm(['captcha' => 'nexor', 'nexor' => ['chars' => 'mixed']]);
        $id = FormCaptcha::challenge($form);

        $this->submitForm($form, ['captcha_id' => $id, 'captcha_answer' => mb_strtolower($this->code($id))])->assertSessionHasNoErrors();
    }

    public function test_a_fresh_nexor_challenge_can_be_requested(): void
    {
        $form = $this->nexorForm();

        $response = $this->getJson('/nexor/captcha?form='.$form->id)->assertOk();

        $this->assertNotNull(Cache::get('nexor-captcha:'.$response->json('id')));
        $this->assertStringEndsWith('/nexor/captcha/'.$response->json('id'), $response->json('image'));

        $other = $this->yandexForm();
        $this->getJson('/nexor/captcha?form='.$other->id)->assertNotFound();
        $this->getJson('/nexor/captcha?form=999')->assertNotFound();
    }

    public function test_the_livewire_form_checks_the_nexor_captcha(): void
    {
        $form = $this->nexorForm(['ajax' => true]);

        $component = Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])
            ->assertSeeHtml('wire:model="captchaAnswer"')
            ->assertSeeHtml('wire:click="refreshCaptcha"');

        $first = $component->get('captchaId');

        $component->set('fields.name', 'Андрей')
            ->set('captchaAnswer', '00000')
            ->call('submit')
            ->assertHasErrors('captcha')
            ->assertDispatched('nexor-captcha-reset')
            ->assertSet('sent', false);

        $second = $component->get('captchaId');
        $this->assertNotSame($first, $second);

        $component->set('captchaAnswer', $this->code($second))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        $this->assertSame(1, FeedbackSubmission::query()->count());
    }

    public function test_the_livewire_captcha_can_be_refreshed(): void
    {
        $form = $this->nexorForm(['ajax' => true]);

        $component = Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id]);
        $first = $component->get('captchaId');

        $component->set('captchaAnswer', '123')->call('refreshCaptcha')->assertSet('captchaAnswer', '');

        $this->assertNotSame($first, $component->get('captchaId'));
    }

    public function test_the_livewire_form_checks_the_yandex_token(): void
    {
        Http::fake([YandexSmartCaptcha::VALIDATE_URL => Http::response(['status' => 'ok'])]);
        $form = $this->yandexForm([], ['ajax' => true]);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])
            ->assertSeeHtml('wire:model="captchaToken"')
            ->set('fields.name', 'Андрей')
            ->call('submit')
            ->assertHasErrors('captcha')
            ->set('captchaToken', 'token-123')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('sent', true);

        Http::assertSent(fn (Request $request) => $request['token'] === 'token-123');
    }

    public function test_the_captcha_id_of_the_livewire_form_is_locked(): void
    {
        $form = $this->nexorForm(['ajax' => true]);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(FeedbackFormComponent::class, ['formId' => $form->id])->set('captchaId', str_repeat('a', 40));
    }
}
