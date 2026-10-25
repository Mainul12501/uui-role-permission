<?php

namespace Mainul\UuiRolePermission\Events;

class RoleAssigned extends AccessControlChanged
{
    public function __construct($assignment, $actor, ?string $reason, ?array $before = null)
    {
        parent::__construct('role.assigned', $assignment, $actor, $reason, $before, $assignment->getAttributes());
    }
}
