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
        $mockTool = $this->createMock(\JTD\LaravelMCP\Abstracts\McpTool::class);

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
        $mockResource = $this->createMock(\JTD\LaravelMCP\Abstracts\McpResource::class);

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
        $mockPrompt = $this->createMock(\JTD\LaravelMCP\Abstracts\McpPrompt::class);

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

    /**
     * Test route-related attribute merging.
     */
    public function test_route_attribute_merging(): void
    {
        $tool = $this->createTestTool('tool');

        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('api.v1', $options['prefix']);
                $this->assertEquals('App\\Tools\\Api\\V1', $options['namespace']);
                $this->assertEquals(['cors', 'auth', 'throttle'], $options['middleware']);
                $this->assertEquals(['route' => 'custom'], $options['route_options']);
            });

        $this->registrar->group([
            'prefix' => 'api',
            'namespace' => 'App\\Tools\\Api',
            'middleware' => ['cors', 'auth'],
        ], function ($registrar) use ($tool) {
            $registrar->prefix('v1', function ($registrar) use ($tool) {
                $registrar->namespace('V1', function ($registrar) use ($tool) {
                    $registrar->tool('endpoint', $tool, [
                        'middleware' => 'throttle',
                        'route_options' => ['route' => 'custom'],
                    ]);
                });
            });
        });
    }

    /**
     * Test registration with route-specific options.
     */
    public function test_registration_with_route_options(): void
    {
        $tool = $this->createTestTool('tool');
        $routeOptions = [
            'methods' => ['GET', 'POST'],
            'constraints' => ['id' => '[0-9]+'],
            'domain' => 'api.example.com',
            'secure' => true,
        ];

        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) use ($routeOptions) {
                $this->assertEquals($routeOptions, $options['route_options']);
            });

        $this->registrar->tool('api_tool', $tool, ['route_options' => $routeOptions]);
    }

    /**
     * Test error handling during registration.
     */
    public function test_error_handling_during_registration(): void
    {
        $tool = $this->createTestTool('tool');

        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->willThrowException(new \InvalidArgumentException('Invalid tool configuration'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid tool configuration');

        $this->registrar->tool('invalid_tool', $tool);
    }

    /**
     * Test fluent API with different component types in chain.
     */
    public function test_fluent_api_with_mixed_component_types(): void
    {
        $tool = $this->createTestTool('tool');
        $resource = $this->createTestResource('resource');
        $prompt = $this->createTestPrompt('prompt');

        $this->registry->expects($this->exactly(6))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertContains($type, ['tool', 'resource', 'prompt']);
                $this->assertIsString($name);
                $this->assertNotNull($handler);
                $this->assertIsArray($options);
            });

        $result = $this->registrar
            ->tool('calculator', $tool)
            ->resource('user_data', $resource, ['cache' => true])
            ->prompt('email_template', $prompt, ['version' => 'v1'])
            ->batch('tool', [
                'validator' => $tool,
                'transformer' => $tool,
            ])
            ->resource('file_system', $resource);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test registration with callable handlers.
     */
    public function test_registration_with_callable_handlers(): void
    {
        $toolCallback = function ($params) {
            return ['result' => 'Tool executed'];
        };

        $resourceCallback = function ($options) {
            return ['data' => 'Resource data'];
        };

        $promptCallback = function ($args) {
            return ['messages' => ['Prompt rendered']];
        };

        $this->registry->expects($this->exactly(3))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertIsCallable($handler);
            });

        $this->registrar
            ->tool('callback_tool', $toolCallback)
            ->resource('callback_resource', $resourceCallback)
            ->prompt('callback_prompt', $promptCallback);
    }

    /**
     * Test registration with string class names.
     */
    public function test_registration_with_string_class_names(): void
    {
        $toolClassName = 'App\\Tools\\Calculator';
        $resourceClassName = 'App\\Resources\\UserData';
        $promptClassName = 'App\\Prompts\\EmailTemplate';

        $this->registry->expects($this->exactly(3))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertIsString($handler);
                $this->assertStringStartsWith('App\\', $handler);
            });

        $this->registrar
            ->tool('string_tool', $toolClassName)
            ->resource('string_resource', $resourceClassName)
            ->prompt('string_prompt', $promptClassName);
    }

    /**
     * Test batch registration with empty arrays.
     */
    public function test_batch_registration_with_empty_arrays(): void
    {
        $this->registry->expects($this->never())
            ->method('registerWithType');

        $result = $this->registrar->batch('tool', [], ['common' => 'value']);
        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test complex nested group scenarios.
     */
    public function test_complex_nested_group_scenarios(): void
    {
        $tool = $this->createTestTool('tool');

        $this->registry->expects($this->exactly(2))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                if ($name === 'nested_tool1') {
                    $this->assertEquals('api.v1.admin', $options['prefix']);
                    $this->assertEquals('App\\Api\\V1\\Admin', $options['namespace']);
                    $this->assertEquals(['cors', 'auth', 'admin'], $options['middleware']);
                } elseif ($name === 'nested_tool2') {
                    $this->assertEquals('api.v1.user', $options['prefix']);
                    $this->assertEquals('App\\Api\\V1\\User', $options['namespace']);
                    $this->assertEquals(['cors', 'auth', 'user'], $options['middleware']);
                }
            });

        $this->registrar->group(['prefix' => 'api', 'namespace' => 'App\\Api', 'middleware' => ['cors', 'auth']], function ($registrar) use ($tool) {
            $registrar->group(['prefix' => 'v1', 'namespace' => 'V1'], function ($registrar) use ($tool) {
                $registrar->group(['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => 'admin'], function ($registrar) use ($tool) {
                    $registrar->tool('nested_tool1', $tool);
                });
                $registrar->group(['prefix' => 'user', 'namespace' => 'User', 'middleware' => 'user'], function ($registrar) use ($tool) {
                    $registrar->tool('nested_tool2', $tool);
                });
            });
        });
    }

    /**
     * Test middleware array handling in groups.
     */
    public function test_middleware_array_handling_in_groups(): void
    {
        $tool = $this->createTestTool('tool');

        $this->registry->expects($this->once())
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals(['cors', 'auth', 'throttle', 'component1', 'component2'], $options['middleware']);
            });

        $this->registrar->middleware(['cors', 'auth'], function ($registrar) use ($tool) {
            $registrar->middleware('throttle', function ($registrar) use ($tool) {
                $registrar->tool('multi_middleware_tool', $tool, ['middleware' => ['component1', 'component2']]);
            });
        });
    }

    /**
     * Test registration with duplicate names.
     */
    public function test_registration_with_duplicate_names(): void
    {
        $tool1 = $this->createTestTool('tool1');
        $tool2 = $this->createTestTool('tool2');

        $this->registry->expects($this->exactly(2))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('duplicate_tool', $name);
            });

        // Both registrations should be passed to the registry
        // The registry itself should handle duplicate detection
        $this->registrar
            ->tool('duplicate_tool', $tool1)
            ->tool('duplicate_tool', $tool2);
    }

    /**
     * Test group attributes don't leak between separate group calls.
     */
    public function test_group_attributes_isolation(): void
    {
        $tool = $this->createTestTool('tool');

        $this->registry->expects($this->exactly(2))
            ->method('registerWithType')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                if ($name === 'group1_tool') {
                    $this->assertEquals('v1', $options['version']);
                    $this->assertArrayNotHasKey('scope', $options);
                } elseif ($name === 'group2_tool') {
                    $this->assertEquals('internal', $options['scope']);
                    $this->assertArrayNotHasKey('version', $options);
                }
            });

        // First group
        $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tool) {
            $registrar->tool('group1_tool', $tool);
        });

        // Second group - should not have attributes from first group
        $this->registrar->group(['scope' => 'internal'], function ($registrar) use ($tool) {
            $registrar->tool('group2_tool', $tool);
        });
    }

    /**
     * Test registration count tracking.
     */
    public function test_registration_count_tracking(): void
    {
        $tool = $this->createTestTool('tool');
        $resource = $this->createTestResource('resource');
        $prompt = $this->createTestPrompt('prompt');

        $registrationCount = 0;
        $this->registry->expects($this->exactly(7))
            ->method('registerWithType')
            ->willReturnCallback(function () use (&$registrationCount) {
                $registrationCount++;
            });

        $this->registrar
            ->tool('tool1', $tool)
            ->resource('resource1', $resource)
            ->prompt('prompt1', $prompt)
            ->batch('tool', [
                'tool2' => $tool,
                'tool3' => $tool,
                'tool4' => $tool,
            ])
            ->tool('tool5', $tool);

        $this->assertEquals(7, $registrationCount);
    }
}
