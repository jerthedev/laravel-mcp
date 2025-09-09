<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use Illuminate\Filesystem\Filesystem;
use JTD\LaravelMCP\Commands\MakeResourceCommand;
use Mockery;
use ReflectionClass;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

/**
 * Unit tests for MakeResourceCommand.
 *
 * @group commands
 * @group make-commands
 * @group ticket-006
 */
class MakeResourceCommandTest extends TestCase
{
    protected MakeResourceCommand $command;

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
        $this->command = new MakeResourceCommand($this->files);
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
        $this->assertEquals('make:mcp-resource', $this->command->getName());
    }

    /** @test */
    public function it_has_correct_description(): void
    {
        $this->assertEquals('Create a new MCP resource class', $this->command->getDescription());
    }

    /** @test */
    public function it_returns_correct_stub_path(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getStubMethod = $reflection->getMethod('getStub');
        $getStubMethod->setAccessible(true);

        $stubPath = $getStubMethod->invoke($this->command);

        $this->assertStringContainsString('resource.stub', $stubPath);
    }

    /** @test */
    public function it_returns_correct_default_namespace(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getDefaultNamespaceMethod = $reflection->getMethod('getDefaultNamespace');
        $getDefaultNamespaceMethod->setAccessible(true);

        $namespace = $getDefaultNamespaceMethod->invoke($this->command, 'App');

        $this->assertEquals('App\\Mcp\\Resources', $namespace);
    }

    /** @test */
    public function it_generates_correct_resource_name_from_class(): void
    {
        $reflection = new ReflectionClass($this->command);
        $getResourceNameMethod = $reflection->getMethod('getResourceName');
        $getResourceNameMethod->setAccessible(true);

        $this->assertEquals('user', $getResourceNameMethod->invoke($this->command, 'UserResource'));
        $this->assertEquals('article_data', $getResourceNameMethod->invoke($this->command, 'ArticleDataResource'));
        $this->assertEquals('simple', $getResourceNameMethod->invoke($this->command, 'Simple'));
    }

    /** @test */
    public function it_creates_resource_file_successfully(): void
    {
        $input = new ArrayInput(['name' => 'TestResource']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Resources/TestResource.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestResource extends McpResource', $content);
        $this->assertStringContainsString('namespace App\\Mcp\\Resources;', $content);
        $this->assertStringContainsString("protected string \$name = 'test';", $content);
        $this->assertStringContainsString("protected string \$uri = '/test';", $content);
    }

    /** @test */
    public function it_creates_resource_with_model_integration(): void
    {
        $input = new ArrayInput([
            'name' => 'UserResource',
            '--model' => 'User',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Resources/UserResource.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('providing access to User model data', $content);
        $this->assertStringContainsString("protected string \$uri = '/user/{id}';", $content);
    }

    /** @test */
    public function it_creates_resource_with_custom_uri_template(): void
    {
        $input = new ArrayInput([
            'name' => 'ArticleResource',
            '--uri-template' => '/articles/{id}/details',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Resources/ArticleResource.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString("protected string \$uri = '/articles/{id}/details';", $content);
    }

    /** @test */
    public function it_validates_invalid_class_names(): void
    {
        $input = new ArrayInput(['name' => 'invalid_resource_name']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('must be in PascalCase', $output);
    }

    /** @test */
    public function it_validates_uri_template_format(): void
    {
        // Valid URI template should work
        $input = new ArrayInput([
            'name' => 'TestResource',
            '--uri-template' => '/api/test/{id}',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(0, $result);

        // Invalid URI template should fail
        $input = new ArrayInput([
            'name' => 'TestResource2',
            '--uri-template' => '/invalid<script>alert(1)</script>',
        ]);
        $result = $this->command->run($input, $this->output);
        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('contains invalid characters', $output);
    }

    /** @test */
    public function it_handles_nonexistent_model_gracefully(): void
    {
        $input = new ArrayInput([
            'name' => 'TestResource',
            '--model' => 'NonExistentModel',
        ]);
        $result = $this->command->run($input, $this->output);

        // Should succeed but show warning
        $this->assertEquals(0, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('does not exist', $output);
    }

    /** @test */
    public function it_handles_nested_namespaces(): void
    {
        $input = new ArrayInput(['name' => 'Api\\V1\\UserResource']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Resources/Api/V1/UserResource.php';
        $this->assertTrue(file_exists($expectedPath));

        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\\Mcp\\Resources\\Api\\V1;', $content);
        $this->assertStringContainsString('class UserResource extends McpResource', $content);
    }

    /** @test */
    public function it_handles_force_option_to_overwrite_existing_files(): void
    {
        // Create file first
        $input1 = new ArrayInput(['name' => 'TestResource']);
        $result1 = $this->command->run($input1, $this->output);
        $this->assertEquals(0, $result1);

        // With force option - should succeed
        $this->output = new BufferedOutput; // Reset output buffer
        $input2 = new ArrayInput(['name' => 'TestResource', '--force' => true]);
        $result2 = $this->command->run($input2, $this->output);
        $this->assertEquals(0, $result2);
    }

    /** @test */
    public function it_displays_success_message_with_details(): void
    {
        $input = new ArrayInput([
            'name' => 'TestResource',
            '--model' => 'TestModel',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Resource created successfully!', $output);
        $this->assertStringContainsString('Class: TestResource', $output);
        $this->assertStringContainsString('Resource Name: test', $output);
        $this->assertStringContainsString('Model: TestModel', $output);
        $this->assertStringContainsString('Next steps:', $output);
    }

    /** @test */
    public function it_generates_appropriate_uri_for_model_resources(): void
    {
        $input = new ArrayInput([
            'name' => 'UserResource',
            '--model' => 'Custom\\Models\\User',
        ]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);

        $expectedPath = $this->tempDir.'/Mcp/Resources/UserResource.php';
        $content = file_get_contents($expectedPath);

        // Should generate URI based on model name
        $this->assertStringContainsString("protected string \$uri = '/user/{id}';", $content);
    }

    /** @test */
    public function it_qualifies_model_class_correctly(): void
    {
        $reflection = new ReflectionClass($this->command);
        $qualifyModelMethod = $reflection->getMethod('qualifyModel');
        $qualifyModelMethod->setAccessible(true);

        // Test basic model qualification
        $qualified = $qualifyModelMethod->invoke($this->command, 'User');
        $this->assertEquals('App\\Models\\User', $qualified);

        // Test fully qualified model
        $qualified = $qualifyModelMethod->invoke($this->command, 'App\\Models\\User');
        $this->assertEquals('App\\Models\\User', $qualified);

        // Test custom namespace
        $qualified = $qualifyModelMethod->invoke($this->command, 'Domain\\User\\Models\\User');
        $this->assertEquals('App\\Models\\Domain\\User\\Models\\User', $qualified);
    }

    /** @test */
    public function it_handles_filesystem_errors_gracefully(): void
    {
        // Make directory read-only to cause write error
        $readOnlyDir = $this->tempDir.'/readonly';
        mkdir($readOnlyDir, 0444, true);
        $this->app->instance('path', $readOnlyDir);

        $input = new ArrayInput(['name' => 'TestResource']);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(1, $result);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Failed to create resource', $output);

        // Cleanup
        chmod($readOnlyDir, 0755);
    }
}
