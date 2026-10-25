<?php

namespace Mainul\UuiRolePermission\Events;

class RoleDeleted extends AccessControlChanged
{
    public function __construct($role, $actor, ?string $reason, array $before, ?array $after = null)
    {
        parent::__construct('role.deleted', $role, $actor, $reason, $before, $after);
    }
}
