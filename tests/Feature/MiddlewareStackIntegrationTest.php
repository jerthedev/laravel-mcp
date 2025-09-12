<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpErrorHandlingMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpRateLimitMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * EPIC: 021-LaravelMiddleware
 * SPEC: 021-spec-laravel-middleware-integration.md
 * SPRINT: Sprint 5
 * TICKET: LARAVEL-MCP-021
 *
 * Comprehensive integration tests for the complete middleware stack
 */
#[CoversClass(McpAuthMiddleware::class)]
#[CoversClass(McpCorsMiddleware::class)]
#[CoversClass(McpErrorHandlingMiddleware::class)]
#[CoversClass(McpLoggingMiddleware::class)]
#[CoversClass(McpRateLimitMiddleware::class)]
#[CoversClass(McpValidationMiddleware::class)]
#[Group('feature')]
#[Group('middleware')]
#[Group('integration')]
class MiddlewareStackIntegrationTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Register all middleware aliases
        $router = $this->app['router'];
        $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
        $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);
        $router->aliasMiddleware('mcp.error', McpErrorHandlingMiddleware::class);
        $router->aliasMiddleware('mcp.logging', McpLoggingMiddleware::class);
        $router->aliasMiddleware('mcp.ratelimit', McpRateLimitMiddleware::class);
        $router->aliasMiddleware('mcp.validation', McpValidationMiddleware::class);

        // Define the complete middleware stack
        $middlewareStack = [
            'mcp.error',      // Error handling first to catch all exceptions
            'mcp.logging',    // Logging second to log all requests
            'mcp.cors',       // CORS third for browser security
            'mcp.ratelimit',  // Rate limiting before auth to prevent brute force
            'mcp.auth',       // Authentication
            'mcp.validation', // Validation last before hitting the handler
        ];

        // Set up test routes with full middleware stack
        Route::group(['prefix' => 'mcp', 'middleware' => $middlewareStack], function () {
            // Initialize endpoint
            Route::post('/initialize', function (Request $request) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'protocolVersion' => '1.0',
                        'capabilities' => [
                            'tools' => true,
                            'resources' => true,
                            'prompts' => true,
                        ],
                        'serverInfo' => [
                            'name' => 'Laravel MCP Server',
                            'version' => '1.0.0',
                        ],
                    ],
                    'id' => $request->json('id'),
                ]);
            });

            // Tools endpoint
            Route::post('/tools/call', function (Request $request) {
                $toolName = $request->json('params.name');
                if ($toolName === 'error-tool') {
                    throw new \RuntimeException('Tool execution failed');
                }

                return response()->json([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'toolName' => $toolName,
                        'output' => 'Tool executed successfully',
                    ],
                    'id' => $request->json('id'),
                ]);
            });

            // Resources endpoint
            Route::post('/resources/read', function (Request $request) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'uri' => $request->json('params.uri'),
                        'contents' => 'Resource content',
                    ],
                    'id' => $request->json('id'),
                ]);
            });

            // Slow endpoint for testing timeouts
            Route::post('/slow', function (Request $request) {
                sleep(1); // Simulate slow processing

                return response()->json([
                    'jsonrpc' => '2.0',
                    'result' => ['message' => 'Slow response'],
                    'id' => $request->json('id'),
                ]);
            });
        });

        // Mock facades for testing
        Log::spy();
    }

    #[Test]
    public function it_processes_valid_mcp_request_through_full_stack(): void
    {
        // Configure middleware
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'test-key');
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);
        Config::set('laravel-mcp.validation.enabled', true);
        Config::set('laravel-mcp.rate_limiting.enabled', true);
        Config::set('laravel-mcp.logging.enabled', true);

        // Make request
        $response = $this->postJson('/mcp/initialize', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'Test Client',
                    'version' => '1.0.0',
                ],
            ],
            'id' => 'init-1',
        ], [
            'X-MCP-API-Key' => 'test-key',
            'Origin' => 'http://example.com',
        ]);

        // Assert successful response
        $response->assertSuccessful();
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'protocolVersion',
                'capabilities',
                'serverInfo',
            ],
            'id',
        ]);
        $response->assertJson(['id' => 'init-1']);

        // Assert CORS headers
        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');

        // Assert rate limit headers
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');

        // Assert logging occurred
        Log::shouldHaveReceived('channel')->atLeast()->once();
    }

    #[Test]
    public function it_handles_authentication_failure_gracefully(): void
    {
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'test-tool'],
            'id' => 'auth-fail',
        ], [
            'X-MCP-API-Key' => 'wrong-key',
            'Origin' => 'http://example.com',
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => 'Authentication required',
            ],
            'id' => null,
        ]);

        // CORS headers should still be present
        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');
    }

    #[Test]
    public function it_handles_validation_errors_with_proper_error_format(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.validation.enabled', true);

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [], // Missing required 'name' parameter
            'id' => 'validation-fail',
        ]);

        $response->assertStatus(400);
        $response->assertJsonStructure([
            'jsonrpc',
            'error' => [
                'code',
                'message',
                'data' => [
                    'validation_errors',
                ],
            ],
            'id',
        ]);
        $response->assertJson([
            'error' => [
                'code' => -32602,
                'message' => 'Invalid params',
            ],
        ]);
    }

    #[Test]
    public function it_enforces_rate_limits_across_requests(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.rate_limiting.enabled', true);
        Config::set('laravel-mcp.rate_limiting.default', ['attempts' => 2, 'decay' => 60]);

        // First request - should succeed
        $response1 = $this->postJson('/mcp/resources/read', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'file://test1.txt'],
            'id' => 'rate-1',
        ]);
        $response1->assertSuccessful();
        $response1->assertHeader('X-RateLimit-Limit', '2');
        $response1->assertHeader('X-RateLimit-Remaining', '1');

        // Second request - should succeed
        $response2 = $this->postJson('/mcp/resources/read', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'file://test2.txt'],
            'id' => 'rate-2',
        ]);
        $response2->assertSuccessful();
        $response2->assertHeader('X-RateLimit-Remaining', '0');

        // Third request - should be rate limited
        $response3 = $this->postJson('/mcp/resources/read', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => ['uri' => 'file://test3.txt'],
            'id' => 'rate-3',
        ]);
        $response3->assertStatus(429);
        $response3->assertJson([
            'error' => [
                'code' => -32029,
                'message' => 'Too many requests',
            ],
        ]);
        $response3->assertHeader('Retry-After');
    }

    #[Test]
    public function it_handles_runtime_errors_with_error_middleware(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('app.debug', false);

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'error-tool'],
            'id' => 'error-test',
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32603,
                'message' => 'Internal error',
                'data' => [
                    'type' => 'internal_error',
                ],
            ],
            'id' => 'error-test',
        ]);

        // Should not expose sensitive information in production
        $response->assertJsonMissing(['exception' => 'RuntimeException']);
    }

    #[Test]
    public function it_logs_slow_requests_with_performance_warning(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.slow_request_threshold', 500); // 500ms

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('withContext')->andReturnSelf();
        Log::shouldReceive('info')->once();
        Log::shouldReceive('warning')
            ->once()
            ->with('Slow MCP request detected', \Mockery::on(function ($context) {
                return $context['execution_time'] > 500;
            }));

        $response = $this->postJson('/mcp/slow', [
            'jsonrpc' => '2.0',
            'method' => 'slow',
            'params' => [],
            'id' => 'slow-1',
        ]);

        $response->assertSuccessful();
    }

    #[Test]
    public function it_handles_preflight_cors_requests_with_full_stack(): void
    {
        Config::set('laravel-mcp.cors.allowed_origins', ['http://app.com']);
        Config::set('laravel-mcp.cors.allowed_methods', ['POST', 'OPTIONS']);
        Config::set('laravel-mcp.cors.allowed_headers', ['Content-Type', 'X-MCP-API-Key']);
        Config::set('laravel-mcp.cors.max_age', 3600);

        $response = $this->call('OPTIONS', '/mcp/initialize', [], [], [], [
            'HTTP_ORIGIN' => 'http://app.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type,X-MCP-API-Key',
        ]);

        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://app.com');
        $response->assertHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Content-Type, X-MCP-API-Key');
        $response->assertHeader('Access-Control-Max-Age', '3600');
    }

    #[Test]
    public function it_validates_json_rpc_structure_before_processing(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.validation.enabled', true);

        // Invalid JSON-RPC version
        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '1.0', // Should be 2.0
            'method' => 'tools/call',
            'params' => ['name' => 'test'],
            'id' => 'version-fail',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'code' => -32602,
            'message' => 'Invalid params',
        ]);
    }

    #[Test]
    public function it_adds_user_context_when_authenticated(): void
    {
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'user-key');
        Config::set('laravel-mcp.auth.api_key_users', ['user-key' => 123]);

        // Mock user model
        $userModel = \Mockery::mock('alias:App\Models\User');
        $userModel->shouldReceive('find')
            ->with(123)
            ->andReturn((object) ['id' => 123]);

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'test-tool'],
            'id' => 'user-context',
        ], [
            'X-MCP-API-Key' => 'user-key',
        ]);

        $response->assertSuccessful();
    }

    #[Test]
    public function it_validates_mcp_protocol_compliance(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.validation.enabled', true);
        Config::set('laravel-mcp.validation.strict_mcp_methods', true);

        // Unknown MCP method
        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'unknown/method',
            'params' => [],
            'id' => 'unknown-method',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Invalid params',
        ]);
    }

    #[Test]
    public function it_handles_multiple_middleware_failures_correctly(): void
    {
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');
        Config::set('laravel-mcp.validation.enabled', true);
        Config::set('laravel-mcp.validation.max_request_size', 100); // Very small limit

        // Large request with wrong API key
        $largeParams = ['data' => str_repeat('x', 200)];

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => $largeParams,
            'id' => 'multi-fail',
        ], [
            'X-MCP-API-Key' => 'wrong-key',
        ]);

        // Should fail on auth first (middleware order)
        $response->assertStatus(401);
        $response->assertJsonFragment([
            'code' => -32001,
            'message' => 'Authentication required',
        ]);
    }

    #[Test]
    public function it_maintains_request_context_through_middleware_stack(): void
    {
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'context-key');
        Config::set('laravel-mcp.logging.enabled', true);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('withContext')
            ->once()
            ->with(\Mockery::on(function ($context) {
                return isset($context['request_id']);
            }))
            ->andReturnSelf();
        Log::shouldReceive('info')->twice();

        $response = $this->postJson('/mcp/initialize', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [],
                'clientInfo' => ['name' => 'Test', 'version' => '1.0'],
            ],
            'id' => 'context-test',
        ], [
            'X-MCP-API-Key' => 'context-key',
            'X-Request-ID' => 'req-123-456',
        ]);

        $response->assertSuccessful();
    }

    #[Test]
    public function it_handles_different_authentication_methods(): void
    {
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.bearer_token_enabled', true);
        Config::set('laravel-mcp.auth.token_guard', 'sanctum');

        // Mock Sanctum guard
        $sanctumGuard = \Mockery::mock(Guard::class);
        $sanctumGuard->shouldReceive('setToken')->with('bearer-token-123');
        $sanctumGuard->shouldReceive('check')->andReturn(true);

        $authFactory = \Mockery::mock(AuthFactory::class);
        $authFactory->shouldReceive('guard')->with('sanctum')->andReturn($sanctumGuard);
        $authFactory->shouldReceive('user')->andReturn((object) ['id' => 456]);

        $this->app->instance(AuthFactory::class, $authFactory);

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'bearer-test'],
            'id' => 'bearer-1',
        ], [
            'Authorization' => 'Bearer bearer-token-123',
        ]);

        $response->assertSuccessful();
    }

    #[Test]
    public function it_respects_capability_configuration(): void
    {
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.validation.enabled', true);
        Config::set('laravel-mcp.capabilities.tools', false); // Disable tools capability

        $response = $this->postJson('/mcp/tools/call', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'disabled-tool'],
            'id' => 'capability-test',
        ]);

        $response->assertStatus(400);
        $response->assertJsonFragment([
            'message' => 'Invalid params',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        \Mockery::close();
    }
}
