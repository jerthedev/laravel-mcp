<?php

return [
    'enabled' => env('MCP_ENABLED', true),

    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
        'version' => '1.0.0',
        'description' => env('MCP_SERVER_DESCRIPTION', 'MCP Server built with Laravel'),
        'vendor' => 'JTD/LaravelMCP',
    ],

    'capabilities' => [
        'tools' => [
            'enabled' => env('MCP_TOOLS_ENABLED', true),
            'list_changed_notifications' => true,
        ],
        'resources' => [
            'enabled' => env('MCP_RESOURCES_ENABLED', true),
            'list_changed_notifications' => true,
            'subscriptions' => env('MCP_RESOURCE_SUBSCRIPTIONS', false),
        ],
        'prompts' => [
            'enabled' => env('MCP_PROMPTS_ENABLED', true),
            'list_changed_notifications' => true,
        ],
        'logging' => [
            'enabled' => env('MCP_LOGGING_ENABLED', true),
            'level' => env('MCP_LOG_LEVEL', 'info'),
        ],
        'experimental' => [
            'enabled' => env('MCP_EXPERIMENTAL_ENABLED', false),
        ],
        'completion' => [
            'enabled' => env('MCP_COMPLETION_ENABLED', false),
        ],
    ],

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

    'auth' => [
        'enabled' => env('MCP_AUTH_ENABLED', false),
        'api_key' => env('MCP_API_KEY'),
    ],

    'cors' => [
        'allowed_origins' => explode(',', env('MCP_CORS_ALLOWED_ORIGINS', '*')),
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-MCP-API-Key',
        ],
        'max_age' => env('MCP_CORS_MAX_AGE', 86400),
    ],

    'cache' => [
        'store' => env('MCP_CACHE_STORE', 'file'),
        'ttl' => env('MCP_CACHE_TTL', 3600),
    ],

    'validation' => [
        'validate_handlers' => env('MCP_VALIDATE_HANDLERS', true),
        'strict_mode' => env('MCP_STRICT_MODE', false),
    ],

    'events' => [
        'enabled' => env('MCP_EVENTS_ENABLED', true),
        'listeners' => [
            // Event listeners will be registered here
        ],
    ],

    'queue' => [
        'enabled' => env('MCP_QUEUE_ENABLED', false),
        'default' => env('MCP_QUEUE_NAME', 'mcp'),
        'connection' => env('MCP_QUEUE_CONNECTION', null),
        'retry_after' => env('MCP_QUEUE_RETRY_AFTER', 90),
        'timeout' => env('MCP_QUEUE_TIMEOUT', 300),
    ],

    'notifications' => [
        'enabled' => env('MCP_NOTIFICATIONS_ENABLED', true),
        'channels' => ['database'],
        'notifiable' => null, // Class that should receive notifications
        'admin_email' => env('MCP_ADMIN_EMAIL'),
        'severity_threshold' => env('MCP_NOTIFICATION_SEVERITY', 'error'),
        'slack' => [
            'enabled' => env('MCP_SLACK_ENABLED', false),
            'webhook_url' => env('MCP_SLACK_WEBHOOK_URL'),
            'channel' => env('MCP_SLACK_CHANNEL', '#mcp-errors'),
            'username' => env('MCP_SLACK_USERNAME', 'MCP Error Bot'),
        ],
        'dashboard_url' => env('MCP_DASHBOARD_URL'),
    ],
];
