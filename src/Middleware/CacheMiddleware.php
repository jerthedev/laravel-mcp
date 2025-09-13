<?php

namespace JTD\LaravelMCP\Middleware;

use JTD\LaravelMCP\Exceptions\CachedResultException;

class CacheMiddleware extends McpMiddleware
{
    private int $ttl;

    public function __construct(int $ttl = 300) // 5 minutes default
    {
        $this->ttl = $ttl;
    }

    protected function before(array $parameters): array
    {
        $cacheKey = $this->getCacheKey($parameters);

        if ($cached = cache()->get($cacheKey)) {
            throw new CachedResultException($cached);
        }

        return $parameters;
    }

    protected function after(mixed $result, array $parameters): mixed
    {
        $cacheKey = $this->getCacheKey($parameters);
        cache()->put($cacheKey, $result, $this->ttl);

        return $result;
    }

    private function getCacheKey(array $parameters): string
    {
        return 'mcp:cache:'.md5(serialize($parameters));
    }
}
