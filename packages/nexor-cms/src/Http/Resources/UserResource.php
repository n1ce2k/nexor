<?php

namespace Nexor\Cms\Http\Resources;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Support\Nexor;
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
            'initials' => Nexor::initials($this->name),
            'is_active' => (bool) $this->is_active,
            'is_super_admin' => (bool) $this->is_super_admin,
            'last_login_at' => self::moment($this->last_login_at),
            'last_login_ip' => $this->last_login_ip,
            'created_at' => self::moment($this->created_at),
            'fields' => $this->whenLoaded('fieldValues', fn () => UserFields::forForm($this->resource)),
            'list_fields' => $this->whenLoaded('fieldValues', fn () => UserFields::forList($this->resource)),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')),
        ];
    }

    /**
     * Дата в ISO. Модель пользователя живёт в приложении, и приведения типов
     * там может не быть — тогда из базы приходит обычная строка.
     */
    protected static function moment(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof DateTimeInterface
            ? Carbon::instance($value)->toIso8601String()
            : Carbon::parse((string) $value)->toIso8601String();
    }
}
