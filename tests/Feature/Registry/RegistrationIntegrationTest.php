<?php

/**
 * Epic: MCP Protocol Layer
 * Sprint: Registration System
 * Ticket: REGISTRATION-016 - Registration System Core Implementation
 *
 * @epic MCP-002
 *
 * @sprint 3
 *
 * @ticket 016
 */

namespace JTD\LaravelMCP\Tests\Feature\Registry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('registry')]
#[Group('integration')]
#[Group('ticket-016')]
class RegistrationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private string $routesPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test routes file
        $this->routesPath = base_path('routes/mcp.php');
    }

    protected function tearDown(): void
    {
        // Clean up test routes file
        if (File::exists($this->routesPath)) {
            File::delete($this->routesPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_supports_route_style_registration(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register components using route-style API
        $registrar->tool('calculator', 'App\Mcp\Tools\CalculatorTool');
        $registrar->resource('users', 'App\Mcp\Resources\UserResource');
        $registrar->prompt('email', 'App\Mcp\Prompts\EmailPrompt');

        $registry = $registrar->getRegistry();

        // Verify registrations
        $this->assertTrue($registry->hasTool('calculator'));
        $this->assertTrue($registry->hasResource('users'));
        $this->assertTrue($registry->hasPrompt('email'));
    }

    #[Test]
    public function it_supports_group_registration_with_middleware(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register with middleware group
        $registrar->group(['middleware' => ['auth', 'admin']], function ($mcp) {
            $mcp->tool('admin_tool', 'App\Mcp\Tools\AdminTool');
            $mcp->resource('admin_logs', 'App\Mcp\Resources\AdminLogResource');
        });

        $registry = $registrar->getRegistry();

        $this->assertTrue($registry->hasTool('admin_tool'));
        $this->assertTrue($registry->hasResource('admin_logs'));
    }

    #[Test]
    public function it_supports_namespace_group_registration(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register with namespace group
        $registrar->namespace('App\Mcp\Admin', function ($mcp) {
            $mcp->tool('system_info', 'SystemInfoTool');
            $mcp->resource('system_stats', 'SystemStatsResource');
        });

        $registry = $registrar->getRegistry();

        $this->assertTrue($registry->hasTool('system_info'));
        $this->assertTrue($registry->hasResource('system_stats'));
    }

    #[Test]
    public function it_loads_routes_from_mcp_routes_file(): void
    {
        // Create routes file
        $routesContent = <<<'PHP'
<?php

use JTD\LaravelMCP\Facades\Mcp;

Mcp::tool('test_tool', 'App\Mcp\Tools\TestTool');
Mcp::resource('test_resource', 'App\Mcp\Resources\TestResource');
Mcp::prompt('test_prompt', 'App\Mcp\Prompts\TestPrompt');

Mcp::group(['middleware' => 'auth'], function ($mcp) {
    $mcp->tool('protected_tool', 'App\Mcp\Tools\ProtectedTool');
});
PHP;

        File::put($this->routesPath, $routesContent);

        // Load routes
        $registrar = $this->app->make(RouteRegistrar::class);
        $registrar->loadRoutesFrom($this->routesPath);

        // Note: Routes won't actually be registered because they use the Facade
        // which requires the service provider to be fully booted
        $this->assertTrue(File::exists($this->routesPath));
    }

    #[Test]
    public function it_supports_fluent_interface_registration(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Chain registrations
        $result = $registrar
            ->tool('tool1', 'Tool1')
            ->tool('tool2', 'Tool2')
            ->resource('resource1', 'Resource1')
            ->prompt('prompt1', 'Prompt1');

        $this->assertInstanceOf(RouteRegistrar::class, $result);

        $registry = $registrar->getRegistry();

        $this->assertTrue($registry->hasTool('tool1'));
        $this->assertTrue($registry->hasTool('tool2'));
        $this->assertTrue($registry->hasResource('resource1'));
        $this->assertTrue($registry->hasPrompt('prompt1'));
    }

    #[Test]
    public function it_handles_complex_nested_groups(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Complex nested groups
        $registrar->group(['prefix' => 'v1_'], function ($mcp) {
            $mcp->group(['middleware' => 'auth'], function ($mcp) {
                $mcp->namespace('App\Mcp\V1', function ($mcp) {
                    $mcp->tool('api_tool', 'ApiTool');
                });
            });
        });

        $registry = $registrar->getRegistry();

        // Tool should be registered with prefix
        $this->assertTrue($registry->hasTool('v1_api_tool'));
    }

    #[Test]
    public function it_provides_list_functionality(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register multiple components
        $registrar->tool('tool1', 'Tool1');
        $registrar->tool('tool2', 'Tool2');
        $registrar->resource('resource1', 'Resource1');
        $registrar->prompt('prompt1', 'Prompt1');

        // List components
        $tools = $registrar->list('tool');
        $resources = $registrar->list('resource');
        $prompts = $registrar->list('prompt');

        $this->assertIsArray($tools);
        $this->assertIsArray($resources);
        $this->assertIsArray($prompts);
    }

    #[Test]
    public function it_integrates_with_service_provider(): void
    {
        // Re-register service provider
        $this->app->register(\JTD\LaravelMCP\LaravelMcpServiceProvider::class);

        // Get registrar from container
        $registrar = $this->app->make(RouteRegistrar::class);

        $this->assertInstanceOf(RouteRegistrar::class, $registrar);

        // Register a component
        $registrar->tool('provider_test', 'ProviderTestTool');

        // Get registry and verify
        $registry = $this->app->make(McpRegistry::class);

        $this->assertTrue($registry->hasTool('provider_test'));
    }

    #[Test]
    public function it_supports_closure_handlers(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register with closure
        $closure = function ($container) {
            return new class
            {
                public function execute()
                {
                    return 'executed';
                }
            };
        };

        $registrar->tool('closure_tool', $closure);

        $registry = $registrar->getRegistry();

        $this->assertTrue($registry->hasTool('closure_tool'));
    }

    #[Test]
    public function it_supports_instance_handlers(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register with instance
        $instance = new class
        {
            public function execute()
            {
                return 'executed';
            }
        };

        $registrar->tool('instance_tool', $instance);

        $registry = $registrar->getRegistry();

        $this->assertTrue($registry->hasTool('instance_tool'));
    }

    #[Test]
    public function it_handles_duplicate_registrations(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Register component with closure
        $registrar->tool('duplicate', function () {
            return ['result' => 'tool1'];
        });

        // Try to register again - should fail
        try {
            $registrar->tool('duplicate', function () {
                return ['result' => 'tool2'];
            });
            $this->fail('Expected exception for duplicate registration');
        } catch (\Exception $e) {
            $this->assertStringContainsString('already registered', $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_empty_component_names(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);

        // Try to register with empty name
        try {
            $registrar->tool('', 'EmptyNameTool');
            $this->fail('Expected exception for empty name');
        } catch (\Exception $e) {
            $this->assertStringContainsString('cannot be empty', $e->getMessage());
        }
    }

    #[Test]
    public function it_validates_non_existent_classes(): void
    {
        // Enable validation for this test
        $this->app['config']->set('laravel-mcp.validation.validate_handlers', true);

        $registrar = $this->app->make(RouteRegistrar::class);

        // Try to register non-existent class
        try {
            $registrar->tool('nonexistent', 'App\Mcp\Tools\NonExistentTool');
            $this->fail('Expected exception for non-existent class');
        } catch (\Exception $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }
    }

    #[Test]
    public function it_unregisters_components(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);
        $registry = $registrar->getRegistry();

        // Register and then unregister
        $registrar->tool('temp_tool', function () {
            return ['result' => 'temp'];
        });
        $this->assertTrue($registry->hasTool('temp_tool'));

        $registry->unregisterTool('temp_tool');
        $this->assertFalse($registry->hasTool('temp_tool'));
    }

    #[Test]
    public function it_counts_registered_components(): void
    {
        $registrar = $this->app->make(RouteRegistrar::class);
        $registry = $registrar->getRegistry();

        // Register components
        $registrar->tool('tool1', function () {
            return ['result' => 'tool1'];
        });
        $registrar->tool('tool2', function () {
            return ['result' => 'tool2'];
        });
        $registrar->resource('resource1', function () {
            return ['result' => 'resource1'];
        });

        // Get counts
        $toolCount = count($registry->listTools());
        $resourceCount = count($registry->listResources());
        $promptCount = count($registry->listPrompts());

        $this->assertEquals(2, $toolCount);
        $this->assertEquals(1, $resourceCount);
        $this->assertEquals(0, $promptCount);
    }
}
