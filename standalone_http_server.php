<?php

/**
 * Simple HTTP server endpoint for MCP protocol
 * Use with PHP's built-in server: php -S 127.0.0.1:8001 standalone_http_server.php
 */

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json');
    http_response_code(200);
    exit();
}

// Set CORS headers for all responses
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// Only handle POST requests to /mcp
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $_SERVER['REQUEST_URI'] !== '/mcp') {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
    exit();
}

// Get request body
$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Log the request for debugging
error_log("MCP Request: " . $body);

// Handle MCP initialize request
if (isset($data['method']) && $data['method'] === 'initialize') {
    $response = [
        'result' => [
            'protocolVersion' => $data['params']['protocolVersion'] ?? '2025-06-18',
            'capabilities' => [
                'tools' => (object)[]  // Empty object {}, not array []
            ],
            'serverInfo' => [
                'name' => 'Laravel MCP Server',
                'version' => '1.0.0'
            ]
        ],
        'jsonrpc' => '2.0',
        'id' => $data['id'] ?? 0
    ];

    error_log("MCP Response: " . json_encode($response));
    echo json_encode($response);

    exit();
}

// Handle tools/list request
if (isset($data['method']) && $data['method'] === 'tools/list') {
    $response = [
        'jsonrpc' => '2.0',
        'id' => $data['id'] ?? null,
        'result' => [
            'tools' => []
        ]
    ];

    error_log("MCP Response: " . json_encode($response));
    echo json_encode($response);
    exit();
}

// Handle roots/list request
if (isset($data['method']) && $data['method'] === 'roots/list') {
    $response = [
        'jsonrpc' => '2.0',
        'id' => $data['id'] ?? null,
        'result' => [
            'roots' => []
        ]
    ];

    error_log("MCP Response: " . json_encode($response));
    echo json_encode($response);
    exit();
}

// Handle notifications/initialized - this is key for Claude CLI compatibility
if (isset($data['method']) && $data['method'] === 'notifications/initialized') {
    error_log("MCP: Received notifications/initialized - sending proactive roots/list like Playwright");

    // Send proactive roots/list request like Playwright does
    $rootsListRequest = [
        'method' => 'roots/list',
        'jsonrpc' => '2.0',
        'id' => 0
    ];

    error_log("MCP: Sending proactive roots/list: " . json_encode($rootsListRequest));
    echo json_encode($rootsListRequest);
    exit(0); // Exit like Playwright does
}

// Handle other requests with a generic response
$response = [
    'jsonrpc' => '2.0',
    'id' => $data['id'] ?? null,
    'error' => [
        'code' => -32601,
        'message' => 'Method not found',
        'data' => ['method' => $data['method'] ?? 'unknown']
    ]
];

error_log("MCP Error Response: " . json_encode($response));
echo json_encode($response);