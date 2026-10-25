<?php

namespace Mainul\UuiRolePermission\Contracts;

interface PermissionRegistry
{
    /** @return array<string, array<string, mixed>> */
    public function all(): array;
}
