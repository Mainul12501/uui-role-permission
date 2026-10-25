<?php

namespace Mainul\UuiRolePermission\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mainul\UuiRolePermission\Support\Config;

class AssignmentFactory extends Factory
{
    public function modelName()
    {
        return Config::model('assignment');
    }

    public function definition(): array
    {
        return ['expires_at' => null];
    }
}
