<?php

namespace Mainul\UuiRolePermission\Support;

class Config
{
    public static function get(string $key, $default = null)
    {
        return config('uui-role-permission.'.$key, $default);
    }

    public static function table(string $name): string
    {
        $table = (string) static::get('tables.'.$name);
        if ($table === '' || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid configured table name: '.$name);
        }

        return $table;
    }

    public static function model(string $name): string
    {
        return (string) static::get('models.'.$name);
    }

    public static function tenancyEnabled(): bool
    {
        return (bool) static::get('tenancy.enabled', true);
    }

    public static function tenantKey(): string
    {
        return (string) static::get('tenancy.foreign_key', 'organization_id');
    }

    public static function keyType(): string
    {
        return (string) static::get('key_type', 'uuid');
    }

    public static function userKeyType(): string
    {
        return (string) static::get('user_key_type', 'uuid');
    }

    public static function tenantKeyType(): string
    {
        return (string) static::get('tenancy.key_type', 'uuid');
    }
}
