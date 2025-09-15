<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\StdioTransport;

/**
 * Test that simulates Claude Code's exact JSON-RPC message flow via Stdio
 *
 * This test reproduces the hanging issue by following the exact same path
 * that Claude Code takes when communicating via Stdio transport.
 */
class ClaudeCodeStdioTest extends TestCase
{
    private MessageProcessor $messageProcessor;
    private StdioTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        // Use the same setup as the working ToolsListTest
        $this->app['config']->set('laravel-mcp.discovery.paths', [
            __DIR__.'/../Fixtures/Mcp/Tools',
            __DIR__.'/../Fixtures/Mcp/Resources',
            __DIR__.'/../Fixtures/Mcp/Prompts',
        ]);

        $this->app['config']->set('app.debug', true);

        $this->messageProcessor = $this->app->make(MessageProcessor::class);
        $this->transport = $this->app->make(StdioTransport::class);
        $this->transport->initialize(['debug' => true]);
        $this->transport->setMessageHandler($this->messageProcessor);

        $jsonRpcHandler = $this->app->make(\JTD\LaravelMCP\Protocol\JsonRpcHandler::class);
        $jsonRpcHandler->setDebug(true);
    }

    /** @test */
    public function it_processes_claude_code_initialization_sequence()
    {
        error_log('TEST: Starting Claude Code initialization sequence simulation');

        // 1. Initialize request (like Claude Code sends)
        $initRequest = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2025-06-18',
                'capabilities' => [
                    'tools' => []
                ],
                'clientInfo' => [
                    'name' => 'claude-code-test',
                    'version' => '1.0.0'
                ]
            ]
        ];

        error_log('TEST: Sending initialize request');
        $initResponse = $this->messageProcessor->handle($initRequest, $this->transport);
        error_log('TEST: Initialize response received: ' . json_encode($initResponse));

        $this->assertNotNull($initResponse);
        $this->assertArrayNotHasKey('error', $initResponse);

        // 2. Initialized notification (like Claude Code sends)
        $initNotification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
            'params' => []
        ];

        error_log('TEST: Sending notifications/initialized');
        $this->messageProcessor->handle($initNotification, $this->transport);
        error_log('TEST: Notification processed');

        // 3. tools/list request (this is where the hang occurs with Claude Code)
        $toolsListRequest = [
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
            'params' => []
        ];

        error_log('TEST: About to send tools/list request - this might hang');

        // Set a reasonable timeout to catch hangs
        $start = microtime(true);
        $toolsResponse = $this->messageProcessor->handle($toolsListRequest, $this->transport);
        $duration = microtime(true) - $start;

        error_log('TEST: tools/list completed in ' . round($duration * 1000) . 'ms');
        error_log('TEST: tools/list response: ' . json_encode($toolsResponse));

        $this->assertNotNull($toolsResponse);
        $this->assertArrayNotHasKey('error', $toolsResponse);
        $this->assertArrayHasKey('result', $toolsResponse);
        $this->assertArrayHasKey('tools', $toolsResponse['result']);

        // Should complete quickly, not hang for 60 seconds
        $this->assertLessThan(5.0, $duration, 'tools/list should complete quickly, not hang');
    }
}