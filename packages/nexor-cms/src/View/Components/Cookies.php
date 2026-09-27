<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use Nexor\Cms\Support\Cookies as CookieSettings;

/**
 * Баннер согласия на cookie и окно с категориями.
 *
 * ```blade
 * <x-nexor::cookies />
 * ```
 *
 * Ставится один раз в макете сайта, перед `</body>`. Тексты, категории и цвета
 * задаются в панели, поэтому в шаблоне ничего настраивать не нужно. Когда
 * посетитель уже ответил или баннер выключен, компонент не выводит ничего —
 * разметку не приходится прятать стилями.
 */
class Cookies extends BaseComponent
{
    /**
     * @param  string  $template  Имя шаблона вёрстки
     */
    public function __construct(public string $template = 'default') {}

    public function shouldRender(): bool
    {
        return CookieSettings::enabled() && CookieSettings::consent() === null;
    }

    public function render(): View
    {
        $settings = CookieSettings::settings();

        return view('nexor::components.cookies.'.$this->template, [
            'settings' => $settings,
            'links' => [
                'policy' => $settings['policy_url'],
                'cookies' => $settings['cookie_policy_url'] ?: $settings['policy_url'],
            ],
            'assets' => [
                'css' => route('nexor.cookies.asset', ['file' => 'cookies.css', 'v' => $this->stamp('cookies.css')]),
                'js' => route('nexor.cookies.asset', ['file' => 'cookies.js', 'v' => $this->stamp('cookies.js')]),
            ],
        ]);
    }

    /**
     * Отпечаток файла в адресе: правка баннера доезжает до браузера сразу.
     */
    protected function stamp(string $file): string
    {
        $path = dirname(__DIR__, 3).'/resources/assets/'.$file;

        return is_file($path) ? substr(md5_file($path), 0, 10) : '1';
    }
}
