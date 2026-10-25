<?php

namespace Mainul\UuiRolePermission\Concerns;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Actions\RevokeRole;
use Mainul\UuiRolePermission\Contracts\PermissionResolver;
use Mainul\UuiRolePermission\Support\Config;

trait HasRoles
{
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(Config::model('assignment'), 'user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Config::model('membership'), 'user_id');
    }

    public function hasPermission(string $permission, $tenant = null): bool
    {
        return app(PermissionResolver::class)->has($this, $permission, $tenant);
    }

    public function hasAnyPermission(array $permissions, $tenant = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $tenant)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        return app(PermissionResolver::class)->resolve($this)['super_admin'];
    }

    public function effectivePermissions(): array
    {
        return app(PermissionResolver::class)->resolve($this);
    }

    public function hasRole(string $role, $tenant = null): bool
    {
        if ($tenant !== null && ! Config::tenancyEnabled()) {
            return false;
        }
        $query = $this->roleAssignments()->whereHas('role', function ($query) use ($role): void {
            $query->where('code', $role);
        });
        $query->where(function ($q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
        if ($tenant === null) {
            return Config::tenancyEnabled() ? $query->whereNull(Config::tenantKey())->exists() : $query->exists();
        }
        $id = is_object($tenant) && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;

        return $query->where(Config::tenantKey(), $id)->exists();
    }

    public function assignRole($role, $tenant = null, $actor = null, ?string $expiresAt = null, ?string $reason = null)
    {
        $roleModel = Config::model('role');
        if (! is_object($role)) {
            $query = $roleModel::where('code', $role);
            if (Config::tenancyEnabled()) {
                $tenantId = is_object($tenant) && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
                if ($tenantId !== null) {
                    $role = (clone $query)->where(Config::tenantKey(), $tenantId)->first();
                }
                $role = $role ?? $query->whereNull(Config::tenantKey())->firstOrFail();
            } else {
                $role = $query->firstOrFail();
            }
        }

        return app(AssignRole::class)->handle($this, $role, $tenant, $actor ?: auth()->user(), $expiresAt, $reason);
    }

    public function revokeRole($role, $tenant = null, $actor = null, ?string $reason = null): void
    {
        $roleModel = Config::model('role');
        if (! is_object($role)) {
            $query = $roleModel::where('code', $role);
            if (Config::tenancyEnabled()) {
                $tenantId = is_object($tenant) && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
                if ($tenantId !== null) {
                    $role = (clone $query)->where(Config::tenantKey(), $tenantId)->first();
                }
                $role = $role ?? $query->whereNull(Config::tenantKey())->firstOrFail();
            } else {
                $role = $query->firstOrFail();
            }
        }
        $query = $this->roleAssignments()->where('role_id', $role->getKey());
        if (Config::tenancyEnabled()) {
            $tenantId = $tenant && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
            $tenantId === null ? $query->whereNull(Config::tenantKey()) : $query->where(Config::tenantKey(), $tenantId);
        }
        $assignment = $query->firstOrFail();
        app(RevokeRole::class)->handle($assignment, $actor ?: auth()->user(), $reason);
    }
}
