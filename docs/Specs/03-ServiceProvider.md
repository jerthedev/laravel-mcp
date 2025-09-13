# Service Provider Specification

## Overview

The `LaravelMcpServiceProvider` serves as the main integration point between the Laravel framework and the MCP package. It handles registration of services, configuration, routes, commands, middleware, events, jobs, and notifications while following Laravel's service provider patterns. The enhanced implementation provides comprehensive Laravel framework integration with production-ready features including async processing, event-driven architecture, and advanced monitoring capabilities.

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
use JTD\LaravelMCP\Commands\DocumentationCommand;
use JTD\LaravelMCP\McpManager;

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
    $this->registerConfiguration();
    $this->registerServices();
    $this->registerInterfaces();
    $this->registerLazyServices();
    $this->registerCaching();
}

private function registerServices(): void
{
    $this->registerCoreServices();
    $this->registerAdvancedServices();
    $this->registerEventListeners();
    $this->registerJobBindings();
}

/**
 * Register core MCP services.
 */
private function registerCoreServices(): void
{
    // Register registry services first (no dependencies)
    $this->app->singleton(ToolRegistry::class);
    $this->app->singleton(ResourceRegistry::class);
    $this->app->singleton(PromptRegistry::class);

    // Register core MCP services with explicit factory for McpRegistry
    $this->app->singleton(McpRegistry::class, function ($app) {
        return new McpRegistry(
            $app->make(ToolRegistry::class),
            $app->make(ResourceRegistry::class),
            $app->make(PromptRegistry::class)
        );
    });

    $this->app->singleton(JsonRpcHandler::class);
    $this->app->singleton(MessageProcessor::class);
    $this->app->singleton(CapabilityNegotiator::class);

    // Register server services
    $this->app->singleton(ServerInfo::class);
    $this->app->singleton(CapabilityManager::class);
    $this->app->singleton(McpServer::class);

    // Register route registrar for fluent API
    $this->app->singleton(RouteRegistrar::class);
    $this->app->singleton(RoutingPatterns::class);

    // Register discovery service
    $this->app->singleton(ComponentDiscovery::class, function ($app) {
        return new ComponentDiscovery(
            $app->make(McpRegistry::class),
            $app->make(RoutingPatterns::class)
        );
    });

    // Register MCP Manager that bridges facade to both registry and registrar
    $this->app->singleton(McpManager::class, function ($app) {
        return new McpManager(
            $app->make(McpRegistry::class),
            $app->make(RouteRegistrar::class)
        );
    });

    // Register transport implementations
    $this->app->bind('mcp.transport.http', HttpTransport::class);
    $this->app->bind('mcp.transport.stdio', StdioTransport::class);

    // Register transport manager with proper factory methods
    $this->app->singleton(TransportManager::class, function ($app) {
        return new TransportManager($app);
    });

    // Register facade and registry aliases
    $this->app->singleton('laravel-mcp', function ($app) {
        return $app->make(McpManager::class);
    });

    $this->app->singleton('mcp.registry', function ($app) {
        return $app->make(McpRegistry::class);
    });
}

/**
 * Register advanced MCP services and generators.
 */
private function registerAdvancedServices(): void
{
    // Register advanced services (only if classes exist)
    $advancedServices = [
        'JTD\\LaravelMCP\\Support\\PerformanceMonitor',
        'JTD\\LaravelMCP\\Support\\SchemaValidator',
        'JTD\\LaravelMCP\\Support\\MessageSerializer',
        'JTD\\LaravelMCP\\Support\\AdvancedDocumentationGenerator',
        'JTD\\LaravelMCP\\Support\\Debugger',
        'JTD\\LaravelMCP\\Support\\ExampleCompiler',
        'JTD\\LaravelMCP\\Support\\ExtensionGuideBuilder',
    ];

    foreach ($advancedServices as $serviceClass) {
        if (class_exists($serviceClass)) {
            $this->app->singleton($serviceClass);
        }
    }

    // Register client generators (bind as needed, not singletons)
    $clientGenerators = [
        'JTD\\LaravelMCP\\Support\\ClientGenerators\\ClaudeDesktopGenerator',
        'JTD\\LaravelMCP\\Support\\ClientGenerators\\ClaudeCodeGenerator',
        'JTD\\LaravelMCP\\Support\\ClientGenerators\\ChatGptGenerator',
    ];

    foreach ($clientGenerators as $generatorClass) {
        if (class_exists($generatorClass)) {
            $this->app->bind($generatorClass);
        }
    }

    // Register notification handler if available
    if (class_exists('JTD\\LaravelMCP\\Protocol\\NotificationHandler')) {
        $this->app->singleton('JTD\\LaravelMCP\\Protocol\\NotificationHandler');
    }
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
    $this->validateDependencies();

    try {
        $this->registerEventHooks();

        $this->bootPublishing();
        $this->bootRoutes();
        $this->bootCommands();
        $this->bootMiddleware();
        $this->bootDiscovery();
        $this->bootMcpRoutes();
        $this->bootViews();
        $this->bootEvents();
        $this->bootJobs();
        $this->bootNotifications();
        $this->bootPerformanceMonitoring();

        if ($this->app->runningInConsole()) {
            $this->bootConsole();
        }
    } catch (\Throwable $e) {
        $this->handleBootFailure($e);
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
            DocumentationCommand::class,
        ]);
    }
}
```

### Middleware Registration
```php
private function bootMiddleware(): void
{
    $router = $this->app['router'];
    
    // Register all middleware
    $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
    $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);
    $router->aliasMiddleware('mcp.validation', McpValidationMiddleware::class);
    $router->aliasMiddleware('mcp.rate-limit', McpRateLimitMiddleware::class);
    $router->aliasMiddleware('mcp.logging', McpLoggingMiddleware::class);
    $router->aliasMiddleware('mcp.error-handling', McpErrorHandlingMiddleware::class);
    $router->aliasMiddleware('sse', HandleSseRequest::class);
    
    // Register middleware groups
    $router->middlewareGroup('mcp', [
        'mcp.cors',
        'mcp.auth',
        'mcp.rate-limit',
        'mcp.validation',
        'mcp.logging',
        'mcp.error-handling',
    ]);
    
    // Add middleware to existing groups if configured
    if (config('laravel-mcp.middleware.auto_register', true)) {
        $router->pushMiddlewareToGroup('api', McpCorsMiddleware::class);
        $router->pushMiddlewareToGroup('web', HandleSseRequest::class);
    }
}
```

### Component Discovery
```php
private function bootDiscovery(): void
{
    if (!config('laravel-mcp.discovery.enabled', true)) {
        return;
    }
    
    $discovery = $this->app->make(ComponentDiscovery::class);
    
    // Get discovery paths from config
    $paths = config('laravel-mcp.discovery.paths', [
        app_path('Mcp/Tools'),
        app_path('Mcp/Resources'),
        app_path('Mcp/Prompts'),
    ]);
    
    // Discover components in application directories
    $discovery->discoverComponents($paths);
    
    // Register discovered components
    $discovery->registerDiscoveredComponents();
    
    // Cache discovery results for performance
    if (config('laravel-mcp.discovery.cache_enabled', true)) {
        $discovery->cacheDiscoveryResults();
    }
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

### Enhanced Boot Methods

#### Event System Boot
```php
private function bootEvents(): void
{
    // Register event listeners if events are enabled
    if (config('laravel-mcp.events.enabled', true)) {
        $this->registerMcpEventListeners();
    }
}

private function registerMcpEventListeners(): void
{
    $events = $this->app['events'];
    
    // Register built-in listeners
    if (config('laravel-mcp.events.listeners.activity', true)) {
        $events->listen(McpRequestProcessed::class, LogMcpActivity::class);
    }
    
    if (config('laravel-mcp.events.listeners.metrics', true)) {
        $events->listen(McpRequestProcessed::class, TrackMcpRequestMetrics::class);
    }
    
    if (config('laravel-mcp.events.listeners.registration', true)) {
        $events->listen(McpComponentRegistered::class, LogMcpComponentRegistration::class);
    }
    
    // Register usage tracking
    $events->listen([
        McpToolExecuted::class,
        McpResourceAccessed::class,
        McpPromptGenerated::class,
    ], TrackMcpUsage::class);
}
```

#### Jobs System Boot
```php
private function bootJobs(): void
{
    if (! config('laravel-mcp.queue.enabled', false)) {
        return;
    }

    // Register job event listeners for monitoring
    $this->app['events']->listen('queue.job.failed', function ($event) {
        if ($this->isMcpJob($event->job)) {
            $this->handleMcpJobFailure($event);
        }
    });

    // Register job processing listeners
    $this->app['events']->listen('queue.job.processing', function ($event) {
        if ($this->isMcpJob($event->job)) {
            if ($this->app->bound('log')) {
                $this->app['log']->info('MCP job processing started', [
                    'job' => $event->job->resolveName(),
                    'queue' => $event->job->getQueue(),
                ]);
            }
        }
    });

    if ($this->app->bound('log')) {
        $this->app['log']->debug('MCP jobs system initialized');
    }
}

private function isMcpJob($job): bool
{
    $mcpJobClasses = [
        'JTD\\LaravelMCP\\Jobs\\ProcessMcpRequest',
        'JTD\\LaravelMCP\\Jobs\\ProcessNotificationDelivery',
    ];

    $jobName = is_object($job) ? get_class($job) : (string) $job;
    if (method_exists($job, 'resolveName')) {
        $jobName = $job->resolveName();
    }

    return in_array($jobName, $mcpJobClasses);
}

private function handleMcpJobFailure($event): void
{
    if ($this->app->bound('log')) {
        $this->app['log']->error('MCP job failed', [
            'job' => $event->job->resolveName(),
            'exception' => $event->exception->getMessage(),
            'queue' => $event->job->getQueue(),
            'attempts' => $event->job->attempts(),
        ]);
    }

    // Dispatch notification about job failure if notifications are enabled
    if (config('laravel-mcp.notifications.enabled', true) && 
        class_exists('JTD\\LaravelMCP\\Events\\NotificationFailed')) {
        $this->app['events']->dispatch(new \\JTD\\LaravelMCP\\Events\\NotificationFailed(
            'MCP job failed: ' . $event->job->resolveName(),
            $event->exception
        ));
    }
}
```

#### Notifications System Boot
```php
private function bootNotifications(): void
{
    if (! config('laravel-mcp.notifications.enabled', true)) {
        return;
    }

    // Register notification channels
    $this->registerNotificationChannels();

    // Set up notification event listeners
    $this->registerNotificationEventListeners();

    if ($this->app->bound('log')) {
        $this->app['log']->debug('MCP notifications system initialized');
    }
}

private function registerNotificationChannels(): void
{
    $channels = config('laravel-mcp.notifications.channels', ['database']);

    if (! $this->app->bound('notification.channel.manager')) {
        return;
    }

    foreach ($channels as $channel) {
        if ($channel === 'slack' && config('laravel-mcp.notifications.slack.enabled', false)) {
            // Register Slack channel if configuration exists
            if (class_exists('JTD\\LaravelMCP\\Notifications\\Channels\\SlackChannel')) {
                $this->app->make('notification.channel.manager')
                     ->extend('mcp-slack', function () {
                         return new \\JTD\\LaravelMCP\\Notifications\\Channels\\SlackChannel(
                             config('laravel-mcp.notifications.slack')
                         );
                     });
            }
        }
    }
}

private function registerNotificationEventListeners(): void
{
    // Register listeners for notification events if they exist
    $notificationEvents = [
        'JTD\\LaravelMCP\\Events\\NotificationQueued',
        'JTD\\LaravelMCP\\Events\\NotificationSent',
        'JTD\\LaravelMCP\\Events\\NotificationFailed',
        'JTD\\LaravelMCP\\Events\\NotificationDelivered',
        'JTD\\LaravelMCP\\Events\\NotificationBroadcast',
    ];

    foreach ($notificationEvents as $eventClass) {
        if (class_exists($eventClass)) {
            $this->app['events']->listen($eventClass, function ($event) {
                if ($this->app->bound('log')) {
                    $this->app['log']->info('MCP notification event fired', [
                        'event' => get_class($event),
                        'timestamp' => now()->toISOString(),
                    ]);
                }
            });
        }
    }
}
```

#### Performance Monitoring Boot
```php
private function bootPerformanceMonitoring(): void
{
    if (! config('laravel-mcp.performance.enabled', false)) {
        return;
    }

    // Register performance monitoring service if it exists
    if (class_exists('JTD\\LaravelMCP\\Support\\PerformanceMonitor')) {
        $this->app->singleton('JTD\\LaravelMCP\\Support\\PerformanceMonitor');

        // Start monitoring if configured
        if (config('laravel-mcp.performance.auto_start', true)) {
            $monitor = $this->app->make('JTD\\LaravelMCP\\Support\\PerformanceMonitor');
            $monitor->start();

            // Register shutdown handler for performance cleanup
            register_shutdown_function(function () use ($monitor) {
                $monitor->stop();
            });
        }
    }

    if ($this->app->bound('log')) {
        $this->app['log']->debug('MCP performance monitoring initialized');
    }
}
```

### Console-Specific Boot
```php
private function bootConsole(): void
{
    // Additional console-specific initialization
    $this->bootMigrations();
    $this->bootAssets();
    $this->bootConsoleServices();
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

private function bootConsoleServices(): void
{
    // Register console-specific services
    $this->app->singleton('mcp.console.output', function ($app) {
        return new OutputFormatter();
    });
}
```

## Service Provider Features

### Enhanced Service Registration Methods

#### Event Listeners Registration
```php
private function registerEventListeners(): void
{
    $this->app->bind(LogMcpActivity::class);
    $this->app->bind(LogMcpComponentRegistration::class);
    $this->app->bind(TrackMcpRequestMetrics::class);
    $this->app->bind(TrackMcpUsage::class);
}
```

#### Job Bindings Registration
```php
private function registerJobBindings(): void
{
    $this->app->bind(ProcessMcpRequest::class);
    $this->app->bind(ProcessNotificationDelivery::class);
}
```

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
                'middleware' => ['mcp'],
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
            'cache_enabled' => env('MCP_DISCOVERY_CACHE', true),
        ],
        'routes' => [
            'prefix' => env('MCP_ROUTES_PREFIX', 'mcp'),
            'middleware' => ['mcp'],
        ],
        'events' => [
            'enabled' => env('MCP_EVENTS_ENABLED', true),
            'listeners' => [
                'activity' => env('MCP_LOG_ACTIVITY', true),
                'metrics' => env('MCP_TRACK_METRICS', true),
                'registration' => env('MCP_LOG_REGISTRATION', true),
            ],
        ],
        'queue' => [
            'enabled' => env('MCP_QUEUE_ENABLED', true),
            'default' => env('MCP_QUEUE_NAME', 'mcp'),
        ],
        'notifications' => [
            'enabled' => env('MCP_NOTIFICATIONS_ENABLED', true),
            'channels' => ['mail', 'slack'],
        ],
        'performance' => [
            'enabled' => env('MCP_PERFORMANCE_MONITORING', true),
            'auto_start' => env('MCP_AUTO_START_MONITORING', true),
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

### Enhanced Dependency Validation
```php
private function validateDependencies(): void
{
    $required = [
        'Symfony\\Component\\Process\\Process' => 'Symfony Process component is required for stdio transport',
        'Illuminate\\Support\\ServiceProvider' => 'Laravel Framework 11.x is required',
    ];

    $optional = [
        'Predis\\Client' => 'Redis support for caching and queues',
        'Pusher\\Pusher' => 'Pusher support for real-time notifications',
    ];

    // Validate required dependencies
    foreach ($required as $class => $message) {
        if (! class_exists($class)) {
            throw new \RuntimeException($message);
        }
    }

    // Check optional dependencies and log warnings
    foreach ($optional as $class => $description) {
        if (! class_exists($class)) {
            $this->logOptionalDependencyWarning($class, $description);
        }
    }
}

private function getPackageClass(string $package): string
{
    $classMap = [
        'symfony/process' => 'Symfony\\Component\\Process\\Process',
        'laravel/framework' => 'Illuminate\\Support\\ServiceProvider',
        'predis/predis' => 'Predis\\Client',
        'pusher/pusher-php-server' => 'Pusher\\Pusher',
    ];

    return $classMap[$package] ?? '';
}

private function logOptionalDependencyWarning(string $class, string $description): void
{
    if ($this->app->bound('log')) {
        $this->app['log']->info("Optional MCP dependency not found: {$class}", [
            'class' => $class,
            'description' => $description,
            'impact' => 'Some features may not be available'
        ]);
    }
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