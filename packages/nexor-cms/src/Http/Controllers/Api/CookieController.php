<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Nexor\Cms\Models\CookieConsent;
use Nexor\Cms\Models\CookieCounter;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Cookies;

/**
 * Раздел «Cookie»: настройки баннера, счётчики и журнал согласий.
 */
class CookieController extends ApiController
{
    public function show(): JsonResponse
    {
        return response()->json($this->payload());
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['boolean'],
            'js_mode' => ['boolean'],
            'delay' => ['integer', 'min:0', 'max:60000'],
            'cookie_name' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'lifetime' => ['integer', 'min:1', 'max:3650'],
            'keep_months' => ['integer', 'min:0', 'max:120'],
            'policy_url' => ['nullable', 'string', 'max:500'],
            'cookie_policy_url' => ['nullable', 'string', 'max:500'],
            'accept_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accept_text_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'link_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'metrika' => ['nullable', 'string', 'max:20'],

            'banner_title' => ['required', 'string', 'max:255'],
            'banner_text' => ['required', 'string', 'max:2000'],
            'banner_accept' => ['required', 'string', 'max:100'],
            'banner_decline' => ['nullable', 'string', 'max:100'],
            'banner_settings' => ['nullable', 'string', 'max:100'],

            'modal_title' => ['required', 'string', 'max:255'],
            'modal_text' => ['nullable', 'string', 'max:2000'],
            'modal_subtitle' => ['nullable', 'string', 'max:255'],
            'modal_accept_all' => ['required', 'string', 'max:100'],
            'modal_accept_chosen' => ['required', 'string', 'max:100'],
            'modal_decline' => ['required', 'string', 'max:100'],

            'technical_title' => ['required', 'string', 'max:255'],
            'technical_text' => ['nullable', 'string', 'max:2000'],

            'analytics_enabled' => ['boolean'],
            'analytics_checked' => ['boolean'],
            'analytics_title' => ['required', 'string', 'max:255'],
            'analytics_text' => ['nullable', 'string', 'max:2000'],

            'marketing_enabled' => ['boolean'],
            'marketing_checked' => ['boolean'],
            'marketing_title' => ['required', 'string', 'max:255'],
            'marketing_text' => ['nullable', 'string', 'max:2000'],
        ], [], $this->attributes());

        Cookies::save($data);
        ActivityLogger::log('update', null, 'Настройки cookie');

        return response()->json($this->payload() + ['message' => 'Настройки cookie сохранены.']);
    }

    // ------------------------------------------------------------ счётчики

    public function storeCounter(Request $request): JsonResponse
    {
        $counter = CookieCounter::query()->create($this->counterData($request));

        ActivityLogger::created($counter, 'Счётчик «'.$counter->name.'»');

        return response()->json(['data' => $counter, 'message' => 'Счётчик «'.$counter->name.'» добавлен.'], 201);
    }

    public function updateCounter(Request $request, CookieCounter $counter): JsonResponse
    {
        $counter->update($this->counterData($request));

        ActivityLogger::updated($counter, 'Счётчик «'.$counter->name.'»');

        return response()->json(['data' => $counter, 'message' => 'Счётчик «'.$counter->name.'» сохранён.']);
    }

    public function destroyCounter(CookieCounter $counter): JsonResponse
    {
        ActivityLogger::deleted($counter, 'Счётчик «'.$counter->name.'»');
        $counter->delete();

        return $this->ok('Счётчик удалён.');
    }

    // -------------------------------------------------------------- журнал

    public function consents(Request $request): JsonResponse
    {
        $consents = CookieConsent::query()
            ->latest('id')
            ->paginate($this->perPage($request))
            ->through(fn (CookieConsent $consent) => [
                'id' => $consent->id,
                'preferences' => $consent->preferences,
                'summary' => $consent->summary(),
                'ip' => $consent->ip,
                'user_agent' => $consent->user_agent,
                'created_at' => $consent->created_at?->toIso8601String(),
            ]);

        return response()->json($consents->toArray());
    }

    /**
     * Чистка журнала руками — то же, что делает команда по расписанию.
     */
    public function prune(Request $request): JsonResponse
    {
        $months = (int) ($request->integer('months') ?: Cookies::get('keep_months'));

        if ($months < 1) {
            return $this->refuse('Задайте срок хранения — иначе удалять нечего.');
        }

        $deleted = CookieConsent::query()->where('created_at', '<', now()->subMonths($months))->delete();

        return $this->ok('Удалено записей: '.$deleted.'.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(): array
    {
        return [
            'settings' => Cookies::settings(),
            'counters' => CookieCounter::query()->ordered()->get(),
            'categories' => CookieCounter::CATEGORIES,
            'placements' => CookieCounter::PLACEMENTS,
            'consents_count' => CookieConsent::query()->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function counterData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(CookieCounter::CATEGORIES))],
            'placement' => ['required', Rule::in(array_keys(CookieCounter::PLACEMENTS))],
            // Код уходит в страницу как есть, поэтому право на этот экран —
            // это право положить скрипт на весь сайт.
            'code' => ['required', 'string', 'max:20000'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999999'],
        ], [], ['name' => 'название', 'code' => 'код', 'category' => 'категория', 'placement' => 'место']);
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return [
            'cookie_name' => 'имя cookie',
            'lifetime' => 'срок хранения согласия',
            'delay' => 'задержка показа',
            'keep_months' => 'срок хранения журнала',
            'banner_title' => 'заголовок баннера',
            'banner_text' => 'текст баннера',
            'modal_title' => 'заголовок окна',
            'metrika' => 'номер счётчика Метрики',
        ];
    }
}
