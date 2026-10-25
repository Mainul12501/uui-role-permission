<?php

namespace Mainul\UuiRolePermission\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray($request): array
    {
        $role = $this->resource;

        return [
            'id' => $role->getKey(), 'code' => $role->code, 'name' => $role->name,
            'description' => $role->description, 'scope' => $role->assignable_scope,
            'is_system' => (bool) $role->is_system, 'is_locked' => (bool) $role->is_locked,
            'permissions' => $this->whenLoaded('permissions', fn () => $role->permissions->pluck('code')->values()),
            'assignments_count' => $this->whenCounted('assignments'),
            'created_at' => $role->created_at, 'updated_at' => $role->updated_at,
        ];
    }
}
