<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for RouteRegistrar functionality.
 *
 * Tests the route-style registration API that provides fluent method chaining
 * and group registration with shared attributes.
 */
class RouteRegistrarTest extends TestCase
{
    private RouteRegistrar $registrar;

    private MockObject|McpRegistry $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = $this->createMock(McpRegistry::class);
        $this->registrar = new RouteRegistrar($this->mockRegistry);
    }

    /**
     * Test successful tool registration with fluent API.
     */
    public function test_tool_registration_with_fluent_api(): void
    {
        $toolName = 'calculator';
        $tool = $this->createTestTool($toolName);
        $options = ['description' => 'Performs calculations'];

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->with('tool', $toolName, $tool, $options);

        $result = $this->registrar->tool($toolName, $tool, $options);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test successful resource registration with fluent API.
     */
    public function test_resource_registration_with_fluent_api(): void
    {
        $resourceName = 'user_profile';
        $resource = $this->createTestResource($resourceName);
        $options = ['uri' => 'user://profile/{id}'];

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->with('resource', $resourceName, $resource, $options);

        $result = $this->registrar->resource($resourceName, $resource, $options);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test successful prompt registration with fluent API.
     */
    public function test_prompt_registration_with_fluent_api(): void
    {
        $promptName = 'email_template';
        $prompt = $this->createTestPrompt($promptName);
        $options = ['description' => 'Email template generator'];

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->with('prompt', $promptName, $prompt, $options);

        $result = $this->registrar->prompt($promptName, $prompt, $options);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test method chaining functionality.
     */
    public function test_method_chaining(): void
    {
        $tool = $this->createTestTool('tool');
        $resource = $this->createTestResource('resource');
        $prompt = $this->createTestPrompt('prompt');

        $this->mockRegistry->expects($this->exactly(3))
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertContains($type, ['tool', 'resource', 'prompt']);
                $this->assertContains($name, ['test_tool', 'test_resource', 'test_prompt']);
            });

        $result = $this->registrar
            ->tool('test_tool', $tool)
            ->resource('test_resource', $resource)
            ->prompt('test_prompt', $prompt);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test group registration with shared attributes.
     */
    public function test_group_registration_with_shared_attributes(): void
    {
        $tool = $this->createTestTool('tool');
        $resource = $this->createTestResource('resource');

        $this->mockRegistry->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('v1', $options['version']);
                $this->assertEquals('internal', $options['scope']);
            });

        $this->registrar->group(['version' => 'v1', 'scope' => 'internal'], function ($registrar) use ($tool, $resource) {
            $registrar->tool('grouped_tool', $tool);
            $registrar->resource('grouped_resource', $resource);
        });
    }

    /**
     * Test nested group registration.
     */
    public function test_nested_group_registration(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('v1', $options['version']);
                $this->assertEquals('internal', $options['scope']);
                $this->assertEquals('test', $options['category']);
            });

        $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tool) {
            $registrar->group(['scope' => 'internal'], function ($registrar) use ($tool) {
                $registrar->tool('nested_tool', $tool, ['category' => 'test']);
            });
        });
    }

    /**
     * Test batch registration functionality.
     */
    public function test_batch_registration(): void
    {
        $tools = [
            'calculator' => $this->createTestTool('calculator'),
            'converter' => $this->createTestTool('converter'),
        ];
        $commonOptions = ['category' => 'math'];

        $this->mockRegistry->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('tool', $type);
                $this->assertEquals('math', $options['category']);
                $this->assertContains($name, ['calculator', 'converter']);
            });

        $result = $this->registrar->batch('tool', $tools, $commonOptions);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test batch registration with group attributes.
     */
    public function test_batch_registration_with_group_attributes(): void
    {
        $tools = [
            'tool1' => $this->createTestTool('tool1'),
            'tool2' => $this->createTestTool('tool2'),
        ];

        $this->mockRegistry->expects($this->exactly(2))
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('v1', $options['version']);
                $this->assertEquals('batch', $options['category']);
            });

        $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tools) {
            $registrar->batch('tool', $tools, ['category' => 'batch']);
        });
    }

    /**
     * Test prefix functionality.
     */
    public function test_prefix_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('api', $options['prefix']);
            });

        $this->registrar->prefix('api', function ($registrar) use ($tool) {
            $registrar->tool('endpoint', $tool);
        });
    }

    /**
     * Test nested prefix functionality.
     */
    public function test_nested_prefix_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('api.v1', $options['prefix']);
            });

        $this->registrar->prefix('api', function ($registrar) use ($tool) {
            $registrar->prefix('v1', function ($registrar) use ($tool) {
                $registrar->tool('endpoint', $tool);
            });
        });
    }

    /**
     * Test namespace functionality.
     */
    public function test_namespace_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('App\\Tools', $options['namespace']);
            });

        $this->registrar->namespace('App\\Tools', function ($registrar) use ($tool) {
            $registrar->tool('Calculator', $tool);
        });
    }

    /**
     * Test nested namespace functionality.
     */
    public function test_nested_namespace_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('App\\Tools\\Math', $options['namespace']);
            });

        $this->registrar->namespace('App\\Tools', function ($registrar) use ($tool) {
            $registrar->namespace('Math', function ($registrar) use ($tool) {
                $registrar->tool('Calculator', $tool);
            });
        });
    }

    /**
     * Test middleware functionality.
     */
    public function test_middleware_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals(['auth'], $options['middleware']);
            });

        $this->registrar->middleware('auth', function ($registrar) use ($tool) {
            $registrar->tool('secure_tool', $tool);
        });
    }

    /**
     * Test middleware with array functionality.
     */
    public function test_middleware_with_array_functionality(): void
    {
        $tool = $this->createTestTool('tool');
        $middleware = ['auth', 'throttle'];

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) use ($middleware) {
                $this->assertEquals($middleware, $options['middleware']);
            });

        $this->registrar->middleware($middleware, function ($registrar) use ($tool) {
            $registrar->tool('secure_tool', $tool);
        });
    }

    /**
     * Test nested middleware functionality.
     */
    public function test_nested_middleware_functionality(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals(['cors', 'auth'], $options['middleware']);
            });

        $this->registrar->middleware('cors', function ($registrar) use ($tool) {
            $registrar->middleware('auth', function ($registrar) use ($tool) {
                $registrar->tool('secure_tool', $tool);
            });
        });
    }

    /**
     * Test middleware merging with existing middleware.
     */
    public function test_middleware_merging_with_existing(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals(['cors', 'component'], $options['middleware']);
            });

        $this->registrar->middleware('cors', function ($registrar) use ($tool) {
            $registrar->tool('tool_with_middleware', $tool, ['middleware' => 'component']);
        });
    }

    /**
     * Test getting the underlying registry.
     */
    public function test_get_registry(): void
    {
        $registry = $this->registrar->getRegistry();

        $this->assertSame($this->mockRegistry, $registry);
    }

    /**
     * Test group attribute merging with component options.
     */
    public function test_group_attribute_merging_with_component_options(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('group_value', $options['group_attr']);
                $this->assertEquals('component_value', $options['component_attr']);
                $this->assertEquals('component_override', $options['override_attr']);
            });

        $this->registrar->group(['group_attr' => 'group_value', 'override_attr' => 'group_value'], function ($registrar) use ($tool) {
            $registrar->tool('test_tool', $tool, [
                'component_attr' => 'component_value',
                'override_attr' => 'component_override', // This should take precedence
            ]);
        });
    }

    /**
     * Test empty group registration.
     */
    public function test_empty_group_registration(): void
    {
        $this->mockRegistry->expects($this->never())
            ->method('register');

        $this->registrar->group(['version' => 'v1'], function ($registrar) {
            // No registrations in group
        });

        $this->assertTrue(true); // Test passes if no exceptions thrown
    }

    /**
     * Test exception handling in groups.
     */
    public function test_exception_handling_in_groups(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willThrowException(new \Exception('Registration failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Registration failed');

        $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tool) {
            $registrar->tool('failing_tool', $tool);
        });
    }

    /**
     * Test group stack cleanup after exceptions.
     */
    public function test_group_stack_cleanup_after_exceptions(): void
    {
        $tool = $this->createTestTool('tool');

        // First registration should fail
        $this->mockRegistry->expects($this->exactly(2))
            ->method('register')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('First registration failed')),
                $this->returnValue(null)
            );

        // First group should fail
        try {
            $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tool) {
                $registrar->tool('failing_tool', $tool);
            });
        } catch (\Exception $e) {
            // Expected exception
        }

        // Second group should work normally (group stack should be clean)
        $this->registrar->group(['version' => 'v2'], function ($registrar) use ($tool) {
            $registrar->tool('working_tool', $tool);
        });

        $this->assertTrue(true); // Test passes if no exceptions thrown in second group
    }

    /**
     * Test complex attribute merging scenario.
     */
    public function test_complex_attribute_merging(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('api.v1.users', $options['prefix']);
                $this->assertEquals('App\\Tools\\Api\\V1', $options['namespace']);
                $this->assertEquals(['cors', 'auth', 'throttle'], $options['middleware']);
                $this->assertEquals('v1', $options['version']);
                $this->assertEquals('component_category', $options['category']);
            });

        $this->registrar->group(['version' => 'v1'], function ($registrar) use ($tool) {
            $registrar->prefix('api', function ($registrar) use ($tool) {
                $registrar->namespace('App\\Tools\\Api', function ($registrar) use ($tool) {
                    $registrar->middleware(['cors', 'auth'], function ($registrar) use ($tool) {
                        $registrar->prefix('v1', function ($registrar) use ($tool) {
                            $registrar->namespace('V1', function ($registrar) use ($tool) {
                                $registrar->prefix('users', function ($registrar) use ($tool) {
                                    $registrar->tool('list', $tool, [
                                        'middleware' => 'throttle',
                                        'category' => 'component_category',
                                    ]);
                                });
                            });
                        });
                    });
                });
            });
        });
    }

    /**
     * Test batch registration with multiple component types.
     */
    public function test_batch_registration_with_multiple_types(): void
    {
        $tools = ['tool1' => $this->createTestTool('tool1'), 'tool2' => $this->createTestTool('tool2')];
        $resources = ['res1' => $this->createTestResource('res1')];

        $this->mockRegistry->expects($this->exactly(3))
            ->method('register')
            ->willReturnCallback(function ($type, $name, $handler, $options) {
                $this->assertEquals('shared', $options['common']);
            });

        $result = $this->registrar
            ->batch('tool', $tools, ['common' => 'shared'])
            ->batch('resource', $resources, ['common' => 'shared']);

        $this->assertSame($this->registrar, $result);
    }

    /**
     * Test registration with empty options.
     */
    public function test_registration_with_empty_options(): void
    {
        $tool = $this->createTestTool('tool');

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->with('tool', 'simple_tool', $tool, []);

        $this->registrar->tool('simple_tool', $tool);
    }

    /**
     * Test registration with null values in options.
     */
    public function test_registration_with_null_values(): void
    {
        $tool = $this->createTestTool('tool');
        $options = ['description' => 'Tool description', 'version' => null];

        $this->mockRegistry->expects($this->once())
            ->method('register')
            ->with('tool', 'null_version_tool', $tool, $options);

        $this->registrar->tool('null_version_tool', $tool, $options);
    }
}
