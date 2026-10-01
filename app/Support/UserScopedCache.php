<?php

namespace App\Support;

use Closure;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Cache;

class UserScopedCache
{
    public static function remember(array $tags, string $key, int $ttl, Closure $callback)
    {
        if (Cache::getStore() instanceof TaggableStore) {
            return Cache::tags($tags)->remember($key, $ttl, $callback);
        }

        return Cache::remember(self::fallbackKey($tags, $key), $ttl, $callback);
    }

    public static function flush(array $tags, array $knownKeys = []): void
    {
        if (Cache::getStore() instanceof TaggableStore) {
            Cache::tags($tags)->flush();
            return;
        }

        Cache::forever(self::versionKey($tags), self::version($tags) + 1);
    }

    public static function fallbackKey(array $tags, string $key): string
    {
        return implode(':', $tags) . ':v' . self::version($tags) . ':' . $key;
    }

    protected static function version(array $tags): int
    {
        return (int) Cache::get(self::versionKey($tags), 1);
    }

    protected static function versionKey(array $tags): string
    {
        return implode(':', $tags) . ':version';
    }
}
