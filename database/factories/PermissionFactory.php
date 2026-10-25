<?php

namespace Mainul\UuiRolePermission\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mainul\UuiRolePermission\Support\Config;

class PermissionFactory extends Factory
{
    public function modelName()
    {
        return Config::model('permission');
    }

    public function definition(): array
    {
        $code = strtolower($this->faker->unique()->bothify('module.????####'));

        return ['code' => $code, 'name' => $code, 'group' => 'module', 'scope' => 'ANY'];
    }
}
