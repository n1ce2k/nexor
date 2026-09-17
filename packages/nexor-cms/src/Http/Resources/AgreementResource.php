<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\Agreement;

/**
 * @mixin Agreement
 */
class AgreementResource extends JsonResource
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
            'label' => $this->label,
            'link_text' => $this->link_text,
            'label_parts' => $this->resource->labelParts(),
            'text' => $this->text,
            'text_type' => $this->text_type,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'url' => $this->resource->url(),
            'forms_count' => $this->whenCounted('forms'),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
