<?php

namespace Mainul\UuiRolePermission\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Mainul\UuiRolePermission\Database\Factories\PermissionFactory;
use Mainul\UuiRolePermission\Models\Concerns\UsesConfiguredKey;
use Mainul\UuiRolePermission\Support\Config;

class Permission extends Model
{
    use HasFactory, UsesConfiguredKey;

    protected $fillable = ['code', 'name', 'description', 'group', 'scope', 'is_deprecated', 'sort_order'];

    protected $casts = ['is_deprecated' => 'boolean', 'sort_order' => 'integer'];

    public function getTable(): string
    {
        return Config::table('permissions');
    }

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Config::model('role'), Config::table('role_permissions'), 'permission_id', 'role_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_deprecated', false);
    }
}
