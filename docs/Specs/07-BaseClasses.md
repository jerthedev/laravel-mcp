# Base Classes Specification

## Overview

The base classes provide the foundation for MCP Tools, Resources, and Prompts in Laravel applications. They extend the official MCP PHP SDK while adding Laravel-specific features like dependency injection, validation, authorization, and integration with Laravel's ecosystem.

## Abstract Base Classes Architecture

### McpTool Base Class
```php
<?php

namespace JTD\LaravelMCP\Abstracts;

use MCP\Tool as McpSdkTool;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

abstract class McpTool extends McpSdkTool
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
            throw new \UnauthorizedHttpException('Unauthorized tool execution');
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

use MCP\Resource as McpSdkResource;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;

abstract class McpResource extends McpSdkResource
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
            throw new \UnauthorizedHttpException('Unauthorized resource access');
        }

        $validatedParams = $this->validateParameters($params, 'read');
        
        return $this->handleRead($validatedParams);
    }

    public function list(array $params = []): array
    {
        if (!$this->authorize('list', $params)) {
            throw new \UnauthorizedHttpException('Unauthorized resource listing');
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
            throw new \UnauthorizedHttpException('Unauthorized subscription');
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

use MCP\Prompt as McpSdkPrompt;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Illuminate\Container\Container;
use Illuminate\View\Factory as ViewFactory;

abstract class McpPrompt extends McpSdkPrompt
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
            throw new \UnauthorizedHttpException('Unauthorized prompt access');
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

        if (method_exists($this, 'getValidationRules')) {
            return $this->getValidationRules();
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
        return match (static::class) {
            McpTool::class => ['execute'],
            McpResource::class => ['read', 'list'],
            McpPrompt::class => ['get'],
            default => [],
        };
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

        return match ($op) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b !== 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
            default => throw new \InvalidArgumentException('Unknown operation'),
        };
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

    protected function authorize(string $action, array $params): bool
    {
        // Custom authorization logic
        return auth()->check();
    }

    protected function customList(array $params): array
    {
        // Add custom filtering or transformation
        $users = parent::listFromModel($params);
        
        // Remove sensitive data
        foreach ($users['data'] as &$user) {
            unset($user['password'], $user['remember_token']);
        }
        
        return $users;
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
}
```

## Testing Support

### Base Test Case
```php
<?php

namespace JTD\LaravelMCP\Tests;

use Orchestra\Testbench\TestCase;
use JTD\LaravelMCP\LaravelMcpServiceProvider;

abstract class McpComponentTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LaravelMcpServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('laravel-mcp.discovery.enabled', false);
    }

    protected function createMockTool(string $name = 'test_tool'): McpTool
    {
        return new class($name) extends McpTool {
            private string $toolName;

            public function __construct(string $name)
            {
                $this->toolName = $name;
                parent::__construct();
            }

            protected function handle(array $parameters): mixed
            {
                return ['result' => 'test'];
            }

            public function getName(): string
            {
                return $this->toolName;
            }
        };
    }
}
```