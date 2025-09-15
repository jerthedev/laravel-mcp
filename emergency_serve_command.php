#!/usr/bin/env php
<?php

/**
 * Emergency MCP serve command that bypasses Laravel's transport layer
 *
 * This uses the working minimal server logic but within Laravel's context
 * to test if the issue is in Laravel's StdioTransport implementation.
 */

// Bootstrap Laravel
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

error_log('EMERGENCY: Starting emergency MCP server with Laravel context');

// Initialize Laravel without full command bootstrapping
$app->make('config');

// Get MessageProcessor from Laravel container
$messageProcessor = $app->make(\JTD\LaravelMCP\Protocol\MessageProcessor::class);

error_log('EMERGENCY: MessageProcessor created');

// Set server info
$messageProcessor->setServerInfo([
    'name' => 'Emergency Laravel MCP Server',
    'version' => '1.0.0',
]);

// Read from stdin
$input = '';
while (!feof(STDIN)) {
    $line = fgets(STDIN);
    if ($line === false) break;

    $input .= $line;

    // Check if we have a complete JSON message
    if (strpos($input, "\n") !== false) {
        error_log('EMERGENCY: Received input: ' . trim($input));

        // Try to decode JSON
        $request = json_decode(trim($input), true);

        if ($request && isset($request['method'])) {
            error_log('EMERGENCY: Processing method: ' . $request['method']);

            $response = null;

            try {
                // Create a minimal transport mock
                $transport = new class {
                    public function send($data) {
                        error_log('EMERGENCY: Mock transport send: ' . json_encode($data));
                    }
                };

                error_log('EMERGENCY: About to call messageProcessor->handle()');
                $start = microtime(true);

                // Use Laravel's MessageProcessor but bypass transport complexity
                $response = $messageProcessor->handle($request, $transport);

                $duration = microtime(true) - $start;
                error_log('EMERGENCY: messageProcessor->handle() completed in ' . round($duration * 1000) . 'ms');

            } catch (\Throwable $e) {
                error_log('EMERGENCY: Exception in messageProcessor->handle(): ' . $e->getMessage());
                $response = [
                    'jsonrpc' => '2.0',
                    'id' => $request['id'] ?? null,
                    'error' => [
                        'code' => -32603,
                        'message' => 'Internal error: ' . $e->getMessage()
                    ]
                ];
            }

            if ($response) {
                $responseJson = json_encode($response) . "\n";
                error_log('EMERGENCY: Sending response: ' . trim($responseJson));

                // Write response to stdout
                fwrite(STDOUT, $responseJson);
                fflush(STDOUT);

                error_log('EMERGENCY: Response sent and flushed');
            }
        } else {
            error_log('EMERGENCY: Invalid JSON or missing method: ' . $input);
        }

        // Reset input buffer
        $input = '';
    }
}

error_log('EMERGENCY: EOF reached, exiting');