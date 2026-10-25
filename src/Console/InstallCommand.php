<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallCommand extends Command
{
    protected $signature = 'uui-rp:install {--force : Overwrite published files and run migrations} {--publish-migrations} {--publish-seeder}';

    protected $description = 'Install and initialize UUI role and permission tables.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'uui-role-permission-config', '--force' => $this->option('force')]);
        if ($this->option('publish-migrations')) {
            $this->call('vendor:publish', ['--tag' => 'uui-role-permission-migrations', '--force' => $this->option('force')]);
        }
        if ($this->option('publish-seeder')) {
            $this->call('vendor:publish', ['--tag' => 'uui-role-permission-seeder', '--force' => $this->option('force')]);
        }
        if (config('uui-role-permission.run_migrations', true)) {
            Artisan::call('migrate', ['--force' => $this->option('force')]);
            $this->line(Artisan::output());
        }
        $this->call('uui-rp:sync');
        $this->info('Role permission package installed.');
        $this->line('Scheduler: * * * * * php artisan schedule:run');
        $this->line('Queue: php artisan queue:work --queue='.config('uui-role-permission.queue.queue', 'default'));

        return self::SUCCESS;
    }
}
