<?php

return [
    'enabled' => env('MCP_ENABLED', true),

    'transports' => [
        'default' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),
        'http' => [
            'enabled' => env('MCP_HTTP_ENABLED', true),
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
            'port' => env('MCP_HTTP_PORT', 8000),
            'middleware' => ['mcp.cors', 'mcp.auth'],
        ],
        'stdio' => [
            'enabled' => env('MCP_STDIO_ENABLED', true),
            'timeout' => env('MCP_STDIO_TIMEOUT', 30),
        ],
    ],

    'discovery' => [
        'enabled' => env('MCP_AUTO_DISCOVERY', true),
        'paths' => [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ],
    ],

    'routes' => [
        'prefix' => env('MCP_ROUTES_PREFIX', 'mcp'),
        'middleware' => ['api'],
    ],

    'middleware' => [
        'auto_register' => true,
    ],

    'cache' => [
        'store' => env('MCP_CACHE_STORE', 'file'),
        'ttl' => env('MCP_CACHE_TTL', 3600),
    ],
];