# Base Classes Specification

## Overview

The base classes provide the foundation for MCP Tools, Resources, and Prompts in Laravel applications. They implement the MCP 1.0 specification while adding Laravel-specific features like dependency injection, validation, authorization, and integration with Laravel's ecosystem.

## Abstract Base Classes Architecture

### BaseComponent Abstract Class

The `BaseComponent` class provides shared functionality for all MCP components, including dependency injection, validation, authorization, and Laravel integration.

```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use JTD\LaravelMCP\Traits\ValidatesParameters;

abstract class BaseComponent
{
    use HandlesMcpRequests, ManagesCapabilities, ValidatesParameters;

    protected Container $container;
    protected ValidationFactory $validator;
    protected string $name;
    protected string $description;
    protected array $middleware = [];
    protected bool $requiresAuth = false;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(ValidationFactory::class);

        $this->boot();
    }

    protected function boot(): void
    {
        // Override in child classes for initialization
    }

    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    public function getDescription(): string
    {
        return $this->description ?? 'MCP Component';
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function requiresAuth(): bool
    {
        return $this->requiresAuth;
    }

    protected function authorize(array $params = [], ?string $action = null): bool
    {
        if (!$this->requiresAuth) {
            return true;
        }

        // Default authorization logic - can be overridden in child classes
        return true;
    }

    private function generateNameFromClass(): string
    {
        $className = class_basename($this);

        // Remove common suffixes
        $suffixes = ['Tool', 'Resource', 'Prompt', 'Component'];
        foreach ($suffixes as $suffix) {
            if (Str::endsWith($className, $suffix)) {
                $className = str_replace($suffix, '', $className);
                break;
            }
        }

        return Str::snake($className);
    }

    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    protected function resolve(string $abstract)
    {
        return $this->container->make($abstract);
    }

    protected function applyComponentMiddleware(array $params): array
    {
        foreach ($this->middleware as $middleware) {
            $params = $this->applyMiddleware($middleware, $params);
        }

        return $params;
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        if (config('laravel-mcp.logging.enabled', false)) {
            logger()->{$level}($message, array_merge([
                'component' => static::class,
                'name' => $this->getName(),
            ], $context));
        }
    }

    protected function fireEvent(string $event, array $payload = []): void
    {
        if (function_exists('event')) {
            event($event, array_merge(['component' => $this], $payload));
        }
    }

    public function getMetadata(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'class' => static::class,
            'requiresAuth' => $this->requiresAuth(),
            'middleware' => $this->getMiddleware(),
            'capabilities' => $this->getCapabilities(),
        ];
    }

    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
    }

    /**
     * Get the component type for this MCP component.
     * 
     * @return string The component type (e.g., 'tool', 'resource', 'prompt')
     */
    abstract protected function getComponentType(): string;
}
```

### McpTool Base Class
```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

abstract class McpTool extends BaseComponent
{
    use HandlesMcpRequests, ValidatesParameters, ManagesCapabilities;

    protected Container $container;
    protected ValidationFactory $validator;
    protected string $name;
    protected string $description;
    protected array $parameterSchema = [];
    protected array $middleware = [];
    protected bool $requiresAuth = false;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(ValidationFactory::class);
        
        $this->boot();
    }

    protected function boot(): void
    {
        // Override in child classes for initialization
    }

    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    public function getDescription(): string
    {
        return $this->description ?? 'MCP Tool';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => $this->getParameterSchema(),
            'required' => $this->getRequiredParameters(),
        ];
    }

    protected function getParameterSchema(): array
    {
        return $this->parameterSchema;
    }

    protected function getRequiredParameters(): array
    {
        return array_keys(array_filter($this->parameterSchema, function ($schema) {
            return $schema['required'] ?? false;
        }));
    }

    public function execute(array $parameters): mixed
    {
        // 1. Authorize the request
        if (!$this->authorize($parameters)) {
            throw new UnauthorizedHttpException('Unauthorized tool execution');
        }

        // 2. Validate parameters
        $validatedParams = $this->validateParameters($parameters);

        // 3. Apply middleware
        foreach ($this->middleware as $middleware) {
            $validatedParams = $this->applyMiddleware($middleware, $validatedParams);
        }

        // 4. Execute the tool
        return $this->handle($validatedParams);
    }

    abstract protected function handle(array $parameters): mixed;

    protected function authorize(array $parameters): bool
    {
        if (!$this->requiresAuth) {
            return true;
        }

        // Default authorization logic
        return true;
    }

    private function generateNameFromClass(): string
    {
        $className = class_basename($this);
        return Str::snake(str_replace('Tool', '', $className));
    }

    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    protected function resolve(string $abstract)
    {
        return $this->container->resolve($abstract);
    }
}
```

### McpResource Base Class
```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

abstract class McpResource extends BaseComponent
{
    use HandlesMcpRequests, ValidatesParameters;

    protected Container $container;
    protected string $name;
    protected string $description;
    protected string $uriTemplate;
    protected ?string $modelClass = null;
    protected array $middleware = [];
    protected bool $requiresAuth = false;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->boot();
    }

    protected function boot(): void
    {
        // Override in child classes for initialization
    }

    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    public function getDescription(): string
    {
        return $this->description ?? 'MCP Resource';
    }

    public function getUriTemplate(): string
    {
        return $this->uriTemplate ?? $this->generateUriTemplate();
    }

    public function read(array $params): mixed
    {
        if (!$this->authorize('read', $params)) {
            throw new UnauthorizedHttpException('Unauthorized resource access');
        }

        $validatedParams = $this->validateParameters($params, 'read');
        
        return $this->handleRead($validatedParams);
    }

    public function list(array $params = []): array
    {
        if (!$this->authorize('list', $params)) {
            throw new UnauthorizedHttpException('Unauthorized resource listing');
        }

        $validatedParams = $this->validateParameters($params, 'list');
        
        return $this->handleList($validatedParams);
    }

    public function subscribe(array $params): mixed
    {
        if (!$this->supportsSubscription()) {
            throw new \BadMethodCallException('Resource does not support subscriptions');
        }

        if (!$this->authorize('subscribe', $params)) {
            throw new UnauthorizedHttpException('Unauthorized subscription');
        }

        return $this->handleSubscribe($params);
    }

    protected function handleRead(array $params): mixed
    {
        if ($this->modelClass) {
            return $this->readFromModel($params);
        }

        return $this->customRead($params);
    }

    protected function handleList(array $params): array
    {
        if ($this->modelClass) {
            return $this->listFromModel($params);
        }

        return $this->customList($params);
    }

    protected function readFromModel(array $params): mixed
    {
        $model = $this->make($this->modelClass);
        
        if (isset($params['id'])) {
            return $model->findOrFail($params['id'])->toArray();
        }

        return $model->first()?->toArray();
    }

    protected function listFromModel(array $params): array
    {
        $model = $this->make($this->modelClass);
        $query = $model->newQuery();

        // Apply filters
        if (isset($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        // Apply pagination
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;
        
        return $query->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    protected function customRead(array $params): mixed
    {
        throw new \BadMethodCallException('Custom read method not implemented');
    }

    protected function customList(array $params): array
    {
        throw new \BadMethodCallException('Custom list method not implemented');
    }

    protected function handleSubscribe(array $params): mixed
    {
        // Default subscription handling
        return ['subscribed' => true, 'resource' => $this->getName()];
    }

    protected function supportsSubscription(): bool
    {
        return false;
    }

    protected function authorize(string $action, array $params): bool
    {
        if (!$this->requiresAuth) {
            return true;
        }

        // Default authorization logic
        return true;
    }

    private function generateNameFromClass(): string
    {
        $className = class_basename($this);
        return Str::snake(str_replace('Resource', '', $className));
    }

    private function generateUriTemplate(): string
    {
        $name = $this->getName();
        return "{$name}/*";
    }

    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }
}
```

### McpPrompt Base Class
```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Illuminate\Container\Container;
use Illuminate\View\Factory as ViewFactory;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

abstract class McpPrompt extends BaseComponent
{
    use HandlesMcpRequests, ValidatesParameters;

    protected Container $container;
    protected ViewFactory $view;
    protected string $name;
    protected string $description;
    protected array $arguments = [];
    protected ?string $template = null;
    protected array $middleware = [];
    protected bool $requiresAuth = false;

    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->view = $this->container->make(ViewFactory::class);
        $this->boot();
    }

    protected function boot(): void
    {
        // Override in child classes for initialization
    }

    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    public function getDescription(): string
    {
        return $this->description ?? 'MCP Prompt';
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function get(array $arguments = []): array
    {
        if (!$this->authorize($arguments)) {
            throw new UnauthorizedHttpException('Unauthorized prompt access');
        }

        $validatedArgs = $this->validateArguments($arguments);
        
        return $this->handleGet($validatedArgs);
    }

    protected function handleGet(array $arguments): array
    {
        $content = $this->generateContent($arguments);
        
        return [
            'description' => $this->getDescription(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $content,
                    ],
                ],
            ],
        ];
    }

    protected function generateContent(array $arguments): string
    {
        if ($this->template) {
            return $this->renderTemplate($arguments);
        }

        return $this->customContent($arguments);
    }

    protected function renderTemplate(array $arguments): string
    {
        if (str_contains($this->template, '.blade.php') || $this->view->exists($this->template)) {
            return $this->view->make($this->template, $arguments)->render();
        }

        // Simple string template
        $content = $this->template;
        foreach ($arguments as $key => $value) {
            $content = str_replace("{{$key}}", $value, $content);
        }
        
        return $content;
    }

    protected function customContent(array $arguments): string
    {
        throw new \BadMethodCallException('Custom content method not implemented');
    }

    protected function validateArguments(array $arguments): array
    {
        if (empty($this->arguments)) {
            return $arguments;
        }

        $rules = $this->buildValidationRules();
        
        return $this->validator->make($arguments, $rules)->validated();
    }

    private function buildValidationRules(): array
    {
        $rules = [];
        
        foreach ($this->arguments as $name => $config) {
            $rules[$name] = $this->buildArgumentRule($config);
        }
        
        return $rules;
    }

    private function buildArgumentRule(array $config): string
    {
        $rules = [];
        
        if ($config['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        
        switch ($config['type'] ?? 'string') {
            case 'string':
                $rules[] = 'string';
                if (isset($config['max_length'])) {
                    $rules[] = "max:{$config['max_length']}";
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
        }
        
        return implode('|', $rules);
    }

    protected function authorize(array $arguments): bool
    {
        if (!$this->requiresAuth) {
            return true;
        }

        // Default authorization logic
        return true;
    }

    private function generateNameFromClass(): string
    {
        $className = class_basename($this);
        return Str::snake(str_replace('Prompt', '', $className));
    }

    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }
}
```

## Supporting Traits

### HandlesMcpRequests Trait
```php
<?php

namespace JTD\LaravelMCP\Traits;

use JTD\LaravelMCP\Exceptions\McpException;

trait HandlesMcpRequests
{
    protected function handleError(\Throwable $e): void
    {
        logger()->error('MCP Component Error', [
            'component' => static::class,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new McpException($e->getMessage(), $e->getCode(), $e);
    }

    protected function logRequest(string $action, array $params): void
    {
        if (config('laravel-mcp.logging.requests', false)) {
            logger()->info('MCP Request', [
                'component' => static::class,
                'action' => $action,
                'parameters' => $params,
            ]);
        }
    }

    protected function applyMiddleware(string $middleware, array $params): array
    {
        $middlewareClass = $this->resolveMiddleware($middleware);
        
        if (!$middlewareClass) {
            return $params;
        }

        return $middlewareClass->handle($params, function ($params) {
            return $params;
        });
    }

    private function resolveMiddleware(string $middleware): ?object
    {
        if (class_exists($middleware)) {
            return $this->make($middleware);
        }

        // Check middleware aliases
        $aliases = config('laravel-mcp.middleware.aliases', []);
        if (isset($aliases[$middleware])) {
            return $this->make($aliases[$middleware]);
        }

        return null;
    }
}
```

### ValidatesParameters Trait
```php
<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Validation\ValidationException;

trait ValidatesParameters
{
    protected function validateParameters(array $parameters, ?string $context = null): array
    {
        $rules = $this->getValidationRules($context);
        
        if (empty($rules)) {
            return $parameters;
        }

        try {
            return $this->validator->make($parameters, $rules)->validated();
        } catch (ValidationException $e) {
            throw new \InvalidArgumentException('Parameter validation failed: ' . $e->getMessage());
        }
    }

    protected function getValidationRules(?string $context = null): array
    {
        if ($context && method_exists($this, "get{$context}ValidationRules")) {
            return $this->{"get{$context}ValidationRules"}();
        }

        return $this->buildRulesFromSchema();
    }

    private function buildRulesFromSchema(): array
    {
        if (!isset($this->parameterSchema)) {
            return [];
        }

        $rules = [];
        
        foreach ($this->parameterSchema as $field => $schema) {
            $rules[$field] = $this->buildFieldRule($schema);
        }
        
        return $rules;
    }

    private function buildFieldRule(array $schema): string
    {
        $rules = [];
        
        if ($schema['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }
        
        switch ($schema['type'] ?? 'string') {
            case 'string':
                $rules[] = 'string';
                if (isset($schema['maxLength'])) {
                    $rules[] = "max:{$schema['maxLength']}";
                }
                if (isset($schema['minLength'])) {
                    $rules[] = "min:{$schema['minLength']}";
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                if (isset($schema['minimum'])) {
                    $rules[] = "min:{$schema['minimum']}";
                }
                if (isset($schema['maximum'])) {
                    $rules[] = "max:{$schema['maximum']}";
                }
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
            case 'object':
                $rules[] = 'array';
                break;
        }
        
        if (isset($schema['enum'])) {
            $values = implode(',', $schema['enum']);
            $rules[] = "in:$values";
        }
        
        return implode('|', $rules);
    }
}
```

### ManagesCapabilities Trait
```php
<?php

namespace JTD\LaravelMCP\Traits;

trait ManagesCapabilities
{
    protected array $capabilities = [];

    public function getCapabilities(): array
    {
        return array_merge($this->getDefaultCapabilities(), $this->capabilities);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities());
    }

    public function addCapability(string $capability): self
    {
        if (!in_array($capability, $this->capabilities)) {
            $this->capabilities[] = $capability;
        }

        return $this;
    }

    public function removeCapability(string $capability): self
    {
        $this->capabilities = array_filter($this->capabilities, fn($c) => $c !== $capability);
        return $this;
    }

    protected function getDefaultCapabilities(): array
    {
        return match (true) {
            is_subclass_of(static::class, McpTool::class) => ['execute'],
            is_subclass_of(static::class, McpResource::class) => ['read', 'list'],
            is_subclass_of(static::class, McpPrompt::class) => ['get'],
            default => [],
        };
    }
}
```

### FormatsResponses Trait

The `FormatsResponses` trait provides comprehensive response formatting functionality for MCP components, ensuring consistent response structures across all tools, resources, and prompts.

```php
<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

trait FormatsResponses
{
    protected function formatSuccess($data = null, array $meta = []): array
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $this->formatData($data);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        if ($this->shouldIncludeTimestamp()) {
            $response['timestamp'] = now()->toIso8601String();
        }

        if ($this->isDebugMode()) {
            $response['_debug'] = $this->getDebugInfo();
        }

        return $response;
    }

    protected function formatError(
        string $message,
        int $code = -32603,
        $data = null,
        array $meta = []
    ): array {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $response['error']['data'] = $this->formatData($data);
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        if ($this->shouldIncludeTimestamp()) {
            $response['timestamp'] = now()->toIso8601String();
        }

        if ($this->isDebugMode()) {
            $response['_debug'] = $this->getDebugInfo();
        }

        return $response;
    }

    protected function formatToolResponse($result, array $meta = []): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->formatToolResult($result),
                ],
            ],
            'meta' => array_merge([
                'tool' => $this->getName(),
                'executed_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    protected function formatResourceReadResponse($data, string $uri, array $meta = []): array
    {
        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'mimeType' => $this->getMimeType($data),
                    'text' => $this->formatResourceData($data),
                ],
            ],
            'meta' => array_merge([
                'resource' => $this->getName(),
                'read_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    protected function formatResourceListResponse(array $items, array $meta = []): array
    {
        $formattedItems = [];

        foreach ($items as $item) {
            $formattedItems[] = $this->formatResourceListItem($item);
        }

        return [
            'resources' => $formattedItems,
            'meta' => array_merge([
                'resource' => $this->getName(),
                'count' => count($formattedItems),
                'listed_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    protected function formatPromptResponse(string $content, array $meta = []): array
    {
        return [
            'description' => $this->getDescription(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $content,
                    ],
                ],
            ],
            'meta' => array_merge([
                'prompt' => $this->getName(),
                'generated_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    protected function formatJsonRpcResponse($result, $id = null): array
    {
        $response = [
            'jsonrpc' => '2.0',
        ];

        if ($result instanceof \Throwable) {
            $response['error'] = [
                'code' => $result->getCode() ?: -32603,
                'message' => $result->getMessage(),
            ];

            if ($this->isDebugMode()) {
                $response['error']['data'] = [
                    'type' => get_class($result),
                    'trace' => $result->getTraceAsString(),
                ];
            }
        } else {
            $response['result'] = $result;
        }

        if ($id !== null) {
            $response['id'] = $id;
        }

        return $response;
    }

    protected function formatData($data)
    {
        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return $data->toArray();
            }

            if ($data instanceof \JsonSerializable) {
                return $data->jsonSerialize();
            }

            return (array) $data;
        }

        if (is_iterable($data) && !is_array($data)) {
            return collect($data)->toArray();
        }

        return $data;
    }

    protected function shouldIncludeTimestamp(): bool
    {
        return Config::get('laravel-mcp.response.include_timestamp', true);
    }

    protected function isDebugMode(): bool
    {
        return Config::get('app.debug', false) && Config::get('laravel-mcp.debug', false);
    }

    protected function getDebugInfo(): array
    {
        return [
            'component' => static::class,
            'type' => $this->getComponentType(),
            'name' => $this->getName(),
            'memory_usage' => memory_get_usage(true),
            'execution_time' => defined('LARAVEL_START')
                ? microtime(true) - LARAVEL_START
                : null,
        ];
    }
}
```

### LogsOperations Trait

The `LogsOperations` trait provides comprehensive logging functionality for MCP components, including request/response logging, performance tracking, error logging, and debug information collection.

```php
<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

trait LogsOperations
{
    protected ?string $logChannel = null;
    protected array $performanceData = [];
    protected ?float $operationStartTime = null;

    protected function logOperation(string $operation, array $data = [], string $level = LogLevel::INFO): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $context = $this->buildLogContext($operation, $data);

        $this->getLogger()->log($level, "MCP Operation: {$operation}", $context);
    }

    protected function logOperationStart(string $operation, array $params = []): void
    {
        $this->operationStartTime = microtime(true);

        $this->logOperation("{$operation}.start", [
            'parameters' => $this->sanitizeForLogging($params),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::DEBUG);
    }

    protected function logOperationComplete(string $operation, $result = null): void
    {
        $duration = $this->operationStartTime
            ? (microtime(true) - $this->operationStartTime) * 1000
            : null;

        $this->logOperation("{$operation}.complete", [
            'duration_ms' => $duration,
            'result_type' => gettype($result),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::DEBUG);

        if ($duration !== null) {
            $this->trackPerformance($operation, $duration);
        }
    }

    protected function logOperationError(string $operation, \Throwable $error, array $context = []): void
    {
        $duration = $this->operationStartTime
            ? (microtime(true) - $this->operationStartTime) * 1000
            : null;

        $this->logOperation("{$operation}.error", array_merge([
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_type' => get_class($error),
            'duration_ms' => $duration,
            'component' => $this->getComponentIdentifier(),
            'trace' => $this->shouldLogStackTrace() ? $error->getTraceAsString() : null,
        ], $context), LogLevel::ERROR);
    }

    protected function logRequest(string $method, array $params = []): void
    {
        if (!$this->shouldLogRequests()) {
            return;
        }

        $this->logOperation('request', [
            'method' => $method,
            'parameters' => $this->sanitizeForLogging($params),
            'component' => $this->getComponentIdentifier(),
            'request_id' => $this->generateRequestId(),
        ], LogLevel::INFO);
    }

    protected function logResponse(string $method, $response = null): void
    {
        if (!$this->shouldLogResponses()) {
            return;
        }

        $this->logOperation('response', [
            'method' => $method,
            'response_type' => gettype($response),
            'response_size' => $this->getDataSize($response),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::INFO);
    }

    protected function buildLogContext(string $operation, array $data = []): array
    {
        $context = [
            'operation' => $operation,
            'component_type' => $this->getComponentType(),
            'component_name' => $this->getName(),
            'timestamp' => now()->toIso8601String(),
        ];

        if (app()->has('request')) {
            $request = app('request');
            $context['request_id'] = $request->header('X-Request-ID');
            $context['user_agent'] = $request->userAgent();
            $context['ip'] = $request->ip();
        }

        if ($userId = $this->getCurrentUserId()) {
            $context['user_id'] = $userId;
        }

        return array_merge($context, $data);
    }

    protected function sanitizeForLogging($data): array
    {
        if (!is_array($data)) {
            return ['value' => '[non-array data]'];
        }

        $sensitiveFields = Config::get('laravel-mcp.logging.sensitive_fields', [
            'password', 'token', 'secret', 'api_key', 'private_key',
            'access_token', 'refresh_token', 'credit_card', 'ssn',
        ]);

        $sanitized = $data;

        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveFields) {
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });

        return $sanitized;
    }

    protected function getLogger(): \Psr\Log\LoggerInterface
    {
        $channel = $this->logChannel ?? Config::get('laravel-mcp.logging.channel', 'mcp');

        if (!Config::has("logging.channels.{$channel}")) {
            Config::set("logging.channels.{$channel}", [
                'driver' => 'daily',
                'path' => storage_path("logs/{$channel}.log"),
                'level' => 'debug',
                'days' => 14,
            ]);
        }

        return Log::channel($channel);
    }

    protected function shouldLog(string $level): bool
    {
        if (!Config::get('laravel-mcp.logging.enabled', true)) {
            return false;
        }

        $configuredLevel = Config::get('laravel-mcp.logging.level', LogLevel::INFO);
        $levels = [
            LogLevel::EMERGENCY => 0, LogLevel::ALERT => 1, LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3, LogLevel::WARNING => 4, LogLevel::NOTICE => 5,
            LogLevel::INFO => 6, LogLevel::DEBUG => 7,
        ];

        return ($levels[$level] ?? 6) <= ($levels[$configuredLevel] ?? 6);
    }

    protected function shouldLogRequests(): bool
    {
        return Config::get('laravel-mcp.logging.log_requests', true);
    }

    protected function shouldLogResponses(): bool
    {
        return Config::get('laravel-mcp.logging.log_responses', false);
    }
}
```

## Example Implementations

### Example Tool Implementation
```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class CalculatorTool extends McpTool
{
    protected string $name = 'calculator';
    protected string $description = 'Perform basic mathematical calculations';
    protected array $parameterSchema = [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
            'required' => true,
        ],
        'a' => [
            'type' => 'number',
            'description' => 'First operand',
            'required' => true,
        ],
        'b' => [
            'type' => 'number',
            'description' => 'Second operand',
            'required' => true,
        ],
    ];

    protected function handle(array $parameters): mixed
    {
        ['operation' => $op, 'a' => $a, 'b' => $b] = $parameters;

        $result = match ($op) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
            default => throw new \InvalidArgumentException('Unknown operation'),
        };

        return $this->formatToolResponse($result, [
            'operation' => $op,
            'operands' => [$a, $b],
        ]);
    }

    protected function getComponentType(): string
    {
        return 'tool';
    }
}
```

### Example Resource Implementation
```php
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use App\Models\User;

class UserResource extends McpResource
{
    protected string $name = 'users';
    protected string $description = 'Access user data';
    protected string $uriTemplate = 'users/{id?}';
    protected string $modelClass = User::class;
    protected bool $requiresAuth = true;

    protected function authorize(array $params = [], ?string $action = null): bool
    {
        // Custom authorization logic
        return auth()->check();
    }

    protected function customList(array $params): array
    {
        // Add custom filtering or transformation
        $users = $this->listFromModel($params);
        
        // Remove sensitive data
        foreach ($users['data'] as &$user) {
            unset($user['password'], $user['remember_token']);
        }
        
        return $this->formatResourceListResponse($users['data'], [
            'total' => $users['total'] ?? count($users['data']),
            'filtered' => true,
        ]);
    }

    protected function customRead(array $params): mixed
    {
        $user = $this->readFromModel($params);
        
        // Remove sensitive data
        unset($user['password'], $user['remember_token']);
        
        return $this->formatResourceReadResponse(
            $user,
            "users/{$user['id']}",
            ['read_at' => now()->toIso8601String()]
        );
    }

    protected function getComponentType(): string
    {
        return 'resource';
    }
}
```

### Example Prompt Implementation
```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class EmailTemplatePrompt extends McpPrompt
{
    protected string $name = 'email_template';
    protected string $description = 'Generate email templates';
    protected array $arguments = [
        'type' => [
            'type' => 'string',
            'description' => 'Email type',
            'enum' => ['welcome', 'reset_password', 'notification'],
            'required' => true,
        ],
        'recipient_name' => [
            'type' => 'string',
            'description' => 'Recipient name',
            'required' => false,
        ],
    ];

    protected function customContent(array $arguments): string
    {
        $type = $arguments['type'];
        $name = $arguments['recipient_name'] ?? 'User';

        return match ($type) {
            'welcome' => "Welcome to our platform, {$name}! We're excited to have you on board.",
            'reset_password' => "Hi {$name}, you've requested to reset your password. Click the link below to proceed.",
            'notification' => "Hi {$name}, you have new notifications waiting for you.",
            default => "Hello {$name}, this is a generic email template.",
        };
    }

    protected function handleGet(array $arguments): array
    {
        $content = $this->generateContent($arguments);
        
        return $this->formatPromptResponse($content, [
            'template_type' => $arguments['type'] ?? 'generic',
            'character_count' => strlen($content),
        ]);
    }

    protected function getComponentType(): string
    {
        return 'prompt';
    }
}
```

## Testing Support

### Base Test Case

The package provides comprehensive testing support through the `McpComponentTestCase` class located in `src/Tests/McpComponentTestCase.php`.

```php
<?php

namespace JTD\LaravelMCP\Tests;

use Orchestra\Testbench\TestCase;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpPrompt;

abstract class McpComponentTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelMcpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('laravel-mcp.discovery.enabled', false);
        $app['config']->set('laravel-mcp.logging.enabled', false);
    }

    protected function createMockTool(string $name = 'test_tool', array $schema = []): McpTool
    {
        return new class($name, $schema) extends McpTool {
            private string $toolName;
            private array $toolSchema;

            public function __construct(string $name, array $schema = [])
            {
                $this->toolName = $name;
                $this->toolSchema = $schema;
                parent::__construct();
            }

            protected function handle(array $parameters): mixed
            {
                return ['result' => 'test', 'parameters' => $parameters];
            }

            public function getName(): string
            {
                return $this->toolName;
            }

            protected function getParameterSchema(): array
            {
                return $this->toolSchema;
            }

            protected function getComponentType(): string
            {
                return 'tool';
            }
        };
    }

    protected function createMockResource(string $name = 'test_resource', string $uriTemplate = null): McpResource
    {
        return new class($name, $uriTemplate) extends McpResource {
            private string $resourceName;
            private ?string $resourceUriTemplate;

            public function __construct(string $name, ?string $uriTemplate = null)
            {
                $this->resourceName = $name;
                $this->resourceUriTemplate = $uriTemplate;
                parent::__construct();
            }

            protected function customRead(array $params): mixed
            {
                return ['data' => 'test', 'params' => $params];
            }

            protected function customList(array $params): array
            {
                return [
                    'data' => [['id' => 1, 'name' => 'Test Item']],
                    'params' => $params
                ];
            }

            public function getName(): string
            {
                return $this->resourceName;
            }

            public function getUriTemplate(): string
            {
                return $this->resourceUriTemplate ?? parent::getUriTemplate();
            }

            protected function getComponentType(): string
            {
                return 'resource';
            }
        };
    }

    protected function createMockPrompt(string $name = 'test_prompt', array $arguments = []): McpPrompt
    {
        return new class($name, $arguments) extends McpPrompt {
            private string $promptName;
            private array $promptArguments;

            public function __construct(string $name, array $arguments = [])
            {
                $this->promptName = $name;
                $this->promptArguments = $arguments;
                parent::__construct();
            }

            protected function customContent(array $arguments): string
            {
                return "Test prompt content with arguments: " . json_encode($arguments);
            }

            public function getName(): string
            {
                return $this->promptName;
            }

            public function getArguments(): array
            {
                return $this->promptArguments;
            }

            protected function getComponentType(): string
            {
                return 'prompt';
            }
        };
    }

    protected function assertValidMcpResponse(array $response): void
    {
        $this->assertIsArray($response);
        
        if (isset($response['error'])) {
            $this->assertArrayHasKey('code', $response['error']);
            $this->assertArrayHasKey('message', $response['error']);
        } else {
            $this->assertTrue(true); // Valid non-error response
        }
    }

    protected function assertValidToolResponse(array $response): void
    {
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);
        
        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertArrayHasKey('text', $content);
        }
    }

    protected function assertValidResourceResponse(array $response): void
    {
        $this->assertArrayHasKey('contents', $response);
        $this->assertIsArray($response['contents']);
        
        foreach ($response['contents'] as $content) {
            $this->assertArrayHasKey('uri', $content);
            $this->assertArrayHasKey('mimeType', $content);
            $this->assertArrayHasKey('text', $content);
        }
    }

    protected function assertValidPromptResponse(array $response): void
    {
        $this->assertArrayHasKey('description', $response);
        $this->assertArrayHasKey('messages', $response);
        $this->assertIsArray($response['messages']);
        
        foreach ($response['messages'] as $message) {
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
        }
    }
}
```