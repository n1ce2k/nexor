<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Support\Uploads;
use Nexor\Cms\Support\UserFields;

/**
 * The user model belongs to the host application, so this resource only reads
 * the columns the CMS itself installs.
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login' => $this->login,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'avatar_url' => $this->avatar ? Uploads::url($this->avatar) : null,
            'initials' => $this->initials,
            'is_active' => (bool) $this->is_active,
            'is_super_admin' => (bool) $this->is_super_admin,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => $this->created_at?->toIso8601String(),
            'fields' => $this->whenLoaded('fieldValues', fn () => UserFields::forForm($this->resource)),
            'list_fields' => $this->whenLoaded('fieldValues', fn () => UserFields::forList($this->resource)),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')),
        ];
    }
}
