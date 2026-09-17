<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\FeedbackFormFactory;

/**
 * Форма обратной связи — как веб-форма Битрикса: свои поля, почтовый шаблон,
 * соглашение и записи того, что прислали.
 *
 * На сайте: `<x-nexor::form :id="3" />` или `<x-nexor::form form="callback" />`.
 */
#[Fillable([
    'code', 'name', 'title', 'button_text', 'success_text', 'mail_template_id', 'to',
    'agreement_id', 'agreement_popup', 'ajax', 'store_submissions', 'telegram', 'protection', 'is_active', 'sort',
])]
class FeedbackForm extends Model
{
    /** @use HasFactory<FeedbackFormFactory> */
    use HasFactory;

    protected $attributes = [
        'button_text' => 'Отправить',
        'success_text' => 'Спасибо! Мы получили ваше сообщение.',
        'agreement_popup' => true,
        'ajax' => true,
        'store_submissions' => true,
        'is_active' => true,
        'sort' => 500,
    ];

    protected static function newFactory(): Factory
    {
        return FeedbackFormFactory::new();
    }

    protected function casts(): array
    {
        return [
            'agreement_popup' => 'boolean',
            'ajax' => 'boolean',
            'store_submissions' => 'boolean',
            // Секреты внутри зашифрованы отдельно — см. FormTelegram и FormCaptcha.
            'telegram' => 'array',
            'protection' => 'array',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<FeedbackFormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FeedbackFormField::class, 'form_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<FeedbackSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FeedbackSubmission::class, 'form_id');
    }

    /**
     * @return BelongsTo<MailTemplate, $this>
     */
    public function mailTemplate(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class);
    }

    /**
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Соглашение, которое действительно показывается: выключенное — как не заданное.
     */
    public function activeAgreement(): ?Agreement
    {
        return $this->agreement?->is_active ? $this->agreement : null;
    }

    /**
     * Опубликованная форма по id или коду — так её называют в компоненте.
     */
    public static function findForSite(int|string $key): ?self
    {
        $key = trim((string) $key);

        $query = fn () => self::query()->where('is_active', true)->with(['fields', 'agreement']);

        return (ctype_digit($key) ? $query()->whereKey((int) $key)->first() : null)
            ?? $query()->where('code', $key)->first();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }
}
