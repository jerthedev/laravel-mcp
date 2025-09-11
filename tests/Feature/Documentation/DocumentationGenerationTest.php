<?php

namespace JTD\LaravelMCP\Tests\Feature\Documentation;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Support\SchemaDocumenter;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Feature tests for end-to-end documentation generation.
 *
 * @group documentation
 * @group feature
 */
class DocumentationGenerationTest extends TestCase
{
    protected string $testDocsPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test documentation path
        $this->testDocsPath = storage_path('app/test-docs');

        // Clean up any existing test docs
        if (File::exists($this->testDocsPath)) {
            File::deleteDirectory($this->testDocsPath);
        }

        // Create test directory
        File::makeDirectory($this->testDocsPath, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up test documentation
        if (File::exists($this->testDocsPath)) {
            File::deleteDirectory($this->testDocsPath);
        }

        parent::tearDown();
    }

    /**
     * @test
     */
    public function it_generates_complete_documentation_via_artisan_command(): void
    {
        // Execute documentation command
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--include-schemas' => true,
            '--include-examples' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert documentation files were created
        $this->assertFileExists($this->testDocsPath.'/overview.md');
        $this->assertFileExists($this->testDocsPath.'/usage_guide.md');
        $this->assertFileExists($this->testDocsPath.'/configuration.md');
        $this->assertFileExists($this->testDocsPath.'/api_reference.md');
        $this->assertFileExists($this->testDocsPath.'/examples.md');

        // Assert component documentation directory exists
        $this->assertDirectoryExists($this->testDocsPath.'/components');
        $this->assertFileExists($this->testDocsPath.'/components/tools.md');
        $this->assertFileExists($this->testDocsPath.'/components/resources.md');
        $this->assertFileExists($this->testDocsPath.'/components/prompts.md');
    }

    /**
     * @test
     */
    public function it_generates_single_file_documentation(): void
    {
        // Execute documentation command with single-file option
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--single-file' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert single documentation file was created
        $this->assertFileExists($this->testDocsPath.'/mcp-documentation.md');

        // Read the file and verify it contains expected sections
        $content = File::get($this->testDocsPath.'/mcp-documentation.md');
        $this->assertStringContainsString('# Laravel MCP Server', $content);
        $this->assertStringContainsString('## Component Statistics', $content);
        $this->assertStringContainsString('# Tools', $content);
        $this->assertStringContainsString('# Resources', $content);
        $this->assertStringContainsString('# Prompts', $content);
    }

    /**
     * @test
     */
    public function it_generates_api_only_documentation(): void
    {
        // Execute documentation command with api-only option
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--api-only' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert only API documentation was created
        $this->assertFileExists($this->testDocsPath.'/api-reference.md');
        $this->assertFileDoesNotExist($this->testDocsPath.'/overview.md');
        $this->assertFileDoesNotExist($this->testDocsPath.'/usage-guide.md');

        // Verify API documentation content
        $content = File::get($this->testDocsPath.'/api-reference.md');
        $this->assertStringContainsString('# API Reference', $content);
        $this->assertStringContainsString('## Core Methods', $content);
        $this->assertStringContainsString('### initialize', $content);
        $this->assertStringContainsString('### ping', $content);
    }

    /**
     * @test
     */
    public function it_generates_components_only_documentation(): void
    {
        // Execute documentation command with components-only option
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--components-only' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert only component documentation was created
        $this->assertFileExists($this->testDocsPath.'/tools.md');
        $this->assertFileExists($this->testDocsPath.'/resources.md');
        $this->assertFileExists($this->testDocsPath.'/prompts.md');
        $this->assertFileDoesNotExist($this->testDocsPath.'/api-reference.md');
        $this->assertFileDoesNotExist($this->testDocsPath.'/overview.md');
    }

    /**
     * @test
     */
    public function it_enriches_documentation_with_schemas(): void
    {
        // Create a mock tool with schema
        $registry = app(McpRegistry::class);
        $toolRegistry = $registry->getToolRegistry();

        // Register a test tool with schema
        $toolRegistry->register('test-tool', new class
        {
            public function getName(): string
            {
                return 'test-tool';
            }

            public function getDescription(): string
            {
                return 'A test tool';
            }

            public function execute(array $params): array
            {
                return ['result' => 'success'];
            }
        });

        // Set metadata with input schema
        $toolRegistry->setMetadata('test-tool', [
            'description' => 'A test tool',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'Input parameter',
                    ],
                ],
                'required' => ['input'],
            ],
        ]);

        // Execute documentation command with schema inclusion
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--include-schemas' => true,
            '--components-only' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Read tools documentation and verify schema is included
        $content = File::get($this->testDocsPath.'/tools.md');
        $this->assertStringContainsString('test-tool', $content);
        $this->assertStringContainsString('Input Schema', $content);
        $this->assertStringContainsString('"type": "object"', $content);
        $this->assertStringContainsString('"input"', $content);
    }

    /**
     * @test
     */
    public function it_generates_documentation_with_examples(): void
    {
        // Execute documentation command with examples
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--include-examples' => true,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Assert examples file was created
        $this->assertFileExists($this->testDocsPath.'/examples.md');

        // Verify examples content
        $content = File::get($this->testDocsPath.'/examples.md');
        $this->assertStringContainsString('# Examples', $content);
        $this->assertStringContainsString('## Simple Tool Example', $content);
        $this->assertStringContainsString('## Simple Resource Example', $content);
        $this->assertStringContainsString('## Simple Prompt Example', $content);
    }

    /**
     * @test
     */
    public function it_integrates_documentation_generator_and_schema_documenter(): void
    {
        // Get instances
        $docGenerator = app(DocumentationGenerator::class);
        $schemaDocumenter = app(SchemaDocumenter::class);

        // Generate complete documentation
        $docs = $docGenerator->generateCompleteDocumentation([
            'name' => 'Test MCP Server',
            'version' => '1.0.0',
            'description' => 'Test server description',
        ]);

        // Assert all sections are present
        $this->assertArrayHasKey('overview', $docs);
        $this->assertArrayHasKey('components', $docs);
        $this->assertArrayHasKey('api_reference', $docs);
        $this->assertArrayHasKey('usage_guide', $docs);
        $this->assertArrayHasKey('configuration', $docs);
        $this->assertArrayHasKey('examples', $docs);

        // Test schema documentation integration
        $testSchema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer', 'minimum' => 0],
            ],
            'required' => ['name'],
        ];

        $schemaDoc = $schemaDocumenter->documentSchema($testSchema);

        // Verify schema documentation format
        $this->assertStringContainsString('**Type:** `object`', $schemaDoc);
        $this->assertStringContainsString('### Properties', $schemaDoc);
        $this->assertStringContainsString('**name** (`string`)', $schemaDoc);
        $this->assertStringContainsString('**age** (`integer`)', $schemaDoc);
        $this->assertStringContainsString('### Validation Rules', $schemaDoc);
        $this->assertStringContainsString('Required fields: `name`', $schemaDoc);
    }

    /**
     * @test
     */
    public function it_handles_empty_registries_gracefully(): void
    {
        // Execute documentation command with empty registries
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
        ]);

        // Assert command executed successfully
        $this->assertEquals(0, $exitCode);

        // Read component documentation and verify empty state messages
        $toolsContent = File::get($this->testDocsPath.'/components/tools.md');
        $resourcesContent = File::get($this->testDocsPath.'/components/resources.md');
        $promptsContent = File::get($this->testDocsPath.'/components/prompts.md');

        $this->assertStringContainsString('No tools are currently registered', $toolsContent);
        $this->assertStringContainsString('No resources are currently registered', $resourcesContent);
        $this->assertStringContainsString('No prompts are currently registered', $promptsContent);
    }

    /**
     * @test
     */
    public function it_generates_documentation_from_blade_templates(): void
    {
        // Register a test component
        $registry = app(McpRegistry::class);
        $toolRegistry = $registry->getToolRegistry();

        $toolRegistry->register('blade-test-tool', new class
        {
            public function getName(): string
            {
                return 'blade-test-tool';
            }

            public function getDescription(): string
            {
                return 'Tool for testing Blade templates';
            }

            public function execute(array $params): array
            {
                return ['result' => 'blade-success'];
            }
        });

        $toolRegistry->setMetadata('blade-test-tool', [
            'description' => 'Tool for testing Blade templates',
            'class' => 'TestToolClass',
            'parameters' => [
                'test_param' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'A test parameter',
                ],
            ],
            'examples' => [
                [
                    'title' => 'Basic Example',
                    'request' => ['test_param' => 'value'],
                    'response' => ['result' => 'blade-success'],
                ],
            ],
        ]);

        // Publish views for testing
        Artisan::call('vendor:publish', [
            '--tag' => 'laravel-mcp-views',
            '--force' => true,
        ]);

        // Test Blade template rendering
        $viewPath = resource_path('views/vendor/laravel-mcp/docs/tool.blade.php');
        $this->assertFileExists($viewPath);

        // Generate documentation
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--components-only' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verify tool documentation includes metadata
        $content = File::get($this->testDocsPath.'/tools.md');
        $this->assertStringContainsString('blade-test-tool', $content);
        $this->assertStringContainsString('TestToolClass', $content);
        $this->assertStringContainsString('test_param', $content);
    }

    /**
     * @test
     */
    public function it_validates_documentation_output_structure(): void
    {
        // Execute full documentation generation
        $exitCode = Artisan::call('mcp:docs', [
            '--output' => $this->testDocsPath,
            '--include-schemas' => true,
            '--include-examples' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Validate directory structure
        $expectedStructure = [
            'overview.md',
            'api_reference.md',
            'usage_guide.md',
            'configuration.md',
            'examples.md',
            'components/tools.md',
            'components/resources.md',
            'components/prompts.md',
        ];

        foreach ($expectedStructure as $path) {
            $fullPath = $this->testDocsPath.'/'.$path;
            $this->assertFileExists($fullPath, "Expected file not found: $path");

            // Verify files are not empty
            $content = File::get($fullPath);
            $this->assertNotEmpty($content, "File should not be empty: $path");

            // Verify markdown formatting
            $this->assertStringContainsString('#', $content, "File should contain markdown headers: $path");
        }
    }

    /**
     * @test
     */
    public function it_handles_custom_output_formats(): void
    {
        // Test with different format options
        $formats = ['markdown', 'html', 'json'];

        foreach ($formats as $format) {
            $outputPath = $this->testDocsPath."/$format";
            File::makeDirectory($outputPath, 0755, true);

            $exitCode = Artisan::call('mcp:docs', [
                '--output' => $outputPath,
                '--format' => $format,
                '--single-file' => true,
            ]);

            $this->assertEquals(0, $exitCode);

            // Verify correct file extension
            $expectedExtension = match ($format) {
                'markdown' => 'md',
                'html' => 'html',
                'json' => 'json',
            };

            $expectedFile = "$outputPath/mcp-documentation.$expectedExtension";
            $this->assertFileExists($expectedFile);
        }
    }
}
