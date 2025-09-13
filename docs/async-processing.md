# Asynchronous Processing

The Laravel MCP package provides comprehensive asynchronous processing capabilities built on Laravel's queue system. This enables scalable, non-blocking operations for MCP requests while maintaining reliability and observability.

## Overview

Async processing is essential for:

- **Long-running Operations**: Tools or resources that take significant time to process
- **High-throughput Scenarios**: Handling many requests without blocking
- **Resource-intensive Tasks**: CPU or memory-intensive operations
- **External API Calls**: Network requests that may have variable response times
- **Background Processing**: Operations that don't require immediate response

## Core Components

### 1. Job Classes

#### ProcessMcpRequest

The primary job class for processing MCP requests asynchronously.

```php
<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelMCP\McpManager;

class ProcessMcpRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public string $requestId;
    public int $tries = 3;
    public int $timeout = 300;
    
    public function __construct(
        public string $method,
        public array $parameters,
        public ?string $transport = null,
        public array $context = []
    ) {
        $this->requestId = uniqid('mcp_async_', true);
    }
    
    public function handle(McpManager $manager): void
    {
        $startTime = microtime(true);
        
        try {
            // Process the MCP request
            $result = $this->processRequest($manager);
            
            // Store result for retrieval
            $this->storeResult($result, 'completed');
            
            // Dispatch success event
            $executionTime = (microtime(true) - $startTime) * 1000;
            $manager->dispatchRequestProcessed(
                $this->requestId,
                $this->method,
                $this->parameters,
                $result,
                $executionTime,
                $this->transport ?? 'async',
                $this->context
            );
            
        } catch (Exception $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }
    
    public function failed(Exception $exception): void
    {
        $this->storeResult([
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ], 'failed');
        
        // Notify error
        app(McpManager::class)->notifyError(
            'async_job_failed',
            $exception->getMessage(),
            $this->method,
            $this->parameters,
            $exception
        );
    }
}
```

#### ProcessNotificationDelivery

Handles asynchronous notification delivery.

```php
<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelMCP\Events\NotificationDelivered;
use JTD\LaravelMCP\Events\NotificationFailed;

class ProcessNotificationDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $backoff = 60;
    
    public function __construct(
        public string $notificationId,
        public string $channel,
        public mixed $notifiable,
        public array $data
    ) {}
    
    public function handle(): void
    {
        try {
            $result = $this->deliverNotification();
            
            event(new NotificationDelivered(
                $this->notificationId,
                $this->channel,
                $this->notifiable,
                $result,
                now()
            ));
            
        } catch (Exception $e) {
            event(new NotificationFailed(
                $this->notificationId,
                $this->channel,
                $this->notifiable,
                $e,
                ['attempt' => $this->attempts()],
                now()
            ));
            
            throw $e;
        }
    }
}
```

### 2. McpManager Async Methods

The `McpManager` provides convenient methods for async operations:

#### Dispatching Async Requests

```php
use JTD\LaravelMCP\Facades\Mcp;

// Dispatch a tool call asynchronously
$requestId = Mcp::dispatchAsync('tools/call', [
    'name' => 'data-processor',
    'arguments' => [
        'dataset' => 'large-dataset.csv',
        'operation' => 'analyze'
    ]
]);

// Dispatch with custom context
$requestId = Mcp::dispatchAsync('resources/list', [
    'uri' => 'database://users',
    'filters' => ['status' => 'active']
], [
    'client_id' => 'admin-dashboard',
    'priority' => 'high'
]);
```

#### Checking Request Status

```php
// Get request status
$status = Mcp::getAsyncStatus($requestId);
/*
[
    'status' => 'processing', // 'queued', 'processing', 'completed', 'failed'
    'progress' => 0.65,      // Optional progress indicator (0.0 - 1.0)
    'message' => 'Processing dataset...',
    'started_at' => '2024-01-15T10:30:00Z',
    'estimated_completion' => '2024-01-15T10:45:00Z'
]
*/

// Get request result (null if not completed)
$result = Mcp::getAsyncResult($requestId);
```

#### Polling for Results

```php
function waitForResult(string $requestId, int $timeout = 300): mixed
{
    $start = time();
    
    while (time() - $start < $timeout) {
        $status = Mcp::getAsyncStatus($requestId);
        
        if ($status['status'] === 'completed') {
            return Mcp::getAsyncResult($requestId);
        }
        
        if ($status['status'] === 'failed') {
            throw new RuntimeException('Async request failed: ' . $status['error']);
        }
        
        sleep(1); // Poll every second
    }
    
    throw new TimeoutException('Async request timed out');
}
```

## Configuration

### Queue Configuration

Configure async processing in `config/laravel-mcp.php`:

```php
'queue' => [
    'enabled' => true,
    'default' => 'mcp',           // Default queue for MCP jobs
    'timeout' => 300,             // Job timeout in seconds
    'retry_after' => 90,          // Retry failed jobs after seconds
    'max_retries' => 3,           // Maximum retry attempts
    'backoff' => [60, 120, 300],  // Backoff strategy for retries
],

'async' => [
    'result_ttl' => 3600,         // Result cache TTL in seconds
    'status_ttl' => 7200,         // Status cache TTL in seconds
    'cleanup_interval' => 1800,   // Cleanup old results interval
    
    'priority' => [
        'default' => 0,
        'low' => -10,
        'high' => 10,
        'urgent' => 20,
    ],
],
```

### Laravel Queue Configuration

Ensure your `config/queue.php` includes MCP queue configuration:

```php
'connections' => [
    'mcp' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('MCP_QUEUE', 'mcp'),
        'retry_after' => 90,
        'block_for' => null,
        'after_commit' => false,
    ],
],
```

## Advanced Usage Patterns

### 1. Batch Processing

Process multiple requests as a batch:

```php
use Illuminate\Bus\Batch;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;

$jobs = collect([
    ['method' => 'tools/call', 'params' => ['name' => 'tool1', 'args' => []]],
    ['method' => 'tools/call', 'params' => ['name' => 'tool2', 'args' => []]],
    ['method' => 'tools/call', 'params' => ['name' => 'tool3', 'args' => []]],
])->map(fn($job) => new ProcessMcpRequest($job['method'], $job['params']));

Bus::batch($jobs)->then(function (Batch $batch) {
    // All jobs completed successfully
    Log::info('Batch processing completed', ['batch_id' => $batch->id]);
})->catch(function (Batch $batch, Throwable $e) {
    // First batch job failure
    Log::error('Batch processing failed', [
        'batch_id' => $batch->id,
        'error' => $e->getMessage()
    ]);
})->finally(function (Batch $batch) {
    // The batch has finished executing
    Cache::forget("batch_status:{$batch->id}");
})->dispatch();
```

### 2. Chain Processing

Chain dependent async operations:

```php
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;

// First job: Data extraction
$extractJob = new ProcessMcpRequest('tools/call', [
    'name' => 'data-extractor',
    'arguments' => ['source' => 'api']
]);

// Second job: Data processing (depends on first)
$processJob = new ProcessMcpRequest('tools/call', [
    'name' => 'data-processor', 
    'arguments' => ['data' => '{{RESULT_FROM_PREVIOUS}}']
]);

// Third job: Data export (depends on second)
$exportJob = new ProcessMcpRequest('tools/call', [
    'name' => 'data-exporter',
    'arguments' => ['format' => 'csv']
]);

// Chain the jobs
$extractJob->chain([$processJob, $exportJob]);
dispatch($extractJob);
```

### 3. Priority-based Processing

```php
// High priority request
$urgentRequestId = Mcp::dispatchAsync('tools/call', $params, [
    'priority' => 'urgent'
]);

// Low priority request  
$backgroundRequestId = Mcp::dispatchAsync('resources/list', $params, [
    'priority' => 'low'
]);
```

### 4. Progress Tracking

Implement progress tracking in your MCP tools:

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\Cache;

class DataProcessorTool extends McpTool
{
    public function execute(array $parameters): mixed
    {
        $requestId = $parameters['_async_request_id'] ?? null;
        $dataset = $parameters['dataset'];
        $totalItems = count($dataset);
        
        $results = [];
        
        foreach ($dataset as $index => $item) {
            // Process item
            $results[] = $this->processItem($item);
            
            // Update progress if async
            if ($requestId) {
                $progress = ($index + 1) / $totalItems;
                $this->updateProgress($requestId, $progress, "Processing item " . ($index + 1) . " of {$totalItems}");
            }
        }
        
        return $results;
    }
    
    private function updateProgress(string $requestId, float $progress, string $message): void
    {
        Cache::put("mcp:async:status:{$requestId}", [
            'status' => 'processing',
            'progress' => $progress,
            'message' => $message,
            'updated_at' => now()->toISOString()
        ], 3600);
    }
}
```

## Error Handling and Reliability

### 1. Retry Logic

```php
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;

class ProcessMcpRequest implements ShouldQueue
{
    public int $tries = 3;
    public array $backoff = [60, 120, 300];
    
    public function retryUntil(): DateTime
    {
        return now()->addMinutes(30);
    }
    
    public function failed(Exception $exception): void
    {
        // Custom failure handling
        if ($exception instanceof ValidationException) {
            // Don't retry validation errors
            $this->delete();
        }
        
        // Log failure
        Log::error('MCP async job failed', [
            'request_id' => $this->requestId,
            'method' => $this->method,
            'attempt' => $this->attempts(),
            'error' => $exception->getMessage()
        ]);
        
        // Store failure result
        Cache::put("mcp:async:result:{$this->requestId}", [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString()
        ], 3600);
    }
}
```

### 2. Circuit Breaker Pattern

```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Exception;

class AsyncCircuitBreaker
{
    private string $key;
    private int $failureThreshold;
    private int $timeoutSeconds;
    
    public function __construct(string $service, int $threshold = 5, int $timeout = 60)
    {
        $this->key = "circuit_breaker:{$service}";
        $this->failureThreshold = $threshold;
        $this->timeoutSeconds = $timeout;
    }
    
    public function canExecute(): bool
    {
        $state = Cache::get($this->key, ['failures' => 0, 'last_failure' => null]);
        
        if ($state['failures'] >= $this->failureThreshold) {
            $timeSinceFailure = time() - $state['last_failure'];
            return $timeSinceFailure >= $this->timeoutSeconds;
        }
        
        return true;
    }
    
    public function recordSuccess(): void
    {
        Cache::forget($this->key);
    }
    
    public function recordFailure(): void
    {
        $state = Cache::get($this->key, ['failures' => 0, 'last_failure' => null]);
        $state['failures']++;
        $state['last_failure'] = time();
        
        Cache::put($this->key, $state, 3600);
    }
}
```

## Monitoring and Observability

### 1. Job Metrics Collection

```php
use JTD\LaravelMCP\Events\McpRequestProcessed;

class AsyncJobMetricsListener
{
    public function handle(McpRequestProcessed $event): void
    {
        if ($event->transport === 'async') {
            // Track async job metrics
            $this->updateMetrics([
                'method' => $event->method,
                'execution_time' => $event->executionTime,
                'success' => $event->result !== null,
                'timestamp' => $event->timestamp
            ]);
        }
    }
    
    private function updateMetrics(array $data): void
    {
        // Update various metrics
        Cache::increment('mcp:async:jobs:total');
        
        if ($data['success']) {
            Cache::increment('mcp:async:jobs:successful');
        } else {
            Cache::increment('mcp:async:jobs:failed');
        }
        
        // Track average execution time
        $avgKey = 'mcp:async:avg_execution_time';
        $currentAvg = Cache::get($avgKey, 0);
        $newAvg = ($currentAvg + $data['execution_time']) / 2;
        Cache::put($avgKey, $newAvg, 3600);
        
        // Track method-specific metrics
        $methodKey = "mcp:async:methods:{$data['method']}";
        Cache::increment("{$methodKey}:count");
        Cache::put("{$methodKey}:last_execution_time", $data['execution_time'], 3600);
    }
}
```

### 2. Health Checks

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use JTD\LaravelMCP\Facades\Mcp;

class HealthController extends Controller
{
    public function asyncHealth(): JsonResponse
    {
        $metrics = [
            'queue_size' => $this->getQueueSize(),
            'failed_jobs' => $this->getFailedJobCount(),
            'average_processing_time' => Cache::get('mcp:async:avg_execution_time', 0),
            'success_rate' => $this->calculateSuccessRate(),
            'oldest_pending_job' => $this->getOldestPendingJob(),
        ];
        
        $isHealthy = $metrics['queue_size'] < 1000 && 
                    $metrics['success_rate'] > 0.95 && 
                    $metrics['oldest_pending_job'] < 300; // 5 minutes
        
        return response()->json([
            'status' => $isHealthy ? 'healthy' : 'unhealthy',
            'metrics' => $metrics,
            'timestamp' => now()->toISOString()
        ]);
    }
}
```

## Testing Async Operations

### 1. Unit Testing

```php
<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\McpManager;
use Mockery;

class ProcessMcpRequestTest extends TestCase
{
    public function test_processes_tool_call_successfully(): void
    {
        $manager = Mockery::mock(McpManager::class);
        $manager->shouldReceive('processRequest')
               ->once()
               ->with('tools/call', ['name' => 'test'])
               ->andReturn(['result' => 'success']);
        
        $job = new ProcessMcpRequest('tools/call', ['name' => 'test']);
        $job->handle($manager);
        
        // Assert result was stored
        $this->assertNotNull(Cache::get("mcp:async:result:{$job->requestId}"));
    }
    
    public function test_handles_job_failure(): void
    {
        $manager = Mockery::mock(McpManager::class);
        $manager->shouldReceive('processRequest')
               ->once()
               ->andThrow(new RuntimeException('Test error'));
        
        $job = new ProcessMcpRequest('tools/call', ['name' => 'test']);
        
        $this->expectException(RuntimeException::class);
        $job->handle($manager);
        
        // Assert failure was recorded
        $result = Cache::get("mcp:async:result:{$job->requestId}");
        $this->assertEquals('failed', $result['status']);
    }
}
```

### 2. Feature Testing

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\Facades\Mcp;

class AsyncProcessingTest extends TestCase
{
    public function test_async_request_is_queued(): void
    {
        Queue::fake();
        
        $requestId = Mcp::dispatchAsync('tools/call', [
            'name' => 'test-tool',
            'arguments' => ['param' => 'value']
        ]);
        
        Queue::assertPushed(ProcessMcpRequest::class, function ($job) {
            return $job->method === 'tools/call' &&
                   $job->parameters['name'] === 'test-tool';
        });
        
        $this->assertIsString($requestId);
    }
    
    public function test_async_result_retrieval(): void
    {
        // Simulate completed job result
        $requestId = 'test_request_123';
        Cache::put("mcp:async:result:{$requestId}", [
            'status' => 'completed',
            'result' => ['success' => true],
            'completed_at' => now()->toISOString()
        ], 3600);
        
        $result = Mcp::getAsyncResult($requestId);
        
        $this->assertEquals(['success' => true], $result);
    }
}
```

## Performance Optimization

### 1. Queue Optimization

```php
// Use dedicated queues for different priorities
'connections' => [
    'mcp_high' => [
        'driver' => 'redis',
        'queue' => 'mcp_high_priority',
    ],
    'mcp_normal' => [
        'driver' => 'redis', 
        'queue' => 'mcp_normal',
    ],
    'mcp_low' => [
        'driver' => 'redis',
        'queue' => 'mcp_low_priority',
    ],
],
```

### 2. Result Caching Strategy

```php
class OptimizedResultStorage
{
    public function storeResult(string $requestId, array $result): void
    {
        $ttl = $this->calculateTtl($result);
        $compressed = $this->shouldCompress($result);
        
        $data = [
            'result' => $compressed ? gzcompress(serialize($result)) : $result,
            'compressed' => $compressed,
            'stored_at' => time()
        ];
        
        Cache::put("mcp:async:result:{$requestId}", $data, $ttl);
    }
    
    private function calculateTtl(array $result): int
    {
        $size = strlen(serialize($result));
        
        // Larger results have shorter TTL
        if ($size > 1048576) return 300;    // 5 minutes for > 1MB
        if ($size > 102400) return 1800;    // 30 minutes for > 100KB
        return 3600;                        // 1 hour for smaller results
    }
}
```

The async processing system provides a robust foundation for handling complex, long-running MCP operations while maintaining system performance and reliability.