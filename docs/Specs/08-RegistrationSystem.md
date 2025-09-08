# Registration System Specification

## Overview

The Registration System provides a Laravel-like routing interface for registering MCP components (Tools, Resources, Prompts) and manages their discovery, validation, and lifecycle. It includes both manual registration via `routes/mcp.php` and automatic discovery from application directories.

## Registry Architecture

### Core Registry Classes
```
Registry/
├── McpRegistry.php              # Central component registry
├── ToolRegistry.php             # Tool-specific registration
├── ResourceRegistry.php         # Resource-specific registration
├── PromptRegistry.php           # Prompt-specific registration
├── ComponentDiscovery.php       # Auto-discovery service
└── RouteRegistrar.php           # Route-style registration
```

### Central Registry Implementation
```php
<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Exceptions\RegistrationException;

class McpRegistry implements RegistryInterface
{
    private ToolRegistry $toolRegistry;
    private ResourceRegistry $resourceRegistry;
    private PromptRegistry $promptRegistry;
    private array $registered = [];
    private bool $initialized = false;

    public function __construct(
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->toolRegistry->initialize();
        $this->resourceRegistry->initialize();
        $this->promptRegistry->initialize();

        $this->initialized = true;
    }

    public function register(string $type, string $name, $handler, array $options = []): void
    {
        $this->validateRegistration($type, $name, $handler);

        match ($type) {
            'tool' => $this->toolRegistry->register($name, $handler, $options),
            'resource' => $this->resourceRegistry->register($name, $handler, $options),
            'prompt' => $this->promptRegistry->register($name, $handler, $options),
            default => throw new RegistrationException("Unknown component type: $type")
        };

        $this->registered[$type][$name] = [
            'handler' => $handler,
            'options' => $options,
            'registered_at' => time(),
        ];
    }

    public function get(string $type, string $name)
    {
        return match ($type) {
            'tool' => $this->toolRegistry->get($name),
            'resource' => $this->resourceRegistry->get($name),
            'prompt' => $this->promptRegistry->get($name),
            default => null
        };
    }

    public function getAll(string $type): array
    {
        return match ($type) {
            'tool' => $this->toolRegistry->getAll(),
            'resource' => $this->resourceRegistry->getAll(),
            'prompt' => $this->promptRegistry->getAll(),
            default => []
        };
    }

    public function has(string $type, string $name): bool
    {
        return match ($type) {
            'tool' => $this->toolRegistry->has($name),
            'resource' => $this->resourceRegistry->has($name),
            'prompt' => $this->promptRegistry->has($name),
            default => false
        };
    }

    public function getTools(): array
    {
        return $this->toolRegistry->getAll();
    }

    public function getTool(string $name): ?McpTool
    {
        return $this->toolRegistry->get($name);
    }

    public function getResources(): array
    {
        return $this->resourceRegistry->getAll();
    }

    public function getResource(string $name): ?McpResource
    {
        return $this->resourceRegistry->get($name);
    }

    public function getPrompts(): array
    {
        return $this->promptRegistry->getAll();
    }

    public function getPrompt(string $name): ?McpPrompt
    {
        return $this->promptRegistry->get($name);
    }

    private function validateRegistration(string $type, string $name, $handler): void
    {
        if (empty($name)) {
            throw new RegistrationException('Component name cannot be empty');
        }

        if ($this->has($type, $name)) {
            throw new RegistrationException("Component '{$name}' of type '{$type}' is already registered");
        }

        $this->validateHandler($type, $handler);
    }

    private function validateHandler(string $type, $handler): void
    {
        if (is_string($handler) && !class_exists($handler)) {
            throw new RegistrationException("Handler class '{$handler}' does not exist");
        }

        if (is_string($handler)) {
            $requiredInterface = match ($type) {
                'tool' => McpTool::class,
                'resource' => McpResource::class,
                'prompt' => McpPrompt::class,
                default => null
            };

            if ($requiredInterface && !is_subclass_of($handler, $requiredInterface)) {
                throw new RegistrationException("Handler must extend {$requiredInterface}");
            }
        }
    }
}
```

## Route-Style Registration

### MCP Route Registrar
```php
<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Facades\Mcp;

class RouteRegistrar
{
    private McpRegistry $registry;
    private array $groupStack = [];

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function tool(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('tool', $name, $handler, $options);
        return $this;
    }

    public function resource(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('resource', $name, $handler, $options);
        return $this;
    }

    public function prompt(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('prompt', $name, $handler, $options);
        return $this;
    }

    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        $callback($this);
        array_pop($this->groupStack);
    }

    private function mergeGroupAttributes(array $options): array
    {
        foreach ($this->groupStack as $group) {
            $options = array_merge($group, $options);
        }
        return $options;
    }
}
```

### MCP Routes File Structure (`routes/mcp.php`)
```php
<?php

use JTD\LaravelMCP\Facades\Mcp;
use App\Mcp\Tools\CalculatorTool;
use App\Mcp\Tools\DatabaseQueryTool;
use App\Mcp\Resources\UserResource;
use App\Mcp\Resources\PostResource;
use App\Mcp\Prompts\EmailTemplatePrompt;

// Register individual components
Mcp::tool('calculator', CalculatorTool::class);
Mcp::tool('db_query', DatabaseQueryTool::class, ['middleware' => ['auth']]);

Mcp::resource('users', UserResource::class);
Mcp::resource('posts', PostResource::class, ['middleware' => ['auth']]);

Mcp::prompt('email_template', EmailTemplatePrompt::class);

// Group registration with common attributes
Mcp::group(['middleware' => ['auth', 'admin']], function ($mcp) {
    $mcp->tool('admin_tool', AdminTool::class);
    $mcp->resource('admin_logs', AdminLogResource::class);
});

// Namespace grouping
Mcp::group(['namespace' => 'App\\Mcp\\Admin'], function ($mcp) {
    $mcp->tool('system_info', 'SystemInfoTool');
    $mcp->resource('system_stats', 'SystemStatsResource');
});
```

## Component Discovery System

### Auto-Discovery Implementation
```php
<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use Symfony\Component\Finder\Finder;
use ReflectionClass;

class ComponentDiscovery implements DiscoveryInterface
{
    private McpRegistry $registry;
    private array $discoveredComponents = [];
    private array $discoveryPaths;
    private bool $cacheEnabled;
    private string $cacheKey = 'laravel_mcp_discovered_components';

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
        $this->discoveryPaths = config('laravel-mcp.discovery.paths', [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ]);
        $this->cacheEnabled = config('laravel-mcp.discovery.cache', true);
    }

    public function discoverComponents(array $paths = []): array
    {
        $searchPaths = empty($paths) ? $this->discoveryPaths : $paths;
        
        if ($this->cacheEnabled && $cached = $this->getCachedDiscovery()) {
            return $this->discoveredComponents = $cached;
        }

        $this->discoveredComponents = [];

        foreach ($searchPaths as $path) {
            if (is_dir($path)) {
                $this->scanDirectory($path);
            }
        }

        if ($this->cacheEnabled) {
            $this->cacheDiscovery();
        }

        return $this->discoveredComponents;
    }

    public function registerDiscoveredComponents(): void
    {
        foreach ($this->discoveredComponents as $component) {
            try {
                $this->registry->register(
                    $component['type'],
                    $component['name'],
                    $component['class'],
                    $component['options'] ?? []
                );
            } catch (\Throwable $e) {
                logger()->warning('Failed to register discovered component', [
                    'component' => $component,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function scanDirectory(string $path): void
    {
        $finder = new Finder();
        $finder->files()->name('*.php')->in($path);

        foreach ($finder as $file) {
            $this->analyzeFile($file->getRealPath());
        }
    }

    private function analyzeFile(string $filePath): void
    {
        try {
            $className = $this->extractClassName($filePath);
            
            if (!$className || !class_exists($className)) {
                return;
            }

            $reflection = new ReflectionClass($className);
            
            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                return;
            }

            $component = $this->classifyComponent($reflection);
            
            if ($component) {
                $this->discoveredComponents[] = $component;
            }
        } catch (\Throwable $e) {
            logger()->debug('Failed to analyze file for MCP discovery', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        
        // Extract namespace
        if (!preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }
        $namespace = trim($namespaceMatches[1]);

        // Extract class name
        if (!preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            return null;
        }
        $className = trim($classMatches[1]);

        return $namespace . '\\' . $className;
    }

    private function classifyComponent(ReflectionClass $reflection): ?array
    {
        $className = $reflection->getName();
        
        if ($reflection->isSubclassOf(McpTool::class)) {
            return [
                'type' => 'tool',
                'name' => $this->generateComponentName($className, 'Tool'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        if ($reflection->isSubclassOf(McpResource::class)) {
            return [
                'type' => 'resource',
                'name' => $this->generateComponentName($className, 'Resource'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        if ($reflection->isSubclassOf(McpPrompt::class)) {
            return [
                'type' => 'prompt',
                'name' => $this->generateComponentName($className, 'Prompt'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        return null;
    }

    private function generateComponentName(string $className, string $suffix): string
    {
        $baseName = class_basename($className);
        $name = str_replace($suffix, '', $baseName);
        return Str::snake($name);
    }

    private function getCachedDiscovery(): ?array
    {
        return cache()->get($this->cacheKey);
    }

    private function cacheDiscovery(): void
    {
        cache()->put($this->cacheKey, $this->discoveredComponents, now()->addHours(1));
    }

    public function clearCache(): void
    {
        cache()->forget($this->cacheKey);
    }
}
```

## Individual Registry Implementations

### Tool Registry
```php
<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Container\Container;

class ToolRegistry
{
    private Container $container;
    private array $tools = [];
    private array $instances = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function initialize(): void
    {
        // Initialize any required services
    }

    public function register(string $name, $handler, array $options = []): void
    {
        $this->tools[$name] = [
            'handler' => $handler,
            'options' => $options,
        ];
    }

    public function get(string $name): ?McpTool
    {
        if (!$this->has($name)) {
            return null;
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $tool = $this->createToolInstance($name);
        
        if (config('laravel-mcp.cache.instances', true)) {
            $this->instances[$name] = $tool;
        }

        return $tool;
    }

    public function getAll(): array
    {
        $tools = [];
        
        foreach (array_keys($this->tools) as $name) {
            $tools[$name] = $this->get($name);
        }

        return array_filter($tools);
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    private function createToolInstance(string $name): McpTool
    {
        $config = $this->tools[$name];
        $handler = $config['handler'];

        if (is_string($handler)) {
            return $this->container->make($handler);
        }

        if (is_callable($handler)) {
            return $handler($this->container);
        }

        if ($handler instanceof McpTool) {
            return $handler;
        }

        throw new \InvalidArgumentException("Invalid tool handler for: $name");
    }
}
```

### Resource Registry
```php
<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Container\Container;

class ResourceRegistry
{
    private Container $container;
    private array $resources = [];
    private array $instances = [];
    private array $uriMappings = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function initialize(): void
    {
        // Build URI mappings for resource routing
        $this->buildUriMappings();
    }

    public function register(string $name, $handler, array $options = []): void
    {
        $this->resources[$name] = [
            'handler' => $handler,
            'options' => $options,
        ];

        // Update URI mappings
        $this->buildUriMappings();
    }

    public function get(string $name): ?McpResource
    {
        if (!$this->has($name)) {
            return null;
        }

        if (isset($this->instances[$name])) {
            return $this->instances[$name];
        }

        $resource = $this->createResourceInstance($name);
        
        if (config('laravel-mcp.cache.instances', true)) {
            $this->instances[$name] = $resource;
        }

        return $resource;
    }

    public function getByUri(string $uri): ?McpResource
    {
        foreach ($this->uriMappings as $pattern => $name) {
            if ($this->matchesPattern($uri, $pattern)) {
                return $this->get($name);
            }
        }

        return null;
    }

    public function getAll(): array
    {
        $resources = [];
        
        foreach (array_keys($this->resources) as $name) {
            $resources[$name] = $this->get($name);
        }

        return array_filter($resources);
    }

    public function has(string $name): bool
    {
        return isset($this->resources[$name]);
    }

    private function createResourceInstance(string $name): McpResource
    {
        $config = $this->resources[$name];
        $handler = $config['handler'];

        if (is_string($handler)) {
            return $this->container->make($handler);
        }

        if (is_callable($handler)) {
            return $handler($this->container);
        }

        if ($handler instanceof McpResource) {
            return $handler;
        }

        throw new \InvalidArgumentException("Invalid resource handler for: $name");
    }

    private function buildUriMappings(): void
    {
        $this->uriMappings = [];

        foreach ($this->resources as $name => $config) {
            $resource = $this->get($name);
            if ($resource) {
                $this->uriMappings[$resource->getUriTemplate()] = $name;
            }
        }
    }

    private function matchesPattern(string $uri, string $pattern): bool
    {
        $regex = str_replace(['*', '{', '}'], ['[^/]*', '(?P<', '>[^/]*)'], $pattern);
        return preg_match('#^' . $regex . '$#', $uri);
    }
}
```

## MCP Facade

### Facade Implementation
```php
<?php

namespace JTD\LaravelMCP\Facades;

use Illuminate\Support\Facades\Facade;
use JTD\LaravelMCP\Registry\RouteRegistrar;

/**
 * @method static \JTD\LaravelMCP\Registry\RouteRegistrar tool(string $name, $handler, array $options = [])
 * @method static \JTD\LaravelMCP\Registry\RouteRegistrar resource(string $name, $handler, array $options = [])
 * @method static \JTD\LaravelMCP\Registry\RouteRegistrar prompt(string $name, $handler, array $options = [])
 * @method static void group(array $attributes, \Closure $callback)
 */
class Mcp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RouteRegistrar::class;
    }
}
```

## Registration Events

### Event System
```php
<?php

namespace JTD\LaravelMCP\Events;

class ComponentRegistered
{
    public string $type;
    public string $name;
    public string $handler;
    public array $options;

    public function __construct(string $type, string $name, string $handler, array $options = [])
    {
        $this->type = $type;
        $this->name = $name;
        $this->handler = $handler;
        $this->options = $options;
    }
}

class ComponentDiscovered
{
    public array $components;

    public function __construct(array $components)
    {
        $this->components = $components;
    }
}
```

## Configuration

### Discovery Configuration
```php
return [
    'discovery' => [
        'enabled' => env('MCP_AUTO_DISCOVERY', true),
        'paths' => [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ],
        'cache' => env('MCP_DISCOVERY_CACHE', true),
        'cache_duration' => env('MCP_DISCOVERY_CACHE_DURATION', 3600), // 1 hour
    ],
    
    'registration' => [
        'strict_mode' => env('MCP_STRICT_REGISTRATION', false),
        'allow_duplicates' => env('MCP_ALLOW_DUPLICATE_NAMES', false),
        'validate_handlers' => env('MCP_VALIDATE_HANDLERS', true),
    ],
];
```