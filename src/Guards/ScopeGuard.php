<?php

namespace Mainul\UuiRolePermission\Guards;

use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Support\Config;

class ScopeGuard
{
    public function assertRoleAssignmentCompatible($role, $tenant): void
    {
        $tenantId = is_object($tenant) && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;
        if (! Config::tenancyEnabled() && $tenantId !== null) {
            throw new AccessControlException('This installation does not support tenant scoped assignments.', AccessControlException::TENANT_MISMATCH);
        }
        if (! Config::tenancyEnabled()) {
            return;
        }
        $roleTenant = $role->{Config::tenantKey()};
        if ($roleTenant !== null && (string) $roleTenant !== (string) $tenantId) {
            throw new AccessControlException('Role and assignment tenant do not match.', AccessControlException::TENANT_MISMATCH);
        }
        if (($role->assignable_scope === 'GLOBAL' && $tenantId !== null) || ($role->assignable_scope === 'TENANT' && $tenantId === null)) {
            throw new AccessControlException('Role scope does not match assignment scope.', AccessControlException::TENANT_MISMATCH);
        }
    }

    public function assertPermissionScopes($role, $permissions): void
    {
        if (! Config::tenancyEnabled() || $role->{Config::tenantKey()} === null) {
            return;
        }
        foreach ($permissions as $permission) {
            if ($permission->scope === 'GLOBAL') {
                throw new AccessControlException('Global-only permissions cannot be granted to a tenant role.', AccessControlException::TENANT_MISMATCH);
            }
        }
    }
}
