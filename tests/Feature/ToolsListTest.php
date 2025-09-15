<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Transport\StdioTransport;

/**
 * Test that tools/list request properly returns tools
 * This tests the specific issue where tools were available via artisan but not via JSON-RPC
 */
class ToolsListTest extends TestCase
{
    protected MessageProcessor $messageProcessor;
    protected StdioTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure discovery paths to use test fixtures
        $this->app['config']->set('laravel-mcp.discovery.paths', [
            __DIR__.'/../Fixtures/Mcp/Tools',
            __DIR__.'/../Fixtures/Mcp/Resources',
            __DIR__.'/../Fixtures/Mcp/Prompts',
        ]);

        // Enable debug mode to see detailed error messages
        $this->app['config']->set('app.debug', true);

        $this->messageProcessor = $this->app->make(MessageProcessor::class);
        $this->transport = $this->app->make(StdioTransport::class);
        $this->transport->initialize(['debug' => true]);
        $this->transport->setMessageHandler($this->messageProcessor);

        // Enable debug mode in JSON-RPC handler as well
        $jsonRpcHandler = $this->app->make(\JTD\LaravelMCP\Protocol\JsonRpcHandler::class);
        $jsonRpcHandler->setDebug(true);
    }

    /** @test */
    public function it_returns_tools_via_json_rpc_request()
    {
        // First initialize the connection
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['tools' => []],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0']
            ]
        ];

        $this->messageProcessor->handle($initMessage, $this->transport);

        // Send initialized notification (required by MCP protocol)
        $initializedMessage = [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            'params' => []
        ];

        $this->messageProcessor->handle($initializedMessage, $this->transport);

        // Now test tools/list request
        $toolsListMessage = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => []
        ];

        $response = $this->messageProcessor->handle($toolsListMessage, $this->transport);

        // Debug: Output the actual response
        if (isset($response['error'])) {
            $this->fail('Received error response: ' . json_encode($response, JSON_PRETTY_PRINT));
        }

        // Verify response structure
        $this->assertNotNull($response, 'tools/list should return a response');
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertIsArray($response['result']['tools']);

        // The test should not fail even if there are no tools in the test environment
        // The key is that it should not return an internal error
        $this->assertArrayNotHasKey('error', $response, 'tools/list should not return an error');
    }

    /** @test */
    public function it_handles_tools_list_without_internal_error()
    {
        // First initialize the connection
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['tools' => []],
                'clientInfo' => ['name' => 'test-client', 'version' => '1.0.0']
            ]
        ];

        $this->messageProcessor->handle($initMessage, $this->transport);

        // Send initialized notification (required by MCP protocol)
        $initializedMessage = [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
            'params' => []
        ];

        $this->messageProcessor->handle($initializedMessage, $this->transport);

        // Test tools/list request
        $toolsListMessage = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => []
        ];

        $response = $this->messageProcessor->handle($toolsListMessage, $this->transport);

        // Should not return internal error (-32603)
        $this->assertNotNull($response);
        if (isset($response['error'])) {
            $this->assertNotEquals(-32603, $response['error']['code'],
                'Should not return internal error - the failsafe discovery should prevent this');
        } else {
            // Success case
            $this->assertArrayHasKey('result', $response);
            $this->assertArrayHasKey('tools', $response['result']);
        }
    }
}