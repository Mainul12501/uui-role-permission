<?php

namespace Mainul\UuiRolePermission\Tests\Fixtures;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Mainul\UuiRolePermission\Concerns\HasRoles;

class User extends Authenticatable
{
    use HasRoles, HasUuids;

    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password'];
}
