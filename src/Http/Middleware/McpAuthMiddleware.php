<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Check if authentication is required for MCP endpoints
        if (! config('laravel-mcp.auth.enabled', false)) {
            return $next($request);
        }

        // Check for API key authentication
        $apiKey = $request->header('X-MCP-API-Key') ?? $request->input('api_key');
        $configuredKey = config('laravel-mcp.auth.api_key');

        if (empty($configuredKey)) {
            return $next($request);
        }

        if ($apiKey !== $configuredKey) {
            return response()->json([
                'error' => [
                    'code' => -32001,
                    'message' => 'Invalid API key',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
