<?php

namespace Mainul\UuiRolePermission\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Mainul\UuiRolePermission\Contracts\PermissionResolver;
use Mainul\UuiRolePermission\Support\Config;

class CacheCommand extends Command
{
    protected $signature = 'uui-rp:cache {operation : flush or warm} {--user= : User id to warm}';

    protected $description = 'Flush or warm the role permission resolver cache.';

    public function handle(PermissionResolver $resolver): int
    {
        $operation = $this->argument('operation');
        $cache = config('uui-role-permission.cache.store') ? Cache::store(config('uui-role-permission.cache.store')) : Cache::store();
        if ($operation === 'flush') {
            $key = config('uui-role-permission.cache.prefix', 'uui-rp').':version';
            $cache->add($key, 1, now()->addYears(10));
            $cache->increment($key);
            $this->info('Permission cache version advanced.');

            return self::SUCCESS;
        }
        if ($operation !== 'warm') {
            $this->error('Operation must be flush or warm.');

            return self::INVALID;
        }
        $userClass = Config::model('user');
        $query = $userClass::query();
        if ($this->option('user')) {
            $query->whereKey($this->option('user'));
        }
        $count = 0;
        $query->chunkById(200, function ($users) use ($resolver, &$count): void {
            foreach ($users as $user) {
                $resolver->resolve($user);
                $count++;
            }
        }, (new $userClass)->getKeyName());
        $this->info("Warmed permissions for {$count} user(s).");

        return self::SUCCESS;
    }
}
