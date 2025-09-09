<?php

namespace JTD\LaravelMCP\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use Tests\TestCase;

/**
 * Feature tests for the MCP ListCommand.
 *
 * This test validates the complete integration of the ListCommand with
 * the Laravel application container, service providers, and all dependencies.
 *
 * @epic Commands
 *
 * @spec MCP-SPEC-004: Artisan Commands
 *
 * @sprint Sprint-2: Command Implementation
 *
 * @ticket TICKET-004: Artisan Commands
 *
 * @group feature
 * @group commands
 * @group list-command
 *
 * @covers \JTD\LaravelMCP\Commands\ListCommand
 * @covers \JTD\LaravelMCP\Registry\McpRegistry
 * @covers \JTD\LaravelMCP\Registry\ToolRegistry
 * @covers \JTD\LaravelMCP\Registry\ResourceRegistry
 * @covers \JTD\LaravelMCP\Registry\PromptRegistry
 */
class ListCommandFeatureTest extends TestCase
{
    /**
     * Test that the list command is registered in Laravel's artisan.
     */
    public function test_list_command_is_registered_in_artisan(): void
    {
        // Arrange & Act
        $commands = Artisan::all();

        // Assert
        $this->assertArrayHasKey('mcp:list', $commands);
        $this->assertInstanceOf(
            \JTD\LaravelMCP\Commands\ListCommand::class,
            $commands['mcp:list']
        );
    }

    /**
     * Test command registration and availability.
     */
    public function test_command_can_be_discovered_by_artisan(): void
    {
        // Act
        $exitCode = Artisan::call('list', ['--raw' => true]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('mcp:list', $output);
    }

    /**
     * Test command help output displays correct information.
     */
    public function test_command_help_displays_correct_information(): void
    {
        // Act
        $exitCode = Artisan::call('help', ['command_name' => 'mcp:list']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('List all registered MCP components', $output);
        $this->assertStringContainsString('--type', $output);
        $this->assertStringContainsString('--format', $output);
        $this->assertStringContainsString('--detailed', $output);
        $this->assertStringContainsString('--debug', $output);
    }

    /**
     * Test command with empty registries.
     */
    public function test_command_handles_empty_registries(): void
    {
        // Arrange - Clear all registrations
        $this->clearMcpRegistrations();

        // Act
        $exitCode = Artisan::call('mcp:list');
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('No MCP components are currently registered', $output);
        $this->assertStringContainsString('php artisan make:mcp-tool', $output);
        $this->assertStringContainsString('php artisan make:mcp-resource', $output);
        $this->assertStringContainsString('php artisan make:mcp-prompt', $output);
    }

    /**
     * Test command lists all component types.
     */
    public function test_command_lists_all_component_types(): void
    {
        // Arrange - Register test components
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);
        $promptRegistry = $this->app->make(PromptRegistry::class);

        $testTool = $this->createTestTool('calculator', [
            'description' => 'A calculator tool',
        ]);
        $testResource = $this->createTestResource('data-source', [
            'description' => 'A data resource',
            'uri' => 'test://data',
        ]);
        $testPrompt = $this->createTestPrompt('template', [
            'description' => 'A prompt template',
        ]);

        $toolRegistry->register('calculator', $testTool, [
            'description' => 'A calculator tool',
            'parameters' => [],
        ]);

        $resourceRegistry->register('data-source', $testResource, [
            'description' => 'A data resource',
            'uri' => 'test://data',
        ]);

        $promptRegistry->register('template', $testPrompt, [
            'description' => 'A prompt template',
            'arguments' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'all']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Tools', $output);
        $this->assertStringContainsString('calculator', $output);
        $this->assertStringContainsString('A calculator tool', $output);
        $this->assertStringContainsString('Resources', $output);
        $this->assertStringContainsString('data-source', $output);
        $this->assertStringContainsString('A data resource', $output);
        $this->assertStringContainsString('Prompts', $output);
        $this->assertStringContainsString('template', $output);
        $this->assertStringContainsString('A prompt template', $output);
        $this->assertStringContainsString('Summary', $output);
        $this->assertStringContainsString('Total: 3', $output);
    }

    /**
     * Test command filters by component type - tools only.
     */
    public function test_command_filters_by_tools_type(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);

        $testTool = $this->createTestTool('math-tool', [
            'description' => 'Math operations',
        ]);
        $testResource = $this->createTestResource('config', [
            'description' => 'Configuration resource',
        ]);

        $toolRegistry->register('math-tool', $testTool, [
            'description' => 'Math operations',
            'parameters' => [],
        ]);

        $resourceRegistry->register('config', $testResource, [
            'description' => 'Configuration resource',
            'uri' => 'test://config',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'tools']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Tools', $output);
        $this->assertStringContainsString('math-tool', $output);
        $this->assertStringNotContainsString('Resources', $output);
        $this->assertStringNotContainsString('config', $output);
    }

    /**
     * Test command filters by component type - resources only.
     */
    public function test_command_filters_by_resources_type(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);

        $testTool = $this->createTestTool('tool1');
        $testResource = $this->createTestResource('resource1', [
            'description' => 'Test resource',
            'uri' => 'file://test.txt',
            'mimeType' => 'text/plain',
        ]);

        $toolRegistry->register('tool1', $testTool, [
            'description' => 'Test tool',
            'parameters' => [],
        ]);

        $resourceRegistry->register('resource1', $testResource, [
            'description' => 'Test resource',
            'uri' => 'file://test.txt',
            'mime_type' => 'text/plain',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'resources']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Resources', $output);
        $this->assertStringContainsString('resource1', $output);
        $this->assertStringNotContainsString('Tools', $output);
        $this->assertStringNotContainsString('tool1', $output);
    }

    /**
     * Test command filters by component type - prompts only.
     */
    public function test_command_filters_by_prompts_type(): void
    {
        // Arrange
        $promptRegistry = $this->app->make(PromptRegistry::class);
        $toolRegistry = $this->app->make(ToolRegistry::class);

        $testPrompt = $this->createTestPrompt('email-prompt', [
            'description' => 'Email template prompt',
        ]);
        $testTool = $this->createTestTool('tool2');

        $promptRegistry->register('email-prompt', $testPrompt, [
            'description' => 'Email template prompt',
            'arguments' => [],
        ]);

        $toolRegistry->register('tool2', $testTool, [
            'description' => 'Test tool',
            'parameters' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'prompts']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Prompts', $output);
        $this->assertStringContainsString('email-prompt', $output);
        $this->assertStringNotContainsString('Tools', $output);
        $this->assertStringNotContainsString('tool2', $output);
    }

    /**
     * Test command with JSON output format.
     */
    public function test_command_outputs_json_format(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $testTool = $this->createTestTool('json-tool', [
            'description' => 'JSON test tool',
        ]);
        $toolRegistry->register('json-tool', $testTool, [
            'description' => 'JSON test tool',
            'parameters' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--format' => 'json']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $json = json_decode($output, true);
        $this->assertNotNull($json);
        $this->assertArrayHasKey('tools', $json);
        $this->assertArrayHasKey('json-tool', $json['tools']);
        $this->assertEquals('JSON test tool', $json['tools']['json-tool']['description']);
    }

    /**
     * Test command with YAML output format.
     */
    public function test_command_outputs_yaml_format(): void
    {
        // Arrange
        $resourceRegistry = $this->app->make(ResourceRegistry::class);
        $testResource = $this->createTestResource('yaml-resource', [
            'description' => 'YAML test resource',
            'uri' => 'yaml://test',
        ]);
        $resourceRegistry->register('yaml-resource', $testResource, [
            'description' => 'YAML test resource',
            'uri' => 'yaml://test',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--format' => 'yaml']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('resources:', $output);
        $this->assertStringContainsString('yaml-resource:', $output);
        $this->assertStringContainsString('description: \'YAML test resource\'', $output);
    }

    /**
     * Test command with detailed output.
     */
    public function test_command_shows_detailed_information(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $testTool = $this->createTestTool('detailed-tool', [
            'description' => 'Tool with details',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string'],
                    'param2' => ['type' => 'number'],
                ],
            ],
        ]);

        $toolRegistry->register('detailed-tool', $testTool, [
            'description' => 'Tool with details',
            'parameters' => ['param1' => 'string', 'param2' => 'number'],
            'input_schema' => $testTool->getInputSchema(),
            'registered_at' => '2024-01-01 12:00:00',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', [
            '--type' => 'tools',
            '--detailed' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('● detailed-tool', $output);
        $this->assertStringContainsString('Description: Tool with details', $output);
        $this->assertStringContainsString('Class:', $output);
        $this->assertStringContainsString('Registered: 2024-01-01 12:00:00', $output);
        $this->assertStringContainsString('Parameters:', $output);
        $this->assertStringContainsString('param1:', $output);
        $this->assertStringContainsString('param2:', $output);
        $this->assertStringContainsString('Input Schema:', $output);
    }

    /**
     * Test command with detailed resource information.
     */
    public function test_command_shows_detailed_resource_information(): void
    {
        // Arrange
        $resourceRegistry = $this->app->make(ResourceRegistry::class);
        $testResource = $this->createTestResource('detailed-resource', [
            'description' => 'Resource with URI',
            'uri' => 'https://example.com/api/data',
            'mimeType' => 'application/json',
        ]);

        $resourceRegistry->register('detailed-resource', $testResource, [
            'description' => 'Resource with URI',
            'uri' => 'https://example.com/api/data',
            'mime_type' => 'application/json',
            'registered_at' => '2024-01-01 14:00:00',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', [
            '--type' => 'resources',
            '--detailed' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('● detailed-resource', $output);
        $this->assertStringContainsString('URI: https://example.com/api/data', $output);
        $this->assertStringContainsString('MIME Type: application/json', $output);
    }

    /**
     * Test command with detailed prompt information.
     */
    public function test_command_shows_detailed_prompt_information(): void
    {
        // Arrange
        $promptRegistry = $this->app->make(PromptRegistry::class);
        $testPrompt = $this->createTestPrompt('detailed-prompt', [
            'description' => 'Prompt with arguments',
            'argumentsSchema' => [
                'type' => 'object',
                'properties' => [
                    'topic' => ['type' => 'string'],
                    'style' => ['type' => 'string'],
                ],
            ],
        ]);

        $promptRegistry->register('detailed-prompt', $testPrompt, [
            'description' => 'Prompt with arguments',
            'arguments' => ['topic' => 'string', 'style' => 'string'],
            'registered_at' => '2024-01-01 16:00:00',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', [
            '--type' => 'prompts',
            '--detailed' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('● detailed-prompt', $output);
        $this->assertStringContainsString('Arguments:', $output);
        $this->assertStringContainsString('topic:', $output);
        $this->assertStringContainsString('style:', $output);
    }

    /**
     * Test command with debug mode enabled.
     */
    public function test_command_runs_with_debug_mode(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $testTool = $this->createTestTool('debug-tool');
        $toolRegistry->register('debug-tool', $testTool, [
            'description' => 'Test tool',
            'parameters' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', [
            '--debug' => true,
            '-vvv' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Listing MCP components', $output);
    }

    /**
     * Test command handles invalid type option.
     */
    public function test_command_validates_invalid_type_option(): void
    {
        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'invalid']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(2, $exitCode);
        $this->assertStringContainsString('Invalid value for --type', $output);
        $this->assertStringContainsString('Allowed values: all, tools, resources, prompts', $output);
    }

    /**
     * Test command handles invalid format option.
     */
    public function test_command_validates_invalid_format_option(): void
    {
        // Act
        $exitCode = Artisan::call('mcp:list', ['--format' => 'xml']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(2, $exitCode);
        $this->assertStringContainsString('Invalid value for --format', $output);
        $this->assertStringContainsString('Allowed values: table, json, yaml', $output);
    }

    /**
     * Test command handles metadata retrieval failures gracefully.
     */
    public function test_command_handles_metadata_failures_gracefully(): void
    {
        // Arrange - Create a registry that throws exceptions
        $toolRegistry = $this->createMock(ToolRegistry::class);
        $toolRegistry->method('all')->willReturn([
            'broken-tool' => new \stdClass,
        ]);
        $toolRegistry->method('getMetadata')
            ->willThrowException(new \RuntimeException('Metadata unavailable'));

        $this->app->instance(ToolRegistry::class, $toolRegistry);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'tools']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('broken-tool', $output);
        $this->assertStringContainsString('Unable to retrieve metadata', $output);
    }

    /**
     * Test command with multiple registered components.
     */
    public function test_command_lists_multiple_components(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $resourceRegistry = $this->app->make(ResourceRegistry::class);
        $promptRegistry = $this->app->make(PromptRegistry::class);

        // Register multiple components
        for ($i = 1; $i <= 3; $i++) {
            $toolRegistry->register("tool-$i", $this->createTestTool("tool-$i"), [
                'description' => "Test tool $i",
                'parameters' => [],
            ]);

            $resourceRegistry->register("resource-$i", $this->createTestResource("resource-$i"), [
                'description' => "Test resource $i",
                'uri' => "test://resource-$i",
            ]);

            $promptRegistry->register("prompt-$i", $this->createTestPrompt("prompt-$i"), [
                'description' => "Test prompt $i",
                'arguments' => [],
            ]);
        }

        // Act
        $exitCode = Artisan::call('mcp:list');
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Tools:', $output);
        $this->assertStringContainsString('Resources:', $output);
        $this->assertStringContainsString('Prompts:', $output);
        $this->assertStringContainsString('Total: 9', $output);

        // Verify all components are listed
        for ($i = 1; $i <= 3; $i++) {
            $this->assertStringContainsString("tool-$i", $output);
            $this->assertStringContainsString("resource-$i", $output);
            $this->assertStringContainsString("prompt-$i", $output);
        }
    }

    /**
     * Test command with JSON format and detailed output.
     */
    public function test_command_json_format_with_detailed_flag(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $testTool = $this->createTestTool('json-detailed', [
            'description' => 'Detailed JSON tool',
        ]);

        $toolRegistry->register('json-detailed', $testTool, [
            'description' => 'Detailed JSON tool',
            'parameters' => ['input' => 'string'],
            'input_schema' => ['type' => 'object'],
            'registered_at' => '2024-01-01',
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', [
            '--format' => 'json',
            '--detailed' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $json = json_decode($output, true);
        $this->assertNotNull($json);
        $this->assertArrayHasKey('tools', $json);
        $this->assertArrayHasKey('json-detailed', $json['tools']);
        $this->assertArrayHasKey('parameters', $json['tools']['json-detailed']);
        $this->assertArrayHasKey('input_schema', $json['tools']['json-detailed']);
        $this->assertArrayHasKey('registered_at', $json['tools']['json-detailed']);
    }

    /**
     * Test full integration with service provider and real registries.
     */
    public function test_full_integration_with_service_provider(): void
    {
        // This test verifies the command works with the actual service provider
        // and all real dependencies properly registered

        // Act
        $commands = Artisan::all();
        $command = $commands['mcp:list'];

        // Assert
        $this->assertInstanceOf(\JTD\LaravelMCP\Commands\ListCommand::class, $command);

        // Verify dependencies are injected
        $reflection = new \ReflectionClass($command);
        $mcpRegistryProp = $reflection->getProperty('mcpRegistry');
        $mcpRegistryProp->setAccessible(true);
        $toolRegistryProp = $reflection->getProperty('toolRegistry');
        $toolRegistryProp->setAccessible(true);
        $resourceRegistryProp = $reflection->getProperty('resourceRegistry');
        $resourceRegistryProp->setAccessible(true);
        $promptRegistryProp = $reflection->getProperty('promptRegistry');
        $promptRegistryProp->setAccessible(true);

        $this->assertInstanceOf(McpRegistry::class, $mcpRegistryProp->getValue($command));
        $this->assertInstanceOf(ToolRegistry::class, $toolRegistryProp->getValue($command));
        $this->assertInstanceOf(ResourceRegistry::class, $resourceRegistryProp->getValue($command));
        $this->assertInstanceOf(PromptRegistry::class, $promptRegistryProp->getValue($command));
    }

    /**
     * Test command integration with Mcp facade.
     */
    public function test_command_integrates_with_mcp_facade(): void
    {
        // Arrange - Register components via facade
        Mcp::registerTool('facade-tool', $this->createTestTool('facade-tool'));
        Mcp::registerResource('facade-resource', $this->createTestResource('facade-resource'));
        Mcp::registerPrompt('facade-prompt', $this->createTestPrompt('facade-prompt'));

        // Act
        $exitCode = Artisan::call('mcp:list');
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('facade-tool', $output);
        $this->assertStringContainsString('facade-resource', $output);
        $this->assertStringContainsString('facade-prompt', $output);
        $this->assertStringContainsString('Total: 3', $output);
    }

    /**
     * Test command truncates long descriptions in table format.
     */
    public function test_command_truncates_long_descriptions(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $longDescription = str_repeat('This is a very long description. ', 10);
        $testTool = $this->createTestTool('long-desc-tool', [
            'description' => $longDescription,
        ]);

        $toolRegistry->register('long-desc-tool', $testTool, [
            'description' => $longDescription,
            'parameters' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'tools']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('long-desc-tool', $output);
        $this->assertStringContainsString('...', $output);
        // Check that the description was truncated (should show "..." at the end)
        $this->assertMatchesRegularExpression('/\.\.\.\s+\|/', $output);
    }

    /**
     * Test command displays class names correctly.
     */
    public function test_command_formats_class_names(): void
    {
        // Arrange
        $toolRegistry = $this->app->make(ToolRegistry::class);
        $testTool = new class('named-tool') extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name;

            protected string $description = 'Named tool';

            protected array $parameterSchema = [];

            public function __construct(string $name)
            {
                $this->name = $name;
                parent::__construct();
            }

            protected function handle(array $parameters): array
            {
                return ['content' => []];
            }
        };

        $toolRegistry->register('named-tool', $testTool, [
            'description' => 'Named tool',
            'parameters' => [],
        ]);

        // Act
        $exitCode = Artisan::call('mcp:list', ['--type' => 'tools']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('named-tool', $output);
        // Anonymous classes show as McpTool@anonymous
        $this->assertMatchesRegularExpression('/McpTool@anonymous/', $output);
    }
}
