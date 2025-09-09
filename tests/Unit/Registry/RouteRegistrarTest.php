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

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('registry')]
#[Group('route-registrar')]
#[Group('ticket-016')]
class RouteRegistrarTest extends TestCase
{
    private RouteRegistrar $registrar;

    private McpRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(McpRegistry::class);
        $this->registrar = new RouteRegistrar($this->registry);
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(RouteRegistrar::class, $this->registrar);
    }

    #[Test]
    public function it_registers_a_tool(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('tool', 'calculator', 'App\Mcp\Tools\CalculatorTool', ['middleware' => 'auth']);

        $result = $this->registrar->tool('calculator', 'App\Mcp\Tools\CalculatorTool', ['middleware' => 'auth']);

        $this->assertSame($this->registrar, $result);
    }

    #[Test]
    public function it_registers_a_resource(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('resource', 'users', 'App\Mcp\Resources\UserResource', ['cache' => true]);

        $result = $this->registrar->resource('users', 'App\Mcp\Resources\UserResource', ['cache' => true]);

        $this->assertSame($this->registrar, $result);
    }

    #[Test]
    public function it_registers_a_prompt(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('prompt', 'email_template', 'App\Mcp\Prompts\EmailPrompt', []);

        $result = $this->registrar->prompt('email_template', 'App\Mcp\Prompts\EmailPrompt');

        $this->assertSame($this->registrar, $result);
    }

    #[Test]
    public function it_supports_group_registration(): void
    {
        $matcher = $this->exactly(2);
        $this->registry->expects($matcher)
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $class, $meta) use ($matcher) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals('tool', $type);
                    $this->assertEquals('admin_tool', $name);
                    $this->assertEquals('AdminTool', $class);
                    $this->assertEquals(['middleware' => ['auth', 'admin']], $meta);
                } elseif ($matcher->numberOfInvocations() === 2) {
                    $this->assertEquals('resource', $type);
                    $this->assertEquals('admin_logs', $name);
                    $this->assertEquals('AdminLogResource', $class);
                    $this->assertEquals(['middleware' => ['auth', 'admin']], $meta);
                }
            });

        $this->registrar->group(['middleware' => ['auth', 'admin']], function ($registrar) {
            $registrar->tool('admin_tool', 'AdminTool');
            $registrar->resource('admin_logs', 'AdminLogResource');
        });
    }

    #[Test]
    public function it_supports_nested_groups(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('tool', 'super_admin_tool', 'SuperAdminTool', [
                'middleware' => ['auth', 'admin', 'super'],
                'role' => 'super_admin',
            ]);

        $this->registrar->group(['middleware' => ['auth', 'admin']], function ($registrar) {
            $registrar->group(['middleware' => ['super'], 'role' => 'super_admin'], function ($registrar) {
                $registrar->tool('super_admin_tool', 'SuperAdminTool');
            });
        });
    }

    #[Test]
    public function it_supports_namespace_groups(): void
    {
        $matcher = $this->exactly(2);
        $this->registry->expects($matcher)
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $class, $meta) use ($matcher) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals('tool', $type);
                    $this->assertEquals('system_info', $name);
                    $this->assertEquals('App\Mcp\Admin\SystemInfoTool', $class);
                    $this->assertEquals([], $meta);
                } elseif ($matcher->numberOfInvocations() === 2) {
                    $this->assertEquals('resource', $type);
                    $this->assertEquals('system_stats', $name);
                    $this->assertEquals('App\Mcp\Admin\SystemStatsResource', $class);
                    $this->assertEquals([], $meta);
                }
            });

        $this->registrar->namespace('App\Mcp\Admin', function ($registrar) {
            $registrar->tool('system_info', 'SystemInfoTool');
            $registrar->resource('system_stats', 'SystemStatsResource');
        });
    }

    #[Test]
    public function it_supports_middleware_groups(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('tool', 'protected_tool', 'ProtectedTool', ['middleware' => ['auth', 'verified']]);

        $this->registrar->middleware(['auth', 'verified'], function ($registrar) {
            $registrar->tool('protected_tool', 'ProtectedTool');
        });
    }

    #[Test]
    public function it_supports_prefix_groups(): void
    {
        $matcher = $this->exactly(2);
        $this->registry->expects($matcher)
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $class, $meta) use ($matcher) {
                if ($matcher->numberOfInvocations() === 1) {
                    $this->assertEquals('tool', $type);
                    $this->assertEquals('admin_tool1', $name);
                    $this->assertEquals('Tool1', $class);
                    $this->assertEquals(['prefix' => 'admin_', 'name' => 'admin_tool1'], $meta);
                } elseif ($matcher->numberOfInvocations() === 2) {
                    $this->assertEquals('tool', $type);
                    $this->assertEquals('admin_tool2', $name);
                    $this->assertEquals('Tool2', $class);
                    $this->assertEquals(['prefix' => 'admin_', 'name' => 'admin_tool2'], $meta);
                }
            });

        $this->registrar->prefix('admin_', function ($registrar) {
            $registrar->tool('tool1', 'Tool1');
            $registrar->tool('tool2', 'Tool2');
        });
    }

    #[Test]
    public function it_merges_middleware_in_groups(): void
    {
        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->with('tool', 'test_tool', 'TestTool', ['middleware' => ['auth', 'admin', 'custom']]);

        $this->registrar->group(['middleware' => ['auth', 'admin']], function ($registrar) {
            $registrar->tool('test_tool', 'TestTool', ['middleware' => ['custom']]);
        });
    }

    #[Test]
    public function it_checks_if_component_exists(): void
    {
        $this->registry->expects($this->once())
            ->method('hasTool')
            ->with('test_tool')
            ->willReturn(true);

        $result = $this->registrar->has('tool', 'test_tool');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_gets_a_registered_tool(): void
    {
        $mockTool = new \stdClass;

        $this->registry->expects($this->once())
            ->method('getTool')
            ->with('test_tool')
            ->willReturn($mockTool);

        $result = $this->registrar->get('tool', 'test_tool');

        $this->assertSame($mockTool, $result);
    }

    #[Test]
    public function it_gets_a_registered_resource(): void
    {
        $mockResource = new \stdClass;

        $this->registry->expects($this->once())
            ->method('getResource')
            ->with('test_resource')
            ->willReturn($mockResource);

        $result = $this->registrar->get('resource', 'test_resource');

        $this->assertSame($mockResource, $result);
    }

    #[Test]
    public function it_gets_a_registered_prompt(): void
    {
        $mockPrompt = new \stdClass;

        $this->registry->expects($this->once())
            ->method('getPrompt')
            ->with('test_prompt')
            ->willReturn($mockPrompt);

        $result = $this->registrar->get('prompt', 'test_prompt');

        $this->assertSame($mockPrompt, $result);
    }

    #[Test]
    public function it_lists_all_tools(): void
    {
        $tools = ['tool1', 'tool2'];

        $this->registry->expects($this->once())
            ->method('listTools')
            ->willReturn($tools);

        $result = $this->registrar->list('tool');

        $this->assertEquals($tools, $result);
    }

    #[Test]
    public function it_lists_all_resources(): void
    {
        $resources = ['resource1', 'resource2'];

        $this->registry->expects($this->once())
            ->method('listResources')
            ->willReturn($resources);

        $result = $this->registrar->list('resource');

        $this->assertEquals($resources, $result);
    }

    #[Test]
    public function it_lists_all_prompts(): void
    {
        $prompts = ['prompt1', 'prompt2'];

        $this->registry->expects($this->once())
            ->method('listPrompts')
            ->willReturn($prompts);

        $result = $this->registrar->list('prompt');

        $this->assertEquals($prompts, $result);
    }

    #[Test]
    public function it_returns_empty_array_for_invalid_type(): void
    {
        $result = $this->registrar->list('invalid');

        $this->assertEquals([], $result);
    }

    #[Test]
    public function it_returns_null_for_invalid_type_get(): void
    {
        $result = $this->registrar->get('invalid', 'test');

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_false_for_invalid_type_has(): void
    {
        $result = $this->registrar->has('invalid', 'test');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_gets_the_underlying_registry(): void
    {
        $result = $this->registrar->getRegistry();

        $this->assertSame($this->registry, $result);
    }

    #[Test]
    public function it_loads_routes_from_file(): void
    {
        $routeFile = sys_get_temp_dir().'/test_routes.php';

        file_put_contents($routeFile, '<?php // Test routes file');

        // Should not throw exception
        $this->registrar->loadRoutesFrom($routeFile);

        unlink($routeFile);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_non_existent_route_file(): void
    {
        // Should not throw exception for non-existent file
        $this->registrar->loadRoutesFrom('/non/existent/file.php');

        $this->assertTrue(true);
    }
}
