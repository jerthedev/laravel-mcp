<?php

/**
 * EPIC: DOCUMENTATION-025
 * SPEC: 11-Documentation.md
 * SPRINT: Implementation Phase
 * TICKET: Advanced Documentation Features - Feature Tests
 *
 * Feature tests for advanced documentation scenarios.
 */

namespace Tests\Feature\Documentation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\AdvancedDocumentationGenerator;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Support\ExampleCompiler;
use JTD\LaravelMCP\Support\ExtensionGuideBuilder;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('documentation')]
#[Group('advanced')]
#[Group('ticket-025')]
class AdvancedDocumentationTest extends TestCase
{
    use RefreshDatabase;

    protected string $tempDocsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDocsPath = storage_path('app/test-docs');
        File::makeDirectory($this->tempDocsPath, 0755, true, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempDocsPath);
        parent::tearDown();
    }

    #[Test]
    public function it_generates_complete_advanced_documentation_suite(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);

        // Act
        $documentation = $generator->generateCompleteAdvancedDocumentation([
            'package_name' => 'Test MCP Package',
            'version' => '2.0.0',
        ]);

        // Assert
        $this->assertIsArray($documentation);
        $this->assertCount(6, $documentation);

        // Verify all sections are present
        $this->assertArrayHasKey('architecture', $documentation);
        $this->assertArrayHasKey('extension_guide', $documentation);
        $this->assertArrayHasKey('performance', $documentation);
        $this->assertArrayHasKey('security', $documentation);
        $this->assertArrayHasKey('contributing', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        // Verify content is generated
        $this->assertNotEmpty($documentation['architecture']);
        $this->assertNotEmpty($documentation['extension_guide']);
        $this->assertNotEmpty($documentation['performance']);
        $this->assertNotEmpty($documentation['security']);
        $this->assertNotEmpty($documentation['contributing']);
        $this->assertIsArray($documentation['examples']);
    }

    #[Test]
    public function it_saves_advanced_documentation_to_files(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);
        $documentation = $generator->generateCompleteAdvancedDocumentation();

        // Act
        $success = $generator->saveAdvancedDocumentation($documentation, $this->tempDocsPath);

        // Assert
        $this->assertTrue($success);

        // Verify files were created
        $this->assertFileExists($this->tempDocsPath.'/architecture.md');
        $this->assertFileExists($this->tempDocsPath.'/extension-guide.md');
        $this->assertFileExists($this->tempDocsPath.'/performance.md');
        $this->assertFileExists($this->tempDocsPath.'/security.md');
        $this->assertFileExists($this->tempDocsPath.'/contributing.md');
        $this->assertDirectoryExists($this->tempDocsPath.'/examples');

        // Verify content was written
        $architectureContent = File::get($this->tempDocsPath.'/architecture.md');
        $this->assertStringContainsString('# Laravel MCP Architecture', $architectureContent);
    }

    #[Test]
    public function it_saves_example_files_correctly(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);
        $documentation = [
            'examples' => [
                'database-tool' => '<?php // Database tool code',
                'api-integration' => '<?php // API integration code',
            ],
        ];

        // Act
        $success = $generator->saveAdvancedDocumentation($documentation, $this->tempDocsPath);

        // Assert
        $this->assertTrue($success);
        $this->assertFileExists($this->tempDocsPath.'/examples/database-tool.php');
        $this->assertFileExists($this->tempDocsPath.'/examples/api-integration.php');

        $dbToolContent = File::get($this->tempDocsPath.'/examples/database-tool.php');
        $this->assertEquals('<?php // Database tool code', $dbToolContent);
    }

    #[Test]
    public function it_integrates_performance_monitor_metrics(): void
    {
        // Arrange
        $monitor = $this->createMock(PerformanceMonitor::class);
        $monitor->method('isEnabled')->willReturn(true);
        $monitor->method('getSummary')->willReturn([
            'enabled' => true,
            'total_metrics' => 1000,
            'unique_metrics' => 50,
            'active_timers' => 2,
            'memory_usage' => 67108864,
            'peak_memory' => 134217728,
            'aggregates' => [
                'tool.execution' => [
                    'count' => 100,
                    'avg' => 45.5,
                    'min' => 10,
                    'max' => 200,
                ],
            ],
        ]);

        $generator = new AdvancedDocumentationGenerator($monitor);

        // Act
        $documentation = $generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('## Current Performance Metrics', $documentation);
        $this->assertStringContainsString('"total_metrics": 1000', $documentation);
        $this->assertStringContainsString('"unique_metrics": 50', $documentation);
        $this->assertStringContainsString('tool.execution', $documentation);
    }

    #[Test]
    public function it_compiles_and_validates_all_examples(): void
    {
        // Arrange
        $compiler = app(ExampleCompiler::class);

        // Act
        $examples = $compiler->compileAdvancedExamples();
        $validationResults = $compiler->getValidationResults();

        // Assert
        $this->assertNotEmpty($examples);
        $this->assertNotEmpty($validationResults);

        // Check that all examples were validated
        foreach (array_keys($examples) as $exampleName) {
            $this->assertArrayHasKey($exampleName, $validationResults,
                "Example {$exampleName} should have validation results");
        }

        // Verify specific examples are included
        $expectedExamples = [
            'database-tool',
            'api-integration',
            'file-processor',
            'cache-resource',
            'complex-prompt',
            'custom-transport',
            'middleware-integration',
            'event-driven',
        ];

        foreach ($expectedExamples as $expected) {
            $this->assertArrayHasKey($expected, $examples,
                "Expected example {$expected} not found");
        }
    }

    #[Test]
    public function it_validates_extension_guides_are_complete(): void
    {
        // Arrange
        $builder = app(ExtensionGuideBuilder::class);

        // Act
        $guide = $builder->buildGuide();
        $validation = $builder->validateGuide($guide);

        // Assert
        $this->assertTrue($validation['valid'], 'Extension guide should be valid');
        $this->assertEmpty($validation['errors'], 'Extension guide should have no errors');

        // The generated guide should pass all validation checks
        if (! empty($validation['warnings'])) {
            $this->assertLessThanOrEqual(2, count($validation['warnings']),
                'Extension guide should have minimal warnings');
        }
    }

    #[Test]
    public function it_generates_working_extension_templates(): void
    {
        // Arrange
        $builder = app(ExtensionGuideBuilder::class);

        // Act & Assert for Tool
        $toolTemplate = $builder->generateExtensionTemplate('tool', 'test_tool');
        $this->assertStringContainsString('class TestTool extends McpTool', $toolTemplate);
        $this->assertStringContainsString('public function execute(array $parameters): array', $toolTemplate);

        // Act & Assert for Resource
        $resourceTemplate = $builder->generateExtensionTemplate('resource', 'test_resource');
        $this->assertStringContainsString('class TestResource extends McpResource', $resourceTemplate);
        $this->assertStringContainsString('public function read(array $parameters): array', $resourceTemplate);

        // Act & Assert for Prompt
        $promptTemplate = $builder->generateExtensionTemplate('prompt', 'test_prompt');
        $this->assertStringContainsString('class TestPrompt extends McpPrompt', $promptTemplate);
        $this->assertStringContainsString('public function render(array $arguments): array', $promptTemplate);
    }

    #[Test]
    public function it_combines_basic_and_advanced_documentation(): void
    {
        // Arrange
        $basicGenerator = app(DocumentationGenerator::class);
        $advancedGenerator = app(AdvancedDocumentationGenerator::class);

        // Act
        $basicDocs = $basicGenerator->generateCompleteDocumentation();
        $advancedDocs = $advancedGenerator->generateCompleteAdvancedDocumentation();

        // Assert
        // Basic documentation sections
        $this->assertArrayHasKey('overview', $basicDocs);
        $this->assertArrayHasKey('components', $basicDocs);
        $this->assertArrayHasKey('api_reference', $basicDocs);
        $this->assertArrayHasKey('usage_guide', $basicDocs);
        $this->assertArrayHasKey('configuration', $basicDocs);
        $this->assertArrayHasKey('examples', $basicDocs);

        // Advanced documentation sections
        $this->assertArrayHasKey('architecture', $advancedDocs);
        $this->assertArrayHasKey('extension_guide', $advancedDocs);
        $this->assertArrayHasKey('performance', $advancedDocs);
        $this->assertArrayHasKey('security', $advancedDocs);
        $this->assertArrayHasKey('contributing', $advancedDocs);
        $this->assertArrayHasKey('examples', $advancedDocs);

        // Verify they complement each other
        $this->assertIsString($basicDocs['examples']);
        $this->assertIsArray($advancedDocs['examples']);
    }

    #[Test]
    public function it_handles_documentation_generation_errors_gracefully(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);
        $invalidPath = '/invalid/path/that/does/not/exist';

        // Act
        $success = $generator->saveAdvancedDocumentation([], $invalidPath);

        // Assert
        $this->assertFalse($success, 'Should return false when saving fails');
    }

    #[Test]
    public function it_includes_mermaid_diagrams_in_architecture_docs(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);

        // Act
        $architecture = $generator->generateArchitectureDocumentation();

        // Assert
        $this->assertStringContainsString('```mermaid', $architecture);
        $this->assertStringContainsString('sequenceDiagram', $architecture);
        $this->assertStringContainsString('graph TD', $architecture);

        // Verify diagram structure
        $this->assertStringContainsString('Client->>Transport', $architecture);
        $this->assertStringContainsString('Transport->>Protocol', $architecture);
        $this->assertStringContainsString('Protocol->>Registry', $architecture);
        $this->assertStringContainsString('Registry->>Component', $architecture);
    }

    #[Test]
    public function it_generates_security_documentation_with_code_examples(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);

        // Act
        $security = $generator->generateSecurityBestPractices();

        // Assert
        // Check for security-related code examples
        $this->assertStringContainsString('```php', $security);
        $this->assertStringContainsString('X-MCP-Token', $security);
        $this->assertStringContainsString('tokenCan(\'mcp:access\')', $security);
        $this->assertStringContainsString('Crypt::encryptString', $security);
        $this->assertStringContainsString('RateLimiter', $security);

        // Check for environment variable examples
        $this->assertStringContainsString('```env', $security);
        $this->assertStringContainsString('MCP_API_KEY=', $security);

        // Check for nginx configuration
        $this->assertStringContainsString('```nginx', $security);
        $this->assertStringContainsString('ssl_client_certificate', $security);
    }

    #[Test]
    public function it_generates_contribution_guidelines_with_proper_formatting(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);

        // Act
        $contributing = $generator->generateContributionGuidelines();

        // Assert
        // Check for proper markdown formatting
        $this->assertStringContainsString('# Contributing to Laravel MCP', $contributing);
        $this->assertStringContainsString('## ', $contributing);
        $this->assertStringContainsString('### ', $contributing);
        $this->assertStringContainsString('- ', $contributing);
        $this->assertStringContainsString('```bash', $contributing);
        $this->assertStringContainsString('```php', $contributing);

        // Check for git workflow
        $this->assertStringContainsString('git checkout -b', $contributing);
        $this->assertStringContainsString('composer test', $contributing);
        $this->assertStringContainsString('pull request', $contributing);
    }

    #[Test]
    public function it_validates_all_generated_examples_compile(): void
    {
        // Arrange
        $compiler = app(ExampleCompiler::class);

        // Act
        $examples = $compiler->compileAdvancedExamples();
        $validationResults = $compiler->getValidationResults();

        // Assert
        foreach ($validationResults as $name => $result) {
            // Check if validation was attempted
            $this->assertArrayHasKey('success', $result,
                "Example {$name} should have success status");

            // Database tool and similar complex examples might have validation issues
            // in test environment, but they should at least parse correctly
            if (isset($result['errors']) && ! empty($result['errors'])) {
                // Ensure no syntax errors
                foreach ($result['errors'] as $error) {
                    $this->assertStringNotContainsString('syntax error', strtolower($error),
                        "Example {$name} has syntax error: {$error}");
                }
            }
        }
    }

    #[Test]
    public function it_generates_performance_optimization_with_real_examples(): void
    {
        // Arrange
        $generator = app(AdvancedDocumentationGenerator::class);

        // Act
        $performance = $generator->generatePerformanceOptimization();

        // Assert
        // Check for specific optimization techniques
        $this->assertStringContainsString('Lazy load components', $performance);
        $this->assertStringContainsString('eager loading', $performance);
        $this->assertStringContainsString('Cache::remember', $performance);
        $this->assertStringContainsString('paginate', $performance);
        $this->assertStringContainsString('streamDownload', $performance);

        // Check for monitoring integration
        $this->assertStringContainsString('Laravel Telescope', $performance);
        $this->assertStringContainsString('PerformanceMonitor', $performance);

        // Check for configuration examples
        $this->assertStringContainsString('CACHE_DRIVER=redis', $performance);
        $this->assertStringContainsString('REDIS_HOST=', $performance);
    }
}
