<?php

namespace Mainul\UuiRolePermission\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mainul\UuiRolePermission\Support\Config;

class RoleFactory extends Factory
{
    public function modelName()
    {
        return Config::model('role');
    }

    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->bothify('ROLE_????_####'));

        return ['code' => $code, 'name' => str_replace('_', ' ', $code), 'assignable_scope' => 'ANY', 'priority' => 0];
    }
}
