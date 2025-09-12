<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JTD\LaravelMCP\Contracts\McpException;
use JTD\LaravelMCP\Http\Middleware\McpErrorHandlingMiddleware;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * EPIC: 021-LaravelMiddleware
 * SPEC: 021-spec-laravel-middleware-integration.md
 * SPRINT: Sprint 5
 * TICKET: LARAVEL-MCP-021
 *
 * Comprehensive test coverage for MCP Error Handling Middleware
 */
#[CoversClass(McpErrorHandlingMiddleware::class)]
#[Group('middleware')]
#[Group('error-handling')]
#[Group('unit')]
class McpErrorHandlingMiddlewareTest extends TestCase
{
    private McpErrorHandlingMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new McpErrorHandlingMiddleware;

        // Set up default config values
        Config::shouldReceive('get')->with('app.debug')->andReturn(false)->byDefault();
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.show_debug_info', true)->andReturn(true)->byDefault();
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.log_stack_trace', true)->andReturn(true)->byDefault();

        Log::spy();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_passes_through_successful_requests(): void
    {
        $request = Request::create('/mcp', 'POST');
        $response = new Response('success');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_handles_validation_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'test-id',
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $errors = [
            'name' => ['The name field is required.'],
            'type' => ['The type field is invalid.'],
        ];
        $exception = ValidationException::withMessages($errors);

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(400, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32602, $data['error']['code']);
        $this->assertEquals('Invalid params', $data['error']['message']);
        $this->assertEquals('validation_error', $data['error']['data']['type']);
        $this->assertEquals('The name field is required.', $data['error']['data']['message']);
        $this->assertEquals($errors, $data['error']['data']['errors']);
        $this->assertEquals('test-id', $data['id']);
    }

    #[Test]
    public function it_handles_authentication_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 123,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $exception = new AuthenticationException('Custom auth message', ['api', 'web']);

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(401, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32001, $data['error']['code']);
        $this->assertEquals('Authentication required', $data['error']['message']);
        $this->assertEquals('authentication_error', $data['error']['data']['type']);
        $this->assertEquals('Custom auth message', $data['error']['data']['message']);
        $this->assertEquals(['api', 'web'], $data['error']['data']['guards']);
        $this->assertEquals(123, $data['id']);
    }

    #[Test]
    public function it_handles_authorization_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'resources/write',
            'id' => null,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $exception = new AuthorizationException('Not authorized to write resources');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(403, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32002, $data['error']['code']);
        $this->assertEquals('Permission denied', $data['error']['message']);
        $this->assertEquals('authorization_error', $data['error']['data']['type']);
        $this->assertEquals('Not authorized to write resources', $data['error']['data']['message']);
        $this->assertNull($data['id']);
    }

    #[Test]
    public function it_handles_model_not_found_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST');
        $request->attributes = new ParameterBag(['mcp_user_id' => 456]);

        $exception = (new ModelNotFoundException)->setModel('App\\Models\\Resource', [1, 2, 3]);

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(404, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32003, $data['error']['code']);
        $this->assertEquals('Resource not found', $data['error']['message']);
        $this->assertEquals('resource_not_found', $data['error']['data']['type']);
        $this->assertEquals('Resource Resource not found', $data['error']['data']['message']);
        $this->assertEquals('Resource', $data['error']['data']['model']);
        $this->assertEquals([1, 2, 3], $data['error']['data']['ids']);
    }

    #[Test]
    public function it_handles_not_found_http_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new NotFoundHttpException('Route not found');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('warning', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(404, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32003, $data['error']['code']);
        $this->assertEquals('Not found', $data['error']['message']);
        $this->assertEquals('not_found', $data['error']['data']['type']);
        $this->assertEquals('Route not found', $data['error']['data']['message']);
    }

    #[Test]
    #[DataProvider('httpExceptionProvider')]
    public function it_handles_http_exceptions(int $statusCode, int $expectedErrorCode, string $expectedMessage): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new HttpException($statusCode, 'Custom HTTP error');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        $logLevel = match ($statusCode) {
            429 => 'info',
            400, 401, 403, 404, 405, 409, 422 => 'warning',
            default => 'error',
        };

        Log::shouldReceive('log')
            ->once()
            ->with($logLevel, 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals($statusCode, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals($expectedErrorCode, $data['error']['code']);
        $this->assertEquals($expectedMessage, $data['error']['message']);
        $this->assertEquals('http_error', $data['error']['data']['type']);
        $this->assertEquals('Custom HTTP error', $data['error']['data']['message']);
        $this->assertEquals($statusCode, $data['error']['data']['status_code']);
    }

    public static function httpExceptionProvider(): array
    {
        return [
            'Bad Request' => [400, -32600, 'Bad request'],
            'Unauthorized' => [401, -32001, 'Authentication required'],
            'Forbidden' => [403, -32002, 'Permission denied'],
            'Not Found' => [404, -32003, 'Not found'],
            'Method Not Allowed' => [405, -32601, 'Method not allowed'],
            'Conflict' => [409, -32004, 'Conflict'],
            'Unprocessable Entity' => [422, -32602, 'Unprocessable entity'],
            'Too Many Requests' => [429, -32029, 'Too many requests'],
            'Internal Server Error' => [500, -32603, 'Internal server error'],
            'Service Unavailable' => [503, -32603, 'Service unavailable'],
            'Other Error' => [418, -32603, 'Error'], // I'm a teapot
        ];
    }

    #[Test]
    public function it_handles_mcp_specific_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new class extends \Exception implements McpException
        {
            public function getErrorCode(): int
            {
                return -32007;
            }

            public function getErrorMessage(): string
            {
                return 'Tool execution failed';
            }

            public function getErrorData(): array
            {
                return [
                    'tool' => 'calculator',
                    'reason' => 'Division by zero',
                ];
            }
        };

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32007, $data['error']['code']);
        $this->assertEquals('Tool execution failed', $data['error']['message']);
        $this->assertEquals('mcp_error', $data['error']['data']['type']);
        $this->assertEquals('calculator', $data['error']['data']['tool']);
        $this->assertEquals('Division by zero', $data['error']['data']['reason']);
    }

    #[Test]
    public function it_handles_mcp_namespace_exceptions(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new class extends \Exception
        {
            public function __construct()
            {
                parent::__construct('MCP namespace exception');
            }
        };

        // Mock the namespace check
        $reflectionClass = new \ReflectionClass($exception);
        $namespace = 'JTD\\LaravelMCP\\Exceptions';

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
    }

    #[Test]
    public function it_handles_generic_exceptions_without_debug(): void
    {
        Config::shouldReceive('get')->with('app.debug')->andReturn(false);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'error-id',
        ]));
        $request->headers = new HeaderBag([
            'Content-Type' => 'application/json',
            'X-Request-ID' => 'req-123',
        ]);

        $exception = new \RuntimeException('Sensitive internal error');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::on(function ($context) {
                return $context['exception'] === 'RuntimeException' &&
                       $context['message'] === 'Sensitive internal error' &&
                       $context['request_id'] === 'req-123' &&
                       $context['mcp_method'] === 'tools/call';
            }));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32603, $data['error']['code']);
        $this->assertEquals('Internal error', $data['error']['message']);
        $this->assertEquals('internal_error', $data['error']['data']['type']);
        $this->assertEquals('An internal error occurred', $data['error']['data']['message']);
        $this->assertArrayNotHasKey('exception', $data['error']['data']);
        $this->assertArrayNotHasKey('file', $data['error']['data']);
        $this->assertArrayNotHasKey('line', $data['error']['data']);
        $this->assertArrayNotHasKey('trace', $data['error']['data']);
        $this->assertEquals('error-id', $data['id']);
    }

    #[Test]
    public function it_handles_generic_exceptions_with_debug(): void
    {
        Config::shouldReceive('get')->with('app.debug')->andReturn(true);
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.show_debug_info', true)->andReturn(true);

        $request = Request::create('/mcp', 'POST');

        $exception = new \LogicException('Debug error message');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        Log::shouldReceive('debug')
            ->once()
            ->with('MCP exception stack trace', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $this->assertInstanceOf(\Illuminate\Http\JsonResponse::class, $result);
        $this->assertEquals(500, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32603, $data['error']['code']);
        $this->assertEquals('Internal error', $data['error']['message']);
        $this->assertEquals('internal_error', $data['error']['data']['type']);
        $this->assertEquals('Debug error message', $data['error']['data']['message']);
        $this->assertEquals('LogicException', $data['error']['data']['exception']);
        $this->assertArrayHasKey('file', $data['error']['data']);
        $this->assertArrayHasKey('line', $data['error']['data']);
        $this->assertArrayHasKey('trace', $data['error']['data']);
        $this->assertIsArray($data['error']['data']['trace']);
    }

    #[Test]
    public function it_does_not_show_debug_info_when_disabled(): void
    {
        Config::shouldReceive('get')->with('app.debug')->andReturn(true);
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.show_debug_info', true)->andReturn(false);

        $request = Request::create('/mcp', 'POST');

        $exception = new \Exception('Should not be shown');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('An internal error occurred', $data['error']['data']['message']);
        $this->assertArrayNotHasKey('exception', $data['error']['data']);
    }

    #[Test]
    public function it_does_not_log_stack_trace_when_disabled(): void
    {
        Config::shouldReceive('get')->with('app.debug')->andReturn(true);
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.log_stack_trace', true)->andReturn(false);

        $request = Request::create('/mcp', 'POST');

        $exception = new \Exception('Test exception');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        Log::shouldReceive('debug')->never();

        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_extracts_request_id_from_non_json_requests(): void
    {
        $request = Request::create('/mcp', 'GET');

        $exception = new \Exception('Test exception');

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')
            ->once()
            ->with('error', 'MCP exception handled', \Mockery::type('array'));

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertNull($data['id']);
    }

    #[Test]
    public function it_maps_mcp_error_codes_to_http_status(): void
    {
        $testCases = [
            [-32001, 401], // AUTHENTICATION_REQUIRED
            [-32002, 403], // PERMISSION_DENIED
            [-32003, 404], // RESOURCE_NOT_FOUND
            [-32004, 409], // RESOURCE_CONFLICT
            [-32029, 429], // RATE_LIMIT_EXCEEDED
            [-32050, 500], // Server error range
            [-32500, 500], // Implementation-defined server error
            [-31000, 400], // Other errors default to bad request
        ];

        foreach ($testCases as [$errorCode, $expectedStatus]) {
            $request = Request::create('/mcp', 'POST');

            $exception = new class($errorCode) extends \Exception implements McpException
            {
                private int $code;

                public function __construct(int $code)
                {
                    $this->code = $code;
                    parent::__construct('Test exception');
                }

                public function getErrorCode(): int
                {
                    return $this->code;
                }

                public function getErrorMessage(): string
                {
                    return 'Test error';
                }

                public function getErrorData(): array
                {
                    return [];
                }
            };

            $next = function ($req) use ($exception) {
                throw $exception;
            };

            Log::shouldReceive('log')->once();

            $result = $this->middleware->handle($request, $next);

            $this->assertEquals($expectedStatus, $result->getStatusCode(),
                "Error code {$errorCode} should map to HTTP {$expectedStatus}");
        }
    }

    #[Test]
    public function it_limits_stack_trace_to_10_frames(): void
    {
        Config::shouldReceive('get')->with('app.debug')->andReturn(true);
        Config::shouldReceive('get')->with('laravel-mcp.error_handling.show_debug_info', true)->andReturn(true);

        $request = Request::create('/mcp', 'POST');

        // Create a deep stack trace
        $createDeepStack = function ($depth) use (&$createDeepStack) {
            if ($depth <= 0) {
                throw new \Exception('Deep stack exception');
            }

            return $createDeepStack($depth - 1);
        };

        $next = function ($req) use ($createDeepStack) {
            try {
                $createDeepStack(20);
            } catch (\Exception $e) {
                throw $e;
            }
        };

        Log::shouldReceive('log')->once();
        Log::shouldReceive('debug')->once();

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertCount(10, $data['error']['data']['trace']);
    }

    #[Test]
    public function it_handles_validation_exception_with_non_array_errors(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'id' => 'test-id',
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $errors = ['field' => 'Single error message'];
        $exception = ValidationException::withMessages($errors);

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')->once();

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('Single error message', $data['error']['data']['message']);
    }

    #[Test]
    public function it_handles_authentication_exception_with_empty_message(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new AuthenticationException;

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')->once();

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('Unauthenticated', $data['error']['data']['message']);
    }

    #[Test]
    public function it_handles_authorization_exception_with_empty_message(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new AuthorizationException;

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')->once();

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('Unauthorized', $data['error']['data']['message']);
    }

    #[Test]
    public function it_handles_not_found_exception_with_empty_message(): void
    {
        $request = Request::create('/mcp', 'POST');

        $exception = new NotFoundHttpException;

        $next = function ($req) use ($exception) {
            throw $exception;
        };

        Log::shouldReceive('log')->once();

        $result = $this->middleware->handle($request, $next);

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('The requested resource was not found', $data['error']['data']['message']);
    }
}
