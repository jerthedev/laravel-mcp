<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for handling Server-Sent Events (SSE) requests.
 *
 * This middleware sets up the proper headers and environment
 * for SSE connections in MCP HTTP transport scenarios.
 */
class HandleSseRequest
{
    /**
     * Handle an incoming request for SSE.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if this is an SSE request
        if ($this->isSseRequest($request)) {
            // Set up environment for SSE
            $this->setupSseEnvironment();
            
            // Process the request
            $response = $next($request);
            
            // Ensure proper SSE headers
            if ($response instanceof Response) {
                $this->setSseHeaders($response);
            }
            
            return $response;
        }

        return $next($request);
    }

    /**
     * Check if the request is for Server-Sent Events.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function isSseRequest(Request $request): bool
    {
        // Check for SSE-specific headers or query parameters
        return $request->header('Accept') === 'text/event-stream' ||
               $request->query('stream') === 'sse' ||
               $request->is('*/sse', '*/stream', '*/events');
    }

    /**
     * Set up the environment for SSE connections.
     */
    protected function setupSseEnvironment(): void
    {
        // Disable PHP's output buffering
        while (ob_get_level()) {
            ob_end_flush();
        }

        // Disable Apache's mod_deflate compression
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }

        // Set execution time limit for long-running SSE connections
        set_time_limit(0);

        // Disable PHP's automatic gzip compression
        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');
    }

    /**
     * Set proper SSE headers on the response.
     *
     * @param  Response  $response
     */
    protected function setSseHeaders(Response $response): void
    {
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('X-Accel-Buffering', 'no'); // Disable Nginx buffering
        
        // CORS headers for cross-origin SSE requests
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Headers', 'Cache-Control');
        $response->headers->set('Access-Control-Expose-Headers', 'Content-Type');
    }
}