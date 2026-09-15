<?php

namespace Nexor\PageBuilder\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Nexor\PageBuilder\Enums\Surface;

/**
 * Раскладка из блоков одного владельца на одной поверхности.
 *
 * `content` — документ `{ version, blocks: [{ id, type, data }] }`, уже
 * проверенный и очищенный при сохранении.
 *
 * @property array{version: int, blocks: array<int, array{id: string, type: string, data: array<string, mixed>}>} $content
 */
#[Fillable(['owner_type', 'owner_id', 'surface', 'content', 'search_text'])]
class Layout extends Model
{
    protected $table = 'pagebuilder_layouts';

    /** Владелец — элемент инфоблока. */
    public const OWNER_ELEMENT = 'element';

    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'surface' => Surface::class,
            'content' => 'array',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOwnedBy(Builder $query, string $type, int $id, Surface $surface = Surface::Detail): void
    {
        $query->where('owner_type', $type)->where('owner_id', $id)->where('surface', $surface->value);
    }

    /**
     * @return array<int, array{id: string, type: string, data: array<string, mixed>}>
     */
    public function blocks(): array
    {
        return $this->content['blocks'] ?? [];
    }
}
