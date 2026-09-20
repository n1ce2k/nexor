<?php

namespace Nexor\Cms\View\Components\Edit;

use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use Nexor\Cms\Support\ContentBlocks;
use Nexor\Cms\Support\InlineEditor;
use Nexor\Cms\Support\Uploads;

/**
 * Картинка, которую меняют прямо на странице.
 *
 * ```blade
 * <x-nexor::edit.image key="about.photo" src="{{ asset('assets/img/about.jpg') }}"
 *                      alt="О компании" class="rounded-2xl" />
 * ```
 *
 * `src` — картинка по умолчанию из вёрстки. Заменили на сайте — адрес берётся
 * из загруженного файла; убрали правку — снова показывается исходная.
 */
class Image extends BaseComponent
{
    /**
     * @param  string  $key  Ключ блока, общий для всего сайта: about.photo
     * @param  string|null  $src  Картинка по умолчанию
     * @param  string  $alt  Подпись для доступности
     */
    public function __construct(
        public string $key,
        public ?string $src = null,
        public string $alt = '',
    ) {}

    public function render(): View
    {
        $stored = ContentBlocks::get($this->key);

        if (InlineEditor::active()) {
            ContentBlocks::remember($this->key, 'image', (string) $this->src);
        }

        return view('nexor::inline.image', [
            'url' => $stored ? Uploads::url($stored) : $this->src,
            'editing' => InlineEditor::active(),
        ]);
    }
}
