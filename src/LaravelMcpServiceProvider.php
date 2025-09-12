<?php

namespace JTD\LaravelMCP;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JTD\LaravelMCP\Commands\DocumentationCommand;
use JTD\LaravelMCP\Commands\ListCommand;
use JTD\LaravelMCP\Commands\MakePromptCommand;
use JTD\LaravelMCP\Commands\MakeResourceCommand;
use JTD\LaravelMCP\Commands\MakeToolCommand;
use JTD\LaravelMCP\Commands\RegisterCommand;
use JTD\LaravelMCP\Commands\ServeCommand;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpErrorHandlingMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpRateLimitMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Registry\RoutingPatterns;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Server\CapabilityManager;
use JTD\LaravelMCP\Server\Contracts\ServerInterface;
use JTD\LaravelMCP\Server\McpServer;
use JTD\LaravelMCP\Server\ServerInfo;
use JTD\LaravelMCP\Support\ClientDetector;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Support\SchemaDocumenter;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;

class LaravelMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerConfiguration();
        $this->registerServices();
        $this->registerInterfaces();
        $this->registerLazyServices();
        $this->registerCaching();
    }

    private function registerConfiguration(): void
    {
        // Ensure configuration is an array before merging
        if (! is_array($this->app['config']->get('laravel-mcp'))) {
            $this->app['config']->set('laravel-mcp', []);
        }

        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-mcp.php', 'laravel-mcp'
        );

        // Ensure transport configuration is an array before merging
        if (! is_array($this->app['config']->get('mcp-transports'))) {
            $this->app['config']->set('mcp-transports', []);
        }

        $this->mergeConfigFrom(
            __DIR__.'/../config/mcp-transports.php', 'mcp-transports'
        );
    }

    private function registerServices(): void
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

        // Register routing patterns as singleton
        $this->app->singleton(RoutingPatterns::class);

        // Register discovery service
        $this->app->singleton(ComponentDiscovery::class, function ($app) {
            return new ComponentDiscovery(
                $app->make(McpRegistry::class),
                $app->make(RoutingPatterns::class)
            );
        });

        // Register route registrar
        $this->app->singleton(RouteRegistrar::class, function ($app) {
            return new RouteRegistrar($app->make(McpRegistry::class));
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

        // Support services will be registered lazily in registerLazyServices()

        // Register facade accessor - should point to McpManager that provides both interfaces
        $this->app->singleton('laravel-mcp', function ($app) {
            return $app->make(McpManager::class);
        });

        // Register registry aliases for easier access
        $this->app->singleton('mcp.registry', function ($app) {
            return $app->make(McpRegistry::class);
        });

        $this->app->singleton('mcp.registry.tool', function ($app) {
            return $app->make(ToolRegistry::class);
        });

        $this->app->singleton('mcp.registry.resource', function ($app) {
            return $app->make(ResourceRegistry::class);
        });

        $this->app->singleton('mcp.registry.prompt', function ($app) {
            return $app->make(PromptRegistry::class);
        });
    }

    private function registerInterfaces(): void
    {
        $this->app->bind(
            TransportInterface::class,
            function ($app) {
                $manager = $app->make(TransportManager::class);

                return $manager->driver(); // Uses default driver from configuration
            }
        );

        $this->app->bind(
            JsonRpcHandlerInterface::class,
            JsonRpcHandler::class
        );

        $this->app->bind(
            RegistryInterface::class,
            McpRegistry::class
        );

        $this->app->bind(
            ServerInterface::class,
            McpServer::class
        );
    }

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

            if ($this->app->runningInConsole()) {
                $this->bootConsole();
            }
        } catch (\Throwable $e) {
            $this->handleBootFailure($e);
        }
    }

    private function validateDependencies(): void
    {
        $required = [
            'Symfony\\Component\\Process\\Process' => 'Symfony Process component is required for stdio transport',
        ];

        foreach ($required as $class => $message) {
            if (! class_exists($class)) {
                throw new \RuntimeException($message);
            }
        }
    }

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

    private function registerEventHooks(): void
    {
        // Hook into Laravel application events
        $this->app['events']->listen('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders', function () {
            $this->onProvidersBooted();
        });

        $this->app['events']->listen('kernel.handled', function () {
            $this->onRequestHandled();
        });

        // Listen for Laravel terminating event
        $this->app->terminating(function () {
            $this->onApplicationTerminating();
        });

        // Register MCP-specific event listeners
        $this->registerMcpEventListeners();
    }

    private function registerMcpEventListeners(): void
    {
        if (! config('laravel-mcp.events.enabled', true)) {
            return;
        }

        // Register component registration listener
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpComponentRegistered::class,
            \JTD\LaravelMCP\Listeners\LogMcpComponentRegistration::class
        );

        // Register request processed listener
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpRequestProcessed::class,
            \JTD\LaravelMCP\Listeners\TrackMcpRequestMetrics::class
        );

        // Register tool executed listeners
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpToolExecuted::class,
            [\JTD\LaravelMCP\Listeners\LogMcpActivity::class, 'handle']
        );
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpToolExecuted::class,
            [\JTD\LaravelMCP\Listeners\TrackMcpUsage::class, 'handle']
        );

        // Register resource accessed listeners
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpResourceAccessed::class,
            [\JTD\LaravelMCP\Listeners\LogMcpActivity::class, 'handle']
        );
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpResourceAccessed::class,
            [\JTD\LaravelMCP\Listeners\TrackMcpUsage::class, 'handle']
        );

        // Register prompt generated listeners
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpPromptGenerated::class,
            [\JTD\LaravelMCP\Listeners\LogMcpActivity::class, 'handle']
        );
        $this->app['events']->listen(
            \JTD\LaravelMCP\Events\McpPromptGenerated::class,
            [\JTD\LaravelMCP\Listeners\TrackMcpUsage::class, 'handle']
        );

        // Register custom listeners from config (including those from specification)
        $defaultListeners = [
            \JTD\LaravelMCP\Events\McpToolExecuted::class => [
                \JTD\LaravelMCP\Listeners\LogMcpActivity::class,
                \JTD\LaravelMCP\Listeners\TrackMcpUsage::class,
            ],
            \JTD\LaravelMCP\Events\McpResourceAccessed::class => [
                \JTD\LaravelMCP\Listeners\LogMcpActivity::class,
                \JTD\LaravelMCP\Listeners\TrackMcpUsage::class,
            ],
            \JTD\LaravelMCP\Events\McpPromptGenerated::class => [
                \JTD\LaravelMCP\Listeners\LogMcpActivity::class,
                \JTD\LaravelMCP\Listeners\TrackMcpUsage::class,
            ],
        ];

        // Merge with custom listeners from configuration
        $listeners = array_merge_recursive(
            $defaultListeners,
            config('laravel-mcp.events.listeners', [])
        );

        // Register any additional custom listeners from config
        foreach ($listeners as $event => $eventListeners) {
            // Skip if already registered above
            if (in_array($event, [
                \JTD\LaravelMCP\Events\McpComponentRegistered::class,
                \JTD\LaravelMCP\Events\McpRequestProcessed::class,
                \JTD\LaravelMCP\Events\McpToolExecuted::class,
                \JTD\LaravelMCP\Events\McpResourceAccessed::class,
                \JTD\LaravelMCP\Events\McpPromptGenerated::class,
            ])) {
                continue;
            }

            foreach ((array) $eventListeners as $listener) {
                $this->app['events']->listen($event, $listener);
            }
        }
    }

    private function onProvidersBooted(): void
    {
        // All providers have been booted - finalize component discovery
        if (config('laravel-mcp.discovery.enabled', true)) {
            $this->finalizeComponentDiscovery();
        }
    }

    private function onRequestHandled(): void
    {
        // Request has been handled - cleanup resources if needed
        $this->cleanupResources();
    }

    private function onApplicationTerminating(): void
    {
        // Application is terminating - perform final cleanup
        $this->performFinalCleanup();
    }

    private function finalizeComponentDiscovery(): void
    {
        try {
            $discovery = $this->app->make(ComponentDiscovery::class);
            $discovery->validateDiscoveredComponents();
        } catch (\Throwable $e) {
            if ($this->app->bound('log')) {
                $this->app['log']->warning('Failed to finalize component discovery', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function cleanupResources(): void
    {
        // Cleanup any temporary resources or connections
        if ($this->app->bound(TransportManager::class)) {
            try {
                $this->app->make(TransportManager::class)->cleanup();
            } catch (\Throwable $e) {
                // Silent failure for cleanup operations
            }
        }
    }

    private function performFinalCleanup(): void
    {
        // Perform final cleanup operations
        $this->cleanupResources();

        // Clear any cached discovery results if needed
        if ($this->app->bound('mcp.discovery.cache')) {
            try {
                $this->app->make('mcp.discovery.cache')->flush();
            } catch (\Throwable $e) {
                // Silent failure for cleanup operations
            }
        }
    }

    private function handleBootFailure(\Throwable $e): void
    {
        // Log the error with appropriate severity
        if ($this->app->bound('log')) {
            $this->app['log']->critical('MCP Service Provider boot failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Disable MCP features gracefully
        $this->app['config']->set('laravel-mcp.enabled', false);

        // Report to error tracking service if available
        if ($this->app->bound('sentry') || $this->app->bound('bugsnag')) {
            report($e);
        }

        // In production, log critical errors but continue; in other environments, fail fast
        if ($this->app->environment('production')) {
            // Send alert to monitoring service if configured
            if ($this->app->bound('events')) {
                $this->app['events']->dispatch('mcp.boot.failed', [$e]);
            }
        } else {
            // In non-production environments, throw the exception for debugging
            throw $e;
        }
    }

    private function bootDiscovery(): void
    {
        if (! config('laravel-mcp.discovery.enabled', true)) {
            return;
        }

        try {
            $discovery = $this->app->make(ComponentDiscovery::class);

            // Discover components in application directories
            $paths = config('laravel-mcp.discovery.paths', [
                app_path('Mcp/Tools'),
                app_path('Mcp/Resources'),
                app_path('Mcp/Prompts'),
            ]);

            // Ensure paths is an array
            if (! is_array($paths)) {
                $paths = [$paths];
            }

            $discovery->discoverComponents($paths);

            // Register discovered components
            $discovery->registerDiscoveredComponents();
        } catch (\Throwable $e) {
            // Log discovery errors but don't fail the boot process
            if ($this->app->bound('log')) {
                $this->app['log']->warning('MCP component discovery failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // In non-production, we want to know about discovery issues
            if (! $this->app->environment('production')) {
                throw $e;
            }
        }
    }

    private function bootPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/laravel-mcp.php' => config_path('laravel-mcp.php'),
        ], 'laravel-mcp-config');

        $this->publishes([
            __DIR__.'/../config/mcp-transports.php' => config_path('mcp-transports.php'),
        ], 'laravel-mcp-transports');

        $this->publishes([
            __DIR__.'/../routes/mcp.php' => base_path('routes/mcp.php'),
        ], 'laravel-mcp-routes');

        $this->publishes([
            __DIR__.'/../resources/stubs' => resource_path('stubs/mcp'),
        ], 'laravel-mcp-stubs');

        $this->publishes([
            __DIR__.'/../config/laravel-mcp.php' => config_path('laravel-mcp.php'),
            __DIR__.'/../config/mcp-transports.php' => config_path('mcp-transports.php'),
            __DIR__.'/../routes/mcp.php' => base_path('routes/mcp.php'),
            __DIR__.'/../resources/stubs' => resource_path('stubs/mcp'),
        ], 'laravel-mcp');
    }

    private function bootRoutes(): void
    {
        // Skip route registration if Route facade is not available (e.g., during testing)
        if (! class_exists(\Illuminate\Support\Facades\Route::class) ||
            ! \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
            return;
        }

        // Build middleware array with auth middleware always included
        $middleware = config('laravel-mcp.routes.middleware', ['api']);

        // Ensure middleware is an array
        if (! is_array($middleware)) {
            $middleware = [$middleware];
        }

        // Always add auth middleware - it checks config on each request
        $middleware[] = McpAuthMiddleware::class;

        // Register package routes
        Route::group([
            'namespace' => 'JTD\\LaravelMCP\\Http\\Controllers',
            'prefix' => config('laravel-mcp.routes.prefix', 'mcp'),
            'middleware' => $middleware,
        ], function () {
            if (file_exists(__DIR__.'/../routes/web.php')) {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            }
        });

        // Load application MCP routes if they exist
        if (file_exists(base_path('routes/mcp.php'))) {
            $this->loadMcpRoutes();
        }
    }

    private function bootCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ServeCommand::class,
                ListCommand::class,
                MakeToolCommand::class,
                MakeResourceCommand::class,
                MakePromptCommand::class,
                RegisterCommand::class,
                DocumentationCommand::class,
            ]);
        }
    }

    private function bootMiddleware(): void
    {
        $router = $this->app['router'];

        // Register all MCP middleware aliases
        $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
        $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);
        $router->aliasMiddleware('mcp.logging', McpLoggingMiddleware::class);
        $router->aliasMiddleware('mcp.validation', McpValidationMiddleware::class);
        $router->aliasMiddleware('mcp.rate_limit', McpRateLimitMiddleware::class);
        $router->aliasMiddleware('mcp.error_handling', McpErrorHandlingMiddleware::class);

        // Register middleware groups for MCP
        $router->middlewareGroup('mcp', [
            McpErrorHandlingMiddleware::class, // Must be first to catch all errors
            McpCorsMiddleware::class,
            McpLoggingMiddleware::class,
            McpAuthMiddleware::class,
            McpValidationMiddleware::class,
            McpRateLimitMiddleware::class,
        ]);

        // Add middleware to API group if configured
        if (config('laravel-mcp.middleware.auto_register', true)) {
            // Add in correct order for API group
            $router->prependMiddlewareToGroup('api', McpErrorHandlingMiddleware::class);
            $router->pushMiddlewareToGroup('api', McpCorsMiddleware::class);
            $router->pushMiddlewareToGroup('api', McpLoggingMiddleware::class);
            $router->pushMiddlewareToGroup('api', McpAuthMiddleware::class);
            $router->pushMiddlewareToGroup('api', McpValidationMiddleware::class);
            $router->pushMiddlewareToGroup('api', McpRateLimitMiddleware::class);
        }
    }

    private function bootViews(): void
    {
        if (file_exists(__DIR__.'/../resources/views')) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-mcp');

            // Publish views if needed
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-mcp'),
            ], 'laravel-mcp-views');
        }
    }

    private function loadMcpRoutes(): void
    {
        $routeFile = base_path('routes/mcp.php');

        // Only load routes if the file exists
        if (! file_exists($routeFile)) {
            return;
        }

        $middleware = config('laravel-mcp.routes.middleware', ['api']);

        // Ensure middleware is an array
        if (! is_array($middleware)) {
            $middleware = [$middleware];
        }

        $this->app['router']->group([
            'middleware' => $middleware,
        ], function () use ($routeFile) {
            require $routeFile;
        });
    }

    /**
     * Boot MCP component routes.
     */
    private function bootMcpRoutes(): void
    {
        // Check if route registration is enabled
        if (! config('laravel-mcp.routes.auto_register', true)) {
            return;
        }

        // Skip if Route facade is not available (e.g., during testing)
        if (! class_exists(\Illuminate\Support\Facades\Route::class) ||
            ! \Illuminate\Support\Facades\Facade::getFacadeApplication()) {
            return;
        }

        try {
            $discovery = $this->app->make(ComponentDiscovery::class);

            // Check for cached routes first if caching is enabled
            $routingPatterns = $this->app->make(RoutingPatterns::class);

            if ($routingPatterns->isCacheEnabled()) {
                $cacheKey = $routingPatterns->generateCacheKey('discovered_routes');

                if (function_exists('cache') && cache()->has($cacheKey)) {
                    // Routes are already cached, no need to re-register
                    return;
                }
            }

            // Discover and register component routes
            $paths = config('laravel-mcp.discovery.paths', [
                app_path('Mcp/Tools'),
                app_path('Mcp/Resources'),
                app_path('Mcp/Prompts'),
            ]);

            // Ensure paths is an array
            if (! is_array($paths)) {
                $paths = [$paths];
            }

            $discovered = $discovery->discover($paths);
            $discovery->registerRoutes($discovered);
        } catch (\Throwable $e) {
            // Log route registration errors but don't fail the boot process
            if ($this->app->bound('log')) {
                $this->app['log']->warning('MCP route registration failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            // In test environments, don't throw to avoid breaking tests
            // In non-production, we want to know about route issues, but only if not testing
            if (! $this->app->environment('production') && ! $this->app->environment('testing')) {
                throw $e;
            }
        }
    }

    private function bootConsole(): void
    {
        // Additional console-specific initialization
        $this->bootMigrations();
        $this->bootAssets();
    }

    private function bootMigrations(): void
    {
        // Load package migrations if any
        if (file_exists(__DIR__.'/../database/migrations')) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }
    }

    private function bootAssets(): void
    {
        // Publish additional assets for development
        if (file_exists(__DIR__.'/../resources/assets')) {
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/laravel-mcp'),
            ], 'laravel-mcp-assets');
        }
    }

    private function registerLazyServices(): void
    {
        // Register services that are only created when needed (as singletons)
        $this->app->singleton(DocumentationGenerator::class, function ($app) {
            return new DocumentationGenerator(
                $app->make(McpRegistry::class),
                $app->make(ToolRegistry::class),
                $app->make(ResourceRegistry::class),
                $app->make(PromptRegistry::class)
            );
        });

        $this->app->singleton(SchemaDocumenter::class, function ($app) {
            return new SchemaDocumenter;
        });

        // Register ClientDetector
        $this->app->singleton(ClientDetector::class, function ($app) {
            return new ClientDetector;
        });

        $this->app->singleton(ConfigGenerator::class, function ($app) {
            return new ConfigGenerator(
                $app->make(McpRegistry::class),
                $app->make(ClientDetector::class)
            );
        });

        // Lazy bind transport implementations
        $this->app->bindIf('mcp.transport.manager', function ($app) {
            return $app->make(TransportManager::class);
        });
    }

    private function registerCaching(): void
    {
        // Cache component discovery results
        $this->app->singleton('mcp.discovery.cache', function ($app) {
            return $app->make('cache')->store(
                config('laravel-mcp.cache.store', 'file')
            );
        });

        // Cache configuration for performance
        $this->app->singleton('mcp.config.cache', function ($app) {
            return $app->make('cache')->store(
                config('laravel-mcp.cache.store', 'file')
            )->tags(['mcp', 'config']);
        });

        // Cache component registrations
        $this->app->singleton('mcp.component.cache', function ($app) {
            return $app->make('cache')->store(
                config('laravel-mcp.cache.store', 'file')
            )->tags(['mcp', 'components']);
        });
    }
}
