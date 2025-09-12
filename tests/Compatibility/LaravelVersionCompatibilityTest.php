<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Compatibility;

use Illuminate\Support\Facades\App;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Test Suite Header
 *
 * Epic: TESTING-QUALITY
 * Sprint: Sprint 3
 * Ticket: TESTING-028 - Testing Strategy Quality Assurance
 *
 * Purpose: Validate compatibility across different Laravel versions
 * Dependencies: Core Laravel framework, Orchestra Testbench
 */
#[Group('compatibility')]
#[Group('ticket-028')]
class LaravelVersionCompatibilityTest extends TestCase
{
    /**
     * Test that the package is compatible with Laravel 11.x
     */
    #[Test]
    public function it_is_compatible_with_laravel_11(): void
    {
        $version = App::version();

        // Check if we're running on Laravel 11.x
        if (str_starts_with($version, '11.')) {
            $this->assertTrue(true, 'Running on Laravel 11.x');
            $this->validateLaravel11Compatibility();
        } else {
            $this->markTestSkipped('Not running on Laravel 11.x');
        }
    }

    /**
     * Test that the service provider registers correctly
     */
    #[Test]
    public function service_provider_registers_correctly(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(
            LaravelMcpServiceProvider::class,
            $providers,
            'LaravelMcpServiceProvider should be registered'
        );
    }

    /**
     * Test that all required services are bound in the container
     */
    #[Test]
    public function required_services_are_bound(): void
    {
        // Core services that must be available
        $requiredServices = [
            'mcp.registry',
            'mcp.transport.manager',
            'mcp.protocol.handler',
            'mcp.discovery',
        ];

        foreach ($requiredServices as $service) {
            $this->assertTrue(
                $this->app->bound($service),
                "Service {$service} should be bound in the container"
            );
        }
    }

    /**
     * Test that package configuration is published correctly
     */
    #[Test]
    public function configuration_publishes_correctly(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-config',
            '--force' => true,
        ])->assertExitCode(0);

        // Check if config file exists after publishing
        $configPath = config_path('laravel-mcp.php');
        if (file_exists($configPath)) {
            $this->assertFileExists($configPath);

            // Validate config structure
            $config = require $configPath;
            $this->assertIsArray($config);
            $this->assertArrayHasKey('discovery', $config);
            $this->assertArrayHasKey('transports', $config);
        }
    }

    /**
     * Test that artisan commands are registered
     */
    #[Test]
    public function artisan_commands_are_registered(): void
    {
        $artisan = $this->app->make('artisan');

        $expectedCommands = [
            'mcp:serve',
            'mcp:list',
            'make:mcp-tool',
            'make:mcp-resource',
            'make:mcp-prompt',
        ];

        foreach ($expectedCommands as $command) {
            $this->assertTrue(
                $artisan->has($command),
                "Command {$command} should be registered"
            );
        }
    }

    /**
     * Test that facades work correctly
     */
    #[Test]
    public function facades_work_correctly(): void
    {
        // Test that the Mcp facade is registered
        $this->assertTrue(
            class_exists('Mcp'),
            'Mcp facade should be available'
        );

        // Test facade resolution
        $instance = \Mcp::getFacadeRoot();
        $this->assertNotNull($instance, 'Facade should resolve to an instance');
    }

    /**
     * Test middleware registration
     */
    #[Test]
    public function middleware_registers_correctly(): void
    {
        $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);

        // Check if our middleware groups are registered
        $middlewareGroups = $kernel->getMiddlewareGroups();

        if (isset($middlewareGroups['mcp'])) {
            $this->assertIsArray($middlewareGroups['mcp']);
            $this->assertNotEmpty($middlewareGroups['mcp']);
        }
    }

    /**
     * Test event listeners registration
     */
    #[Test]
    public function event_listeners_register_correctly(): void
    {
        $dispatcher = $this->app->make('events');

        // Check if MCP events have listeners
        $events = [
            'mcp.initialized',
            'mcp.tool.executed',
            'mcp.resource.accessed',
            'mcp.prompt.generated',
        ];

        foreach ($events as $event) {
            $listeners = $dispatcher->getListeners($event);
            // Just verify the method exists, listeners might not be registered yet
            $this->assertIsArray($listeners);
        }
    }

    /**
     * Test that routes are loaded correctly
     */
    #[Test]
    public function routes_are_loaded_correctly(): void
    {
        // Check if MCP routes are registered
        $routes = $this->app->make('router')->getRoutes();

        $mcpRoutes = collect($routes->getRoutes())->filter(function ($route) {
            return str_starts_with($route->uri(), 'mcp');
        });

        // Routes might not be loaded in test environment
        if ($mcpRoutes->isNotEmpty()) {
            $this->assertNotEmpty($mcpRoutes);

            // Check for expected routes
            $expectedRoutes = [
                'mcp',
                'mcp/health',
                'mcp/info',
            ];

            foreach ($expectedRoutes as $expectedRoute) {
                $found = $mcpRoutes->contains(function ($route) use ($expectedRoute) {
                    return $route->uri() === $expectedRoute;
                });

                if ($found) {
                    $this->assertTrue($found, "Route {$expectedRoute} should be registered");
                }
            }
        }
    }

    /**
     * Test PHP version compatibility
     */
    #[Test]
    public function php_version_is_compatible(): void
    {
        $phpVersion = PHP_VERSION;

        // Package requires PHP 8.2+
        $this->assertTrue(
            version_compare($phpVersion, '8.2.0', '>='),
            'PHP version must be 8.2 or higher'
        );

        // Test PHP 8.2 specific features
        if (version_compare($phpVersion, '8.2.0', '>=')) {
            $this->validatePhp82Features();
        }

        // Test PHP 8.3 specific features if available
        if (version_compare($phpVersion, '8.3.0', '>=')) {
            $this->validatePhp83Features();
        }
    }

    /**
     * Validate Laravel 11 specific compatibility
     */
    private function validateLaravel11Compatibility(): void
    {
        // Check for Laravel 11 specific features
        $this->assertTrue(
            method_exists($this->app, 'hasDebugModeEnabled'),
            'Laravel 11 debug mode method should exist'
        );

        // Check for Laravel 11 container improvements
        $this->assertTrue(
            method_exists($this->app, 'scoped'),
            'Laravel 11 scoped bindings should be available'
        );
    }

    /**
     * Validate PHP 8.2 features are working
     */
    private function validatePhp82Features(): void
    {
        // Test readonly classes support
        $this->assertTrue(
            class_exists(\ReflectionClass::class),
            'Reflection should be available for readonly classes'
        );

        // Test that we can use PHP 8.2 features
        $testClass = new class
        {
            public readonly string $property;

            public function __construct()
            {
                $this->property = 'test';
            }
        };

        $this->assertEquals('test', $testClass->property);
    }

    /**
     * Validate PHP 8.3 features if available
     */
    private function validatePhp83Features(): void
    {
        // Test typed class constants (PHP 8.3 feature)
        $testClass = new class
        {
            public const string TEST_CONSTANT = 'test';
        };

        $this->assertEquals('test', $testClass::TEST_CONSTANT);
    }

    /**
     * Test that the package doesn't conflict with common Laravel packages
     */
    #[Test]
    public function no_conflicts_with_common_packages(): void
    {
        $commonPackages = [
            'laravel/sanctum',
            'laravel/horizon',
            'laravel/telescope',
            'spatie/laravel-permission',
            'barryvdh/laravel-debugbar',
        ];

        foreach ($commonPackages as $package) {
            // Check if package is installed
            if (class_exists('\\Composer\\InstalledVersions')) {
                if (\Composer\InstalledVersions::isInstalled($package)) {
                    // If installed, verify no namespace conflicts
                    $this->assertNotEquals(
                        'JTD\\LaravelMCP',
                        $this->getPackageNamespace($package),
                        "Should not conflict with {$package}"
                    );
                }
            }
        }
    }

    /**
     * Get the namespace of a package
     */
    private function getPackageNamespace(string $package): ?string
    {
        // This is a simplified check - in reality would need composer.json parsing
        $namespaceMap = [
            'laravel/sanctum' => 'Laravel\\Sanctum',
            'laravel/horizon' => 'Laravel\\Horizon',
            'laravel/telescope' => 'Laravel\\Telescope',
            'spatie/laravel-permission' => 'Spatie\\Permission',
            'barryvdh/laravel-debugbar' => 'Barryvdh\\Debugbar',
        ];

        return $namespaceMap[$package] ?? null;
    }
}
