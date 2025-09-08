# Laravel Integration Specification

## Overview

The Laravel Integration specification defines how the MCP package seamlessly integrates with Laravel's ecosystem, leveraging framework features like dependency injection, middleware, validation, events, jobs, and other Laravel services.

## Dependency Injection Integration

### Service Container Integration
```php
<?php

namespace JTD\LaravelMCP\Integration;

use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;

class LaravelServiceBridge
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function resolveForMcp(string $abstract, array $parameters = [])
    {
        try {
            return $this->container->make($abstract, $parameters);
        } catch (BindingResolutionException $e) {
            throw new \RuntimeException("Failed to resolve service for MCP: {$abstract}", 0, $e);
        }
    }

    public function injectDependencies(object $mcpComponent): void
    {
        $reflection = new \ReflectionClass($mcpComponent);
        
        // Inject through constructor if needed
        $this->injectConstructorDependencies($mcpComponent, $reflection);
        
        // Inject through properties with attributes
        $this->injectPropertyDependencies($mcpComponent, $reflection);
        
        // Inject through setter methods
        $this->injectSetterDependencies($mcpComponent, $reflection);
    }

    private function injectConstructorDependencies(object $component, \ReflectionClass $reflection): void
    {
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return;
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();
            
            if ($type && !$type->isBuiltin()) {
                $parameters[] = $this->container->make($type->getName());
            }
        }

        if (!empty($parameters)) {
            $constructor->invokeArgs($component, $parameters);
        }
    }

    private function injectPropertyDependencies(object $component, \ReflectionClass $reflection): void
    {
        foreach ($reflection->getProperties() as $property) {
            $attributes = $property->getAttributes(Inject::class);
            
            if (!empty($attributes)) {
                $injectAttribute = $attributes[0]->newInstance();
                $service = $this->container->make($injectAttribute->service ?? $property->getType()->getName());
                
                $property->setAccessible(true);
                $property->setValue($component, $service);
            }
        }
    }

    private function injectSetterDependencies(object $component, \ReflectionClass $reflection): void
    {
        foreach ($reflection->getMethods() as $method) {
            if (str_starts_with($method->getName(), 'set') && $method->getNumberOfParameters() === 1) {
                $parameter = $method->getParameters()[0];
                $type = $parameter->getType();
                
                if ($type && !$type->isBuiltin() && $this->container->bound($type->getName())) {
                    $service = $this->container->make($type->getName());
                    $method->invoke($component, $service);
                }
            }
        }
    }
}

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Inject
{
    public function __construct(public ?string $service = null)
    {
    }
}
```

### Enhanced Base Classes with DI
```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use JTD\LaravelMCP\Integration\LaravelServiceBridge;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Logging\Log;

abstract class LaravelMcpTool extends McpTool
{
    protected ValidationFactory $validator;
    protected Dispatcher $events;
    protected Cache $cache;
    protected Log $logger;

    public function __construct()
    {
        parent::__construct();
        
        // Inject Laravel services
        $this->validator = $this->container->make(ValidationFactory::class);
        $this->events = $this->container->make(Dispatcher::class);
        $this->cache = $this->container->make(Cache::class);
        $this->logger = $this->container->make(Log::class);
    }

    protected function dispatch($event): void
    {
        $this->events->dispatch($event);
    }

    protected function cache(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        return $this->cache->remember($key, $ttl, $callback);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
```

## Validation Integration

### Laravel Validation Integration
```php
<?php

namespace JTD\LaravelMCP\Validation;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;

class McpValidator
{
    private Factory $validator;

    public function __construct(Factory $validator)
    {
        $this->validator = $validator;
    }

    public function validateMcpParameters(array $parameters, array $rules, array $messages = []): array
    {
        try {
            $validator = $this->validator->make($parameters, $rules, $messages);
            return $validator->validated();
        } catch (ValidationException $e) {
            throw new McpValidationException(
                'MCP parameter validation failed',
                $e->errors(),
                $e
            );
        }
    }

    public function validateToolParameters(string $toolName, array $parameters, array $schema): array
    {
        $rules = $this->convertSchemaToRules($schema);
        
        return $this->validateMcpParameters(
            $parameters,
            $rules,
            $this->generateValidationMessages($toolName, $schema)
        );
    }

    public function validateResourceParameters(string $resourceName, array $parameters, string $action = 'read'): array
    {
        $rules = $this->getResourceValidationRules($resourceName, $action);
        
        return $this->validateMcpParameters(
            $parameters,
            $rules,
            $this->getResourceValidationMessages($resourceName, $action)
        );
    }

    private function convertSchemaToRules(array $schema): array
    {
        $rules = [];
        
        foreach ($schema as $field => $config) {
            $rules[$field] = $this->buildFieldRules($config);
        }
        
        return $rules;
    }

    private function buildFieldRules(array $config): array
    {
        $rules = [];
        
        // Required/Optional
        if ($config['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        
        // Type validation
        match ($config['type'] ?? 'string') {
            'string' => $rules[] = 'string',
            'integer' => $rules[] = 'integer',
            'number' => $rules[] = 'numeric',
            'boolean' => $rules[] = 'boolean',
            'array' => $rules[] = 'array',
            'object' => $rules[] = 'array',
            default => null,
        };
        
        // Additional constraints
        if (isset($config['minLength'])) {
            $rules[] = 'min:' . $config['minLength'];
        }
        
        if (isset($config['maxLength'])) {
            $rules[] = 'max:' . $config['maxLength'];
        }
        
        if (isset($config['minimum'])) {
            $rules[] = 'min:' . $config['minimum'];
        }
        
        if (isset($config['maximum'])) {
            $rules[] = 'max:' . $config['maximum'];
        }
        
        if (isset($config['enum'])) {
            $rules[] = 'in:' . implode(',', $config['enum']);
        }
        
        return $rules;
    }

    private function generateValidationMessages(string $toolName, array $schema): array
    {
        $messages = [];
        
        foreach ($schema as $field => $config) {
            $fieldName = $config['description'] ?? $field;
            
            $messages["{$field}.required"] = "The {$fieldName} parameter is required for {$toolName}.";
            $messages["{$field}.string"] = "The {$fieldName} parameter must be a string.";
            $messages["{$field}.integer"] = "The {$fieldName} parameter must be an integer.";
            $messages["{$field}.numeric"] = "The {$fieldName} parameter must be numeric.";
            $messages["{$field}.boolean"] = "The {$fieldName} parameter must be true or false.";
            $messages["{$field}.array"] = "The {$fieldName} parameter must be an array.";
        }
        
        return $messages;
    }
}

class McpValidationException extends \Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [], \Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
```

## Middleware Integration

### MCP Middleware System
```php
<?php

namespace JTD\LaravelMCP\Middleware;

use JTD\LaravelMCP\Contracts\McpMiddlewareInterface;

abstract class McpMiddleware implements McpMiddlewareInterface
{
    public function handle(array $parameters, \Closure $next): mixed
    {
        $parameters = $this->before($parameters);
        $result = $next($parameters);
        return $this->after($result, $parameters);
    }

    protected function before(array $parameters): array
    {
        return $parameters;
    }

    protected function after(mixed $result, array $parameters): mixed
    {
        return $result;
    }
}

class AuthenticationMiddleware extends McpMiddleware
{
    protected function before(array $parameters): array
    {
        if (!auth()->check()) {
            throw new \UnauthorizedHttpException('Authentication required');
        }
        
        return $parameters;
    }
}

class RateLimitMiddleware extends McpMiddleware
{
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    protected function before(array $parameters): array
    {
        $key = $this->getRateLimitKey();
        
        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            throw new \HttpException(429, 'Too many requests');
        }
        
        RateLimiter::hit($key, $this->decayMinutes * 60);
        
        return $parameters;
    }

    private function getRateLimitKey(): string
    {
        return 'mcp:rate_limit:' . (auth()->id() ?? request()->ip());
    }
}

class CacheMiddleware extends McpMiddleware
{
    private int $ttl;

    public function __construct(int $ttl = 300) // 5 minutes default
    {
        $this->ttl = $ttl;
    }

    protected function before(array $parameters): array
    {
        $cacheKey = $this->getCacheKey($parameters);
        
        if ($cached = cache()->get($cacheKey)) {
            throw new CachedResultException($cached);
        }
        
        return $parameters;
    }

    protected function after(mixed $result, array $parameters): mixed
    {
        $cacheKey = $this->getCacheKey($parameters);
        cache()->put($cacheKey, $result, $this->ttl);
        
        return $result;
    }

    private function getCacheKey(array $parameters): string
    {
        return 'mcp:cache:' . md5(serialize($parameters));
    }
}
```

## Event System Integration

### MCP Events
```php
<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class McpToolExecuted
{
    use Dispatchable, SerializesModels;

    public string $toolName;
    public array $parameters;
    public mixed $result;
    public float $executionTime;
    public ?string $userId;

    public function __construct(
        string $toolName,
        array $parameters,
        mixed $result,
        float $executionTime,
        ?string $userId = null
    ) {
        $this->toolName = $toolName;
        $this->parameters = $parameters;
        $this->result = $result;
        $this->executionTime = $executionTime;
        $this->userId = $userId;
    }
}

class McpResourceAccessed
{
    use Dispatchable, SerializesModels;

    public string $resourceName;
    public string $action;
    public array $parameters;
    public ?string $userId;

    public function __construct(
        string $resourceName,
        string $action,
        array $parameters,
        ?string $userId = null
    ) {
        $this->resourceName = $resourceName;
        $this->action = $action;
        $this->parameters = $parameters;
        $this->userId = $userId;
    }
}

class McpPromptGenerated
{
    use Dispatchable, SerializesModels;

    public string $promptName;
    public array $arguments;
    public string $generatedContent;
    public ?string $userId;

    public function __construct(
        string $promptName,
        array $arguments,
        string $generatedContent,
        ?string $userId = null
    ) {
        $this->promptName = $promptName;
        $this->arguments = $arguments;
        $this->generatedContent = $generatedContent;
        $this->userId = $userId;
    }
}
```

### Event Listeners
```php
<?php

namespace JTD\LaravelMCP\Listeners;

use JTD\LaravelMCP\Events\McpToolExecuted;
use Illuminate\Contracts\Queue\ShouldQueue;

class LogMcpActivity implements ShouldQueue
{
    public function handle(McpToolExecuted $event): void
    {
        logger()->info('MCP Tool Executed', [
            'tool' => $event->toolName,
            'parameters' => $event->parameters,
            'execution_time' => $event->executionTime,
            'user_id' => $event->userId,
            'timestamp' => now()->toISOString(),
        ]);
    }
}

class TrackMcpUsage implements ShouldQueue
{
    public function handle(McpToolExecuted $event): void
    {
        // Track usage metrics
        $this->incrementUsageCounter($event->toolName);
        $this->recordExecutionTime($event->toolName, $event->executionTime);
        $this->trackUserActivity($event->userId, $event->toolName);
    }

    private function incrementUsageCounter(string $toolName): void
    {
        cache()->increment("mcp:usage:tool:{$toolName}:count");
        cache()->increment("mcp:usage:tool:{$toolName}:daily:" . now()->format('Y-m-d'));
    }

    private function recordExecutionTime(string $toolName, float $time): void
    {
        $key = "mcp:performance:tool:{$toolName}:times";
        $times = cache()->get($key, []);
        $times[] = $time;
        
        // Keep only last 100 execution times
        if (count($times) > 100) {
            $times = array_slice($times, -100);
        }
        
        cache()->put($key, $times, now()->addHours(24));
    }

    private function trackUserActivity(?string $userId, string $toolName): void
    {
        if ($userId) {
            cache()->increment("mcp:user:{$userId}:tool_usage");
            cache()->sadd("mcp:user:{$userId}:tools_used", $toolName);
        }
    }
}
```

## Queue Integration

### Background Job Processing
```php
<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelMCP\Registry\McpRegistry;

class ProcessMcpToolAsync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $toolName;
    public array $parameters;
    public string $requestId;
    public int $tries = 3;
    public int $timeout = 300; // 5 minutes

    public function __construct(string $toolName, array $parameters, string $requestId)
    {
        $this->toolName = $toolName;
        $this->parameters = $parameters;
        $this->requestId = $requestId;
    }

    public function handle(McpRegistry $registry): void
    {
        $tool = $registry->getTool($this->toolName);
        
        if (!$tool) {
            $this->fail(new \RuntimeException("Tool not found: {$this->toolName}"));
            return;
        }

        try {
            $result = $tool->execute($this->parameters);
            
            // Store result for retrieval
            cache()->put(
                "mcp:async_result:{$this->requestId}",
                [
                    'status' => 'completed',
                    'result' => $result,
                    'completed_at' => now()->toISOString(),
                ],
                now()->addHours(1)
            );
            
            // Notify completion
            broadcast(new AsyncToolCompleted($this->requestId, $result));
            
        } catch (\Throwable $e) {
            cache()->put(
                "mcp:async_result:{$this->requestId}",
                [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'failed_at' => now()->toISOString(),
                ],
                now()->addHours(1)
            );
            
            broadcast(new AsyncToolFailed($this->requestId, $e->getMessage()));
            
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('Async MCP tool execution failed', [
            'tool' => $this->toolName,
            'parameters' => $this->parameters,
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

// Support for long-running tools
trait SupportsAsyncExecution
{
    protected bool $runAsync = false;
    protected ?string $queue = null;

    public function executeAsync(array $parameters): string
    {
        $requestId = Str::uuid()->toString();
        
        ProcessMcpToolAsync::dispatch(
            $this->getName(),
            $parameters,
            $requestId
        )->onQueue($this->queue ?? 'mcp-tools');
        
        return $requestId;
    }

    public function getAsyncResult(string $requestId): ?array
    {
        return cache()->get("mcp:async_result:{$requestId}");
    }
}
```

## Database Integration

### Eloquent Model Integration
```php
<?php

namespace JTD\LaravelMCP\Integration;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

trait McpModelIntegration
{
    public function toMcpResource(): array
    {
        $data = $this->toArray();
        
        // Apply MCP-specific transformations
        return $this->transformForMcp($data);
    }

    public static function fromMcpParameters(array $parameters): static
    {
        $fillable = (new static)->getFillable();
        $attributes = array_intersect_key($parameters, array_flip($fillable));
        
        return new static($attributes);
    }

    public function scopeForMcp(Builder $query): Builder
    {
        // Apply MCP-specific scopes
        return $query->where('mcp_accessible', true);
    }

    protected function transformForMcp(array $data): array
    {
        // Remove sensitive attributes
        $hidden = $this->getHidden();
        foreach ($hidden as $attribute) {
            unset($data[$attribute]);
        }
        
        // Add computed attributes for MCP
        if (method_exists($this, 'getMcpAttributes')) {
            $data = array_merge($data, $this->getMcpAttributes());
        }
        
        return $data;
    }
}

// Example usage in a model
class User extends Model
{
    use McpModelIntegration;
    
    protected $fillable = ['name', 'email'];
    protected $hidden = ['password', 'remember_token'];
    
    public function getMcpAttributes(): array
    {
        return [
            'display_name' => $this->name,
            'avatar_url' => $this->getAvatarUrl(),
            'is_online' => $this->isOnline(),
        ];
    }
}
```

### Database Query Tools
```php
<?php

namespace JTD\LaravelMCP\Tools;

use JTD\LaravelMCP\Abstracts\LaravelMcpTool;
use Illuminate\Database\Query\Builder;

class DatabaseQueryTool extends LaravelMcpTool
{
    protected string $name = 'db_query';
    protected string $description = 'Execute safe database queries';
    protected array $parameterSchema = [
        'table' => [
            'type' => 'string',
            'description' => 'Table name to query',
            'required' => true,
        ],
        'columns' => [
            'type' => 'array',
            'description' => 'Columns to select',
            'required' => false,
        ],
        'where' => [
            'type' => 'array',
            'description' => 'Where conditions',
            'required' => false,
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of records',
            'minimum' => 1,
            'maximum' => 100,
            'required' => false,
        ],
    ];
    protected bool $requiresAuth = true;

    protected function handle(array $parameters): mixed
    {
        $this->validateTableAccess($parameters['table']);
        
        $query = DB::table($parameters['table']);
        
        if (isset($parameters['columns'])) {
            $query->select($parameters['columns']);
        }
        
        if (isset($parameters['where'])) {
            foreach ($parameters['where'] as $condition) {
                $query->where($condition['column'], $condition['operator'] ?? '=', $condition['value']);
            }
        }
        
        $limit = $parameters['limit'] ?? 50;
        
        return $query->limit($limit)->get()->toArray();
    }

    private function validateTableAccess(string $table): void
    {
        $allowedTables = config('laravel-mcp.database.allowed_tables', []);
        
        if (!empty($allowedTables) && !in_array($table, $allowedTables)) {
            throw new \UnauthorizedHttpException("Access to table '{$table}' is not allowed");
        }
        
        $forbiddenTables = config('laravel-mcp.database.forbidden_tables', [
            'password_resets', 'sessions', 'personal_access_tokens'
        ]);
        
        if (in_array($table, $forbiddenTables)) {
            throw new \UnauthorizedHttpException("Access to table '{$table}' is forbidden");
        }
    }
}
```

## Configuration Integration

### Environment-Based Configuration
```php
return [
    'laravel_integration' => [
        'dependency_injection' => [
            'enabled' => env('MCP_DI_ENABLED', true),
            'auto_inject' => env('MCP_AUTO_INJECT', true),
        ],
        
        'validation' => [
            'use_laravel_validator' => env('MCP_USE_LARAVEL_VALIDATION', true),
            'custom_rules_path' => env('MCP_CUSTOM_RULES_PATH', 'app/Rules'),
        ],
        
        'middleware' => [
            'enabled' => env('MCP_MIDDLEWARE_ENABLED', true),
            'global_middleware' => explode(',', env('MCP_GLOBAL_MIDDLEWARE', '')),
            'aliases' => [
                'auth' => AuthenticationMiddleware::class,
                'rate_limit' => RateLimitMiddleware::class,
                'cache' => CacheMiddleware::class,
            ],
        ],
        
        'events' => [
            'enabled' => env('MCP_EVENTS_ENABLED', true),
            'listeners' => [
                McpToolExecuted::class => [
                    LogMcpActivity::class,
                    TrackMcpUsage::class,
                ],
            ],
        ],
        
        'database' => [
            'allowed_tables' => explode(',', env('MCP_ALLOWED_TABLES', '')),
            'forbidden_tables' => explode(',', env('MCP_FORBIDDEN_TABLES', 'password_resets,sessions')),
            'max_query_limit' => env('MCP_MAX_QUERY_LIMIT', 100),
        ],
        
        'queue' => [
            'enabled' => env('MCP_QUEUE_ENABLED', true),
            'default_queue' => env('MCP_DEFAULT_QUEUE', 'mcp-tools'),
            'async_timeout' => env('MCP_ASYNC_TIMEOUT', 300),
        ],
    ],
];
```

## Testing Integration

### Laravel Testing Support
```php
<?php

namespace JTD\LaravelMCP\Testing;

use Illuminate\Foundation\Testing\TestCase;
use JTD\LaravelMCP\Registry\McpRegistry;

trait McpTestingHelpers
{
    protected function registerMockTool(string $name, $handler): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $registry->register('tool', $name, $handler);
    }

    protected function assertToolExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('tool', $name));
    }

    protected function assertToolExecutes(string $name, array $parameters, $expectedResult = null): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);
        
        $this->assertNotNull($tool);
        
        $result = $tool->execute($parameters);
        
        if ($expectedResult !== null) {
            $this->assertEquals($expectedResult, $result);
        }
    }

    protected function mockMcpRequest(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];
    }

    protected function assertMcpResponse(array $response, $expectedResult = null): void
    {
        $this->assertArrayHasKey('jsonrpc', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('id', $response);
        
        if ($expectedResult !== null) {
            $this->assertArrayHasKey('result', $response);
            $this->assertEquals($expectedResult, $response['result']);
        }
    }
}
```