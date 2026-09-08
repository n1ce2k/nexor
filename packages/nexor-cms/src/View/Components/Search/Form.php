<?php

namespace Nexor\Cms\View\Components\Search;

use Illuminate\View\View;
use Nexor\Cms\View\Components\Component;

/**
 * Строка поиска — аналог `search.form`.
 *
 * ```blade
 * <x-nexor::search.form />
 * <x-nexor::search.form action="/katalog" placeholder="Поиск по каталогу" />
 * ```
 *
 * Обычная форма методом GET: запрос виден в адресе, работает «назад», страница
 * результатов индексируется.
 */
class Form extends Component
{
    /**
     * @param  string  $template  Имя шаблона вёрстки
     * @param  string|null  $action  Куда отправлять; по умолчанию /search
     * @param  string  $placeholder  Подсказка в поле
     * @param  string  $button  Подпись кнопки
     * @param  string|null  $iblock  Искать только в этом инфоблоке
     */
    public function __construct(
        public string $template = 'default',
        public ?string $action = null,
        public string $placeholder = 'Поиск по сайту',
        public string $button = 'Найти',
        public ?string $iblock = null,
    ) {}

    public function render(): View
    {
        return $this->template($this->template, [
            'formAction' => $this->action ?? url('/search'),
            'query' => (string) request()->query('q', ''),
            'scope' => $this->iblock,
        ]);
    }

    protected function component(): string
    {
        return 'search.form';
    }
}
