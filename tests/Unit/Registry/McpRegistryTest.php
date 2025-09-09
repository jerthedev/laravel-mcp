<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Test suite for McpRegistry core functionality.
 *
 * Tests the central registry that coordinates all MCP component types,
 * ensuring proper registration, validation, and retrieval of tools,
 * resources, and prompts.
 */
class McpRegistryTest extends TestCase
{
    private McpRegistry $registry;

    private MockObject|ToolRegistry $mockToolRegistry;

    private MockObject|ResourceRegistry $mockResourceRegistry;

    private MockObject|PromptRegistry $mockPromptRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for type-specific registries
        $this->mockToolRegistry = $this->createMock(ToolRegistry::class);
        $this->mockResourceRegistry = $this->createMock(ResourceRegistry::class);
        $this->mockPromptRegistry = $this->createMock(PromptRegistry::class);

        $this->registry = new McpRegistry(
            $this->mockToolRegistry,
            $this->mockResourceRegistry,
            $this->mockPromptRegistry
        );
    }

    /**
     * Test successful registration of different component types.
     */
    public function test_register_components_successfully(): void
    {
        $toolName = 'test_tool';
        $toolHandler = $this->createTestTool($toolName);
        $toolOptions = ['description' => 'Test tool description'];

        $resourceName = 'test_resource';
        $resourceHandler = $this->createTestResource($resourceName);
        $resourceOptions = ['uri' => 'test://resource'];

        $promptName = 'test_prompt';
        $promptHandler = $this->createTestPrompt($promptName);
        $promptOptions = ['description' => 'Test prompt description'];

        // Set up mock expectations for successful registrations
        $this->mockToolRegistry->expects($this->once())
            ->method('register')
            ->with($toolName, $toolHandler, $toolOptions);

        $this->mockResourceRegistry->expects($this->once())
            ->method('register')
            ->with($resourceName, $resourceHandler, $resourceOptions);

        $this->mockPromptRegistry->expects($this->once())
            ->method('register')
            ->with($promptName, $promptHandler, $promptOptions);

        // Set up has() method responses for validation
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(false);
        $this->mockResourceRegistry->method('has')->with($resourceName)->willReturn(false);
        $this->mockPromptRegistry->method('has')->with($promptName)->willReturn(false);

        // Test registrations
        $this->registry->register('tool', $toolName, $toolHandler, $toolOptions);
        $this->registry->register('resource', $resourceName, $resourceHandler, $resourceOptions);
        $this->registry->register('prompt', $promptName, $promptHandler, $promptOptions);

        // Verify that components were tracked in main registry
        $this->assertTrue($this->registry->has('tool', $toolName));
        $this->assertTrue($this->registry->has('resource', $resourceName));
        $this->assertTrue($this->registry->has('prompt', $promptName));
    }

    /**
     * Test registration validation for empty names.
     */
    public function test_register_with_empty_name_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Component name cannot be empty');

        $this->registry->register('tool', '', $this->createTestTool('test'));
    }

    /**
     * Test registration validation for duplicate names.
     */
    public function test_register_duplicate_name_throws_exception(): void
    {
        $toolName = 'duplicate_tool';
        $handler = $this->createTestTool($toolName);

        // Mock that the tool already exists
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(true);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Component '{$toolName}' of type 'tool' is already registered");

        $this->registry->register('tool', $toolName, $handler);
    }

    /**
     * Test registration validation for invalid component types.
     */
    public function test_register_invalid_type_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Unknown component type: invalid_type');

        $this->registry->register('invalid_type', 'test', $this->createTestTool('test'));
    }

    /**
     * Test registration validation for non-existent handler classes.
     */
    public function test_register_non_existent_class_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Handler class 'NonExistentClass' does not exist");

        $this->registry->register('tool', 'test', 'NonExistentClass');
    }

    /**
     * Test registration validation for invalid handler types.
     */
    public function test_register_invalid_handler_type_throws_exception(): void
    {
        // Create a class that doesn't extend McpTool
        $invalidHandler = new class
        {
            public function execute(array $args): array
            {
                return [];
            }
        };

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Handler must extend '.McpTool::class);

        $this->registry->register('tool', 'test', get_class($invalidHandler));
    }

    /**
     * Test successful component unregistration.
     */
    public function test_unregister_component_successfully(): void
    {
        $toolName = 'test_tool';

        // Mock successful unregistration
        $this->mockToolRegistry->expects($this->once())
            ->method('unregister')
            ->with($toolName)
            ->willReturn(true);

        $result = $this->registry->unregister('tool', $toolName);

        $this->assertTrue($result);
    }

    /**
     * Test unregistration of non-existent component.
     */
    public function test_unregister_non_existent_component(): void
    {
        $toolName = 'nonexistent_tool';

        // Mock failed unregistration
        $this->mockToolRegistry->expects($this->once())
            ->method('unregister')
            ->with($toolName)
            ->willReturn(false);

        $result = $this->registry->unregister('tool', $toolName);

        $this->assertFalse($result);
    }

    /**
     * Test unregistration with invalid type.
     */
    public function test_unregister_invalid_type(): void
    {
        $result = $this->registry->unregister('invalid_type', 'test');

        $this->assertFalse($result);
    }

    /**
     * Test component counting functionality.
     */
    public function test_count_components(): void
    {
        // Mock individual registry counts
        $this->mockToolRegistry->method('count')->willReturn(3);
        $this->mockResourceRegistry->method('count')->willReturn(2);
        $this->mockPromptRegistry->method('count')->willReturn(1);

        // Test individual type counts
        $this->assertEquals(3, $this->registry->count('tool'));
        $this->assertEquals(2, $this->registry->count('resource'));
        $this->assertEquals(1, $this->registry->count('prompt'));

        // Test total count (when type is null)
        $this->assertEquals(6, $this->registry->count());
    }

    /**
     * Test count with invalid type returns zero.
     */
    public function test_count_invalid_type_returns_zero(): void
    {
        $this->assertEquals(0, $this->registry->count('invalid_type'));
    }

    /**
     * Test getting supported component types.
     */
    public function test_get_types(): void
    {
        $types = $this->registry->getTypes();

        $this->assertEquals(['tool', 'resource', 'prompt'], $types);
    }

    /**
     * Test checking if components exist.
     */
    public function test_has_component(): void
    {
        $toolName = 'test_tool';

        // Mock has() method responses
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(true);

        $this->assertTrue($this->registry->has('tool', $toolName));
    }

    /**
     * Test has() with invalid type returns false.
     */
    public function test_has_with_invalid_type_returns_false(): void
    {
        $this->assertFalse($this->registry->has('invalid_type', 'test'));
    }

    /**
     * Test getting components.
     */
    public function test_get_component(): void
    {
        $toolName = 'test_tool';
        $expectedTool = $this->createTestTool($toolName);

        // Mock get() method response
        $this->mockToolRegistry->method('get')->with($toolName)->willReturn($expectedTool);

        $result = $this->registry->get('tool', $toolName);

        $this->assertSame($expectedTool, $result);
    }

    /**
     * Test get() with invalid type returns null.
     */
    public function test_get_with_invalid_type_returns_null(): void
    {
        $result = $this->registry->get('invalid_type', 'test');

        $this->assertNull($result);
    }

    /**
     * Test getting all components of a type.
     */
    public function test_get_all_components(): void
    {
        $expectedTools = [
            'tool1' => $this->createTestTool('tool1'),
            'tool2' => $this->createTestTool('tool2'),
        ];

        // Mock getAll() method response
        $this->mockToolRegistry->method('getAll')->willReturn($expectedTools);

        $result = $this->registry->getAll('tool');

        $this->assertEquals($expectedTools, $result);
    }

    /**
     * Test getAll() with invalid type returns empty array.
     */
    public function test_get_all_with_invalid_type_returns_empty(): void
    {
        $result = $this->registry->getAll('invalid_type');

        $this->assertEquals([], $result);
    }

    /**
     * Test clearing all registrations.
     */
    public function test_clear_all_registrations(): void
    {
        // Set up mock expectations for clear() calls
        $this->mockToolRegistry->expects($this->once())->method('clear');
        $this->mockResourceRegistry->expects($this->once())->method('clear');
        $this->mockPromptRegistry->expects($this->once())->method('clear');

        $this->registry->clear();
    }

    /**
     * Test getting type-specific registries.
     */
    public function test_get_type_registry(): void
    {
        $toolRegistry = $this->registry->getTypeRegistry('tool');
        $resourceRegistry = $this->registry->getTypeRegistry('resource');
        $promptRegistry = $this->registry->getTypeRegistry('prompt');

        $this->assertSame($this->mockToolRegistry, $toolRegistry);
        $this->assertSame($this->mockResourceRegistry, $resourceRegistry);
        $this->assertSame($this->mockPromptRegistry, $promptRegistry);

        // Test invalid type returns null
        $this->assertNull($this->registry->getTypeRegistry('invalid_type'));
    }

    /**
     * Test getting all type registries.
     */
    public function test_get_type_registries(): void
    {
        $registries = $this->registry->getTypeRegistries();

        $expectedKeys = ['tool', 'resource', 'prompt'];
        $this->assertEquals($expectedKeys, array_keys($registries));
        $this->assertSame($this->mockToolRegistry, $registries['tool']);
        $this->assertSame($this->mockResourceRegistry, $registries['resource']);
        $this->assertSame($this->mockPromptRegistry, $registries['prompt']);
    }

    /**
     * Test initialization of registry.
     */
    public function test_initialize(): void
    {
        // Set up mock expectations for initialize() calls
        $this->mockToolRegistry->expects($this->once())->method('initialize');
        $this->mockResourceRegistry->expects($this->once())->method('initialize');
        $this->mockPromptRegistry->expects($this->once())->method('initialize');

        $this->registry->initialize();
    }

    /**
     * Test initialization only runs once.
     */
    public function test_initialize_only_runs_once(): void
    {
        // Set up mock expectations - should only be called once
        $this->mockToolRegistry->expects($this->once())->method('initialize');
        $this->mockResourceRegistry->expects($this->once())->method('initialize');
        $this->mockPromptRegistry->expects($this->once())->method('initialize');

        $this->registry->initialize();
        $this->registry->initialize(); // Second call should not trigger initialize()
    }

    /**
     * Test backward compatibility methods.
     */
    public function test_backward_compatibility_methods(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);

        // Set up mocks for backward compatibility methods
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(false);
        $this->mockToolRegistry->expects($this->once())
            ->method('register')
            ->with($toolName, $tool, []);

        // Test registerTool
        $this->registry->registerTool($toolName, $tool);

        // Test hasTool
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(true);
        $this->assertTrue($this->registry->hasTool($toolName));

        // Test getTool
        $this->mockToolRegistry->method('get')->with($toolName)->willReturn($tool);
        $this->assertSame($tool, $this->registry->getTool($toolName));

        // Test unregisterTool
        $this->mockToolRegistry->expects($this->once())
            ->method('unregister')
            ->with($toolName)
            ->willReturn(true);
        $this->assertTrue($this->registry->unregisterTool($toolName));
    }

    /**
     * Test getMetadata functionality.
     */
    public function test_get_metadata(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);
        $options = ['description' => 'Test tool', 'version' => '1.0'];

        // Register component first
        $this->mockToolRegistry->method('has')->with($toolName)->willReturn(false);
        $this->registry->register('tool', $toolName, $tool, $options);

        $metadata = $this->registry->getMetadata('tool', $toolName);

        // Check that options are included in metadata
        $this->assertEquals('Test tool', $metadata['description']);
        $this->assertEquals('1.0', $metadata['version']);
        $this->assertArrayHasKey('registered_at', $metadata);
    }

    /**
     * Test getting server capabilities.
     */
    public function test_get_capabilities(): void
    {
        $capabilities = $this->registry->getCapabilities();

        $expectedCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
            'logging' => [],
        ];

        $this->assertEquals($expectedCapabilities, $capabilities);
    }

    /**
     * Test all() method for backward compatibility.
     */
    public function test_all_method_backward_compatibility(): void
    {
        $tools = ['tool1' => $this->createTestTool('tool1')];
        $resources = ['resource1' => $this->createTestResource('resource1')];
        $prompts = ['prompt1' => $this->createTestPrompt('prompt1')];

        $this->mockToolRegistry->method('getAll')->willReturn($tools);
        $this->mockResourceRegistry->method('getAll')->willReturn($resources);
        $this->mockPromptRegistry->method('getAll')->willReturn($prompts);

        $all = $this->registry->all();

        $expected = array_merge($tools, $resources, $prompts);
        $this->assertEquals($expected, $all);
    }

    /**
     * Test component validation with class name strings.
     */
    public function test_validate_handler_with_class_names(): void
    {
        // Create actual test classes for validation
        $validToolClass = get_class($this->createTestTool('test'));

        // This should not throw an exception
        $this->mockToolRegistry->method('has')->willReturn(false);
        $this->registry->register('tool', 'test_tool', $validToolClass);

        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * Test that registration tracks registration time.
     */
    public function test_registration_tracks_timestamp(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);

        $this->mockToolRegistry->method('has')->willReturn(false);

        $startTime = time();
        $this->registry->register('tool', $toolName, $tool);
        $endTime = time();

        $metadata = $this->registry->getMetadata('tool', $toolName);

        $this->assertArrayHasKey('registered_at', $metadata);
        $this->assertGreaterThanOrEqual($startTime, $metadata['registered_at']);
        $this->assertLessThanOrEqual($endTime, $metadata['registered_at']);
    }
}
