<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Что сайт решил про установленный модуль: включён ли и с какими настройками.
 *
 * Сам модуль — это код пакета; здесь только его состояние. Строки нет —
 * значит, модуль ни разу не трогали, и он считается включённым.
 */
#[Fillable(['code', 'is_enabled', 'settings'])]
class ModuleState extends Model
{
    protected $table = 'modules';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'settings' => 'array',
        ];
    }
}
