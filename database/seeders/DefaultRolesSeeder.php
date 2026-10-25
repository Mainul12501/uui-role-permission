<?php

namespace Mainul\UuiRolePermission\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DefaultRolesSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('uui-rp:sync');
    }
}
