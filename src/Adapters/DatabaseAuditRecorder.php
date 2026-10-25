<?php

namespace Mainul\UuiRolePermission\Adapters;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mainul\UuiRolePermission\Contracts\AuditRecorder;
use Mainul\UuiRolePermission\Support\Config;

class DatabaseAuditRecorder implements AuditRecorder
{
    public function record(string $action, $subject, $actor = null, ?string $reason = null, ?array $before = null, ?array $after = null): void
    {
        $attributes = [
            'actor_id' => $actor ? $actor->getKey() : null,
            'action' => $action, 'subject_type' => is_object($subject) ? get_class($subject) : null,
            'subject_id' => is_object($subject) ? $subject->getKey() : null, 'reason' => $reason,
            'before' => $before !== null ? json_encode($before) : null, 'after' => $after !== null ? json_encode($after) : (is_object($subject) ? json_encode($subject->getAttributes()) : null),
            'tenant_id' => is_object($subject) ? $subject->{Config::tenantKey()} : null,
            'ip' => app()->bound('request') ? request()->ip() : null,
            'user_agent' => app()->bound('request') ? request()->userAgent() : null, 'created_at' => now(),
        ];
        if (Config::keyType() !== 'int') {
            $attributes = ['id' => Config::keyType() === 'ulid' ? (string) Str::ulid() : (string) Str::uuid()] + $attributes;
        }
        DB::table(Config::table('audit_logs'))->insert($attributes);
    }
}
