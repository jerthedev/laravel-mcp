# Enhanced Laravel MCP Architecture

This document provides a comprehensive overview of the Laravel MCP package architecture, including all enhanced features beyond the original specification.

## Architecture Overview

The Laravel MCP package implements a sophisticated, production-ready architecture that goes beyond the basic MCP 1.0 specification. The architecture is built on Laravel best practices and includes enterprise-grade features for scalability, monitoring, and reliability.

### Core Architectural Layers

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Layer                        │
├─────────────────────────────────────────────────────────────────┤
│                        Facade Layer                            │
│                      (McpManager)                               │
├─────────────────────────────────────────────────────────────────┤
│                        Service Layer                           │
│        (Registry, Server, Events, Jobs, Notifications)         │
├─────────────────────────────────────────────────────────────────┤
│                      Transport Layer                           │
│                   (HTTP, Stdio, WebSocket)                     │
├─────────────────────────────────────────────────────────────────┤
│                      Protocol Layer                            │
│                     (JSON-RPC 2.0, MCP 1.0)                   │
├─────────────────────────────────────────────────────────────────┤
│                    Infrastructure Layer                        │
│              (Laravel Framework, Queue, Events)                │
└─────────────────────────────────────────────────────────────────┘
```

## Enhanced Components

### 1. Central Management Layer (McpManager)

The `McpManager` class serves as the central coordination point for all MCP operations, providing:

#### Core Functionality
- **Component Registration**: Unified registration for Tools, Resources, and Prompts
- **Route-Style Registration**: Laravel-like fluent API for component registration
- **Event Dispatching**: Centralized event management for all MCP operations
- **Async Processing**: Queue-based asynchronous request processing
- **Error Notification**: Centralized error handling and notification system

#### API Surface
```php
// Direct registration
$manager->registerTool('calculator', new CalculatorTool());
$manager->registerResource('users', new UserResource());
$manager->registerPrompt('email', new EmailPrompt());

// Route-style registration
$manager->tool('calculator', CalculatorTool::class);
$manager->resource('users', UserResource::class);
$manager->prompt('email', EmailPrompt::class);

// Group registration with shared attributes
$manager->group(['middleware' => 'auth'], function ($mcp) {
    $mcp->tool('admin-tool', AdminTool::class);
    $mcp->resource('admin-users', AdminUserResource::class);
});

// Async processing
$requestId = $manager->dispatchAsync('tools/call', ['tool' => 'calculator', 'params' => []]);
$result = $manager->getAsyncResult($requestId);

// Event dispatching
$manager->dispatchComponentRegistered('tool', 'calculator', $tool);
$manager->dispatchRequestProcessed($id, 'tools/call', $params, $result, $time);

// Error notifications
$manager->notifyError('validation_error', 'Invalid parameters', 'tools/call', $params);
```

### 2. Event-Driven Architecture

The package implements a comprehensive event system for extensibility and monitoring.

#### Component Registration Events
```php
McpComponentRegistered::class     # Fired when any component is registered
  - string $type                  # 'tool', 'resource', or 'prompt'
  - string $name                  # Component name
  - mixed $component              # Component instance
  - array $metadata               # Registration metadata
```

#### Operation Events  
```php
McpToolExecuted::class           # Tool execution event
  - string $toolName
  - array $parameters
  - mixed $result
  - float $executionTime
  
McpResourceAccessed::class       # Resource access event
  - string $resourceName
  - array $filters
  - mixed $data
  - float $executionTime
  
McpPromptGenerated::class        # Prompt generation event
  - string $promptName
  - array $variables
  - string $generatedPrompt
  - float $executionTime

McpRequestProcessed::class       # Request processing event
  - string|int $requestId
  - string $method
  - array $parameters
  - mixed $result
  - float $executionTime
  - string $transport
  - array $context
```

#### Notification Events
```php
NotificationQueued::class        # Notification queued for delivery
NotificationSent::class          # Notification sent successfully
NotificationDelivered::class     # Notification delivered to recipient
NotificationFailed::class        # Notification delivery failed
NotificationBroadcast::class     # Notification broadcast to multiple channels
```

#### Event Listeners
Automatic event handling for production monitoring:

```php
LogMcpActivity::class                   # Logs all MCP activity
LogMcpComponentRegistration::class      # Logs component registrations
TrackMcpRequestMetrics::class          # Tracks request performance metrics
TrackMcpUsage::class                   # Tracks usage statistics and quotas
```

### 3. Asynchronous Processing System

Built on Laravel's queue system for scalable async operations.

#### Job Classes
```php
ProcessMcpRequest::class                # Processes MCP requests asynchronously
  - Handles tool calls, resource access, prompt generation
  - Implements retry logic and error handling
  - Stores results in cache for retrieval
  
ProcessNotificationDelivery::class     # Handles notification delivery
  - Supports multiple delivery channels
  - Implements delivery confirmation
  - Handles failed delivery scenarios
```

#### Usage Examples
```php
// Dispatch async tool call
$requestId = app(McpManager::class)->dispatchAsync('tools/call', [
    'name' => 'long-running-tool',
    'arguments' => ['param1' => 'value1']
]);

// Check status and retrieve result
$status = app(McpManager::class)->getAsyncStatus($requestId);
if ($status['status'] === 'completed') {
    $result = app(McpManager::class)->getAsyncResult($requestId);
}
```

#### Configuration
```php
// config/laravel-mcp.php
'queue' => [
    'enabled' => true,
    'default' => 'mcp',
    'timeout' => 300,
    'retry_after' => 90,
    'max_retries' => 3,
],
```

### 4. Enhanced Middleware Stack

Production-ready middleware for security, monitoring, and reliability.

#### Core Middleware
```php
McpAuthMiddleware::class               # Authentication and authorization
  - Supports multiple auth strategies
  - Token-based and session-based auth
  - Configurable auth providers

McpCorsMiddleware::class               # CORS handling for web clients
  - Configurable origins and methods
  - Supports preflight requests
  - Security header management

McpValidationMiddleware::class         # Request validation
  - JSON schema validation
  - Parameter type checking
  - Required field validation

McpRateLimitMiddleware::class          # Rate limiting protection
  - Per-client rate limiting
  - Configurable limits and windows
  - Redis-based distributed limiting

McpErrorHandlingMiddleware::class      # Centralized error handling
  - Structured error responses
  - Error logging and monitoring
  - Security-aware error messages

McpLoggingMiddleware::class            # Request/response logging
  - Structured logging format
  - Performance metrics capture
  - Configurable log levels
```

#### Specialized Middleware
```php
HandleSseRequest::class                # Server-sent events support
  - Streaming response handling
  - Connection management
  - Event stream formatting
```

#### Middleware Configuration
```php
// config/laravel-mcp.php
'middleware' => [
    'global' => [
        'mcp.cors',
        'mcp.auth',
        'mcp.rate-limit',
        'mcp.validation',
        'mcp.logging',
        'mcp.error-handling',
    ],
    'groups' => [
        'api' => ['mcp.cors', 'mcp.auth', 'mcp.rate-limit'],
        'stdio' => ['mcp.auth', 'mcp.validation'],
    ],
],
```

### 5. Server Layer Architecture

Complete MCP server implementation with specialized request handlers.

#### Core Server Components
```php
McpServer::class                       # Main server implementation
  - Transport-agnostic server logic
  - Capability negotiation
  - Request routing and processing
  
CapabilityManager::class               # Server capability management
  - Dynamic capability registration
  - Client capability negotiation
  - Feature flag support

ServerInfo::class                      # Server information and metadata
  - Version information
  - Capability reporting
  - Performance statistics
```

#### Specialized Request Handlers
```php
BaseHandler::class                     # Base handler with common functionality
  - Request validation
  - Response formatting
  - Error handling
  - Performance monitoring

ToolHandler::class                     # Tool request processing
  - Tool discovery and listing
  - Tool execution with parameter validation
  - Result formatting and caching
  
ResourceHandler::class                 # Resource request processing
  - Resource discovery and listing
  - Data fetching with filtering
  - Pagination and sorting support
  
PromptHandler::class                   # Prompt request processing
  - Prompt template resolution
  - Variable substitution
  - Generated prompt caching
```

#### Handler Pipeline
```
Request → Authentication → Validation → Handler → Response
    ↓           ↓             ↓          ↓         ↓
  Events    Error Log    Validation   Business   Format
            Metrics      Cache        Logic      Response
```

### 6. Transport Layer Enhancements

Extended transport implementations with production features.

#### Base Transport Architecture
```php
BaseTransport::class                   # Common transport functionality
  - Connection management
  - Message serialization/deserialization
  - Error handling
  - Performance monitoring

TransportInterface::class              # Transport contract
  - Standardized transport API
  - Connection lifecycle methods
  - Message handling interface
```

#### Transport Implementations
```php
HttpTransport::class                   # HTTP/HTTPS transport
  - RESTful API endpoints
  - WebSocket upgrade support
  - Middleware pipeline integration
  - CORS and security headers

StdioTransport::class                  # Standard I/O transport
  - Desktop client communication
  - Message framing
  - Stream multiplexing
  - Connection persistence
```

#### Transport Utilities
```php
MessageFramer::class                   # Message framing for stdio
  - Length-prefixed framing
  - JSON-RPC message boundaries
  - Stream buffering

StreamHandler::class                   # Stream handling utilities
  - Non-blocking I/O
  - Buffer management
  - Connection state tracking
```

### 7. Advanced Support System

Production utilities for monitoring, documentation, and client integration.

#### Client Configuration Generators
```php
ClaudeDesktopGenerator::class          # Claude Desktop configuration
  - JSON config generation
  - Path resolution
  - Command configuration

ClaudeCodeGenerator::class             # Claude Code configuration
  - MCP server registration
  - Tool and resource definitions
  - Authentication setup

ChatGptGenerator::class                # ChatGPT Desktop configuration
  - Plugin manifest generation
  - API endpoint configuration
  - Authentication flow setup
```

#### Documentation and Monitoring
```php
AdvancedDocumentationGenerator::class  # Advanced documentation generation
  - Interactive API documentation
  - Code examples generation
  - Schema documentation
  - Usage analytics

PerformanceMonitor::class              # Performance monitoring
  - Request latency tracking
  - Memory usage monitoring
  - Error rate calculation
  - Performance alerts

Debugger::class                        # Debug utilities
  - Request/response inspection
  - Component state debugging
  - Performance profiling
  - Error analysis
```

#### Utility Classes
```php
ClientDetector::class                  # Client type detection
  - User-agent parsing
  - Client capability detection
  - Version compatibility checking

ExampleCompiler::class                 # Example compilation
  - Code example generation
  - Usage pattern extraction
  - Test case generation

ExtensionGuideBuilder::class           # Extension guide generation
  - Custom component guides
  - Best practices documentation
  - Integration examples

SchemaDocumenter::class                # Schema documentation
  - JSON schema documentation
  - API endpoint documentation
  - Parameter validation docs
```

## Configuration Architecture

### Two-Tier Configuration System

#### Main Configuration (`config/laravel-mcp.php`)
```php
return [
    'server' => [
        'name' => 'Laravel MCP Server',
        'version' => '1.0.0',
    ],
    
    'discovery' => [
        'paths' => [
            'tools' => app_path('Mcp/Tools'),
            'resources' => app_path('Mcp/Resources'),
            'prompts' => app_path('Mcp/Prompts'),
        ],
        'auto_register' => true,
    ],
    
    'events' => [
        'enabled' => true,
        'listeners' => [
            'activity' => true,
            'metrics' => true,
            'registration' => true,
        ],
    ],
    
    'queue' => [
        'enabled' => true,
        'default' => 'mcp',
        'timeout' => 300,
    ],
    
    'notifications' => [
        'enabled' => true,
        'channels' => ['mail', 'slack'],
        'admin_email' => env('MCP_ADMIN_EMAIL'),
    ],
];
```

#### Transport Configuration (`config/mcp-transports.php`)
```php
return [
    'default' => 'stdio',
    
    'transports' => [
        'stdio' => [
            'driver' => 'stdio',
            'buffer_size' => 8192,
            'timeout' => 30,
        ],
        
        'http' => [
            'driver' => 'http',
            'host' => '127.0.0.1',
            'port' => 3000,
            'middleware' => ['mcp.cors', 'mcp.auth'],
        ],
    ],
];
```

## Integration Patterns

### Laravel Framework Integration

#### Service Provider Registration
```php
class LaravelMcpServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Core service bindings
        $this->app->singleton(McpRegistry::class);
        $this->app->singleton(McpManager::class);
        $this->app->singleton(TransportManager::class);
        
        // Event listeners
        $this->registerEventListeners();
        
        // Queue job bindings
        $this->registerJobBindings();
    }
    
    public function boot()
    {
        // Component discovery
        $this->bootComponentDiscovery();
        
        // Middleware registration
        $this->bootMiddleware();
        
        // Route registration
        $this->bootRoutes();
        
        // Command registration
        $this->bootCommands();
    }
}
```

#### Event System Integration
```php
// Event listener registration
Event::listen(McpComponentRegistered::class, [
    LogMcpComponentRegistration::class,
    TrackMcpUsage::class,
]);

Event::listen(McpRequestProcessed::class, [
    LogMcpActivity::class,
    TrackMcpRequestMetrics::class,
]);
```

#### Queue Integration
```php
// Job processing
Queue::failing(function (JobFailed $event) {
    if ($event->job->payload()['displayName'] === ProcessMcpRequest::class) {
        app(McpManager::class)->notifyError(
            'job_failed',
            $event->exception->getMessage(),
            $event->job->payload()['data']['method'] ?? null,
            $event->job->payload()['data']['parameters'] ?? []
        );
    }
});
```

## Performance Considerations

### Caching Strategy
- **Component Registration**: Cached for fast lookup
- **Schema Validation**: Compiled schemas cached
- **Generated Documentation**: Cached with invalidation
- **Async Results**: Redis-backed result storage

### Memory Management
- **Lazy Loading**: Components loaded on-demand
- **Memory Monitoring**: Built-in memory usage tracking
- **Garbage Collection**: Automatic cleanup of expired data

### Scalability Features
- **Horizontal Scaling**: Stateless design for multiple instances
- **Load Balancing**: Transport-agnostic server design
- **Database Sharding**: Support for distributed registries
- **CDN Integration**: Static asset optimization

## Security Architecture

### Multi-Layer Security
1. **Transport Security**: TLS/SSL for HTTP, secure pipes for stdio
2. **Authentication**: Multiple auth providers with token validation
3. **Authorization**: Role-based access control for components
4. **Input Validation**: Schema-based validation with sanitization
5. **Rate Limiting**: Distributed rate limiting with Redis
6. **Error Handling**: Secure error messages without information leakage
7. **Audit Logging**: Complete audit trail of all operations

### Security Configuration
```php
'security' => [
    'authentication' => [
        'enabled' => true,
        'providers' => ['token', 'session'],
        'token_lifetime' => 3600,
    ],
    
    'authorization' => [
        'enabled' => true,
        'default_policy' => 'deny',
        'role_mapping' => [
            'admin' => ['*'],
            'user' => ['tools:calculator', 'resources:users'],
        ],
    ],
    
    'rate_limiting' => [
        'enabled' => true,
        'default_limit' => 100,
        'window' => 60,
    ],
],
```

This enhanced architecture provides a production-ready, scalable, and maintainable foundation for MCP server implementations in Laravel applications, going far beyond the basic MCP 1.0 specification while maintaining full compatibility.