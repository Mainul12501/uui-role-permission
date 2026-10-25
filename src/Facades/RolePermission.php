<?php

namespace Mainul\UuiRolePermission\Facades;

use Illuminate\Support\Facades\Facade;
use Mainul\UuiRolePermission\Contracts\PermissionResolver;

class RolePermission extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PermissionResolver::class;
    }
}
