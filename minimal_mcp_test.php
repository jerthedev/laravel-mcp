<?php

/**
 * Minimal standalone MCP server test
 *
 * This bypasses Laravel entirely to isolate whether the hanging issue
 * is in Laravel's service container, discovery system, or the basic
 * stdio communication with Claude Code.
 */

error_log('MINIMAL: Starting minimal MCP server');

// Read from stdin
$input = '';
while (!feof(STDIN)) {
    $line = fgets(STDIN);
    if ($line === false) break;

    $input .= $line;

    // Check if we have a complete JSON message
    if (strpos($input, "\n") !== false) {
        error_log('MINIMAL: Received input: ' . trim($input));

        // Try to decode JSON
        $request = json_decode(trim($input), true);

        if ($request && isset($request['method'])) {
            error_log('MINIMAL: Processing method: ' . $request['method']);

            $response = null;

            switch ($request['method']) {
                case 'initialize':
                    error_log('MINIMAL: Handling initialize');
                    $response = [
                        'jsonrpc' => '2.0',
                        'id' => $request['id'],
                        'result' => [
                            'protocolVersion' => '2025-06-18',
                            'capabilities' => [
                                'tools' => new stdClass() // Empty object, not array
                            ],
                            'serverInfo' => [
                                'name' => 'Minimal Test Server',
                                'version' => '1.0.0'
                            ]
                        ]
                    ];
                    break;

                case 'notifications/initialized':
                    error_log('MINIMAL: Handling notifications/initialized');
                    // No response for notifications
                    $input = '';
                    continue 2;

                case 'tools/list':
                    error_log('MINIMAL: Handling tools/list - THIS IS WHERE IT USUALLY HANGS');
                    $response = [
                        'jsonrpc' => '2.0',
                        'id' => $request['id'],
                        'result' => [
                            'tools' => [
                                [
                                    'name' => 'minimal_test_tool',
                                    'description' => 'A minimal test tool to verify basic functionality',
                                    'inputSchema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'message' => [
                                                'type' => 'string',
                                                'description' => 'Test message'
                                            ]
                                        ],
                                        'required' => ['message']
                                    ]
                                ]
                            ]
                        ]
                    ];
                    break;

                default:
                    error_log('MINIMAL: Unknown method: ' . $request['method']);
                    $response = [
                        'jsonrpc' => '2.0',
                        'id' => $request['id'] ?? null,
                        'error' => [
                            'code' => -32601,
                            'message' => 'Method not found: ' . $request['method']
                        ]
                    ];
                    break;
            }

            if ($response) {
                $responseJson = json_encode($response) . "\n";
                error_log('MINIMAL: Sending response: ' . trim($responseJson));

                // Write response to stdout
                fwrite(STDOUT, $responseJson);
                fflush(STDOUT);

                error_log('MINIMAL: Response sent and flushed');
            }
        } else {
            error_log('MINIMAL: Invalid JSON or missing method: ' . $input);
        }

        // Reset input buffer
        $input = '';
    }
}

error_log('MINIMAL: EOF reached, exiting');