<?php

namespace Mainul\UuiRolePermission\Events;

class RoleUpdated extends AccessControlChanged
{
    public function __construct($role, $actor, ?string $reason, array $before)
    {
        parent::__construct('role.updated', $role, $actor, $reason, $before, $role->getAttributes());
    }
}
