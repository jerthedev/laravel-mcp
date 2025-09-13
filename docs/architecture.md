# Laravel MCP Package Architecture

## Overview

The Laravel MCP package implements the Model Context Protocol (MCP) 1.0 specification, providing a seamless bridge between Laravel applications and AI clients like Claude Desktop, Claude Code, and ChatGPT Desktop. This document provides a comprehensive overview of the package's architecture, design decisions, and implementation patterns.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                        AI Clients                           │
│        (Claude Desktop, Claude Code, ChatGPT Desktop)       │
└─────────────────┬───────────────────┬───────────────────────┘
                  │                   │
                  ▼                   ▼
         ┌────────────────┐  ┌────────────────┐
         │  HTTP Transport │  │ Stdio Transport │
         └────────┬───────┘  └────────┬───────┘
                  │                   │
                  ▼                   ▼
         ┌──────────────────────────────────────┐
         │         Transport Manager            │
         │  (Factory pattern for transports)    │
         └────────────────┬─────────────────────┘
                          │
                          ▼
         ┌──────────────────────────────────────┐
         │      JSON-RPC 2.0 Protocol Layer     │
         │   (Request/Response processing)       │
         └────────────────┬─────────────────────┘
                          │
                          ▼
         ┌──────────────────────────────────────┐
         │         MCP Request Router           │
         │    (Method routing and dispatch)      │
         └────────────────┬─────────────────────┘
                          │
                          ▼
         ┌──────────────────────────────────────┐
         │          MCP Registry System         │
         │  (Component discovery & management)   │
         └────────┬───────┬───────┬─────────────┘
                  │       │       │
                  ▼       ▼       ▼
         ┌─────────┐ ┌──────────┐ ┌─────────┐
         │  Tools  │ │Resources │ │ Prompts │
         └─────────┘ └──────────┘ └─────────┘
                  │       │       │
                  ▼       ▼       ▼
         ┌──────────────────────────────────────┐
         │       Laravel Application Layer      │
         │  (Services, Models, Controllers)     │
         └──────────────────────────────────────┘
```

## Core Components

### 1. Service Provider (`LaravelMcpServiceProvider`)

The service provider is the main integration point with Laravel, responsible for:

- **Registration Phase**: Binding core services and interfaces to the IoC container
- **Boot Phase**: Setting up routes, middleware, component discovery
- **Publishing**: Managing configuration and asset publishing

```php
// Key bindings
$this->app->singleton(McpRegistry::class);
$this->app->singleton(TransportManager::class);
$this->app->singleton(JsonRpcProcessor::class);
$this->app->singleton(McpRequestRouter::class);
```

### 2. Transport Layer

The transport layer handles communication between AI clients and the MCP server:

#### HTTP Transport
- RESTful API endpoint at `/mcp`
- Middleware stack for authentication, CORS, rate limiting
- JSON request/response handling
- Suitable for web-based integrations

#### Stdio Transport
- Standard input/output communication
- Message framing for desktop AI clients
- Efficient for local process communication
- Primary transport for Claude Desktop

#### Transport Manager
- Factory pattern implementation
- Dynamic transport selection
- Configuration-based instantiation
- Extensible for custom transports

### 3. Protocol Layer

Implements JSON-RPC 2.0 and MCP 1.0 specifications:

#### JSON-RPC Processor
```php
class JsonRpcProcessor {
    public function process(array $request): array {
        // Validate JSON-RPC structure
        // Route to appropriate handler
        // Format response according to spec
    }
}
```

#### MCP Request Router
- Method-based routing (`tools/call`, `resources/read`, etc.)
- Parameter validation
- Response formatting
- Error handling

### 4. Registry System

The registry system provides automatic discovery and management of MCP components:

#### Component Discovery
```php
class ComponentDiscovery {
    protected array $paths = [
        'tools' => 'app/Mcp/Tools',
        'resources' => 'app/Mcp/Resources',
        'prompts' => 'app/Mcp/Prompts',
    ];
    
    public function discover(): void {
        // Scan directories
        // Load classes
        // Register with appropriate registry
    }
}
```

#### Type-Specific Registries
- `ToolRegistry`: Manages tool components
- `ResourceRegistry`: Manages resource components
- `PromptRegistry`: Manages prompt components

Each registry provides:
- Component registration
- Retrieval by name
- Listing capabilities
- Metadata management

### 5. Base Classes

Abstract classes that developers extend to create MCP components:

#### McpTool
```php
abstract class McpTool {
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getInputSchema(): array;
    abstract public function execute(array $arguments): mixed;
}
```

#### McpResource
```php
abstract class McpResource {
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getUriTemplate(): string;
    abstract public function read(string $uri): array;
    
    public function list(): array { /* Optional */ }
    public function subscribe(string $uri): void { /* Optional */ }
}
```

#### McpPrompt
```php
abstract class McpPrompt {
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getArguments(): array;
    abstract public function generate(array $arguments): array;
}
```

## Command Architecture

The package provides a comprehensive Artisan command system built on a modular architecture with shared traits and base classes.

### Command Hierarchy

```
BaseCommand (Abstract)
├── Uses FormatsOutput trait
├── Uses HandlesConfiguration trait
├── Uses HandlesCommandErrors trait
└── Provides common command functionality

BaseMcpGeneratorCommand (Abstract)
├── Extends BaseCommand
├── Adds generator-specific functionality
└── Used by all make:mcp-* commands

Concrete Commands:
├── ServeCommand extends BaseCommand
├── ListCommand extends BaseCommand
├── RegisterCommand extends BaseCommand
├── DocumentationCommand extends BaseCommand
├── MakeToolCommand extends BaseMcpGeneratorCommand
├── MakeResourceCommand extends BaseMcpGeneratorCommand
└── MakePromptCommand extends BaseMcpGeneratorCommand
```

### Command Traits

The package uses three key traits to provide shared functionality:

#### FormatsOutput Trait
- `formatTable()` - Display data in table format
- `formatJson()` - Output JSON formatted data  
- `formatYaml()` - Output YAML formatted data
- `displayInFormat()` - Dynamic format selection

#### HandlesConfiguration Trait
- `getClientConfigPath()` - Locate client configuration files
- `detectOS()` - Operating system detection
- `getMcpConfig()` - Configuration with environment overrides

#### HandlesCommandErrors Trait
- `handleError()` - Exception handling with debug output
- `validateInput()` - Input validation framework
- `confirmDestructiveAction()` - User confirmation prompts

### Generator Command Pattern

All `make:mcp-*` commands extend `BaseMcpGeneratorCommand`:

```php
abstract class BaseMcpGeneratorCommand extends GeneratorCommand
{
    abstract protected function getStubName(): string;
    abstract protected function getComponentType(): string;
}
```

### Testing Support

Commands are tested using `CommandTestCase` base class:

```php
class MyCommandTest extends CommandTestCase
{
    public function test_command_works(): void
    {
        $this->executeAndAssertSuccess('mcp:list');
        $this->assertOutputContains('Component listing');
    }
}
```

## Design Patterns

### 1. Factory Pattern
Used in `TransportManager` for creating transport instances:
```php
public function create(string $type): TransportInterface {
    return match($type) {
        'http' => new HttpTransport($this->config),
        'stdio' => new StdioTransport($this->config),
        default => throw new InvalidTransportException($type),
    };
}
```

### 2. Registry Pattern
Central registration and retrieval of components:
```php
public function register(string $name, McpTool $tool): void {
    $this->tools[$name] = $tool;
}

public function get(string $name): ?McpTool {
    return $this->tools[$name] ?? null;
}
```

### 3. Strategy Pattern
Different execution strategies for tools, resources, and prompts:
```php
interface ExecutionStrategy {
    public function execute(string $method, array $params): mixed;
}

class ToolExecutionStrategy implements ExecutionStrategy { }
class ResourceExecutionStrategy implements ExecutionStrategy { }
class PromptExecutionStrategy implements ExecutionStrategy { }
```

### 4. Decorator Pattern
Middleware stack for request processing:
```php
class AuthenticationMiddleware {
    public function handle($request, Closure $next) {
        // Authenticate request
        return $next($request);
    }
}
```

### 5. Template Method Pattern
Base classes define the structure, subclasses implement specifics:
```php
abstract class McpTool {
    final public function process(array $arguments): array {
        $this->validate($arguments);
        $result = $this->execute($arguments);
        return $this->format($result);
    }
    
    abstract protected function execute(array $arguments): mixed;
}
```

## Laravel Integration

### 1. Service Container Integration
All core services are registered with Laravel's IoC container:
```php
// Binding interfaces to implementations
$this->app->bind(TransportInterface::class, function ($app, $params) {
    return $app->make(TransportManager::class)->create($params['type']);
});
```

### 2. Configuration Management
Two-tier configuration system:
- `config/laravel-mcp.php`: Main package configuration
- `config/mcp-transports.php`: Transport-specific settings

### 3. Route Registration
Automatic route registration for HTTP transport:
```php
Route::middleware(config('laravel-mcp.middleware'))
    ->prefix(config('laravel-mcp.route_prefix'))
    ->group(function () {
        Route::post('/', [McpController::class, 'handle']);
    });
```

### 4. Event System
Laravel events for component lifecycle:
```php
event(new ComponentRegistered($component));
event(new RequestReceived($request));
event(new ResponseSent($response));
```

### 5. Facade Pattern
Fluent API through Laravel facade:
```php
Mcp::registerTool(new CalculatorTool());
$result = Mcp::callTool('calculator', ['operation' => 'add', 'a' => 5, 'b' => 3]);
```

### 6. Middleware Stack
Leverages Laravel's middleware for:
- Authentication (`auth:api`)
- CORS handling (`cors`)
- Rate limiting (`throttle:api`)
- Request validation
- Response caching

### 7. Validation Integration
Laravel's validation for request parameters:
```php
$validator = Validator::make($arguments, [
    'operation' => 'required|in:add,subtract,multiply,divide',
    'a' => 'required|numeric',
    'b' => 'required|numeric',
]);
```

## Data Flow

### Request Flow
1. AI client sends request (HTTP/Stdio)
2. Transport layer receives and parses request
3. JSON-RPC processor validates structure
4. MCP router determines handler
5. Registry retrieves component
6. Component executes with parameters
7. Response formatted and returned

### Component Registration Flow
1. Service provider boot phase triggers discovery
2. ComponentDiscovery scans configured directories
3. Classes extending base classes are instantiated
4. Components registered with appropriate registry
5. Registry available for request handling

## Performance Considerations

### 1. Lazy Loading
Components are loaded only when needed:
```php
protected function loadComponent(string $class): McpTool {
    if (!isset($this->loaded[$class])) {
        $this->loaded[$class] = new $class();
    }
    return $this->loaded[$class];
}
```

### 2. Caching Strategy
- Component metadata cached
- Configuration cached in production
- Response caching for idempotent operations

### 3. Connection Pooling
For database-backed resources:
```php
class DatabaseResourcePool {
    protected array $connections = [];
    
    public function getConnection(string $name): Connection {
        return $this->connections[$name] ??= DB::connection($name);
    }
}
```

### 4. Async Processing
Queue integration for long-running operations:
```php
class AsyncToolExecutor {
    public function execute(McpTool $tool, array $arguments): string {
        $job = new ExecuteToolJob($tool, $arguments);
        dispatch($job);
        return $job->getId();
    }
}
```

## Security Architecture

### 1. Authentication Layer
Multiple authentication strategies:
- API tokens for HTTP transport
- Process verification for Stdio transport
- OAuth2 integration support

### 2. Authorization
Role-based access control:
```php
class ToolAuthorizationPolicy {
    public function execute(User $user, McpTool $tool): bool {
        return $user->can('execute-tool', $tool);
    }
}
```

### 3. Input Validation
Comprehensive validation at multiple levels:
- Transport layer validation
- JSON-RPC structure validation
- Parameter schema validation
- Business logic validation

### 4. Output Sanitization
Preventing information leakage:
```php
class ResponseSanitizer {
    public function sanitize(array $response): array {
        // Remove sensitive data
        // Apply field-level permissions
        // Format according to client capabilities
    }
}
```

## Extensibility Points

### 1. Custom Transports
Implement `TransportInterface` for new transport types:
```php
class WebSocketTransport implements TransportInterface {
    public function receive(): array { }
    public function send(array $response): void { }
}
```

### 2. Custom Components
Extend base classes for new functionality:
```php
class CustomTool extends McpTool {
    // Implementation
}
```

### 3. Middleware Extensions
Add custom middleware to the stack:
```php
class CustomMiddleware {
    public function handle($request, Closure $next) {
        // Custom processing
        return $next($request);
    }
}
```

### 4. Event Listeners
Hook into component lifecycle:
```php
Event::listen(ComponentRegistered::class, function ($event) {
    // Custom logic
});
```

## Testing Architecture

### 1. Unit Testing
Isolated component testing:
```php
class ToolTest extends TestCase {
    public function test_calculator_addition() {
        $tool = new CalculatorTool();
        $result = $tool->execute(['operation' => 'add', 'a' => 5, 'b' => 3]);
        $this->assertEquals(8, $result);
    }
}
```

### 2. Integration Testing
Full stack testing:
```php
class McpIntegrationTest extends TestCase {
    public function test_http_transport_tool_execution() {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'calculator', 'arguments' => ['operation' => 'add']],
            'id' => 1
        ]);
        
        $response->assertJson(['result' => ['content' => [['text' => '8']]]]);
    }
}
```

### 3. Mocking Strategy
Interface-based mocking:
```php
$mockRegistry = $this->mock(McpRegistry::class);
$mockRegistry->shouldReceive('getTool')
    ->with('calculator')
    ->andReturn(new CalculatorTool());
```

## Deployment Architecture

### 1. Package Distribution
- Composer package via Packagist
- Semantic versioning
- Auto-discovery for Laravel

### 2. Configuration Publishing
```bash
php artisan vendor:publish --tag=laravel-mcp-config
```

### 3. Asset Management
- Configuration files
- Route definitions
- View templates
- Language files

### 4. Environment Support
- Development: Full debugging, verbose logging
- Staging: Performance monitoring, error tracking
- Production: Optimized caching, minimal logging

## Monitoring and Debugging

### 1. Logging Strategy
Structured logging with context:
```php
Log::channel('mcp')->info('Tool executed', [
    'tool' => $toolName,
    'arguments' => $arguments,
    'duration' => $duration,
    'user' => $userId,
]);
```

### 2. Performance Metrics
Track key performance indicators:
- Request/response times
- Component execution duration
- Memory usage
- Cache hit rates

### 3. Debug Mode
Enhanced debugging in development:
```php
if (config('laravel-mcp.debug')) {
    $response['debug'] = [
        'execution_time' => $executionTime,
        'memory_usage' => memory_get_peak_usage(),
        'component_calls' => $this->getComponentCalls(),
    ];
}
```

### 4. Error Tracking
Integration with error tracking services:
```php
try {
    $result = $tool->execute($arguments);
} catch (Exception $e) {
    report($e); // Send to error tracking service
    throw new McpExecutionException($e->getMessage());
}
```

## Best Practices

### 1. Component Design
- Single responsibility principle
- Clear naming conventions
- Comprehensive documentation
- Proper error handling

### 2. Performance
- Lazy load components
- Cache expensive operations
- Use database indexes
- Optimize queries

### 3. Security
- Validate all inputs
- Sanitize outputs
- Use parameterized queries
- Implement rate limiting

### 4. Testing
- High test coverage
- Test edge cases
- Mock external dependencies
- Performance benchmarks

### 5. Documentation
- PHPDoc for all public methods
- Usage examples
- Configuration documentation
- Troubleshooting guides

## Future Considerations

### 1. Scalability
- Horizontal scaling support
- Load balancing strategies
- Distributed caching
- Message queue integration

### 2. Features
- WebSocket transport
- GraphQL support
- Real-time subscriptions
- Plugin system

### 3. Integration
- Additional AI client support
- Third-party service integration
- Webhook support
- API versioning

## Conclusion

The Laravel MCP package architecture is designed to be robust, extensible, and performant while maintaining Laravel's elegant syntax and conventions. The modular design allows developers to easily extend functionality while the comprehensive testing and documentation ensure reliability and maintainability.