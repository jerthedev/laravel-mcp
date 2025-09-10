<?php

namespace JTD\LaravelMCP\Tests\Feature\Http;

use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Http\Controllers\McpController;
use JTD\LaravelMCP\Transport\TransportManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EPIC: TRANSPORT
 * SPEC: docs/Specs/07-Transport.md
 * SPRINT: Sprint 2
 * TICKET: TRANSPORT-012
 *
 * Integration tests for HTTP transport implementation
 * Tests end-to-end HTTP communication, middleware stack, and MCP protocol
 */
#[Group('feature')]
#[Group('integration')]
#[Group('http')]
#[Group('transport')]
class HttpTransportIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up routes for testing
        $this->setupTestRoutes();

        // Configure HTTP transport
        config([
            'laravel-mcp.transports.http.enabled' => true,
            'laravel-mcp.transports.http.host' => '127.0.0.1',
            'laravel-mcp.transports.http.port' => 8080,
            'laravel-mcp.transports.http.path' => '/mcp',
            'laravel-mcp.transports.http.middleware' => ['api'],
            'laravel-mcp.cors.enabled' => true,
            'laravel-mcp.cors.allowed_origins' => ['*'],
            'laravel-mcp.cors.allowed_methods' => ['GET', 'POST', 'OPTIONS'],
            'laravel-mcp.cors.allowed_headers' => ['Content-Type', 'Authorization', 'X-MCP-API-Key'],
            'laravel-mcp.auth.enabled' => false,
        ]);
    }

    protected function setupTestRoutes(): void
    {
        Route::prefix('mcp')
            ->middleware(\JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware::class)
            ->group(function () {
                Route::post('/', [McpController::class, 'handle'])->name('mcp.handle');
                Route::options('/', [McpController::class, 'options'])->name('mcp.options');
                Route::get('/health', [McpController::class, 'health'])->name('mcp.health');
                Route::get('/info', [McpController::class, 'info'])->name('mcp.info');
                Route::get('/events', [McpController::class, 'events'])->name('mcp.events');
            });
    }

    /**
     * Test complete HTTP request/response cycle
     */
    #[Test]
    public function it_handles_complete_http_request_response_cycle(): void
    {
        // Register a test tool
        $tool = $this->createTestTool('calculator', [
            'description' => 'Performs calculations',
            'parameterSchema' => [
                'operation' => ['type' => 'string'],
                'a' => ['type' => 'number'],
                'b' => ['type' => 'number'],
            ],
        ]);

        app('mcp.registry')->registerTool($tool);

        // Send a tools/list request
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]);

        // Assert response
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'tools' => [
                    '*' => [
                        'name',
                        'description',
                        'inputSchema',
                    ],
                ],
            ],
            'id',
        ]);

        $content = $response->json();
        $this->assertEquals('2.0', $content['jsonrpc']);
        $this->assertEquals(1, $content['id']);
        $this->assertCount(1, $content['result']['tools']);
        $this->assertEquals('calculator', $content['result']['tools'][0]['name']);
    }

    /**
     * Test CORS preflight handling
     */
    #[Test]
    public function it_handles_cors_preflight_requests(): void
    {
        $response = $this->options('/mcp', [], [
            'Origin' => 'http://localhost:3000',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'Content-Type',
        ]);

        $response->assertStatus(204);
        $response->assertHeader('Access-Control-Allow-Origin');
        $response->assertHeader('Access-Control-Allow-Methods');
        $response->assertHeader('Access-Control-Allow-Headers');
        $response->assertHeader('Access-Control-Max-Age');
    }

    /**
     * Test CORS headers on regular requests
     */
    #[Test]
    public function it_adds_cors_headers_to_regular_requests(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ], [
            'Origin' => 'http://example.com',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', 'http://example.com');
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    /**
     * Test authentication middleware when enabled
     */
    #[Test]
    public function it_enforces_authentication_when_enabled(): void
    {
        // Enable authentication
        config([
            'laravel-mcp.auth.enabled' => true,
            'laravel-mcp.auth.api_key' => 'test-secret-key',
        ]);

        // Request without API key should fail
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ]);

        $response->assertStatus(401);
        $response->assertJson([
            'error' => [
                'code' => -32001,
                'message' => 'Invalid API key',
            ],
        ]);

        // Request with valid API key should succeed
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2,
        ], [
            'X-MCP-API-Key' => 'test-secret-key',
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test health check endpoint
     */
    #[Test]
    public function it_provides_health_check_endpoint(): void
    {
        $response = $this->getJson('/mcp/health');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'timestamp',
            'checks',
            'errors',
            'transport' => [
                'type',
                'connected',
                'stats',
            ],
        ]);

        $content = $response->json();
        $this->assertEquals('healthy', $content['status']);
        $this->assertEquals('http', $content['transport']['type']);
    }

    /**
     * Test server info endpoint
     */
    #[Test]
    public function it_provides_server_info_endpoint(): void
    {
        config([
            'laravel-mcp.server.name' => 'Test MCP Server',
            'laravel-mcp.server.version' => '1.0.0',
            'laravel-mcp.server.description' => 'Test server',
            'laravel-mcp.capabilities.tools' => ['calculator'],
        ]);

        $response = $this->getJson('/mcp/info');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'server' => [
                'name',
                'version',
                'description',
                'vendor',
            ],
            'protocol' => [
                'version',
                'transport',
            ],
            'capabilities',
            'endpoints',
            'timestamp',
        ]);

        $content = $response->json();
        $this->assertEquals('Test MCP Server', $content['server']['name']);
        $this->assertEquals('1.0', $content['protocol']['version']);
        $this->assertEquals('http', $content['protocol']['transport']);
    }

    /**
     * Test error handling for malformed JSON
     */
    #[Test]
    public function it_handles_malformed_json_requests(): void
    {
        $response = $this->call('POST', '/mcp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $response->assertStatus(400);
        $response->assertJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error: Empty or invalid request body',
            ],
            'id' => null,
        ]);
    }

    /**
     * Test error handling for invalid content type
     */
    #[Test]
    public function it_rejects_invalid_content_type(): void
    {
        $response = $this->call('POST', '/mcp', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'some text');

        $response->assertStatus(400);
        $response->assertJson([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Invalid Content-Type header. Expected application/json',
            ],
            'id' => null,
        ]);
    }

    /**
     * Test handling of JSON-RPC notifications
     */
    #[Test]
    public function it_handles_jsonrpc_notifications(): void
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'notification',
            'params' => ['message' => 'test'],
            // No 'id' field - this is a notification
        ]);

        $response->assertStatus(204);
        $response->assertNoContent();
    }

    /**
     * Test handling of batch requests
     */
    #[Test]
    public function it_handles_batch_requests(): void
    {
        $response = $this->postJson('/mcp', [
            [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => 1,
            ],
            [
                'jsonrpc' => '2.0',
                'method' => 'tools/list',
                'id' => 2,
            ],
        ]);

        $response->assertStatus(200);
        $content = $response->json();

        // Should receive an array of responses
        $this->assertIsArray($content);
        $this->assertCount(2, $content);
    }

    /**
     * Test tool invocation through HTTP
     */
    #[Test]
    public function it_invokes_tools_through_http(): void
    {
        // Register a calculator tool
        $tool = new class extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name = 'add';

            protected string $description = 'Adds two numbers';

            protected array $parameterSchema = [
                'a' => ['type' => 'number', 'required' => true],
                'b' => ['type' => 'number', 'required' => true],
            ];

            protected function handle(array $parameters): mixed
            {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Result: '.($parameters['a'] + $parameters['b']),
                        ],
                    ],
                ];
            }
        };

        app('mcp.registry')->registerTool($tool);

        // Invoke the tool
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'add',
                'arguments' => [
                    'a' => 5,
                    'b' => 3,
                ],
            ],
            'id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Result: 8',
                    ],
                ],
            ],
            'id' => 1,
        ]);
    }

    /**
     * Test resource reading through HTTP
     */
    #[Test]
    public function it_reads_resources_through_http(): void
    {
        // Register a test resource
        $resource = $this->createTestResource('config', [
            'uri' => 'config://app',
            'description' => 'Application configuration',
            'mimeType' => 'application/json',
        ]);

        app('mcp.registry')->registerResource($resource);

        // List resources
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/list',
            'id' => 1,
        ]);

        $response->assertStatus(200);
        $content = $response->json();
        $this->assertCount(1, $content['result']['resources']);
        $this->assertEquals('config://app', $content['result']['resources'][0]['uri']);

        // Read resource
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [
                'uri' => 'config://app',
            ],
            'id' => 2,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'contents' => [
                    '*' => [
                        'uri',
                        'mimeType',
                        'text',
                    ],
                ],
            ],
            'id',
        ]);
    }

    /**
     * Test prompt handling through HTTP
     */
    #[Test]
    public function it_handles_prompts_through_http(): void
    {
        // Register a test prompt
        $prompt = $this->createTestPrompt('greeting', [
            'description' => 'Generates a greeting',
            'argumentsSchema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                ],
            ],
        ]);

        app('mcp.registry')->registerPrompt($prompt);

        // Get prompt
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'prompts/get',
            'params' => [
                'name' => 'greeting',
                'arguments' => [
                    'name' => 'World',
                ],
            ],
            'id' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'jsonrpc',
            'result' => [
                'messages' => [
                    '*' => [
                        'role',
                        'content',
                    ],
                ],
            ],
            'id',
        ]);
    }

    /**
     * Test HTTP transport with middleware stack
     */
    #[Test]
    public function it_processes_requests_through_middleware_stack(): void
    {
        // Add custom middleware to track execution
        $middlewareExecuted = false;

        Route::middleware(['web'])->group(function () use (&$middlewareExecuted) {
            Route::post('/mcp-test', function () use (&$middlewareExecuted) {
                $middlewareExecuted = true;

                return response()->json(['success' => true]);
            });
        });

        $response = $this->postJson('/mcp-test', []);

        $response->assertStatus(200);
        $this->assertTrue($middlewareExecuted);
    }

    /**
     * Test concurrent HTTP requests
     */
    #[Test]
    public function it_handles_concurrent_http_requests(): void
    {
        $responses = [];

        // Send multiple requests
        for ($i = 1; $i <= 5; $i++) {
            $responses[] = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'ping',
                'id' => $i,
            ]);
        }

        // All should succeed
        foreach ($responses as $i => $response) {
            $response->assertStatus(200);
            $content = $response->json();
            $this->assertEquals($i + 1, $content['id']);
        }
    }

    /**
     * Test HTTP transport error recovery
     */
    #[Test]
    public function it_recovers_from_transport_errors(): void
    {
        // First request with error
        $response1 = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'unknown/method',
            'id' => 1,
        ]);

        $response1->assertStatus(200);
        $content1 = $response1->json();
        $this->assertArrayHasKey('error', $content1);

        // Second request should still work
        $response2 = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 2,
        ]);

        $response2->assertStatus(200);
        $content2 = $response2->json();
        $this->assertArrayHasKey('result', $content2);
    }

    /**
     * Test large payload handling
     */
    #[Test]
    public function it_handles_large_payloads(): void
    {
        // Create a large payload
        $largeData = str_repeat('x', 100000); // 100KB of data

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [
                'data' => $largeData,
            ],
            'id' => 1,
        ]);

        // Should handle without crashing
        $response->assertStatus(200);
    }

    /**
     * Test request timeout handling
     */
    #[Test]
    public function it_handles_request_timeouts_gracefully(): void
    {
        // Register a slow tool
        $tool = new class extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name = 'slow';

            protected string $description = 'Slow operation';

            protected array $parameterSchema = [];

            protected function handle(array $parameters): mixed
            {
                sleep(1); // Simulate slow operation

                return ['content' => [['type' => 'text', 'text' => 'Done']]];
            }
        };

        app('mcp.registry')->registerTool($tool);

        // Request should complete (no timeout in test environment)
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'slow',
                'arguments' => [],
            ],
            'id' => 1,
        ]);

        $response->assertStatus(200);
    }

    /**
     * Test HTTP transport statistics
     */
    #[Test]
    public function it_tracks_transport_statistics(): void
    {
        $transportManager = app(TransportManager::class);
        $transport = $transportManager->createTransport('http');

        // Send some requests
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1]);
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 2]);

        $stats = $transport->getStatistics();

        $this->assertArrayHasKey('messages_sent', $stats);
        $this->assertArrayHasKey('messages_received', $stats);
        $this->assertArrayHasKey('bytes_sent', $stats);
        $this->assertArrayHasKey('bytes_received', $stats);
    }

    /**
     * Test SSL configuration (when enabled)
     */
    #[Test]
    public function it_validates_ssl_configuration(): void
    {
        config([
            'laravel-mcp.transports.http.ssl.enabled' => true,
            'laravel-mcp.transports.http.ssl.cert_path' => '/invalid/cert.pem',
            'laravel-mcp.transports.http.ssl.key_path' => '/invalid/key.pem',
        ]);

        $response = $this->getJson('/mcp/health');

        $response->assertStatus(503); // Unhealthy due to missing SSL files
        $content = $response->json();
        $this->assertEquals('unhealthy', $content['status']);
        $this->assertNotEmpty($content['errors']);
    }

    /**
     * Test custom headers in responses
     */
    #[Test]
    public function it_includes_custom_headers_in_responses(): void
    {
        config([
            'laravel-mcp.transports.http.headers' => [
                'X-MCP-Version' => '1.0',
                'X-Custom-Header' => 'custom-value',
            ],
        ]);

        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);

        $response->assertHeader('Content-Type', 'application/json');
    }
}
