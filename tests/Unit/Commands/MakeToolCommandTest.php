<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Commands\MakeToolCommand;
use Mockery;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Unit tests for MakeToolCommand.
 *
 * @group commands
 * @group make-commands
 * @group ticket-006
 */
class MakeToolCommandTest extends TestCase
{
    protected MakeToolCommand $command;

    protected Filesystem $files;

    protected BufferedOutput $output;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for testing
        $this->tempDir = sys_get_temp_dir().'/mcp_test_'.uniqid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        $this->files = new Filesystem;
        $this->command = new MakeToolCommand($this->files);
        $this->command->setLaravel($this->app);
        $this->output = new BufferedOutput;

        // Mock app path to use temp directory
        $this->app->instance('path', $this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->files->deleteDirectory($this->tempDir);
        }

        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_extends_generator_command(): void
    {
        $this->assertInstanceOf(\Illuminate\Console\GeneratorCommand::class, $this->command);
    }

    /** @test */
    public function it_has_correct_signature(): void
    {
        $this->assertEquals('make:mcp-tool', $this->command->getName());
    }

    /** @test */
    public function it_has_correct_description(): void
    {
        $this->assertEquals('Create a new MCP tool class', $this->command->getDescription());
    }

    /** @test */
    public function it_returns_correct_stub_path(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getStubMethod = $reflection->getMethod('getStub');
        $getStubMethod->setAccessible(true);

        $stubPath = $getStubMethod->invoke($this->command);

        $this->assertStringContainsString('tool.stub', $stubPath);
    }

    /** @test */
    public function it_returns_correct_default_namespace(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getDefaultNamespaceMethod = $reflection->getMethod('getDefaultNamespace');
        $getDefaultNamespaceMethod->setAccessible(true);

        $namespace = $getDefaultNamespaceMethod->invoke($this->command, 'App');

        $this->assertEquals('App\\Mcp\\Tools', $namespace);
    }

    /** @test */
    public function it_generates_correct_tool_name_from_class(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getToolNameMethod = $reflection->getMethod('getToolName');
        $getToolNameMethod->setAccessible(true);

        $this->assertEquals('calculator', $getToolNameMethod->invoke($this->command, 'CalculatorTool'));
        $this->assertEquals('user_search', $getToolNameMethod->invoke($this->command, 'UserSearchTool'));
        $this->assertEquals('simple', $getToolNameMethod->invoke($this->command, 'Simple'));
    }

    /** @test */
    public function it_creates_tool_file_successfully(): void
    {
        $input = new ArrayInput(['name' => 'TestTool']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Tools/TestTool.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestTool extends McpTool', $content);
        $this->assertStringContainsString('namespace App\\Mcp\\Tools;', $content);
        $this->assertStringContainsString("protected string \$name = 'test';", $content);
    }

    /** @test */
    public function it_creates_tool_with_custom_description(): void
    {
        $input = new ArrayInput([
            'name' => 'CalculatorTool',
            '--description' => 'A calculator for mathematical operations',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Tools/CalculatorTool.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('A calculator for mathematical operations', $content);
        $this->assertStringContainsString("protected string \$name = 'calculator';", $content);
    }

    /** @test */
    public function it_validates_parameters_json(): void
    {
        // Valid JSON should work
        $input = new ArrayInput([
            'name' => 'TestTool',
            '--parameters' => '{"query": {"type": "string", "description": "Search query"}}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(0, $result);

        // Invalid JSON should fail
        $input = new ArrayInput([
            'name' => 'TestTool2',
            '--parameters' => '{invalid json}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(1, $result); // FAILURE

        $output = $this->output->fetch();
        $this->assertStringContainsString('Invalid JSON format for parameters', $output);
    }

    /** @test */
    public function it_handles_invalid_class_names(): void
    {
        $input = new ArrayInput(['name' => 'invalid_tool_name']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('must be in PascalCase', $output);
    }

    /** @test */
    public function it_handles_force_option_to_overwrite_existing_files(): void
    {
        // Create file first
        $input1 = new ArrayInput(['name' => 'TestTool']);
        $result1 = $this->command->run($input1, $this->output);
        $this->assertEquals(0, $result1);

        // Try to create again without force - should ask for confirmation or fail
        $this->output = new BufferedOutput; // Reset output buffer
        $input2 = new ArrayInput(['name' => 'TestTool']);
        $result2 = $this->command->run($input2, $this->output);

        // With force option - should succeed
        $this->output = new BufferedOutput; // Reset output buffer
        $input3 = new ArrayInput(['name' => 'TestTool', '--force' => true]);
        $result3 = $this->command->run($input3, $this->output);
        $this->assertEquals(0, $result3);
    }

    /** @test */
    public function it_generates_default_description_when_none_provided(): void
    {
        $input = new ArrayInput(['name' => 'CalculatorTool']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Tools/CalculatorTool.php';
        $content = file_get_contents($expectedPath);

        // Should contain default description based on tool name
        $this->assertStringContainsString('calculator operations', $content);
    }

    /** @test */
    public function it_handles_nested_namespaces(): void
    {
        $input = new ArrayInput(['name' => 'Analytics\\ReportTool']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Tools/Analytics/ReportTool.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Mcp\\Tools\\Analytics;', $content);
        $this->assertStringContainsString('class ReportTool extends McpTool', $content);
        $this->assertStringContainsString("protected string \$name = 'report';", $content);
    }

    /** @test */
    public function it_displays_success_message_with_details(): void
    {
        $input = new ArrayInput(['name' => 'TestTool']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Tool created successfully!', $output);
        $this->assertStringContainsString('Class: TestTool', $output);
        $this->assertStringContainsString('Tool Name: test', $output);
        $this->assertStringContainsString('Next steps:', $output);
    }

    /** @test */
    public function it_handles_filesystem_errors_gracefully(): void
    {
        // Make directory read-only to cause write error
        $readOnlyDir = $this->tempDir.'/readonly';
        mkdir($readOnlyDir, 0444, true);
        $this->app->instance('path', $readOnlyDir);

        $input = new ArrayInput(['name' => 'TestTool']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Failed to create tool', $output);

        // Cleanup
        chmod($readOnlyDir, 0755);
    }
}
