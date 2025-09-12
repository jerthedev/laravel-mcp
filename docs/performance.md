# Performance Optimization Guide

This guide provides comprehensive strategies and techniques for optimizing the performance of your Laravel MCP server implementation. Follow these best practices to ensure your MCP server can handle high loads efficiently.

## Table of Contents

1. [Performance Benchmarking](#performance-benchmarking)
2. [Caching Strategies](#caching-strategies)
3. [Database Optimization](#database-optimization)
4. [Component Optimization](#component-optimization)
5. [Transport Optimization](#transport-optimization)
6. [Memory Management](#memory-management)
7. [Queue and Async Processing](#queue-and-async-processing)
8. [Monitoring and Profiling](#monitoring-and-profiling)
9. [Production Configuration](#production-configuration)

## Performance Benchmarking

### Establishing Baselines

Before optimizing, establish performance baselines:

```php
<?php

namespace App\Mcp\Benchmarks;

use JTD\LaravelMCP\Testing\BenchmarkRunner;

class McpBenchmark
{
    protected BenchmarkRunner $runner;
    
    public function __construct(BenchmarkRunner $runner)
    {
        $this->runner = $runner;
    }
    
    public function runBaseline(): array
    {
        return [
            'tool_execution' => $this->benchmarkToolExecution(),
            'resource_reading' => $this->benchmarkResourceReading(),
            'prompt_generation' => $this->benchmarkPromptGeneration(),
            'request_throughput' => $this->benchmarkRequestThroughput(),
        ];
    }
    
    protected function benchmarkToolExecution(): array
    {
        return $this->runner->measure(function () {
            app('mcp.registry')->getTool('calculator')->execute([
                'operation' => 'add',
                'a' => 5,
                'b' => 3,
            ]);
        }, 1000); // Run 1000 times
    }
    
    protected function benchmarkResourceReading(): array
    {
        return $this->runner->measure(function () {
            app('mcp.registry')->getResource('users')->read('user://1');
        }, 1000);
    }
    
    protected function benchmarkPromptGeneration(): array
    {
        return $this->runner->measure(function () {
            app('mcp.registry')->getPrompt('email')->generate([
                'type' => 'welcome',
                'recipient_name' => 'John',
            ]);
        }, 1000);
    }
    
    protected function benchmarkRequestThroughput(): array
    {
        $requests = $this->generateSampleRequests(1000);
        
        $start = microtime(true);
        foreach ($requests as $request) {
            app('mcp.processor')->process($request);
        }
        $duration = microtime(true) - $start;
        
        return [
            'total_requests' => 1000,
            'duration_seconds' => $duration,
            'requests_per_second' => 1000 / $duration,
        ];
    }
}
```

### Performance Metrics Tracking

```php
<?php

namespace App\Mcp\Services;

use Illuminate\Support\Facades\Redis;

class PerformanceMetrics
{
    public function track(string $operation, callable $callback)
    {
        $start = microtime(true);
        $memoryBefore = memory_get_usage(true);
        
        try {
            $result = $callback();
            $success = true;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            throw $e;
        } finally {
            $duration = (microtime(true) - $start) * 1000; // ms
            $memoryUsed = memory_get_usage(true) - $memoryBefore;
            
            $this->recordMetrics($operation, $duration, $memoryUsed, $success);
        }
        
        return $result;
    }
    
    protected function recordMetrics(
        string $operation,
        float $duration,
        int $memoryUsed,
        bool $success
    ): void {
        // Store in Redis for real-time monitoring
        $key = "metrics:{$operation}:" . date('Y-m-d:H');
        
        Redis::pipeline(function ($pipe) use ($key, $duration, $memoryUsed, $success) {
            $pipe->hincrby($key, 'count', 1);
            $pipe->hincrbyfloat($key, 'total_duration', $duration);
            $pipe->hincrby($key, 'total_memory', $memoryUsed);
            
            if (!$success) {
                $pipe->hincrby($key, 'failures', 1);
            }
            
            $pipe->expire($key, 86400); // Keep for 24 hours
        });
        
        // Update running averages
        $this->updateAverages($operation, $duration, $memoryUsed);
    }
    
    protected function updateAverages(string $operation, float $duration, int $memory): void
    {
        $avgKey = "metrics:avg:{$operation}";
        
        $current = Redis::hgetall($avgKey);
        $count = ($current['count'] ?? 0) + 1;
        
        $avgDuration = (($current['duration'] ?? 0) * ($count - 1) + $duration) / $count;
        $avgMemory = (($current['memory'] ?? 0) * ($count - 1) + $memory) / $count;
        
        Redis::hmset($avgKey, [
            'count' => $count,
            'duration' => $avgDuration,
            'memory' => $avgMemory,
        ]);
    }
}
```

## Caching Strategies

### Multi-Level Caching

Implement a multi-level caching strategy for optimal performance:

```php
<?php

namespace App\Mcp\Cache;

use Illuminate\Support\Facades\Cache;

class MultiLevelCache
{
    protected array $memoryCache = [];
    protected int $memoryCacheSize = 0;
    protected int $maxMemoryCacheSize = 10485760; // 10MB
    
    public function get(string $key, callable $callback = null)
    {
        // Level 1: In-memory cache
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key]['value'];
        }
        
        // Level 2: Redis cache
        $value = Cache::store('redis')->get($key);
        if ($value !== null) {
            $this->storeInMemory($key, $value);
            return $value;
        }
        
        // Level 3: Database cache
        $value = $this->getFromDatabase($key);
        if ($value !== null) {
            $this->storeInRedis($key, $value);
            $this->storeInMemory($key, $value);
            return $value;
        }
        
        // Generate value if callback provided
        if ($callback) {
            $value = $callback();
            $this->store($key, $value);
            return $value;
        }
        
        return null;
    }
    
    public function store(string $key, $value, int $ttl = 3600): void
    {
        // Store in all cache levels
        $this->storeInMemory($key, $value);
        $this->storeInRedis($key, $value, $ttl);
        $this->storeInDatabase($key, $value, $ttl);
    }
    
    protected function storeInMemory(string $key, $value): void
    {
        $serialized = serialize($value);
        $size = strlen($serialized);
        
        // Evict old entries if cache is full
        while ($this->memoryCacheSize + $size > $this->maxMemoryCacheSize && !empty($this->memoryCache)) {
            $this->evictOldestFromMemory();
        }
        
        $this->memoryCache[$key] = [
            'value' => $value,
            'size' => $size,
            'accessed' => microtime(true),
        ];
        
        $this->memoryCacheSize += $size;
    }
    
    protected function evictOldestFromMemory(): void
    {
        $oldest = null;
        $oldestTime = PHP_INT_MAX;
        
        foreach ($this->memoryCache as $key => $entry) {
            if ($entry['accessed'] < $oldestTime) {
                $oldest = $key;
                $oldestTime = $entry['accessed'];
            }
        }
        
        if ($oldest) {
            $this->memoryCacheSize -= $this->memoryCache[$oldest]['size'];
            unset($this->memoryCache[$oldest]);
        }
    }
    
    protected function storeInRedis(string $key, $value, int $ttl): void
    {
        Cache::store('redis')->put($key, $value, $ttl);
    }
    
    protected function storeInDatabase(string $key, $value, int $ttl): void
    {
        DB::table('cache_entries')->updateOrInsert(
            ['key' => $key],
            [
                'value' => serialize($value),
                'expires_at' => now()->addSeconds($ttl),
                'updated_at' => now(),
            ]
        );
    }
    
    protected function getFromDatabase(string $key)
    {
        $entry = DB::table('cache_entries')
            ->where('key', $key)
            ->where('expires_at', '>', now())
            ->first();
        
        return $entry ? unserialize($entry->value) : null;
    }
}
```

### Tool Result Caching

Cache tool execution results for idempotent operations:

```php
<?php

namespace App\Mcp\Cache;

use JTD\LaravelMCP\Abstracts\McpTool;

trait CacheableToolTrait
{
    protected function getCacheKey(array $arguments): string
    {
        return 'tool:' . $this->getName() . ':' . md5(json_encode($arguments));
    }
    
    protected function getCacheDuration(): int
    {
        return property_exists($this, 'cacheDuration') ? $this->cacheDuration : 300;
    }
    
    public function execute(array $arguments): mixed
    {
        // Check if caching is enabled for this tool
        if (!$this->isCacheable($arguments)) {
            return parent::execute($arguments);
        }
        
        $cacheKey = $this->getCacheKey($arguments);
        
        return Cache::remember($cacheKey, $this->getCacheDuration(), function () use ($arguments) {
            return parent::execute($arguments);
        });
    }
    
    protected function isCacheable(array $arguments): bool
    {
        // Override in specific tools to determine cacheability
        return true;
    }
}
```

### Resource Response Caching

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Cache;

abstract class CacheableResource extends McpResource
{
    protected int $cacheDuration = 300; // 5 minutes default
    
    public function read(string $uri): array
    {
        $cacheKey = $this->getCacheKey('read', $uri);
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($uri) {
            return $this->performRead($uri);
        });
    }
    
    public function list(): array
    {
        $cacheKey = $this->getCacheKey('list');
        
        return Cache::remember($cacheKey, $this->cacheDuration, function () {
            return $this->performList();
        });
    }
    
    abstract protected function performRead(string $uri): array;
    abstract protected function performList(): array;
    
    protected function getCacheKey(string $operation, string $uri = ''): string
    {
        return sprintf(
            'resource:%s:%s:%s',
            $this->getName(),
            $operation,
            md5($uri)
        );
    }
    
    public function invalidateCache(string $uri = null): void
    {
        if ($uri) {
            Cache::forget($this->getCacheKey('read', $uri));
        } else {
            Cache::forget($this->getCacheKey('list'));
        }
    }
}
```

## Database Optimization

### Query Optimization

```php
<?php

namespace App\Mcp\Database;

use Illuminate\Support\Facades\DB;

class OptimizedQueryBuilder
{
    public function getUsers(array $filters = []): Collection
    {
        $query = DB::table('users')
            ->select(['id', 'name', 'email', 'created_at']) // Select only needed columns
            ->where('active', true);
        
        // Use indexes effectively
        if (isset($filters['email'])) {
            $query->where('email', $filters['email']); // Uses email index
        }
        
        if (isset($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']); // Uses created_at index
        }
        
        // Avoid N+1 queries with eager loading
        if (isset($filters['with_posts'])) {
            $query->with(['posts' => function ($q) {
                $q->select(['id', 'user_id', 'title', 'published_at'])
                  ->where('published', true)
                  ->orderBy('published_at', 'desc')
                  ->limit(10);
            }]);
        }
        
        // Use chunking for large datasets
        if (isset($filters['chunk'])) {
            $results = collect();
            $query->chunk(1000, function ($users) use (&$results) {
                $results = $results->merge($users);
            });
            return $results;
        }
        
        // Use pagination for API responses
        if (isset($filters['paginate'])) {
            return $query->paginate($filters['per_page'] ?? 15);
        }
        
        return $query->get();
    }
    
    public function searchPosts(string $term): Collection
    {
        // Use full-text search indexes
        return DB::table('posts')
            ->whereRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE)', [$term])
            ->select(['id', 'title', 'excerpt', 'published_at'])
            ->orderByRaw('MATCH(title, content) AGAINST(? IN BOOLEAN MODE) DESC', [$term])
            ->limit(50)
            ->get();
    }
}
```

### Connection Pooling

```php
<?php

namespace App\Mcp\Database;

use Illuminate\Database\Connection;

class ConnectionPool
{
    protected array $connections = [];
    protected array $inUse = [];
    protected int $maxConnections = 10;
    
    public function getConnection(string $name = 'default'): Connection
    {
        // Return existing idle connection
        foreach ($this->connections[$name] ?? [] as $key => $connection) {
            if (!in_array($key, $this->inUse[$name] ?? [])) {
                $this->inUse[$name][] = $key;
                return $connection;
            }
        }
        
        // Create new connection if under limit
        if (count($this->connections[$name] ?? []) < $this->maxConnections) {
            $connection = DB::connection($name);
            $key = spl_object_id($connection);
            
            $this->connections[$name][$key] = $connection;
            $this->inUse[$name][] = $key;
            
            return $connection;
        }
        
        // Wait for available connection
        return $this->waitForConnection($name);
    }
    
    public function releaseConnection(Connection $connection, string $name = 'default'): void
    {
        $key = spl_object_id($connection);
        
        if (($index = array_search($key, $this->inUse[$name] ?? [])) !== false) {
            unset($this->inUse[$name][$index]);
        }
    }
    
    protected function waitForConnection(string $name, int $timeout = 5): Connection
    {
        $start = time();
        
        while (time() - $start < $timeout) {
            foreach ($this->connections[$name] ?? [] as $key => $connection) {
                if (!in_array($key, $this->inUse[$name] ?? [])) {
                    $this->inUse[$name][] = $key;
                    return $connection;
                }
            }
            
            usleep(10000); // Wait 10ms
        }
        
        throw new \RuntimeException("Connection pool timeout for {$name}");
    }
}
```

### Index Management

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeIndexes extends Command
{
    protected $signature = 'mcp:optimize-indexes';
    protected $description = 'Optimize database indexes for MCP operations';
    
    public function handle(): int
    {
        $this->info('Analyzing and optimizing database indexes...');
        
        // Analyze table statistics
        $tables = ['mcp_tools', 'mcp_resources', 'mcp_prompts', 'mcp_audit_logs'];
        
        foreach ($tables as $table) {
            DB::statement("ANALYZE TABLE {$table}");
            $this->info("Analyzed table: {$table}");
        }
        
        // Create missing indexes
        $this->createIndexIfNotExists('mcp_audit_logs', 'idx_method_created', ['method', 'created_at']);
        $this->createIndexIfNotExists('mcp_audit_logs', 'idx_user_created', ['user_id', 'created_at']);
        $this->createIndexIfNotExists('cache_entries', 'idx_key_expires', ['key', 'expires_at']);
        
        // Remove unused indexes
        $this->removeUnusedIndexes();
        
        $this->info('Index optimization complete!');
        return 0;
    }
    
    protected function createIndexIfNotExists(string $table, string $indexName, array $columns): void
    {
        $exists = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        
        if (empty($exists)) {
            $columnList = implode(', ', $columns);
            DB::statement("CREATE INDEX {$indexName} ON {$table} ({$columnList})");
            $this->info("Created index: {$indexName} on {$table}");
        }
    }
    
    protected function removeUnusedIndexes(): void
    {
        // Query to find unused indexes (MySQL specific)
        $unusedIndexes = DB::select("
            SELECT 
                table_name,
                index_name,
                cardinality
            FROM information_schema.statistics
            WHERE 
                table_schema = DATABASE()
                AND index_name != 'PRIMARY'
                AND cardinality = 0
        ");
        
        foreach ($unusedIndexes as $index) {
            $this->warn("Consider removing unused index: {$index->index_name} on {$index->table_name}");
        }
    }
}
```

## Component Optimization

### Lazy Loading Components

```php
<?php

namespace App\Mcp\Registry;

use JTD\LaravelMCP\Registry\McpRegistry;

class OptimizedRegistry extends McpRegistry
{
    protected array $componentCache = [];
    protected array $componentMetadata = [];
    
    public function register(string $type, string $name, string $class): void
    {
        // Store only metadata, not the actual instance
        $this->componentMetadata[$type][$name] = [
            'class' => $class,
            'loaded' => false,
        ];
    }
    
    public function get(string $type, string $name)
    {
        // Check if component is already loaded
        if (isset($this->componentCache[$type][$name])) {
            return $this->componentCache[$type][$name];
        }
        
        // Load component on demand
        if (isset($this->componentMetadata[$type][$name])) {
            $metadata = $this->componentMetadata[$type][$name];
            $component = app($metadata['class']);
            
            // Cache the instance
            $this->componentCache[$type][$name] = $component;
            $this->componentMetadata[$type][$name]['loaded'] = true;
            
            return $component;
        }
        
        return null;
    }
    
    public function preload(array $components): void
    {
        foreach ($components as $type => $names) {
            foreach ($names as $name) {
                $this->get($type, $name);
            }
        }
    }
    
    public function getLoadedComponents(): array
    {
        $loaded = [];
        
        foreach ($this->componentMetadata as $type => $components) {
            foreach ($components as $name => $metadata) {
                if ($metadata['loaded']) {
                    $loaded[$type][] = $name;
                }
            }
        }
        
        return $loaded;
    }
}
```

### Component Pooling

```php
<?php

namespace App\Mcp\Pool;

class ComponentPool
{
    protected array $pool = [];
    protected array $inUse = [];
    protected int $maxPoolSize = 10;
    
    public function acquire(string $class): object
    {
        $poolKey = $this->getPoolKey($class);
        
        // Check for available instance in pool
        if (!empty($this->pool[$poolKey])) {
            $instance = array_pop($this->pool[$poolKey]);
            $this->inUse[spl_object_id($instance)] = $poolKey;
            return $instance;
        }
        
        // Create new instance
        $instance = app($class);
        $this->inUse[spl_object_id($instance)] = $poolKey;
        
        return $instance;
    }
    
    public function release(object $instance): void
    {
        $id = spl_object_id($instance);
        
        if (!isset($this->inUse[$id])) {
            return;
        }
        
        $poolKey = $this->inUse[$id];
        unset($this->inUse[$id]);
        
        // Reset instance state if possible
        if (method_exists($instance, 'reset')) {
            $instance->reset();
        }
        
        // Add back to pool if not at capacity
        if (count($this->pool[$poolKey] ?? []) < $this->maxPoolSize) {
            $this->pool[$poolKey][] = $instance;
        }
    }
    
    protected function getPoolKey(string $class): string
    {
        return $class;
    }
    
    public function getStats(): array
    {
        $stats = [];
        
        foreach ($this->pool as $class => $instances) {
            $stats[$class] = [
                'pooled' => count($instances),
                'in_use' => count(array_filter($this->inUse, fn($key) => $key === $class)),
            ];
        }
        
        return $stats;
    }
}
```

## Transport Optimization

### HTTP Transport Optimization

```php
<?php

namespace App\Mcp\Transport;

use JTD\LaravelMCP\Transport\HttpTransport;

class OptimizedHttpTransport extends HttpTransport
{
    protected bool $compressionEnabled = true;
    protected int $compressionThreshold = 1024; // Compress responses > 1KB
    
    public function send(array $response): void
    {
        $json = json_encode($response);
        
        // Apply compression for large responses
        if ($this->compressionEnabled && strlen($json) > $this->compressionThreshold) {
            $compressed = gzencode($json, 6);
            
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($compressed));
            echo $compressed;
        } else {
            header('Content-Length: ' . strlen($json));
            echo $json;
        }
    }
    
    public function receive(): array
    {
        $input = file_get_contents('php://input');
        
        // Check if request is compressed
        if (isset($_SERVER['HTTP_CONTENT_ENCODING']) && $_SERVER['HTTP_CONTENT_ENCODING'] === 'gzip') {
            $input = gzdecode($input);
        }
        
        return json_decode($input, true);
    }
}
```

### Connection Keep-Alive

```php
<?php

namespace App\Mcp\Transport;

class KeepAliveTransport
{
    protected array $connections = [];
    protected int $maxIdleTime = 30; // seconds
    
    public function handleConnection($socket): void
    {
        stream_set_timeout($socket, $this->maxIdleTime);
        
        $connectionId = uniqid();
        $this->connections[$connectionId] = [
            'socket' => $socket,
            'last_activity' => time(),
            'requests_handled' => 0,
        ];
        
        while ($this->isConnectionAlive($connectionId)) {
            $request = $this->readRequest($socket);
            
            if ($request === false) {
                break; // Connection closed or timeout
            }
            
            $response = $this->processRequest($request);
            $this->sendResponse($socket, $response);
            
            $this->connections[$connectionId]['last_activity'] = time();
            $this->connections[$connectionId]['requests_handled']++;
            
            // Close connection after certain number of requests
            if ($this->connections[$connectionId]['requests_handled'] >= 100) {
                break;
            }
        }
        
        $this->closeConnection($connectionId);
    }
    
    protected function isConnectionAlive(string $connectionId): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }
        
        $connection = $this->connections[$connectionId];
        
        // Check if connection has been idle too long
        if (time() - $connection['last_activity'] > $this->maxIdleTime) {
            return false;
        }
        
        // Check if socket is still valid
        if (!is_resource($connection['socket'])) {
            return false;
        }
        
        return true;
    }
    
    protected function closeConnection(string $connectionId): void
    {
        if (isset($this->connections[$connectionId])) {
            fclose($this->connections[$connectionId]['socket']);
            unset($this->connections[$connectionId]);
        }
    }
}
```

## Memory Management

### Memory-Efficient Data Processing

```php
<?php

namespace App\Mcp\Memory;

class MemoryEfficientProcessor
{
    protected int $batchSize = 1000;
    protected int $memoryLimit;
    
    public function __construct()
    {
        $this->memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
    }
    
    public function processLargeDataset(iterable $data, callable $processor): \Generator
    {
        $batch = [];
        $batchCount = 0;
        
        foreach ($data as $item) {
            $batch[] = $item;
            $batchCount++;
            
            // Process batch when size limit reached or memory usage is high
            if ($batchCount >= $this->batchSize || $this->isMemoryHigh()) {
                yield from $this->processBatch($batch, $processor);
                
                // Clear batch and run garbage collection
                $batch = [];
                $batchCount = 0;
                gc_collect_cycles();
            }
        }
        
        // Process remaining items
        if (!empty($batch)) {
            yield from $this->processBatch($batch, $processor);
        }
    }
    
    protected function processBatch(array $batch, callable $processor): array
    {
        $results = [];
        
        foreach ($batch as $item) {
            try {
                $results[] = $processor($item);
            } catch (\Exception $e) {
                // Log error and continue
                \Log::error('Batch processing error', [
                    'error' => $e->getMessage(),
                    'item' => $item,
                ]);
            }
        }
        
        return $results;
    }
    
    protected function isMemoryHigh(): bool
    {
        $currentUsage = memory_get_usage(true);
        $threshold = $this->memoryLimit * 0.8; // 80% of limit
        
        return $currentUsage > $threshold;
    }
    
    protected function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
```

### Object Recycling

```php
<?php

namespace App\Mcp\Memory;

trait RecyclableTrait
{
    protected static array $recyclePool = [];
    protected static int $maxPoolSize = 50;
    
    public static function obtain(...$args): static
    {
        $class = static::class;
        
        // Try to get from recycle pool
        if (!empty(self::$recyclePool[$class])) {
            $instance = array_pop(self::$recyclePool[$class]);
            $instance->reset();
            $instance->initialize(...$args);
            return $instance;
        }
        
        // Create new instance
        return new static(...$args);
    }
    
    public function recycle(): void
    {
        $class = static::class;
        
        // Reset state
        $this->reset();
        
        // Add to pool if not at capacity
        if (count(self::$recyclePool[$class] ?? []) < self::$maxPoolSize) {
            self::$recyclePool[$class][] = $this;
        }
    }
    
    abstract protected function reset(): void;
    abstract protected function initialize(...$args): void;
}
```

## Queue and Async Processing

### Async Tool Execution

```php
<?php

namespace App\Mcp\Async;

use Illuminate\Support\Facades\Queue;
use App\Jobs\ExecuteToolJob;

class AsyncToolExecutor
{
    protected array $asyncTools = [
        'data_processor',
        'report_generator',
        'batch_import',
    ];
    
    public function execute(string $toolName, array $arguments): array
    {
        if (!$this->shouldRunAsync($toolName, $arguments)) {
            return $this->executeSync($toolName, $arguments);
        }
        
        $job = new ExecuteToolJob($toolName, $arguments);
        $jobId = uniqid('job_');
        
        // Store job metadata
        Cache::put("job:{$jobId}", [
            'status' => 'queued',
            'tool' => $toolName,
            'arguments' => $arguments,
            'created_at' => now(),
        ], 3600);
        
        // Dispatch to appropriate queue
        $queue = $this->determineQueue($toolName, $arguments);
        Queue::pushOn($queue, $job->withJobId($jobId));
        
        return [
            'async' => true,
            'job_id' => $jobId,
            'status_url' => route('mcp.job.status', ['id' => $jobId]),
            'estimated_time' => $this->estimateExecutionTime($toolName, $arguments),
        ];
    }
    
    protected function shouldRunAsync(string $toolName, array $arguments): bool
    {
        // Check if tool is configured for async
        if (!in_array($toolName, $this->asyncTools)) {
            return false;
        }
        
        // Check based on input size
        $inputSize = strlen(json_encode($arguments));
        if ($inputSize > 10000) { // > 10KB
            return true;
        }
        
        // Check based on estimated execution time
        $estimatedTime = $this->estimateExecutionTime($toolName, $arguments);
        return $estimatedTime > 5; // > 5 seconds
    }
    
    protected function determineQueue(string $toolName, array $arguments): string
    {
        // Priority-based queue selection
        $priority = $arguments['priority'] ?? 'normal';
        
        return match($priority) {
            'high' => 'high-priority',
            'low' => 'low-priority',
            default => 'default',
        };
    }
    
    protected function estimateExecutionTime(string $toolName, array $arguments): int
    {
        // Get historical average from metrics
        $avgTime = Redis::get("metrics:tool:{$toolName}:avg_time");
        
        if ($avgTime) {
            return (int) ceil($avgTime);
        }
        
        // Default estimates
        return match($toolName) {
            'data_processor' => 10,
            'report_generator' => 30,
            'batch_import' => 60,
            default => 5,
        };
    }
}
```

### Batch Processing

```php
<?php

namespace App\Mcp\Batch;

use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class BatchProcessor
{
    public function processBatch(array $items, string $processorClass): Batch
    {
        $jobs = [];
        
        // Create jobs for each chunk
        foreach (array_chunk($items, 100) as $chunk) {
            $jobs[] = new ProcessChunkJob($chunk, $processorClass);
        }
        
        // Dispatch batch with callbacks
        return Bus::batch($jobs)
            ->name('MCP Batch Processing')
            ->onQueue('batch')
            ->allowFailures()
            ->then(function (Batch $batch) {
                // Success callback
                $this->notifyBatchComplete($batch);
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                // Error callback
                $this->handleBatchError($batch, $e);
            })
            ->finally(function (Batch $batch) {
                // Cleanup
                $this->cleanupBatch($batch);
            })
            ->dispatch();
    }
    
    protected function notifyBatchComplete(Batch $batch): void
    {
        \Log::info('Batch completed', [
            'id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'processed' => $batch->processedJobs(),
            'failed' => $batch->failedJobs,
        ]);
    }
    
    protected function handleBatchError(Batch $batch, \Throwable $e): void
    {
        \Log::error('Batch error', [
            'id' => $batch->id,
            'error' => $e->getMessage(),
            'failed_jobs' => $batch->failedJobs,
        ]);
    }
    
    protected function cleanupBatch(Batch $batch): void
    {
        // Clean up temporary data
        Cache::forget("batch:{$batch->id}:data");
    }
}
```

## Monitoring and Profiling

### Performance Monitor

```php
<?php

namespace App\Mcp\Monitoring;

use Illuminate\Support\Facades\Redis;

class PerformanceMonitor
{
    protected array $metrics = [];
    protected float $startTime;
    protected int $startMemory;
    
    public function start(): void
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->metrics = [];
    }
    
    public function checkpoint(string $name): void
    {
        $this->metrics[$name] = [
            'time' => microtime(true) - $this->startTime,
            'memory' => memory_get_usage(true) - $this->startMemory,
        ];
    }
    
    public function end(): array
    {
        $totalTime = microtime(true) - $this->startTime;
        $totalMemory = memory_get_usage(true) - $this->startMemory;
        
        return [
            'total_time' => $totalTime,
            'total_memory' => $totalMemory,
            'checkpoints' => $this->metrics,
        ];
    }
    
    public function profile(callable $callback, string $operation)
    {
        $this->start();
        
        try {
            $result = $callback();
        } finally {
            $metrics = $this->end();
            $this->recordMetrics($operation, $metrics);
        }
        
        return $result;
    }
    
    protected function recordMetrics(string $operation, array $metrics): void
    {
        // Store in Redis for real-time monitoring
        $key = "performance:{$operation}:" . date('Y-m-d:H');
        
        Redis::pipeline(function ($pipe) use ($key, $metrics) {
            $pipe->hincrby($key, 'count', 1);
            $pipe->hincrbyfloat($key, 'total_time', $metrics['total_time']);
            $pipe->hincrby($key, 'total_memory', $metrics['total_memory']);
            $pipe->expire($key, 86400);
        });
        
        // Alert if performance degrades
        if ($metrics['total_time'] > $this->getThreshold($operation)) {
            $this->alertSlowOperation($operation, $metrics);
        }
    }
    
    protected function getThreshold(string $operation): float
    {
        return config("mcp.performance.thresholds.{$operation}", 1.0);
    }
    
    protected function alertSlowOperation(string $operation, array $metrics): void
    {
        \Log::warning('Slow operation detected', [
            'operation' => $operation,
            'time' => $metrics['total_time'],
            'memory' => $metrics['total_memory'],
            'checkpoints' => $metrics['checkpoints'],
        ]);
    }
}
```

### Query Profiling

```php
<?php

namespace App\Mcp\Profiling;

use Illuminate\Support\Facades\DB;

class QueryProfiler
{
    protected array $queries = [];
    protected bool $enabled = false;
    
    public function enable(): void
    {
        $this->enabled = true;
        $this->queries = [];
        
        DB::listen(function ($query) {
            if ($this->enabled) {
                $this->queries[] = [
                    'sql' => $query->sql,
                    'bindings' => $query->bindings,
                    'time' => $query->time,
                ];
            }
        });
    }
    
    public function disable(): array
    {
        $this->enabled = false;
        return $this->analyze();
    }
    
    protected function analyze(): array
    {
        $totalTime = array_sum(array_column($this->queries, 'time'));
        $slowQueries = array_filter($this->queries, fn($q) => $q['time'] > 100);
        
        return [
            'total_queries' => count($this->queries),
            'total_time' => $totalTime,
            'average_time' => $totalTime / max(count($this->queries), 1),
            'slow_queries' => $slowQueries,
            'duplicate_queries' => $this->findDuplicates(),
        ];
    }
    
    protected function findDuplicates(): array
    {
        $counts = [];
        
        foreach ($this->queries as $query) {
            $key = md5($query['sql'] . json_encode($query['bindings']));
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        
        return array_filter($counts, fn($count) => $count > 1);
    }
}
```

## Production Configuration

### Optimized Configuration

```php
<?php

// config/laravel-mcp.php

return [
    'enabled' => env('MCP_ENABLED', true),
    
    // Performance settings
    'performance' => [
        'cache_enabled' => env('MCP_CACHE_ENABLED', true),
        'cache_duration' => env('MCP_CACHE_DURATION', 300),
        'lazy_loading' => env('MCP_LAZY_LOADING', true),
        'connection_pooling' => env('MCP_CONNECTION_POOLING', true),
        'async_threshold' => env('MCP_ASYNC_THRESHOLD', 5), // seconds
    ],
    
    // Memory management
    'memory' => [
        'max_request_size' => env('MCP_MAX_REQUEST_SIZE', 10485760), // 10MB
        'max_response_size' => env('MCP_MAX_RESPONSE_SIZE', 10485760), // 10MB
        'batch_size' => env('MCP_BATCH_SIZE', 1000),
        'gc_interval' => env('MCP_GC_INTERVAL', 100), // requests
    ],
    
    // Database optimization
    'database' => [
        'chunk_size' => env('MCP_DB_CHUNK_SIZE', 1000),
        'query_timeout' => env('MCP_DB_QUERY_TIMEOUT', 30), // seconds
        'connection_pool_size' => env('MCP_DB_POOL_SIZE', 10),
    ],
    
    // Queue settings
    'queue' => [
        'default_queue' => env('MCP_DEFAULT_QUEUE', 'default'),
        'high_priority_queue' => env('MCP_HIGH_PRIORITY_QUEUE', 'high'),
        'low_priority_queue' => env('MCP_LOW_PRIORITY_QUEUE', 'low'),
        'batch_queue' => env('MCP_BATCH_QUEUE', 'batch'),
        'retry_after' => env('MCP_QUEUE_RETRY_AFTER', 90), // seconds
    ],
    
    // Monitoring
    'monitoring' => [
        'enabled' => env('MCP_MONITORING_ENABLED', true),
        'sample_rate' => env('MCP_MONITORING_SAMPLE_RATE', 0.1), // 10%
        'slow_query_threshold' => env('MCP_SLOW_QUERY_THRESHOLD', 100), // ms
        'memory_limit_warning' => env('MCP_MEMORY_WARNING', 0.8), // 80%
    ],
];
```

### Production Optimization Checklist

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class McpProductionCheck extends Command
{
    protected $signature = 'mcp:production-check';
    protected $description = 'Check MCP production optimization settings';
    
    public function handle(): int
    {
        $this->info('Checking MCP production optimizations...');
        
        $checks = [
            'OPcache enabled' => $this->checkOpcache(),
            'Redis configured' => $this->checkRedis(),
            'Queue workers running' => $this->checkQueueWorkers(),
            'Database indexes optimal' => $this->checkDatabaseIndexes(),
            'Cache configured' => $this->checkCache(),
            'Compression enabled' => $this->checkCompression(),
            'Debug mode disabled' => $this->checkDebugMode(),
            'Memory limit adequate' => $this->checkMemoryLimit(),
        ];
        
        $failed = 0;
        foreach ($checks as $check => $result) {
            if ($result) {
                $this->info("✓ {$check}");
            } else {
                $this->error("✗ {$check}");
                $failed++;
            }
        }
        
        if ($failed > 0) {
            $this->error("\n{$failed} checks failed. Please review and fix before deploying to production.");
            return 1;
        }
        
        $this->info("\nAll checks passed! Your MCP server is optimized for production.");
        return 0;
    }
    
    protected function checkOpcache(): bool
    {
        return function_exists('opcache_get_status') && opcache_get_status() !== false;
    }
    
    protected function checkRedis(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    protected function checkQueueWorkers(): bool
    {
        // Check if queue workers are running
        exec('ps aux | grep "[q]ueue:work"', $output);
        return !empty($output);
    }
    
    protected function checkDatabaseIndexes(): bool
    {
        // Check for missing indexes
        $missingIndexes = DB::select("
            SELECT DISTINCT
                s.table_name,
                s.column_name
            FROM information_schema.statistics s
            WHERE s.table_schema = DATABASE()
            AND s.table_name LIKE 'mcp_%'
            AND s.seq_in_index = 1
            AND s.cardinality < 10
        ");
        
        return empty($missingIndexes);
    }
    
    protected function checkCache(): bool
    {
        return config('laravel-mcp.performance.cache_enabled') === true;
    }
    
    protected function checkCompression(): bool
    {
        return extension_loaded('zlib');
    }
    
    protected function checkDebugMode(): bool
    {
        return config('app.debug') === false;
    }
    
    protected function checkMemoryLimit(): bool
    {
        $limit = ini_get('memory_limit');
        $bytes = $this->parseMemoryLimit($limit);
        return $bytes >= 256 * 1024 * 1024; // At least 256MB
    }
    
    protected function parseMemoryLimit(string $limit): int
    {
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        return match($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
```

## Conclusion

By implementing these performance optimization strategies, your Laravel MCP server will be able to handle high loads efficiently while maintaining low latency and optimal resource usage. Regular monitoring and profiling will help identify bottlenecks and ensure continued optimal performance as your application scales.