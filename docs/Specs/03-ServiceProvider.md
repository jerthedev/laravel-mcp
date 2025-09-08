# Service Provider Specification

## Overview

The `LaravelMcpServiceProvider` serves as the main integration point between the Laravel framework and the MCP package. It handles registration of services, configuration, routes, commands, and middleware while following Laravel's service provider patterns.

## Service Provider Structure

### Class Definition
```php
<?php

namespace JTD\LaravelMCP;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Commands\ServeCommand;
use JTD\LaravelMCP\Commands\MakeToolCommand;
use JTD\LaravelMCP\Commands\MakeResourceCommand;
use JTD\LaravelMCP\Commands\MakePromptCommand;
use JTD\LaravelMCP\Commands\ListCommand;
use JTD\LaravelMCP\Commands\RegisterCommand;

class LaravelMcpServiceProvider extends ServiceProvider
{
    // Implementation details below
}
```

## Registration Phase (`register` method)

### Service Container Bindings
The `register` method handles core service bindings and singletons:

```php
public function register(): void
{
    // Merge package configuration
    $this->mergeConfigFrom(
        __DIR__.'/../config/laravel-mcp.php', 'laravel-mcp'
    );

    // Register core MCP services as singletons
    $this->app->singleton(McpRegistry::class);
    $this->app->singleton(TransportManager::class);
    $this->app->singleton(JsonRpcHandler::class);
    $this->app->singleton(MessageProcessor::class);
    $this->app->singleton(CapabilityNegotiator::class);
    
    // Register registry services
    $this->app->singleton(ToolRegistry::class);
    $this->app->singleton(ResourceRegistry::class);
    $this->app->singleton(PromptRegistry::class);
    
    // Register discovery service
    $this->app->singleton(ComponentDiscovery::class);
    
    // Register transport implementations
    $this->app->bind('mcp.transport.http', HttpTransport::class);
    $this->app->bind('mcp.transport.stdio', StdioTransport::class);
    
    // Register support services
    $this->app->singleton(ConfigGenerator::class);
    $this->app->singleton(DocumentationGenerator::class);
}
```

### Interface Bindings
```php
private function registerInterfaces(): void
{
    $this->app->bind(
        TransportInterface::class,
        fn($app) => $app->make(TransportManager::class)->getDefaultTransport()
    );
    
    $this->app->bind(
        JsonRpcHandlerInterface::class,
        JsonRpcHandler::class
    );
    
    $this->app->bind(
        RegistryInterface::class,
        McpRegistry::class
    );
}
```

### Configuration Registration
```php
private function registerConfiguration(): void
{
    // Register configuration files
    $this->mergeConfigFrom(
        __DIR__.'/../config/laravel-mcp.php',
        'laravel-mcp'
    );
    
    $this->mergeConfigFrom(
        __DIR__.'/../config/mcp-transports.php',
        'mcp-transports'
    );
}
```

## Boot Phase (`boot` method)

### Boot Method Implementation
```php
public function boot(): void
{
    $this->bootPublishing();
    $this->bootRoutes();
    $this->bootCommands();
    $this->bootMiddleware();
    $this->bootDiscovery();
    $this->bootViews();
    
    if ($this->app->runningInConsole()) {
        $this->bootConsole();
    }
}
```

### Publishing Configuration
```php
private function bootPublishing(): void
{
    // Publish main configuration
    $this->publishes([
        __DIR__.'/../config/laravel-mcp.php' => config_path('laravel-mcp.php'),
    ], 'laravel-mcp-config');
    
    // Publish transport configuration
    $this->publishes([
        __DIR__.'/../config/mcp-transports.php' => config_path('mcp-transports.php'),
    ], 'laravel-mcp-transports');
    
    // Publish routes
    $this->publishes([
        __DIR__.'/../routes/mcp.php' => base_path('routes/mcp.php'),
    ], 'laravel-mcp-routes');
    
    // Publish stubs for code generation
    $this->publishes([
        __DIR__.'/../resources/stubs' => resource_path('stubs/mcp'),
    ], 'laravel-mcp-stubs');
    
    // Publish all assets with single tag
    $this->publishes([
        __DIR__.'/../config/laravel-mcp.php' => config_path('laravel-mcp.php'),
        __DIR__.'/../config/mcp-transports.php' => config_path('mcp-transports.php'),
        __DIR__.'/../routes/mcp.php' => base_path('routes/mcp.php'),
        __DIR__.'/../resources/stubs' => resource_path('stubs/mcp'),
    ], 'laravel-mcp');
}
```

### Route Registration
```php
private function bootRoutes(): void
{
    // Register package routes
    Route::group([
        'namespace' => 'JTD\LaravelMCP\Http\Controllers',
        'prefix' => config('laravel-mcp.routes.prefix', 'mcp'),
        'middleware' => config('laravel-mcp.routes.middleware', ['api']),
    ], function () {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
    });
    
    // Load application MCP routes if they exist
    if (file_exists(base_path('routes/mcp.php'))) {
        $this->loadMcpRoutes();
    }
}

private function loadMcpRoutes(): void
{
    Route::group([
        'middleware' => config('laravel-mcp.routes.middleware', ['api']),
    ], function () {
        require base_path('routes/mcp.php');
    });
}
```

### Command Registration
```php
private function bootCommands(): void
{
    if ($this->app->runningInConsole()) {
        $this->commands([
            ServeCommand::class,
            MakeToolCommand::class,
            MakeResourceCommand::class,
            MakePromptCommand::class,
            ListCommand::class,
            RegisterCommand::class,
        ]);
    }
}
```

### Middleware Registration
```php
private function bootMiddleware(): void
{
    $router = $this->app['router'];
    
    // Register middleware
    $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
    $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);
    
    // Add middleware to groups if configured
    if (config('laravel-mcp.middleware.auto_register', true)) {
        $router->pushMiddlewareToGroup('api', McpCorsMiddleware::class);
    }
}
```

### Component Discovery
```php
private function bootDiscovery(): void
{
    $discovery = $this->app->make(ComponentDiscovery::class);
    
    // Discover components in application directories
    $discovery->discoverComponents([
        app_path('Mcp/Tools'),
        app_path('Mcp/Resources'),
        app_path('Mcp/Prompts'),
    ]);
    
    // Register discovered components
    $discovery->registerDiscoveredComponents();
}
```

### View Registration
```php
private function bootViews(): void
{
    $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-mcp');
    
    // Publish views if needed
    $this->publishes([
        __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-mcp'),
    ], 'laravel-mcp-views');
}
```

### Console-Specific Boot
```php
private function bootConsole(): void
{
    // Additional console-specific initialization
    $this->bootMigrations();
    $this->bootAssets();
}

private function bootMigrations(): void
{
    // Load package migrations if any
    $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
}

private function bootAssets(): void
{
    // Publish additional assets for development
    $this->publishes([
        __DIR__.'/../resources/assets' => public_path('vendor/laravel-mcp'),
    ], 'laravel-mcp-assets');
}
```

## Service Provider Features

### Configuration Management
```php
private function registerConfig(): array
{
    return [
        'transports' => [
            'default' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),
            'http' => [
                'enabled' => env('MCP_HTTP_ENABLED', true),
                'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
                'port' => env('MCP_HTTP_PORT', 8000),
                'middleware' => ['mcp.cors', 'mcp.auth'],
            ],
            'stdio' => [
                'enabled' => env('MCP_STDIO_ENABLED', true),
                'timeout' => env('MCP_STDIO_TIMEOUT', 30),
            ],
        ],
        'discovery' => [
            'enabled' => env('MCP_AUTO_DISCOVERY', true),
            'paths' => [
                app_path('Mcp/Tools'),
                app_path('Mcp/Resources'),
                app_path('Mcp/Prompts'),
            ],
        ],
        'routes' => [
            'prefix' => env('MCP_ROUTES_PREFIX', 'mcp'),
            'middleware' => ['api'],
        ],
    ];
}
```

### Environment Detection
```php
private function detectEnvironment(): string
{
    if ($this->app->runningInConsole()) {
        return 'console';
    }
    
    if ($this->app->environment('testing')) {
        return 'testing';
    }
    
    return 'web';
}
```

### Dependency Validation
```php
private function validateDependencies(): void
{
    $required = [
        'modelcontextprotocol/php-sdk' => 'MCP PHP SDK is required',
        'symfony/process' => 'Symfony Process component is required for stdio transport',
    ];
    
    foreach ($required as $package => $message) {
        if (!class_exists($this->getPackageClass($package))) {
            throw new \RuntimeException($message);
        }
    }
}

private function getPackageClass(string $package): string
{
    $classMap = [
        'modelcontextprotocol/php-sdk' => 'MCP\\Server',
        'symfony/process' => 'Symfony\\Component\\Process\\Process',
    ];
    
    return $classMap[$package] ?? '';
}
```

## Service Provider Events

### Event Hooks
```php
protected function registerEventHooks(): void
{
    // Hook into Laravel application events
    $this->app['events']->listen('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders', function () {
        $this->onProvidersBooted();
    });
    
    $this->app['events']->listen('kernel.handled', function () {
        $this->onRequestHandled();
    });
}

private function onProvidersBooted(): void
{
    // All providers have been booted
    $this->finalizeComponentDiscovery();
}

private function onRequestHandled(): void
{
    // Request has been handled
    $this->cleanupResources();
}
```

### Deferred Loading
```php
protected $defer = false; // Not deferred due to route registration needs

// If we needed deferred loading:
/*
protected $defer = true;

public function provides(): array
{
    return [
        McpRegistry::class,
        TransportManager::class,
        JsonRpcHandler::class,
    ];
}
*/
```

## Testing Considerations

### Test Service Provider
```php
class TestServiceProvider extends LaravelMcpServiceProvider
{
    public function boot(): void
    {
        parent::boot();
        
        // Test-specific overrides
        $this->bootTestingEnvironment();
    }
    
    private function bootTestingEnvironment(): void
    {
        // Disable auto-discovery in tests
        config(['laravel-mcp.discovery.enabled' => false]);
        
        // Use test-specific paths
        config(['laravel-mcp.discovery.paths' => [
            __DIR__ . '/Fixtures/Tools',
            __DIR__ . '/Fixtures/Resources',
            __DIR__ . '/Fixtures/Prompts',
        ]]);
    }
}
```

## Error Handling

### Graceful Degradation
```php
private function handleBootFailure(\Throwable $e): void
{
    // Log the error
    if ($this->app->bound('log')) {
        $this->app['log']->error('MCP Service Provider boot failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    // Disable MCP features gracefully
    config(['laravel-mcp.enabled' => false]);
    
    // Don't break the application
    if (!$this->app->environment('production')) {
        throw $e;
    }
}
```

## Performance Optimizations

### Lazy Loading
```php
private function registerLazyServices(): void
{
    // Register services that are only created when needed
    $this->app->bindIf(DocumentationGenerator::class, function ($app) {
        return new DocumentationGenerator(
            $app->make(McpRegistry::class)
        );
    });
}
```

### Caching
```php
private function registerCaching(): void
{
    // Cache component discovery results
    $this->app->singleton('mcp.discovery.cache', function ($app) {
        return $app->make('cache')->store(
            config('laravel-mcp.cache.store', 'file')
        );
    });
}
```