<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Validator;
use JTD\LaravelMCP\Http\Exceptions\McpValidationException;
use JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware;
use JTD\LaravelMCP\Tests\UnitTestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\HeaderBag;

/**
 * EPIC: 021-LaravelMiddleware
 * SPEC: 021-spec-laravel-middleware-integration.md
 * SPRINT: Sprint 5
 * TICKET: LARAVEL-MCP-021
 *
 * Comprehensive test coverage for MCP Validation Middleware
 */
#[CoversClass(McpValidationMiddleware::class)]
#[Group('middleware')]
#[Group('validation')]
#[Group('unit')]
class McpValidationMiddlewareTest extends UnitTestCase
{
    private McpValidationMiddleware $middleware;

    private MockObject|ValidationFactory $validatorFactory;

    private MockObject|Validator $validator;

    private array $defaultConfig = [
        'enabled' => true,
        'strict_content_type' => true,
        'max_request_size' => 10485760,
        'strict_mcp_methods' => false,
        'supported_protocol_versions' => ['1.0'],
        'allow_custom_tools' => true,
        'custom_methods' => [],
        'method_rules' => [],
        'capabilities' => [
            'tools' => true,
            'resources' => true,
            'prompts' => true,
            'logging' => true,
            'completion' => true,
            'sampling' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = $this->createMock(Validator::class);
        $this->validatorFactory = $this->createMock(ValidationFactory::class);
        $this->middleware = new McpValidationMiddleware($this->validatorFactory, $this->defaultConfig);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * Create middleware with custom config
     */
    protected function createMiddlewareWithConfig(array $config, ?LoggerInterface $logger = null): McpValidationMiddleware
    {
        $mergedConfig = array_replace_recursive($this->defaultConfig, $config);

        return new McpValidationMiddleware($this->validatorFactory, $mergedConfig, $logger);
    }

    #[Test]
    public function it_skips_validation_when_disabled(): void
    {
        $middleware = $this->createMiddlewareWithConfig(['enabled' => false]);

        $request = Request::create('/mcp', 'POST');
        $response = new Response('success');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_content_type_for_post_requests(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], 'test content');
        $request->headers = new HeaderBag(['Content-Type' => 'text/plain']);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_allows_non_json_content_when_strict_mode_disabled(): void
    {
        $middleware = $this->createMiddlewareWithConfig(['strict_content_type' => false]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], 'test content');
        $request->headers = new HeaderBag(['Content-Type' => 'text/plain']);
        $response = new Response('success');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_request_size(): void
    {
        $middleware = $this->createMiddlewareWithConfig(['max_request_size' => 100]);

        $largeContent = str_repeat('x', 200);
        $request = Request::create('/mcp', 'POST', [], [], [], [], $largeContent);
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $middleware->handle($request, $next);
    }

    #[Test]
    public function it_validates_json_rpc_structure(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '1.0', // Invalid version
            'method' => 'test',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(true);

        $this->validatorFactory->expects($this->once())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_validates_valid_json_rpc_request(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_method_format(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'invalid-method-format!',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_allows_single_word_methods(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_params_must_be_array(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => 'invalid',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_validates_id_format(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => ['invalid'], // Invalid ID format
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_enforces_strict_mcp_methods_when_enabled(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'strict_mcp_methods' => true,
            'custom_methods' => [],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'unknown/method',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $middleware->handle($request, $next);
    }

    #[Test]
    public function it_allows_custom_methods_from_config(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'strict_mcp_methods' => true,
            'custom_methods' => ['custom/method'],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'custom/method',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_initialize_protocol_version(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'supported_protocol_versions' => ['1.0', '2.0'],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '3.0', // Unsupported version
                'capabilities' => [],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $middleware->handle($request, $next);
    }

    #[Test]
    public function it_validates_capabilities_configuration(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [
                    'tools' => 'invalid', // Should be array or boolean
                ],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }

    #[Test]
    public function it_validates_disabled_capabilities(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'capabilities' => ['tools' => false],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'test'],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->once())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $middleware->handle($request, $next);
    }

    #[Test]
    #[DataProvider('methodParameterValidationProvider')]
    public function it_validates_method_specific_parameters(string $method, array $params, bool $shouldPass): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(! $shouldPass);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        if (! $shouldPass) {
            $this->expectException(McpValidationException::class);
        }

        $result = $this->middleware->handle($request, $next);

        if ($shouldPass) {
            $this->assertSame($response, $result);
        }
    }

    public static function methodParameterValidationProvider(): array
    {
        return [
            'tools/call with valid params' => [
                'tools/call',
                ['name' => 'test-tool', 'arguments' => ['arg1' => 'value1']],
                true,
            ],
            'tools/call without name' => [
                'tools/call',
                ['arguments' => ['arg1' => 'value1']],
                false,
            ],
            'resources/read with valid uri' => [
                'resources/read',
                ['uri' => 'file://test.txt'],
                true,
            ],
            'resources/read without uri' => [
                'resources/read',
                [],
                false,
            ],
            'resources/write with valid params' => [
                'resources/write',
                ['uri' => 'file://test.txt', 'contents' => 'test content'],
                true,
            ],
            'resources/write without contents' => [
                'resources/write',
                ['uri' => 'file://test.txt'],
                false,
            ],
            'prompts/get with valid params' => [
                'prompts/get',
                ['name' => 'test-prompt', 'arguments' => ['arg1' => 'value1']],
                true,
            ],
            'logging/setLevel with valid level' => [
                'logging/setLevel',
                ['level' => 'debug'],
                true,
            ],
            'logging/setLevel with invalid level' => [
                'logging/setLevel',
                ['level' => 'invalid'],
                false,
            ],
            'sampling/createMessage with valid params' => [
                'sampling/createMessage',
                [
                    'messages' => [
                        ['role' => 'user', 'content' => 'Hello'],
                    ],
                    'temperature' => 0.7,
                    'maxTokens' => 100,
                ],
                true,
            ],
            'sampling/createMessage with invalid role' => [
                'sampling/createMessage',
                [
                    'messages' => [
                        ['role' => 'invalid', 'content' => 'Hello'],
                    ],
                ],
                false,
            ],
            'completion/complete with valid params' => [
                'completion/complete',
                [
                    'ref' => 'test-ref',
                    'argument' => ['name' => 'arg1', 'value' => 'val1'],
                ],
                true,
            ],
        ];
    }

    #[Test]
    public function it_applies_custom_validation_rules_from_config(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'method_rules' => [
                'tools/call' => ['name' => 'required|min:5'],
            ],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'test'], // Too short for custom rule
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(true);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $middleware->handle($request, $next);
    }

    #[Test]
    public function it_allows_custom_tool_methods(): void
    {
        $middleware = $this->createMiddlewareWithConfig([
            'strict_mcp_methods' => true,
            'allow_custom_tools' => true,
            'custom_methods' => [],
        ]);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/my-custom-tool',
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_logs_unknown_capabilities(): void
    {
        // Create a mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Unknown capability requested', ['capability' => 'unknown']);

        $middleware = new McpValidationMiddleware($this->validatorFactory, $this->defaultConfig, $logger);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [
                    'unknown' => true,
                ],
                'clientInfo' => ['name' => 'test', 'version' => '1.0'],
            ],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(false);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $response = new Response('success');
        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_handles_validation_exception_properly(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [],
            'id' => 'test-id',
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $errors = ['name' => ['The name field is required.']];

        // Create a mock MessageBag for errors
        $errorBag = $this->createMock(\Illuminate\Support\MessageBag::class);
        $errorBag->expects($this->any())
            ->method('toArray')
            ->willReturn($errors);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(true);

        $this->validator->expects($this->any())
            ->method('errors')
            ->willReturn($errorBag);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        try {
            $this->middleware->handle($request, $next);
            $this->fail('Expected McpValidationException');
        } catch (McpValidationException $e) {
            $this->assertEquals($errors, $e->errors());
        }
    }

    #[Test]
    public function it_handles_generic_exceptions(): void
    {
        // Create a mock logger
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('MCP validation error'),
                $this->callback(function ($context) {
                    return isset($context['error']) && isset($context['trace']);
                })
            );

        $middleware = new McpValidationMiddleware($this->validatorFactory, $this->defaultConfig, $logger);

        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [],
            'id' => 'test-id',
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willThrowException(new \RuntimeException('Test exception'));

        $next = function ($req) {
            return new Response('success');
        };

        $response = $middleware->handle($request, $next);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('2.0', $data['jsonrpc']);
        $this->assertEquals(-32603, $data['error']['code']);
        $this->assertEquals('Validation error occurred', $data['error']['message']);
    }

    #[Test]
    public function it_skips_validation_for_non_json_requests(): void
    {
        $request = Request::create('/mcp', 'GET');
        $response = new Response('success');

        $next = function ($req) use ($response) {
            return $response;
        };

        $result = $this->middleware->handle($request, $next);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_validates_nested_parameters(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '1.0',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'test',
                    // Missing 'version'
                ],
            ],
            'id' => 1,
        ]));
        $request->headers = new HeaderBag(['Content-Type' => 'application/json']);

        $errors = ['clientInfo.version' => ['The clientInfo.version field is required.']];

        // Create a mock MessageBag for errors
        $errorBag = $this->createMock(\Illuminate\Support\MessageBag::class);
        $errorBag->expects($this->any())
            ->method('toArray')
            ->willReturn($errors);

        $this->validator->expects($this->any())
            ->method('fails')
            ->willReturn(true);

        $this->validator->expects($this->any())
            ->method('errors')
            ->willReturn($errorBag);

        $this->validatorFactory->expects($this->any())
            ->method('make')
            ->willReturn($this->validator);

        $next = function ($req) {
            return new Response('success');
        };

        $this->expectException(McpValidationException::class);
        $this->middleware->handle($request, $next);
    }
}
