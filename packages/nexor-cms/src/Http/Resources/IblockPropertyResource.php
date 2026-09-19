<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\IblockProperty;

/**
 * @mixin IblockProperty
 */
class IblockPropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iblock_id' => $this->iblock_id,
            'code' => $this->code,
            'name' => $this->name,
            'hint' => $this->hint,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_multiple' => $this->is_multiple,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'is_searchable' => $this->is_searchable,
            'is_shown_in_list' => $this->is_shown_in_list,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'default_value' => $this->default_value,
            'description' => $this->description,
            'settings' => $this->settings ?? [],
            'enums' => IblockPropertyEnumResource::collection($this->whenLoaded('enums')),
            'values_count' => $this->whenCounted('values'),
        ];
    }
}
