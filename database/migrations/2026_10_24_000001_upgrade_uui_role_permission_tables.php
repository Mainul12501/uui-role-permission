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
        $tenantKey = config('uui-role-permission.tenancy.foreign_key', 'organization_id');

        $this->addColumn($tables['permissions'], 'group', fn (Blueprint $table) => $table->string('group', 80)->nullable());
        $this->addColumn($tables['permissions'], 'scope', fn (Blueprint $table) => $table->string('scope', 16)->default('ANY'));
        $this->addColumn($tables['permissions'], 'is_deprecated', fn (Blueprint $table) => $table->boolean('is_deprecated')->default(false));
        $this->addColumn($tables['permissions'], 'sort_order', fn (Blueprint $table) => $table->unsignedInteger('sort_order')->default(0));

        if (config('uui-role-permission.tenancy.enabled', true)) {
            $this->addKeyColumn($tables['roles'], $tenantKey, (string) config('uui-role-permission.tenancy.key_type', 'uuid'), true);
        }
        $this->addColumn($tables['roles'], 'is_system', fn (Blueprint $table) => $table->boolean('is_system')->default(false));
        $this->addColumn($tables['roles'], 'is_locked', fn (Blueprint $table) => $table->boolean('is_locked')->default(false));
        $this->addColumn($tables['roles'], 'assignable_scope', fn (Blueprint $table) => $table->string('assignable_scope', 16)->default('ANY'));
        $this->addColumn($tables['roles'], 'priority', fn (Blueprint $table) => $table->integer('priority')->default(0));
        $this->addKeyColumn($tables['roles'], 'created_by', (string) config('uui-role-permission.user_key_type', 'uuid'), true);
        $this->addKeyColumn($tables['roles'], 'updated_by', (string) config('uui-role-permission.user_key_type', 'uuid'), true);
        $this->addColumn($tables['roles'], 'deleted_at', fn (Blueprint $table) => $table->softDeletes());

        $this->addKeyColumn($tables['assignments'], 'assigned_by', (string) config('uui-role-permission.user_key_type', 'uuid'), true);
        $this->addColumn($tables['assignments'], 'expires_at', fn (Blueprint $table) => $table->timestamp('expires_at')->nullable());

        $this->backfillPermissionMetadata($tables['permissions']);
        $this->ensureIndexes($tables, $tenantKey);
    }

    public function down(): void
    {
        // Adoption is deliberately additive. Host application data is never removed.
    }

    private function addKeyColumn(string $tableName, string $column, string $type, bool $nullable): void
    {
        $this->addColumn($tableName, $column, function (Blueprint $table) use ($column, $type, $nullable): void {
            if ($type === 'int') {
                $definition = $table->unsignedBigInteger($column);
            } elseif ($type === 'ulid') {
                $definition = $table->ulid($column);
            } else {
                $definition = $table->uuid($column);
            }
            if ($nullable) {
                $definition->nullable();
            }
        });
    }

    private function addColumn(string $tableName, string $column, Closure $callback): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
            return;
        }
        Schema::table($tableName, $callback);
    }

    private function backfillPermissionMetadata(string $tableName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'group')) {
            return;
        }
        $model = config('uui-role-permission.models.permission');
        $model::query()->whereNull('group')->orderBy('code')->chunkById(200, function ($permissions): void {
            foreach ($permissions as $permission) {
                $permission->forceFill(['group' => explode('.', $permission->code, 2)[0]])->save();
            }
        });
    }

    private function ensureIndexes(array $tables, string $tenantKey): void
    {
        if (! Schema::hasTable($tables['assignments'])) {
            return;
        }
        $assignmentIndexes = Schema::getIndexes($tables['assignments']);
        if (! $this->hasIndex($assignmentIndexes, ['expires_at'])) {
            Schema::table($tables['assignments'], fn (Blueprint $table) => $table->index('expires_at', 'uui_rp_assignments_expiry_index'));
        }
        if (! config('uui-role-permission.tenancy.enabled', true)) {
            if (! $this->hasIndex($assignmentIndexes, ['user_id', 'role_id'], true)) {
                Schema::table($tables['assignments'], fn (Blueprint $table) => $table->unique(['user_id', 'role_id'], 'uui_rp_assignments_unique'));
            }

            return;
        }
        if (! $this->hasIndex($assignmentIndexes, ['user_id', $tenantKey])) {
            Schema::table($tables['assignments'], fn (Blueprint $table) => $table->index(['user_id', $tenantKey], 'uui_rp_assignments_user_scope_index'));
        }
        if (! $this->hasIndex($assignmentIndexes, ['user_id', 'role_id', $tenantKey], true)) {
            Schema::table($tables['assignments'], fn (Blueprint $table) => $table->unique(['user_id', 'role_id', $tenantKey], 'uui_rp_assignments_scoped_unique'));
        }
        $this->ensureGlobalUnique($tables['assignments'], ['user_id', 'role_id'], $tenantKey, (string) config('uui-role-permission.tenancy.key_type', 'uuid'), 'uui_rp_assignments_global_unique');

        if (! Schema::hasTable($tables['roles'])) {
            return;
        }
        $roleIndexes = Schema::getIndexes($tables['roles']);
        foreach ($roleIndexes as $index) {
            if ($index['columns'] === ['code'] && $index['unique']) {
                Schema::table($tables['roles'], fn (Blueprint $table) => $table->dropUnique((string) $index['name']));
            }
        }
        $roleIndexes = Schema::getIndexes($tables['roles']);
        if (DB::getDriverName() !== 'mysql' && ! $this->hasIndex($roleIndexes, [$tenantKey, 'code'], true)) {
            $driver = DB::getDriverName();
            $table = $this->identifier($tables['roles'], $driver);
            $tenant = $this->identifier($tenantKey, $driver);
            $name = $this->identifier('uui_rp_roles_scoped_unique', $driver);
            DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} ({$tenant}, \"code\") WHERE {$tenant} IS NOT NULL AND \"deleted_at\" IS NULL");
        }
        $this->ensureGlobalUnique($tables['roles'], ['code'], $tenantKey, (string) config('uui-role-permission.tenancy.key_type', 'uuid'), 'uui_rp_roles_global_unique', true);
    }

    private function ensureGlobalUnique(string $table, array $columns, string $nullableColumn, string $nullableType, string $name, bool $softDeletes = false): void
    {
        $indexes = Schema::getIndexes($table);
        if ($this->hasGlobalIndex($indexes, $columns, $nullableColumn)) {
            return;
        }
        $driver = DB::getDriverName();
        $quotedTable = $this->identifier($table, $driver);
        $quotedName = $this->identifier($name, $driver);
        $quotedColumns = implode(', ', array_map(fn (string $column): string => $this->identifier($column, $driver), $columns));
        $quotedNullable = $this->identifier($nullableColumn, $driver);
        if ($driver === 'mysql') {
            $generated = $name.'_scope_key';
            if (! Schema::hasColumn($table, $generated)) {
                $sentinel = $nullableType === 'int' ? '0' : "'".str_repeat('0', $nullableType === 'ulid' ? 26 : 36)."'";
                $type = $nullableType === 'int' ? 'BIGINT UNSIGNED' : 'CHAR('.($nullableType === 'ulid' ? 26 : 36).')';
                $expression = $softDeletes ? "CASE WHEN `deleted_at` IS NULL THEN COALESCE({$quotedNullable}, {$sentinel}) ELSE NULL END" : "COALESCE({$quotedNullable}, {$sentinel})";
                DB::statement("ALTER TABLE {$quotedTable} ADD COLUMN ".$this->identifier($generated, $driver)." {$type} GENERATED ALWAYS AS ({$expression}) STORED");
            }
            DB::statement("CREATE UNIQUE INDEX {$quotedName} ON {$quotedTable} ({$quotedColumns}, ".$this->identifier($generated, $driver).')');

            return;
        }
        $predicate = $quotedNullable.' IS NULL'.($softDeletes ? ' AND "deleted_at" IS NULL' : '');
        DB::statement("CREATE UNIQUE INDEX {$quotedName} ON {$quotedTable} ({$quotedColumns}) WHERE {$predicate}");
    }

    private function hasIndex(array $indexes, array $columns, bool $unique = false): bool
    {
        foreach ($indexes as $index) {
            $indexed = array_values($index['columns'] ?? []);
            $matches = $indexed === $columns;
            if ($matches && (! $unique || (bool) ($index['unique'] ?? false))) {
                return true;
            }
        }

        return false;
    }

    private function hasGlobalIndex(array $indexes, array $columns, string $nullableColumn): bool
    {
        foreach ($indexes as $index) {
            $indexed = array_values($index['columns'] ?? []);
            if (! (bool) ($index['unique'] ?? false) || count(array_diff($columns, $indexed)) > 0) {
                continue;
            }
            if (! in_array($nullableColumn, $indexed, true) || str_contains((string) ($index['name'] ?? ''), 'global')) {
                return true;
            }
        }

        return false;
    }

    private function identifier(string $identifier, string $driver): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException('Unsafe database identifier: '.$identifier);
        }

        return $driver === 'mysql' ? '`'.$identifier.'`' : '"'.$identifier.'"';
    }
};
