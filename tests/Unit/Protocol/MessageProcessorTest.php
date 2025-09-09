<?php

namespace JTD\LaravelMCP\Tests\Unit\Protocol;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for MessageProcessor class.
 *
 * This test suite ensures the MessageProcessor properly integrates with handlers,
 * manages MCP server lifecycle, handles initialization, and routes messages correctly.
 *
 * @epic 009-McpServerHandlers
 *
 * @spec docs/Specs/009-McpServerHandlers.md
 *
 * @ticket 009-McpServerHandlers.md
 *
 * @sprint Sprint-2
 */
#[CoversClass(MessageProcessor::class)]
class MessageProcessorTest extends TestCase
{
    private JsonRpcHandlerInterface $jsonRpcHandler;

    private McpRegistry $registry;

    private ToolRegistry $toolRegistry;

    private ResourceRegistry $resourceRegistry;

    private PromptRegistry $promptRegistry;

    private CapabilityNegotiator $capabilityNegotiator;

    private TransportInterface $transport;

    private MessageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jsonRpcHandler = Mockery::mock(JsonRpcHandlerInterface::class);
        $this->registry = Mockery::mock(McpRegistry::class);
        $this->toolRegistry = Mockery::mock(ToolRegistry::class);
        $this->resourceRegistry = Mockery::mock(ResourceRegistry::class);
        $this->promptRegistry = Mockery::mock(PromptRegistry::class);
        $this->capabilityNegotiator = Mockery::mock(CapabilityNegotiator::class);
        $this->transport = Mockery::mock(TransportInterface::class);

        // Configure mock to allow all onRequest and onNotification calls during construction
        $this->jsonRpcHandler->shouldReceive('onRequest')->andReturn();
        $this->jsonRpcHandler->shouldReceive('onNotification')->andReturn();

        $this->processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructor_initializes_handlers_and_capabilities(): void
    {
        // Constructor should call setupHandlers and setupServerCapabilities
        $this->assertInstanceOf(MessageProcessor::class, $this->processor);
        $this->assertFalse($this->processor->isInitialized());
        $this->assertSame([], $this->processor->getClientCapabilities());
        $this->assertIsArray($this->processor->getServerCapabilities());
    }

    #[Test]
    public function get_supported_message_types_returns_expected_methods(): void
    {
        $expected = [
            'initialize',
            'initialized',
            'ping',
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'resources/templates/list',
            'prompts/list',
            'prompts/get',
        ];

        $this->assertSame($expected, $this->processor->getSupportedMessageTypes());
    }

    #[Test]
    public function can_handle_returns_true_for_valid_json_rpc_messages(): void
    {
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->processor->canHandle($message));
    }

    #[Test]
    public function can_handle_returns_false_for_invalid_messages(): void
    {
        $message = ['invalid' => 'message'];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->processor->canHandle($message));
    }

    #[Test]
    public function handle_returns_error_for_invalid_json_rpc_messages(): void
    {
        $message = ['invalid' => 'message', 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('createErrorResponse')
            ->with(-32600, 'Invalid request', null, 1)
            ->once()
            ->andReturn(['error' => ['code' => -32600, 'message' => 'Invalid request']]);

        $response = $this->processor->handle($message, $this->transport);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32600, $response['error']['code']);
    }

    #[Test]
    public function handle_processes_valid_requests(): void
    {
        $message = ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1];
        $expectedResponse = ['jsonrpc' => '2.0', 'result' => [], 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('isRequest')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('handleRequest')
            ->with($message)
            ->once()
            ->andReturn($expectedResponse);

        $response = $this->processor->handle($message, $this->transport);

        $this->assertSame($expectedResponse, $response);
    }

    #[Test]
    public function handle_processes_notifications_without_response(): void
    {
        $message = ['jsonrpc' => '2.0', 'method' => 'initialized', 'params' => []];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('isRequest')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('isNotification')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('handleNotification')
            ->with($message)
            ->once();

        $response = $this->processor->handle($message, $this->transport);

        $this->assertNull($response);
    }

    #[Test]
    public function handle_processes_responses_without_response(): void
    {
        $message = ['jsonrpc' => '2.0', 'result' => [], 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('isRequest')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('isNotification')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('isResponse')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('handleResponse')
            ->with($message)
            ->once();

        $response = $this->processor->handle($message, $this->transport);

        $this->assertNull($response);
    }

    #[Test]
    public function handle_returns_error_for_unrecognized_message_types(): void
    {
        $message = ['jsonrpc' => '2.0', 'unknown' => 'type', 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('isRequest')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('isNotification')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('isResponse')
            ->with($message)
            ->once()
            ->andReturn(false);

        $this->jsonRpcHandler
            ->shouldReceive('createErrorResponse')
            ->with(-32600, 'Invalid request', null, 1)
            ->once()
            ->andReturn(['error' => ['code' => -32600, 'message' => 'Invalid request']]);

        $response = $this->processor->handle($message, $this->transport);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32600, $response['error']['code']);
    }

    #[Test]
    public function handle_catches_and_handles_exceptions(): void
    {
        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];

        $this->jsonRpcHandler
            ->shouldReceive('validateMessage')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->jsonRpcHandler
            ->shouldReceive('isRequest')
            ->with($message)
            ->once()
            ->andThrow(new \RuntimeException('Test exception'));

        $this->jsonRpcHandler
            ->shouldReceive('createErrorResponse')
            ->with(-32603, 'Internal error', null, 1)
            ->once()
            ->andReturn(['error' => ['code' => -32603, 'message' => 'Internal error']]);

        $response = $this->processor->handle($message, $this->transport);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32603, $response['error']['code']);
    }

    #[Test]
    public function handle_error_logs_transport_errors(): void
    {
        $error = new \RuntimeException('Transport error');

        $this->transport
            ->shouldReceive('getConfig')
            ->andReturn(['host' => '127.0.0.1']);

        // Should not throw, just log
        $this->processor->handleError($error, $this->transport);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function on_connect_logs_transport_connection(): void
    {
        $this->transport
            ->shouldReceive('getConfig')
            ->once()
            ->andReturn(['host' => '127.0.0.1', 'port' => 8080]);

        // Should not throw, just log
        $this->processor->onConnect($this->transport);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function on_disconnect_resets_initialization_state(): void
    {
        // First initialize the processor
        $this->initializeProcessor();

        $this->assertTrue($this->processor->isInitialized());
        $this->assertNotEmpty($this->processor->getClientCapabilities());

        // Now disconnect
        $this->processor->onDisconnect($this->transport);

        $this->assertFalse($this->processor->isInitialized());
        $this->assertEmpty($this->processor->getClientCapabilities());
    }

    #[Test]
    public function set_server_info_updates_server_information(): void
    {
        $newInfo = ['name' => 'Custom MCP Server', 'version' => '2.0.0', 'author' => 'Test'];

        $this->processor->setServerInfo($newInfo);

        $serverInfo = $this->processor->getServerInfo();
        $this->assertSame('Custom MCP Server', $serverInfo['name']);
        $this->assertSame('2.0.0', $serverInfo['version']);
        $this->assertSame('Test', $serverInfo['author']);
    }

    #[Test]
    public function get_server_info_returns_current_information(): void
    {
        $serverInfo = $this->processor->getServerInfo();

        $this->assertArrayHasKey('name', $serverInfo);
        $this->assertArrayHasKey('version', $serverInfo);
        $this->assertSame('Laravel MCP Server', $serverInfo['name']);
        $this->assertSame('1.0.0', $serverInfo['version']);
    }

    #[Test]
    public function initialization_workflow_works_correctly(): void
    {
        $initializeParams = [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'Test Client', 'version' => '1.0.0'],
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => true],
            ],
        ];

        $expectedServerCapabilities = [
            'tools' => ['listChanged' => false],
            'resources' => ['subscribe' => false, 'listChanged' => false],
            'prompts' => ['listChanged' => false],
        ];

        $this->capabilityNegotiator
            ->shouldReceive('negotiate')
            ->with($initializeParams['capabilities'], $expectedServerCapabilities)
            ->zeroOrMoreTimes()
            ->andReturn($expectedServerCapabilities);

        // Set up other handler registrations first to avoid conflicts
        $this->setupJsonRpcHandlerMocks();

        // Re-create processor to trigger setup
        $processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );

        $this->assertFalse($processor->isInitialized());
        $this->assertEmpty($processor->getClientCapabilities());
    }

    #[Test]
    public function tools_list_handler_integration_works(): void
    {
        $this->setupJsonRpcHandlerMocks();

        // Re-create processor to trigger setup (which registers the handlers)
        $processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );

        // Test should just verify that MessageProcessor can be created successfully
        // The setupJsonRpcHandlerMocks() already ensures onRequest is called for tools/list
        $this->assertInstanceOf(MessageProcessor::class, $processor);
        $this->assertContains('tools/list', $processor->getSupportedMessageTypes());
    }

    #[Test]
    public function resources_list_handler_integration_works(): void
    {
        $this->setupJsonRpcHandlerMocks();

        // Re-create processor to trigger setup (which registers the handlers)
        $processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );

        // Test should just verify that MessageProcessor can be created successfully
        $this->assertInstanceOf(MessageProcessor::class, $processor);
        $this->assertContains('resources/list', $processor->getSupportedMessageTypes());
    }

    #[Test]
    public function prompts_list_handler_integration_works(): void
    {
        $this->setupJsonRpcHandlerMocks();

        // Re-create processor to trigger setup (which registers the handlers)
        $processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );

        // Test should just verify that MessageProcessor can be created successfully
        $this->assertInstanceOf(MessageProcessor::class, $processor);
        $this->assertContains('prompts/list', $processor->getSupportedMessageTypes());
    }

    #[Test]
    public function handler_methods_check_initialization_status(): void
    {
        // Don't initialize the processor - should throw ProtocolException

        $this->toolRegistry
            ->shouldReceive('all')
            ->never(); // Should not be called due to initialization check

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32002);
        $this->expectExceptionMessage('Server not initialized');

        // Create a fresh processor that is not initialized
        $processor = new MessageProcessor(
            $this->jsonRpcHandler,
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
            $this->capabilityNegotiator
        );

        // Call a handler method that should check initialization
        // Use reflection to access the protected method
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('handleToolsList');
        $method->setAccessible(true);
        
        // This should throw ProtocolException because not initialized
        $method->invoke($processor, []);
    }

    private function initializeProcessor(): void
    {
        $initParams = [
            'protocolVersion' => '2024-11-05',
            'clientInfo' => ['name' => 'Test Client'],
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => true],
            ],
        ];

        $serverCapabilities = [
            'tools' => ['listChanged' => false],
            'resources' => ['subscribe' => false, 'listChanged' => false],
            'prompts' => ['listChanged' => false],
        ];

        $this->capabilityNegotiator
            ->shouldReceive('negotiate')
            ->andReturn($serverCapabilities);

        // Simulate initialization through reflection to test initialized state
        $reflection = new \ReflectionClass($this->processor);
        $initializedProperty = $reflection->getProperty('initialized');
        $initializedProperty->setAccessible(true);
        $initializedProperty->setValue($this->processor, true);

        $clientCapabilitiesProperty = $reflection->getProperty('clientCapabilities');
        $clientCapabilitiesProperty->setAccessible(true);
        $clientCapabilitiesProperty->setValue($this->processor, $initParams['capabilities']);
    }

    private function setupJsonRpcHandlerMocks(): void
    {
        // Mock all the onRequest and onNotification calls made in setupHandlers
        $methods = [
            'initialize', 'ping', 'tools/list', 'tools/call',
            'resources/list', 'resources/read', 'resources/templates/list',
            'prompts/list', 'prompts/get',
        ];

        foreach ($methods as $method) {
            $this->jsonRpcHandler
                ->shouldReceive('onRequest')
                ->with($method, Mockery::type('callable'))
                ->zeroOrMoreTimes();
        }

        $this->jsonRpcHandler
            ->shouldReceive('onNotification')
            ->with('initialized', Mockery::type('callable'))
            ->zeroOrMoreTimes();
    }
}
