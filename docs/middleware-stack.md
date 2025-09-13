# Middleware Stack

The Laravel MCP package provides a comprehensive middleware stack designed for production-ready security, monitoring, and reliability. This document covers all middleware components, their configuration, and usage patterns.

## Overview

The middleware stack provides:

- **Authentication and Authorization**: Multi-provider authentication with role-based access
- **Security**: CORS handling, rate limiting, and input validation  
- **Monitoring**: Request/response logging and performance tracking
- **Error Handling**: Centralized error processing and secure error responses
- **Specialized Features**: Server-sent events, WebSocket upgrades, and more

## Middleware Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Incoming Request                       │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│              HandleSseRequest                               │
│          (Server-Sent Events Support)                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│               McpCorsMiddleware                             │
│            (CORS Headers & Preflight)                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│               McpAuthMiddleware                             │
│          (Authentication & Authorization)                  │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│            McpRateLimitMiddleware                           │
│              (Rate Limiting Protection)                    │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│            McpValidationMiddleware                          │
│              (Request Validation)                          │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│             McpLoggingMiddleware                            │
│            (Request/Response Logging)                      │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│           McpErrorHandlingMiddleware                        │
│             (Centralized Error Handling)                   │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                MCP Request Handler                          │
│             (Tools, Resources, Prompts)                    │
└─────────────────────────────────────────────────────────────┘
```

## Core Middleware Components

### 1. HandleSseRequest

Handles Server-Sent Events (SSE) connections for real-time updates.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HandleSseRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Check if this is an SSE request
        if ($request->header('Accept') === 'text/event-stream') {
            return $this->handleSseConnection($request);
        }
        
        return $next($request);
    }
    
    private function handleSseConnection(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () use ($request) {
            // Set SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            
            $eventStream = app('mcp.event_stream');
            $clientId = $request->query('client_id', uniqid());
            
            // Subscribe to events
            $eventStream->subscribe($clientId, $request->query('events', []));
            
            // Keep connection alive and send events
            while (true) {
                $events = $eventStream->getEvents($clientId);
                
                foreach ($events as $event) {
                    echo "data: " . json_encode($event) . "\n\n";
                    flush();
                }
                
                // Check if connection is still alive
                if (connection_aborted()) {
                    $eventStream->unsubscribe($clientId);
                    break;
                }
                
                sleep(1);
            }
        });
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'sse' => [
    'enabled' => true,
    'events' => [
        'tool_executed',
        'resource_accessed',
        'request_processed',
    ],
    'client_timeout' => 300,
    'heartbeat_interval' => 30,
],
```

### 2. McpCorsMiddleware

Handles Cross-Origin Resource Sharing (CORS) for web clients.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class McpCorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Handle preflight OPTIONS request
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflightRequest($request);
        }
        
        $response = $next($request);
        
        return $this->addCorsHeaders($response, $request);
    }
    
    private function handlePreflightRequest(Request $request)
    {
        $allowedOrigins = config('laravel-mcp.cors.allowed_origins', ['*']);
        $allowedMethods = config('laravel-mcp.cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        $allowedHeaders = config('laravel-mcp.cors.allowed_headers', ['Content-Type', 'Authorization']);
        
        return response()->json(null, 200, [
            'Access-Control-Allow-Origin' => $this->getOrigin($request, $allowedOrigins),
            'Access-Control-Allow-Methods' => implode(', ', $allowedMethods),
            'Access-Control-Allow-Headers' => implode(', ', $allowedHeaders),
            'Access-Control-Max-Age' => config('laravel-mcp.cors.max_age', 86400),
        ]);
    }
    
    private function addCorsHeaders($response, Request $request)
    {
        $allowedOrigins = config('laravel-mcp.cors.allowed_origins', ['*']);
        $origin = $this->getOrigin($request, $allowedOrigins);
        
        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', 'X-MCP-Request-ID');
        }
        
        return $response;
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'cors' => [
    'enabled' => true,
    'allowed_origins' => [
        'https://claude.ai',
        'https://chatgpt.com',
        'http://localhost:*',
    ],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-MCP-Client-Version',
    ],
    'max_age' => 86400,
],
```

### 3. McpAuthMiddleware

Handles authentication and authorization for MCP requests.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JTD\LaravelMCP\Exceptions\McpAuthException;

class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $authConfig = config('laravel-mcp.auth');
        
        if (!$authConfig['enabled']) {
            return $next($request);
        }
        
        // Attempt authentication
        $user = $this->authenticate($request, $authConfig);
        
        if (!$user) {
            throw new McpAuthException('Authentication required');
        }
        
        // Check authorization for the requested resource
        if (!$this->authorize($request, $user, $authConfig)) {
            throw new McpAuthException('Insufficient permissions');
        }
        
        // Set authenticated user in request
        $request->setUserResolver(fn() => $user);
        
        return $next($request);
    }
    
    private function authenticate(Request $request, array $config)
    {
        foreach ($config['providers'] as $provider) {
            $user = match ($provider) {
                'token' => $this->authenticateWithToken($request),
                'session' => $this->authenticateWithSession($request),
                'api_key' => $this->authenticateWithApiKey($request),
                default => null,
            };
            
            if ($user) {
                return $user;
            }
        }
        
        return null;
    }
    
    private function authenticateWithToken(Request $request)
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        // Validate token (implementation depends on your token system)
        return app('auth.token')->validate($token);
    }
    
    private function authorize(Request $request, $user, array $config): bool
    {
        if (!$config['authorization']['enabled']) {
            return true;
        }
        
        $resource = $this->getRequestedResource($request);
        $userPermissions = $this->getUserPermissions($user);
        
        return $this->checkPermission($resource, $userPermissions, $config);
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'auth' => [
    'enabled' => true,
    'providers' => ['token', 'session', 'api_key'],
    
    'token' => [
        'header' => 'Authorization',
        'lifetime' => 3600,
        'refresh_threshold' => 300,
    ],
    
    'authorization' => [
        'enabled' => true,
        'default_policy' => 'deny',
        'role_mapping' => [
            'admin' => ['*'],
            'user' => [
                'tools:calculator',
                'tools:weather',
                'resources:users:read',
                'prompts:*',
            ],
            'readonly' => [
                'resources:*:read',
                'prompts:*:read',
            ],
        ],
    ],
],
```

### 4. McpRateLimitMiddleware

Provides rate limiting protection to prevent abuse.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use JTD\LaravelMCP\Exceptions\RateLimitException;

class McpRateLimitMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $config = config('laravel-mcp.rate_limiting');
        
        if (!$config['enabled']) {
            return $next($request);
        }
        
        $key = $this->getRateLimitKey($request);
        $limit = $this->getLimit($request, $config);
        $window = $config['window'] ?? 60;
        
        if ($this->isLimitExceeded($key, $limit, $window)) {
            throw new RateLimitException("Rate limit exceeded: {$limit} requests per {$window} seconds");
        }
        
        // Track request
        $this->trackRequest($key, $window);
        
        $response = $next($request);
        
        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $key, $limit, $window);
    }
    
    private function getRateLimitKey(Request $request): string
    {
        $identifier = $request->user()?->id ?? $request->ip();
        $method = $request->input('method', 'unknown');
        
        return "mcp_rate_limit:{$identifier}:{$method}";
    }
    
    private function getLimit(Request $request, array $config): int
    {
        $user = $request->user();
        $method = $request->input('method');
        
        // Check method-specific limits
        if (isset($config['method_limits'][$method])) {
            return $config['method_limits'][$method];
        }
        
        // Check user role limits
        if ($user && isset($config['role_limits'][$user->role])) {
            return $config['role_limits'][$user->role];
        }
        
        return $config['default_limit'] ?? 100;
    }
    
    private function isLimitExceeded(string $key, int $limit, int $window): bool
    {
        $current = Redis::get($key) ?: 0;
        return $current >= $limit;
    }
    
    private function trackRequest(string $key, int $window): void
    {
        Redis::multi();
        Redis::incr($key);
        Redis::expire($key, $window);
        Redis::exec();
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'rate_limiting' => [
    'enabled' => true,
    'default_limit' => 100,
    'window' => 60, // seconds
    
    'role_limits' => [
        'admin' => 1000,
        'premium' => 500,
        'user' => 100,
        'anonymous' => 20,
    ],
    
    'method_limits' => [
        'tools/call' => 50,
        'resources/list' => 100,
        'resources/read' => 200,
        'prompts/get' => 100,
    ],
    
    'burst_protection' => [
        'enabled' => true,
        'threshold' => 10, // requests
        'window' => 1,     // second
        'penalty' => 30,   // seconds blocked
    ],
],
```

### 5. McpValidationMiddleware

Validates incoming MCP requests against JSON schemas.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JTD\LaravelMCP\Support\SchemaValidator;
use JTD\LaravelMCP\Http\Exceptions\McpValidationException;

class McpValidationMiddleware
{
    public function __construct(
        private SchemaValidator $validator
    ) {}
    
    public function handle(Request $request, Closure $next)
    {
        $config = config('laravel-mcp.validation');
        
        if (!$config['enabled']) {
            return $next($request);
        }
        
        // Validate JSON-RPC structure
        $this->validateJsonRpc($request);
        
        // Validate MCP method and parameters
        $this->validateMcpRequest($request);
        
        return $next($request);
    }
    
    private function validateJsonRpc(Request $request): void
    {
        $data = $request->json()->all();
        
        $schema = [
            'type' => 'object',
            'required' => ['jsonrpc', 'method'],
            'properties' => [
                'jsonrpc' => ['const' => '2.0'],
                'id' => ['oneOf' => [
                    ['type' => 'string'],
                    ['type' => 'number'],
                    ['type' => 'null']
                ]],
                'method' => ['type' => 'string'],
                'params' => ['type' => 'object']
            ]
        ];
        
        if (!$this->validator->validate($data, $schema)) {
            throw new McpValidationException('Invalid JSON-RPC request format');
        }
    }
    
    private function validateMcpRequest(Request $request): void
    {
        $method = $request->input('method');
        $params = $request->input('params', []);
        
        $schema = $this->getSchemaForMethod($method);
        
        if ($schema && !$this->validator->validate($params, $schema)) {
            throw new McpValidationException("Invalid parameters for method: {$method}");
        }
    }
    
    private function getSchemaForMethod(string $method): ?array
    {
        $schemas = config('laravel-mcp.validation.schemas', []);
        
        return $schemas[$method] ?? null;
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'validation' => [
    'enabled' => true,
    'strict_mode' => false,
    
    'schemas' => [
        'tools/call' => [
            'type' => 'object',
            'required' => ['name'],
            'properties' => [
                'name' => ['type' => 'string'],
                'arguments' => ['type' => 'object']
            ]
        ],
        
        'resources/list' => [
            'type' => 'object',
            'properties' => [
                'cursor' => ['type' => 'string'],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000]
            ]
        ],
        
        'resources/read' => [
            'type' => 'object',
            'required' => ['uri'],
            'properties' => [
                'uri' => ['type' => 'string', 'format' => 'uri']
            ]
        ],
    ],
    
    'custom_validators' => [
        'tool_name' => 'App\\Validators\\ToolNameValidator',
        'resource_uri' => 'App\\Validators\\ResourceUriValidator',
    ],
],
```

### 6. McpLoggingMiddleware

Provides comprehensive request/response logging and metrics collection.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpRequestProcessed;

class McpLoggingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $requestId = $request->header('X-MCP-Request-ID') ?: uniqid('mcp_', true);
        
        // Log incoming request
        $this->logIncomingRequest($request, $requestId);
        
        $response = $next($request);
        
        // Calculate execution time
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        // Log outgoing response
        $this->logOutgoingResponse($request, $response, $requestId, $executionTime);
        
        // Dispatch metrics event
        $this->dispatchMetricsEvent($request, $response, $requestId, $executionTime);
        
        // Add request ID to response headers
        $response->headers->set('X-MCP-Request-ID', $requestId);
        
        return $response;
    }
    
    private function logIncomingRequest(Request $request, string $requestId): void
    {
        $config = config('laravel-mcp.logging');
        
        if (!$config['requests']['enabled']) {
            return;
        }
        
        $logData = [
            'request_id' => $requestId,
            'method' => $request->input('method'),
            'transport' => 'http',
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ];
        
        // Add parameters if configured
        if ($config['requests']['include_parameters']) {
            $params = $request->input('params', []);
            $logData['parameters'] = $this->filterSensitiveData($params);
        }
        
        Log::channel($config['channel'])->info('MCP request received', $logData);
    }
    
    private function logOutgoingResponse(Request $request, $response, string $requestId, float $executionTime): void
    {
        $config = config('laravel-mcp.logging');
        
        if (!$config['responses']['enabled']) {
            return;
        }
        
        $logData = [
            'request_id' => $requestId,
            'status_code' => $response->getStatusCode(),
            'execution_time_ms' => $executionTime,
            'memory_usage' => memory_get_usage(true),
            'timestamp' => now()->toISOString(),
        ];
        
        // Add response data if configured  
        if ($config['responses']['include_data'] && $response->getStatusCode() < 400) {
            $responseData = json_decode($response->getContent(), true);
            if (isset($responseData['result'])) {
                $logData['result_summary'] = $this->summarizeResult($responseData['result']);
            }
        }
        
        $level = $response->getStatusCode() >= 400 ? 'error' : 'info';
        Log::channel($config['channel'])->{$level}('MCP response sent', $logData);
    }
}
```

**Configuration:**
```php
// config/laravel-mcp.php
'logging' => [
    'enabled' => true,
    'channel' => 'mcp',
    
    'requests' => [
        'enabled' => true,
        'include_parameters' => true,
        'sensitive_fields' => ['password', 'token', 'api_key'],
    ],
    
    'responses' => [
        'enabled' => true,
        'include_data' => true,
        'max_data_length' => 1024,
    ],
    
    'performance' => [
        'slow_threshold' => 1000, // milliseconds
        'memory_threshold' => 50 * 1024 * 1024, // 50MB
    ],
],
```

### 7. McpErrorHandlingMiddleware

Provides centralized error handling with secure error responses.

```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\McpException;
use Throwable;

class McpErrorHandlingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }
    
    private function handleException(Throwable $e, Request $request)
    {
        $requestId = $request->header('X-MCP-Request-ID') ?: 'unknown';
        
        // Log the error
        $this->logError($e, $request, $requestId);
        
        // Send error notification if configured
        $this->notifyError($e, $request, $requestId);
        
        // Return appropriate JSON-RPC error response
        return $this->createErrorResponse($e, $requestId);
    }
    
    private function logError(Throwable $e, Request $request, string $requestId): void
    {
        Log::error('MCP request failed', [
            'request_id' => $requestId,
            'method' => $request->input('method'),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $request->user()?->id,
            'client_ip' => $request->ip(),
        ]);
    }
    
    private function createErrorResponse(Throwable $e, string $requestId)
    {
        $isDebug = config('app.debug', false);
        
        $error = [
            'code' => $this->getErrorCode($e),
            'message' => $this->getErrorMessage($e, $isDebug),
        ];
        
        // Add debug info in debug mode
        if ($isDebug) {
            $error['data'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }
        
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => $error,
            'id' => null,
        ], $this->getHttpStatusCode($e))
        ->header('X-MCP-Request-ID', $requestId);
    }
}
```

## Middleware Configuration

### Global Middleware Registration

```php
// app/Http/Kernel.php
protected $middleware = [
    // ... other global middleware
    \JTD\LaravelMCP\Http\Middleware\HandleSseRequest::class,
];

protected $middlewareGroups = [
    'mcp' => [
        \JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpRateLimitMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpErrorHandlingMiddleware::class,
    ],
];
```

### Route-Specific Middleware

```php
// routes/mcp.php
Route::middleware(['mcp'])->group(function () {
    Route::post('/mcp', [McpController::class, 'handle']);
});

// Different middleware for different transports
Route::middleware(['mcp.cors', 'mcp.auth'])->group(function () {
    Route::post('/mcp/http', [McpController::class, 'handleHttp']);
});

Route::middleware(['mcp.auth', 'mcp.validation'])->group(function () {
    Route::post('/mcp/stdio', [McpController::class, 'handleStdio']);
});
```

### Conditional Middleware

```php
// config/laravel-mcp.php
'middleware' => [
    'global' => [
        'mcp.cors' => env('MCP_CORS_ENABLED', true),
        'mcp.auth' => env('MCP_AUTH_ENABLED', true),
        'mcp.rate-limit' => env('MCP_RATE_LIMIT_ENABLED', true),
        'mcp.validation' => env('MCP_VALIDATION_ENABLED', true),
        'mcp.logging' => env('MCP_LOGGING_ENABLED', true),
        'mcp.error-handling' => true,
    ],
    
    'development' => [
        'mcp.cors',
        'mcp.validation',
        'mcp.logging',
        'mcp.error-handling',
    ],
    
    'production' => [
        'mcp.cors',
        'mcp.auth',
        'mcp.rate-limit',
        'mcp.validation',
        'mcp.logging',
        'mcp.error-handling',
    ],
],
```

## Testing Middleware

### Unit Testing

```php
<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use Illuminate\Http\Request;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;

class McpAuthMiddlewareTest extends TestCase
{
    public function test_allows_request_with_valid_token(): void
    {
        config(['laravel-mcp.auth.enabled' => true]);
        
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        ]);
        
        $middleware = new McpAuthMiddleware();
        $response = $middleware->handle($request, fn($req) => response('OK'));
        
        $this->assertEquals('OK', $response->getContent());
    }
    
    public function test_rejects_request_without_token(): void
    {
        config(['laravel-mcp.auth.enabled' => true]);
        
        $request = Request::create('/mcp', 'POST');
        
        $middleware = new McpAuthMiddleware();
        
        $this->expectException(McpAuthException::class);
        $middleware->handle($request, fn($req) => response('OK'));
    }
}
```

### Integration Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;

class MiddlewareStackTest extends TestCase
{
    public function test_full_middleware_stack_processes_request(): void
    {
        $this->withHeaders([
            'Authorization' => 'Bearer valid-token',
            'Content-Type' => 'application/json',
        ])->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => []
        ])->assertOk()
          ->assertJsonStructure([
              'jsonrpc',
              'id', 
              'result'
          ])
          ->assertHeader('X-MCP-Request-ID');
    }
    
    public function test_rate_limiting_works(): void
    {
        config(['laravel-mcp.rate_limiting.default_limit' => 2]);
        
        // First two requests should succeed
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/list'
            ])->assertOk();
        }
        
        // Third request should be rate limited
        $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list'
        ])->assertStatus(429);
    }
}
```

## Performance Optimization

### Caching Strategies

```php
// Middleware caching for expensive operations
class OptimizedAuthMiddleware extends McpAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        
        if ($token) {
            // Cache token validation results
            $cacheKey = "mcp_auth_token:" . hash('sha256', $token);
            $user = Cache::remember($cacheKey, 300, function () use ($token) {
                return $this->validateToken($token);
            });
            
            if ($user) {
                $request->setUserResolver(fn() => $user);
                return $next($request);
            }
        }
        
        return parent::handle($request, $next);
    }
}
```

### Async Processing for Heavy Middleware

```php
class AsyncLoggingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Queue logging for async processing
        dispatch(new LogMcpRequest(
            $request->toArray(),
            $response->getContent(),
            microtime(true)
        ))->onQueue('logging');
        
        return $response;
    }
}
```

The comprehensive middleware stack provides enterprise-grade security, monitoring, and reliability features while maintaining high performance and flexibility for various deployment scenarios.