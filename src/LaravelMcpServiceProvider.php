<?php

namespace JTD\LaravelMCP;

use Illuminate\Support\ServiceProvider;

class LaravelMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/laravel-mcp.php', 'laravel-mcp'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/mcp-transports.php', 'mcp-transports'
        );
    }

    public function boot(): void
    {
        $this->bootPublishing();
        $this->bootRoutes();
        
        if ($this->app->runningInConsole()) {
            $this->bootConsole();
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
        if (file_exists(base_path('routes/mcp.php'))) {
            $this->loadMcpRoutes();
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
        // Commands will be registered here in future implementation
    }
}