# MCP Server Specification

## Overview

The MCP Server is the core component that implements the Model Context Protocol specification. It handles client connections, processes JSON-RPC messages, manages capabilities, and orchestrates communication between AI clients and Laravel application components.

## Server Architecture

### Core Components
```
McpServer
├── Protocol Layer
│   ├── JsonRpcHandler           # JSON-RPC 2.0 implementation
│   ├── MessageProcessor         # MCP message processing
│   ├── CapabilityNegotiator     # Client capability negotiation
│   └── NotificationHandler      # Real-time notifications
├── Transport Layer
│   ├── HttpTransport            # HTTP-based transport
│   ├── StdioTransport           # Stdio-based transport
│   └── TransportManager         # Transport orchestration
├── Registry Layer
│   ├── McpRegistry              # Component registry
│   ├── ToolRegistry             # Tool management
│   ├── ResourceRegistry         # Resource management
│   └── PromptRegistry           # Prompt management
└── Integration Layer
    ├── LaravelBridge            # Laravel service integration
    ├── SecurityManager          # Authentication & authorization
    └── ErrorHandler             # Error management
```

## Server Implementation

### Main Server Class
```php
<?php

namespace JTD\LaravelMCP;

use MCP\Server as McpSdkServer;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Transport\TransportManager;

class McpServer
{
    private McpSdkServer $server;
    private JsonRpcHandler $jsonRpcHandler;
    private MessageProcessor $messageProcessor;
    private CapabilityNegotiator $capabilityNegotiator;
    private McpRegistry $registry;
    private TransportManager $transportManager;

    public function __construct(
        JsonRpcHandler $jsonRpcHandler,
        MessageProcessor $messageProcessor,
        CapabilityNegotiator $capabilityNegotiator,
        McpRegistry $registry,
        TransportManager $transportManager
    ) {
        $this->jsonRpcHandler = $jsonRpcHandler;
        $this->messageProcessor = $messageProcessor;
        $this->capabilityNegotiator = $capabilityNegotiator;
        $this->registry = $registry;
        $this->transportManager = $transportManager;
        
        $this->initializeServer();
    }

    private function initializeServer(): void
    {
        $this->server = new McpSdkServer();
        $this->setupCapabilities();
        $this->registerHandlers();
    }
}
```

## Protocol Implementation

### JSON-RPC Handler
```php
<?php

namespace JTD\LaravelMCP\Protocol;

use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Exceptions\ProtocolException;

class JsonRpcHandler implements JsonRpcHandlerInterface
{
    public function processRequest(string $rawMessage): string
    {
        try {
            $request = $this->parseRequest($rawMessage);
            $this->validateRequest($request);
            
            $response = $this->handleRequest($request);
            return $this->formatResponse($response, $request['id'] ?? null);
        } catch (\Throwable $e) {
            return $this->formatError($e, $request['id'] ?? null);
        }
    }

    private function parseRequest(string $rawMessage): array
    {
        $decoded = json_decode($rawMessage, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ProtocolException('Parse error: Invalid JSON');
        }
        
        return $decoded;
    }

    private function validateRequest(array $request): void
    {
        // Validate JSON-RPC 2.0 structure
        if (!isset($request['jsonrpc']) || $request['jsonrpc'] !== '2.0') {
            throw new ProtocolException('Invalid JSON-RPC version');
        }
        
        if (!isset($request['method'])) {
            throw new ProtocolException('Missing method parameter');
        }
    }

    private function handleRequest(array $request): mixed
    {
        $method = $request['method'];
        $params = $request['params'] ?? [];
        
        return match ($method) {
            'initialize' => $this->handleInitialize($params),
            'tools/list' => $this->handleToolsList($params),
            'tools/call' => $this->handleToolsCall($params),
            'resources/list' => $this->handleResourcesList($params),
            'resources/read' => $this->handleResourcesRead($params),
            'prompts/list' => $this->handlePromptsList($params),
            'prompts/get' => $this->handlePromptsGet($params),
            'completion/complete' => $this->handleCompletionComplete($params),
            default => throw new ProtocolException("Unknown method: $method")
        };
    }
}
```

### Message Processor
```php
<?php

namespace JTD\LaravelMCP\Protocol;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Protocol\Contracts\MessageHandlerInterface;

class MessageProcessor implements MessageHandlerInterface
{
    private McpRegistry $registry;
    private array $messageHandlers = [];

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
        $this->registerMessageHandlers();
    }

    public function processMessage(string $method, array $params): mixed
    {
        if (!isset($this->messageHandlers[$method])) {
            throw new \InvalidArgumentException("No handler for method: $method");
        }

        $handler = $this->messageHandlers[$method];
        return $handler($params);
    }

    private function registerMessageHandlers(): void
    {
        $this->messageHandlers = [
            'initialize' => [$this, 'handleInitialize'],
            'tools/list' => [$this, 'handleToolsList'],
            'tools/call' => [$this, 'handleToolCall'],
            'resources/list' => [$this, 'handleResourcesList'],
            'resources/read' => [$this, 'handleResourceRead'],
            'prompts/list' => [$this, 'handlePromptsList'],
            'prompts/get' => [$this, 'handlePromptGet'],
            'completion/complete' => [$this, 'handleCompletion'],
        ];
    }

    public function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $this->getServerCapabilities(),
            'serverInfo' => [
                'name' => config('laravel-mcp.server.name', 'Laravel MCP Server'),
                'version' => $this->getServerVersion(),
            ],
        ];
    }

    public function handleToolsList(array $params): array
    {
        $tools = $this->registry->getTools();
        
        return [
            'tools' => array_map(function ($tool) {
                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => $tool->getInputSchema(),
                ];
            }, $tools),
        ];
    }

    public function handleToolCall(array $params): array
    {
        $name = $params['name'] ?? throw new \InvalidArgumentException('Tool name is required');
        $arguments = $params['arguments'] ?? [];

        $tool = $this->registry->getTool($name);
        if (!$tool) {
            throw new \InvalidArgumentException("Tool not found: $name");
        }

        $result = $tool->execute($arguments);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($result) ? $result : json_encode($result),
                ]
            ],
            'isError' => false,
        ];
    }
}
```

### Capability Negotiation
```php
<?php

namespace JTD\LaravelMCP\Protocol;

class CapabilityNegotiator
{
    private array $serverCapabilities = [];
    private array $clientCapabilities = [];

    public function __construct()
    {
        $this->initializeServerCapabilities();
    }

    public function negotiateCapabilities(array $clientCapabilities): array
    {
        $this->clientCapabilities = $clientCapabilities;
        
        return [
            'capabilities' => $this->getNegotiatedCapabilities(),
            'protocolVersion' => $this->getProtocolVersion(),
        ];
    }

    private function initializeServerCapabilities(): void
    {
        $this->serverCapabilities = [
            'tools' => [
                'listChanged' => true,
            ],
            'resources' => [
                'listChanged' => true,
                'subscribe' => config('laravel-mcp.features.resource_subscriptions', false),
            ],
            'prompts' => [
                'listChanged' => true,
            ],
            'logging' => [
                'level' => config('laravel-mcp.logging.level', 'info'),
            ],
        ];
    }

    private function getNegotiatedCapabilities(): array
    {
        $negotiated = [];

        foreach ($this->serverCapabilities as $capability => $features) {
            if ($this->clientSupports($capability)) {
                $negotiated[$capability] = $this->negotiateFeatures($capability, $features);
            }
        }

        return $negotiated;
    }

    private function clientSupports(string $capability): bool
    {
        return isset($this->clientCapabilities[$capability]);
    }

    private function negotiateFeatures(string $capability, array $serverFeatures): array
    {
        $clientFeatures = $this->clientCapabilities[$capability] ?? [];
        $negotiated = [];

        foreach ($serverFeatures as $feature => $value) {
            if ($this->featureSupported($clientFeatures, $feature)) {
                $negotiated[$feature] = $value;
            }
        }

        return $negotiated;
    }
}
```

## Server Lifecycle

### Initialization Process
```php
public function initialize(array $clientInfo): array
{
    // 1. Validate client information
    $this->validateClientInfo($clientInfo);
    
    // 2. Negotiate capabilities
    $capabilities = $this->capabilityNegotiator->negotiateCapabilities(
        $clientInfo['capabilities'] ?? []
    );
    
    // 3. Initialize components
    $this->initializeComponents();
    
    // 4. Start background services
    $this->startBackgroundServices();
    
    // 5. Return server information
    return [
        'protocolVersion' => '2024-11-05',
        'capabilities' => $capabilities['capabilities'],
        'serverInfo' => $this->getServerInfo(),
    ];
}

private function initializeComponents(): void
{
    // Initialize registries
    $this->registry->initialize();
    
    // Start transport layers
    $this->transportManager->initialize();
    
    // Setup error handling
    $this->setupErrorHandling();
    
    // Initialize logging
    $this->initializeLogging();
}
```

### Shutdown Process
```php
public function shutdown(): void
{
    try {
        // 1. Stop accepting new connections
        $this->transportManager->stopAcceptingConnections();
        
        // 2. Complete pending requests
        $this->completePendingRequests();
        
        // 3. Clean up resources
        $this->cleanupResources();
        
        // 4. Close connections
        $this->transportManager->closeConnections();
        
        // 5. Log shutdown
        $this->logShutdown();
    } catch (\Throwable $e) {
        // Log error but don't throw during shutdown
        logger()->error('Error during MCP server shutdown', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
```

## Server Configuration

### Configuration Schema
```php
return [
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
        'version' => '1.0.0',
        'description' => env('MCP_SERVER_DESCRIPTION', 'MCP Server built with Laravel'),
    ],
    
    'protocol' => [
        'version' => '2024-11-05',
        'timeout' => env('MCP_PROTOCOL_TIMEOUT', 30),
        'max_message_size' => env('MCP_MAX_MESSAGE_SIZE', 1048576), // 1MB
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
        'completion' => [
            'enabled' => env('MCP_COMPLETION_ENABLED', false),
        ],
        'logging' => [
            'enabled' => env('MCP_LOGGING_ENABLED', true),
            'level' => env('MCP_LOG_LEVEL', 'info'),
        ],
    ],
    
    'security' => [
        'authentication' => [
            'enabled' => env('MCP_AUTH_ENABLED', false),
            'method' => env('MCP_AUTH_METHOD', 'token'), // token, basic, none
            'token' => env('MCP_AUTH_TOKEN'),
        ],
        'authorization' => [
            'enabled' => env('MCP_AUTHZ_ENABLED', false),
            'rules' => [
                // Authorization rules
            ],
        ],
        'rate_limiting' => [
            'enabled' => env('MCP_RATE_LIMITING_ENABLED', true),
            'max_requests_per_minute' => env('MCP_RATE_LIMIT', 60),
        ],
    ],
    
    'performance' => [
        'max_concurrent_requests' => env('MCP_MAX_CONCURRENT_REQUESTS', 10),
        'request_timeout' => env('MCP_REQUEST_TIMEOUT', 30),
        'memory_limit' => env('MCP_MEMORY_LIMIT', '128M'),
    ],
];
```

## Error Handling

### Error Response Format
```php
private function formatError(\Throwable $e, ?string $id = null): string
{
    $code = match (true) {
        $e instanceof ParseException => -32700,
        $e instanceof InvalidRequestException => -32600,
        $e instanceof MethodNotFoundException => -32601,
        $e instanceof InvalidParamsException => -32602,
        $e instanceof InternalErrorException => -32603,
        default => -32000, // Server error
    };

    $error = [
        'jsonrpc' => '2.0',
        'error' => [
            'code' => $code,
            'message' => $e->getMessage(),
        ],
        'id' => $id,
    ];

    // Add debug information in development
    if (app()->environment('local', 'development')) {
        $error['error']['data'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }

    return json_encode($error);
}
```

## Monitoring and Diagnostics

### Server Status
```php
public function getStatus(): array
{
    return [
        'server' => [
            'name' => $this->getServerName(),
            'version' => $this->getServerVersion(),
            'uptime' => $this->getUptime(),
            'status' => 'running',
        ],
        'connections' => [
            'active' => $this->transportManager->getActiveConnectionCount(),
            'total' => $this->transportManager->getTotalConnectionCount(),
        ],
        'components' => [
            'tools' => count($this->registry->getTools()),
            'resources' => count($this->registry->getResources()),
            'prompts' => count($this->registry->getPrompts()),
        ],
        'performance' => [
            'requests_processed' => $this->getRequestsProcessed(),
            'average_response_time' => $this->getAverageResponseTime(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ],
    ];
}
```

### Health Checks
```php
public function healthCheck(): array
{
    $checks = [
        'server' => $this->checkServerHealth(),
        'transports' => $this->checkTransportHealth(),
        'registry' => $this->checkRegistryHealth(),
        'database' => $this->checkDatabaseHealth(),
        'dependencies' => $this->checkDependencyHealth(),
    ];

    $healthy = array_reduce($checks, fn($carry, $check) => $carry && $check['healthy'], true);

    return [
        'healthy' => $healthy,
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ];
}
```

## Performance Optimization

### Request Processing
- Asynchronous message processing where possible
- Connection pooling for HTTP transport
- Message batching for bulk operations
- Caching for frequently accessed data

### Resource Management
- Memory usage monitoring and cleanup
- Connection lifecycle management
- Garbage collection optimization
- Resource pooling for expensive operations

### Scalability Features
- Horizontal scaling via load balancing
- State externalization for multi-instance deployments
- Background job processing for long-running tasks
- Real-time notification distribution