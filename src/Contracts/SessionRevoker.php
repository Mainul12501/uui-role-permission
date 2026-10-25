<?php

namespace Mainul\UuiRolePermission\Contracts;

interface SessionRevoker
{
    public function revoke($user): void;
}
