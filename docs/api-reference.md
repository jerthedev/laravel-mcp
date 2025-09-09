# API Reference

This document provides comprehensive reference documentation for the Laravel MCP package API, including all classes, methods, configuration options, and extension points.

## Table of Contents

- [Core Classes](#core-classes)
- [Abstract Classes](#abstract-classes)
- [Registry System](#registry-system)
- [Transport Layer](#transport-layer)
- [Configuration](#configuration)
- [Events](#events)
- [Facades](#facades)
- [Artisan Commands](#artisan-commands)
- [Extension Points](#extension-points)

## Core Classes

### LaravelMcpServiceProvider

The main service provider that integrates the MCP package with Laravel.

```php
namespace JTD\LaravelMCP;

class LaravelMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    public function boot(): void
    protected function registerServices(): void
    protected function registerConfigs(): void
    protected function registerCommands(): void
    protected function bootDiscovery(): void
    protected function bootRoutes(): void
}
```

**Methods:**

- `register()` - Registers package services with Laravel container
- `boot()` - Boots package functionality after all providers are registered
- `registerServices()` - Binds core services to container
- `registerConfigs()` - Publishes configuration files
- `registerCommands()` - Registers Artisan commands
- `bootDiscovery()` - Initiates component auto-discovery
- `bootRoutes()` - Sets up HTTP transport routes

### McpRegistry

Central registry for managing MCP components.

```php
namespace JTD\LaravelMCP\Registry;

class McpRegistry
{
    public function __construct(
        protected ToolRegistry $toolRegistry,
        protected ResourceRegistry $resourceRegistry,
        protected PromptRegistry $promptRegistry
    ) {}
    
    public function getTools(): array
    public function getResources(): array
    public function getPrompts(): array
    public function getTool(string $name): ?McpTool
    public function getResource(string $name): ?McpResource
    public function getPrompt(string $name): ?McpPrompt
    public function registerTool(string $name, McpTool $tool): void
    public function registerResource(string $name, McpResource $resource): void
    public function registerPrompt(string $name, McpPrompt $prompt): void
    public function getAllComponents(): array
}
```

**Methods:**

- `getTools()` - Returns array of registered tools
- `getResources()` - Returns array of registered resources  
- `getPrompts()` - Returns array of registered prompts
- `getTool(string $name)` - Get specific tool by name
- `getResource(string $name)` - Get specific resource by name
- `getPrompt(string $name)` - Get specific prompt by name
- `registerTool()` - Register a new tool
- `registerResource()` - Register a new resource
- `registerPrompt()` - Register a new prompt
- `getAllComponents()` - Get all registered components

## Abstract Classes

### McpTool

Base class for creating MCP tools.

```php
namespace JTD\LaravelMCP\Abstracts;

abstract class McpTool
{
    protected Container $container;
    protected ValidationFactory $validator;
    protected string $name;
    protected string $description;
    protected array $parameterSchema = [];
    protected array $middleware = [];
    protected bool $requiresAuth = false;
    
    public function __construct()
    public function getName(): string
    public function getDescription(): string
    public function getInputSchema(): array
    public function execute(array $parameters): mixed
    public function toArray(): array
    
    abstract protected function handle(array $parameters): mixed;
    
    protected function boot(): void
    protected function authorize(array $parameters): bool
    protected function getParameterSchema(): array
    protected function getRequiredParameters(): array
    protected function make(string $abstract, array $parameters = [])
    protected function resolve(string $abstract)
}
```

**Properties:**

- `$container` - Laravel container instance
- `$validator` - Laravel validation factory
- `$name` - Tool name (auto-generated if not set)
- `$description` - Tool description
- `$parameterSchema` - JSON Schema for input validation
- `$middleware` - Middleware to apply
- `$requiresAuth` - Whether authentication is required

**Methods:**

- `getName()` - Get tool name
- `getDescription()` - Get tool description
- `getInputSchema()` - Get JSON Schema for parameters
- `execute(array $parameters)` - Execute the tool with parameters
- `handle(array $parameters)` - **Abstract** - Implement tool logic
- `authorize(array $parameters)` - Authorization check
- `boot()` - Tool initialization hook
- `toArray()` - Get tool definition for MCP

### McpResource

Base class for creating MCP resources.

```php
namespace JTD\LaravelMCP\Abstracts;

abstract class McpResource
{
    protected Container $container;
    protected ValidationFactory $validator;
    protected string $name;
    protected string $description;
    protected string $uriTemplate;
    protected ?string $modelClass = null;
    protected array $middleware = [];
    protected bool $requiresAuth = false;
    
    public function __construct()
    public function getName(): string
    public function getDescription(): string
    public function getUriTemplate(): string
    public function read(array $params): mixed
    public function list(array $params = []): array
    public function subscribe(array $params): mixed
    public function toArray(): array
    
    protected function boot(): void
    protected function handleRead(array $params): mixed
    protected function handleList(array $params): array
    protected function handleSubscribe(array $params): mixed
    protected function customRead(array $params): mixed
    protected function customList(array $params): array
    protected function readFromModel(array $params): mixed
    protected function listFromModel(array $params): array
    protected function authorize(string $action, array $params): bool
    protected function supportsSubscription(): bool
    protected function formatContent(mixed $content): array
    protected function make(string $abstract, array $parameters = [])
}
```

**Properties:**

- `$container` - Laravel container instance
- `$validator` - Laravel validation factory
- `$name` - Resource name (auto-generated if not set)
- `$description` - Resource description
- `$uriTemplate` - URI template for resource access
- `$modelClass` - Eloquent model class (optional)
- `$middleware` - Middleware to apply
- `$requiresAuth` - Whether authentication is required

**Methods:**

- `getName()` - Get resource name
- `getDescription()` - Get resource description
- `getUriTemplate()` - Get URI template
- `read(array $params)` - Read resource data
- `list(array $params)` - List resource items
- `subscribe(array $params)` - Subscribe to resource changes
- `handleRead()` - Handle read operation
- `handleList()` - Handle list operation
- `customRead()` - Custom read implementation
- `customList()` - Custom list implementation
- `readFromModel()` - Read using Eloquent model
- `listFromModel()` - List using Eloquent model
- `authorize()` - Authorization check
- `supportsSubscription()` - Check if subscriptions are supported
- `toArray()` - Get resource definition for MCP

### McpPrompt

Base class for creating MCP prompts.

```php
namespace JTD\LaravelMCP\Abstracts;

abstract class McpPrompt
{
    protected Container $container;
    protected ValidationFactory $validator;
    protected ViewFactory $view;
    protected string $name;
    protected string $description;
    protected array $arguments = [];
    protected ?string $template = null;
    protected array $middleware = [];
    protected bool $requiresAuth = false;
    
    public function __construct()
    public function getName(): string
    public function getDescription(): string
    public function getArguments(): array
    public function get(array $arguments = []): array
    public function toArray(): array
    
    protected function boot(): void
    protected function handleGet(array $arguments): array
    protected function generateContent(array $arguments): string
    protected function renderTemplate(array $arguments): string
    protected function customContent(array $arguments): string
    protected function validateArguments(array $arguments): array
    protected function authorize(array $arguments): bool
    protected function createMessage(string $role, string $content): array
    protected function formatMessages(array $messages): array
    protected function applyTemplate(string $template, array $variables): string
    protected function make(string $abstract, array $parameters = [])
}
```

**Properties:**

- `$container` - Laravel container instance
- `$validator` - Laravel validation factory
- `$view` - Laravel view factory
- `$name` - Prompt name (auto-generated if not set)
- `$description` - Prompt description
- `$arguments` - Argument definitions
- `$template` - Template string or Blade template name
- `$middleware` - Middleware to apply
- `$requiresAuth` - Whether authentication is required

**Methods:**

- `getName()` - Get prompt name
- `getDescription()` - Get prompt description
- `getArguments()` - Get argument definitions
- `get(array $arguments)` - Generate prompt with arguments
- `handleGet()` - Handle prompt generation
- `generateContent()` - Generate prompt content
- `renderTemplate()` - Render template with arguments
- `customContent()` - Custom content generation
- `validateArguments()` - Validate input arguments
- `authorize()` - Authorization check
- `createMessage()` - Create message structure
- `toArray()` - Get prompt definition for MCP

## Registry System

### ToolRegistry

Registry for managing tools.

```php
namespace JTD\LaravelMCP\Registry;

class ToolRegistry extends BaseRegistry
{
    public function register(string $name, McpTool $tool): void
    public function get(string $name): ?McpTool
    public function getAll(): array
    public function has(string $name): bool
    public function remove(string $name): void
    public function clear(): void
    public function count(): int
}
```

### ResourceRegistry

Registry for managing resources.

```php
namespace JTD\LaravelMCP\Registry;

class ResourceRegistry extends BaseRegistry
{
    public function register(string $name, McpResource $resource): void
    public function get(string $name): ?McpResource
    public function getAll(): array
    public function has(string $name): bool
    public function remove(string $name): void
    public function clear(): void
    public function count(): int
}
```

### PromptRegistry

Registry for managing prompts.

```php
namespace JTD\LaravelMCP\Registry;

class PromptRegistry extends BaseRegistry
{
    public function register(string $name, McpPrompt $prompt): void
    public function get(string $name): ?McpPrompt
    public function getAll(): array
    public function has(string $name): bool
    public function remove(string $name): void
    public function clear(): void
    public function count(): int
}
```

### ComponentDiscovery

Automatically discovers and registers MCP components.

```php
namespace JTD\LaravelMCP\Registry;

class ComponentDiscovery
{
    public function __construct(
        protected Filesystem $filesystem,
        protected McpRegistry $registry
    ) {}
    
    public function discover(): void
    public function discoverTools(string $path): void
    public function discoverResources(string $path): void
    public function discoverPrompts(string $path): void
    protected function scanDirectory(string $directory): array
    protected function loadClass(string $filePath): ?string
    protected function registerComponent(string $className, string $type): void
}
```

## Transport Layer

### HttpTransport

HTTP transport for MCP communication.

```php
namespace JTD\LaravelMCP\Transport;

class HttpTransport implements TransportInterface
{
    public function __construct(
        protected McpRegistry $registry,
        protected array $config
    ) {}
    
    public function handle(Request $request): JsonResponse
    public function listTools(): JsonResponse
    public function listResources(): JsonResponse
    public function listPrompts(): JsonResponse
    public function callTool(string $name, array $arguments): JsonResponse
    public function readResource(string $name, array $params): JsonResponse
    public function listResourceItems(string $name, array $params): JsonResponse
    public function getPrompt(string $name, array $arguments): JsonResponse
    protected function formatError(string $message, int $code = 400): JsonResponse
    protected function formatSuccess(mixed $data): JsonResponse
}
```

### StdioTransport

Standard input/output transport for MCP communication.

```php
namespace JTD\LaravelMCP\Transport;

class StdioTransport implements TransportInterface
{
    public function __construct(
        protected McpRegistry $registry,
        protected array $config
    ) {}
    
    public function serve(): void
    public function handleMessage(array $message): array
    protected function handleListTools(): array
    protected function handleListResources(): array
    protected function handleListPrompts(): array
    protected function handleCallTool(array $params): array
    protected function handleReadResource(array $params): array
    protected function handleGetPrompt(array $params): array
    protected function sendMessage(array $message): void
    protected function readMessage(): ?array
    protected function formatResponse(string $id, mixed $result): array
    protected function formatError(string $id, string $message, int $code): array
}
```

### TransportManager

Factory for creating transport instances.

```php
namespace JTD\LaravelMCP\Transport;

class TransportManager
{
    public function __construct(protected array $config) {}
    
    public function create(string $type): TransportInterface
    public function createHttp(): HttpTransport
    public function createStdio(): StdioTransport
    public function getDefaultTransport(): string
    public function getAvailableTransports(): array
}
```

## Configuration

### Main Configuration

Configuration options in `config/laravel-mcp.php`:

```php
return [
    'enabled' => env('MCP_ENABLED', true),
    'default_transport' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),
    
    'discovery' => [
        'enabled' => env('MCP_DISCOVERY_ENABLED', true),
        'paths' => [
            'tools' => env('MCP_TOOLS_PATH', 'app/Mcp/Tools'),
            'resources' => env('MCP_RESOURCES_PATH', 'app/Mcp/Resources'),
            'prompts' => env('MCP_PROMPTS_PATH', 'app/Mcp/Prompts'),
        ],
        'cache' => env('MCP_DISCOVERY_CACHE', true),
    ],
    
    'auth' => [
        'required' => env('MCP_REQUIRE_AUTH', false),
        'guard' => env('MCP_AUTH_GUARD', 'api'),
    ],
    
    'validation' => [
        'strict_mode' => env('MCP_VALIDATION_STRICT', true),
        'custom_rules' => [],
    ],
    
    'logging' => [
        'enabled' => env('MCP_LOGGING_ENABLED', true),
        'channel' => env('MCP_LOG_CHANNEL', 'default'),
        'level' => env('MCP_LOG_LEVEL', 'info'),
    ],
];
```

### Transport Configuration

Configuration options in `config/mcp-transports.php`:

```php
return [
    'http' => [
        'enabled' => env('MCP_HTTP_ENABLED', false),
        'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
        'port' => env('MCP_HTTP_PORT', 8000),
        'middleware' => env('MCP_HTTP_MIDDLEWARE', 'api'),
        'prefix' => env('MCP_HTTP_PREFIX', 'mcp'),
        'rate_limit' => env('MCP_HTTP_RATE_LIMIT', 60),
        'timeout' => env('MCP_HTTP_TIMEOUT', 30),
        'cors' => [
            'enabled' => env('MCP_CORS_ENABLED', true),
            'origins' => env('MCP_CORS_ORIGINS', '*'),
            'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
            'headers' => ['Content-Type', 'Authorization'],
        ],
    ],
    
    'stdio' => [
        'enabled' => env('MCP_STDIO_ENABLED', true),
        'timeout' => env('MCP_STDIO_TIMEOUT', 30),
        'buffer_size' => env('MCP_STDIO_BUFFER_SIZE', 8192),
        'max_message_size' => env('MCP_STDIO_MAX_MESSAGE', 1048576),
        'encoding' => env('MCP_STDIO_ENCODING', 'utf-8'),
    ],
];
```

## Events

### McpComponentRegistered

Fired when an MCP component is registered.

```php
namespace JTD\LaravelMCP\Events;

class McpComponentRegistered
{
    public function __construct(
        public string $name,
        public string $type,
        public mixed $component
    ) {}
}
```

### McpToolExecuted

Fired when an MCP tool is executed.

```php
namespace JTD\LaravelMCP\Events;

class McpToolExecuted
{
    public function __construct(
        public string $toolName,
        public array $parameters,
        public mixed $result,
        public float $executionTime,
        public ?int $userId = null
    ) {}
}
```

### McpResourceAccessed

Fired when an MCP resource is accessed.

```php
namespace JTD\LaravelMCP\Events;

class McpResourceAccessed
{
    public function __construct(
        public string $resourceName,
        public string $action,
        public array $parameters,
        public mixed $result,
        public ?int $userId = null
    ) {}
}
```

### McpPromptGenerated

Fired when an MCP prompt is generated.

```php
namespace JTD\LaravelMCP\Events;

class McpPromptGenerated
{
    public function __construct(
        public string $promptName,
        public array $arguments,
        public array $messages,
        public ?int $userId = null
    ) {}
}
```

## Facades

### Mcp

Main facade for accessing MCP functionality.

```php
namespace JTD\LaravelMCP\Facades;

class Mcp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'mcp.registry';
    }
}
```

**Usage:**

```php
use JTD\LaravelMCP\Facades\Mcp;

// Get all components
$tools = Mcp::getTools();
$resources = Mcp::getResources();
$prompts = Mcp::getPrompts();

// Get specific component
$calculator = Mcp::getTool('calculator');
$users = Mcp::getResource('users');
$email = Mcp::getPrompt('email_template');

// Register components manually
Mcp::registerTool('custom_tool', new CustomTool());
Mcp::registerResource('custom_resource', new CustomResource());
Mcp::registerPrompt('custom_prompt', new CustomPrompt());
```

## Artisan Commands

### mcp:list

List all registered MCP components.

```bash
# List all components
php artisan mcp:list

# List specific type
php artisan mcp:list --type=tools
php artisan mcp:list --type=resources
php artisan mcp:list --type=prompts

# Show detailed information
php artisan mcp:list --verbose
```

### mcp:serve

Start the MCP server with specified transport.

```bash
# Start with default transport
php artisan mcp:serve

# Start with specific transport
php artisan mcp:serve --transport=stdio
php artisan mcp:serve --transport=http

# Specify host and port for HTTP
php artisan mcp:serve --host=0.0.0.0 --port=8080
```

### mcp:status

Show MCP package status and configuration.

```bash
php artisan mcp:status
```

### mcp:clear

Clear MCP caches and registries.

```bash
# Clear all MCP caches
php artisan mcp:clear

# Clear specific cache
php artisan mcp:clear --type=discovery
php artisan mcp:clear --type=components
```

### make:mcp-tool

Generate a new MCP tool.

```bash
# Generate basic tool
php artisan make:mcp-tool CalculatorTool

# Generate with custom namespace
php artisan make:mcp-tool Tools/Advanced/CalculatorTool
```

### make:mcp-resource

Generate a new MCP resource.

```bash
# Generate basic resource
php artisan make:mcp-resource UserResource

# Generate with model binding
php artisan make:mcp-resource UserResource --model=User
```

### make:mcp-prompt

Generate a new MCP prompt.

```bash
# Generate basic prompt
php artisan make:mcp-prompt EmailPrompt

# Generate with template
php artisan make:mcp-prompt EmailPrompt --template
```

## Extension Points

### Custom Middleware

Create custom middleware for MCP components:

```php
namespace App\Mcp\Middleware;

class CustomMcpMiddleware
{
    public function handle($request, Closure $next)
    {
        // Pre-processing logic
        
        $response = $next($request);
        
        // Post-processing logic
        
        return $response;
    }
}
```

### Custom Validation Rules

Add custom validation rules for MCP parameters:

```php
// In AppServiceProvider
use Illuminate\Support\Facades\Validator;

Validator::extend('mcp_custom_rule', function ($attribute, $value, $parameters, $validator) {
    return $this->validateCustomRule($value);
});
```

### Event Listeners

Listen to MCP events:

```php
namespace App\Listeners;

use JTD\LaravelMCP\Events\McpToolExecuted;

class LogMcpToolExecution
{
    public function handle(McpToolExecuted $event): void
    {
        Log::info('MCP Tool executed', [
            'tool' => $event->toolName,
            'parameters' => $event->parameters,
            'execution_time' => $event->executionTime,
            'user_id' => $event->userId,
        ]);
    }
}
```

### Custom Transport

Implement custom transport layer:

```php
namespace App\Mcp\Transports;

use JTD\LaravelMCP\Transport\TransportInterface;

class CustomTransport implements TransportInterface
{
    public function handle($request)
    {
        // Custom transport logic
    }
}
```

### Service Container Extensions

Extend MCP services:

```php
// In a service provider
$this->app->extend('mcp.registry', function ($registry, $app) {
    // Extend registry functionality
    return new EnhancedMcpRegistry($registry);
});
```

## Type Definitions

### Parameter Schema Types

```php
// String parameter
[
    'type' => 'string',
    'description' => 'Parameter description',
    'required' => true,
    'minLength' => 1,
    'maxLength' => 255,
    'pattern' => '^[a-zA-Z0-9]+$',
    'format' => 'email|uri|date|datetime',
]

// Number parameter
[
    'type' => 'number',
    'description' => 'Parameter description',
    'required' => false,
    'minimum' => 0,
    'maximum' => 100,
    'exclusiveMinimum' => false,
    'exclusiveMaximum' => false,
]

// Integer parameter
[
    'type' => 'integer',
    'description' => 'Parameter description',
    'required' => true,
    'minimum' => 1,
    'maximum' => 1000,
]

// Boolean parameter
[
    'type' => 'boolean',
    'description' => 'Parameter description',
    'required' => false,
]

// Array parameter
[
    'type' => 'array',
    'description' => 'Parameter description',
    'required' => false,
    'items' => ['type' => 'string'],
    'minItems' => 1,
    'maxItems' => 10,
    'uniqueItems' => true,
]

// Object parameter
[
    'type' => 'object',
    'description' => 'Parameter description',
    'required' => false,
    'properties' => [
        'name' => ['type' => 'string', 'required' => true],
        'age' => ['type' => 'integer', 'minimum' => 0],
    ],
    'additionalProperties' => false,
]

// Enum parameter
[
    'type' => 'string',
    'description' => 'Parameter description',
    'required' => true,
    'enum' => ['option1', 'option2', 'option3'],
]
```

## Error Codes

### Common Error Codes

- `400` - Bad Request (invalid parameters)
- `401` - Unauthorized (authentication required)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found (component/resource not found)
- `422` - Unprocessable Entity (validation errors)
- `429` - Too Many Requests (rate limit exceeded)
- `500` - Internal Server Error (system error)

### MCP-Specific Error Codes

- `MCP_001` - Component not registered
- `MCP_002` - Invalid parameter schema
- `MCP_003` - Transport not available
- `MCP_004` - Discovery failed
- `MCP_005` - Component initialization failed

## Performance Considerations

### Caching

```php
// Cache component discovery results
'discovery' => [
    'cache' => true,
    'cache_ttl' => 3600,
]

// Cache tool results
Cache::remember("mcp.tool.{$name}.{$hash}", 300, $callback);

// Cache resource data
Cache::tags(['mcp', 'resources'])->remember($key, 600, $callback);
```

### Optimization Tips

1. **Use model-based resources** for simple CRUD operations
2. **Implement caching** for expensive operations
3. **Use lazy loading** for large datasets
4. **Optimize database queries** with proper indexing
5. **Consider async processing** for long-running operations
6. **Implement rate limiting** for public endpoints

---

This API reference provides comprehensive documentation for the Laravel MCP package. For specific implementation examples, see the [Usage Guides](usage/tools.md) and [Quick Start Guide](quick-start.md).