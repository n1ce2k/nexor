<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\MailTemplate;

/**
 * @mixin MailTemplate
 */
class MailTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'from' => $this->from,
            'to' => $this->to,
            'reply_to' => $this->reply_to,
            'bcc' => $this->bcc,
            'subject' => $this->subject,
            'body' => $this->body,
            'body_type' => $this->body_type,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'placeholders' => $this->placeholders(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
