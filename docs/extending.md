# Extending Laravel MCP

This guide covers how to extend and customize the Laravel MCP package to meet your specific needs. Whether you're creating custom tools, resources, prompts, or extending the core functionality, this guide provides comprehensive instructions and best practices.

## Table of Contents

1. [Creating Custom Tools](#creating-custom-tools)
2. [Creating Custom Resources](#creating-custom-resources)
3. [Creating Custom Prompts](#creating-custom-prompts)
4. [Custom Transports](#custom-transports)
5. [Middleware Extensions](#middleware-extensions)
6. [Custom Validation Rules](#custom-validation-rules)
7. [Event Listeners and Hooks](#event-listeners-and-hooks)
8. [Advanced Configuration](#advanced-configuration)
9. [Plugin Development](#plugin-development)

## Creating Custom Tools

Tools are executable functions that AI clients can call. They're the primary way to add custom functionality to your MCP server.

### Basic Tool Structure

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class WeatherTool extends McpTool
{
    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return 'weather';
    }
    
    /**
     * Get the tool description
     */
    public function getDescription(): string
    {
        return 'Get current weather information for a location';
    }
    
    /**
     * Define input schema using JSON Schema
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                    'description' => 'City name or coordinates',
                ],
                'units' => [
                    'type' => 'string',
                    'enum' => ['celsius', 'fahrenheit'],
                    'default' => 'celsius',
                    'description' => 'Temperature units',
                ],
            ],
            'required' => ['location'],
        ];
    }
    
    /**
     * Execute the tool
     */
    public function execute(array $arguments): mixed
    {
        $location = $arguments['location'];
        $units = $arguments['units'] ?? 'celsius';
        
        // Your weather API integration here
        $weatherData = $this->fetchWeatherData($location, $units);
        
        return [
            'location' => $location,
            'temperature' => $weatherData['temp'],
            'conditions' => $weatherData['conditions'],
            'humidity' => $weatherData['humidity'],
            'units' => $units,
        ];
    }
    
    private function fetchWeatherData(string $location, string $units): array
    {
        // Implementation details
        return [
            'temp' => 22,
            'conditions' => 'Partly cloudy',
            'humidity' => 65,
        ];
    }
}
```

### Advanced Tool with Dependencies

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use App\Services\DatabaseService;
use App\Services\CacheService;
use Illuminate\Support\Facades\Log;

class DatabaseQueryTool extends McpTool
{
    protected DatabaseService $database;
    protected CacheService $cache;
    
    public function __construct(DatabaseService $database, CacheService $cache)
    {
        $this->database = $database;
        $this->cache = $cache;
    }
    
    public function getName(): string
    {
        return 'database_query';
    }
    
    public function getDescription(): string
    {
        return 'Execute safe database queries with caching';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'SQL query to execute (SELECT only)',
                ],
                'parameters' => [
                    'type' => 'array',
                    'description' => 'Query parameters for binding',
                    'items' => ['type' => 'string'],
                ],
                'cache_duration' => [
                    'type' => 'integer',
                    'description' => 'Cache duration in seconds',
                    'minimum' => 0,
                    'maximum' => 3600,
                    'default' => 60,
                ],
            ],
            'required' => ['query'],
        ];
    }
    
    public function execute(array $arguments): mixed
    {
        // Validate query is SELECT only
        if (!$this->isSelectQuery($arguments['query'])) {
            throw new \InvalidArgumentException('Only SELECT queries are allowed');
        }
        
        $cacheKey = $this->getCacheKey($arguments);
        $cacheDuration = $arguments['cache_duration'] ?? 60;
        
        // Try to get from cache
        if ($cached = $this->cache->get($cacheKey)) {
            Log::info('Database query served from cache', ['key' => $cacheKey]);
            return $cached;
        }
        
        // Execute query
        $results = $this->database->select(
            $arguments['query'],
            $arguments['parameters'] ?? []
        );
        
        // Cache results
        $this->cache->put($cacheKey, $results, $cacheDuration);
        
        Log::info('Database query executed', [
            'query' => $arguments['query'],
            'rows' => count($results),
        ]);
        
        return $results;
    }
    
    private function isSelectQuery(string $query): bool
    {
        return preg_match('/^\s*SELECT\s+/i', trim($query)) === 1;
    }
    
    private function getCacheKey(array $arguments): string
    {
        return 'db_query:' . md5(json_encode($arguments));
    }
}
```

### Tool with Async Execution

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use App\Jobs\ProcessDataJob;
use Illuminate\Support\Facades\Queue;

class AsyncProcessingTool extends McpTool
{
    public function getName(): string
    {
        return 'async_processor';
    }
    
    public function getDescription(): string
    {
        return 'Process data asynchronously using Laravel queues';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'description' => 'Data to process',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'default' => 'normal',
                ],
                'callback_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'URL to call when processing is complete',
                ],
            ],
            'required' => ['data'],
        ];
    }
    
    public function execute(array $arguments): mixed
    {
        $job = new ProcessDataJob(
            $arguments['data'],
            $arguments['callback_url'] ?? null
        );
        
        $queue = match($arguments['priority'] ?? 'normal') {
            'high' => 'high-priority',
            'low' => 'low-priority',
            default => 'default',
        };
        
        $jobId = Queue::connection('redis')
            ->pushOn($queue, $job);
        
        return [
            'status' => 'queued',
            'job_id' => $jobId,
            'queue' => $queue,
            'estimated_time' => $this->estimateProcessingTime($arguments['data']),
        ];
    }
    
    private function estimateProcessingTime(array $data): int
    {
        // Estimate based on data size
        return count($data) * 2; // seconds
    }
}
```

## Creating Custom Resources

Resources provide data that AI clients can read, list, and potentially subscribe to.

### Basic Resource Structure

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;

class UserResource extends McpResource
{
    public function getName(): string
    {
        return 'users';
    }
    
    public function getDescription(): string
    {
        return 'Access user information from the database';
    }
    
    public function getUriTemplate(): string
    {
        return 'user://{id}';
    }
    
    public function read(string $uri): array
    {
        // Parse the URI to get the user ID
        preg_match('/user:\/\/(\d+)/', $uri, $matches);
        $userId = $matches[1] ?? null;
        
        if (!$userId) {
            throw new \InvalidArgumentException('Invalid user URI');
        }
        
        $user = \App\Models\User::find($userId);
        
        if (!$user) {
            throw new \RuntimeException('User not found');
        }
        
        return [
            'uri' => $uri,
            'name' => 'User Profile',
            'mimeType' => 'application/json',
            'text' => json_encode([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
            ]),
        ];
    }
    
    public function list(): array
    {
        $users = \App\Models\User::select('id', 'name')
            ->limit(100)
            ->get();
        
        return $users->map(function ($user) {
            return [
                'uri' => "user://{$user->id}",
                'name' => $user->name,
                'description' => "User profile for {$user->name}",
            ];
        })->toArray();
    }
}
```

### Advanced Resource with Pagination

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\DB;

class LogResource extends McpResource
{
    public function getName(): string
    {
        return 'logs';
    }
    
    public function getDescription(): string
    {
        return 'Access application logs with filtering and pagination';
    }
    
    public function getUriTemplate(): string
    {
        return 'log://{type}/{date}?page={page}&level={level}';
    }
    
    public function read(string $uri): array
    {
        $parsed = $this->parseUri($uri);
        
        $query = DB::table('logs')
            ->where('type', $parsed['type'])
            ->whereDate('created_at', $parsed['date']);
        
        if ($parsed['level']) {
            $query->where('level', $parsed['level']);
        }
        
        $logs = $query->paginate(50, ['*'], 'page', $parsed['page'] ?? 1);
        
        return [
            'uri' => $uri,
            'name' => "Logs: {$parsed['type']} - {$parsed['date']}",
            'mimeType' => 'application/json',
            'text' => json_encode([
                'data' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'total_pages' => $logs->lastPage(),
                    'total_items' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ],
            ]),
        ];
    }
    
    public function list(): array
    {
        $types = DB::table('logs')
            ->select('type')
            ->distinct()
            ->pluck('type');
        
        $resources = [];
        foreach ($types as $type) {
            $dates = DB::table('logs')
                ->where('type', $type)
                ->selectRaw('DATE(created_at) as date')
                ->distinct()
                ->orderBy('date', 'desc')
                ->limit(30)
                ->pluck('date');
            
            foreach ($dates as $date) {
                $resources[] = [
                    'uri' => "log://{$type}/{$date}",
                    'name' => "{$type} logs for {$date}",
                    'description' => "Application logs of type {$type}",
                ];
            }
        }
        
        return $resources;
    }
    
    private function parseUri(string $uri): array
    {
        preg_match('/log:\/\/([^\/]+)\/([^?]+)(?:\?(.+))?/', $uri, $matches);
        
        $params = [];
        if (isset($matches[3])) {
            parse_str($matches[3], $params);
        }
        
        return [
            'type' => $matches[1] ?? '',
            'date' => $matches[2] ?? '',
            'page' => $params['page'] ?? 1,
            'level' => $params['level'] ?? null,
        ];
    }
}
```

### Resource with Real-time Subscriptions

```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Redis;

class MetricsResource extends McpResource
{
    public function getName(): string
    {
        return 'metrics';
    }
    
    public function getDescription(): string
    {
        return 'Real-time application metrics';
    }
    
    public function getUriTemplate(): string
    {
        return 'metrics://{metric_type}';
    }
    
    public function read(string $uri): array
    {
        preg_match('/metrics:\/\/(.+)/', $uri, $matches);
        $metricType = $matches[1] ?? '';
        
        $metrics = $this->getMetrics($metricType);
        
        return [
            'uri' => $uri,
            'name' => "Metrics: {$metricType}",
            'mimeType' => 'application/json',
            'text' => json_encode($metrics),
        ];
    }
    
    public function subscribe(string $uri): void
    {
        preg_match('/metrics:\/\/(.+)/', $uri, $matches);
        $metricType = $matches[1] ?? '';
        
        // Subscribe to Redis channel for real-time updates
        Redis::subscribe(["metrics.{$metricType}"], function ($message) use ($uri) {
            // Emit update to MCP client
            $this->emitUpdate($uri, json_decode($message, true));
        });
    }
    
    private function getMetrics(string $type): array
    {
        return match($type) {
            'cpu' => $this->getCpuMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'requests' => $this->getRequestMetrics(),
            default => throw new \InvalidArgumentException("Unknown metric type: {$type}"),
        };
    }
    
    private function getCpuMetrics(): array
    {
        return [
            'usage' => sys_getloadavg()[0],
            'cores' => swoole_cpu_num(),
            'timestamp' => now()->toISOString(),
        ];
    }
    
    private function getMemoryMetrics(): array
    {
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'timestamp' => now()->toISOString(),
        ];
    }
    
    private function getRequestMetrics(): array
    {
        return [
            'total' => Redis::get('metrics:requests:total') ?? 0,
            'per_minute' => Redis::get('metrics:requests:rpm') ?? 0,
            'average_time' => Redis::get('metrics:requests:avg_time') ?? 0,
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

## Creating Custom Prompts

Prompts are template systems that help AI clients generate contextual responses.

### Basic Prompt Structure

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class EmailPrompt extends McpPrompt
{
    public function getName(): string
    {
        return 'email_template';
    }
    
    public function getDescription(): string
    {
        return 'Generate professional email templates';
    }
    
    public function getArguments(): array
    {
        return [
            'type' => [
                'type' => 'string',
                'enum' => ['welcome', 'follow-up', 'apology', 'invitation'],
                'required' => true,
                'description' => 'Type of email to generate',
            ],
            'recipient_name' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Name of the recipient',
            ],
            'context' => [
                'type' => 'object',
                'required' => false,
                'description' => 'Additional context for the email',
            ],
        ];
    }
    
    public function generate(array $arguments): array
    {
        $template = $this->getTemplate($arguments['type']);
        $content = $this->fillTemplate($template, $arguments);
        
        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ];
    }
    
    private function getTemplate(string $type): string
    {
        return match($type) {
            'welcome' => 'Dear {recipient_name},\n\nWelcome to our service...',
            'follow-up' => 'Hi {recipient_name},\n\nI wanted to follow up on...',
            'apology' => 'Dear {recipient_name},\n\nI sincerely apologize for...',
            'invitation' => 'Dear {recipient_name},\n\nYou are cordially invited to...',
        };
    }
    
    private function fillTemplate(string $template, array $arguments): string
    {
        $replacements = [
            '{recipient_name}' => $arguments['recipient_name'],
        ];
        
        if (isset($arguments['context'])) {
            foreach ($arguments['context'] as $key => $value) {
                $replacements["{{$key}}"] = $value;
            }
        }
        
        return strtr($template, $replacements);
    }
}
```

### Advanced Prompt with AI Enhancement

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use App\Services\AiService;

class CodeReviewPrompt extends McpPrompt
{
    protected AiService $ai;
    
    public function __construct(AiService $ai)
    {
        $this->ai = $ai;
    }
    
    public function getName(): string
    {
        return 'code_review';
    }
    
    public function getDescription(): string
    {
        return 'Generate comprehensive code review prompts';
    }
    
    public function getArguments(): array
    {
        return [
            'language' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Programming language',
            ],
            'code' => [
                'type' => 'string',
                'required' => true,
                'description' => 'Code to review',
            ],
            'focus_areas' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Specific areas to focus on',
            ],
            'severity' => [
                'type' => 'string',
                'enum' => ['lenient', 'normal', 'strict'],
                'default' => 'normal',
            ],
        ];
    }
    
    public function generate(array $arguments): array
    {
        $systemPrompt = $this->buildSystemPrompt($arguments);
        $userPrompt = $this->buildUserPrompt($arguments);
        
        // Enhance with AI analysis if available
        $analysis = $this->ai->analyzeCode($arguments['code'], $arguments['language']);
        
        return [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => $userPrompt,
                ],
                [
                    'role' => 'assistant',
                    'content' => "Initial analysis:\n" . json_encode($analysis, JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }
    
    private function buildSystemPrompt(array $arguments): string
    {
        $severity = $arguments['severity'] ?? 'normal';
        
        $prompt = "You are an expert {$arguments['language']} code reviewer. ";
        
        $prompt .= match($severity) {
            'lenient' => 'Focus on major issues and provide encouraging feedback.',
            'strict' => 'Be thorough and critical, catching even minor issues.',
            default => 'Provide balanced, constructive feedback.',
        };
        
        if (!empty($arguments['focus_areas'])) {
            $areas = implode(', ', $arguments['focus_areas']);
            $prompt .= " Pay special attention to: {$areas}.";
        }
        
        return $prompt;
    }
    
    private function buildUserPrompt(array $arguments): string
    {
        return "Please review the following {$arguments['language']} code:\n\n```{$arguments['language']}\n{$arguments['code']}\n```";
    }
}
```

## Custom Transports

Create custom transport implementations for different communication protocols.

### WebSocket Transport Example

```php
<?php

namespace App\Mcp\Transports;

use JTD\LaravelMCP\Transport\TransportInterface;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class WebSocketTransport implements TransportInterface, MessageComponentInterface
{
    protected array $connections = [];
    protected $server;
    
    public function getName(): string
    {
        return 'websocket';
    }
    
    public function start(): void
    {
        $this->server = new \Ratchet\Server\IoServer(
            new \Ratchet\Http\HttpServer(
                new \Ratchet\WebSocket\WsServer($this)
            ),
            config('mcp-transports.websocket.port', 8080)
        );
        
        $this->server->run();
    }
    
    public function stop(): void
    {
        if ($this->server) {
            $this->server->loop->stop();
        }
    }
    
    public function receive(): array
    {
        // Handled via onMessage callback
        return [];
    }
    
    public function send(array $response): void
    {
        $json = json_encode($response);
        
        foreach ($this->connections as $connection) {
            $connection->send($json);
        }
    }
    
    public function onOpen(ConnectionInterface $conn)
    {
        $this->connections[$conn->resourceId] = $conn;
        \Log::info("WebSocket connection opened: {$conn->resourceId}");
    }
    
    public function onMessage(ConnectionInterface $from, $msg)
    {
        $request = json_decode($msg, true);
        
        // Process through MCP handler
        $response = app('mcp.handler')->handle($request);
        
        $from->send(json_encode($response));
    }
    
    public function onClose(ConnectionInterface $conn)
    {
        unset($this->connections[$conn->resourceId]);
        \Log::info("WebSocket connection closed: {$conn->resourceId}");
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        \Log::error("WebSocket error: {$e->getMessage()}");
        $conn->close();
    }
}
```

### gRPC Transport Example

```php
<?php

namespace App\Mcp\Transports;

use JTD\LaravelMCP\Transport\TransportInterface;
use Grpc\Server;

class GrpcTransport implements TransportInterface
{
    protected Server $server;
    
    public function getName(): string
    {
        return 'grpc';
    }
    
    public function start(): void
    {
        $this->server = new Server();
        $this->server->addHttp2Port('0.0.0.0:50051');
        $this->server->addService(McpService::class, new McpServiceImpl());
        $this->server->start();
    }
    
    public function stop(): void
    {
        $this->server->shutdown();
    }
    
    public function receive(): array
    {
        // Handled by gRPC service implementation
        return [];
    }
    
    public function send(array $response): void
    {
        // Handled by gRPC service implementation
    }
}
```

## Middleware Extensions

Add custom middleware to the MCP request processing pipeline.

### Rate Limiting Middleware

```php
<?php

namespace App\Mcp\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class McpRateLimitMiddleware
{
    public function handle($request, Closure $next)
    {
        $key = $this->resolveRequestKey($request);
        
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts())) {
            return $this->buildRateLimitResponse($key);
        }
        
        RateLimiter::hit($key, $this->decayMinutes() * 60);
        
        $response = $next($request);
        
        return $this->addRateLimitHeaders($response, $key);
    }
    
    protected function resolveRequestKey($request): string
    {
        $user = $request['user'] ?? 'anonymous';
        $method = $request['method'] ?? 'unknown';
        
        return "mcp:{$user}:{$method}";
    }
    
    protected function maxAttempts(): int
    {
        return config('laravel-mcp.rate_limit.max_attempts', 60);
    }
    
    protected function decayMinutes(): int
    {
        return config('laravel-mcp.rate_limit.decay_minutes', 1);
    }
    
    protected function buildRateLimitResponse(string $key): array
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32000,
                'message' => 'Rate limit exceeded',
                'data' => [
                    'retry_after' => $retryAfter,
                ],
            ],
            'id' => null,
        ];
    }
    
    protected function addRateLimitHeaders(array $response, string $key): array
    {
        $response['_meta'] = $response['_meta'] ?? [];
        $response['_meta']['rate_limit'] = [
            'limit' => $this->maxAttempts(),
            'remaining' => RateLimiter::remaining($key, $this->maxAttempts()),
            'reset' => RateLimiter::availableIn($key),
        ];
        
        return $response;
    }
}
```

### Audit Logging Middleware

```php
<?php

namespace App\Mcp\Middleware;

use Closure;
use App\Models\McpAuditLog;

class McpAuditMiddleware
{
    public function handle($request, Closure $next)
    {
        $startTime = microtime(true);
        
        // Create audit log entry
        $audit = McpAuditLog::create([
            'request_id' => $request['id'] ?? uniqid(),
            'method' => $request['method'] ?? 'unknown',
            'params' => json_encode($request['params'] ?? []),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'started_at' => now(),
        ]);
        
        try {
            $response = $next($request);
            
            // Update with response
            $audit->update([
                'response' => json_encode($response),
                'status' => 'success',
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'completed_at' => now(),
            ]);
            
            return $response;
        } catch (\Exception $e) {
            // Log failure
            $audit->update([
                'error' => $e->getMessage(),
                'status' => 'failed',
                'duration_ms' => (microtime(true) - $startTime) * 1000,
                'completed_at' => now(),
            ]);
            
            throw $e;
        }
    }
}
```

## Custom Validation Rules

Create custom validation rules for MCP request parameters.

### Custom Validation Rule

```php
<?php

namespace App\Mcp\Rules;

use Illuminate\Contracts\Validation\Rule;

class SafeSqlQuery implements Rule
{
    protected array $forbidden = [
        'DROP', 'DELETE', 'UPDATE', 'INSERT', 'CREATE', 'ALTER',
        'TRUNCATE', 'EXEC', 'EXECUTE', 'GRANT', 'REVOKE',
    ];
    
    public function passes($attribute, $value): bool
    {
        $upperValue = strtoupper($value);
        
        foreach ($this->forbidden as $keyword) {
            if (str_contains($upperValue, $keyword)) {
                return false;
            }
        }
        
        return true;
    }
    
    public function message(): string
    {
        return 'The :attribute contains forbidden SQL keywords.';
    }
}
```

### Using Custom Validation in Tools

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use App\Mcp\Rules\SafeSqlQuery;
use Illuminate\Support\Facades\Validator;

class QueryTool extends McpTool
{
    public function execute(array $arguments): mixed
    {
        $validator = Validator::make($arguments, [
            'query' => ['required', 'string', new SafeSqlQuery()],
        ]);
        
        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                $validator->errors()->first()
            );
        }
        
        // Execute safe query
        return DB::select($arguments['query']);
    }
}
```

## Event Listeners and Hooks

Hook into the MCP lifecycle with event listeners.

### Component Registration Listener

```php
<?php

namespace App\Listeners;

use JTD\LaravelMCP\Events\ComponentRegistered;
use Illuminate\Support\Facades\Log;

class LogComponentRegistration
{
    public function handle(ComponentRegistered $event): void
    {
        Log::info('MCP component registered', [
            'type' => $event->getType(),
            'name' => $event->getName(),
            'class' => get_class($event->getComponent()),
        ]);
        
        // Send notification to admin
        if ($event->getType() === 'tool') {
            $this->notifyAdminOfNewTool($event->getComponent());
        }
    }
    
    private function notifyAdminOfNewTool($tool): void
    {
        // Implementation
    }
}
```

### Request Processing Listener

```php
<?php

namespace App\Listeners;

use JTD\LaravelMCP\Events\McpRequestReceived;
use App\Services\MetricsService;

class TrackMcpMetrics
{
    protected MetricsService $metrics;
    
    public function __construct(MetricsService $metrics)
    {
        $this->metrics = $metrics;
    }
    
    public function handle(McpRequestReceived $event): void
    {
        $this->metrics->increment('mcp.requests.total');
        $this->metrics->increment("mcp.requests.method.{$event->getMethod()}");
        
        if ($event->hasUser()) {
            $this->metrics->trackUserActivity(
                $event->getUserId(),
                $event->getMethod()
            );
        }
    }
}
```

## Advanced Configuration

### Dynamic Configuration

```php
<?php

namespace App\Mcp\Config;

use JTD\LaravelMCP\Config\ConfiguratorInterface;

class DynamicMcpConfigurator implements ConfiguratorInterface
{
    public function configure(array $config): array
    {
        // Load configuration from database
        $dbConfig = \App\Models\McpConfig::first();
        
        if ($dbConfig) {
            $config['enabled'] = $dbConfig->enabled;
            $config['rate_limit'] = $dbConfig->rate_limit;
            $config['allowed_methods'] = $dbConfig->allowed_methods;
        }
        
        // Environment-specific overrides
        if (app()->environment('production')) {
            $config['debug'] = false;
            $config['cache_duration'] = 3600;
        }
        
        // Feature flags
        if (feature('mcp_advanced_tools')) {
            $config['discovery']['paths']['tools'][] = 'app/Mcp/AdvancedTools';
        }
        
        return $config;
    }
}
```

### Configuration Provider

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Mcp\Config\DynamicMcpConfigurator;

class McpConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('mcp.configurator', DynamicMcpConfigurator::class);
    }
    
    public function boot(): void
    {
        // Apply dynamic configuration
        $configurator = $this->app->make('mcp.configurator');
        $config = config('laravel-mcp');
        $dynamicConfig = $configurator->configure($config);
        
        config(['laravel-mcp' => $dynamicConfig]);
    }
}
```

## Plugin Development

Create reusable MCP plugins that can be shared across projects.

### Plugin Structure

```php
<?php

namespace YourVendor\McpPlugin;

use JTD\LaravelMCP\Plugin\McpPlugin;

class AnalyticsPlugin extends McpPlugin
{
    public function getName(): string
    {
        return 'analytics';
    }
    
    public function getVersion(): string
    {
        return '1.0.0';
    }
    
    public function register(): void
    {
        // Register plugin services
        $this->app->singleton(AnalyticsService::class);
        
        // Register tools
        $this->registerTool(AnalyticsTool::class);
        
        // Register resources
        $this->registerResource(AnalyticsResource::class);
        
        // Register middleware
        $this->registerMiddleware(AnalyticsMiddleware::class);
    }
    
    public function boot(): void
    {
        // Publish configuration
        $this->publishes([
            __DIR__.'/../config/analytics.php' => config_path('mcp-analytics.php'),
        ], 'mcp-analytics-config');
        
        // Load routes
        $this->loadRoutesFrom(__DIR__.'/../routes/analytics.php');
        
        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'mcp-analytics');
        
        // Register event listeners
        $this->registerEventListeners();
    }
    
    protected function registerEventListeners(): void
    {
        Event::listen(
            McpRequestReceived::class,
            [AnalyticsListener::class, 'handle']
        );
    }
}
```

### Plugin Service Provider

```php
<?php

namespace YourVendor\McpPlugin;

use Illuminate\Support\ServiceProvider;

class AnalyticsPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->register(AnalyticsPlugin::class);
    }
    
    public function boot(): void
    {
        // Additional boot logic if needed
    }
}
```

### Using Plugins

In your `config/app.php`:

```php
'providers' => [
    // Other providers...
    YourVendor\McpPlugin\AnalyticsPluginServiceProvider::class,
],
```

## Best Practices

### 1. Dependency Injection
Always use dependency injection for services:

```php
public function __construct(
    protected DatabaseService $database,
    protected CacheService $cache
) {}
```

### 2. Error Handling
Implement comprehensive error handling:

```php
public function execute(array $arguments): mixed
{
    try {
        return $this->performOperation($arguments);
    } catch (ValidationException $e) {
        throw new McpValidationException($e->getMessage());
    } catch (\Exception $e) {
        Log::error('Tool execution failed', [
            'tool' => $this->getName(),
            'error' => $e->getMessage(),
        ]);
        throw new McpExecutionException('Operation failed');
    }
}
```

### 3. Testing
Write comprehensive tests for your extensions:

```php
public function test_custom_tool_execution()
{
    $tool = new CustomTool();
    
    $result = $tool->execute([
        'param1' => 'value1',
        'param2' => 'value2',
    ]);
    
    $this->assertIsArray($result);
    $this->assertArrayHasKey('success', $result);
}
```

### 4. Documentation
Document your extensions thoroughly:

```php
/**
 * Custom weather tool for MCP
 * 
 * @mcp-tool weather
 * @mcp-param location string required The location to get weather for
 * @mcp-param units string optional Temperature units (celsius|fahrenheit)
 * @mcp-return array Weather information
 */
class WeatherTool extends McpTool
```

### 5. Performance
Optimize for performance:

```php
// Use caching for expensive operations
$cached = Cache::remember($key, 3600, function () {
    return $this->expensiveOperation();
});

// Use lazy loading
$this->tools = $this->tools ?? $this->loadTools();

// Use database query optimization
$results = DB::table('users')
    ->select(['id', 'name']) // Only select needed columns
    ->where('active', true)
    ->limit(100)
    ->get();
```

## Conclusion

The Laravel MCP package provides extensive extensibility options, allowing you to create custom tools, resources, prompts, transports, and middleware. By following the patterns and best practices outlined in this guide, you can build powerful MCP extensions that integrate seamlessly with your Laravel application.

For more examples and advanced use cases, check the `docs/examples/` directory and the package's test suite.