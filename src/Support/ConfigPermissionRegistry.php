<?php

namespace Mainul\UuiRolePermission\Support;

use Mainul\UuiRolePermission\Contracts\PermissionRegistry;

class ConfigPermissionRegistry implements PermissionRegistry
{
    public function all(): array
    {
        $permissions = Config::get('permissions', []);
        $normalised = [];

        foreach ($permissions as $code => $definition) {
            if (is_int($code)) {
                $code = $definition;
                $definition = [];
            }

            $normalised[(string) $code] = is_array($definition) ? $definition : [];
        }

        return $normalised;
    }
}
