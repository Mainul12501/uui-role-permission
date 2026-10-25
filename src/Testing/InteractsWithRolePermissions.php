<?php

namespace Mainul\UuiRolePermission\Testing;

use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Support\Config;

trait InteractsWithRolePermissions
{
    public function actingWithPermissions($user, array $codes, $tenant = null)
    {
        $permissionClass = Config::model('permission');
        $roleClass = Config::model('role');
        foreach ($codes as $index => $code) {
            $permissionClass::firstOrCreate(['code' => $code], ['name' => $code, 'group' => 'testing', 'scope' => 'ANY', 'sort_order' => $index]);
        }
        $identity = ['code' => 'TEST_'.strtoupper(substr(sha1(implode('|', $codes).serialize($tenant)), 0, 12))];
        if (Config::tenancyEnabled()) {
            $identity[Config::tenantKey()] = is_object($tenant) ? $tenant->getKey() : $tenant;
        }
        $role = $roleClass::firstOrCreate($identity, ['name' => 'Test role', 'assignable_scope' => $tenant === null ? 'GLOBAL' : 'TENANT']);
        $role->permissions()->sync($permissionClass::whereIn('code', $codes)->pluck('id'));
        app(AssignRole::class)->handle($user, $role, $tenant, null, null, 'Test setup', true);

        return $this->actingAs($user);
    }

    public function assertUserHasPermission($user, string $permission, $tenant = null): void
    {
        $this->assertTrue($user->hasPermission($permission, $tenant), "Expected user to have permission {$permission}.");
    }

    public function assertUserLacksPermission($user, string $permission, $tenant = null): void
    {
        $this->assertFalse($user->hasPermission($permission, $tenant), "Expected user not to have permission {$permission}.");
    }
}
