<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Mainul\UuiRolePermission\Events\AccessControlChanged;
use Mainul\UuiRolePermission\Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_global_permissions_and_expiry_are_resolved(): void
    {
        $user = $this->user();
        $this->grant($user, $this->role('VIEWER', ['orders.view']));
        $expired = $this->grant($user, $this->role('EDITOR', ['orders.manage']));
        $expired->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertFalse($user->hasPermission('orders.manage'));
        $this->assertFalse($user->hasPermission('unknown.permission'));
    }

    public function test_inactive_users_fail_closed(): void
    {
        config(['uui-role-permission.user_active' => fn ($user): bool => $user->status === 'ACTIVE']);
        $user = $this->user();
        $this->grant($user, $this->role('VIEWER', ['orders.view']));
        $user->forceFill(['status' => 'LOCKED'])->save();
        $this->assertFalse($user->hasPermission('orders.view'));
    }

    public function test_super_admin_bypasses_new_permissions(): void
    {
        $user = $this->user();
        $this->grant($user, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $this->assertTrue($user->hasPermission('brand.new.permission'));
        $this->assertTrue($user->isSuperAdmin());
    }

    public function test_resolution_is_one_query_and_memoized(): void
    {
        $user = $this->user();
        $this->grant($user, $this->role('VIEWER', ['orders.view', 'orders.manage']));
        $queries = 0;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query) use (&$queries): void {
            if (str_contains($query->sql, 'user_role_assignments')) {
                $queries++;
            }
        });
        $this->assertTrue($user->hasPermission('orders.view'));
        $this->assertTrue($user->hasPermission('orders.manage'));
        $this->assertSame(1, $queries);
    }

    public function test_change_event_invalidates_a_warm_cache(): void
    {
        $user = $this->user();
        $role = $this->role('VIEWER', ['orders.view']);
        $this->grant($user, $role);
        $this->assertTrue($user->hasPermission('orders.view'));
        $role->permissions()->detach();
        AccessControlChanged::dispatch('role.permissions_synced', $role);
        $this->assertFalse($user->hasPermission('orders.view'));
    }

    public function test_scoped_binding_does_not_leak_between_users(): void
    {
        $allowed = $this->user('allowed@example.test');
        $denied = $this->user('denied@example.test');
        $this->grant($allowed, $this->role('VIEWER', ['orders.view']));
        $this->assertTrue($allowed->hasPermission('orders.view'));
        $this->assertFalse($denied->hasPermission('orders.view'));
        app()->forgetScopedInstances();
        $this->assertFalse($denied->fresh()->hasPermission('orders.view'));
    }
}
