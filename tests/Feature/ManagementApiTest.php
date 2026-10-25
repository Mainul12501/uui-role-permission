<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Mainul\UuiRolePermission\Tests\Fixtures\User;
use Mainul\UuiRolePermission\Tests\TestCase;

class ManagementApiTest extends TestCase
{
    private function registerManagementRoutes(): void
    {
        config(['uui-role-permission.api' => [
            'enabled' => true, 'prefix' => 'api/v1', 'middleware' => ['auth'], 'name' => 'api.v1.',
        ]]);
        require dirname(__DIR__, 2).'/routes/api.php';
    }

    public function test_management_api_requires_authentication(): void
    {
        $this->registerManagementRoutes();
        $this->getJson('/api/v1/roles')->assertUnauthorized();
    }

    public function test_role_writes_return_validation_errors(): void
    {
        $this->registerManagementRoutes();
        $user = User::create(['name' => 'Manager', 'email' => 'manager@example.test', 'password' => 'x']);
        $this->grant($user, $this->role('ROLE_MANAGER', ['roles.manage']));
        $this->actingAs($user)->postJson('/api/v1/roles', [])->assertUnprocessable();
    }

    public function test_authenticated_user_without_permission_is_forbidden(): void
    {
        $this->registerManagementRoutes();
        $this->actingAs($this->user())->getJson('/api/v1/roles')
            ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_authorized_actor_can_create_read_update_and_delete_role(): void
    {
        $this->registerManagementRoutes();
        $actor = $this->user('root@example.test');
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $created = $this->actingAs($actor)->postJson('/api/v1/roles', [
            'code' => 'SUPPORT', 'name' => 'Support', 'assignable_scope' => 'GLOBAL',
        ])->assertCreated()->json('data');
        $this->getJson('/api/v1/roles/'.$created['id'])->assertOk()->assertJsonPath('data.code', 'SUPPORT');
        $this->patchJson('/api/v1/roles/'.$created['id'], ['name' => 'Customer support'])->assertOk()->assertJsonPath('data.name', 'Customer support');
        $this->deleteJson('/api/v1/roles/'.$created['id'])->assertNoContent();
    }

    public function test_permission_and_role_lists_are_paginated_and_filterable(): void
    {
        $this->registerManagementRoutes();
        $actor = $this->user('reader@example.test');
        $this->grant($actor, $this->role('READER', ['permissions.view', 'roles.view']));
        $this->artisan('uui-rp:sync')->assertSuccessful();
        $this->actingAs($actor)->getJson('/api/v1/permissions?search=roles&per_page=2')->assertOk()->assertJsonStructure(['data']);
        $this->getJson('/api/v1/roles?search=READ')->assertOk()->assertJsonPath('data.data.0.code', 'READER');
    }
}
