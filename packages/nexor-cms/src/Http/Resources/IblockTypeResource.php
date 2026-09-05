<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\IblockType;

/**
 * @mixin IblockType
 */
class IblockTypeResource extends JsonResource
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
            'sections_name' => $this->sections_name,
            'elements_name' => $this->elements_name,
            'description' => $this->description,
            'has_sections' => $this->has_sections,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'iblocks_count' => $this->whenCounted('iblocks'),
        ];
    }
}
