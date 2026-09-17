<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\FeedbackSubmission;

/**
 * @mixin FeedbackSubmission
 */
class FeedbackSubmissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fields = [];

        foreach ($this->resource->fields() as $code => $field) {
            $value = $field['value'] ?? null;
            $isFile = ($field['type'] ?? null) === 'file';

            $fields[] = [
                'code' => $code,
                'label' => $field['label'] ?? $code,
                'type' => $field['type'] ?? 'string',
                'value' => $isFile ? null : $value,
                'file' => $isFile && is_array($value) ? [
                    'name' => $value['name'] ?? '',
                    'size' => $value['size'] ?? 0,
                    'url' => route('admin.api.forms.submissions.file', [
                        'form' => $this->form_id,
                        'submission' => $this->id,
                        'field' => $code,
                    ]),
                ] : null,
            ];
        }

        return [
            'id' => $this->id,
            'form_id' => $this->form_id,
            'fields' => $fields,
            'preview' => $this->resource->preview(),
            'agreement' => $this->agreement_id ? [
                'id' => $this->agreement_id,
                'name' => $this->agreement?->name,
                'agreed_at' => $this->agreed_at?->toIso8601String(),
            ] : null,
            'page_url' => $this->page_url,
            'ip' => $this->ip,
            'user_agent' => $this->user_agent,
            'user_id' => $this->user_id,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
