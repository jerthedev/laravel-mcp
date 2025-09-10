<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use Illuminate\Support\Facades\Artisan;
use JTD\LaravelMCP\Commands\ListCommand;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use Mockery;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Test file for the ListCommand class.
 *
 * @epic Commands
 *
 * @sprint Sprint-2: Command Implementation
 *
 * @ticket TICKET-004: Artisan Commands
 *
 * @covers \JTD\LaravelMCP\Commands\ListCommand
 */
class ListCommandTest extends TestCase
{
    /**
     * The MCP registry mock.
     */
    protected $mcpRegistry;

    /**
     * The tool registry mock.
     */
    protected $toolRegistry;

    /**
     * The resource registry mock.
     */
    protected $resourceRegistry;

    /**
     * The prompt registry mock.
     */
    protected $promptRegistry;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for registries
        $this->mcpRegistry = Mockery::mock(McpRegistry::class);
        $this->toolRegistry = Mockery::mock(ToolRegistry::class);
        $this->resourceRegistry = Mockery::mock(ResourceRegistry::class);
        $this->promptRegistry = Mockery::mock(PromptRegistry::class);

        // Bind mocks to the container
        $this->app->instance(McpRegistry::class, $this->mcpRegistry);
        $this->app->instance(ToolRegistry::class, $this->toolRegistry);
        $this->app->instance(ResourceRegistry::class, $this->resourceRegistry);
        $this->app->instance(PromptRegistry::class, $this->promptRegistry);
    }

    /**
     * Define the environment setup.
     */
    protected function defineEnvironment($app)
    {
        // Call parent to set up base configuration
        parent::defineEnvironment($app);

        // Ensure MCP is enabled for tests (override any parent settings)
        $app['config']->set('laravel-mcp.enabled', true);

        // Set environment variable as well
        putenv('MCP_ENABLED=true');
        $_ENV['MCP_ENABLED'] = 'true';

        // Register the command manually
        $app->singleton(ListCommand::class, function ($app) {
            return new ListCommand(
                $app[McpRegistry::class],
                $app[ToolRegistry::class],
                $app[ResourceRegistry::class],
                $app[PromptRegistry::class]
            );
        });
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app)
    {
        $providers = parent::getPackageProviders($app);

        // Register the ListCommand after the application boots
        $app->afterResolving('artisan', function ($artisan, $app) {
            $artisan->add($app->make(ListCommand::class));
        });

        return $providers;
    }

    /**
     * Test that the command lists all components in table format.
     *
     * @test
     */
    public function it_lists_all_components_in_table_format(): void
    {
        // Set up mock data
        $this->setupMockData();

        // Execute the command
        $this->artisan('mcp:list')
            ->expectsOutputToContain('Tools')
            ->expectsOutputToContain('CalculatorTool')
            ->expectsOutputToContain('Resources')
            ->expectsOutputToContain('UserResource')
            ->expectsOutputToContain('Prompts')
            ->expectsOutputToContain('EmailPrompt')
            ->expectsOutputToContain('Summary')
            ->assertExitCode(0);
    }

    /**
     * Test that the command filters by tool type.
     *
     * @test
     */
    public function it_filters_by_tool_type(): void
    {
        // Set up mock data
        $this->setupMockData();

        // Execute the command
        $this->artisan('mcp:list', ['--type' => 'tools'])
            ->expectsOutputToContain('Tools')
            ->expectsOutputToContain('CalculatorTool')
            ->doesntExpectOutputToContain('Resources')
            ->doesntExpectOutputToContain('Prompts')
            ->assertExitCode(0);
    }

    /**
     * Test that the command filters by resource type.
     *
     * @test
     */
    public function it_filters_by_resource_type(): void
    {
        // Set up mock data
        $this->setupMockData();

        // Execute the command
        $this->artisan('mcp:list', ['--type' => 'resources'])
            ->expectsOutputToContain('Resources')
            ->expectsOutputToContain('UserResource')
            ->doesntExpectOutputToContain('Tools')
            ->doesntExpectOutputToContain('Prompts')
            ->assertExitCode(0);
    }

    /**
     * Test that the command filters by prompt type.
     *
     * @test
     */
    public function it_filters_by_prompt_type(): void
    {
        // Set up mock data
        $this->setupMockData();

        // Execute the command
        $this->artisan('mcp:list', ['--type' => 'prompts'])
            ->expectsOutputToContain('Prompts')
            ->expectsOutputToContain('EmailPrompt')
            ->doesntExpectOutputToContain('Tools')
            ->doesntExpectOutputToContain('Resources')
            ->assertExitCode(0);
    }

    /**
     * Test that the command outputs in JSON format.
     *
     * @test
     */
    public function it_outputs_in_json_format(): void
    {
        // Set up mock data
        $this->setupMockData();

        // Execute the command
        $this->artisan('mcp:list', ['--format' => 'json'])
            ->expectsOutput(json_encode([
                'tools' => [
                    'CalculatorTool' => [
                        'name' => 'CalculatorTool',
                        'description' => 'Performs calculations',
                        'class' => 'App\\Mcp\\Tools\\CalculatorTool',
                    ],
                ],
                'resources' => [
                    'UserResource' => [
                        'name' => 'UserResource',
                        'description' => 'User data resource',
                        'class' => 'App\\Mcp\\Resources\\UserResource',
                    ],
                ],
                'prompts' => [
                    'EmailPrompt' => [
                        'name' => 'EmailPrompt',
                        'description' => 'Email template prompt',
                        'class' => 'App\\Mcp\\Prompts\\EmailPrompt',
                    ],
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
            ->assertExitCode(0);
    }

    /**
     * Test that the command shows detailed information.
     *
     * @test
     */
    public function it_shows_detailed_information(): void
    {
        // Set up mock data with detailed metadata
        $this->setupDetailedMockData();

        // Execute the command
        $this->artisan('mcp:list', ['--detailed' => true])
            ->expectsOutputToContain('CalculatorTool')
            ->expectsOutputToContain('Parameters:')
            ->expectsOutputToContain('operation')
            ->expectsOutputToContain('Input Schema:')
            ->assertExitCode(0);
    }

    /**
     * Test that the command handles empty registries gracefully.
     *
     * @test
     */
    public function it_handles_empty_registries_gracefully(): void
    {
        // Set up empty mock data
        $this->setupEmptyMockData();

        // Execute the command
        $this->artisan('mcp:list')
            ->expectsOutputToContain('No MCP components are currently registered')
            ->expectsOutputToContain('To create MCP components, use the following commands:')
            ->expectsOutputToContain('php artisan make:mcp-tool')
            ->assertExitCode(0);
    }

    /**
     * Test that the command validates invalid type option.
     *
     * @test
     */
    public function it_validates_invalid_type_option(): void
    {
        // Execute the command with invalid type
        $this->artisan('mcp:list', ['--type' => 'invalid'])
            ->expectsOutputToContain('Invalid value for --type')
            ->assertExitCode(2);
    }

    /**
     * Test that the command validates invalid format option.
     *
     * @test
     */
    public function it_validates_invalid_format_option(): void
    {
        // Execute the command with invalid format
        $this->artisan('mcp:list', ['--format' => 'invalid'])
            ->expectsOutputToContain('Invalid value for --format')
            ->assertExitCode(2);
    }

    /**
     * Set up mock data for testing.
     */
    protected function setupMockData(): void
    {
        // Mock tool registry data
        $this->toolRegistry->shouldReceive('all')->andReturn([
            'CalculatorTool' => 'App\\Mcp\\Tools\\CalculatorTool',
        ]);
        $this->toolRegistry->shouldReceive('getMetadata')
            ->with('CalculatorTool')
            ->andReturn([
                'name' => 'CalculatorTool',
                'description' => 'Performs calculations',
                'parameters' => [],
                'registered_at' => '2024-01-01T00:00:00Z',
            ]);

        // Mock resource registry data
        $this->resourceRegistry->shouldReceive('all')->andReturn([
            'UserResource' => 'App\\Mcp\\Resources\\UserResource',
        ]);
        $this->resourceRegistry->shouldReceive('getMetadata')
            ->with('UserResource')
            ->andReturn([
                'name' => 'UserResource',
                'description' => 'User data resource',
                'uri' => '/users/{id}',
                'mime_type' => 'application/json',
                'registered_at' => '2024-01-01T00:00:00Z',
            ]);

        // Mock prompt registry data
        $this->promptRegistry->shouldReceive('all')->andReturn([
            'EmailPrompt' => 'App\\Mcp\\Prompts\\EmailPrompt',
        ]);
        $this->promptRegistry->shouldReceive('getMetadata')
            ->with('EmailPrompt')
            ->andReturn([
                'name' => 'EmailPrompt',
                'description' => 'Email template prompt',
                'arguments' => [],
                'registered_at' => '2024-01-01T00:00:00Z',
            ]);
    }

    /**
     * Set up detailed mock data for testing.
     */
    protected function setupDetailedMockData(): void
    {
        // Mock tool registry data with detailed information
        $this->toolRegistry->shouldReceive('all')->andReturn([
            'CalculatorTool' => 'App\\Mcp\\Tools\\CalculatorTool',
        ]);
        $this->toolRegistry->shouldReceive('getMetadata')
            ->with('CalculatorTool')
            ->andReturn([
                'name' => 'CalculatorTool',
                'description' => 'Performs mathematical calculations',
                'parameters' => [
                    'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number'],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string'],
                        'a' => ['type' => 'number'],
                        'b' => ['type' => 'number'],
                    ],
                    'required' => ['operation', 'a', 'b'],
                ],
                'registered_at' => '2024-01-01T00:00:00Z',
            ]);

        // Mock empty resource and prompt registries
        $this->resourceRegistry->shouldReceive('all')->andReturn([]);
        $this->promptRegistry->shouldReceive('all')->andReturn([]);
    }

    /**
     * Set up empty mock data for testing.
     */
    protected function setupEmptyMockData(): void
    {
        $this->toolRegistry->shouldReceive('all')->andReturn([]);
        $this->resourceRegistry->shouldReceive('all')->andReturn([]);
        $this->promptRegistry->shouldReceive('all')->andReturn([]);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
