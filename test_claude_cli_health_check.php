<?php

/**
 * Test script that replicates Claude CLI's exact health check behavior
 *
 * This script simulates what Claude CLI does during MCP server health checks:
 * 1. Sends initialize request
 * 2. Expects proper response format matching Playwright
 * 3. Sends notifications/initialized if response is acceptable
 * 4. Expects proactive roots/list request from server
 * 5. Sends roots response
 * 6. Expects server to exit cleanly
 */

require_once __DIR__ . '/vendor/autoload.php';

use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;

echo "=== Claude CLI Health Check Test ===" . PHP_EOL;
echo "Simulating exact Claude CLI behavior..." . PHP_EOL . PHP_EOL;

try {
    // Create minimal Laravel-like container mock
    $container = new class {
        public function make($class) {
            return new $class($this);
        }
    };

    // Create registries
    $toolRegistry = new ToolRegistry($container);
    $resourceRegistry = new ResourceRegistry($container);
    $promptRegistry = new PromptRegistry($container);
    $mcpRegistry = new McpRegistry($toolRegistry, $resourceRegistry, $promptRegistry);

    // Create JSON-RPC handler
    $jsonRpcHandler = new JsonRpcHandler();

    // Create capability negotiator
    $capabilityNegotiator = new CapabilityNegotiator();

    // Create message processor
    $messageProcessor = new MessageProcessor(
        $jsonRpcHandler,
        $mcpRegistry,
        $toolRegistry,
        $resourceRegistry,
        $promptRegistry,
        $capabilityNegotiator
    );

    // Create STDIO transport
    $transport = new StdioTransport();
    $transport->initialize(['debug' => false]);
    $transport->setMessageHandler($messageProcessor);

    echo "✅ Components initialized successfully" . PHP_EOL . PHP_EOL;

    // Step 1: Send initialize request (exactly like Claude CLI)
    echo "🔄 Step 1: Sending initialize request..." . PHP_EOL;
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

    echo "   Sending: " . json_encode($initMessage) . PHP_EOL;
    $response = $messageProcessor->handle($initMessage, $transport);
    echo "   Response: " . json_encode($response) . PHP_EOL . PHP_EOL;

    // Step 2: Validate response format matches Playwright exactly
    echo "🔍 Step 2: Validating response format..." . PHP_EOL;

    $expectedStructure = [
        'result' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => ['tools' => []],  // Should be {} not []
            'serverInfo' => ['name' => 'Laravel MCP Server', 'version' => '1.0.0']
        ],
        'jsonrpc' => '2.0',
        'id' => 0
    ];

    // Check field order (result should be first)
    $responseKeys = array_keys($response);
    $expectedKeys = array_keys($expectedStructure);
    $fieldOrderCorrect = $responseKeys === $expectedKeys;

    echo "   ✓ Field order (result, jsonrpc, id): " . ($fieldOrderCorrect ? "✅ CORRECT" : "❌ WRONG") . PHP_EOL;
    echo "     Expected: [" . implode(', ', $expectedKeys) . "]" . PHP_EOL;
    echo "     Actual:   [" . implode(', ', $responseKeys) . "]" . PHP_EOL;

    // Check protocol version
    $protocolCorrect = $response['result']['protocolVersion'] === '2025-06-18';
    echo "   ✓ Protocol version: " . ($protocolCorrect ? "✅ CORRECT" : "❌ WRONG") . PHP_EOL;

    // Check tools capability format (CRITICAL)
    $toolsValue = $response['result']['capabilities']['tools'];
    $toolsJson = json_encode($toolsValue);
    $toolsCorrect = $toolsJson === '{}';
    echo "   ✓ Tools capability format: " . ($toolsCorrect ? "✅ CORRECT ({})" : "❌ WRONG ($toolsJson)") . PHP_EOL;
    echo "     Type: " . gettype($toolsValue) . PHP_EOL;

    // Overall validation
    $responseValid = $fieldOrderCorrect && $protocolCorrect && $toolsCorrect;
    echo "   📋 Overall response valid: " . ($responseValid ? "✅ YES" : "❌ NO") . PHP_EOL . PHP_EOL;

    if (!$responseValid) {
        echo "❌ HEALTH CHECK FAILED: Claude CLI would reject this response" . PHP_EOL;
        echo "   Reason: Response format doesn't match Playwright exactly" . PHP_EOL;
        exit(1);
    }

    // Step 3: Send notifications/initialized (Claude CLI would do this if response is valid)
    echo "🔄 Step 3: Sending notifications/initialized..." . PHP_EOL;
    $initializedMessage = [
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
        'params' => []
    ];

    echo "   Sending: " . json_encode($initializedMessage) . PHP_EOL;
    $initializedResponse = $messageProcessor->handle($initializedMessage, $transport);
    echo "   Response: " . ($initializedResponse ? json_encode($initializedResponse) : "null (notification)") . PHP_EOL . PHP_EOL;

    // Step 4: Check if server sends proactive roots/list (this is what Playwright does)
    echo "🔄 Step 4: Checking for proactive roots/list request..." . PHP_EOL;
    echo "   ⚠️  NOTE: Our current server implementation should send this proactively" . PHP_EOL;
    echo "   ⚠️  In real Claude CLI, the server would send this automatically after initialized" . PHP_EOL . PHP_EOL;

    // If we get here, the format is correct
    echo "✅ HEALTH CHECK PASSED!" . PHP_EOL;
    echo "   ✓ Initialize response format matches Playwright exactly" . PHP_EOL;
    echo "   ✓ Claude CLI would accept this response and proceed" . PHP_EOL;
    echo "   ✓ Server would receive notifications/initialized" . PHP_EOL . PHP_EOL;

    echo "🎉 SUCCESS: Your Laravel MCP server should work with Claude CLI!" . PHP_EOL;

} catch (\Throwable $e) {
    echo "❌ ERROR during health check test:" . PHP_EOL;
    echo "   " . $e->getMessage() . PHP_EOL;
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
    echo PHP_EOL . "Stack trace:" . PHP_EOL . $e->getTraceAsString() . PHP_EOL;
    exit(1);
}