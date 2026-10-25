<?php

namespace Mainul\UuiRolePermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    use HasUuids;

    protected $table = 'organizations';

    protected $guarded = [];
}
