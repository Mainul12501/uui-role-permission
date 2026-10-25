<?php

namespace Mainul\UuiRolePermission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Mainul\UuiRolePermission\Database\Factories\RoleFactory;
use Mainul\UuiRolePermission\Models\Concerns\UsesConfiguredKey;
use Mainul\UuiRolePermission\Support\Config;

class Role extends Model
{
    use HasFactory, SoftDeletes, UsesConfiguredKey;

    protected $fillable = ['organization_id', 'code', 'name', 'description', 'assignable_scope', 'priority', 'created_by', 'updated_by'];

    protected $casts = ['is_system' => 'boolean', 'is_locked' => 'boolean', 'priority' => 'integer'];

    public function __construct(array $attributes = [])
    {
        $this->fillable[] = Config::tenantKey();
        $this->fillable = array_values(array_unique($this->fillable));
        parent::__construct($attributes);
    }

    public function getTable(): string
    {
        return Config::table('roles');
    }

    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Config::model('permission'), Config::table('role_permissions'), 'role_id', 'permission_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Config::model('assignment'), 'role_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo((string) Config::get('tenancy.model'), Config::tenantKey());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Config::model('user'), 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(Config::model('user'), 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull($this->getQualifiedDeletedAtColumn());
    }

    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    public function scopeForTenant($query, $tenant)
    {
        $id = is_object($tenant) && method_exists($tenant, 'getKey') ? $tenant->getKey() : $tenant;

        return $query->where(function ($builder) use ($id): void {
            $builder->whereNull(Config::tenantKey())->orWhere(Config::tenantKey(), $id);
        });
    }
}
