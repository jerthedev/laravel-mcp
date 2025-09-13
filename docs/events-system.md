# Events System

The Laravel MCP package implements a comprehensive event-driven architecture that enables extensibility, monitoring, and integration with external systems. This document provides detailed information about the events system and how to use it effectively.

## Overview

The events system is built on Laravel's native event system and provides:

- **Component Lifecycle Events**: Track registration, execution, and access of MCP components
- **Request Processing Events**: Monitor MCP request processing and performance
- **Notification Events**: Handle notification delivery lifecycle
- **Extensibility Points**: Custom event handling and business logic integration
- **Monitoring Integration**: Built-in metrics and logging capabilities

## Event Categories

### 1. Component Registration Events

#### McpComponentRegistered

Fired when any MCP component (Tool, Resource, or Prompt) is registered.

```php
use JTD\LaravelMCP\Events\McpComponentRegistered;

class McpComponentRegistered
{
    public string $type;        // 'tool', 'resource', or 'prompt'
    public string $name;        // Component name
    public mixed $component;    // Component instance
    public array $metadata;     // Registration metadata
    public DateTime $timestamp; // Registration timestamp
}
```

**Usage Example:**
```php
Event::listen(McpComponentRegistered::class, function (McpComponentRegistered $event) {
    Log::info("MCP component registered", [
        'type' => $event->type,
        'name' => $event->name,
        'class' => get_class($event->component),
        'metadata' => $event->metadata,
    ]);
});
```

### 2. Component Operation Events

#### McpToolExecuted

Fired when a tool is executed.

```php
use JTD\LaravelMCP\Events\McpToolExecuted;

class McpToolExecuted
{
    public string $toolName;        // Tool name
    public array $parameters;       // Input parameters
    public mixed $result;          // Execution result
    public float $executionTime;   // Execution time in milliseconds
    public array $context;         // Additional context
    public DateTime $timestamp;    // Execution timestamp
}
```

#### McpResourceAccessed

Fired when a resource is accessed.

```php
use JTD\LaravelMCP\Events\McpResourceAccessed;

class McpResourceAccessed  
{
    public string $resourceName;    // Resource name
    public array $filters;         // Applied filters
    public mixed $data;            // Returned data
    public float $executionTime;   // Access time in milliseconds
    public array $context;         // Additional context
    public DateTime $timestamp;    // Access timestamp
}
```

#### McpPromptGenerated

Fired when a prompt is generated.

```php
use JTD\LaravelMCP\Events\McpPromptGenerated;

class McpPromptGenerated
{
    public string $promptName;      // Prompt name
    public array $variables;       // Input variables
    public string $generatedPrompt; // Generated prompt text
    public float $executionTime;   // Generation time in milliseconds
    public array $context;         // Additional context
    public DateTime $timestamp;    // Generation timestamp
}
```

### 3. Request Processing Events

#### McpRequestProcessed

Fired when an MCP request is processed (covers all request types).

```php
use JTD\LaravelMCP\Events\McpRequestProcessed;

class McpRequestProcessed
{
    public string|int $requestId;   // Unique request ID
    public string $method;         // MCP method called
    public array $parameters;      // Request parameters
    public mixed $result;          // Request result
    public float $executionTime;   // Processing time in milliseconds
    public string $transport;      // Transport used ('http', 'stdio')
    public array $context;         // Additional context
    public DateTime $timestamp;    // Processing timestamp
}
```

### 4. Notification Events

#### NotificationQueued

Fired when a notification is queued for delivery.

```php
use JTD\LaravelMCP\Events\NotificationQueued;

class NotificationQueued
{
    public string $notificationId;    // Unique notification ID
    public string $type;             // Notification type
    public mixed $notifiable;        // Target recipient
    public array $channels;          // Delivery channels
    public array $data;             // Notification data
    public DateTime $timestamp;     // Queue timestamp
}
```

#### NotificationSent

Fired when a notification is sent to a channel.

```php
use JTD\LaravelMCP\Events\NotificationSent;

class NotificationSent
{
    public string $notificationId;   // Unique notification ID
    public string $channel;         // Delivery channel
    public mixed $notifiable;       // Recipient
    public array $response;         // Channel response data
    public DateTime $timestamp;     // Send timestamp
}
```

#### NotificationDelivered

Fired when a notification is successfully delivered.

```php
use JTD\LaravelMCP\Events\NotificationDelivered;

class NotificationDelivered
{
    public string $notificationId;   // Unique notification ID
    public string $channel;         // Delivery channel
    public mixed $notifiable;       // Recipient
    public array $deliveryData;     // Delivery confirmation data
    public DateTime $timestamp;     // Delivery timestamp
}
```

#### NotificationFailed

Fired when a notification delivery fails.

```php
use JTD\LaravelMCP\Events\NotificationFailed;

class NotificationFailed
{
    public string $notificationId;   // Unique notification ID
    public string $channel;         // Failed channel
    public mixed $notifiable;       // Intended recipient
    public Exception $exception;    // Failure exception
    public array $context;          // Failure context
    public DateTime $timestamp;     // Failure timestamp
}
```

#### NotificationBroadcast

Fired when a notification is broadcast to multiple recipients.

```php
use JTD\LaravelMCP\Events\NotificationBroadcast;

class NotificationBroadcast
{
    public string $broadcastId;      // Unique broadcast ID
    public array $recipients;       // List of recipients
    public array $channels;         // Broadcast channels
    public array $data;            // Broadcast data
    public DateTime $timestamp;    // Broadcast timestamp
}
```

## Built-in Event Listeners

The package includes several built-in event listeners for common operations:

### LogMcpActivity

Logs all MCP activity for audit and debugging purposes.

```php
use JTD\LaravelMCP\Listeners\LogMcpActivity;

class LogMcpActivity
{
    public function handle(McpRequestProcessed $event): void
    {
        Log::channel('mcp-activity')->info('MCP request processed', [
            'request_id' => $event->requestId,
            'method' => $event->method,
            'execution_time' => $event->executionTime,
            'transport' => $event->transport,
            'success' => $event->result !== null,
        ]);
    }
}
```

### LogMcpComponentRegistration

Logs component registrations for tracking and debugging.

```php
use JTD\LaravelMCP\Listeners\LogMcpComponentRegistration;

class LogMcpComponentRegistration
{
    public function handle(McpComponentRegistered $event): void
    {
        Log::channel('mcp-registry')->info('MCP component registered', [
            'type' => $event->type,
            'name' => $event->name,
            'class' => get_class($event->component),
            'metadata' => $event->metadata,
        ]);
    }
}
```

### TrackMcpRequestMetrics

Tracks performance metrics for monitoring and optimization.

```php
use JTD\LaravelMCP\Listeners\TrackMcpRequestMetrics;

class TrackMcpRequestMetrics
{
    public function handle(McpRequestProcessed $event): void
    {
        // Update performance metrics
        Cache::increment('mcp:stats:requests_processed');
        
        // Track average response time
        $currentAvg = Cache::get('mcp:stats:avg_response_time', 0);
        $newAvg = ($currentAvg + $event->executionTime) / 2;
        Cache::put('mcp:stats:avg_response_time', $newAvg, 3600);
        
        // Track method-specific metrics
        Cache::increment("mcp:stats:method:{$event->method}:count");
        Cache::put("mcp:stats:method:{$event->method}:last_time", $event->executionTime, 3600);
    }
}
```

### TrackMcpUsage

Tracks usage statistics and quotas.

```php
use JTD\LaravelMCP\Listeners\TrackMcpUsage;

class TrackMcpUsage
{
    public function handle(McpComponentRegistered $event): void
    {
        Cache::increment("mcp:usage:{$event->type}:total");
        Cache::put("mcp:usage:{$event->type}:last_registered", $event->name, 86400);
    }
}
```

## Custom Event Listeners

You can create custom event listeners to extend the functionality:

### Example: Custom Metric Tracking

```php
<?php

namespace App\Listeners;

use JTD\LaravelMCP\Events\McpToolExecuted;
use Illuminate\Support\Facades\Cache;

class TrackToolPerformance
{
    public function handle(McpToolExecuted $event): void
    {
        $key = "tool_performance:{$event->toolName}";
        
        // Track execution count
        Cache::increment("{$key}:count");
        
        // Track execution time statistics
        $times = Cache::get("{$key}:times", []);
        $times[] = $event->executionTime;
        
        // Keep only last 100 execution times
        if (count($times) > 100) {
            $times = array_slice($times, -100);
        }
        
        Cache::put("{$key}:times", $times, 3600);
        
        // Calculate and store statistics
        Cache::put("{$key}:avg_time", array_sum($times) / count($times), 3600);
        Cache::put("{$key}:min_time", min($times), 3600);
        Cache::put("{$key}:max_time", max($times), 3600);
    }
}
```

### Example: External System Integration

```php
<?php

namespace App\Listeners;

use JTD\LaravelMCP\Events\McpRequestProcessed;
use Illuminate\Http\Client\Factory as HttpClient;

class SendMetricsToExternal
{
    public function __construct(
        private HttpClient $http
    ) {}
    
    public function handle(McpRequestProcessed $event): void
    {
        // Send metrics to external monitoring system
        $this->http->post('https://metrics.example.com/api/mcp', [
            'request_id' => $event->requestId,
            'method' => $event->method,
            'execution_time' => $event->executionTime,
            'transport' => $event->transport,
            'timestamp' => $event->timestamp->toISOString(),
            'success' => $event->result !== null,
        ]);
    }
    
    public function shouldQueue(): bool
    {
        return true; // Process asynchronously
    }
}
```

## Event Listener Registration

### Service Provider Registration

```php
<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use JTD\LaravelMCP\Events\McpToolExecuted;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use App\Listeners\TrackToolPerformance;
use App\Listeners\SendMetricsToExternal;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        McpToolExecuted::class => [
            TrackToolPerformance::class,
        ],
        
        McpRequestProcessed::class => [
            SendMetricsToExternal::class,
        ],
    ];
}
```

### Runtime Registration

```php
use Illuminate\Support\Facades\Event;
use JTD\LaravelMCP\Events\McpComponentRegistered;

Event::listen(McpComponentRegistered::class, function ($event) {
    // Handle the event inline
    if ($event->type === 'tool') {
        // Specific handling for tool registration
    }
});
```

### Closure-Based Listeners

```php
Event::listen('mcp.*', function (string $eventName, array $data) {
    // Handle all MCP events with a single listener
    Log::debug("MCP event fired: {$eventName}", $data);
});
```

## Event Configuration

Configure event behavior in your `config/laravel-mcp.php`:

```php
'events' => [
    'enabled' => true,
    
    'listeners' => [
        'activity' => true,        // Enable activity logging
        'metrics' => true,         // Enable metrics tracking
        'registration' => true,    // Enable registration logging
    ],
    
    'async' => [
        'enabled' => true,         // Enable async event processing
        'queue' => 'events',       // Queue for async events
    ],
    
    'filtering' => [
        'sensitive_parameters' => [ // Parameters to filter from events
            'password',
            'token',
            'api_key',
        ],
    ],
],
```

## Event Testing

### Unit Testing Events

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use JTD\LaravelMCP\Events\McpToolExecuted;
use App\Mcp\Tools\CalculatorTool;

class McpEventTest extends TestCase
{
    public function test_tool_executed_event_is_fired(): void
    {
        Event::fake();
        
        $tool = new CalculatorTool();
        $result = $tool->execute(['operation' => 'add', 'a' => 5, 'b' => 3]);
        
        Event::assertDispatched(McpToolExecuted::class, function ($event) {
            return $event->toolName === 'calculator' &&
                   $event->result === 8;
        });
    }
    
    public function test_event_listener_processes_correctly(): void
    {
        $listener = new TrackToolPerformance();
        $event = new McpToolExecuted(
            toolName: 'test-tool',
            parameters: ['param' => 'value'],
            result: 'success',
            executionTime: 150.5,
            context: [],
            timestamp: now()
        );
        
        $listener->handle($event);
        
        $this->assertEquals(1, Cache::get('tool_performance:test-tool:count'));
        $this->assertEquals(150.5, Cache::get('tool_performance:test-tool:avg_time'));
    }
}
```

### Integration Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Event;
use JTD\LaravelMCP\Facades\Mcp;

class McpEventIntegrationTest extends TestCase
{
    public function test_complete_tool_execution_flow_fires_events(): void
    {
        Event::fake();
        
        // Register and execute a tool
        Mcp::registerTool('test-tool', new TestTool());
        $result = app('mcp.server')->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => [
                'name' => 'test-tool',
                'arguments' => ['input' => 'test']
            ]
        ]);
        
        // Assert all expected events were fired
        Event::assertDispatched(McpComponentRegistered::class);
        Event::assertDispatched(McpToolExecuted::class);
        Event::assertDispatched(McpRequestProcessed::class);
    }
}
```

## Performance Considerations

### Event Processing Overhead

- **Synchronous Events**: Processed inline with the request
- **Asynchronous Events**: Queued for background processing
- **Event Filtering**: Only fire events when listeners are registered
- **Memory Management**: Events are garbage collected after processing

### Optimization Strategies

```php
// Conditionally fire events
if (Event::hasListeners(McpToolExecuted::class)) {
    event(new McpToolExecuted(/* ... */));
}

// Use queued listeners for expensive operations
class ExpensiveListener implements ShouldQueue
{
    use Queueable;
    
    public function handle(McpRequestProcessed $event): void
    {
        // Expensive processing
    }
}

// Batch similar events
class BatchMetricsListener
{
    private array $batch = [];
    
    public function handle(McpRequestProcessed $event): void
    {
        $this->batch[] = $event;
        
        if (count($this->batch) >= 10) {
            $this->processBatch();
            $this->batch = [];
        }
    }
}
```

## Security Considerations

### Sensitive Data Filtering

```php
// Automatic filtering of sensitive parameters
class McpToolExecuted
{
    public function __construct(
        public string $toolName,
        public array $parameters,
        public mixed $result,
        // ...
    ) {
        $this->parameters = $this->filterSensitiveData($parameters);
    }
    
    private function filterSensitiveData(array $data): array
    {
        $sensitiveKeys = config('laravel-mcp.events.filtering.sensitive_parameters', []);
        
        return collect($data)->except($sensitiveKeys)->toArray();
    }
}
```

### Event Data Validation

```php
class SecureEventListener
{
    public function handle(McpRequestProcessed $event): void
    {
        // Validate event data before processing
        if ($this->isValidEvent($event)) {
            $this->processEvent($event);
        }
    }
    
    private function isValidEvent(McpRequestProcessed $event): bool
    {
        return !empty($event->requestId) &&
               !empty($event->method) &&
               is_numeric($event->executionTime);
    }
}
```

The events system provides a powerful foundation for extending the Laravel MCP package with custom functionality, monitoring, and integration capabilities while maintaining security and performance standards.