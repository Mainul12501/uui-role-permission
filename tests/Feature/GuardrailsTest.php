<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mainul\UuiRolePermission\Actions\AssignRole;
use Mainul\UuiRolePermission\Actions\CreateRole;
use Mainul\UuiRolePermission\Actions\DeleteRole;
use Mainul\UuiRolePermission\Actions\RevokeRole;
use Mainul\UuiRolePermission\Actions\SyncRolePermissions;
use Mainul\UuiRolePermission\Actions\UpdateRole;
use Mainul\UuiRolePermission\Events\RoleCreated;
use Mainul\UuiRolePermission\Exceptions\AccessControlException;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\Fixtures\User;
use Mainul\UuiRolePermission\Tests\TestCase;

class GuardrailsTest extends TestCase
{
    public function test_non_super_admin_cannot_assign_super_admin(): void
    {
        $actor = User::create(['name' => 'Actor', 'email' => 'actor@example.test', 'password' => 'x']);
        $target = User::create(['name' => 'Target', 'email' => 'target@example.test', 'password' => 'x']);
        $roleClass = Config::model('role');
        $role = $roleClass::create(['code' => 'SUPER_ADMIN', 'name' => 'Super Admin']);

        $this->expectException(AccessControlException::class);
        app(AssignRole::class)->handle($target, $role, null, $actor);
    }

    public function test_actor_cannot_change_own_assignment(): void
    {
        $user = User::create(['name' => 'Self', 'email' => 'self@example.test', 'password' => 'x']);
        $roleClass = Config::model('role');
        $role = $roleClass::create(['code' => 'MEMBER', 'name' => 'Member']);
        $assignmentClass = Config::model('assignment');
        $assignment = $assignmentClass::create(['user_id' => $user->getKey(), 'role_id' => $role->getKey()]);

        $this->expectException(AccessControlException::class);
        app(RevokeRole::class)->handle($assignment, $user, 'self revoke');
    }

    public function test_last_super_admin_is_protected(): void
    {
        $user = User::create(['name' => 'Root', 'email' => 'root@example.test', 'password' => 'x']);
        $roleClass = Config::model('role');
        $role = $roleClass::create(['code' => 'SUPER_ADMIN', 'name' => 'Super Admin']);
        $assignmentClass = Config::model('assignment');
        $assignment = $assignmentClass::create(['user_id' => $user->getKey(), 'role_id' => $role->getKey()]);

        try {
            app(RevokeRole::class)->handle($assignment, null, 'rotation', true);
        } catch (AccessControlException $exception) {
            $this->assertSame(AccessControlException::LAST_SUPER_ADMIN, $exception->codeName);

            return;
        }
        $this->fail('Expected the last super administrator guard to reject revocation.');
    }

    public function test_access_changes_write_audit_rows_synchronously(): void
    {
        $actor = User::create(['name' => 'Actor', 'email' => 'audit@example.test', 'password' => 'x']);
        $target = User::create(['name' => 'Target', 'email' => 'audit-target@example.test', 'password' => 'x']);
        $admin = $this->role('ROLE_ADMIN', ['users.assign_roles'], ['priority' => 10]);
        $this->grant($actor, $admin);
        $roleClass = Config::model('role');
        $role = $roleClass::create(['code' => 'MEMBER', 'name' => 'Member']);
        app(AssignRole::class)->handle($target, $role, null, $actor, null, 'approved');
        $this->assertDatabaseHas(Config::table('audit_logs'), ['action' => 'role.assigned', 'reason' => 'approved']);
    }

    public function test_locked_system_role_cannot_be_changed(): void
    {
        $actor = $this->user('root@example.test');
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $locked = $this->role('LOCKED', [], ['is_locked' => true, 'is_system' => true]);
        $this->expectException(AccessControlException::class);
        app(UpdateRole::class)->handle($locked, ['name' => 'Changed'], $actor);
    }

    public function test_stale_role_update_is_rejected(): void
    {
        $actor = $this->user('root@example.test');
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $role = $this->role('EDITABLE');
        try {
            app(UpdateRole::class)->handle($role, ['name' => 'Changed'], $actor, null, '2000-01-01 00:00:00');
        } catch (AccessControlException $exception) {
            $this->assertSame(AccessControlException::STALE_ROLE, $exception->codeName);

            return;
        }
        $this->fail('Expected stale role rejection.');
    }

    public function test_actor_cannot_modify_a_role_they_hold(): void
    {
        $actor = $this->user();
        $role = $this->role('ROLE_ADMIN', ['roles.manage'], ['priority' => 100]);
        $this->grant($actor, $role);
        $this->expectException(AccessControlException::class);
        app(UpdateRole::class)->handle($role, ['name' => 'Changed'], $actor);
    }

    public function test_role_in_use_requires_atomic_reassignment(): void
    {
        $actor = $this->user('root@example.test');
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        $role = $this->role('IN_USE');
        $this->grant($this->user('member@example.test'), $role);
        try {
            app(DeleteRole::class)->handle($role, $actor);
        } catch (AccessControlException $exception) {
            $this->assertSame(AccessControlException::ROLE_IN_USE, $exception->codeName);

            return;
        }
        $this->fail('Expected in-use role rejection.');
    }

    public function test_actor_cannot_grant_a_permission_they_lack(): void
    {
        $actor = $this->user();
        $admin = $this->role('ROLE_ADMIN', ['roles.manage'], ['priority' => 100]);
        $this->grant($actor, $admin);
        $target = $this->role('TARGET');
        $this->permission('orders.manage');
        $this->expectException(AccessControlException::class);
        app(SyncRolePermissions::class)->handle($target, ['orders.manage'], $actor);
    }

    public function test_access_events_wait_for_the_outer_transaction_commit(): void
    {
        $actor = $this->user('root@example.test');
        $this->grant($actor, $this->role('SUPER_ADMIN', [], ['assignable_scope' => 'GLOBAL']));
        Event::fake([RoleCreated::class]);
        DB::beginTransaction();
        app(CreateRole::class)->handle(['code' => 'AFTER_COMMIT', 'name' => 'After commit'], $actor);
        Event::assertNotDispatched(RoleCreated::class);
        DB::commit();
        Event::assertDispatched(RoleCreated::class);
    }
}
