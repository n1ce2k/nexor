<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexor\Cms\Database\Factories\FeedbackFormFieldFactory;
use Nexor\Cms\Enums\FormFieldType;

/**
 * Поле формы обратной связи.
 *
 * `settings` у файла — `extensions` (через запятую) и `max_kb`; у многострочного — `rows`.
 */
#[Fillable(['form_id', 'code', 'label', 'type', 'is_required', 'placeholder', 'settings', 'sort'])]
class FeedbackFormField extends Model
{
    /** @use HasFactory<FeedbackFormFieldFactory> */
    use HasFactory;

    protected $attributes = [
        'type' => 'string',
        'is_required' => false,
        'sort' => 500,
    ];

    protected static function newFactory(): Factory
    {
        return FeedbackFormFieldFactory::new();
    }

    protected function casts(): array
    {
        return [
            'type' => FormFieldType::class,
            'is_required' => 'boolean',
            'settings' => 'array',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FeedbackForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FeedbackForm::class, 'form_id');
    }

    /**
     * @return array<int, string>
     */
    public function rules(): array
    {
        return $this->type->rules($this->is_required, $this->settings ?? []);
    }

    /**
     * Что подставить в `accept` у поля файла: `.pdf,.docx`.
     */
    public function accept(): ?string
    {
        if ($this->type !== FormFieldType::File) {
            return null;
        }

        return implode(',', array_map(
            fn (string $extension) => '.'.$extension,
            explode(',', FormFieldType::extensions($this->settings ?? [])),
        ));
    }
}
