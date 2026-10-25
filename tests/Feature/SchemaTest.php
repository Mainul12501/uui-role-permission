<?php

namespace Mainul\UuiRolePermission\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class SchemaTest extends TestCase
{
    public static function keyTypes(): array
    {
        return [['uuid'], ['ulid'], ['int']];
    }

    #[DataProvider('keyTypes')]
    public function test_fresh_schema_supports_each_key_type(string $type): void
    {
        $this->dropPackageTables();
        Schema::dropIfExists('users');
        config(['uui-role-permission.key_type' => $type, 'uui-role-permission.user_key_type' => $type]);
        Schema::create('users', function (Blueprint $table) use ($type): void {
            if ($type === 'int') {
                $table->bigIncrements('id');
            } elseif ($type === 'ulid') {
                $table->ulid('id')->primary();
            } else {
                $table->uuid('id')->primary();
            }
        });
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_24_000000_create_uui_role_permission_tables.php';
        $migration->up();
        $permissionClass = Config::model('permission');
        $permission = $permissionClass::create(['code' => 'schema.test', 'name' => 'Schema test']);
        $this->assertNotNull($permission->getKey());
        $this->assertSame($type === 'int', $permission->getIncrementing());
        $migration->down();
        $this->assertFalse(Schema::hasTable('permissions'));
    }

    public function test_adoption_adds_only_missing_columns_and_preserves_rows(): void
    {
        $this->dropPackageTables();
        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('user_role_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('role_id');
            $table->timestamps();
        });
        $permissionClass = Config::model('permission');
        $permission = $permissionClass::create(['code' => 'existing.permission', 'name' => 'Existing']);
        $migration = require dirname(__DIR__, 2).'/database/migrations/2026_10_24_000001_upgrade_uui_role_permission_tables.php';
        $migration->up();
        $this->assertTrue(Schema::hasColumns('permissions', ['group', 'scope', 'is_deprecated', 'sort_order']));
        $this->assertTrue(Schema::hasColumns('roles', ['is_system', 'is_locked', 'assignable_scope', 'priority', 'deleted_at']));
        $this->assertTrue(Schema::hasColumns('user_role_assignments', ['assigned_by', 'expires_at']));
        $this->assertSame('existing.permission', $permissionClass::findOrFail($permission->getKey())->code);
    }

    private function dropPackageTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['rp_audit_logs', 'user_role_assignments', 'organization_user', 'role_permissions', 'roles', 'permissions'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }
}
