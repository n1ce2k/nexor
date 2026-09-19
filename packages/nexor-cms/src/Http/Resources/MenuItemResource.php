<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\MenuItem;

/**
 * @mixin MenuItem
 */
class MenuItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'menu_id' => $this->menu_id,
            'parent_id' => $this->parent_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'accepts_children' => $this->type->acceptsChildren(),
            'title' => $this->title,
            'url' => $this->url,
            'iblock_id' => $this->iblock_id,
            'element_id' => $this->element_id,
            'section_id' => $this->section_id,
            'max_depth' => $this->max_depth,
            'with_elements' => $this->with_elements,
            'with_title' => $this->with_title,
            'target' => $this->target,
            'css_class' => $this->css_class,
            'icon' => $this->icon,
            'visibility' => $this->visibility->value,
            'highlight_children' => $this->highlight_children,
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            // Что редактор увидит в строке дерева, когда своего названия нет.
            'display_title' => $this->title
                ?: $this->element?->name
                ?: $this->section?->name
                ?: $this->iblock?->name
                ?: $this->type->label(),
        ];
    }
}
