<?php

namespace Mainul\UuiRolePermission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mainul\UuiRolePermission\Database\Factories\AssignmentFactory;
use Mainul\UuiRolePermission\Models\Concerns\UsesConfiguredKey;
use Mainul\UuiRolePermission\Support\Config;

class RoleAssignment extends Model
{
    use HasFactory, UsesConfiguredKey;

    public function __construct(array $attributes = [])
    {
        $this->fillable[] = Config::tenantKey();
        $this->fillable = array_values(array_unique($this->fillable));
        parent::__construct($attributes);
    }

    protected $fillable = ['user_id', 'role_id', 'organization_id', 'assigned_by', 'expires_at'];

    protected $casts = ['expires_at' => 'datetime'];

    public function getTable(): string
    {
        return Config::table('assignments');
    }

    protected static function newFactory(): AssignmentFactory
    {
        return AssignmentFactory::new();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Config::model('user'));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Config::model('role'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo((string) Config::get('tenancy.model'), Config::tenantKey());
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Config::model('user'), 'assigned_by');
    }

    public function scopeCurrent($query)
    {
        return $query->where(function ($builder): void {
            $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }
}
