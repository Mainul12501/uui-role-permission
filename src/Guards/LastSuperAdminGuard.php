<?php

namespace Mainul\UuiRolePermission\Guards;

use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Support\Config;

class LastSuperAdminGuard
{
    public function assertCanRevoke($assignment): void
    {
        $tenantKey = Config::tenantKey();
        $global = ! Config::tenancyEnabled() || $assignment->{$tenantKey} === null;
        if (! $global || ! $assignment->role || $assignment->role->code !== config('uui-role-permission.super_admin_role')) {
            return;
        }
        $query = $assignment->newQuery()->whereHas('role', fn ($builder) => $builder->where('code', config('uui-role-permission.super_admin_role')))
            ->where(function ($builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        if (Config::tenancyEnabled()) {
            $query->whereNull($tenantKey);
        }
        if ($query->count() <= 1) {
            throw new AccessControlException('The last super administrator cannot be removed.', AccessControlException::LAST_SUPER_ADMIN);
        }
    }
}
