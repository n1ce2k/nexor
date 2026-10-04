<?php

namespace Nexor\Cms\Models;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Nexor\Cms\Support\SvgSanitizer;

#[Fillable([
    'key', 'value', 'type', 'group', 'name', 'hint', 'options', 'sort', 'is_system', 'is_encrypted',
])]
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /** Value shown instead of a secret, so it never reaches the browser. */
    public const MASK = '••••••••';

    /**
     * Что принимает настройка-файл.
     *
     * Список разрешённых, а не запрещённых: файл ложится в публичное
     * хранилище, и .php, .html или .svg оттуда исполнились бы на домене сайта.
     */
    public const FILE_EXTENSIONS = 'jpg,jpeg,png,gif,webp,avif,ico,svg,pdf,doc,docx,xls,xlsx,ppt,pptx,odt,ods,rtf,txt,csv,zip,rar,7z,mp3,mp4,webm';

    /** Размер файла настройки, КБ. */
    public const FILE_MAX_KB = 10240;

    /**
     * Правила для файла настройки — одни на обе панели.
     *
     * SVG принимается, только если разбирается как SVG: при сохранении из него
     * вырезается всё исполняемое (`Uploads::handle`), а то, что вычистить
     * нельзя, сюда не проходит.
     *
     * @return array<int, mixed>
     */
    public static function fileRules(): array
    {
        return [
            'nullable', 'file', 'max:'.self::FILE_MAX_KB, 'mimes:'.self::FILE_EXTENSIONS,
            function (string $attribute, mixed $value, Closure $fail): void {
                if ($value instanceof UploadedFile
                    && strtolower($value->getClientOriginalExtension()) === 'svg'
                    && SvgSanitizer::clean((string) file_get_contents($value->getRealPath())) === null) {
                    $fail('Файл не похож на SVG — загрузите корректное изображение.');
                }
            },
        ];
    }

    /**
     * Editable value types.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            'string' => 'Строка',
            'text' => 'Текст',
            'html' => 'HTML',
            'select' => 'Список',
            'boolean' => 'Да / Нет',
            'integer' => 'Число',
            'file' => 'Файл',
            //            'password' => 'Пароль (хранится зашифрованным)',
        ];
    }

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'sort' => 'integer',
            'is_system' => 'boolean',
            'is_encrypted' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            $setting->is_encrypted = $setting->type === 'password';
        });

        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * @return array<string, mixed>
     */
    public static function all_cached(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()
            ->get()
            ->mapWithKeys(fn (self $setting) => [$setting->key => $setting->castValue()])
            ->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all_cached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        $setting = self::query()->firstOrNew(['key' => $key]);

        // Новая строка без определения — служебное значение модуля. Группа по
        // началу ключа (cookies.enabled → cookies), иначе база поставила бы
        // «general», и значение всплыло бы на экране настроек сайта.
        if (! $setting->exists) {
            $setting->group = str_contains($key, '.') ? strstr($key, '.', true) : 'general';
        }

        $setting->value = $setting->type === 'password' && filled($value)
            ? Crypt::encryptString((string) $value)
            : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);

        $setting->save();
    }

    /**
     * Значение настройки — загружаемый файл.
     *
     * Тип `image` остался от прежних версий: до миграции он встречается в
     * базе, и такие настройки должны работать как раньше.
     */
    public function isFile(): bool
    {
        return in_array($this->type, ['file', 'image'], true);
    }

    public function castValue(): mixed
    {
        if ($this->is_encrypted) {
            return $this->decrypted();
        }

        return match ($this->type) {
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $this->value,
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    /**
     * Plain text of an encrypted setting, or null when the key rotated.
     */
    public function decrypted(): ?string
    {
        if (blank($this->value)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Value safe to send to the browser: secrets become a mask.
     */
    public function publicValue(): mixed
    {
        if ($this->is_encrypted) {
            return blank($this->value) ? '' : self::MASK;
        }

        return $this->castValue();
    }

    /**
     * Настройки с определением — то, что показывает экран настроек.
     *
     * Строки без названия пишет сам код (`Setting::put()` из модулей, например
     * cookies.*): у них свой экран, а здесь они выглядели бы пустыми полями —
     * и затирались бы при сохранении.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeDefined(Builder $query): void
    {
        $query->whereNotNull('name');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('group')->orderBy('sort')->orderBy('id');
    }
}
