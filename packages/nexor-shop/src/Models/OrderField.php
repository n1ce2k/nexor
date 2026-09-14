<?php

namespace Nexor\Shop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Nexor\Shop\Database\Factories\OrderFieldFactory;
use Nexor\Shop\Enums\OrderFieldType;

/**
 * Поле формы заказа: ФИО, телефон, e-mail, комментарий и всё, что добавят.
 */
#[Fillable(['code', 'name', 'type', 'is_required', 'is_active', 'sort'])]
class OrderField extends Model
{
    /** @use HasFactory<OrderFieldFactory> */
    use HasFactory;

    protected $table = 'shop_order_fields';

    protected static function newFactory(): Factory
    {
        return OrderFieldFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => OrderFieldType::class,
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function rules(): array
    {
        return [$this->is_required ? 'required' : 'nullable', ...$this->type->rules()];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('id');
    }
}
