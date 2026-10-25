<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Actions\SyncRolePermissions;
use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\Fixtures\Tenant;
use Mainul\UuiRolePermission\Tests\TenantTestCase;

class TenantAuthorizationTest extends TenantTestCase
{
    private function tenant(string $name = 'Tenant'): Tenant
    {
        return Tenant::create(['name' => $name, 'status' => 'ACTIVE']);
    }

    private function membership($tenant, $user, string $status = 'ACTIVE'): void
    {
        $class = Config::model('membership');
        $class::create([Config::tenantKey() => $tenant->getKey(), 'user_id' => $user->getKey(), 'status' => $status]);
    }

    public function test_tenant_permission_requires_active_membership_and_tenant(): void
    {
        $tenant = $this->tenant();
        $user = $this->user();
        $this->membership($tenant, $user);
        $role = $this->role('TENANT_VIEWER', ['orders.view'], [Config::tenantKey() => $tenant->getKey(), 'assignable_scope' => 'TENANT']);
        $this->grant($user, $role, $tenant);
        $this->assertTrue($user->hasPermission('orders.view', $tenant));
        $this->assertFalse($user->hasPermission('orders.view'));

        Config::model('membership')::query()->update(['status' => 'INACTIVE']);
        AccessControlChanged::dispatch('membership.changed', null);
        $this->assertFalse($user->hasPermission('orders.view', $tenant));
    }

    public function test_suspended_tenant_grants_nothing(): void
    {
        $tenant = $this->tenant();
        $user = $this->user();
        $this->membership($tenant, $user);
        $role = $this->role('TENANT_VIEWER', ['orders.view'], [Config::tenantKey() => $tenant->getKey(), 'assignable_scope' => 'TENANT']);
        $this->grant($user, $role, $tenant);
        $tenant->update(['status' => 'SUSPENDED']);
        $this->assertFalse($user->hasPermission('orders.view', $tenant));
    }

    public function test_assignment_requires_membership_and_matching_scope(): void
    {
        $tenant = $this->tenant();
        $target = $this->user('target@example.test');
        $role = $this->role('TENANT_VIEWER', [], [Config::tenantKey() => $tenant->getKey(), 'assignable_scope' => 'TENANT']);
        $this->expectException(AccessControlException::class);
        app(AssignRole::class)->handle($target, $role, $tenant, null, null, 'setup', true);
    }

    public function test_global_only_permission_is_rejected_on_tenant_role(): void
    {
        $tenant = $this->tenant();
        $actor = $this->user('actor@example.test');
        $this->membership($tenant, $actor);
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $permission = $this->permission('global.only', 'GLOBAL');
        $role = $this->role('CUSTOM', [], [Config::tenantKey() => $tenant->getKey(), 'assignable_scope' => 'TENANT']);
        $this->expectException(AccessControlException::class);
        app(SyncRolePermissions::class)->handle($role, [$permission->code], $actor);
    }
}
