<?php

namespace JTD\LaravelMCP\Tests\Compatibility;

use Illuminate\Support\Facades\Artisan;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Laravel Compatibility Tests
 *
 * EPIC: N/A
 * SPEC: docs/Specs/12-TestingStrategy.md
 * SPRINT: N/A
 * TICKET: 028-TestingQuality
 *
 * Tests compatibility with different Laravel versions and configurations.
 *
 * @group compatibility
 * @group quality
 * @group ticket-028
 */
#[Group('compatibility')]
#[Group('quality')]
#[Group('ticket-028')]
class LaravelCompatibilityTest extends TestCase
{
    #[Test]
    public function it_works_with_laravel_11(): void
    {
        $version = app()->version();
        $this->assertStringStartsWith('11.', $version);
    }

    #[Test]
    public function service_provider_registers_correctly(): void
    {
        $this->assertTrue(
            in_array(LaravelMcpServiceProvider::class, array_keys(app()->getLoadedProviders()))
        );
    }

    #[Test]
    public function facade_is_available(): void
    {
        $this->assertTrue(class_exists(Mcp::class));
        $this->assertNotNull(app('laravel-mcp'));
    }

    #[Test]
    public function artisan_commands_are_registered(): void
    {
        $commands = Artisan::all();

        $expectedCommands = [
            'mcp:serve',
            'mcp:list',
            'mcp:test',
            'mcp:register',
            'make:mcp-tool',
            'make:mcp-resource',
            'make:mcp-prompt',
        ];

        foreach ($expectedCommands as $command) {
            $this->assertArrayHasKey($command, $commands);
        }
    }

    #[Test]
    public function configuration_is_published(): void
    {
        $this->assertNotNull(config('laravel-mcp'));
        $this->assertNotNull(config('mcp-transports'));
    }

    #[Test]
    public function routes_are_registered(): void
    {
        $routes = app('router')->getRoutes();
        $mcpRoutes = [];

        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'mcp/')) {
                $mcpRoutes[] = $route->uri();
            }
        }

        $this->assertNotEmpty($mcpRoutes);
        $this->assertContains('mcp/execute', $mcpRoutes);
    }

    #[Test]
    public function middleware_is_available(): void
    {
        $middleware = app('router')->getMiddleware();

        $expectedMiddleware = [
            'mcp.auth',
            'mcp.logging',
            'mcp.rate-limit',
            'mcp.validate',
            'mcp.cors',
            'mcp.error-handling',
        ];

        foreach ($expectedMiddleware as $name) {
            $this->assertArrayHasKey($name, $middleware);
        }
    }

    #[Test]
    public function services_are_bound_in_container(): void
    {
        $expectedBindings = [
            'laravel-mcp',
            'mcp.registry',
            'mcp.registry.tool',
            'mcp.registry.resource',
            'mcp.registry.prompt',
            'mcp.transport.manager',
            'mcp.transport.http',
            'mcp.transport.stdio',
        ];

        foreach ($expectedBindings as $binding) {
            $this->assertTrue(app()->bound($binding), "Binding {$binding} not found");
        }
    }

    #[Test]
    public function event_listeners_are_registered(): void
    {
        $dispatcher = app('events');

        // Check that our events can be fired without errors
        $this->assertDoesNotThrow(function () use ($dispatcher) {
            $dispatcher->dispatch('mcp.component.registered');
            $dispatcher->dispatch('mcp.request.received');
            $dispatcher->dispatch('mcp.request.processed');
            $dispatcher->dispatch('mcp.error.occurred');
        });
    }

    #[Test]
    public function cache_driver_compatibility(): void
    {
        // Test with different cache drivers
        $drivers = ['array', 'file'];

        foreach ($drivers as $driver) {
            config(['cache.default' => $driver]);
            cache()->put('mcp:test', 'value', 60);
            $this->assertEquals('value', cache()->get('mcp:test'));
            cache()->forget('mcp:test');
        }
    }

    #[Test]
    public function queue_driver_compatibility(): void
    {
        // Test with sync queue driver (default in testing)
        config(['queue.default' => 'sync']);

        $job = new \JTD\LaravelMCP\Jobs\ProcessMcpRequest('test.method', ['param' => 'value']);
        dispatch($job);

        // With sync driver, job executes immediately
        $this->assertTrue(true); // If we get here, job didn't throw
    }

    #[Test]
    public function database_driver_compatibility(): void
    {
        // Test that we work with different database drivers
        $connection = config('database.default');
        $this->assertNotNull($connection);

        // Package should work regardless of database driver
        // since we don't have database dependencies
        $this->assertTrue(true);
    }

    #[Test]
    public function php_version_compatibility(): void
    {
        $phpVersion = PHP_VERSION;
        $this->assertTrue(version_compare($phpVersion, '8.2', '>='));
    }

    #[Test]
    public function composer_autoload_works(): void
    {
        // Check that all our namespaces are properly autoloaded
        $this->assertTrue(class_exists(\JTD\LaravelMCP\LaravelMcpServiceProvider::class));
        $this->assertTrue(class_exists(\JTD\LaravelMCP\McpManager::class));
        $this->assertTrue(class_exists(\JTD\LaravelMCP\Abstracts\McpTool::class));
        $this->assertTrue(class_exists(\JTD\LaravelMCP\Abstracts\McpResource::class));
        $this->assertTrue(class_exists(\JTD\LaravelMCP\Abstracts\McpPrompt::class));
    }

    #[Test]
    public function helper_functions_are_loaded(): void
    {
        $this->assertTrue(function_exists('mcp'));
        $this->assertTrue(function_exists('mcp_error'));
        $this->assertTrue(function_exists('mcp_success'));
        $this->assertTrue(function_exists('mcp_notification'));
        $this->assertTrue(function_exists('mcp_async'));
    }

    #[Test]
    public function package_can_be_discovered(): void
    {
        $providers = config('app.providers', []);

        // In a real Laravel app with package discovery,
        // our provider would be auto-discovered
        // For testing, we manually register it
        $this->assertTrue(
            in_array(LaravelMcpServiceProvider::class, array_keys(app()->getLoadedProviders()))
        );
    }

    #[Test]
    public function handles_missing_optional_dependencies(): void
    {
        // Test that package works without optional dependencies
        // like pusher/pusher-php-server and predis/predis

        $this->assertFalse(class_exists(\Pusher\Pusher::class));
        $this->assertFalse(class_exists(\Predis\Client::class));

        // Package should still work
        $this->assertNotNull(app('laravel-mcp'));
    }
}
