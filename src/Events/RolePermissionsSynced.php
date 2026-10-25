<?php

namespace Mainul\UuiRolePermission\Events;

class RolePermissionsSynced extends AccessControlChanged
{
    public function __construct($role, $actor, ?string $reason, array $before)
    {
        parent::__construct('role.permissions_synced', $role, $actor, $reason, $before, ['permissions' => $role->permissions->pluck('code')->all()]);
    }
}
