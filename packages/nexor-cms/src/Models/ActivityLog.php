<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Nexor\Cms\Support\Nexor;

#[Fillable([
    'user_id', 'action', 'subject_type', 'subject_id',
    'description', 'changes', 'ip', 'user_agent',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<covariant Model, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel());
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'created' => 'Создание',
            'updated' => 'Изменение',
            'deleted' => 'Удаление',
            'restored' => 'Восстановление',
            'login' => 'Вход',
            'logout' => 'Выход',
            'login_failed' => 'Неудачный вход',
            default => $this->action,
        };
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
