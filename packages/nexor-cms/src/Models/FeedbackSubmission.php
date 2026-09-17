<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Nexor\Cms\Support\FeedbackForms;

/**
 * Запись формы — то, что прислал посетитель.
 *
 * `data` — снимок на момент отправки: `{ code: { label, type, value, sort } }`,
 * у файла `value` — `{ path, name, size }` в закрытом хранилище. Читать через
 * fields(): MySQL хранит ключи JSON в своём порядке, порядок полей — в `sort`.
 */
#[Fillable(['form_id', 'data', 'agreement_id', 'agreed_at', 'page_url', 'ip', 'user_agent', 'user_id', 'is_read'])]
class FeedbackSubmission extends Model
{
    protected $attributes = [
        'is_read' => false,
    ];

    protected static function booted(): void
    {
        // Удалили запись — файлы из закрытого хранилища больше никому не нужны.
        static::deleted(function (self $submission): void {
            foreach ($submission->files() as $file) {
                Storage::disk(FeedbackForms::disk())->delete($file['path']);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'agreed_at' => 'datetime',
            'is_read' => 'boolean',
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
     * @return BelongsTo<Agreement, $this>
     */
    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    /**
     * Поля снимка в порядке формы на момент отправки.
     *
     * @return array<string, array{label: string, type: string, value: mixed, sort?: int}>
     */
    public function fields(): array
    {
        $fields = $this->data ?? [];

        uasort($fields, fn (array $a, array $b) => ($a['sort'] ?? 0) <=> ($b['sort'] ?? 0));

        return $fields;
    }

    /**
     * Загруженные файлы: код поля → { path, name, size }.
     *
     * @return array<string, array{path: string, name: string, size: int}>
     */
    public function files(): array
    {
        $files = [];

        foreach ($this->fields() as $code => $field) {
            if (($field['type'] ?? null) === 'file' && is_array($field['value'] ?? null) && isset($field['value']['path'])) {
                $files[$code] = $field['value'];
            }
        }

        return $files;
    }

    /**
     * Короткая строка для списка записей: первые заполненные значения.
     */
    public function preview(int $limit = 3): string
    {
        $parts = [];

        foreach ($this->fields() as $field) {
            $value = $field['value'] ?? null;
            $text = is_array($value) ? ($value['name'] ?? '') : (string) $value;

            if (trim($text) !== '') {
                $parts[] = $text;
            }

            if (count($parts) >= $limit) {
                break;
            }
        }

        return mb_strimwidth(implode(' · ', $parts), 0, 160, '…');
    }
}
