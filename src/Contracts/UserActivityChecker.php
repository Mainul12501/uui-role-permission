<?php

namespace Mainul\UuiRolePermission\Contracts;

interface UserActivityChecker
{
    public function active($user): bool;
}
