<?php

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EPIC: SERVICEPROVIDER
 * SPEC: docs/Specs/03-ServiceProvider.md
 * SPRINT: Sprint 1
 * TICKET: SERVICEPROVIDER-004
 *
 * Feature tests for middleware integration in HTTP requests
 * Tests middleware functionality in real HTTP context
 */
#[Group('feature')]
#[Group('middleware')]
#[Group('http')]
class MiddlewareIntegrationTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Register middleware aliases for tests
        $router = $this->app['router'];
        $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
        $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);

        // Set up test routes with middleware
        Route::group(['prefix' => 'test-mcp'], function () {
            Route::get('/cors-test', function () {
                return response()->json(['message' => 'CORS test success']);
            })->middleware(McpCorsMiddleware::class);

            Route::options('/cors-test', function () {
                return response('');
            })->middleware(McpCorsMiddleware::class);

            Route::get('/auth-test', function () {
                return response()->json(['message' => 'Auth test success']);
            })->middleware([McpCorsMiddleware::class, McpAuthMiddleware::class]);

            Route::post('/api-endpoint', function (Request $request) {
                return response()->json(['received' => $request->all()]);
            })->middleware(['mcp.cors', 'mcp.auth']);
        });
    }

    /**
     * Test CORS middleware allows valid CORS requests
     */
    #[Test]
    public function it_allows_valid_cors_requests(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['http://example.com']);
        Config::set('laravel-mcp.cors.allowed_methods', ['GET', 'POST']);

        // Act
        $response = $this->get('/test-mcp/cors-test', [
            'Origin' => 'http://example.com',
        ]);

        // Assert
        $response->assertSuccessful();
        $response->assertJson(['message' => 'CORS test success']);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test CORS middleware handles preflight requests
     */
    #[Test]
    public function it_handles_cors_preflight_requests(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);
        Config::set('laravel-mcp.cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        Config::set('laravel-mcp.cors.allowed_headers', ['Content-Type', 'Authorization']);

        // Act - Send OPTIONS request (preflight)
        $response = $this->call('OPTIONS', '/test-mcp/cors-test', [], [], [], [
            'HTTP_ORIGIN' => 'http://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type',
        ]);

        // Assert
        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $this->assertEquals('', $response->getContent());
    }

    /**
     * Test auth middleware allows requests with valid API key
     */
    #[Test]
    public function it_allows_requests_with_valid_api_key(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'test-key-123');
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        // Act
        $response = $this->get('/test-mcp/auth-test', [
            'X-MCP-API-Key' => 'test-key-123',
        ]);

        // Assert
        $response->assertSuccessful();
        $response->assertJson(['message' => 'Auth test success']);
    }

    /**
     * Test auth middleware rejects requests with invalid API key
     */
    #[Test]
    public function it_rejects_requests_with_invalid_api_key(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');

        // Act
        $response = $this->get('/test-mcp/auth-test', [
            'X-MCP-API-Key' => 'wrong-key',
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $response->assertJsonStructure([
            'error' => ['code', 'message'],
        ]);
        $response->assertJsonFragment([
            'error' => [
                'code' => -32001,
                'message' => 'Invalid API key',
            ],
        ]);
    }

    /**
     * Test auth middleware rejects requests without API key when required
     */
    #[Test]
    public function it_rejects_requests_without_api_key_when_required(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'required-key');

        // Act
        $response = $this->get('/test-mcp/auth-test');

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $response->assertJsonFragment([
            'error' => [
                'code' => -32001,
                'message' => 'Invalid API key',
            ],
        ]);
    }

    /**
     * Test auth middleware allows requests when auth is disabled
     */
    #[Test]
    public function it_allows_requests_when_auth_disabled(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', false);
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        // Act
        $response = $this->get('/test-mcp/auth-test');

        // Assert
        $response->assertSuccessful();
        $response->assertJson(['message' => 'Auth test success']);
    }

    /**
     * Test middleware stack works together correctly
     */
    #[Test]
    public function it_processes_middleware_stack_correctly(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'valid-key');
        Config::set('laravel-mcp.cors.allowed_origins', ['http://trusted.com']);

        // Act
        $response = $this->withHeaders([
            'Origin' => 'http://trusted.com',
            'X-MCP-API-Key' => 'valid-key',
        ])->postJson('/test-mcp/api-endpoint', ['data' => 'test']);

        // Assert
        $response->assertSuccessful();
        $response->assertJson(['received' => ['data' => 'test']]);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://trusted.com');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test POST request with API key in query parameter
     */
    #[Test]
    public function it_accepts_api_key_in_query_parameter(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'query-key');
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        // Act
        $response = $this->post('/test-mcp/api-endpoint?api_key=query-key',
            ['test' => 'data'],
            ['Origin' => 'http://example.com']
        );

        // Assert
        $response->assertSuccessful();
        $response->assertJson(['received' => ['test' => 'data']]);
    }

    /**
     * Test middleware respects CORS origin restrictions
     */
    #[Test]
    public function it_respects_cors_origin_restrictions(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['http://allowed.com']);

        // Act - Request from disallowed origin
        $response = $this->get('/test-mcp/cors-test', [
            'Origin' => 'http://disallowed.com',
        ]);

        // Assert
        $response->assertSuccessful(); // Request still succeeds
        // But no CORS headers should be set for disallowed origin
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test middleware allows wildcard origins
     */
    #[Test]
    public function it_allows_wildcard_origins(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        // Act
        $response = $this->get('/test-mcp/cors-test', [
            'Origin' => 'http://any-domain.com',
        ]);

        // Assert
        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://any-domain.com');
    }

    /**
     * Test complex CORS scenario with custom headers
     */
    #[Test]
    public function it_handles_complex_cors_scenarios(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['http://app.com']);
        Config::set('laravel-mcp.cors.allowed_methods', ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']);
        Config::set('laravel-mcp.cors.allowed_headers', [
            'Content-Type',
            'Authorization',
            'X-MCP-API-Key',
            'X-Custom-Header',
        ]);
        Config::set('laravel-mcp.cors.max_age', 7200);

        // Act - Preflight request with custom headers
        $response = $this->call('OPTIONS', '/test-mcp/cors-test', [], [], [], [
            'HTTP_ORIGIN' => 'http://app.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type,X-Custom-Header',
        ]);

        // Assert
        $response->assertSuccessful();
        $response->assertHeader('Access-Control-Allow-Origin', 'http://app.com');
        $response->assertHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->assertHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-MCP-API-Key, X-Custom-Header');
        $response->assertHeader('Access-Control-Max-Age', '7200');
    }

    /**
     * Test middleware error handling doesn't break CORS
     */
    #[Test]
    public function it_maintains_cors_headers_on_auth_errors(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        // Act - Invalid API key should still get CORS headers
        $response = $this->get('/test-mcp/auth-test', [
            'Origin' => 'http://example.com',
            'X-MCP-API-Key' => 'invalid-key',
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
