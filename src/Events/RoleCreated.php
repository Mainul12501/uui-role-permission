<?php

namespace Mainul\UuiRolePermission\Events;

class RoleCreated extends AccessControlChanged
{
    public function __construct($role, $actor = null, ?string $reason = null)
    {
        parent::__construct('role.created', $role, $actor, $reason, null, $role->getAttributes());
    }
}
