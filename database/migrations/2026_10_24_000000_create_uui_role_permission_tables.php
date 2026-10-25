<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = config('uui-role-permission.tables');
        $keyType = config('uui-role-permission.key_type', 'uuid');
        $userKeyType = config('uui-role-permission.user_key_type', 'uuid');
        $tenantKeyType = config('uui-role-permission.tenancy.key_type', 'uuid');
        $tenantKey = config('uui-role-permission.tenancy.foreign_key', 'organization_id');
        $tenantEnabled = (bool) config('uui-role-permission.tenancy.enabled', true);

        if (! Schema::hasTable($tables['permissions'])) {
            Schema::create($tables['permissions'], function (Blueprint $table) use ($keyType): void {
                $this->idColumn($table, $keyType);
                $table->string('code', 120)->unique();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('group', 80)->nullable();
                $table->string('scope', 16)->default('ANY');
                $table->boolean('is_deprecated')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable($tables['roles'])) {
            Schema::create($tables['roles'], function (Blueprint $table) use ($keyType, $tenantKeyType, $userKeyType, $tenantKey, $tenantEnabled, $tables): void {
                $this->idColumn($table, $keyType);
                if ($tenantEnabled) {
                    $this->nullableKey($table, $tenantKey, $tenantKeyType);
                }
                $table->string('code', 120);
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->boolean('is_locked')->default(false);
                $table->string('assignable_scope', 16)->default('ANY');
                $table->integer('priority')->default(0);
                $this->nullableKey($table, 'created_by', $userKeyType);
                $this->nullableKey($table, 'updated_by', $userKeyType);
                $table->timestamps();
                $table->softDeletes();
                if ($tenantEnabled) {
                    $table->foreign($tenantKey)->references((string) config('uui-role-permission.tenancy.primary_key', 'id'))->on((string) config('uui-role-permission.tenancy.table'))->restrictOnDelete();
                } else {
                    $table->unique('code', 'uui_rp_roles_code_unique');
                }
                $table->foreign('created_by')->references('id')->on($tables['users'])->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on($tables['users'])->nullOnDelete();
            });
            if ($tenantEnabled) {
                $this->createNullableUniqueIndex($tables['roles'], ['code'], $tenantKey, $tenantKeyType, 'uui_rp_roles_global_unique', true);
                if (DB::getDriverName() !== 'mysql') {
                    $driver = DB::getDriverName();
                    $table = $this->identifier($tables['roles'], $driver);
                    $index = $this->identifier('uui_rp_roles_scoped_unique', $driver);
                    $tenant = $this->identifier($tenantKey, $driver);
                    DB::statement("CREATE UNIQUE INDEX {$index} ON {$table} ({$tenant}, \"code\") WHERE {$tenant} IS NOT NULL AND \"deleted_at\" IS NULL");
                }
            }
        }

        if (! Schema::hasTable($tables['role_permissions'])) {
            Schema::create($tables['role_permissions'], function (Blueprint $table) use ($keyType, $tables): void {
                $this->key($table, 'role_id', $keyType);
                $this->key($table, 'permission_id', $keyType);
                $table->primary(['role_id', 'permission_id']);
                $table->foreign('role_id')->references('id')->on($tables['roles'])->cascadeOnDelete();
                $table->foreign('permission_id')->references('id')->on($tables['permissions'])->cascadeOnDelete();
            });
        }

        if ($tenantEnabled && ! Schema::hasTable($tables['memberships'])) {
            Schema::create($tables['memberships'], function (Blueprint $table) use ($keyType, $tenantKeyType, $userKeyType, $tenantKey, $tables): void {
                $this->idColumn($table, $keyType);
                $this->key($table, $tenantKey, $tenantKeyType);
                $this->key($table, 'user_id', $userKeyType);
                $table->string('status', 24)->default('ACTIVE');
                $table->timestamps();
                $table->unique([$tenantKey, 'user_id'], 'uui_rp_membership_tenant_user_unique');
                $table->unique([$tenantKey, 'id'], 'uui_rp_membership_tenant_id_unique');
                $table->foreign($tenantKey)->references((string) config('uui-role-permission.tenancy.primary_key', 'id'))->on((string) config('uui-role-permission.tenancy.table'))->restrictOnDelete();
                $table->foreign('user_id')->references('id')->on($tables['users'])->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable($tables['assignments'])) {
            Schema::create($tables['assignments'], function (Blueprint $table) use ($keyType, $userKeyType, $tenantKeyType, $tenantKey, $tenantEnabled, $tables): void {
                $this->idColumn($table, $keyType);
                $this->key($table, 'user_id', $userKeyType);
                $this->key($table, 'role_id', $keyType);
                if ($tenantEnabled) {
                    $this->nullableKey($table, $tenantKey, $tenantKeyType);
                }
                $this->nullableKey($table, 'assigned_by', $userKeyType);
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index($tenantEnabled ? ['user_id', $tenantKey] : ['user_id'], 'uui_rp_assignments_user_scope_index');
                $table->index('expires_at', 'uui_rp_assignments_expiry_index');
                $table->foreign('user_id')->references('id')->on($tables['users'])->cascadeOnDelete();
                $table->foreign('role_id')->references('id')->on($tables['roles'])->restrictOnDelete();
                $table->foreign('assigned_by')->references('id')->on($tables['users'])->nullOnDelete();
                if ($tenantEnabled) {
                    $table->unique(['user_id', 'role_id', $tenantKey], 'uui_rp_assignments_scoped_unique');
                    $table->foreign($tenantKey)->references((string) config('uui-role-permission.tenancy.primary_key', 'id'))->on((string) config('uui-role-permission.tenancy.table'))->restrictOnDelete();
                    if (config('uui-role-permission.tenancy.require_membership', true)) {
                        $table->foreign([$tenantKey, 'user_id'], 'uui_rp_assignment_membership_fk')->references([$tenantKey, 'user_id'])->on($tables['memberships'])->restrictOnDelete();
                    }
                } else {
                    $table->unique(['user_id', 'role_id'], 'uui_rp_assignments_unique');
                }
            });
            if ($tenantEnabled) {
                $this->createNullableUniqueIndex($tables['assignments'], ['user_id', 'role_id'], $tenantKey, $tenantKeyType, 'uui_rp_assignments_global_unique');
            }
        }

        if (config('uui-role-permission.audit.driver', 'database') === 'database' && ! Schema::hasTable($tables['audit_logs'])) {
            Schema::create($tables['audit_logs'], function (Blueprint $table) use ($keyType, $tenantKeyType, $userKeyType): void {
                $this->idColumn($table, $keyType);
                $this->nullableKey($table, 'tenant_id', $tenantKeyType);
                $this->nullableKey($table, 'actor_id', $userKeyType);
                $table->string('action', 120);
                $table->string('subject_type')->nullable();
                $table->string('subject_id', 64)->nullable();
                $table->json('before')->nullable();
                $table->json('after')->nullable();
                $table->text('reason')->nullable();
                $table->string('ip', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index(['tenant_id', 'created_at'], 'uui_rp_audit_tenant_created_index');
                $table->index(['subject_type', 'subject_id'], 'uui_rp_audit_subject_index');
            });
        }
    }

    public function down(): void
    {
        $tables = config('uui-role-permission.tables');
        foreach (['audit_logs', 'assignments', 'memberships', 'role_permissions', 'roles', 'permissions'] as $name) {
            Schema::dropIfExists($tables[$name]);
        }
    }

    private function createNullableUniqueIndex(string $table, array $columns, string $nullableColumn, string $nullableType, string $index, bool $softDeletes = false): void
    {
        $driver = DB::getDriverName();
        $quotedTable = $this->identifier($table, $driver);
        $quotedIndex = $this->identifier($index, $driver);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->identifier($column, $driver), $columns));
        $quotedNullable = $this->identifier($nullableColumn, $driver);
        if ($driver === 'mysql') {
            $generated = $index.'_scope_key';
            $quotedGenerated = $this->identifier($generated, $driver);
            $sentinel = $nullableType === 'int' ? '0' : "'".str_repeat('0', $nullableType === 'ulid' ? 26 : 36)."'";
            $type = $nullableType === 'int' ? 'BIGINT UNSIGNED' : 'CHAR('.($nullableType === 'ulid' ? 26 : 36).')';
            $expression = $softDeletes ? "CASE WHEN `deleted_at` IS NULL THEN COALESCE({$quotedNullable}, {$sentinel}) ELSE NULL END" : "COALESCE({$quotedNullable}, {$sentinel})";
            DB::statement("ALTER TABLE {$quotedTable} ADD COLUMN {$quotedGenerated} {$type} GENERATED ALWAYS AS ({$expression}) STORED");
            DB::statement("CREATE UNIQUE INDEX {$quotedIndex} ON {$quotedTable} ({$quotedColumns}, {$quotedGenerated})");

            return;
        }
        $predicate = $softDeletes ? "{$quotedNullable} IS NULL AND \"deleted_at\" IS NULL" : "{$quotedNullable} IS NULL";
        DB::statement("CREATE UNIQUE INDEX {$quotedIndex} ON {$quotedTable} ({$quotedColumns}) WHERE {$predicate}");
    }

    private function identifier(string $identifier, string $driver): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe database identifier: '.$identifier);
        }

        return $driver === 'mysql' ? '`'.$identifier.'`' : '"'.$identifier.'"';
    }

    private function idColumn(Blueprint $table, string $type): void
    {
        if ($type === 'int') {
            $table->bigIncrements('id');
        } elseif ($type === 'ulid') {
            $table->ulid('id')->primary();
        } else {
            $table->uuid('id')->primary();
        }
    }

    private function key(Blueprint $table, string $column, string $type): void
    {
        if ($type === 'int') {
            $table->unsignedBigInteger($column);
        } elseif ($type === 'ulid') {
            $table->ulid($column);
        } else {
            $table->uuid($column);
        }
    }

    private function nullableKey(Blueprint $table, string $column, string $type): void
    {
        if ($type === 'int') {
            $table->unsignedBigInteger($column)->nullable();
        } elseif ($type === 'ulid') {
            $table->ulid($column)->nullable();
        } else {
            $table->uuid($column)->nullable();
        }
    }
};
