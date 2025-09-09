<?php

return [
    'http' => [
        'driver' => 'http',
        'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
        'port' => env('MCP_HTTP_PORT', 8000),
        'timeout' => env('MCP_HTTP_TIMEOUT', 30),
        'max_connections' => env('MCP_HTTP_MAX_CONNECTIONS', 100),
        'cors' => [
            'enabled' => env('MCP_HTTP_CORS_ENABLED', true),
            'origins' => env('MCP_HTTP_CORS_ORIGINS', '*'),
            'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        ],
        'auth' => [
            'enabled' => env('MCP_HTTP_AUTH_ENABLED', false),
            'type' => env('MCP_HTTP_AUTH_TYPE', 'bearer'),
            'token' => env('MCP_HTTP_AUTH_TOKEN'),
        ],
    ],

    'stdio' => [
        'driver' => 'stdio',
        'timeout' => env('MCP_STDIO_TIMEOUT', 30),
        'buffer_size' => env('MCP_STDIO_BUFFER_SIZE', 8192),
        'encoding' => env('MCP_STDIO_ENCODING', 'utf-8'),
    ],
];
