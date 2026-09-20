<?php

namespace Nexor\Cms\View\Components;

use Illuminate\View\Component as BaseComponent;
use Illuminate\View\View;
use Nexor\Cms\Support\ContentBlocks;
use Nexor\Cms\Support\InlineEditor;

/**
 * Текст, который правится прямо на странице.
 *
 * ```blade
 * <x-nexor::edit key="about.title" as="h2" class="text-4xl">О компании</x-nexor::edit>
 * <x-nexor::edit key="about.lead" as="p" html>Текст со <b>ссылками</b>…</x-nexor::edit>
 * ```
 *
 * Содержимое внутри тега — значение по умолчанию: оно живёт в шаблоне и в git.
 * База хранит только правку, сделанную на сайте; удалили правку — снова видно
 * шаблонное. Посетителю компонент отдаёт обычный тег без единого лишнего
 * атрибута, разметка редактора появляется только в режиме правки.
 */
class Edit extends BaseComponent
{
    /**
     * @param  string  $key  Ключ блока, общий для всего сайта: about.title
     * @param  string  $as  Каким тегом выводить
     * @param  bool  $html  Разрешить простое оформление: жирный, курсив, ссылки
     * @param  bool  $multiline  Разрешить переносы строк внутри блока
     */
    public function __construct(
        public string $key,
        public string $as = 'div',
        public bool $html = false,
        public bool $multiline = false,
    ) {}

    public function render(): View
    {
        $type = $this->html ? 'html' : 'text';

        return view('nexor::inline.text', [
            'tag' => preg_replace('/[^a-z0-9]/i', '', $this->as) ?: 'div',
            'stored' => ContentBlocks::get($this->key),
            'type' => $type,
            // В блоке с оформлением переносы есть всегда: там они <br>.
            'breaks' => $this->multiline || $this->html,
            'editing' => InlineEditor::active(),
            // В режиме правки блок заодно записывается в хранилище — со
            // значением из шаблона и пометкой «не правлен».
            'remember' => InlineEditor::active() ? fn (string $default) => ContentBlocks::remember($this->key, $type, $default) : null,
        ]);
    }
}
