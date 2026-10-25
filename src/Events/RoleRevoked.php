<?php

namespace Mainul\UuiRolePermission\Events;

class RoleRevoked extends AccessControlChanged
{
    public function __construct($assignment, $actor, ?string $reason, array $before)
    {
        parent::__construct('role.revoked', $assignment, $actor, $reason, $before, null);
    }
}
