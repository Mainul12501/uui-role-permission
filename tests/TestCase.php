<?php

namespace Mainul\UuiRolePermission\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mainul\UuiRolePermission\Support\Config;
use Mainul\UuiRolePermission\Testing\InteractsWithRolePermissions;
use Mainul\UuiRolePermission\Tests\Fixtures\User;
use Mainul\UuiRolePermission\UuiRolePermissionServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use InteractsWithRolePermissions;

    protected bool $tenancyEnabled = false;

    protected function getPackageProviders($app): array
    {
        return [UuiRolePermissionServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $connection = getenv('DB_CONNECTION') ?: 'sqlite';
        $database = getenv('DB_DATABASE') ?: ':memory:';
        $app['config']->set('database.default', 'testing');
        if ($connection === 'sqlite') {
            $configuration = ['driver' => 'sqlite', 'database' => $database === 'testbench' ? ':memory:' : $database, 'prefix' => '', 'foreign_key_constraints' => true];
        } elseif ($connection === 'pgsql') {
            $configuration = ['driver' => 'pgsql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '5432', 'database' => $database, 'username' => getenv('DB_USERNAME') ?: 'testbench', 'password' => getenv('DB_PASSWORD') ?: 'testbench', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer'];
        } else {
            $configuration = ['driver' => 'mysql', 'host' => getenv('DB_HOST') ?: '127.0.0.1', 'port' => getenv('DB_PORT') ?: '3306', 'database' => $database, 'username' => getenv('DB_USERNAME') ?: 'testbench', 'password' => getenv('DB_PASSWORD') ?: 'testbench', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => ''];
        }
        $app['config']->set('database.connections.testing', $configuration);
        $app['config']->set('uui-role-permission.tenancy.enabled', $this->tenancyEnabled);
        $app['config']->set('uui-role-permission.audit.driver', 'database');
        $app['config']->set('uui-role-permission.models.user', User::class);
        $app['config']->set('uui-role-permission.queue.audit_async', false);
        $app['config']->set('uui-role-permission.permissions', $this->permissionRegistry());
    }

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        foreach (['rp_audit_logs', 'user_role_assignments', 'organization_user', 'role_permissions', 'roles', 'permissions', 'organizations', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('status')->default('ACTIVE');
            $table->timestamps();
        });
        if ($this->tenancyEnabled) {
            Schema::create('organizations', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('status')->default('ACTIVE');
                $table->timestamps();
            });
        }
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
    }

    protected function permissionRegistry(): array
    {
        $codes = ['permissions.view', 'roles.view', 'roles.manage', 'users.view', 'users.manage', 'users.assign_roles', 'audit.view', 'orders.view', 'orders.manage'];

        return array_fill_keys($codes, ['name' => 'Test permission', 'group' => 'test', 'scope' => 'ANY']);
    }

    protected function user(string $email = 'user@example.test'): User
    {
        return User::create(['name' => strtok($email, '@'), 'email' => $email, 'password' => 'x']);
    }

    protected function permission(string $code, string $scope = 'ANY')
    {
        $class = Config::model('permission');

        return $class::firstOrCreate(['code' => $code], ['name' => $code, 'group' => 'test', 'scope' => $scope]);
    }

    protected function role(string $code, array $permissions = [], array $attributes = [])
    {
        $class = Config::model('role');
        $role = $class::create(array_merge(['code' => $code, 'name' => $code, 'priority' => 10, 'assignable_scope' => 'ANY'], $attributes));
        $protected = array_intersect_key($attributes, array_flip(['is_system', 'is_locked']));
        if ($protected) {
            $role->forceFill($protected)->save();
        }
        $role->permissions()->sync(array_map(fn (string $code) => $this->permission($code)->getKey(), $permissions));

        return $role;
    }

    protected function grant(User $user, $role, $tenant = null)
    {
        $class = Config::model('assignment');
        $attributes = ['user_id' => $user->getKey(), 'role_id' => $role->getKey()];
        if (Config::tenancyEnabled()) {
            $attributes[Config::tenantKey()] = is_object($tenant) ? $tenant->getKey() : $tenant;
        }

        return $class::create($attributes);
    }
}
