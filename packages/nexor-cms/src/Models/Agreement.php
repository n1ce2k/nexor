<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexor\Cms\Database\Factories\AgreementFactory;

/**
 * Соглашение — как пользовательские соглашения в Битриксе: галочка с текстом
 * у формы и полный текст во всплывающем окне или на своей странице.
 */
#[Fillable(['code', 'name', 'label', 'link_text', 'text', 'text_type', 'is_active', 'sort'])]
class Agreement extends Model
{
    /** @use HasFactory<AgreementFactory> */
    use HasFactory;

    protected $attributes = [
        'text_type' => 'html',
        'is_active' => true,
        'sort' => 500,
    ];

    protected static function newFactory(): Factory
    {
        return AgreementFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<FeedbackForm, $this>
     */
    public function forms(): HasMany
    {
        return $this->hasMany(FeedbackForm::class);
    }

    public function isHtml(): bool
    {
        return $this->text_type === 'html';
    }

    /**
     * Текст у галочки, разобранный на части: то, что до ссылки, сама ссылка и
     * то, что после. Ссылкой становится `link_text`, если он есть в подписи;
     * если его в подписи нет — дописывается в конец; если не задан — ссылка
     * вся подпись.
     *
     * @return array{before: string, link: string, after: string}
     */
    public function labelParts(): array
    {
        $label = (string) $this->label;
        $link = trim((string) $this->link_text);

        if ($link === '') {
            return ['before' => '', 'link' => $label, 'after' => ''];
        }

        $position = mb_strpos($label, $link);

        if ($position === false) {
            return ['before' => rtrim($label).' ', 'link' => $link, 'after' => ''];
        }

        return [
            'before' => mb_substr($label, 0, $position),
            'link' => $link,
            'after' => mb_substr($label, $position + mb_strlen($link)),
        ];
    }

    public function url(): string
    {
        return route('nexor.agreement', $this->code);
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
        $query->orderBy('sort')->orderBy('name');
    }
}
