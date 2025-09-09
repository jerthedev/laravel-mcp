<?php

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EPIC: SERVICEPROVIDER
 * SPEC: docs/Specs/03-ServiceProvider.md
 * SPRINT: Sprint 1
 * TICKET: SERVICEPROVIDER-004
 *
 * Unit tests for McpAuthMiddleware
 * Tests authentication logic for MCP endpoints
 */
#[CoversClass(McpAuthMiddleware::class)]
#[Group('unit')]
#[Group('middleware')]
#[Group('auth')]
class McpAuthMiddlewareTest extends TestCase
{
    private McpAuthMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new McpAuthMiddleware;
    }

    /**
     * Test middleware passes through when auth is disabled
     */
    #[Test]
    public function it_passes_through_when_auth_disabled(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', false);
        $request = Request::create('/test');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('success', $response->getContent());
    }

    /**
     * Test middleware passes through when no api key is configured
     */
    #[Test]
    public function it_passes_through_when_no_api_key_configured(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', '');
        $request = Request::create('/test');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('success', $response->getContent());
    }

    /**
     * Test middleware authenticates with valid API key in header
     */
    #[Test]
    public function it_authenticates_with_valid_api_key_in_header(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'test-key-123');
        $request = Request::create('/test');
        $request->headers->set('X-MCP-API-Key', 'test-key-123');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('success', $response->getContent());
    }

    /**
     * Test middleware authenticates with valid API key in query parameter
     */
    #[Test]
    public function it_authenticates_with_valid_api_key_in_query(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'test-key-123');
        $request = Request::create('/test?api_key=test-key-123');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('success', $response->getContent());
    }

    /**
     * Test middleware rejects invalid API key
     */
    #[Test]
    public function it_rejects_invalid_api_key(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');
        $request = Request::create('/test');
        $request->headers->set('X-MCP-API-Key', 'wrong-key');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(-32001, $responseData['error']['code']);
        $this->assertEquals('Invalid API key', $responseData['error']['message']);
    }

    /**
     * Test middleware rejects request with no API key when required
     */
    #[Test]
    public function it_rejects_request_with_no_api_key_when_required(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'required-key');
        $request = Request::create('/test');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals(-32001, $responseData['error']['code']);
        $this->assertEquals('Invalid API key', $responseData['error']['message']);
    }

    /**
     * Test middleware prioritizes header over query parameter
     */
    #[Test]
    public function it_prioritizes_header_over_query_parameter(): void
    {
        // Arrange
        Config::set('laravel-mcp.auth.enabled', true);
        Config::set('laravel-mcp.auth.api_key', 'correct-key');
        $request = Request::create('/test?api_key=wrong-key');
        $request->headers->set('X-MCP-API-Key', 'correct-key');
        $next = function ($req) {
            return response('success');
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertEquals('success', $response->getContent());
    }
}
