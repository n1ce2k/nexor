<?php

namespace Nexor\Cms\Support;

use Illuminate\Support\Facades\Gate;

/**
 * Режим правки блоков прямо на странице сайта.
 *
 * Включается только для того, кто вошёл в панель и вправе менять блоки, и
 * живёт в сессии: гость и обычный посетитель не получают ни разметки редактора,
 * ни его скрипта — страница остаётся ровно такой, как была.
 */
class InlineEditor
{
    /** Ключ в сессии и имя параметра в адресе: ?nexor-edit=1 включает, 0 выключает. */
    public const KEY = 'nexor.inline-edit';

    /** Право, без которого править нечего. */
    public const PERMISSION = 'content.blocks.update';

    /**
     * Показывать ли обводку и слушать ли правки на этой странице.
     */
    public static function active(): bool
    {
        return self::allowed() && (bool) session(self::KEY, false);
    }

    /**
     * Может ли текущий пользователь править блоки вообще.
     */
    public static function allowed(): bool
    {
        return auth()->check() && Gate::allows(self::PERMISSION);
    }

    /**
     * Текст, который дойдёт до страницы.
     *
     * Правка приходит из браузера, поэтому в HTML остаются только оформление
     * строки и ссылки: ни скриптов, ни стилей, ни атрибутов вроде onclick.
     */
    public static function clean(string $value, string $type): string
    {
        $value = trim($value);

        if ($type !== 'html') {
            return strip_tags($value);
        }

        $value = strip_tags($value, '<b><strong><i><em><u><s><br><span><a>');

        // Ссылкам оставляем адрес и цель, всё остальное (включая on*) убираем.
        return (string) preg_replace_callback('#<a\b([^>]*)>#i', function (array $match): string {
            preg_match('#href\s*=\s*([\'"])(.*?)\1#i', $match[1], $href);

            $url = trim($href[2] ?? '');
            $safe = $url !== '' && preg_match('#^(https?:|mailto:|tel:|/|\#)#i', $url);

            return $safe ? '<a href="'.e($url).'">' : '<a>';
        }, $value);
    }
}
