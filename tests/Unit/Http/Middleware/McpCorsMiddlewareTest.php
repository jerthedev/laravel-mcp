<?php

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * EPIC: SERVICEPROVIDER
 * SPEC: docs/Specs/03-ServiceProvider.md
 * SPRINT: Sprint 1
 * TICKET: SERVICEPROVIDER-004
 *
 * Unit tests for McpCorsMiddleware
 * Tests CORS handling for MCP endpoints
 */
#[CoversClass(McpCorsMiddleware::class)]
#[Group('unit')]
#[Group('middleware')]
#[Group('cors')]
class McpCorsMiddlewareTest extends TestCase
{
    private McpCorsMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new McpCorsMiddleware;
    }

    /**
     * Test middleware adds CORS headers to regular requests
     */
    #[Test]
    public function it_adds_cors_headers_to_regular_requests(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);
        Config::set('laravel-mcp.cors.allowed_methods', ['GET', 'POST']);
        Config::set('laravel-mcp.cors.allowed_headers', ['Content-Type']);
        Config::set('laravel-mcp.cors.max_age', 3600);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Origin', 'http://example.com');

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Max-Age'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Test middleware handles preflight OPTIONS requests
     */
    #[Test]
    public function it_handles_preflight_options_requests(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);
        Config::set('laravel-mcp.cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        Config::set('laravel-mcp.cors.allowed_headers', ['Content-Type', 'Authorization']);

        $request = Request::create('/test', 'OPTIONS');
        $request->headers->set('Origin', 'http://example.com');

        $next = function ($req) {
            return response('should not be called');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
        $this->assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, Authorization', $response->headers->get('Access-Control-Allow-Headers'));
    }

    /**
     * Test middleware respects specific allowed origins
     */
    #[Test]
    public function it_respects_specific_allowed_origins(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['http://allowed.com', 'https://trusted.com']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Origin', 'http://allowed.com');

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('http://allowed.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test middleware rejects disallowed origins
     */
    #[Test]
    public function it_rejects_disallowed_origins(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['http://allowed.com']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Origin', 'http://disallowed.com');

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test middleware allows all origins with wildcard
     */
    #[Test]
    public function it_allows_all_origins_with_wildcard(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        $request = Request::create('/test', 'GET');
        $request->headers->set('Origin', 'http://any-domain.com');

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('http://any-domain.com', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test middleware sets default origin when no origin header present
     */
    #[Test]
    public function it_sets_default_origin_when_no_origin_header(): void
    {
        // Arrange
        Config::set('laravel-mcp.cors.allowed_origins', ['*']);

        $request = Request::create('/test', 'GET');
        // No Origin header set

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    /**
     * Test middleware uses default configuration values
     */
    #[Test]
    public function it_uses_default_configuration_values(): void
    {
        // Arrange - Don't set any CORS config, should use defaults
        $request = Request::create('/test', 'GET');
        $request->headers->set('Origin', 'http://example.com');

        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert - Check that defaults are applied
        $this->assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('86400', $response->headers->get('Access-Control-Max-Age'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    /**
     * Test middleware includes MCP-specific headers in allowed headers
     */
    #[Test]
    public function it_includes_mcp_specific_headers(): void
    {
        // Arrange - Use default config which should include X-MCP-API-Key
        $request = Request::create('/test', 'OPTIONS');
        $request->headers->set('Origin', 'http://example.com');

        $next = function ($req) {
            return response('should not be called');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $allowedHeaders = $response->headers->get('Access-Control-Allow-Headers');
        $this->assertStringContainsString('X-MCP-API-Key', $allowedHeaders);
        $this->assertStringContainsString('Authorization', $allowedHeaders);
        $this->assertStringContainsString('Content-Type', $allowedHeaders);
    }
}
