# McpManager API Reference

The `McpManager` class is the central coordination point for all MCP operations in the Laravel MCP package. It provides a unified interface for component registration, request processing, event management, and system monitoring.

## Class Overview

```php
<?php

namespace JTD\LaravelMCP;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;

class McpManager
{
    public function __construct(
        protected McpRegistry $registry,
        protected RouteRegistrar $registrar
    ) {}
}
```

## Core Registration Methods

### Component Registration

#### `registerTool(string $name, mixed $tool, array $metadata = []): void`

Registers a tool with the MCP registry.

**Parameters:**
- `$name` (string): Unique tool identifier
- `$tool` (mixed): Tool instance or class name
- `$metadata` (array): Optional metadata for the tool

**Example:**
```php
use App\Mcp\Tools\CalculatorTool;
use JTD\LaravelMCP\Facades\Mcp;

Mcp::registerTool('calculator', new CalculatorTool(), [
    'description' => 'Performs basic mathematical calculations',
    'tags' => ['math', 'utility'],
    'version' => '1.0.0'
]);

// Or with class name (lazy loading)
Mcp::registerTool('calculator', CalculatorTool::class, [
    'description' => 'Performs basic mathematical calculations'
]);
```

#### `registerResource(string $name, mixed $resource, array $metadata = []): void`

Registers a resource with the MCP registry.

**Parameters:**
- `$name` (string): Unique resource identifier
- `$resource` (mixed): Resource instance or class name
- `$metadata` (array): Optional metadata for the resource

**Example:**
```php
use App\Mcp\Resources\UserResource;

Mcp::registerResource('users', new UserResource(), [
    'description' => 'User management resource',
    'schemas' => ['user', 'user_list'],
    'permissions' => ['read', 'write']
]);
```

#### `registerPrompt(string $name, mixed $prompt, array $metadata = []): void`

Registers a prompt template with the MCP registry.

**Parameters:**
- `$name` (string): Unique prompt identifier
- `$prompt` (mixed): Prompt instance or class name
- `$metadata` (array): Optional metadata for the prompt

**Example:**
```php
use App\Mcp\Prompts\EmailPrompt;

Mcp::registerPrompt('email_template', new EmailPrompt(), [
    'description' => 'Email template generator',
    'variables' => ['recipient', 'subject', 'content'],
    'category' => 'communication'
]);
```

### Route-Style Registration

#### `tool(string $name, mixed $handler, array $options = []): self`

Registers a tool using Laravel route-style syntax.

**Parameters:**
- `$name` (string): Tool name
- `$handler` (mixed): Tool handler (class, callable, or instance)
- `$options` (array): Registration options

**Example:**
```php
Mcp::tool('weather', WeatherTool::class)
   ->tool('database', DatabaseTool::class, ['middleware' => 'auth'])
   ->tool('calculator', function($params) {
       return $params['a'] + $params['b'];
   });
```

#### `resource(string $name, mixed $handler, array $options = []): self`

Registers a resource using route-style syntax.

**Example:**
```php
Mcp::resource('posts', PostResource::class)
   ->resource('users', UserResource::class, ['permissions' => 'admin']);
```

#### `prompt(string $name, mixed $handler, array $options = []): self`

Registers a prompt using route-style syntax.

**Example:**
```php
Mcp::prompt('welcome', WelcomePrompt::class)
   ->prompt('notification', NotificationPrompt::class, ['category' => 'system']);
```

#### `group(array $attributes, Closure $callback): void`

Groups component registrations with shared attributes.

**Parameters:**
- `$attributes` (array): Shared attributes for the group
- `$callback` (Closure): Callback containing registrations

**Example:**
```php
Mcp::group(['middleware' => 'auth', 'prefix' => 'admin'], function ($mcp) {
    $mcp->tool('users', AdminUserTool::class);
    $mcp->resource('logs', AdminLogResource::class);
    $mcp->prompt('report', AdminReportPrompt::class);
});

Mcp::group(['tags' => ['utility'], 'version' => '2.0'], function ($mcp) {
    $mcp->tool('calculator', CalculatorTool::class);
    $mcp->tool('converter', ConverterTool::class);
});
```

## Component Management Methods

### Retrieval Methods

#### `getTool(string $name): mixed`

Retrieves a registered tool by name.

**Example:**
```php
$calculator = Mcp::getTool('calculator');
$result = $calculator->execute(['operation' => 'add', 'a' => 5, 'b' => 3]);
```

#### `getResource(string $name): mixed`

Retrieves a registered resource by name.

#### `getPrompt(string $name): mixed`

Retrieves a registered prompt by name.

### Existence Checks

#### `hasTool(string $name): bool`

Checks if a tool exists.

```php
if (Mcp::hasTool('calculator')) {
    $result = Mcp::getTool('calculator')->execute($params);
}
```

#### `hasResource(string $name): bool`

Checks if a resource exists.

#### `hasPrompt(string $name): bool`

Checks if a prompt exists.

### Listing Methods

#### `listTools(): array`

Returns all registered tools with their metadata.

**Returns:** Array of tool definitions
```php
[
    'calculator' => [
        'name' => 'calculator',
        'class' => 'App\Mcp\Tools\CalculatorTool',
        'description' => 'Performs basic mathematical calculations',
        'metadata' => ['tags' => ['math', 'utility']]
    ],
    // ... more tools
]
```

#### `listResources(): array`

Returns all registered resources with their metadata.

#### `listPrompts(): array`

Returns all registered prompts with their metadata.

### Unregistration Methods

#### `unregisterTool(string $name): bool`

Unregisters a tool from the registry.

**Returns:** `true` if successful, `false` if tool doesn't exist

#### `unregisterResource(string $name): bool`

Unregisters a resource from the registry.

#### `unregisterPrompt(string $name): bool`

Unregisters a prompt from the registry.

## Asynchronous Processing

### Request Dispatching

#### `dispatchAsync(string $method, array $parameters = [], array $context = []): string`

Dispatches an MCP request for asynchronous processing.

**Parameters:**
- `$method` (string): MCP method to execute
- `$parameters` (array): Method parameters
- `$context` (array): Additional context for processing

**Returns:** Unique request ID for tracking

**Example:**
```php
$requestId = Mcp::dispatchAsync('tools/call', [
    'name' => 'data-processor',
    'arguments' => [
        'file' => 'large-dataset.csv',
        'operation' => 'analyze'
    ]
], [
    'priority' => 'high',
    'timeout' => 600
]);

echo "Request queued with ID: {$requestId}";
```

**Alias:** `async(string $method, array $parameters = [], array $context = []): string`

### Status and Result Retrieval

#### `getAsyncStatus(string $requestId): ?array`

Gets the status of an async request.

**Returns:** Status array or `null` if not found
```php
[
    'status' => 'processing',    // 'queued', 'processing', 'completed', 'failed'
    'progress' => 0.65,         // Optional progress (0.0 - 1.0)
    'message' => 'Processing...',
    'started_at' => '2024-01-15T10:30:00Z',
    'estimated_completion' => '2024-01-15T10:45:00Z'
]
```

**Example:**
```php
$status = Mcp::getAsyncStatus($requestId);

switch ($status['status']) {
    case 'queued':
        echo "Request is queued for processing";
        break;
    case 'processing':
        echo "Request is being processed ({$status['progress']}% complete)";
        break;
    case 'completed':
        echo "Request completed successfully";
        break;
    case 'failed':
        echo "Request failed: {$status['error']}";
        break;
}
```

#### `getAsyncResult(string $requestId): mixed`

Gets the result of a completed async request.

**Returns:** Request result or `null` if not completed

#### `asyncResult(string $requestId): mixed`

Alias for `getAsyncResult()` that unwraps the result data.

```php
// Wait for completion and get result
while (true) {
    $status = Mcp::getAsyncStatus($requestId);
    
    if ($status['status'] === 'completed') {
        $result = Mcp::asyncResult($requestId);
        break;
    } elseif ($status['status'] === 'failed') {
        throw new Exception($status['error']);
    }
    
    sleep(1);
}
```

## Event Management

### Event Dispatching

#### `dispatchComponentRegistered(string $type, string $name, mixed $component, array $metadata = []): void`

Dispatches a component registration event.

**Parameters:**
- `$type` (string): Component type ('tool', 'resource', 'prompt')
- `$name` (string): Component name
- `$component` (mixed): Component instance
- `$metadata` (array): Component metadata

**Alias:** `fireComponentRegistered()`

#### `dispatchRequestProcessed(string|int $requestId, string $method, array $parameters, mixed $result, float $executionTime, string $transport = 'http', array $context = []): void`

Dispatches a request processed event.

**Parameters:**
- `$requestId` (string|int): Request identifier
- `$method` (string): MCP method executed
- `$parameters` (array): Request parameters
- `$result` (mixed): Request result
- `$executionTime` (float): Execution time in milliseconds
- `$transport` (string): Transport used ('http', 'stdio', 'async')
- `$context` (array): Additional context

**Alias:** `fireRequestProcessed()`

## Notification System

### Error Notifications

#### `notifyError(string $errorType, string $errorMessage, ?string $method = null, array $parameters = [], ?Throwable $exception = null, string $severity = 'error'): void`

Sends an error notification through configured channels.

**Parameters:**
- `$errorType` (string): Type of error (e.g., 'validation_error', 'tool_error')
- `$errorMessage` (string): Human-readable error message
- `$method` (string|null): MCP method that caused the error
- `$parameters` (array): Request parameters when error occurred
- `$exception` (Throwable|null): Exception object for detailed logging
- `$severity` (string): Error severity ('debug', 'info', 'warning', 'error', 'critical')

**Example:**
```php
try {
    $result = $tool->execute($params);
} catch (Exception $e) {
    Mcp::notifyError(
        'tool_execution_error',
        'Calculator tool failed to process request',
        'tools/call',
        $params,
        $e,
        'error'
    );
    throw $e;
}
```

## Capability Management

### Server Capabilities

#### `getCapabilities(): array`

Returns current server capabilities.

**Example:**
```php
$capabilities = Mcp::getCapabilities();
/*
[
    'tools' => [
        'listChanged' => true,
        'supports' => ['call', 'list']
    ],
    'resources' => [
        'listChanged' => true,
        'supports' => ['list', 'read']
    ],
    'prompts' => [
        'listChanged' => true,
        'supports' => ['get', 'list']
    ],
    'logging' => ['enabled' => true]
]
*/
```

#### `setCapabilities(array $capabilities): void`

Sets server capabilities.

**Example:**
```php
Mcp::setCapabilities([
    'tools' => [
        'listChanged' => true,
        'supports' => ['call', 'list']
    ],
    'resources' => [
        'listChanged' => false,
        'supports' => ['list']  // Read-only resources
    ]
]);
```

## Component Discovery

### Auto-Discovery

#### `discover(array $paths = []): array`

Discovers components in specified paths or default locations.

**Parameters:**
- `$paths` (array): Optional custom paths to scan

**Returns:** Array of discovered components

**Example:**
```php
// Use default paths
$discovered = Mcp::discover();

// Custom paths
$discovered = Mcp::discover([
    app_path('Custom/Tools'),
    app_path('Custom/Resources'),
    base_path('packages/mcp-components')
]);

foreach ($discovered['tools'] as $toolName => $toolClass) {
    Mcp::registerTool($toolName, $toolClass);
}
```

## System Information and Monitoring

### Server Information

#### `getServerInfo(): array`

Returns comprehensive server information.

**Example:**
```php
$info = Mcp::getServerInfo();
/*
[
    'name' => 'Laravel MCP Server',
    'version' => '1.0.0',
    'protocol_version' => '2024-11-05',
    'capabilities' => [...],
    'components' => [
        'tools' => 12,
        'resources' => 5,
        'prompts' => 8
    ]
]
*/
```

#### `getServerStats(): array`

Returns server performance statistics.

**Example:**
```php
$stats = Mcp::getServerStats();
/*
[
    'uptime' => 3600,                    // seconds
    'requests_processed' => 1542,
    'errors_count' => 23,
    'average_response_time' => 125.5,    // milliseconds
    'memory_usage' => 52428800,          // bytes
    'peak_memory' => 67108864            // bytes
]
*/
```

## Debug and Development

### Debug Mode

#### `enableDebugMode(): void`

Enables debug mode for detailed logging and error reporting.

#### `disableDebugMode(): void`

Disables debug mode.

#### `isDebugMode(): bool`

Checks if debug mode is enabled.

**Example:**
```php
if (app()->environment('local')) {
    Mcp::enableDebugMode();
}

if (Mcp::isDebugMode()) {
    // Additional debug logging
    Log::debug('Debug mode is active', Mcp::getServerStats());
}
```

### Server Control

#### `startServer(array $config = []): void`

Starts the MCP server with optional configuration.

**Note:** Implementation depends on transport type.

#### `stopServer(): void`

Stops the MCP server.

#### `isServerRunning(): bool`

Checks if the server is currently running.

## Magic Methods

### `__call(string $method, array $parameters): mixed`

Provides dynamic method forwarding to underlying services.

**Behavior:**
1. First checks if method exists on `RouteRegistrar`
2. Then checks if method exists on `McpRegistry`
3. Throws `BadMethodCallException` if method not found

**Example:**
```php
// These calls are forwarded to the appropriate service
Mcp::bind('custom-method', $handler);           // → RouteRegistrar
Mcp::register('tool', 'name', $tool);          // → McpRegistry
Mcp::getAllRegisteredComponents();              // → McpRegistry
```

## Usage Patterns

### Fluent Registration

```php
Mcp::tool('calculator', CalculatorTool::class)
   ->tool('weather', WeatherTool::class)
   ->resource('users', UserResource::class)
   ->prompt('email', EmailPrompt::class);
```

### Batch Operations

```php
// Register multiple tools at once
$tools = [
    'calculator' => CalculatorTool::class,
    'weather' => WeatherTool::class,
    'database' => DatabaseTool::class
];

foreach ($tools as $name => $class) {
    Mcp::registerTool($name, $class, ['batch' => true]);
}
```

### Conditional Registration

```php
// Register based on environment
if (app()->environment('production')) {
    Mcp::group(['middleware' => ['auth', 'rate-limit']], function ($mcp) {
        $mcp->tool('admin', AdminTool::class);
    });
} else {
    Mcp::tool('admin', AdminTool::class);
}
```

### Event-Driven Operations

```php
// Listen for component registration
Event::listen(McpComponentRegistered::class, function ($event) {
    if ($event->type === 'tool') {
        Mcp::notifyError(
            'tool_registered',
            "New tool registered: {$event->name}",
            null,
            [],
            null,
            'info'
        );
    }
});
```

## Error Handling

The `McpManager` throws specific exceptions for different error conditions:

- `BadMethodCallException`: When calling non-existent methods
- `RuntimeException`: When queue processing is disabled for async operations
- `InvalidArgumentException`: When providing invalid parameters

Always wrap manager operations in appropriate try-catch blocks for production applications.