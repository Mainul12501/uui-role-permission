<?php

namespace Mainul\UuiRolePermission\Actions;

use Illuminate\Support\Facades\DB;
use Mainul\UuiRolePermission\Events\RolePermissionsSynced;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Guards\EscalationGuard;
use Mainul\UuiRolePermission\Guards\ScopeGuard;
use Mainul\UuiRolePermission\Guards\SystemRoleGuard;
use Mainul\UuiRolePermission\Support\Config;

class SyncRolePermissions
{
    public function handle($role, array $permissionCodes, $actor = null, ?string $reason = null)
    {
        $result = DB::transaction(function () use ($role, $permissionCodes, $actor) {
            $role = $role->newQuery()->lockForUpdate()->findOrFail($role->getKey());
            app(SystemRoleGuard::class)->assertMutable($role);
            app(EscalationGuard::class)->assertPermission($actor, 'roles.manage', $role->{Config::tenantKey()});
            app(EscalationGuard::class)->assertNotHoldingRole($actor, $role);
            app(EscalationGuard::class)->assertCanManage($actor, $role, $role->{Config::tenantKey()});
            app(EscalationGuard::class)->assertCanGrantPermissions($actor, $permissionCodes, $role->{Config::tenantKey()});
            $before = $role->permissions()->pluck('code')->all();
            $permissions = Config::model('permission');
            $ids = $permissions::whereIn('code', $permissionCodes)->pluck('id');
            if ($ids->count() !== count(array_unique($permissionCodes))) {
                throw new AccessControlException('Unknown permission code.', AccessControlException::UNKNOWN_PERMISSION);
            }
            app(ScopeGuard::class)->assertPermissionScopes($role, $permissions::whereIn('id', $ids)->get());
            $role->permissions()->sync($ids);

            return ['role' => $role->fresh()->load('permissions'), 'before' => ['permissions' => $before]];
        });
        $role = $result['role'];
        RolePermissionsSynced::dispatch($role, $actor, $reason, $result['before']);

        return $role;
    }
}
