<?php

namespace Nexor\Cms\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'name', 'description', 'from', 'to', 'reply_to', 'bcc',
    'subject', 'body', 'body_type', 'is_active', 'sort',
])]
class MailTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Substitute `#PLACEHOLDER#` tokens, the way Bitrix mail templates do.
     *
     * @param  array<string, mixed>  $data
     */
    public function render(string $field, array $data = []): string
    {
        $text = (string) $this->getAttribute($field);

        foreach ($data as $key => $value) {
            $text = str_replace('#'.strtoupper($key).'#', (string) $value, $text);
        }

        return $text;
    }

    /**
     * Placeholders actually used by this template, for the editor's hint line.
     *
     * @return array<int, string>
     */
    public function placeholders(): array
    {
        preg_match_all('/#([A-Z0-9_]+)#/', $this->subject.' '.$this->body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    public function isHtml(): bool
    {
        return $this->body_type === 'html';
    }

    public function plainBody(): string
    {
        return $this->isHtml() ? Str::of($this->body)->stripTags()->toString() : (string) $this->body;
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort')->orderBy('name');
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
