<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;
use Nexor\Cms\Support\SiteAssets;

/**
 * Стили и скрипты сайта — вместо `@vite`.
 *
 * Работает и с Vite (`npm run dev`, `public/build`), и со сборкой, залитой в
 * `storage/app/build`: на хостинге без node второй способ единственный. Нет
 * сборки вовсе — страница всё равно открывается.
 */
class Assets extends Component
{
    /**
     * @param  array<int, string>|string|null  $entries  Точки входа; по умолчанию из настроек
     */
    public function __construct(public array|string|null $entries = null) {}

    public function render(): View
    {
        return view('nexor::site.assets', [
            'tags' => SiteAssets::tags($this->entries === null ? null : (array) $this->entries),
        ]);
    }
}
