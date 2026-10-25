<?php

namespace Mainul\UuiRolePermission\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Mainul\UuiRolePermission\Models\Concerns\UsesConfiguredKey;
use Mainul\UuiRolePermission\Support\Config;

class Membership extends Model
{
    use UsesConfiguredKey;

    public function __construct(array $attributes = [])
    {
        $this->fillable[] = Config::tenantKey();
        $this->fillable = array_values(array_unique($this->fillable));
        parent::__construct($attributes);
    }

    protected $fillable = ['user_id', 'organization_id', 'status'];

    public function getTable(): string
    {
        return Config::table('memberships');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Config::model('user'));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo((string) Config::get('tenancy.model'), Config::tenantKey());
    }
}
