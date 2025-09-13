<?php

namespace JTD\LaravelMCP\Contracts;

interface McpMiddlewareInterface
{
    /**
     * Handle an incoming MCP request.
     *
     * @param  array  $parameters  The request parameters
     * @param  \Closure  $next  The next middleware in the chain
     * @return mixed The response from the middleware chain
     */
    public function handle(array $parameters, \Closure $next): mixed;
}
