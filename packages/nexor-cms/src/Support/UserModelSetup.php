<?php

namespace Nexor\Cms\Support;

use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Models\Concerns\HasRoles;
use Nexor\Cms\Models\Concerns\HasUserFields;
use ReflectionClass;

/**
 * Подготовка модели пользователя приложения к работе с CMS.
 *
 * Модель остаётся в приложении, но панели нужны от неё контракт, трейты ролей
 * и своих полей, колонки CMS в списке заполняемых и их приведения типов.
 * `nexor:install` дописывает это сам: иначе установка падала на первой же
 * учётной записи, а панель получала дату строкой.
 */
class UserModelSetup
{
    /** Колонки, которые CMS добавляет пользователю и заполняет из своих форм. */
    public const COLUMNS = ['login', 'phone', 'avatar', 'is_active', 'is_super_admin'];

    /** Приведения типов для колонок CMS: без них панель получает строки. */
    public const CASTS = [
        'is_active' => 'boolean',
        'is_super_admin' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    /**
     * Чего модели не хватает: contract, roles, fields, колонки и приведения.
     *
     * @return array<int, string>
     */
    public static function missing(?string $class = null): array
    {
        $class ??= Nexor::userModel();

        if (! class_exists($class)) {
            return ['class'];
        }

        $missing = [];
        $model = new $class;
        $traits = class_uses_recursive($class);

        if (! $model instanceof NexorUser) {
            $missing[] = 'contract';
        }

        if (! in_array(HasRoles::class, $traits, true)) {
            $missing[] = 'roles';
        }

        if (! in_array(HasUserFields::class, $traits, true)) {
            $missing[] = 'fields';
        }

        foreach (self::COLUMNS as $column) {
            if (! $model->isFillable($column)) {
                $missing[] = 'fillable:'.$column;
            }
        }

        foreach (array_keys(self::CASTS) as $attribute) {
            if (! $model->hasCast($attribute)) {
                $missing[] = 'cast:'.$attribute;
            }
        }

        return $missing;
    }

    /**
     * Файл модели, если его вообще можно править.
     */
    public static function file(?string $class = null): ?string
    {
        $class ??= Nexor::userModel();

        if (! class_exists($class)) {
            return null;
        }

        $file = (new ReflectionClass($class))->getFileName();

        return $file && is_writable($file) ? $file : null;
    }

    /**
     * Дописывает модель на месте. false — разметка непривычная, правьте руками.
     */
    public static function patch(string $file): bool
    {
        $source = (string) file_get_contents($file);
        $patched = self::apply($source);

        if ($patched === null) {
            return false;
        }

        return $patched === $source || file_put_contents($file, $patched) !== false;
    }

    /**
     * Тот же разбор, но без записи — так его проверяют тесты.
     */
    public static function apply(string $source): ?string
    {
        $result = self::addImports($source);
        $result = self::addContract($result);
        $result = self::addTraits($result);
        $result = self::addFillable($result);

        return self::addCasts($result);
    }

    /**
     * Перевод строки файла: на Windows модель часто лежит с CRLF, и дописанные
     * строки должны быть такими же, иначе разметка поедет.
     */
    protected static function eol(string $source): string
    {
        return str_contains($source, "\r\n") ? "\r\n" : "\n";
    }

    protected static function addImports(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $imports = [
            'use '.NexorUser::class.';',
            'use '.HasRoles::class.';',
            'use '.HasUserFields::class.';',
        ];

        preg_match_all('/^use .+;\r?$/m', $source, $existing);

        if ($existing[0] === []) {
            return null;
        }

        $lines = array_map(fn (string $line) => rtrim($line, "\r"), $existing[0]);
        $lines = array_merge($lines, array_filter($imports, fn (string $line) => ! str_contains($source, $line)));
        sort($lines);

        // Блок use-строк заменяется целиком, чтобы они остались по алфавиту.
        $first = $existing[0][0];
        $last = $existing[0][count($existing[0]) - 1];
        $start = strpos($source, $first);
        $end = strrpos($source, $last) + strlen($last);

        return substr($source, 0, $start).implode(self::eol($source), $lines).substr($source, $end);
    }

    protected static function addContract(?string $source): ?string
    {
        if ($source === null || preg_match('/implements[^{\r\n]*NexorUser/', $source) === 1) {
            return $source;
        }

        if (preg_match('/^class\s+\w+\s+extends\s+[\w\\\\]+(\s+implements\s+[^{\r\n]+)?/m', $source, $match) !== 1) {
            return null;
        }

        $replacement = isset($match[1])
            ? rtrim($match[0]).', NexorUser'
            : $match[0].' implements NexorUser';

        return str_replace($match[0], $replacement, $source);
    }

    protected static function addTraits(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        // Первая use-строка внутри класса — список трейтов; выше идут импорты файла.
        if (preg_match('/^class\s+\w+[^{]*\{/m', $source, $class, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $body = $class[0][1] + strlen($class[0][0]);

        if (preg_match('/\n([ \t]*)use ([A-Za-z0-9_][A-Za-z0-9_, ]*);/', $source, $match, PREG_OFFSET_CAPTURE, $body) !== 1) {
            return null;
        }

        $traits = array_map('trim', explode(',', $match[2][0]));
        $traits = array_unique(array_merge($traits, ['HasRoles', 'HasUserFields']));
        sort($traits);

        $line = "\n".$match[1][0].'use '.implode(', ', $traits).';';

        return substr_replace($source, $line, $match[0][1], strlen($match[0][0]));
    }

    protected static function addFillable(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        // Laravel 13: атрибут #[Fillable([...])]; раньше — свойство $fillable.
        $pattern = preg_match('/#\[Fillable\(\[(.*?)\]\)\]/s', $source) === 1
            ? '/#\[Fillable\(\[(.*?)\]\)\]/s'
            : '/protected \$fillable = \[(.*?)\];/s';

        if (preg_match($pattern, $source, $match) !== 1) {
            return null;
        }

        preg_match_all("/'([^']+)'/", $match[1], $found);
        $columns = $found[1];

        foreach (self::COLUMNS as $column) {
            if (! in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        $list = "'".implode("', '", $columns)."'";

        $replacement = str_starts_with($match[0], '#[')
            ? '#[Fillable(['.$list.'])]'
            : 'protected $fillable = ['.$list.'];';

        return str_replace($match[0], $replacement, $source);
    }

    /**
     * Приведения типов дописываются в метод `casts()` или в свойство `$casts`.
     */
    protected static function addCasts(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $patterns = [
            '/(function casts\(\)[^{]*\{\s*return \[)(.*?)(\];)/s',
            '/(protected \$casts = \[)(.*?)(\];)/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source, $match) !== 1) {
                continue;
            }

            $eol = self::eol($source);
            $added = '';

            foreach (self::CASTS as $attribute => $cast) {
                if (! str_contains($match[2], "'".$attribute."'")) {
                    $added .= $eol."            '".$attribute."' => '".$cast."',";
                }
            }

            return $added === ''
                ? $source
                : str_replace($match[0], $match[1].rtrim($match[2], " \r\n").$added.$eol.'        '.$match[3], $source);
        }

        // Метода casts() нет — дописывать его в чужую разметку не станем.
        return $source;
    }

    /**
     * Что дописать руками, если разметка модели непривычная.
     */
    public static function snippet(): string
    {
        return <<<'PHP'
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Models\Concerns\HasRoles;
use Nexor\Cms\Models\Concerns\HasUserFields;

class User extends Authenticatable implements NexorUser
{
    use HasRoles, HasUserFields;

    // колонки CMS в списке заполняемых:
    // 'login', 'phone', 'avatar', 'is_active', 'is_super_admin'

    // и их приведения типов в casts():
    // 'is_active' => 'boolean', 'is_super_admin' => 'boolean', 'last_login_at' => 'datetime'
}
PHP;
    }
}
