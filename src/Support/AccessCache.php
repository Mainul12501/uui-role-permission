<?php

namespace Mainul\UuiRolePermission\Support;

use Illuminate\Contracts\Cache\Repository;

class AccessCache
{
    public function __construct(private Repository $cache) {}

    public function version(): int
    {
        return max(1, (int) $this->cache->get($this->versionKey(), 1));
    }

    public function userKey($user): string
    {
        $id = is_object($user) ? $user->getKey() : $user;

        return $this->prefix().':v'.$this->version().':u:'.$id;
    }

    public function remember($user, callable $callback): array
    {
        $ttl = (int) Config::get('cache.ttl', 600);

        return $ttl > 0 ? $this->cache->remember($this->userKey($user), $ttl, $callback) : $callback();
    }

    public function forget($user): void
    {
        $this->cache->forget($this->userKey($user));
    }

    public function bump(): int
    {
        $this->cache->add($this->versionKey(), 1, now()->addYears(10));

        return (int) $this->cache->increment($this->versionKey());
    }

    private function prefix(): string
    {
        return (string) Config::get('cache.prefix', 'uui-rp');
    }

    private function versionKey(): string
    {
        return $this->prefix().':version';
    }
}
