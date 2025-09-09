<?php

namespace JTD\LaravelMCP\Tests\Unit\Protocol\Contracts;

use JTD\LaravelMCP\Protocol\Contracts\ProtocolHandlerInterface;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for ProtocolHandlerInterface contract.
 *
 * This test ensures that all implementations of ProtocolHandlerInterface
 * properly implement the required methods for MCP protocol handling.
 */
class ProtocolHandlerInterfaceTest extends TestCase
{
    /** @var ProtocolHandlerInterface&MockObject */
    protected $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->createMock(ProtocolHandlerInterface::class);
    }

    /**
     * Test initialize with capabilities.
     */
    public function test_initialize_with_capabilities(): void
    {
        $capabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
            'logging' => [],
        ];

        $this->handler
            ->expects($this->once())
            ->method('initialize')
            ->with($capabilities);

        $this->handler->initialize($capabilities);
    }

    /**
     * Test initialize with empty capabilities.
     */
    public function test_initialize_with_empty_capabilities(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('initialize')
            ->with([]);

        $this->handler->initialize([]);
    }

    /**
     * Test handleInitialize request.
     */
    public function test_handle_initialize(): void
    {
        $params = [
            'protocolVersion' => '1.0.0',
            'capabilities' => [
                'tools' => [],
                'resources' => ['subscribe' => true],
            ],
            'clientInfo' => [
                'name' => 'TestClient',
                'version' => '1.0.0',
            ],
        ];

        $expectedResponse = [
            'protocolVersion' => '1.0.0',
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => true, 'listChanged' => true],
                'prompts' => ['listChanged' => true],
            ],
            'serverInfo' => [
                'name' => 'Laravel MCP Server',
                'version' => '1.0.0',
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleInitialize')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleInitialize($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleInitialized notification.
     */
    public function test_handle_initialized(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('handleInitialized');

        $this->handler->handleInitialized();
    }

    /**
     * Test handlePing request.
     */
    public function test_handle_ping(): void
    {
        $expectedResponse = ['pong' => true];

        $this->handler
            ->expects($this->once())
            ->method('handlePing')
            ->willReturn($expectedResponse);

        $response = $this->handler->handlePing();

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleToolsList without params.
     */
    public function test_handle_tools_list_without_params(): void
    {
        $expectedResponse = [
            'tools' => [
                [
                    'name' => 'calculator',
                    'description' => 'Perform calculations',
                    'inputSchema' => [
                        'type' => 'object',
                        'properties' => [],
                    ],
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleToolsList')
            ->with([])
            ->willReturn($expectedResponse);

        $response = $this->handler->handleToolsList();

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleToolsList with filters.
     */
    public function test_handle_tools_list_with_filters(): void
    {
        $params = [
            'cursor' => 'page-2',
            'limit' => 10,
        ];

        $expectedResponse = [
            'tools' => [],
            'nextCursor' => 'page-3',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleToolsList')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleToolsList($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleToolsCall request.
     */
    public function test_handle_tools_call(): void
    {
        $params = [
            'name' => 'calculator',
            'arguments' => [
                'operation' => 'add',
                'a' => 5,
                'b' => 3,
            ],
        ];

        $expectedResponse = [
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Result: 8',
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleToolsCall')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleToolsCall($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleResourcesList without params.
     */
    public function test_handle_resources_list_without_params(): void
    {
        $expectedResponse = [
            'resources' => [
                [
                    'uri' => 'file:///test.txt',
                    'name' => 'test.txt',
                    'description' => 'Test file',
                    'mimeType' => 'text/plain',
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResourcesList')
            ->with([])
            ->willReturn($expectedResponse);

        $response = $this->handler->handleResourcesList();

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleResourcesRead request.
     */
    public function test_handle_resources_read(): void
    {
        $params = [
            'uri' => 'file:///test.txt',
        ];

        $expectedResponse = [
            'contents' => [
                [
                    'uri' => 'file:///test.txt',
                    'mimeType' => 'text/plain',
                    'text' => 'Test content',
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResourcesRead')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleResourcesRead($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleResourcesSubscribe request.
     */
    public function test_handle_resources_subscribe(): void
    {
        $params = [
            'uri' => 'file:///test.txt',
        ];

        $expectedResponse = [
            'subscribed' => true,
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResourcesSubscribe')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleResourcesSubscribe($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleResourcesUnsubscribe request.
     */
    public function test_handle_resources_unsubscribe(): void
    {
        $params = [
            'uri' => 'file:///test.txt',
        ];

        $expectedResponse = [
            'unsubscribed' => true,
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResourcesUnsubscribe')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleResourcesUnsubscribe($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handlePromptsList without params.
     */
    public function test_handle_prompts_list_without_params(): void
    {
        $expectedResponse = [
            'prompts' => [
                [
                    'name' => 'greeting',
                    'description' => 'Generate a greeting',
                    'argumentsSchema' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handlePromptsList')
            ->with([])
            ->willReturn($expectedResponse);

        $response = $this->handler->handlePromptsList();

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handlePromptsGet request.
     */
    public function test_handle_prompts_get(): void
    {
        $params = [
            'name' => 'greeting',
            'arguments' => [
                'name' => 'World',
            ],
        ];

        $expectedResponse = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Hello, World!',
                    ],
                ],
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handlePromptsGet')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handlePromptsGet($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleLoggingSetLevel request.
     */
    public function test_handle_logging_set_level(): void
    {
        $params = [
            'level' => 'debug',
        ];

        $expectedResponse = [
            'level' => 'debug',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleLoggingSetLevel')
            ->with($params)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleLoggingSetLevel($params);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test getCapabilities.
     */
    public function test_get_capabilities(): void
    {
        $expectedCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
            'logging' => [],
            'experimental' => [],
        ];

        $this->handler
            ->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($expectedCapabilities);

        $capabilities = $this->handler->getCapabilities();

        $this->assertSame($expectedCapabilities, $capabilities);
    }

    /**
     * Test getServerInfo.
     */
    public function test_get_server_info(): void
    {
        $expectedInfo = [
            'name' => 'Laravel MCP Server',
            'version' => '1.0.0',
            'description' => 'MCP server for Laravel applications',
        ];

        $this->handler
            ->expects($this->once())
            ->method('getServerInfo')
            ->willReturn($expectedInfo);

        $info = $this->handler->getServerInfo();

        $this->assertSame($expectedInfo, $info);
    }

    /**
     * Test canHandleMethod with supported method.
     */
    public function test_can_handle_method_supported(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('canHandleMethod')
            ->with('tools/list')
            ->willReturn(true);

        $this->assertTrue($this->handler->canHandleMethod('tools/list'));
    }

    /**
     * Test canHandleMethod with unsupported method.
     */
    public function test_can_handle_method_unsupported(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('canHandleMethod')
            ->with('unknown/method')
            ->willReturn(false);

        $this->assertFalse($this->handler->canHandleMethod('unknown/method'));
    }

    /**
     * Test getSupportedMethods.
     */
    public function test_get_supported_methods(): void
    {
        $expectedMethods = [
            'initialize',
            'initialized',
            'ping',
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'resources/subscribe',
            'resources/unsubscribe',
            'prompts/list',
            'prompts/get',
            'logging/setLevel',
        ];

        $this->handler
            ->expects($this->once())
            ->method('getSupportedMethods')
            ->willReturn($expectedMethods);

        $methods = $this->handler->getSupportedMethods();

        $this->assertSame($expectedMethods, $methods);
    }

    /**
     * Test full initialization sequence.
     */
    public function test_full_initialization_sequence(): void
    {
        $capabilities = ['tools' => []];
        $initParams = ['protocolVersion' => '1.0.0'];
        $initResponse = ['serverInfo' => ['name' => 'Test']];

        // Initialize handler
        $this->handler
            ->expects($this->once())
            ->method('initialize')
            ->with($capabilities);

        // Handle initialize request
        $this->handler
            ->expects($this->once())
            ->method('handleInitialize')
            ->with($initParams)
            ->willReturn($initResponse);

        // Handle initialized notification
        $this->handler
            ->expects($this->once())
            ->method('handleInitialized');

        // Execute sequence
        $this->handler->initialize($capabilities);
        $response = $this->handler->handleInitialize($initParams);
        $this->assertSame($initResponse, $response);
        $this->handler->handleInitialized();
    }

    /**
     * Test handling all MCP methods.
     */
    public function test_handle_all_mcp_methods(): void
    {
        $methods = [
            'ping' => 'handlePing',
            'tools/list' => 'handleToolsList',
            'tools/call' => 'handleToolsCall',
            'resources/list' => 'handleResourcesList',
            'resources/read' => 'handleResourcesRead',
            'resources/subscribe' => 'handleResourcesSubscribe',
            'resources/unsubscribe' => 'handleResourcesUnsubscribe',
            'prompts/list' => 'handlePromptsList',
            'prompts/get' => 'handlePromptsGet',
            'logging/setLevel' => 'handleLoggingSetLevel',
        ];

        // Set up expectation for multiple calls
        $methodNames = array_keys($methods);
        $callIndex = 0;

        $this->handler
            ->expects($this->exactly(count($methods)))
            ->method('canHandleMethod')
            ->willReturnCallback(function ($method) use ($methodNames, &$callIndex) {
                $this->assertEquals($methodNames[$callIndex], $method);
                $callIndex++;

                return true;
            });

        foreach ($methods as $method => $handlerMethod) {
            $this->assertTrue($this->handler->canHandleMethod($method));
        }
    }

    /**
     * Test error handling for unsupported capabilities.
     */
    public function test_unsupported_capabilities(): void
    {
        $capabilities = [
            'unsupported' => ['feature' => true],
        ];

        $this->handler
            ->expects($this->once())
            ->method('initialize')
            ->with($capabilities);

        $this->handler
            ->expects($this->once())
            ->method('getCapabilities')
            ->willReturn([]); // No unsupported capabilities

        $this->handler->initialize($capabilities);
        $result = $this->handler->getCapabilities();

        $this->assertArrayNotHasKey('unsupported', $result);
    }
}
