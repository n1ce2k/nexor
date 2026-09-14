<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexor\Shop\Database\Factories\PromocodeFactory;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Enums\PromocodeType;

#[Fillable([
    'code', 'description', 'type', 'value', 'min_sum',
    'starts_at', 'ends_at', 'usage_limit', 'scope', 'section_ids', 'element_ids', 'is_active',
])]
class Promocode extends Model
{
    /** @use HasFactory<PromocodeFactory> */
    use HasFactory;

    protected $table = 'shop_promocodes';

    protected static function newFactory(): Factory
    {
        return PromocodeFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => PromocodeType::class,
            'scope' => PromocodeScope::class,
            'value' => 'decimal:2',
            'min_sum' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'used_count' => 'integer',
            'section_ids' => 'array',
            'element_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Код вводят как попало — сравниваем без регистра и пробелов.
     */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public static function findByCode(string $code): ?self
    {
        return self::query()->where('code', self::normalize($code))->first();
    }

    protected function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = self::normalize($value);
    }

    public function isExhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }
}
