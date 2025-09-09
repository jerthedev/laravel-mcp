<?php

namespace JTD\LaravelMCP\Tests\Integration\Commands;

use Illuminate\Filesystem\Filesystem;
use JTD\LaravelMCP\Commands\MakePromptCommand;
use JTD\LaravelMCP\Commands\MakeResourceCommand;
use JTD\LaravelMCP\Commands\MakeToolCommand;
use Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Integration tests for all make commands.
 *
 * These tests validate that generated code compiles correctly,
 * follows MCP specifications, and integrates with the MCP system.
 *
 * @group commands
 * @group make-commands
 * @group integration
 * @group ticket-006
 */
class MakeCommandsIntegrationTest extends TestCase
{
    protected Filesystem $files;

    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temp directory for testing
        $this->tempDir = sys_get_temp_dir().'/mcp_integration_test_'.uniqid();
        if (! is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        $this->files = new Filesystem;

        // Mock app path to use temp directory
        $this->app->instance('path', $this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->files->deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_generates_valid_tool_class_that_compiles(): void
    {
        $command = new MakeToolCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput([
            'name' => 'CalculatorTool',
            '--description' => 'A calculator for mathematical operations',
            '--parameters' => '{"operation": {"type": "string", "description": "Math operation"}, "numbers": {"type": "array", "description": "Numbers to calculate"}}',
        ]);
        $output = new BufferedOutput;

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        $filePath = $this->tempDir.'/Mcp/Tools/CalculatorTool.php';
        $this->assertTrue(file_exists($filePath));

        $content = file_get_contents($filePath);

        // Verify the generated class follows MCP specifications
        $this->assertStringContainsString('namespace App\\Mcp\\Tools;', $content);
        $this->assertStringContainsString('use JTD\\LaravelMCP\\Abstracts\\McpTool;', $content);
        $this->assertStringContainsString('class CalculatorTool extends McpTool', $content);
        $this->assertStringContainsString("protected string \$name = 'calculator';", $content);
        $this->assertStringContainsString('A calculator for mathematical operations', $content);
        $this->assertStringContainsString('protected array $inputSchema', $content);
        $this->assertStringContainsString('public function execute(array $arguments): array', $content);

        // Validate PHP syntax by attempting to parse the file
        $this->assertTrue($this->isValidPhpSyntax($content), 'Generated tool class has invalid PHP syntax');
    }

    /** @test */
    public function it_generates_valid_resource_class_that_compiles(): void
    {
        $command = new MakeResourceCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput([
            'name' => 'UserResource',
            '--model' => 'User',
            '--uri-template' => '/users/{id}',
        ]);
        $output = new BufferedOutput;

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        $filePath = $this->tempDir.'/Mcp/Resources/UserResource.php';
        $this->assertTrue(file_exists($filePath));

        $content = file_get_contents($filePath);

        // Verify the generated class follows MCP specifications
        $this->assertStringContainsString('namespace App\\Mcp\\Resources;', $content);
        $this->assertStringContainsString('use JTD\\LaravelMCP\\Abstracts\\McpResource;', $content);
        $this->assertStringContainsString('class UserResource extends McpResource', $content);
        $this->assertStringContainsString("protected string \$name = 'user';", $content);
        $this->assertStringContainsString("protected string \$uri = '/users/{id}';", $content);
        $this->assertStringContainsString('providing access to User model data', $content);

        // Validate PHP syntax
        $this->assertTrue($this->isValidPhpSyntax($content), 'Generated resource class has invalid PHP syntax');
    }

    /** @test */
    public function it_generates_valid_prompt_class_that_compiles(): void
    {
        $command = new MakePromptCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput([
            'name' => 'EmailPrompt',
            '--template' => 'email/welcome.blade.php',
            '--variables' => '{"name": "string", "email": "string", "date": "date"}',
        ]);
        $output = new BufferedOutput;

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        $filePath = $this->tempDir.'/Mcp/Prompts/EmailPrompt.php';
        $this->assertTrue(file_exists($filePath));

        $content = file_get_contents($filePath);

        // Verify the generated class follows MCP specifications
        $this->assertStringContainsString('namespace App\\Mcp\\Prompts;', $content);
        $this->assertStringContainsString('use JTD\\LaravelMCP\\Abstracts\\McpPrompt;', $content);
        $this->assertStringContainsString('class EmailPrompt extends McpPrompt', $content);
        $this->assertStringContainsString("protected string \$name = 'email';", $content);
        $this->assertStringContainsString('using template email/welcome.blade.php', $content);

        // Validate PHP syntax
        $this->assertTrue($this->isValidPhpSyntax($content), 'Generated prompt class has invalid PHP syntax');
    }

    /** @test */
    public function it_generates_classes_with_proper_inheritance_structure(): void
    {
        // Generate all three types
        $this->generateToolClass();
        $this->generateResourceClass();
        $this->generatePromptClass();

        // Load the base classes to check inheritance would work
        $toolPath = $this->tempDir.'/Mcp/Tools/TestTool.php';
        $resourcePath = $this->tempDir.'/Mcp/Resources/TestResource.php';
        $promptPath = $this->tempDir.'/Mcp/Prompts/TestPrompt.php';

        $this->assertTrue(file_exists($toolPath));
        $this->assertTrue(file_exists($resourcePath));
        $this->assertTrue(file_exists($promptPath));

        // Verify each extends the correct base class
        $toolContent = file_get_contents($toolPath);
        $resourceContent = file_get_contents($resourcePath);
        $promptContent = file_get_contents($promptPath);

        $this->assertStringContainsString('extends McpTool', $toolContent);
        $this->assertStringContainsString('extends McpResource', $resourceContent);
        $this->assertStringContainsString('extends McpPrompt', $promptContent);
    }

    /** @test */
    public function it_handles_complex_nested_namespace_generation(): void
    {
        $command = new MakeToolCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput(['name' => 'Analytics\\Reporting\\DataExportTool']);
        $output = new BufferedOutput;

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        $filePath = $this->tempDir.'/Mcp/Tools/Analytics/Reporting/DataExportTool.php';
        $this->assertTrue(file_exists($filePath));

        $content = file_get_contents($filePath);

        // Verify proper namespace and class structure
        $this->assertStringContainsString('namespace App\\Mcp\\Tools\\Analytics\\Reporting;', $content);
        $this->assertStringContainsString('class DataExportTool extends McpTool', $content);
        $this->assertStringContainsString("protected string \$name = 'data_export';", $content);

        // Validate directory structure was created correctly
        $this->assertTrue(is_dir($this->tempDir.'/Mcp/Tools/Analytics/Reporting'));

        $this->assertTrue($this->isValidPhpSyntax($content), 'Generated nested class has invalid PHP syntax');
    }

    /** @test */
    public function it_generates_classes_with_proper_mcp_schema_structure(): void
    {
        // Test tool with complex input schema
        $command = new MakeToolCommand($this->files);
        $command->setLaravel($this->app);

        $complexSchema = json_encode([
            'query' => [
                'type' => 'string',
                'description' => 'Search query',
                'required' => true,
            ],
            'options' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer'],
                    'sortBy' => ['type' => 'string'],
                ],
            ],
        ]);

        $input = new ArrayInput([
            'name' => 'SearchTool',
            '--parameters' => $complexSchema,
        ]);
        $output = new BufferedOutput;

        $result = $command->run($input, $output);

        $this->assertEquals(0, $result);

        $filePath = $this->tempDir.'/Mcp/Tools/SearchTool.php';
        $content = file_get_contents($filePath);

        // Verify the class structure includes all required MCP properties
        $this->assertStringContainsString('protected string $name', $content);
        $this->assertStringContainsString('protected string $description', $content);
        $this->assertStringContainsString('protected array $inputSchema', $content);
        $this->assertStringContainsString('public function execute(array $arguments): array', $content);

        // Verify return structure follows MCP specification
        $this->assertStringContainsString("'content' => [", $content);
        $this->assertStringContainsString("'type' => 'text'", $content);

        $this->assertTrue($this->isValidPhpSyntax($content), 'Generated complex schema class has invalid PHP syntax');
    }

    /** @test */
    public function it_handles_force_overwrite_correctly_across_all_commands(): void
    {
        // Create initial files
        $this->generateToolClass();
        $this->generateResourceClass();
        $this->generatePromptClass();

        $toolPath = $this->tempDir.'/Mcp/Tools/TestTool.php';
        $resourcePath = $this->tempDir.'/Mcp/Resources/TestResource.php';
        $promptPath = $this->tempDir.'/Mcp/Prompts/TestPrompt.php';

        // Verify files exist
        $this->assertTrue(file_exists($toolPath));
        $this->assertTrue(file_exists($resourcePath));
        $this->assertTrue(file_exists($promptPath));

        // Get original modification times
        $toolMtime = filemtime($toolPath);
        $resourceMtime = filemtime($resourcePath);
        $promptMtime = filemtime($promptPath);

        // Wait a moment to ensure different timestamps
        sleep(1); // 1 second to ensure different mtime

        // Regenerate with force option
        $this->generateToolClass(['--force' => true]);
        $this->generateResourceClass(['--force' => true]);
        $this->generatePromptClass(['--force' => true]);

        // Verify files were overwritten (modification time changed)
        $this->assertNotEquals($toolMtime, filemtime($toolPath));
        $this->assertNotEquals($resourceMtime, filemtime($resourcePath));
        $this->assertNotEquals($promptMtime, filemtime($promptPath));
    }

    /**
     * Helper method to check if PHP code has valid syntax
     */
    protected function isValidPhpSyntax(string $code): bool
    {
        // Use token_get_all to validate PHP syntax
        $tokens = @token_get_all($code);
        if ($tokens === false) {
            return false;
        }

        // Additional check with php -l
        $tempFile = tempnam(sys_get_temp_dir(), 'php_syntax_check_');
        file_put_contents($tempFile, $code);

        $output = [];
        $returnCode = 0;
        exec("php -l {$tempFile} 2>&1", $output, $returnCode);

        unlink($tempFile);

        return $returnCode === 0;
    }

    /**
     * Helper to generate tool class
     */
    protected function generateToolClass(array $extraOptions = []): void
    {
        $command = new MakeToolCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput(array_merge(['name' => 'TestTool'], $extraOptions));
        $output = new BufferedOutput;

        $command->run($input, $output);
    }

    /**
     * Helper to generate resource class
     */
    protected function generateResourceClass(array $extraOptions = []): void
    {
        $command = new MakeResourceCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput(array_merge(['name' => 'TestResource'], $extraOptions));
        $output = new BufferedOutput;

        $command->run($input, $output);
    }

    /**
     * Helper to generate prompt class
     */
    protected function generatePromptClass(array $extraOptions = []): void
    {
        $command = new MakePromptCommand($this->files);
        $command->setLaravel($this->app);

        $input = new ArrayInput(array_merge(['name' => 'TestPrompt'], $extraOptions));
        $output = new BufferedOutput;

        $command->run($input, $output);
    }
}
