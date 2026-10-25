<?php

namespace Mainul\UuiRolePermission\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mainul\UuiRolePermission\Contracts\PermissionResolver;
use Mainul\UuiRolePermission\Contracts\UserActivityChecker;

class DatabasePermissionResolver implements PermissionResolver
{
    /** @var array<string, array{global: array<int, string>, tenants: array<string, array<int, string>>, super_admin: bool}> */
    private array $memo = [];

    public function __construct(private AccessCache $cache, private UserActivityChecker $activity) {}

    public function resolve($user): array
    {
        if (! $user || ! $this->activity->active($user)) {
            return $this->empty();
        }

        $userId = (string) $user->getKey();
        if (isset($this->memo[$userId])) {
            return $this->memo[$userId];
        }

        try {
            $permissions = $this->cache->remember($userId, function () use ($userId): array {
                return $this->fromDatabase($userId);
            });
        } catch (\Throwable $exception) {
            Log::error('Role permission resolution failed closed.', ['user_id' => $userId, 'exception' => $exception]);
            $permissions = $this->empty();
        }

        return $this->memo[$userId] = $permissions;
    }

    public function has($user, string $permission, $tenant = null): bool
    {
        $resolved = $this->resolve($user);
        if ($resolved['super_admin']) {
            return true;
        }

        if (in_array($permission, $resolved['global'], true)) {
            return true;
        }

        $tenantId = $this->tenantId($tenant);

        return $tenantId !== null && in_array($permission, $resolved['tenants'][$tenantId] ?? [], true);
    }

    public function forget($user): void
    {
        $id = is_object($user) ? (string) $user->getKey() : (string) $user;
        unset($this->memo[$id]);
        $this->cache->forget($id);
    }

    public function flush(): void
    {
        $this->memo = [];
    }

    private function fromDatabase(string $userId): array
    {
        $roles = Config::table('roles');
        $permissions = Config::table('permissions');
        $pivot = Config::table('role_permissions');
        $assignments = Config::table('assignments');
        $tenantKey = Config::tenantKey();

        $query = DB::table($assignments.' as a')
            ->join($roles.' as r', 'r.id', '=', 'a.role_id')
            ->leftJoin($pivot.' as rp', 'rp.role_id', '=', 'r.id')
            ->leftJoin($permissions.' as p', function ($join): void {
                $join->on('p.id', '=', 'rp.permission_id')->where('p.is_deprecated', false);
            })
            ->where('a.user_id', $userId)
            ->whereNull('r.deleted_at')
            ->where(function ($query): void {
                $query->whereNull('a.expires_at')->orWhere('a.expires_at', '>', now());
            })
            ->select([Config::tenancyEnabled() ? 'a.'.$tenantKey.' as tenant_id' : DB::raw('NULL as tenant_id'), 'r.code as role_code', 'p.code as permission_code', 'p.scope as permission_scope']);

        if (Config::tenancyEnabled() && Config::get('tenancy.require_membership', true)) {
            $memberships = Config::table('memberships');
            $query->leftJoin($memberships.' as m', function ($join) use ($tenantKey): void {
                $join->on('m.'.$tenantKey, '=', 'a.'.$tenantKey)->on('m.user_id', '=', 'a.user_id');
            })->where(function ($query): void {
                $query->whereNull('a.'.Config::tenantKey())
                    ->orWhereIn('m.status', (array) Config::get('tenancy.membership_active_values', ['ACTIVE']));
            });
        }

        if (Config::tenancyEnabled() && Config::get('tenancy.active_column')) {
            $tenantTable = (string) Config::get('tenancy.table');
            $activeColumn = (string) Config::get('tenancy.active_column');
            $tenantPrimaryKey = (string) Config::get('tenancy.primary_key', 'id');
            $query->leftJoin($tenantTable.' as t', 't.'.$tenantPrimaryKey, '=', 'a.'.$tenantKey)
                ->where(function ($query) use ($activeColumn): void {
                    $query->whereNull('a.'.Config::tenantKey())
                        ->orWhereIn('t.'.$activeColumn, (array) Config::get('tenancy.active_values', ['ACTIVE']));
                });
        }

        $result = $this->empty();
        foreach ($query->get() as $row) {
            if ($row->role_code === Config::get('super_admin_role', 'SUPER_ADMIN') && $row->tenant_id === null) {
                $result['super_admin'] = true;
            }
            if ($row->permission_code === null) {
                continue;
            }
            if ($row->tenant_id === null) {
                if ($row->permission_scope === 'TENANT') {
                    continue;
                }
                $result['global'][] = $row->permission_code;
            } else {
                if ($row->permission_scope === 'GLOBAL') {
                    continue;
                }
                $result['tenants'][(string) $row->tenant_id][] = $row->permission_code;
            }
        }

        $result['global'] = array_values(array_unique($result['global']));
        foreach ($result['tenants'] as $tenant => $codes) {
            $result['tenants'][$tenant] = array_values(array_unique($codes));
        }

        return $result;
    }

    private function tenantId($tenant): ?string
    {
        if ($tenant === null) {
            return null;
        }

        return is_object($tenant) && method_exists($tenant, 'getKey') ? (string) $tenant->getKey() : (string) $tenant;
    }

    /** @return array{global: array<int, string>, tenants: array<string, array<int, string>>, super_admin: bool} */
    private function empty(): array
    {
        return ['global' => [], 'tenants' => [], 'super_admin' => false];
    }
}
