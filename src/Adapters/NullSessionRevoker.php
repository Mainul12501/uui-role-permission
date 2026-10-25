<?php

namespace Mainul\UuiRolePermission\Adapters;

use Mainul\UuiRolePermission\Contracts\SessionRevoker;

class NullSessionRevoker implements SessionRevoker
{
    public function revoke($user): void {}
}
