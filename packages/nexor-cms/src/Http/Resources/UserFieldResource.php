<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\UserField;

/**
 * @mixin UserField
 */
class UserFieldResource extends JsonResource
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
            'hint' => $this->hint,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'is_required' => $this->is_required,
            'is_multiple' => $this->is_multiple,
            'is_shown_in_list' => $this->is_shown_in_list,
            'is_filterable' => $this->is_filterable,
            'is_active' => $this->is_active,
            'settings' => $this->settings ?? (object) [],
            // Панель рисует поле тем же компонентом, что и свойство инфоблока.
            'enums' => array_map(
                fn (array $option) => ['id' => $option['value'], 'value' => $option['label']],
                $this->resource->options(),
            ),
            'sort' => $this->sort,
            'values_count' => $this->whenCounted('values'),
        ];
    }
}
