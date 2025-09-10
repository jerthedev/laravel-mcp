<?php

namespace JTD\LaravelMCP\Tests\Feature\Protocol;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Events\NotificationBroadcast;
use JTD\LaravelMCP\Events\NotificationDelivered;
use JTD\LaravelMCP\Events\NotificationFailed;
use JTD\LaravelMCP\Events\NotificationQueued;
use JTD\LaravelMCP\Events\NotificationSent;
use JTD\LaravelMCP\Http\Controllers\McpController;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

/**
 * EPIC: PROTOCOL INTEGRATION
 * SPEC: docs/Specs/13-TransportProtocol.md
 * SPRINT: Sprint 3
 * TICKET: PROTOCOL-013
 *
 * Comprehensive feature tests for end-to-end MCP protocol handling.
 * Tests complete protocol flows, transport integration, and real-world scenarios.
 */
#[Group('feature')]
#[Group('integration')]
#[Group('protocol')]
#[Group('transport')]
class ProtocolIntegrationTest extends TestCase
{
    protected MessageProcessor $messageProcessor;
    protected TransportManager $transportManager;
    protected HttpTransport $httpTransport;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up comprehensive MCP configuration for protocol testing
        config([
            'laravel-mcp.enabled' => true,
            'laravel-mcp.debug' => true,
            'app.debug' => true,
            'logging.default' => 'single',
            'logging.channels.single.level' => 'debug',
            'laravel-mcp.server.name' => 'Test MCP Protocol Server',
            'laravel-mcp.server.version' => '1.0.0',
            'laravel-mcp.server.description' => 'Integration test server',

            // Transport configuration
            'mcp-transports.http.enabled' => true,
            'mcp-transports.http.host' => '127.0.0.1',
            'mcp-transports.http.port' => 8080,
            'mcp-transports.http.path' => '/mcp',
            'mcp-transports.http.middleware' => ['api'],
            'mcp-transports.http.timeout' => 30,
            'mcp-transports.http.max_payload_size' => 1048576, // 1MB

            'mcp-transports.stdio.enabled' => false, // Disabled for HTTP-focused tests

            // Protocol configuration
            'laravel-mcp.protocol.version' => '2024-11-05',
            'laravel-mcp.protocol.json_rpc_version' => '2.0',
            'laravel-mcp.protocol.strict_validation' => true,

            // Capabilities
            'laravel-mcp.capabilities.tools.enabled' => true,
            'laravel-mcp.capabilities.resources.enabled' => true,
            'laravel-mcp.capabilities.prompts.enabled' => true,
            'laravel-mcp.capabilities.notifications.enabled' => true,

            // Error handling
            'laravel-mcp.error_handling.detailed_errors' => true,
            'laravel-mcp.error_handling.stack_traces' => true,
            'laravel-mcp.error_handling.retry_attempts' => 3,
        ]);

        // Clear the MessageProcessor singleton to ensure clean state for each test
        $this->app->forgetInstance(MessageProcessor::class);
        
        $this->messageProcessor = app(MessageProcessor::class);
        $this->transportManager = app(TransportManager::class);
        $this->httpTransport = $this->transportManager->createTransport('http');

        $this->setupTestRoutes();
        // Components will be registered on demand in tests
    }

    protected function setupTestRoutes(): void
    {
        $this->app['router']->prefix('mcp')->group(function ($router) {
            $router->post('/', [McpController::class, 'handle'])->name('mcp.handle');
            $router->options('/', [McpController::class, 'options'])->name('mcp.options');
            $router->get('/health', [McpController::class, 'health'])->name('mcp.health');
            $router->get('/info', [McpController::class, 'info'])->name('mcp.info');
        });
    }

    protected function setupTestComponents(): void
    {
        // Register test tools with various complexity levels
        $this->registerComplexCalculatorTool();
        $this->registerAsyncProcessingTool();
        $this->registerErrorProducingTool();

        // Register test resources
        $this->registerDynamicConfigResource();
        $this->registerLargeDataResource();

        // Register test prompts
        $this->registerParameterizedPrompt();
        $this->registerComplexPrompt();
    }

    // =============================================================================
    // END-TO-END MCP PROTOCOL FLOW TESTS
    // =============================================================================

    /**
     * Test complete server initialization sequence
     */
    #[Test]
    public function it_handles_complete_server_initialization_sequence(): void
    {
        // Step 1: Initialize request
        $initResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => ['listChanged' => true],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                    'prompts' => ['listChanged' => false],
                    'logging' => [],
                ],
                'clientInfo' => [
                    'name' => 'Test MCP Client',
                    'version' => '1.0.0',
                ],
            ],
            'id' => 1,
        ]);

        $initResponse->assertStatus(200);
        $initData = $initResponse->json();

        $this->assertEquals('2.0', $initData['jsonrpc']);
        $this->assertEquals(1, $initData['id']);
        $this->assertArrayHasKey('result', $initData);

        $result = $initData['result'];
        $this->assertEquals('2024-11-05', $result['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $result);
        $this->assertArrayHasKey('serverInfo', $result);

        // Verify server capabilities were negotiated
        $capabilities = $result['capabilities'];
        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);

        // Step 2: Send initialized notification
        $initializedResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            'params' => [],
        ]);

        $initializedResponse->assertStatus(204);

        // Step 3: Verify server is now ready for requests
        $pingResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2,
        ]);

        $pingResponse->assertStatus(200);
        $pingData = $pingResponse->json();
        $this->assertEquals(2, $pingData['id']);
        $this->assertArrayHasKey('result', $pingData);
    }

    /**
     * Test capability negotiation with different client configurations
     */
    #[Test]
    public function it_negotiates_capabilities_based_on_client_support(): void
    {
        // Test minimal client capabilities
        $minimalResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => [],
                ],
                'clientInfo' => ['name' => 'Minimal Client'],
            ],
            'id' => 1,
        ]);

        $minimalResponse->assertStatus(200);
        $minimalData = $minimalResponse->json();
        $capabilities = $minimalData['result']['capabilities'];

        // Should still provide basic capabilities
        $this->assertArrayHasKey('tools', $capabilities);

        // Test full-featured client capabilities
        $fullResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => ['listChanged' => true],
                    'resources' => ['subscribe' => true, 'listChanged' => true],
                    'prompts' => ['listChanged' => true],
                    'logging' => ['level' => 'debug'],
                    'experimental' => ['customFeature' => true],
                ],
                'clientInfo' => ['name' => 'Full Client'],
            ],
            'id' => 2,
        ]);

        $fullResponse->assertStatus(200);
        $fullData = $fullResponse->json();
        $fullCapabilities = $fullData['result']['capabilities'];

        // Should provide enhanced capabilities
        $this->assertArrayHasKey('tools', $fullCapabilities);
        $this->assertArrayHasKey('resources', $fullCapabilities);
        $this->assertArrayHasKey('prompts', $fullCapabilities);
    }

    /**
     * Test complete tool execution flow
     */
    #[Test]
    public function it_handles_complete_tool_execution_flow(): void
    {
        // Register required tool
        $this->registerComplexCalculatorTool();

        // Verify tool was registered
        $registry = app('mcp.registry');
        $this->assertGreaterThan(0, count($registry->getTools()), 'No tools registered');
        
        // Initialize server first
        $this->initializeServer();
        
        // Verify initialization took effect
        $messageProcessor = app(\JTD\LaravelMCP\Protocol\MessageProcessor::class);
        $this->assertTrue($messageProcessor->isInitialized(), 'MessageProcessor should be initialized after server init');

        // Step 1: List available tools
        $listResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]);

        $listResponse->assertStatus(200);
        $listData = $listResponse->json();
        
        $this->assertArrayHasKey('result', $listData, 'Expected result key in response: ' . json_encode($listData));
        $this->assertArrayHasKey('tools', $listData['result']);
        $this->assertGreaterThan(0, count($listData['result']['tools']));

        // Find the complex calculator tool
        $tools = $listData['result']['tools'];
        $calculatorTool = collect($tools)->firstWhere('name', 'complex_calculator');
        $this->assertNotNull($calculatorTool, 'Complex calculator tool should be available');

        // Step 2: Execute tool with valid parameters
        $executeResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'complex_calculator',
                'arguments' => [
                    'operation' => 'multiply',
                    'values' => [5, 3, 2],
                    'precision' => 2,
                ],
            ],
            'id' => 2,
        ]);

        $executeResponse->assertStatus(200);
        $executeData = $executeResponse->json();

        $this->assertEquals('2.0', $executeData['jsonrpc']);
        $this->assertEquals(2, $executeData['id']);
        $this->assertArrayHasKey('content', $executeData['result']);

        $content = $executeData['result']['content'];
        $this->assertIsArray($content);
        $this->assertGreaterThan(0, count($content));

        // Verify content structure
        $firstContent = $content[0];
        $this->assertArrayHasKey('type', $firstContent);
        $this->assertArrayHasKey('text', $firstContent);
        $this->assertEquals('text', $firstContent['type']);
        $this->assertStringContainsString('30', $firstContent['text']); // 5*3*2 = 30
    }

    /**
     * Test complete resource reading flow
     */
    #[Test]
    public function it_handles_complete_resource_reading_flow(): void
    {
        // Register required resource
        $this->registerDynamicConfigResource();

        $this->initializeServer();

        // Step 1: List available resources
        $listResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/list',
            'id' => 1,
        ]);

        $listResponse->assertStatus(200);
        $listData = $listResponse->json();
        $this->assertArrayHasKey('resources', $listData['result']);

        // Step 2: Read a specific resource
        $readResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [
                'uri' => 'config://dynamic',
            ],
            'id' => 2,
        ]);

        $readResponse->assertStatus(200);
        $readData = $readResponse->json();

        $this->assertArrayHasKey('contents', $readData['result']);
        $contents = $readData['result']['contents'];
        $this->assertIsArray($contents);
        $this->assertGreaterThan(0, count($contents));

        $firstContent = $contents[0];
        $this->assertArrayHasKey('uri', $firstContent);
        $this->assertArrayHasKey('mimeType', $firstContent);
        $this->assertArrayHasKey('text', $firstContent);
    }

    /**
     * Test complete prompt retrieval flow
     */
    #[Test]
    public function it_handles_complete_prompt_retrieval_flow(): void
    {
        // Register required prompt
        $this->registerParameterizedPrompt();

        $this->initializeServer();

        // Step 1: List available prompts
        $listResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'prompts/list',
            'id' => 1,
        ]);

        $listResponse->assertStatus(200);
        $listData = $listResponse->json();
        $this->assertArrayHasKey('prompts', $listData['result']);

        // Step 2: Get a specific prompt with arguments
        $getResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'prompts/get',
            'params' => [
                'name' => 'parameterized_assistant',
                'arguments' => [
                    'role' => 'code_reviewer',
                    'context' => 'Laravel application',
                    'tone' => 'professional',
                ],
            ],
            'id' => 2,
        ]);

        $getResponse->assertStatus(200);
        $getData = $getResponse->json();

        $this->assertArrayHasKey('messages', $getData['result']);
        $messages = $getData['result']['messages'];
        $this->assertIsArray($messages);
        $this->assertGreaterThan(0, count($messages));

        $firstMessage = $messages[0];
        $this->assertArrayHasKey('role', $firstMessage);
        $this->assertArrayHasKey('content', $firstMessage);
    }

    /**
     * Test notification delivery flow
     */
    #[Test]
    public function it_handles_notification_delivery_flow(): void
    {
        Event::fake([
            NotificationSent::class,
            NotificationDelivered::class,
            NotificationQueued::class,
            NotificationBroadcast::class,
        ]);

        $this->initializeServer();

        // Send various types of notifications
        $notifications = [
            ['method' => 'notifications/initialized'],
            ['method' => 'notifications/progress', 'params' => ['progress' => 0.5, 'message' => 'Processing...']],
            ['method' => 'notifications/cancelled', 'params' => ['requestId' => 'req-123']],
        ];

        foreach ($notifications as $notification) {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => $notification['method'],
                'params' => $notification['params'] ?? [],
            ]);

            // Notifications should return no content
            $response->assertStatus(204);
        }

        // Verify events were dispatched
        Event::assertDispatched(NotificationSent::class);
    }

    // =============================================================================
    // TRANSPORT LAYER INTEGRATION TESTS
    // =============================================================================

    /**
     * Test HTTP transport integration with different request types
     */
    #[Test]
    public function it_integrates_with_http_transport_for_different_request_types(): void
    {
        // Test regular JSON-RPC request
        $requestResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);
        $requestResponse->assertStatus(200);

        // Test JSON-RPC notification (no id field)
        $notificationResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            'params' => [],
        ]);
        $notificationResponse->assertStatus(204);

        // Test batch request
        $batchResponse = $this->postJson('/mcp', [
            [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => 1,
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => 2,
            ],
        ]);
        $batchResponse->assertStatus(200);
        $batchData = $batchResponse->json();
        $this->assertIsArray($batchData);
        $this->assertCount(2, $batchData);
    }

    /**
     * Test stdio transport simulation
     */
    #[Test]
    public function it_simulates_stdio_transport_integration(): void
    {
        // Create a simulated stdio transport
        $stdioTransport = $this->createStdioTransportMock();

        // Configure the mock to simulate stdio behavior
        $stdioTransport->method('isConnected')->willReturn(true);
        $stdioTransport->method('receive')->willReturn(
            json_encode([
                'jsonrpc' => '2.0',
                'method' => 'initialize',
                'params' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => ['tools' => []],
                    'clientInfo' => ['name' => 'Stdio Test Client'],
                ],
                'id' => 1,
            ])
        );

        // Process message through message processor
        $message = json_decode($stdioTransport->receive(), true);
        $response = $this->messageProcessor->handle($message, $stdioTransport);

        $this->assertIsArray($response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
    }

    /**
     * Test message routing through different transports
     */
    #[Test]
    public function it_routes_messages_through_different_transport_types(): void
    {
        // Test HTTP transport routing
        $httpResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 'http-1',
        ]);
        $httpResponse->assertStatus(200);
        $httpData = $httpResponse->json();
        $this->assertEquals('http-1', $httpData['id']);

        // Test transport-specific headers and metadata
        $httpResponse->assertHeader('Content-Type', 'application/json');

        // Test error routing through HTTP transport
        $errorResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'nonexistent/method',
            'id' => 'error-1',
        ]);
        $errorResponse->assertStatus(200); // JSON-RPC errors are still HTTP 200
        $errorData = $errorResponse->json();
        $this->assertEquals('error-1', $errorData['id']);
        $this->assertArrayHasKey('error', $errorData);
    }

    // =============================================================================
    // REAL-WORLD SCENARIO TESTS
    // =============================================================================

    /**
     * Test multiple concurrent requests handling
     */
    #[Test]
    public function it_handles_multiple_concurrent_requests(): void
    {
        $this->initializeServer();

        $responses = [];
        $concurrentRequests = 10;

        // Send multiple requests concurrently (simulated)
        for ($i = 1; $i <= $concurrentRequests; $i++) {
            $responses[$i] = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => "concurrent-{$i}",
            ]);
        }

        // Verify all requests succeeded
        foreach ($responses as $i => $response) {
            $response->assertStatus(200);
            $data = $response->json();
            $this->assertEquals("concurrent-{$i}", $data['id']);
            $this->assertArrayHasKey('result', $data);
        }

        // Verify request isolation - each should have unique ID
        $ids = array_map(fn($response) => $response->json()['id'], $responses);
        $this->assertCount($concurrentRequests, array_unique($ids));
    }

    /**
     * Test error recovery and retry scenarios
     */
    #[Test]
    public function it_handles_error_recovery_and_retry_scenarios(): void
    {
        $this->initializeServer();

        // Test recoverable error followed by success
        $errorResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'error_producer',
                'arguments' => ['error_type' => 'temporary'],
            ],
            'id' => 1,
        ]);

        $errorResponse->assertStatus(200);
        $errorData = $errorResponse->json();
        $this->assertArrayHasKey('error', $errorData);

        // Subsequent request should work (error was temporary)
        $successResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2,
        ]);

        $successResponse->assertStatus(200);
        $successData = $successResponse->json();
        $this->assertArrayHasKey('result', $successData);
    }

    /**
     * Test session lifecycle management
     */
    #[Test]
    public function it_manages_session_lifecycle_correctly(): void
    {
        // Test session without initialization
        $unauthorizedResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]);

        $unauthorizedResponse->assertStatus(200);
        $unauthorizedData = $unauthorizedResponse->json();
        $this->assertArrayHasKey('error', $unauthorizedData);
        $this->assertEquals(-32002, $unauthorizedData['error']['code']); // Server not initialized

        // Initialize server
        $this->initializeServer();

        // Same request should now succeed
        $authorizedResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 2,
        ]);

        $authorizedResponse->assertStatus(200);
        $authorizedData = $authorizedResponse->json();
        $this->assertArrayHasKey('result', $authorizedData);
    }

    /**
     * Test client disconnection handling
     */
    #[Test]
    public function it_handles_client_disconnection_scenarios(): void
    {
        // Initialize and establish session
        $this->initializeServer();

        // Verify session is active
        $pingResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);
        $pingResponse->assertStatus(200);

        // Simulate disconnection by sending malformed request that causes transport error
        $disconnectResponse = $this->call('POST', '/mcp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $disconnectResponse->assertStatus(400);

        // Server should handle disconnection gracefully and be ready for new connections
        $this->initializeServer(); // Re-initialize

        $reconnectResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2,
        ]);
        $reconnectResponse->assertStatus(200);
    }

    // =============================================================================
    // PROTOCOL COMPLIANCE TESTS
    // =============================================================================

    /**
     * Test JSON-RPC 2.0 compliance in real scenarios
     */
    #[Test]
    public function it_maintains_jsonrpc_2_0_compliance_in_real_scenarios(): void
    {
        $testCases = [
            // Valid requests
            ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => null],
            ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 'string-id'],
            ['jsonrpc' => '2.0', 'method' => 'initialized', 'params' => []], // notification

            // Invalid requests
            ['method' => 'ping', 'id' => 1], // missing jsonrpc
            ['jsonrpc' => '1.0', 'method' => 'ping', 'id' => 1], // wrong version
            ['jsonrpc' => '2.0', 'id' => 1], // missing method
            ['jsonrpc' => '2.0', 'method' => '', 'id' => 1], // empty method
        ];

        foreach ($testCases as $index => $request) {
            $response = $this->postJson('/mcp', $request);

            if (isset($request['jsonrpc']) && $request['jsonrpc'] === '2.0' &&
                isset($request['method']) && !empty($request['method'])) {
                // Valid request
                if (isset($request['id'])) {
                    // Request - should have response
                    $response->assertStatus(200);
                    $data = $response->json();
                    $this->assertEquals('2.0', $data['jsonrpc']);
                    $this->assertEquals($request['id'], $data['id']);
                    $this->assertTrue(isset($data['result']) || isset($data['error']));
                } else {
                    // Notification - no response
                    $response->assertStatus(204);
                }
            } else {
                // Invalid request - should return error
                $response->assertStatus(400);
                $data = $response->json();
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertArrayHasKey('error', $data);
                $this->assertEquals(-32600, $data['error']['code']); // Invalid Request
            }
        }
    }

    /**
     * Test MCP 1.0 protocol adherence
     */
    #[Test]
    public function it_adheres_to_mcp_1_0_protocol_specification(): void
    {
        // Test protocol version in initialization
        $initResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => ['name' => 'Test Client'],
            ],
            'id' => 1,
        ]);

        $initResponse->assertStatus(200);
        $initData = $initResponse->json();
        $this->assertEquals('2024-11-05', $initData['result']['protocolVersion']);

        // Test required server info fields
        $serverInfo = $initData['result']['serverInfo'];
        $this->assertArrayHasKey('name', $serverInfo);
        $this->assertArrayHasKey('version', $serverInfo);

        // Test capability structure
        $capabilities = $initData['result']['capabilities'];
        $this->assertIsArray($capabilities);

        // Test MCP-specific method naming
        $this->initializeServer();

        $mcpMethods = [
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
        ];

        foreach ($mcpMethods as $method) {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => $method,
                'id' => $method,
            ]);

            $response->assertStatus(200);
            $data = $response->json();
            $this->assertEquals($method, $data['id']);
            // Should either succeed or fail with proper MCP error codes
            $this->assertTrue(isset($data['result']) || isset($data['error']));
        }
    }

    /**
     * Test proper error handling with correct codes
     */
    #[Test]
    public function it_handles_errors_with_correct_jsonrpc_codes(): void
    {
        $errorScenarios = [
            // Parse errors
            ['request' => 'invalid json', 'expectedCode' => -32700],

            // Invalid Request
            ['request' => ['method' => 'test'], 'expectedCode' => -32600],

            // Method not found
            ['request' => ['jsonrpc' => '2.0', 'method' => 'unknown/method', 'id' => 1], 'expectedCode' => -32601],

            // Invalid params
            ['request' => ['jsonrpc' => '2.0', 'method' => 'tools/call', 'params' => 'invalid', 'id' => 1], 'expectedCode' => -32602],
        ];

        foreach ($errorScenarios as $scenario) {
            if (is_string($scenario['request'])) {
                // Invalid JSON
                $response = $this->call('POST', '/mcp', [], [], [], [
                    'CONTENT_TYPE' => 'application/json',
                ], $scenario['request']);
            } else {
                $response = $this->postJson('/mcp', $scenario['request']);
            }

            // Parse errors return 400, others return 200 with error payload
            if ($scenario['expectedCode'] === -32700) {
                $response->assertStatus(400);
            } else {
                $response->assertStatus(200);
            }

            $data = $response->json();
            $this->assertArrayHasKey('error', $data);
            $this->assertEquals($scenario['expectedCode'], $data['error']['code']);
        }
    }

    // =============================================================================
    // PERFORMANCE AND RELIABILITY TESTS
    // =============================================================================

    /**
     * Test large message handling
     */
    #[Test]
    public function it_handles_large_messages_correctly(): void
    {
        $this->initializeServer();

        // Test large request payload
        $largeData = str_repeat('A', 50000); // 50KB of data
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'complex_calculator',
                'arguments' => [
                    'operation' => 'echo',
                    'data' => $largeData,
                ],
            ],
            'id' => 1,
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);

        // Test reading large resource
        $largeResourceResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [
                'uri' => 'data://large',
            ],
            'id' => 2,
        ]);

        $largeResourceResponse->assertStatus(200);
        $largeResourceData = $largeResourceResponse->json();
        $this->assertArrayHasKey('contents', $largeResourceData['result']);
    }

    /**
     * Test timeout scenarios
     */
    #[Test]
    public function it_handles_timeout_scenarios_gracefully(): void
    {
        $this->initializeServer();

        // Test slow operation (should complete within reasonable time)
        $start = microtime(true);
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'async_processor',
                'arguments' => [
                    'duration' => 0.5, // 500ms
                ],
            ],
            'id' => 1,
        ]);
        $duration = microtime(true) - $start;

        $response->assertStatus(200);
        $this->assertLessThan(2.0, $duration); // Should complete within 2 seconds

        // Verify the operation completed successfully
        $data = $response->json();
        $this->assertArrayHasKey('result', $data);
    }

    /**
     * Test invalid message handling
     */
    #[Test]
    public function it_handles_invalid_messages_robustly(): void
    {
        $invalidMessages = [
            // Malformed JSON
            '{invalid json}',
            '{"incomplete": }',

            // Invalid message structures
            '[]', // empty array
            'null',
            '"just a string"',
            '123',

            // Valid JSON but invalid JSON-RPC
            '{"not": "jsonrpc"}',
            '{"jsonrpc": "2.0"}', // missing method and id
        ];

        foreach ($invalidMessages as $message) {
            $response = $this->call('POST', '/mcp', [], [], [], [
                'CONTENT_TYPE' => 'application/json',
            ], $message);

            // Should handle gracefully, not crash
            $this->assertTrue($response->getStatusCode() >= 200 && $response->getStatusCode() < 500);

            if ($response->getStatusCode() !== 204) {
                $data = $response->json();
                $this->assertEquals('2.0', $data['jsonrpc']);
                $this->assertArrayHasKey('error', $data);
            }
        }
    }

    // =============================================================================
    // HELPER METHODS
    // =============================================================================

    protected function initializeServer(): void
    {
        // Send initialize request
        $initResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => ['listChanged' => false],
                    'resources' => ['subscribe' => false, 'listChanged' => false],
                    'prompts' => ['listChanged' => false],
                ],
                'clientInfo' => ['name' => 'Test Client', 'version' => '1.0.0'],
            ],
            'id' => 'init',
        ]);

        $initResponse->assertStatus(200);
        $initData = $initResponse->json();
        $this->assertArrayHasKey('result', $initData, 'Initialize request should return result');

        // Send initialized notification
        $initializedResponse = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            'params' => [],
        ]);

        // Notifications should return 204 No Content (no response expected)
        $initializedResponse->assertStatus(204);
    }

    protected function registerComplexCalculatorTool(): void
    {
        // Check if already registered to prevent duplicate registration errors
        $registry = app('mcp.registry');
        if ($registry->hasTool('complex_calculator')) {
            return;
        }

        $tool = new class extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name = 'complex_calculator';
            protected string $description = 'Performs complex mathematical operations';
            protected array $parameterSchema = [
                'operation' => ['type' => 'string', 'enum' => ['add', 'multiply', 'echo']],
                'values' => ['type' => 'array', 'items' => ['type' => 'number']],
                'precision' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 10],
                'data' => ['type' => 'string'], // For echo operation
            ];

            protected function handle(array $parameters): mixed
            {
                $operation = $parameters['operation'];
                
                switch ($operation) {
                    case 'add':
                        $result = array_sum($parameters['values'] ?? []);
                        break;
                    case 'multiply':
                        $result = array_product($parameters['values'] ?? [1]);
                        break;
                    case 'echo':
                        $result = $parameters['data'] ?? 'No data provided';
                        break;
                    default:
                        throw new \InvalidArgumentException("Unknown operation: {$operation}");
                }

                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Result: {$result}",
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerTool($tool->getName(), $tool);
    }

    protected function registerAsyncProcessingTool(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasTool('async_processor')) {
            return;
        }

        $tool = new class extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name = 'async_processor';
            protected string $description = 'Simulates async processing';
            protected array $parameterSchema = [
                'duration' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            ];

            protected function handle(array $parameters): mixed
            {
                $duration = $parameters['duration'] ?? 0.1;
                usleep((int) ($duration * 1000000)); // Convert to microseconds
                
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Processed for {$duration} seconds",
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerTool($tool->getName(), $tool);
    }

    protected function registerErrorProducingTool(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasTool('error_producer')) {
            return;
        }

        $tool = new class extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name = 'error_producer';
            protected string $description = 'Produces various types of errors for testing';
            protected array $parameterSchema = [
                'error_type' => ['type' => 'string', 'enum' => ['temporary', 'permanent', 'validation']],
            ];

            protected function handle(array $parameters): mixed
            {
                $errorType = $parameters['error_type'] ?? 'temporary';
                
                switch ($errorType) {
                    case 'temporary':
                        throw new \RuntimeException('Temporary error occurred');
                    case 'permanent':
                        throw new \LogicException('Permanent error in logic');
                    case 'validation':
                        throw new \InvalidArgumentException('Invalid parameters provided');
                    default:
                        throw new \Exception('Unknown error type');
                }
            }
        };

        app('mcp.registry')->registerTool($tool->getName(), $tool);
    }

    protected function registerDynamicConfigResource(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasResource('Dynamic Configuration')) {
            return;
        }

        $resource = new class extends \JTD\LaravelMCP\Abstracts\McpResource
        {
            protected string $uri = 'config://dynamic';
            protected string $name = 'Dynamic Configuration';
            protected string $description = 'Dynamic application configuration';
            protected string $mimeType = 'application/json';

            public function read(array $options = []): array
            {
                $config = [
                    'app' => [
                        'name' => config('app.name', 'Laravel'),
                        'env' => app()->environment(),
                        'debug' => config('app.debug', false),
                    ],
                    'database' => [
                        'default' => config('database.default'),
                    ],
                    'timestamp' => now()->toISOString(),
                ];

                return [
                    'contents' => [
                        [
                            'uri' => $this->uri,
                            'mimeType' => $this->mimeType,
                            'text' => json_encode($config, JSON_PRETTY_PRINT),
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerResource($resource->getName(), $resource);
    }

    protected function registerLargeDataResource(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasResource('Large Dataset')) {
            return;
        }

        $resource = new class extends \JTD\LaravelMCP\Abstracts\McpResource
        {
            protected string $uri = 'data://large';
            protected string $name = 'Large Dataset';
            protected string $description = 'Large dataset for testing';
            protected string $mimeType = 'text/plain';

            public function read(array $options = []): array
            {
                // Generate large content for testing
                $largeContent = str_repeat("Line " . str_repeat('X', 100) . "\n", 1000);

                return [
                    'contents' => [
                        [
                            'uri' => $this->uri,
                            'mimeType' => $this->mimeType,
                            'text' => $largeContent,
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerResource($resource->getName(), $resource);
    }

    protected function registerParameterizedPrompt(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasPrompt('parameterized_assistant')) {
            return;
        }

        $prompt = new class extends \JTD\LaravelMCP\Abstracts\McpPrompt
        {
            protected string $name = 'parameterized_assistant';
            protected string $description = 'AI assistant with configurable parameters';
            protected array $argumentsSchema = [
                'type' => 'object',
                'properties' => [
                    'role' => ['type' => 'string', 'enum' => ['helper', 'code_reviewer', 'analyst']],
                    'context' => ['type' => 'string'],
                    'tone' => ['type' => 'string', 'enum' => ['formal', 'casual', 'professional']],
                ],
                'required' => ['role'],
            ];

            public function getMessages(array $arguments): array
            {
                $role = $arguments['role'];
                $context = $arguments['context'] ?? 'general';
                $tone = $arguments['tone'] ?? 'professional';

                $message = "You are a {$tone} AI {$role} working in the context of {$context}. ";
                $message .= "Please provide helpful and accurate assistance.";

                return [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                'type' => 'text',
                                'text' => $message,
                            ],
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerPrompt($prompt->getName(), $prompt);
    }

    protected function registerComplexPrompt(): void
    {
        $registry = app('mcp.registry');
        if ($registry->hasPrompt('complex_interaction')) {
            return;
        }

        $prompt = new class extends \JTD\LaravelMCP\Abstracts\McpPrompt
        {
            protected string $name = 'complex_interaction';
            protected string $description = 'Complex multi-turn interaction prompt';
            protected array $argumentsSchema = [
                'type' => 'object',
                'properties' => [
                    'scenario' => ['type' => 'string'],
                    'participants' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'objective' => ['type' => 'string'],
                ],
                'required' => ['scenario', 'objective'],
            ];

            public function getMessages(array $arguments): array
            {
                $scenario = $arguments['scenario'];
                $participants = $arguments['participants'] ?? ['user', 'assistant'];
                $objective = $arguments['objective'];

                return [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                'type' => 'text',
                                'text' => "Scenario: {$scenario}\nParticipants: " . implode(', ', $participants) . "\nObjective: {$objective}",
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Please help me achieve the objective in this scenario.',
                            ],
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerPrompt($prompt->getName(), $prompt);
    }
}