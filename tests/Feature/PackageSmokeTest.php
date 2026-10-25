<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Mainul\UuiRolePermission\Tests\TestCase;

class PackageSmokeTest extends TestCase
{
    public function test_provider_registers_package_commands(): void
    {
        $commands = Artisan::all();
        $this->assertArrayHasKey('uui-rp:sync', $commands);
        $this->assertArrayHasKey('uui-rp:doctor', $commands);
    }

    public function test_package_migrations_create_core_tables(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('user_role_assignments'));
        $this->assertTrue(Schema::hasTable('rp_audit_logs'));
    }

    public function test_package_schedule_contains_expiry_pruning(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command ?? '')->implode('\n');
        $this->assertStringContainsString('uui-rp:prune', $commands);
    }
}
