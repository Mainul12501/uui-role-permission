<?php

namespace Mainul\UuiRolePermission\Contracts;

interface PermissionResolver
{
    /** @return array{global: array<int, string>, tenants: array<string, array<int, string>>, super_admin: bool} */
    public function resolve($user): array;

    public function has($user, string $permission, $tenant = null): bool;

    public function forget($user): void;

    public function flush(): void;
}
