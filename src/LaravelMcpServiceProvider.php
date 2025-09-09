<?php

namespace JTD\LaravelMCP;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Support\DocumentationGenerator;
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

        // Support services will be registered lazily in registerLazyServices()

        // Register facade accessor
        $this->app->singleton('laravel-mcp', function ($app) {
            return $app->make(McpRegistry::class);
        });
    }

    private function registerInterfaces(): void
    {
        $this->app->bind(
            TransportInterface::class,
            fn ($app) => $app->make(TransportManager::class)->getDefaultTransport()
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

    public function boot(): void
    {
        try {
            $this->validateDependencies();
            $this->registerEventHooks();

            $this->bootPublishing();
            $this->bootRoutes();
            $this->bootCommands();
            $this->bootMiddleware();
            $this->bootDiscovery();
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
        // Log the error
        if ($this->app->bound('log')) {
            $this->app['log']->error('MCP Service Provider boot failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Disable MCP features gracefully
        config(['laravel-mcp.enabled' => false]);

        // Don't break the application in production
        if (! $this->app->environment('production')) {
            throw $e;
        }
    }

    private function bootDiscovery(): void
    {
        if (! config('laravel-mcp.discovery.enabled', true)) {
            return;
        }

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Discover components in application directories
        $discovery->discoverComponents(
            config('laravel-mcp.discovery.paths', [
                app_path('Mcp/Tools'),
                app_path('Mcp/Resources'),
                app_path('Mcp/Prompts'),
            ])
        );

        // Register discovered components
        $discovery->registerDiscoveredComponents();
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
        // Register package routes
        Route::group([
            'namespace' => 'JTD\\LaravelMCP\\Http\\Controllers',
            'prefix' => config('laravel-mcp.routes.prefix', 'mcp'),
            'middleware' => config('laravel-mcp.routes.middleware', ['api']),
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
                // Commands will be registered here in future implementation
                // ServeCommand::class,
                // MakeToolCommand::class,
                // MakeResourceCommand::class,
                // MakePromptCommand::class,
                // ListCommand::class,
                // RegisterCommand::class,
            ]);
        }
    }

    private function bootMiddleware(): void
    {
        $router = $this->app['router'];

        // Register middleware aliases
        $router->aliasMiddleware('mcp.auth', McpAuthMiddleware::class);
        $router->aliasMiddleware('mcp.cors', McpCorsMiddleware::class);

        // Add middleware to groups if configured
        if (config('laravel-mcp.middleware.auto_register', true)) {
            $router->pushMiddlewareToGroup('api', McpCorsMiddleware::class);

            // Only add auth middleware if authentication is enabled
            if (config('laravel-mcp.auth.enabled', false)) {
                $router->pushMiddlewareToGroup('api', McpAuthMiddleware::class);
            }
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
        $this->app['router']->group([
            'middleware' => config('laravel-mcp.routes.middleware', ['api']),
        ], function () {
            require base_path('routes/mcp.php');
        });
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
        // Register services that are only created when needed
        $this->app->bindIf(DocumentationGenerator::class, function ($app) {
            return new DocumentationGenerator(
                $app->make(McpRegistry::class),
                $app->make(ToolRegistry::class),
                $app->make(ResourceRegistry::class),
                $app->make(PromptRegistry::class)
            );
        });

        $this->app->bindIf(ConfigGenerator::class, function ($app) {
            return new ConfigGenerator(
                $app->make(McpRegistry::class)
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
