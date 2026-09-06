<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Nexor\Cms\Http\Requests\SettingDefinitionRequest;
use Nexor\Cms\Http\Resources\SettingResource;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

/**
 * Site settings: both the values and the definitions themselves.
 *
 * Definitions are editable so a site can grow its own fields — a Telegram link
 * under Контакты, a delivery notice, whatever — without a migration.
 */
class SettingController extends ApiController
{
    /** @var array<string, string> */
    protected const GROUP_LABELS = [
        'general' => 'Общие',
        'contacts' => 'Контакты',
        'seo' => 'SEO и счётчики',
        'mail' => 'Почта',
    ];

    public function index(Request $request): JsonResponse
    {
        $settings = Setting::query()
            ->when($request->filled('group'), fn ($query) => $query->where('group', $request->get('group')))
            ->ordered()
            ->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
            'groups' => $this->groups($settings),
            'types' => collect(Setting::types())->map(fn ($label, $value) => compact('value', 'label'))->values(),
        ]);
    }

    /**
     * Bulk-saves values. Only the keys present in the request are touched, so a
     * screen showing one group cannot wipe the others.
     */
    public function update(Request $request): JsonResponse
    {
        $settings = Setting::query()->get()->keyBy('key');

        $request->validate($this->rules($settings));

        foreach ($settings as $key => $setting) {
            $input = $this->inputKey($key);

            if ($setting->type === 'image') {
                if ($request->hasFile('file_'.$input) || $request->boolean('file_'.$input.'_remove')) {
                    $setting->value = Uploads::handle($request, 'file_'.$input, $setting->value, Nexor::directory('settings'));
                    $setting->save();
                }

                continue;
            }

            if (! $request->has('settings.'.$input)) {
                continue;
            }

            // The mask means "unchanged" — compare before the value is encrypted,
            // or the comparison would never match.
            if ($setting->is_encrypted && $request->input('settings.'.$input) === Setting::MASK) {
                continue;
            }

            $setting->value = $this->scalarValue($request, $setting, $input);
            $setting->save();
        }

        ActivityLogger::log('updated', null, 'Настройки сайта');

        return $this->ok('Настройки сохранены.');
    }

    public function store(SettingDefinitionRequest $request): JsonResponse
    {
        $setting = new Setting($request->safe()->except('options'));
        $setting->options = $request->input('options') ?: null;
        $setting->is_system = false;
        $setting->value = $request->input('type') === 'boolean' ? '0' : '';
        $setting->save();

        ActivityLogger::created($setting, 'Настройка: '.$setting->key);

        return SettingResource::make($setting)
            ->additional(['message' => 'Настройка «'.$setting->name.'» добавлена.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateDefinition(SettingDefinitionRequest $request, Setting $setting): JsonResponse
    {
        $setting->fill($request->safe()->except(['options', 'key']));
        $setting->options = $request->input('options') ?: null;

        // A system setting keeps its key: code elsewhere reads it by name.
        if (! $setting->is_system) {
            $setting->key = $request->input('key');
        }

        $setting->save();

        ActivityLogger::updated($setting, 'Настройка: '.$setting->key);

        return SettingResource::make($setting)
            ->additional(['message' => 'Настройка «'.$setting->name.'» сохранена.'])
            ->response();
    }

    public function destroy(Setting $setting): JsonResponse
    {
        if ($setting->is_system) {
            return $this->refuse('Системную настройку удалить нельзя — её читает код CMS.');
        }

        ActivityLogger::deleted($setting, 'Настройка: '.$setting->key);
        Uploads::delete($setting->type === 'image' ? $setting->value : null);
        $setting->delete();

        return $this->ok('Настройка удалена.');
    }

    /**
     * @param  Collection<int, Setting>  $settings
     * @return array<int, array{key: string, label: string}>
     */
    protected function groups(Collection $settings): array
    {
        return $settings
            ->pluck('group')
            ->unique()
            ->values()
            ->map(fn (string $group) => [
                'key' => $group,
                'label' => self::GROUP_LABELS[$group] ?? $group,
            ])
            ->all();
    }

    protected function scalarValue(Request $request, Setting $setting, string $input): ?string
    {
        $key = 'settings.'.$input;

        if ($setting->type === 'boolean') {
            return $request->boolean($key) ? '1' : '0';
        }

        $value = $request->input($key);

        if ($setting->is_encrypted) {
            return filled($value) ? Crypt::encryptString((string) $value) : '';
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * Dots in setting keys would nest the request input, so forms use underscores.
     */
    protected function inputKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /**
     * @param  Collection<string, Setting>  $settings
     * @return array<string, mixed>
     */
    protected function rules(Collection $settings): array
    {
        $rules = [];

        foreach ($settings as $key => $setting) {
            $input = $this->inputKey($key);

            $rules[$setting->type === 'image' ? 'file_'.$input : 'settings.'.$input] = match ($setting->type) {
                'image' => ['nullable', 'image', 'max:4096'],
                'boolean' => ['nullable'],
                'integer' => ['nullable', 'integer'],
                'text', 'html' => ['nullable', 'string', 'max:65535'],
                default => ['nullable', 'string', 'max:2000'],
            };
        }

        return $rules;
    }
}
