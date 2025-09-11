<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class McpCorsMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    private function handlePreflightRequest(Request $request): SymfonyResponse
    {
        $response = response('', 204);

        return $this->addCorsHeaders($request, $response);
    }

    private function addCorsHeaders(Request $request, SymfonyResponse $response): SymfonyResponse
    {
        $allowedOrigins = config('laravel-mcp.cors.allowed_origins', ['*']);
        $allowedMethods = config('laravel-mcp.cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        $allowedHeaders = config('laravel-mcp.cors.allowed_headers', [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-MCP-API-Key',
        ]);
        $maxAge = config('laravel-mcp.cors.max_age', 86400);

        // Determine the allowed origin
        $origin = $request->header('Origin');
        if (in_array('*', $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
        } elseif (in_array($origin, $allowedOrigins)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
        }

        $response->headers->set('Access-Control-Allow-Methods', implode(', ', $allowedMethods));
        $response->headers->set('Access-Control-Allow-Headers', implode(', ', $allowedHeaders));
        $response->headers->set('Access-Control-Max-Age', (string) $maxAge);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');

        return $response;
    }
}
