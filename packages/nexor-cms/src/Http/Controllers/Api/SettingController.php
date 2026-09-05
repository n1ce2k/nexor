<?php

namespace Nexor\Cms\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Nexor\Cms\Http\Resources\SettingResource;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Nexor;
use Nexor\Cms\Support\Uploads;

class SettingController extends ApiController
{
    /** @var array<string, string> */
    protected const GROUP_LABELS = [
        'general' => 'Общие',
        'contacts' => 'Контакты',
        'seo' => 'SEO и счётчики',
    ];

    public function index(): JsonResponse
    {
        $settings = Setting::query()->ordered()->get();

        return response()->json([
            'data' => SettingResource::collection($settings),
            'groups' => $settings->pluck('group')->unique()->values()->map(fn (string $group) => [
                'key' => $group,
                'label' => self::GROUP_LABELS[$group] ?? $group,
            ]),
            'urls' => $settings->where('type', 'image')->mapWithKeys(fn (Setting $setting) => [
                $setting->key => Uploads::url($setting->value),
            ]),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $settings = Setting::query()->get()->keyBy('key');

        $request->validate($this->rules($settings));

        foreach ($settings as $key => $setting) {
            $setting->value = $setting->type === 'image'
                ? Uploads::handle($request, 'file_'.$this->inputKey($key), $setting->value, Nexor::directory('settings'))
                : $this->scalarValue($request, $setting);

            $setting->save();
        }

        ActivityLogger::log('updated', null, 'Настройки сайта');

        return $this->ok('Настройки сохранены.');
    }

    protected function scalarValue(Request $request, Setting $setting): ?string
    {
        $key = 'settings.'.$this->inputKey($setting->key);

        if (! $request->has($key)) {
            return $setting->value;
        }

        return $setting->type === 'boolean'
            ? ($request->boolean($key) ? '1' : '0')
            : ($request->input($key) === null ? null : (string) $request->input($key));
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
                'text' => ['nullable', 'string', 'max:65535'],
                default => ['nullable', 'string', 'max:1000'],
            };
        }

        return $rules;
    }
}
