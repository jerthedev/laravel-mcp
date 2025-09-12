<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JTD\LaravelMCP\Http\Middleware\McpRateLimitMiddleware;
use JTD\LaravelMCP\Tests\UnitTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * EPIC: 021-LaravelMiddleware
 * SPEC: 021-spec-laravel-middleware-integration.md
 * SPRINT: Sprint 5
 * TICKET: LARAVEL-MCP-021
 *
 * Comprehensive test coverage for MCP Rate Limiting Middleware
 */
#[CoversClass(McpRateLimitMiddleware::class)]
#[Group('middleware')]
#[Group('rate-limiting')]
#[Group('unit')]
class McpRateLimitMiddlewareTest extends UnitTestCase
{
    private McpRateLimitMiddleware $middleware;

    private MockObject|RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->middleware = new McpRateLimitMiddleware($this->rateLimiter);
    }

    protected function setUpGlobalMocks(): void
    {
        // Mock Config facade
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')->with('laravel-mcp.rate_limiting.enabled', true)->andReturn(true)->byDefault();
        $configMock->shouldReceive('get')->with('laravel-mcp.rate_limiting.log_exceeded', true)->andReturn(true)->byDefault();
        $configMock->shouldReceive('get')->with('laravel-mcp.rate_limiting.key_suffix')->andReturn(null)->byDefault();
        $configMock->shouldReceive('get')->with('laravel-mcp.rate_limiting.default', Mockery::any())->andReturn(['attempts' => 60, 'decay' => 60])->byDefault();

        // Mock Log facade
        $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
        $logMock->shouldReceive('debug')->andReturnNull()->byDefault();
        $logMock->shouldReceive('info')->andReturnNull()->byDefault();
        $logMock->shouldReceive('warning')->andReturnNull()->byDefault();
        $logMock->shouldReceive('error')->andReturnNull()->byDefault();

        // Mock Auth facade
        $authMock = Mockery::mock('alias:Illuminate\Support\Facades\Auth');
        $authMock->shouldReceive('check')->andReturn(false)->byDefault();
        $authMock->shouldReceive('id')->andReturnNull()->byDefault();
    }

    #[Test]
    public function it_skips_rate_limiting_when_disabled(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')->with('laravel-mcp.rate_limiting.enabled', true)->andReturn(false);

        $request = Request::create('/mcp', 'POST');
        $response = new Response('success');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_allows_requests_within_rate_limit(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
        $this->assertEquals('60', $result->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('59', $result->headers->get('X-RateLimit-Remaining'));
        $this->assertEquals('tools/list', $result->headers->get('X-RateLimit-Method'));
    }

    #[Test]
    public function it_blocks_requests_exceeding_rate_limit(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'test-id',
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(true);

        $this->rateLimiter->expects($this->exactly(2))
            ->method('availableIn')
            ->willReturn(30);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(0);

        $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
        $logMock->shouldReceive('warning')
            ->once()
            ->with('MCP rate limit exceeded', Mockery::type('array'));

        $next = function ($req) {
            return new Response('success');
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(429, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32029, $data['error']['code']);
        $this->assertEquals('Too many requests', $data['error']['message']);
        $this->assertEquals(30, $data['error']['data']['retry_after']);
        $this->assertEquals('test-id', $data['id']);
    }

    #[Test]
    public function it_uses_named_limiter_when_provided(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.limiters.strict', Mockery::any())
            ->andReturn(['attempts' => 10, 'decay' => 120, 'strategy' => 'per_user']);

        $request = Request::create('/mcp', 'POST');

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit')
            ->with($this->anything(), 120);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(9);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next, 'strict');

        $this->assertEquals('10', $result->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('9', $result->headers->get('X-RateLimit-Remaining'));
    }

    #[Test]
    #[DataProvider('methodConfigurationProvider')]
    public function it_uses_method_specific_configuration(string $method, array $expectedConfig): void
    {
        $configKey = str_contains($method, '/') ?
            "laravel-mcp.rate_limiting.methods.{$method}" :
            'laravel-mcp.rate_limiting.categories.'.explode('/', $method)[0];

        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with($configKey, Mockery::any())
            ->andReturn($expectedConfig);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit')
            ->with($this->anything(), $expectedConfig['decay']);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn($expectedConfig['attempts'] - 1);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertEquals((string) $expectedConfig['attempts'], $result->headers->get('X-RateLimit-Limit'));
    }

    public static function methodConfigurationProvider(): array
    {
        return [
            'tools category' => ['tools/call', ['attempts' => 60, 'decay' => 60]],
            'resources category' => ['resources/read', ['attempts' => 100, 'decay' => 60]],
            'prompts category' => ['prompts/get', ['attempts' => 100, 'decay' => 60]],
            'sampling category' => ['sampling/createMessage', ['attempts' => 20, 'decay' => 60]],
            'completion category' => ['completion/complete', ['attempts' => 50, 'decay' => 60]],
        ];
    }

    #[Test]
    #[DataProvider('rateLimitStrategyProvider')]
    public function it_generates_correct_key_for_strategy(string $strategy, array $requestSetup, string $expectedKeyPattern): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.default', Mockery::any())
            ->andReturn(['attempts' => 60, 'decay' => 60, 'strategy' => $strategy]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        // Apply request setup
        if (isset($requestSetup['user_id'])) {
            $request->attributes = new ParameterBag(['mcp_user_id' => $requestSetup['user_id']]);
        }
        if (isset($requestSetup['auth_user'])) {
            $authMock = Mockery::mock('alias:Illuminate\Support\Facades\Auth');
            $authMock->shouldReceive('check')->andReturn(true);
            $authMock->shouldReceive('id')->andReturn($requestSetup['auth_user']);
        }
        if (isset($requestSetup['api_key'])) {
            $request->headers->set('X-MCP-API-Key', $requestSetup['api_key']);
        }
        if (isset($requestSetup['ip'])) {
            $request->server->set('REMOTE_ADDR', $requestSetup['ip']);
        }

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with($this->matchesRegularExpression($expectedKeyPattern), 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit')
            ->with($this->matchesRegularExpression($expectedKeyPattern), 60);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $this->middleware->handle($request, $next);
    }

    public static function rateLimitStrategyProvider(): array
    {
        return [
            'per_user strategy with mcp_user_id' => [
                'per_user',
                ['user_id' => 123],
                '/^mcp:rate_limit:tools\/list:user:123$/',
            ],
            'per_user strategy with auth user' => [
                'per_user',
                ['auth_user' => 456],
                '/^mcp:rate_limit:tools\/list:user:456$/',
            ],
            'per_ip strategy' => [
                'per_ip',
                ['ip' => '192.168.1.1'],
                '/^mcp:rate_limit:tools\/list:192\.168\.1\.1$/',
            ],
            'per_user_per_ip strategy' => [
                'per_user_per_ip',
                ['user_id' => 789, 'ip' => '10.0.0.1'],
                '/^mcp:rate_limit:tools\/list:user:789:10\.0\.0\.1$/',
            ],
            'per_api_key strategy' => [
                'per_api_key',
                ['api_key' => 'test-key-123'],
                '/^mcp:rate_limit:tools\/list:api:[a-f0-9]{16}$/',
            ],
            'global strategy' => [
                'global',
                [],
                '/^mcp:rate_limit:tools\/list$/',
            ],
        ];
    }

    #[Test]
    public function it_adds_custom_key_suffix_from_config(): void
    {
        $customSuffix = function ($request) {
            return 'tenant:'.($request->header('X-Tenant-ID') ?? 'default');
        };

        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.key_suffix')
            ->andReturn($customSuffix);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag([
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => 'acme',
        ]);

        $expectedKey = 'mcp:rate_limit:tools/list:127.0.0.1:tenant:acme';

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with($expectedKey, 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit')
            ->with($expectedKey, 60);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_adds_retry_after_headers_when_limited(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(0);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(45);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertEquals('60', $result->headers->get('X-RateLimit-Limit'));
        $this->assertEquals('0', $result->headers->get('X-RateLimit-Remaining'));
        $this->assertNotNull($result->headers->get('X-RateLimit-Reset'));
        $this->assertEquals('45', $result->headers->get('Retry-After'));
    }

    #[Test]
    public function it_does_not_log_when_logging_disabled(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.log_exceeded', true)
            ->andReturn(false);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(true);

        $this->rateLimiter->expects($this->exactly(2))
            ->method('availableIn')
            ->willReturn(30);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(0);

        $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
        $logMock->shouldReceive('warning')->never();

        $next = function ($req) {
            return new Response('success');
        };

        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_clears_rate_limit_for_specific_key(): void
    {
        $this->rateLimiter->expects($this->once())
            ->method('clear')
            ->with('test-key');

        $this->middleware->clear('test-key');
    }

    #[Test]
    public function it_resets_rate_limits_for_user(): void
    {
        $logMock = Mockery::mock('alias:Illuminate\Support\Facades\Log');
        $logMock->shouldReceive('info')
            ->once()
            ->with('Rate limits reset for user', ['user_id' => 123]);

        $this->middleware->resetForUser(123);
    }

    #[Test]
    public function it_returns_rate_limit_status(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(45);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(30);

        $status = $this->middleware->getStatus($request);

        $this->assertEquals(60, $status['limit']);
        $this->assertEquals(45, $status['remaining']);
        $this->assertNotNull($status['reset']);
        $this->assertEquals(30, $status['retry_after']);
    }

    #[Test]
    public function it_handles_simple_integer_config(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.methods.tools/call', Mockery::any())
            ->andReturn(30); // Simple integer config

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit')
            ->with($this->anything(), 60); // Default decay

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(29);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertEquals('30', $result->headers->get('X-RateLimit-Limit'));
    }

    #[Test]
    public function it_handles_non_json_requests(): void
    {
        $request = Request::create('/mcp', 'GET');

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with('mcp:rate_limit:unknown:127.0.0.1', 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_handles_api_key_from_query_parameter(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.default', Mockery::any())
            ->andReturn(['attempts' => 60, 'decay' => 60, 'strategy' => 'per_api_key']);

        $request = Request::create('/mcp?api_key=query-key-456', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with($this->matchesRegularExpression('/^mcp:rate_limit:tools\/list:api:[a-f0-9]{16}$/'), 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_uses_x_api_key_header_as_fallback(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.default', Mockery::any())
            ->andReturn(['attempts' => 60, 'decay' => 60, 'strategy' => 'per_api_key']);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag([
            'Content-Type' => 'application/json',
            'X-API-Key' => 'header-key-789',
        ]);

        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with($this->matchesRegularExpression('/^mcp:rate_limit:tools\/list:api:[a-f0-9]{16}$/'), 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_uses_anonymous_for_missing_api_key(): void
    {
        $configMock = Mockery::mock('alias:Illuminate\Support\Facades\Config');
        $configMock->shouldReceive('get')
            ->with('laravel-mcp.rate_limiting.default', Mockery::any())
            ->andReturn(['attempts' => 60, 'decay' => 60, 'strategy' => 'per_api_key']);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        // MD5 hash of 'anonymous' starts with '294de3557d9d00b3d424'
        $this->rateLimiter->expects($this->once())
            ->method('tooManyAttempts')
            ->with('mcp:rate_limit:tools/list:api:294de3557d9d00b3', 60)
            ->willReturn(false);

        $this->rateLimiter->expects($this->once())
            ->method('hit');

        $this->rateLimiter->expects($this->once())
            ->method('remaining')
            ->willReturn(59);

        $this->rateLimiter->expects($this->once())
            ->method('availableIn')
            ->willReturn(0);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $this->middleware->handle($request, $next);
    }
}
