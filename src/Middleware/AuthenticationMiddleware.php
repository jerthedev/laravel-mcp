<?php

namespace JTD\LaravelMCP\Middleware;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthenticationMiddleware extends McpMiddleware
{
    protected function before(array $parameters): array
    {
        if (! auth()->check()) {
            throw new UnauthorizedHttpException('Bearer', 'Authentication required');
        }

        return $parameters;
    }
}
