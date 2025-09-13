<?php

namespace JTD\LaravelMCP\Middleware;

use JTD\LaravelMCP\Contracts\McpMiddlewareInterface;

abstract class McpMiddleware implements McpMiddlewareInterface
{
    public function handle(array $parameters, \Closure $next): mixed
    {
        $parameters = $this->before($parameters);
        $result = $next($parameters);

        return $this->after($result, $parameters);
    }

    protected function before(array $parameters): array
    {
        return $parameters;
    }

    protected function after(mixed $result, array $parameters): mixed
    {
        return $result;
    }
}
