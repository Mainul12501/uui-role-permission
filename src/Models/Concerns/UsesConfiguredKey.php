<?php

namespace Mainul\UuiRolePermission\Models\Concerns;

use Illuminate\Support\Str;

trait UsesConfiguredKey
{
    public static function bootUsesConfiguredKey(): void
    {
        static::creating(function ($model): void {
            if ($model->getKey() !== null || config('uui-role-permission.key_type', 'uuid') === 'int') {
                return;
            }

            $model->setAttribute($model->getKeyName(), config('uui-role-permission.key_type') === 'ulid' ? (string) Str::ulid() : (string) Str::uuid());
        });
    }

    public function getIncrementing(): bool
    {
        return config('uui-role-permission.key_type', 'uuid') === 'int';
    }

    public function getKeyType(): string
    {
        return config('uui-role-permission.key_type', 'uuid') === 'int' ? 'int' : 'string';
    }
}
