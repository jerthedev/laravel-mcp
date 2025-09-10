<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Test suite for ToolRegistry functionality.
 *
 * Tests the tool-specific registry that manages registration,
 * validation, and retrieval of MCP tools.
 */
class ToolRegistryTest extends TestCase
{
    private ToolRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ToolRegistry;
    }

    /**
     * Test successful tool registration.
     */
    public function test_register_tool_successfully(): void
    {
        $toolName = 'calculator';
        $tool = $this->createTestTool($toolName, [
            'description' => 'Performs calculations',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => ['type' => 'string'],
                    'numbers' => ['type' => 'array'],
                ],
                'required' => ['operation', 'numbers'],
            ],
        ]);
        $metadata = [
            'description' => 'Performs calculations',
            'category' => 'math',
        ];

        $this->registry->register($toolName, $tool, $metadata);

        $this->assertTrue($this->registry->has($toolName));
        $this->assertSame($tool, $this->registry->get($toolName));
    }

    /**
     * Test registration with duplicate tool name throws exception.
     */
    public function test_register_duplicate_tool_throws_exception(): void
    {
        $toolName = 'duplicate_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Tool '{$toolName}' is already registered");

        $this->registry->register($toolName, $tool);
    }

    /**
     * Test getting non-existent tool throws exception.
     */
    public function test_get_non_existent_tool_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Tool 'nonexistent' is not registered");

        $this->registry->get('nonexistent');
    }

    /**
     * Test successful tool unregistration.
     */
    public function test_unregister_tool_successfully(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool);
        $this->assertTrue($this->registry->has($toolName));

        $result = $this->registry->unregister($toolName);

        $this->assertTrue($result);
        $this->assertFalse($this->registry->has($toolName));
    }

    /**
     * Test unregistering non-existent tool returns false.
     */
    public function test_unregister_non_existent_tool(): void
    {
        $result = $this->registry->unregister('nonexistent');

        $this->assertFalse($result);
    }

    /**
     * Test checking if tool exists.
     */
    public function test_has_tool(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);

        $this->assertFalse($this->registry->has($toolName));

        $this->registry->register($toolName, $tool);

        $this->assertTrue($this->registry->has($toolName));
    }

    /**
     * Test getting all registered tools.
     */
    public function test_get_all_tools(): void
    {
        $tool1 = $this->createTestTool('tool1');
        $tool2 = $this->createTestTool('tool2');

        $this->registry->register('tool1', $tool1);
        $this->registry->register('tool2', $tool2);

        $all = $this->registry->getAll();

        $this->assertCount(2, $all);
        $this->assertSame($tool1, $all['tool1']);
        $this->assertSame($tool2, $all['tool2']);

        // Test alias method
        $this->assertEquals($all, $this->registry->all());
    }

    /**
     * Test getting tool names.
     */
    public function test_get_tool_names(): void
    {
        $this->registry->register('tool1', $this->createTestTool('tool1'));
        $this->registry->register('tool2', $this->createTestTool('tool2'));

        $names = $this->registry->names();

        $this->assertEquals(['tool1', 'tool2'], $names);
    }

    /**
     * Test counting registered tools.
     */
    public function test_count_tools(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register('tool1', $this->createTestTool('tool1'));
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register('tool2', $this->createTestTool('tool2'));
        $this->assertEquals(2, $this->registry->count());

        $this->registry->unregister('tool1');
        $this->assertEquals(1, $this->registry->count());
    }

    /**
     * Test clearing all tools.
     */
    public function test_clear_all_tools(): void
    {
        $this->registry->register('tool1', $this->createTestTool('tool1'));
        $this->registry->register('tool2', $this->createTestTool('tool2'));

        $this->assertEquals(2, $this->registry->count());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count());
        $this->assertFalse($this->registry->has('tool1'));
        $this->assertFalse($this->registry->has('tool2'));
    }

    /**
     * Test getting tool metadata.
     */
    public function test_get_tool_metadata(): void
    {
        $toolName = 'test_tool';
        $tool = $this->createTestTool($toolName);
        $metadata = [
            'description' => 'Test tool description',
            'category' => 'testing',
            'version' => '1.0.0',
        ];

        $this->registry->register($toolName, $tool, $metadata);

        $retrievedMetadata = $this->registry->getMetadata($toolName);

        $this->assertEquals($toolName, $retrievedMetadata['name']);
        $this->assertEquals('tool', $retrievedMetadata['type']);
        $this->assertEquals('Test tool description', $retrievedMetadata['description']);
        $this->assertEquals('testing', $retrievedMetadata['category']);
        $this->assertEquals('1.0.0', $retrievedMetadata['version']);
        $this->assertNotEmpty($retrievedMetadata['registered_at']);
    }

    /**
     * Test getting metadata for non-existent tool throws exception.
     */
    public function test_get_metadata_for_non_existent_tool_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Tool 'nonexistent' is not registered");

        $this->registry->getMetadata('nonexistent');
    }

    /**
     * Test tool filtering by metadata criteria.
     */
    public function test_filter_tools_by_metadata(): void
    {
        $tool1 = $this->createTestTool('tool1');
        $tool2 = $this->createTestTool('tool2');
        $tool3 = $this->createTestTool('tool3');

        $this->registry->register('tool1', $tool1, ['category' => 'math']);
        $this->registry->register('tool2', $tool2, ['category' => 'string']);
        $this->registry->register('tool3', $tool3, ['category' => 'math']);

        $mathTools = $this->registry->filter(['category' => 'math']);

        $this->assertCount(2, $mathTools);
        $this->assertArrayHasKey('tool1', $mathTools);
        $this->assertArrayHasKey('tool3', $mathTools);
        $this->assertArrayNotHasKey('tool2', $mathTools);
    }

    /**
     * Test tool searching by name pattern.
     */
    public function test_search_tools_by_pattern(): void
    {
        $this->registry->register('calculator_add', $this->createTestTool('calculator_add'));
        $this->registry->register('calculator_multiply', $this->createTestTool('calculator_multiply'));
        $this->registry->register('file_reader', $this->createTestTool('file_reader'));

        $calculatorTools = $this->registry->search('calculator_*');

        $this->assertCount(2, $calculatorTools);
        $this->assertArrayHasKey('calculator_add', $calculatorTools);
        $this->assertArrayHasKey('calculator_multiply', $calculatorTools);
        $this->assertArrayNotHasKey('file_reader', $calculatorTools);
    }

    /**
     * Test getting registry type.
     */
    public function test_get_registry_type(): void
    {
        $this->assertEquals('tools', $this->registry->getType());
    }

    /**
     * Test getting tool definitions for MCP protocol.
     */
    public function test_get_tool_definitions(): void
    {
        $tool1 = $this->createTestTool('tool1', [
            'description' => 'First test tool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => ['param1' => ['type' => 'string']],
            ],
        ]);

        $tool2 = $this->createTestTool('tool2', [
            'description' => 'Second test tool',
        ]);

        $this->registry->register('tool1', $tool1, [
            'description' => 'First test tool',
            'input_schema' => [
                'type' => 'object',
                'properties' => ['param1' => ['type' => 'string']],
            ],
        ]);

        $this->registry->register('tool2', $tool2, [
            'description' => 'Second test tool',
        ]);

        $definitions = $this->registry->getToolDefinitions();

        $this->assertCount(2, $definitions);

        $this->assertEquals('tool1', $definitions[0]['name']);
        $this->assertEquals('First test tool', $definitions[0]['description']);
        $this->assertEquals([
            'type' => 'object',
            'properties' => ['param1' => ['type' => 'string']],
        ], $definitions[0]['inputSchema']);

        $this->assertEquals('tool2', $definitions[1]['name']);
        $this->assertEquals('Second test tool', $definitions[1]['description']);
        $this->assertEquals([
            'type' => 'object',
            'properties' => [],
        ], $definitions[1]['inputSchema']);
    }

    /**
     * Test tool execution.
     */
    public function test_execute_tool(): void
    {
        $toolName = 'echo_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool);

        $result = $this->registry->executeTool($toolName, ['input' => 'hello world']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
    }

    /**
     * Test tool execution with class name.
     */
    public function test_execute_tool_with_class_name(): void
    {
        $toolName = 'test_tool';
        $toolClass = get_class($this->createTestTool($toolName));

        $this->registry->register($toolName, $toolClass);

        $result = $this->registry->executeTool($toolName, ['input' => 'test']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
    }

    /**
     * Test tool execution with invalid tool throws exception.
     */
    public function test_execute_invalid_tool_throws_exception(): void
    {
        $toolName = 'invalid_tool';
        $invalidTool = new class
        {
            // No execute method
        };

        $this->registry->register($toolName, $invalidTool);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Tool '{$toolName}' does not have an execute method");

        $this->registry->executeTool($toolName);
    }

    /**
     * Test parameter validation.
     */
    public function test_validate_parameters(): void
    {
        $toolName = 'validation_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool, [
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'required_param' => ['type' => 'string'],
                    'optional_param' => ['type' => 'integer'],
                ],
                'required' => ['required_param'],
            ],
        ]);

        // Valid parameters
        $this->assertTrue($this->registry->validateParameters($toolName, [
            'required_param' => 'test',
            'optional_param' => 42,
        ]));

        // Missing required parameter
        $this->assertFalse($this->registry->validateParameters($toolName, [
            'optional_param' => 42,
        ]));

        // Valid parameters with only required
        $this->assertTrue($this->registry->validateParameters($toolName, [
            'required_param' => 'test',
        ]));
    }

    /**
     * Test parameter validation with no schema allows any parameters.
     */
    public function test_validate_parameters_with_no_schema(): void
    {
        $toolName = 'no_schema_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool);

        // Should return true for any parameters when no schema is defined
        $this->assertTrue($this->registry->validateParameters($toolName, ['any' => 'parameters']));
        $this->assertTrue($this->registry->validateParameters($toolName, []));
    }

    /**
     * Test getting tools by capability.
     */
    public function test_get_tools_by_capability(): void
    {
        $this->registry->register('tool1', $this->createTestTool('tool1'), [
            'capabilities' => ['read', 'write'],
        ]);

        $this->registry->register('tool2', $this->createTestTool('tool2'), [
            'capabilities' => ['read'],
        ]);

        $this->registry->register('tool3', $this->createTestTool('tool3'), [
            'capabilities' => ['write', 'delete'],
        ]);

        $readTools = $this->registry->getToolsByCapability(['read']);
        $this->assertCount(2, $readTools);

        $writeTools = $this->registry->getToolsByCapability(['write']);
        $this->assertCount(2, $writeTools);

        $deleteTools = $this->registry->getToolsByCapability(['delete']);
        $this->assertCount(1, $deleteTools);
    }

    /**
     * Test registry initialization.
     */
    public function test_initialize(): void
    {
        // This should not throw any exception
        $this->registry->initialize();
        $this->assertTrue(true);
    }

    /**
     * Test metadata defaults are set correctly.
     */
    public function test_metadata_defaults(): void
    {
        $toolName = 'default_tool';
        $tool = $this->createTestTool($toolName);

        $this->registry->register($toolName, $tool);

        $metadata = $this->registry->getMetadata($toolName);

        $this->assertEquals($toolName, $metadata['name']);
        $this->assertEquals('tool', $metadata['type']);
        $this->assertEquals('', $metadata['description']);
        $this->assertEquals([], $metadata['parameters']);
        $this->assertNull($metadata['input_schema']);
        $this->assertNotEmpty($metadata['registered_at']);
    }

    /**
     * Test complex filtering scenarios.
     */
    public function test_complex_filtering(): void
    {
        $this->registry->register('tool1', $this->createTestTool('tool1'), [
            'category' => 'math',
            'level' => 'basic',
        ]);

        $this->registry->register('tool2', $this->createTestTool('tool2'), [
            'category' => 'math',
            'level' => 'advanced',
        ]);

        $this->registry->register('tool3', $this->createTestTool('tool3'), [
            'category' => 'string',
            'level' => 'basic',
        ]);

        // Filter by multiple criteria
        $basicMathTools = $this->registry->filter([
            'category' => 'math',
            'level' => 'basic',
        ]);

        $this->assertCount(1, $basicMathTools);
        $this->assertArrayHasKey('tool1', $basicMathTools);

        // Filter with non-matching criteria
        $nonExistentTools = $this->registry->filter([
            'category' => 'nonexistent',
        ]);

        $this->assertCount(0, $nonExistentTools);
    }

    /**
     * Test edge cases for tool names.
     */
    public function test_tool_name_edge_cases(): void
    {
        // Test with special characters in name
        $specialName = 'tool-with_special.chars';
        $tool = $this->createTestTool($specialName);

        $this->registry->register($specialName, $tool);
        $this->assertTrue($this->registry->has($specialName));

        // Test with numeric name
        $numericName = '123';
        $numericTool = $this->createTestTool($numericName);

        $this->registry->register($numericName, $numericTool);
        $this->assertTrue($this->registry->has($numericName));
    }
}
