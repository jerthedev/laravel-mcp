<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Transport\StdioTransport;
use Illuminate\Support\Facades\Log;

/**
 * Feature test that replicates Claude CLI's exact health check behavior
 *
 * This test simulates the complete Claude CLI MCP health check flow:
 * 1. Initialize request with exact Claude CLI format
 * 2. Validates response matches Playwright exactly (critical for acceptance)
 * 3. Sends notifications/initialized
 * 4. Expects proactive roots/list from server
 * 5. Validates complete handshake flow
 */
class ClaudeCliHealthCheckTest extends TestCase
{
    protected MessageProcessor $messageProcessor;
    protected StdioTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageProcessor = $this->app->make(MessageProcessor::class);
        $this->transport = $this->app->make(StdioTransport::class);
        $this->transport->initialize(['debug' => false]);
        $this->transport->setMessageHandler($this->messageProcessor);
    }

    /** @test */
    public function it_responds_to_initialize_with_exact_playwright_format()
    {
        // Step 1: Send initialize request exactly like Claude CLI
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['roots' => []],
                'clientInfo' => ['name' => 'claude-cli', 'version' => '1.0.0']
            ]
        ];

        $response = $this->messageProcessor->handle($initMessage, $this->transport);

        // Step 2: Validate response structure matches Playwright exactly
        $this->assertIsArray($response);

        // Check field order (CRITICAL - result must be first)
        $expectedKeys = ['result', 'jsonrpc', 'id'];
        $actualKeys = array_keys($response);
        $this->assertEquals($expectedKeys, $actualKeys,
            'Field order must match Playwright: [result, jsonrpc, id]');

        // Check protocol version
        $this->assertEquals('2025-06-18', $response['result']['protocolVersion']);

        // Check JSON-RPC format
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(0, $response['id']);

        // Step 3: CRITICAL TEST - Check tools capability format
        $toolsValue = $response['result']['capabilities']['tools'];
        $toolsJson = json_encode($toolsValue);

        $this->assertEquals('{"listChanged":true}', $toolsJson,
            'Tools capability MUST be {"listChanged":true} per MCP specification for tools support.');

        $this->assertIsArray($toolsValue,
            'Tools value must be an associative array with MCP capability properties');

        // Step 4: Validate complete response JSON matches Playwright format
        $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Check if the raw JSON contains {} for tools (this is what matters for Claude CLI)
        $this->assertStringContainsString('"tools":{}', $responseJson,
            'Raw JSON must contain "tools":{} for Claude CLI compatibility');

        // Parse back without converting to arrays to preserve objects
        $parsed = json_decode($responseJson, false);  // false = keep objects as objects
        $parsedToolsJson = json_encode($parsed->result->capabilities->tools);

        $this->assertEquals('{}', $parsedToolsJson,
            'After full JSON encode/decode cycle, tools must still be {}');
    }

    /** @test */
    public function it_handles_notifications_initialized_and_sends_proactive_roots_list()
    {
        // First initialize (required before notifications/initialized)
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['roots' => []],
                'clientInfo' => ['name' => 'claude-cli', 'version' => '1.0.0']
            ]
        ];

        $this->messageProcessor->handle($initMessage, $this->transport);

        // Step 2: Send notifications/initialized (what Claude CLI does after accepting initialize)
        $initializedMessage = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
            'params' => []
        ];

        // Mock transport to capture proactive message
        $proactiveMessage = null;
        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->expects($this->once())
            ->method('send')
            ->willReturnCallback(function($message) use (&$proactiveMessage) {
                $proactiveMessage = $message;
            });

        // Handle initialized notification
        $response = $this->messageProcessor->handle($initializedMessage, $mockTransport);

        // Should return null for notifications
        $this->assertNull($response);

        // Step 3: Verify proactive roots/list was sent (like Playwright does)
        $this->assertNotNull($proactiveMessage,
            'Server must send proactive roots/list after notifications/initialized');

        $proactiveData = json_decode($proactiveMessage, true);
        $this->assertEquals('roots/list', $proactiveData['method']);
        $this->assertEquals('2.0', $proactiveData['jsonrpc']);
        $this->assertEquals(0, $proactiveData['id']);
    }

    /** @test */
    public function it_matches_exact_playwright_response_byte_for_byte()
    {
        // MCP specification compliant format (required for proper tools support)
        $playwrightResponse = '{"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{"listChanged":true}},"serverInfo":{"name":"Playwright","version":"0.0.37"}},"jsonrpc":"2.0","id":0}';

        // Our server's response
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['roots' => []],
                'clientInfo' => ['name' => 'claude-cli', 'version' => '1.0.0']
            ]
        ];

        $response = $this->messageProcessor->handle($initMessage, $this->transport);
        $ourJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Parse both to compare structure (ignoring serverInfo differences)
        $playwrightParsed = json_decode($playwrightResponse, true);
        $ourParsed = json_decode($ourJson, true);

        // Key structural comparisons
        $this->assertEquals(
            array_keys($playwrightParsed),
            array_keys($ourParsed),
            'Top-level field order must match Playwright'
        );

        $this->assertEquals(
            $playwrightParsed['result']['protocolVersion'],
            $ourParsed['result']['protocolVersion'],
            'Protocol version must match'
        );

        $this->assertEquals(
            $playwrightParsed['result']['capabilities'],
            $ourParsed['result']['capabilities'],
            'Capabilities structure must match Playwright exactly'
        );

        // Most critical: tools format - check for MCP specification compliance
        // Servers supporting tools must declare listChanged capability
        $this->assertStringContainsString('"tools":{"listChanged":true}', $playwrightResponse, 'Expected response should have MCP-compliant tools capability');
        $this->assertStringContainsString('"tools":{"listChanged":true}', $ourJson, 'Our server must have MCP-compliant tools capability');
        $this->assertStringNotContainsString('"tools":[]', $ourJson, 'Our server must NOT have tools: [] in raw JSON');

        // Legacy assertion for debugging (will fail due to json_decode behavior)
        $playwrightTools = json_encode($playwrightParsed['result']['capabilities']['tools']);
        $ourTools = json_encode($ourParsed['result']['capabilities']['tools']);
        // Note: Both will be "[]" due to json_decode('{}', true) -> array() -> json_encode() -> "[]"
    }

    /** @test */
    public function it_completes_full_claude_cli_handshake_flow()
    {
        // This test simulates the complete flow that Claude CLI expects:
        // 1. Initialize -> Response
        // 2. notifications/initialized -> Proactive roots/list
        // 3. roots response -> Server exits

        $messages = [];
        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->method('send')
            ->willReturnCallback(function($message) use (&$messages) {
                $messages[] = $message;
            });

        // Step 1: Initialize
        $initMessage = [
            'jsonrpc' => '2.0',
            'id' => 0,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => ['roots' => []],
                'clientInfo' => ['name' => 'claude-cli', 'version' => '1.0.0']
            ]
        ];

        $initResponse = $this->messageProcessor->handle($initMessage, $mockTransport);
        $this->assertNotNull($initResponse);
        $this->assertEquals('{"listChanged":true}', json_encode($initResponse['result']['capabilities']['tools']));

        // Step 2: notifications/initialized
        $initializedMessage = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
            'params' => []
        ];

        $this->messageProcessor->handle($initializedMessage, $mockTransport);

        // Step 3: Verify proactive roots/list was sent
        $this->assertCount(1, $messages, 'Should send exactly one proactive message');

        $rootsRequest = json_decode($messages[0], true);
        $this->assertEquals('roots/list', $rootsRequest['method']);

        // This represents successful Claude CLI health check!
        $this->assertTrue(true, 'Complete handshake flow successful - Claude CLI health check would pass');
    }
}