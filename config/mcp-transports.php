<?php

return [
    'default' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),
    'auto_start' => env('MCP_AUTO_START_TRANSPORTS', []),

    // Connection pooling configuration
    'connection_pooling' => [
        'enabled' => env('MCP_CONNECTION_POOLING_ENABLED', true),
        'max_connections_per_type' => env('MCP_POOL_MAX_CONNECTIONS', 10),
        'connection_timeout' => env('MCP_POOL_CONNECTION_TIMEOUT', 300),
        'idle_timeout' => env('MCP_POOL_IDLE_TIMEOUT', 600),
        'health_check_interval' => env('MCP_POOL_HEALTH_CHECK_INTERVAL', 120),
        'eviction_policy' => env('MCP_POOL_EVICTION_POLICY', 'lru'), // lru, fifo, lifo
        'debug' => env('MCP_POOL_DEBUG', false),
    ],

    'transports' => [
        'http' => [
            'driver' => 'http',
            'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
            'port' => env('MCP_HTTP_PORT', 8000),
            'path' => env('MCP_HTTP_PATH', '/mcp'),
            'timeout' => env('MCP_HTTP_TIMEOUT', 30),
            'max_connections' => env('MCP_HTTP_MAX_CONNECTIONS', 100),
            'middleware' => [
                'mcp.cors',
                'throttle:60,1',
            ],
            'cors' => [
                'enabled' => env('MCP_HTTP_CORS_ENABLED', true),
                'allowed_origins' => explode(',', env('MCP_HTTP_CORS_ORIGINS', '*')),
                'allowed_methods' => ['POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization'],
                'max_age' => env('MCP_HTTP_CORS_MAX_AGE', 86400),
            ],
            'ssl' => [
                'enabled' => env('MCP_HTTP_SSL_ENABLED', false),
                'cert_path' => env('MCP_HTTP_SSL_CERT'),
                'key_path' => env('MCP_HTTP_SSL_KEY'),
            ],
            'auth' => [
                'enabled' => env('MCP_HTTP_AUTH_ENABLED', false),
                'type' => env('MCP_HTTP_AUTH_TYPE', 'bearer'),
                'token' => env('MCP_HTTP_AUTH_TOKEN'),
            ],
            // Message batching configuration
            'batching' => [
                'enabled' => env('MCP_HTTP_BATCHING_ENABLED', false),
                'batch_size' => env('MCP_HTTP_BATCH_SIZE', 10),
                'batch_timeout' => env('MCP_HTTP_BATCH_TIMEOUT', 100),
            ],
            // Resilience configuration
            'resilience' => [
                'max_retry_attempts' => env('MCP_HTTP_MAX_RETRY_ATTEMPTS', 3),
                'base_retry_delay' => env('MCP_HTTP_BASE_RETRY_DELAY', 1000),
                'max_retry_delay' => env('MCP_HTTP_MAX_RETRY_DELAY', 30000),
                'circuit_breaker_threshold' => env('MCP_HTTP_CIRCUIT_BREAKER_THRESHOLD', 5),
                'circuit_breaker_timeout' => env('MCP_HTTP_CIRCUIT_BREAKER_TIMEOUT', 60),
                'max_reconnection_attempts' => env('MCP_HTTP_MAX_RECONNECTION_ATTEMPTS', 5),
            ],
            // Connection management
            'connection_management' => [
                'enabled' => env('MCP_HTTP_CONNECTION_MANAGEMENT_ENABLED', true),
                'health_check_interval' => env('MCP_HTTP_HEALTH_CHECK_INTERVAL', 30),
                'connection_timeout' => env('MCP_HTTP_CONNECTION_TIMEOUT', 60),
            ],
        ],

        'stdio' => [
            'driver' => 'stdio',
            'timeout' => env('MCP_STDIO_TIMEOUT', 30),
            'buffer_size' => env('MCP_STDIO_BUFFER_SIZE', 8192),
            'max_message_size' => env('MCP_STDIO_MAX_MESSAGE_SIZE', 1048576),
            'encoding' => env('MCP_STDIO_ENCODING', 'utf-8'),
            'use_content_length' => env('MCP_STDIO_USE_CONTENT_LENGTH', false),
            'blocking_mode' => env('MCP_STDIO_BLOCKING_MODE', false),
            'read_timeout' => env('MCP_STDIO_READ_TIMEOUT', 0.1),
            'write_timeout' => env('MCP_STDIO_WRITE_TIMEOUT', 5),
            'enable_keepalive' => env('MCP_STDIO_ENABLE_KEEPALIVE', true),
            'keepalive_interval' => env('MCP_STDIO_KEEPALIVE_INTERVAL', 30),
            'process_timeout' => env('MCP_STDIO_PROCESS_TIMEOUT', null),
            'line_delimiter' => env('MCP_STDIO_LINE_DELIMITER', "\n"),
            // Message batching configuration
            'batching' => [
                'enabled' => env('MCP_STDIO_BATCHING_ENABLED', false),
                'batch_size' => env('MCP_STDIO_BATCH_SIZE', 10),
                'batch_timeout' => env('MCP_STDIO_BATCH_TIMEOUT', 100),
            ],
            // Resilience configuration
            'resilience' => [
                'max_retry_attempts' => env('MCP_STDIO_MAX_RETRY_ATTEMPTS', 3),
                'base_retry_delay' => env('MCP_STDIO_BASE_RETRY_DELAY', 1000),
                'max_retry_delay' => env('MCP_STDIO_MAX_RETRY_DELAY', 30000),
                'circuit_breaker_threshold' => env('MCP_STDIO_CIRCUIT_BREAKER_THRESHOLD', 5),
                'circuit_breaker_timeout' => env('MCP_STDIO_CIRCUIT_BREAKER_TIMEOUT', 60),
                'max_reconnection_attempts' => env('MCP_STDIO_MAX_RECONNECTION_ATTEMPTS', 5),
            ],
            // Connection management
            'connection_management' => [
                'enabled' => env('MCP_STDIO_CONNECTION_MANAGEMENT_ENABLED', true),
                'health_check_interval' => env('MCP_STDIO_HEALTH_CHECK_INTERVAL', 30),
                'connection_timeout' => env('MCP_STDIO_CONNECTION_TIMEOUT', 60),
            ],
        ],
    ],
];
