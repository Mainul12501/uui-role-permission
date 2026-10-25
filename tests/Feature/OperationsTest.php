<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Mainul\UuiRolePermission\Events\RoleRevoked;
use Mainul\UuiRolePermission\Jobs\PruneAuditLogs;
use Mainul\UuiRolePermission\Jobs\PruneExpiredAssignments;
use Mainul\UuiRolePermission\Jobs\RevokeUserSessions;
use Mainul\UuiRolePermission\Jobs\WarmUserPermissions;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\TestCase;

class OperationsTest extends TestCase
{
    public function test_registry_sync_is_idempotent_and_deprecates_removed_codes(): void
    {
        $this->artisan('uui-rp:sync')->assertSuccessful();
        $count = Config::model('permission')::count();
        $this->artisan('uui-rp:sync')->assertSuccessful();
        $this->assertSame($count, Config::model('permission')::count());
        config(['uui-role-permission.permissions' => ['roles.view' => ['name' => 'Roles', 'scope' => 'ANY']]]);
        $this->artisan('uui-rp:sync')->assertSuccessful();
        $this->assertTrue((bool) Config::model('permission')::where('code', 'orders.view')->value('is_deprecated'));
    }

    public function test_prune_command_removes_expired_assignments(): void
    {
        $assignment = $this->grant($this->user(), $this->role('EXPIRED', []));
        $assignment->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->artisan('uui-rp:prune')->assertSuccessful();
        $this->assertDatabaseMissing(Config::table('assignments'), ['id' => $assignment->getKey()]);
    }

    public function test_jobs_expose_queue_safety_configuration(): void
    {
        $this->assertNotEmpty((new PruneExpiredAssignments)->middleware());
        $this->assertNotEmpty((new PruneAuditLogs)->middleware());
        $this->assertSame([10, 30, 60], (new WarmUserPermissions('user'))->backoff());
        $this->assertSame([10, 30, 60], (new RevokeUserSessions('user'))->backoff());
    }

    public function test_doctor_passes_after_registry_sync(): void
    {
        $this->artisan('uui-rp:sync')->assertSuccessful();
        $this->artisan('uui-rp:doctor')->assertSuccessful();
    }

    public function test_access_removal_dispatches_session_revocation(): void
    {
        Bus::fake();
        $user = $this->user();
        $assignment = $this->grant($user, $this->role('VIEWER', []));
        RoleRevoked::dispatch($assignment, null, null, $assignment->getAttributes());
        Bus::assertDispatched(RevokeUserSessions::class, fn ($job): bool => (string) $job->userId === (string) $user->getKey());
    }
}
