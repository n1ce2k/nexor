<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Contracts\NexorUser;
use Nexor\Cms\Support\Nexor;

/**
 * Запись о согласии: кто и что разрешил.
 *
 * Время ставит сервер, а не браузер: запись нужна как раз на случай спора,
 * а присланную дату подделывает кто угодно.
 */
#[Fillable(['preferences', 'ip', 'user_agent', 'user_id'])]
class CookieConsent extends Model
{
    protected $table = 'cookie_consents';

    protected function casts(): array
    {
        return ['preferences' => 'array'];
    }

    /**
     * @return BelongsTo<NexorUser, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Nexor::userModel(), 'user_id');
    }

    /**
     * Что разрешено — короткой строкой для списка в панели.
     */
    public function summary(): string
    {
        $allowed = collect($this->preferences ?? [])
            ->filter(fn ($value, $key) => $value === true && $key !== 'technical')
            ->keys()
            ->map(fn (string $key) => CookieCounter::CATEGORIES[$key] ?? $key)
            ->all();

        return $allowed === [] ? 'Только технические' : implode(', ', $allowed);
    }
}
