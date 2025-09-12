<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group Laravel Integration
 * @group Middleware
 * @group ticket-021
 * @group laravelintegration-021
 *
 * @testdox MCP Auth Middleware Enhanced Functionality
 *
 * Test Coverage:
 * - Laravel guard authentication integration
 * - API key authentication with multiple keys
 * - Bearer token authentication
 * - Custom authentication callbacks
 * - User context injection
 * - Authentication logging
 * - Error handling
 *
 * Related Specifications:
 * - docs/Specs/10-LaravelIntegration.md: Laravel Integration spec
 * - docs/Tickets/021-LaravelMiddleware.md: Middleware implementation ticket
 */
class McpAuthMiddlewareEnhancedTest extends TestCase
{
    private McpAuthMiddleware $middleware;

    private MockObject|AuthFactory $authFactory;

    private MockObject|Guard $guard;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authFactory = $this->createMock(AuthFactory::class);
        $this->guard = $this->createMock(Guard::class);
        $this->request = new Request;

        $this->authFactory->method('guard')->willReturn($this->guard);
        $this->guard->method('user')->willReturn(null);

        $this->middleware = new McpAuthMiddleware($this->authFactory);
    }

    /**
     * @test
     */
    public function it_allows_request_when_authentication_is_disabled(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(false);

        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    /**
     * @test
     */
    public function it_authenticates_with_laravel_guard(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn('web');

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $next = function ($request) {
            return new Response('Authenticated');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Authenticated', $response->getContent());
    }

    /**
     * @test
     */
    public function it_authenticates_with_api_key(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn(null);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key')
            ->andReturn('test-api-key');

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_users', [])
            ->andReturn([]);

        $this->request->headers->set('X-MCP-API-Key', 'test-api-key');

        $this->guard->expects($this->any())
            ->method('check')
            ->willReturn(false);

        $next = function ($request) {
            return new Response('API Key Authenticated');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('API Key Authenticated', $response->getContent());
    }

    /**
     * @test
     */
    public function it_supports_multiple_api_keys(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn(null);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key')
            ->andReturn(['key1', 'key2', 'key3']);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_users', [])
            ->andReturn([]);

        $this->request->headers->set('X-MCP-API-Key', 'key2');

        $this->guard->expects($this->any())
            ->method('check')
            ->willReturn(false);

        $next = function ($request) {
            return new Response('Multi-Key Authenticated');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Multi-Key Authenticated', $response->getContent());
    }

    /**
     * @test
     */
    public function it_authenticates_with_bearer_token(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn(null);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_enabled', true)
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.bearer_token_enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.token_guard', 'sanctum')
            ->andReturn('sanctum');

        $this->request->headers->set('Authorization', 'Bearer test-token');

        $tokenGuard = $this->createMock(Guard::class);
        $tokenGuard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->authFactory->expects($this->at(1))
            ->method('guard')
            ->with('sanctum')
            ->willReturn($tokenGuard);

        $next = function ($request) {
            return new Response('Bearer Token Authenticated');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_returns_unauthorized_when_authentication_fails(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')->andReturn(null);

        $this->guard->expects($this->any())
            ->method('check')
            ->willReturn(false);

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32001, $data['error']['code']);
        $this->assertEquals('Authentication required', $data['error']['message']);
    }

    /**
     * @test
     */
    public function it_adds_user_context_when_authenticated(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn('web');

        $user = new \stdClass;
        $user->id = 123;

        $this->guard->expects($this->once())
            ->method('check')
            ->willReturn(true);

        $this->authFactory->expects($this->once())
            ->method('user')
            ->willReturn($user);

        $next = function ($request) {
            // Verify user context was added
            $this->assertEquals(123, $request->attributes->get('mcp_user_id'));
            $this->assertNotNull($request->attributes->get('mcp_user'));
            $this->assertEquals('123', $request->headers->get('X-MCP-User-ID'));

            return new Response('Context Added');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_handles_authentication_exceptions_gracefully(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn('web');

        $this->guard->expects($this->once())
            ->method('check')
            ->willThrowException(new AuthenticationException('Custom auth error'));

        $next = function ($request) {
            return new Response('Should not reach here');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals(-32001, $data['error']['code']);
        $this->assertEquals('Custom auth error', $data['error']['message']);
    }

    /**
     * @test
     */
    public function it_extracts_api_key_from_multiple_locations(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.enabled', false)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.guard')
            ->andReturn(null);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key')
            ->andReturn('valid-key');

        Config::shouldReceive('get')
            ->with('laravel-mcp.auth.api_key_users', [])
            ->andReturn([]);

        $testCases = [
            ['header' => 'X-MCP-API-Key', 'value' => 'valid-key'],
            ['header' => 'X-API-Key', 'value' => 'valid-key'],
            ['query' => 'api_key', 'value' => 'valid-key'],
        ];

        foreach ($testCases as $testCase) {
            $request = new Request;

            if (isset($testCase['header'])) {
                $request->headers->set($testCase['header'], $testCase['value']);
            } elseif (isset($testCase['query'])) {
                $request->query->set($testCase['query'], $testCase['value']);
            }

            $next = function ($request) {
                return new Response('OK');
            };

            $middleware = new McpAuthMiddleware($this->authFactory);
            $response = $middleware->handle($request, $next);

            $this->assertEquals(200, $response->getStatusCode(),
                'Failed for: '.json_encode($testCase));
        }
    }
}
