<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\Fixtures\Tenant;
use Mainul\UuiRolePermission\Tests\TenantTestCase;

class TenantAdoptionTest extends TenantTestCase
{
    public function test_adoption_replaces_global_code_uniqueness_with_tenant_aware_uniqueness(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['rp_audit_logs', 'user_role_assignments', 'organization_user', 'role_permissions', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->uuid('organization_id')->nullable();
            $table->timestamps();
        });
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_24_000001_upgrade_uui_role_permission_tables.php';
        $migration->up();

        $first = Tenant::create(['name' => 'First']);
        $second = Tenant::create(['name' => 'Second']);
        $roleClass = Config::model('role');
        $roleClass::create(['code' => 'MANAGER', 'name' => 'First manager', Config::tenantKey() => $first->getKey()]);
        $roleClass::create(['code' => 'MANAGER', 'name' => 'Second manager', Config::tenantKey() => $second->getKey()]);
        $this->assertSame(2, $roleClass::where('code', 'MANAGER')->count());
    }
}
