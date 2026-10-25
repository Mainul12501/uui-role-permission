<?php

namespace Mainul\UuiRolePermission\Guards;

use Mainul\UuiRolePermission\Exceptions\AccessControlException;

class SystemRoleGuard
{
    public function assertMutable($role): void
    {
        if ($role->is_locked || $role->is_system) {
            throw new AccessControlException('System roles cannot be changed or deleted.', AccessControlException::PROTECTED_ROLE);
        }
    }
}
