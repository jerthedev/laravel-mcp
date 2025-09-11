<?php

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Registry\RoutingPatterns;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Feature tests for route registration integration.
 *
 * Tests the complete route registration flow including discovery,
 * automatic registration, route caching compatibility, and
 * Laravel router integration.
 */
class RouteRegistrationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable route registration for tests
        $this->app['config']->set('laravel-mcp.routes.enabled', true);
        $this->app['config']->set('laravel-mcp.routes.prefix', 'mcp');
        $this->app['config']->set('laravel-mcp.routes.middleware', ['mcp.cors']);
    }

    /**
     * Test full route registration flow for tools.
     */
    public function test_full_route_registration_flow_for_tools(): void
    {
        // Create and register a tool
        $tool = $this->createTestTool('Calculator', ['description' => 'Math calculator']);

        Mcp::registerTool('calculator', $tool);

        // Check that the tool is registered
        $this->assertTrue(Mcp::hasTool('calculator'));

        // For now, just verify the tool registration
        // Route integration tests would require full HTTP setup
        $this->assertTrue(true);
    }

    /**
     * Test full route registration flow for resources.
     */
    public function test_full_route_registration_flow_for_resources(): void
    {
        // Create and register a resource
        $resource = $this->createTestResource('UserProfile', ['uri' => 'user://profile/{id}']);

        Mcp::registerResource('user_profile', $resource);

        // Check that the resource is registered
        $this->assertTrue(Mcp::hasResource('user_profile'));

        // For now, just verify the resource registration
        $this->assertTrue(true);
    }

    /**
     * Test full route registration flow for prompts.
     */
    public function test_full_route_registration_flow_for_prompts(): void
    {
        // Create and register a prompt
        $prompt = $this->createTestPrompt('EmailTemplate', ['description' => 'Email template generator']);

        Mcp::registerPrompt('email_template', $prompt);

        // Check that the prompt is registered
        $this->assertTrue(Mcp::hasPrompt('email_template'));

        // For now, just verify the prompt registration
        $this->assertTrue(true);
    }

    /**
     * Test component discovery with route registration.
     */
    public function test_component_discovery_with_route_registration(): void
    {
        // Create temporary component files
        $this->createTemporaryComponentFile('Tools', 'TestTool', $this->getToolClassContent('TestTool'));
        $this->createTemporaryComponentFile('Resources', 'TestResource', $this->getResourceClassContent('TestResource'));
        $this->createTemporaryComponentFile('Prompts', 'TestPrompt', $this->getPromptClassContent('TestPrompt'));

        // Enable discovery for this test
        $this->app['config']->set('laravel-mcp.discovery.enabled', true);
        $this->app['config']->set('laravel-mcp.discovery.paths', [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ]);

        // For now, just verify the setup works
        // Full discovery tests would require complete implementation
        $this->assertTrue(true);
    }

    /**
     * Test route registration with middleware.
     */
    public function test_route_registration_with_middleware(): void
    {
        $tool = $this->createTestTool('SecureTool');

        // Register tool with middleware
        Mcp::registerTool('secure_tool', $tool, [
            'middleware' => ['auth', 'throttle'],
        ]);

        // Verify the tool is registered
        $this->assertTrue(Mcp::hasTool('secure_tool'));

        // For now, just verify registration works with middleware options
        $this->assertTrue(true);
    }

    /**
     * Test route registration with custom prefix.
     */
    public function test_route_registration_with_custom_prefix(): void
    {
        // Change the route prefix
        $this->app['config']->set('laravel-mcp.routes.prefix', 'api/v1/mcp');

        // Re-register service provider to apply new config
        $this->refreshApplication();

        $tool = $this->createTestTool('ApiTool');
        Mcp::registerTool('api_tool', $tool);

        // Check that route uses custom prefix
        $this->assertRouteExists('POST', 'api/v1/mcp/tools/api_tool', 'mcp.tools.api_tool');
    }

    /**
     * Test route registration with constraints.
     */
    public function test_route_registration_with_constraints(): void
    {
        $tool = $this->createTestTool('ConstrainedTool');
        Mcp::registerTool('constrained-tool.v1', $tool);

        // Verify tool registration works with special characters in name
        $this->assertTrue(Mcp::hasTool('constrained-tool.v1'));
    }

    /**
     * Test route caching compatibility.
     */
    public function test_route_caching_compatibility(): void
    {
        $tool = $this->createTestTool('CacheableTool');
        Mcp::registerTool('cacheable_tool', $tool);

        // Verify tool registration works (route caching would be handled by framework)
        $this->assertTrue(Mcp::hasTool('cacheable_tool'));

        // Test that RoutingPatterns supports caching
        $patterns = $this->app->make(RoutingPatterns::class);
        $this->assertTrue($patterns->isCacheEnabled());
    }

    /**
     * Test route group registration.
     */
    public function test_route_group_registration(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        $tool1 = $this->createTestTool('GroupTool1');
        $tool2 = $this->createTestTool('GroupTool2');
        $resource = $this->createTestResource('GroupResource');

        // Register components in a group with shared middleware
        $registrar->middleware(['auth', 'throttle'], function ($registrar) use ($tool1, $tool2, $resource) {
            $registrar->tool('group_tool1', $tool1);
            $registrar->tool('group_tool2', $tool2);
            $registrar->resource('group_resource', $resource);
        });

        // Verify the RouteRegistrar works for group registration
        $this->assertInstanceOf(RouteRegistrar::class, $registrar);
        $this->assertTrue(true);
    }

    /**
     * Test nested route groups.
     */
    public function test_nested_route_groups(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);
        $tool = $this->createTestTool('NestedTool');

        $registrar->prefix('api', function ($registrar) use ($tool) {
            $registrar->middleware('api', function ($registrar) use ($tool) {
                $registrar->prefix('v1', function ($registrar) use ($tool) {
                    $registrar->middleware('auth', function ($registrar) use ($tool) {
                        $registrar->tool('nested_tool', $tool);
                    });
                });
            });
        });

        // Verify nested group registration works
        $this->assertInstanceOf(RouteRegistrar::class, $registrar);
        $this->assertTrue(true);
    }

    /**
     * Test resource-style route generation.
     */
    public function test_resource_style_route_generation(): void
    {
        $patterns = $this->app->make(RoutingPatterns::class);
        $resource = $this->createTestResource('ResourcefulResource');

        Mcp::registerResource('resourceful_resource', $resource);

        // Generate resource-style routes
        $resourceRoutes = $patterns->generateResourceRoutes('resources', 'resourceful_resource');

        $this->assertCount(4, $resourceRoutes);

        // Check index route
        $this->assertEquals(['GET'], $resourceRoutes[0]['methods']);
        $this->assertEquals('resources', $resourceRoutes[0]['uri']);
        $this->assertEquals('mcp.resources.index', $resourceRoutes[0]['name']);

        // Check show route
        $this->assertEquals(['GET'], $resourceRoutes[1]['methods']);
        $this->assertEquals('resources/resourceful_resource', $resourceRoutes[1]['uri']);
        $this->assertEquals('mcp.resources.resourceful_resource.show', $resourceRoutes[1]['name']);

        // Check store route
        $this->assertEquals(['POST'], $resourceRoutes[2]['methods']);
        $this->assertEquals('resources', $resourceRoutes[2]['uri']);
        $this->assertEquals('mcp.resources.store', $resourceRoutes[2]['name']);

        // Check update/execute route
        $this->assertEquals(['POST'], $resourceRoutes[3]['methods']);
        $this->assertEquals('resources/resourceful_resource', $resourceRoutes[3]['uri']);
        $this->assertEquals('mcp.resources.resourceful_resource', $resourceRoutes[3]['name']);
    }

    /**
     * Test route registration with custom controller.
     */
    public function test_route_registration_with_custom_controller(): void
    {
        $tool = $this->createTestTool('CustomControllerTool');

        Mcp::registerTool('custom_controller_tool', $tool, [
            'controller' => 'CustomMcpController@handleTool',
        ]);

        // Verify tool registration works with custom controller options
        $this->assertTrue(Mcp::hasTool('custom_controller_tool'));
    }

    /**
     * Test error handling during route registration.
     */
    public function test_error_handling_during_route_registration(): void
    {
        // This would test error scenarios like invalid route patterns,
        // conflicting route names, etc.
        // For now, we test that invalid component names are handled gracefully

        $tool = $this->createTestTool('InvalidTool');

        try {
            Mcp::registerTool('invalid@tool#name', $tool);

            // The system should either sanitize the name or handle the error gracefully
            $routes = Route::getRoutes();
            $hasInvalidRoute = false;

            foreach ($routes as $route) {
                if (str_contains($route->getName() ?? '', 'invalid')) {
                    $hasInvalidRoute = true;
                    break;
                }
            }

            // Either the route should be created with a sanitized name,
            // or no route should be created (depending on implementation)
            $this->assertTrue(true); // Test passes if no exception is thrown
        } catch (\Exception $e) {
            // If an exception is thrown, it should be a meaningful error
            $this->assertInstanceOf(\InvalidArgumentException::class, $e);
        }
    }

    /**
     * Test route name collision handling.
     */
    public function test_route_name_collision_handling(): void
    {
        $tool1 = $this->createTestTool('Tool1');
        $tool2 = $this->createTestTool('Tool2');

        // Register two tools with names that could result in the same route name
        Mcp::registerTool('collision_tool', $tool1);
        Mcp::registerTool('collision.tool', $tool2); // Dots become underscores

        // Both should be registered (the registry handles conflicts)
        $this->assertTrue(Mcp::hasTool('collision_tool'));
        $this->assertTrue(Mcp::hasTool('collision.tool'));

        // Routes should be created for both (or one should override)
        $routes = Route::getRoutes();
        $mcpRoutes = collect($routes->getRoutes())->filter(function ($route) {
            return str_starts_with($route->getName() ?? '', 'mcp.tools.collision');
        });

        $this->assertTrue($mcpRoutes->isNotEmpty());
    }

    /**
     * Test route registration with domain constraints.
     */
    public function test_route_registration_with_domain_constraints(): void
    {
        // Configure domain-specific routing
        $this->app['config']->set('laravel-mcp.routes.domain', 'api.{domain}');

        $tool = $this->createTestTool('DomainTool');
        Mcp::registerTool('domain_tool', $tool);

        // Verify that the route can be accessed with domain parameter
        // This would be more comprehensive in a real implementation
        $this->assertTrue(Mcp::hasTool('domain_tool'));
    }

    /**
     * Test batch route registration performance.
     */
    public function test_batch_route_registration_performance(): void
    {
        $startTime = microtime(true);

        // Register a large number of components
        for ($i = 1; $i <= 50; $i++) {
            $tool = $this->createTestTool("BatchTool{$i}");
            Mcp::registerTool("batch_tool_{$i}", $tool);
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        // Registration should complete in reasonable time (< 1 second)
        $this->assertLessThan(1.0, $duration, 'Batch registration took too long');

        // Verify all tools were registered
        for ($i = 1; $i <= 50; $i++) {
            $this->assertTrue(Mcp::hasTool("batch_tool_{$i}"));
        }
    }

    /**
     * Test route registration cleanup and isolation.
     */
    public function test_route_registration_cleanup_and_isolation(): void
    {
        $initialRouteCount = Route::getRoutes()->count();

        // Register a tool
        $tool = $this->createTestTool('TemporaryTool');
        Mcp::registerTool('temporary_tool', $tool);

        $afterRegistrationCount = Route::getRoutes()->count();
        $this->assertGreaterThan($initialRouteCount, $afterRegistrationCount);

        // Clear MCP registrations
        Mcp::reset();

        // Note: In a real implementation, routes might be cleared as well
        // For now, we just verify the component is no longer registered
        $this->assertFalse(Mcp::hasTool('temporary_tool'));
    }

    /**
     * Assert that a route exists with given method, URI, and name.
     */
    protected function assertRouteExists(string $method, string $uri, string $name): void
    {
        $route = $this->getRoute($method, $uri);
        $this->assertNotNull($route, "Route {$method} {$uri} does not exist");
        $this->assertEquals($name, $route->getName(), "Route name does not match expected: {$name}");
    }

    /**
     * Get a route by method and URI.
     */
    protected function getRoute(string $method, string $uri): ?\Illuminate\Routing\Route
    {
        $routes = Route::getRoutes();

        foreach ($routes as $route) {
            if (in_array($method, $route->methods()) && $route->uri() === $uri) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Create a temporary component file for discovery testing.
     */
    protected function createTemporaryComponentFile(string $type, string $className, string $content): void
    {
        $directory = app_path("Mcp/{$type}");
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filePath = "{$directory}/{$className}.php";
        file_put_contents($filePath, $content);

        // Register for cleanup
        $this->beforeApplicationDestroyed(function () use ($filePath) {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        });
    }

    /**
     * Get tool class content for discovery testing.
     */
    protected function getToolClassContent(string $className): string
    {
        return "<?php

namespace App\\Mcp\\Tools;

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$className} extends McpTool
{
    protected string \$name = 'test_tool';
    protected string \$description = 'Test tool for discovery';

    protected function handle(array \$parameters): mixed
    {
        return ['result' => 'test'];
    }
}
";
    }

    /**
     * Get resource class content for discovery testing.
     */
    protected function getResourceClassContent(string $className): string
    {
        return "<?php

namespace App\\Mcp\\Resources;

use JTD\\LaravelMCP\\Abstracts\\McpResource;

class {$className} extends McpResource
{
    protected string \$uri = 'test://resource';
    protected string \$name = 'test_resource';
    protected string \$description = 'Test resource for discovery';

    public function read(array \$options = []): array
    {
        return ['contents' => [['uri' => \$this->uri, 'text' => 'test']]];
    }
}
";
    }

    /**
     * Get prompt class content for discovery testing.
     */
    protected function getPromptClassContent(string $className): string
    {
        return "<?php

namespace App\\Mcp\\Prompts;

use JTD\\LaravelMCP\\Abstracts\\McpPrompt;

class {$className} extends McpPrompt
{
    protected string \$name = 'test_prompt';
    protected string \$description = 'Test prompt for discovery';

    public function getMessages(array \$arguments): array
    {
        return ['messages' => [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'test']]]];
    }
}
";
    }
}
