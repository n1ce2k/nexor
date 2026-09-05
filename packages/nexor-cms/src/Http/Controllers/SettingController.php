<?php

namespace Nexor\Cms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Nexor\Cms\Models\Setting;
use Nexor\Cms\Support\ActivityLogger;
use Nexor\Cms\Support\Uploads;

class SettingController extends Controller
{
    /** @var array<string, string> */
    protected const GROUP_LABELS = [
        'general' => 'Общие',
        'contacts' => 'Контакты',
        'seo' => 'SEO и счётчики',
    ];

    public function index(): View
    {
        $groups = Setting::query()->ordered()->get()->groupBy('group');

        return view('nexor::admin.settings.index', [
            'groups' => $groups,
            'labels' => self::GROUP_LABELS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = Setting::query()->get()->keyBy('key');

        $request->validate($this->rules($settings));

        foreach ($settings as $key => $setting) {
            $setting->value = $setting->type === 'image'
                ? Uploads::handle($request, 'file_'.$this->inputKey($key), $setting->value, 'settings')
                : $this->scalarValue($request, $setting);

            $setting->save();
        }

        ActivityLogger::log('updated', null, 'Настройки сайта');

        return back()->with('success', 'Настройки сохранены.');
    }

    protected function scalarValue(Request $request, Setting $setting): ?string
    {
        $input = $request->input('settings.'.$this->inputKey($setting->key));

        return $setting->type === 'boolean'
            ? ($request->boolean('settings.'.$this->inputKey($setting->key)) ? '1' : '0')
            : ($input === null ? null : (string) $input);
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
