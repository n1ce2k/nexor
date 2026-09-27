<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Привязка установки: домен, на котором CMS запустилась, и номер ключа.
 *
 * Запись одна на сайт. Она нужна, чтобы отличить перенос базы на другой домен
 * от обычного запуска — ключ такой перенос не поймает, если его просто убрали
 * из `.env`.
 */
#[Fillable(['install_id', 'host', 'key_serial'])]
class LicenseBinding extends Model
{
    protected $table = 'license_bindings';

    protected function casts(): array
    {
        return ['key_serial' => 'integer'];
    }
}
