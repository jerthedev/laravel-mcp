<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use Illuminate\Filesystem\Filesystem;
use JTD\LaravelMCP\Commands\MakePromptCommand;
use JTD\LaravelMCP\Tests\TestCase;
use Mockery;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Unit tests for MakePromptCommand.
 *
 * @group commands
 * @group make-commands
 * @group ticket-006
 */
class MakePromptCommandTest extends TestCase
{
    protected MakePromptCommand $command;

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
        $this->command = new MakePromptCommand($this->files);
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
        $this->assertEquals('make:mcp-prompt', $this->command->getName());
    }

    /** @test */
    public function it_has_correct_description(): void
    {
        $this->assertEquals('Create a new MCP prompt class', $this->command->getDescription());
    }

    /** @test */
    public function it_returns_correct_stub_path(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getStubMethod = $reflection->getMethod('getStub');
        $getStubMethod->setAccessible(true);

        $stubPath = $getStubMethod->invoke($this->command);

        $this->assertStringContainsString('prompt.stub', $stubPath);
    }

    /** @test */
    public function it_returns_correct_default_namespace(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getDefaultNamespaceMethod = $reflection->getMethod('getDefaultNamespace');
        $getDefaultNamespaceMethod->setAccessible(true);

        $namespace = $getDefaultNamespaceMethod->invoke($this->command, 'App');

        $this->assertEquals('App\\Mcp\\Prompts', $namespace);
    }

    /** @test */
    public function it_generates_correct_prompt_name_from_class(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getPromptNameMethod = $reflection->getMethod('getPromptName');
        $getPromptNameMethod->setAccessible(true);

        $this->assertEquals('email', $getPromptNameMethod->invoke($this->command, 'EmailPrompt'));
        $this->assertEquals('code_review', $getPromptNameMethod->invoke($this->command, 'CodeReviewPrompt'));
        $this->assertEquals('simple', $getPromptNameMethod->invoke($this->command, 'Simple'));
    }

    /** @test */
    public function it_creates_prompt_file_successfully(): void
    {
        $input = new ArrayInput(['name' => 'TestPrompt']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Prompts/TestPrompt.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestPrompt extends McpPrompt', $content);
        $this->assertStringContainsString('namespace App\\Mcp\\Prompts;', $content);
        $this->assertStringContainsString("protected string \$name = 'test';", $content);
        $this->assertStringContainsString('A prompt for test generation', $content);
    }

    /** @test */
    public function it_creates_prompt_with_template_integration(): void
    {
        $input = new ArrayInput([
            'name' => 'EmailPrompt',
            '--template' => 'email/welcome.blade.php',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Prompts/EmailPrompt.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('A prompt for email using template email/welcome.blade.php', $content);
    }

    /** @test */
    public function it_validates_variables_json(): void
    {
        // Valid JSON should work
        $input = new ArrayInput([
            'name' => 'TestPrompt',
            '--variables' => '{"title": "string", "date": "date", "author": "string"}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(0, $result);

        // Invalid JSON should fail
        $input = new ArrayInput([
            'name' => 'TestPrompt2',
            '--variables' => '{invalid json}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Invalid JSON format for variables', $output);
    }

    /** @test */
    public function it_validates_variable_definitions(): void
    {
        // Invalid variable name should fail
        $input = new ArrayInput([
            'name' => 'TestPrompt',
            '--variables' => '{"123invalid": "string"}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('must be a valid identifier', $output);

        // Empty type should fail
        $this->output = new BufferedOutput; // Reset output buffer
        $input = new ArrayInput([
            'name' => 'TestPrompt2',
            '--variables' => '{"title": ""}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('must have a type specified', $output);
    }

    /** @test */
    public function it_validates_invalid_class_names(): void
    {
        $input = new ArrayInput(['name' => 'invalid_prompt_name']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('must be in PascalCase', $output);
    }

    /** @test */
    public function it_handles_nonexistent_template_gracefully(): void
    {
        $input = new ArrayInput([
            'name' => 'TestPrompt',
            '--template' => 'nonexistent/template.blade.php',
        ]);
        $result = $this->command->run($input, $this->output);

        // Should succeed but show warning
        $this->assertEquals(0, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('does not exist', $output);
    }

    /** @test */
    public function it_resolves_template_paths_correctly(): void
    {
        $reflection = new ReflectionClass($this->command);
        $resolveTemplatePathMethod = $reflection->getMethod('resolveTemplatePath');
        $resolveTemplatePathMethod->setAccessible(true);

        // Test absolute path
        $absolutePath = '/absolute/path/template.php';
        $resolved = $resolveTemplatePathMethod->invoke($this->command, $absolutePath);
        $this->assertEquals($absolutePath, $resolved);

        // Test relative path (should prepend resources/views)
        $relativePath = 'email/welcome.blade.php';
        $resolved = $resolveTemplatePathMethod->invoke($this->command, $relativePath);
        $expected = resource_path("views/{$relativePath}");
        $this->assertEquals($expected, $resolved);
    }

    /** @test */
    public function it_handles_nested_namespaces(): void
    {
        $input = new ArrayInput(['name' => 'Email\\WelcomePrompt']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Prompts/Email/WelcomePrompt.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Mcp\\Prompts\\Email;', $content);
        $this->assertStringContainsString('class WelcomePrompt extends McpPrompt', $content);
        $this->assertStringContainsString("protected string \$name = 'welcome';", $content);
    }

    /** @test */
    public function it_handles_force_option_to_overwrite_existing_files(): void
    {
        // Create file first
        $input1 = new ArrayInput(['name' => 'TestPrompt']);
        $result1 = $this->command->run($input1, $this->output);
        $this->assertEquals(0, $result1);

        // With force option - should succeed
        $this->output = new BufferedOutput; // Reset output buffer
        $input2 = new ArrayInput(['name' => 'TestPrompt', '--force' => true]);
        $result2 = $this->command->run($input2, $this->output);
        $this->assertEquals(0, $result2);
    }

    /** @test */
    public function it_displays_success_message_with_details(): void
    {
        $input = new ArrayInput([
            'name' => 'EmailPrompt',
            '--template' => 'email/welcome.blade.php',
            '--variables' => '{"name": "string", "date": "date"}',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Prompt created successfully!', $output);
        $this->assertStringContainsString('Class: EmailPrompt', $output);
        $this->assertStringContainsString('Prompt Name: email', $output);
        $this->assertStringContainsString('Template: email/welcome.blade.php', $output);
        $this->assertStringContainsString('Variables: name, date', $output);
        $this->assertStringContainsString('Next steps:', $output);
    }

    /** @test */
    public function it_generates_description_with_template_context(): void
    {
        $input = new ArrayInput([
            'name' => 'WelcomePrompt',
            '--template' => 'welcome.blade.php',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Prompts/WelcomePrompt.php';
        $content = file_get_contents($expectedPath);

        // Should contain template-aware description
        $this->assertStringContainsString('A prompt for welcome using template welcome.blade.php', $content);
    }

    /** @test */
    public function it_handles_empty_options_gracefully(): void
    {
        $input = new ArrayInput([
            'name' => 'SimplePrompt',
            '--template' => '',
            '--variables' => '',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Prompts/SimplePrompt.php';
        $content = file_get_contents($expectedPath);

        // Should generate default description
        $this->assertStringContainsString('A prompt for simple generation', $content);
    }

    /** @test */
    public function it_handles_filesystem_errors_gracefully(): void
    {
        // Make directory read-only to cause write error
        $readOnlyDir = $this->tempDir.'/readonly';
        mkdir($readOnlyDir, 0444, true);
        $this->app->instance('path', $readOnlyDir);

        $input = new ArrayInput(['name' => 'TestPrompt']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Failed to create prompt', $output);

        // Cleanup
        chmod($readOnlyDir, 0755);
    }
}
