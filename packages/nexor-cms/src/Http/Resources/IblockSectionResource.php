<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\IblockSection;

/**
 * @mixin IblockSection
 */
class IblockSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'iblock_id' => $this->iblock_id,
            'parent_id' => $this->parent_id,
            'code' => $this->code,
            'url_path' => $this->url_path,
            'name' => $this->name,
            'indented_name' => $this->indented_name,
            'picture' => $this->picture,
            'picture_url' => $this->picture_url,
            'description' => $this->description,
            'depth' => $this->depth,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'meta_keywords' => $this->meta_keywords,
            'elements_count' => $this->whenCounted('elements'),
            'children_count' => $this->whenCounted('children'),
        ];
    }
}
