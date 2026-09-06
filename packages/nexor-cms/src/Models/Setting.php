<?php

namespace Nexor\Cms\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'key', 'value', 'type', 'group', 'name', 'hint', 'options', 'sort', 'is_system', 'is_encrypted',
])]
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /** Value shown instead of a secret, so it never reaches the browser. */
    public const MASK = '••••••••';

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
            'image' => 'Изображение',
            'password' => 'Пароль (хранится зашифрованным)',
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

        $setting->value = $setting->type === 'password' && filled($value)
            ? Crypt::encryptString((string) $value)
            : (is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value);

        $setting->save();
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
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('group')->orderBy('sort')->orderBy('id');
    }
}
