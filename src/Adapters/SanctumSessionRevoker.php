<?php

namespace Mainul\UuiRolePermission\Adapters;

use Mainul\UuiRolePermission\Contracts\SessionRevoker;

class SanctumSessionRevoker implements SessionRevoker
{
    public function revoke($user): void
    {
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }
    }
}
