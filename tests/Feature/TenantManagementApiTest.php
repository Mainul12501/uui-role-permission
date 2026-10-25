<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\Fixtures\Tenant;
use Mainul\UuiRolePermission\Tests\TenantTestCase;

class TenantManagementApiTest extends TenantTestCase
{
    private function registerManagementRoutes(): void
    {
        config(['uui-role-permission.api' => ['enabled' => true, 'prefix' => 'api/v1', 'middleware' => ['auth'], 'name' => 'api.v1.', 'rate_limit' => '60,1']]);
        require dirname(__DIR__, 2).'/routes/api.php';
    }

    public function test_tenant_routes_are_isolated_by_permission_scope(): void
    {
        $this->registerManagementRoutes();
        $first = Tenant::create(['name' => 'First', 'status' => 'ACTIVE']);
        $second = Tenant::create(['name' => 'Second', 'status' => 'ACTIVE']);
        $actor = $this->user('tenant-admin@example.test');
        $membershipClass = Config::model('membership');
        $membershipClass::create([Config::tenantKey() => $first->getKey(), 'user_id' => $actor->getKey(), 'status' => 'ACTIVE']);
        $role = $this->role('TENANT_ROLE_ADMIN', ['roles.view', 'roles.manage'], [Config::tenantKey() => $first->getKey(), 'assignable_scope' => 'TENANT', 'priority' => 100]);
        $this->grant($actor, $role, $first);

        $this->actingAs($actor)->getJson('/api/v1/tenants/'.$first->getKey().'/roles')->assertOk();
        $this->getJson('/api/v1/tenants/'.$second->getKey().'/roles')->assertForbidden();
    }
}
