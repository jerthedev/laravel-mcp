<?php

/**
 * EPIC: DOCUMENTATION-025
 * SPEC: 11-Documentation.md
 * SPRINT: Implementation Phase
 * TICKET: Advanced Documentation Features - Integration Tests
 *
 * Integration tests for advanced documentation system.
 */

namespace Tests\Integration\Documentation;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Support\AdvancedDocumentationGenerator;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Support\ExampleCompiler;
use JTD\LaravelMCP\Support\ExtensionGuideBuilder;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
#[Group('documentation')]
#[Group('advanced')]
#[Group('ticket-025')]
class AdvancedDocumentationIntegrationTest extends TestCase
{
    protected string $tempDocsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDocsPath = sys_get_temp_dir().'/test-docs-'.uniqid();
        if (! is_dir($this->tempDocsPath)) {
            mkdir($this->tempDocsPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDocsPath)) {
            $this->deleteDirectory($this->tempDocsPath);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_integrates_basic_and_advanced_documentation_generators(): void
    {
        // Arrange
        $mcpRegistry = $this->createMock(McpRegistry::class);
        $toolRegistry = $this->createMock(ToolRegistry::class);
        $resourceRegistry = $this->createMock(ResourceRegistry::class);
        $promptRegistry = $this->createMock(PromptRegistry::class);

        $mcpRegistry->method('count')->willReturn(10);
        $toolRegistry->method('count')->willReturn(5);
        $resourceRegistry->method('count')->willReturn(3);
        $promptRegistry->method('count')->willReturn(2);
        $toolRegistry->method('all')->willReturn([]);
        $resourceRegistry->method('all')->willReturn([]);
        $promptRegistry->method('all')->willReturn([]);

        $basicGenerator = new DocumentationGenerator(
            $mcpRegistry,
            $toolRegistry,
            $resourceRegistry,
            $promptRegistry
        );

        $advancedGenerator = new AdvancedDocumentationGenerator;

        // Act
        $basicDocs = $basicGenerator->generateCompleteDocumentation();
        $advancedDocs = $advancedGenerator->generateCompleteAdvancedDocumentation();

        // Assert
        // Verify both generators produce complementary documentation
        $this->assertIsArray($basicDocs);
        $this->assertIsArray($advancedDocs);

        // Basic focuses on component documentation
        $this->assertArrayHasKey('components', $basicDocs);
        $this->assertArrayHasKey('api_reference', $basicDocs);

        // Advanced focuses on architecture and best practices
        $this->assertArrayHasKey('architecture', $advancedDocs);
        $this->assertArrayHasKey('security', $advancedDocs);
        $this->assertArrayHasKey('performance', $advancedDocs);
    }

    #[Test]
    public function it_generates_and_saves_complete_documentation_suite(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator;

        // Act
        $documentation = $generator->generateCompleteAdvancedDocumentation();
        $success = $generator->saveAdvancedDocumentation($documentation, $this->tempDocsPath);

        // Assert
        $this->assertTrue($success);

        // Verify all documentation files were created
        $expectedFiles = [
            'architecture.md',
            'extension-guide.md',
            'performance.md',
            'security.md',
            'contributing.md',
        ];

        foreach ($expectedFiles as $file) {
            $filePath = $this->tempDocsPath.'/'.$file;
            $this->assertFileExists($filePath, "File {$file} should exist");

            $content = file_get_contents($filePath);
            $this->assertNotEmpty($content, "File {$file} should have content");
            $this->assertStringContainsString('#', $content, "File {$file} should contain markdown headers");
        }

        // Verify examples directory was created
        $this->assertDirectoryExists($this->tempDocsPath.'/examples');
    }

    #[Test]
    public function it_compiles_all_advanced_examples_without_syntax_errors(): void
    {
        // Arrange
        $compiler = new ExampleCompiler;

        // Act
        $examples = $compiler->compileAdvancedExamples();
        $validationResults = $compiler->getValidationResults();

        // Assert
        $this->assertNotEmpty($examples);

        // Check each example for basic validity
        foreach ($examples as $name => $code) {
            $this->assertStringStartsWith('<?php', $code, "Example {$name} should start with PHP tag");

            // Check for basic structure
            $this->assertStringContainsString('namespace', $code, "Example {$name} should have namespace");
            $this->assertStringContainsString('class', $code, "Example {$name} should have class");
            $this->assertStringContainsString('public function', $code, "Example {$name} should have methods");

            // Ensure no obvious syntax errors in validation
            if (isset($validationResults[$name]['errors'])) {
                foreach ($validationResults[$name]['errors'] as $error) {
                    $this->assertStringNotContainsString('Parse error', $error);
                    $this->assertStringNotContainsString('syntax error', strtolower($error));
                }
            }
        }
    }

    #[Test]
    public function it_validates_extension_guides_with_proper_structure(): void
    {
        // Arrange
        $builder = new ExtensionGuideBuilder;

        // Act
        $guide = $builder->buildGuide();
        $validation = $builder->validateGuide($guide);

        // Assert
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);

        // Check guide contains all required sections
        $requiredSections = [
            '# Extending Laravel MCP',
            '## Overview',
            '## Creating Custom Tools',
            '## Creating Custom Resources',
            '## Creating Custom Prompts',
            '## Creating Custom Transports',
            '## Adding Custom Middleware',
            '## Creating Event Listeners',
            '## Creating MCP Extension Packages',
        ];

        foreach ($requiredSections as $section) {
            $this->assertStringContainsString($section, $guide, "Guide should contain section: {$section}");
        }
    }

    #[Test]
    public function it_generates_extension_templates_with_valid_php_code(): void
    {
        // Arrange
        $builder = new ExtensionGuideBuilder;

        // Act & Assert
        $types = ['tool', 'resource', 'prompt'];

        foreach ($types as $type) {
            $template = $builder->generateExtensionTemplate($type, 'test_component');

            // Basic PHP validation
            $this->assertStringStartsWith('<?php', $template);
            $this->assertStringContainsString('namespace App\\Mcp\\', $template);
            $this->assertStringContainsString('class TestComponent extends', $template);

            // Check for required methods based on type
            switch ($type) {
                case 'tool':
                    $this->assertStringContainsString('public function execute(array $parameters): array', $template);
                    break;
                case 'resource':
                    $this->assertStringContainsString('public function read(array $parameters): array', $template);
                    $this->assertStringContainsString('public function getUri(): string', $template);
                    break;
                case 'prompt':
                    $this->assertStringContainsString('public function render(array $arguments): array', $template);
                    $this->assertStringContainsString('public function getArguments(): array', $template);
                    break;
            }
        }
    }

    #[Test]
    public function it_integrates_performance_monitor_with_documentation(): void
    {
        // Arrange
        $monitor = $this->createMock(PerformanceMonitor::class);
        $monitor->method('isEnabled')->willReturn(true);
        $monitor->method('getSummary')->willReturn([
            'enabled' => true,
            'total_metrics' => 3,
            'unique_metrics' => 3,
            'active_timers' => 0,
            'memory_usage' => 1048576,
            'peak_memory' => 2097152,
            'aggregates' => [],
        ]);

        $generator = new AdvancedDocumentationGenerator($monitor);

        // Act
        $documentation = $generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('## Current Performance Metrics', $documentation);
        $this->assertStringContainsString('```json', $documentation);
        $this->assertStringContainsString('"total_metrics": 3', $documentation);
    }

    #[Test]
    public function it_generates_documentation_with_all_security_sections(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator;

        // Act
        $security = $generator->generateSecurityBestPractices();

        // Assert
        $securitySections = [
            '## Security Principles',
            '## Authentication',
            '## Authorization',
            '## Input Validation',
            '## Secure Configuration',
            '## Security Auditing',
        ];

        foreach ($securitySections as $section) {
            $this->assertStringContainsString($section, $security, "Security doc should contain: {$section}");
        }

        // Check for specific security implementations
        $this->assertStringContainsString('API Token Authentication', $security);
        $this->assertStringContainsString('OAuth 2.0', $security);
        $this->assertStringContainsString('mTLS', $security);
        $this->assertStringContainsString('SQL Injection Prevention', $security);
        $this->assertStringContainsString('XSS Prevention', $security);
        $this->assertStringContainsString('Rate Limiting', $security);
    }

    #[Test]
    public function it_generates_contribution_guidelines_with_complete_workflow(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator;

        // Act
        $contributing = $generator->generateContributionGuidelines();

        // Assert
        // Check for complete contribution workflow
        $workflowSteps = [
            '### 1. Create an Issue',
            '### 2. Fork and Branch',
            '### 3. Make Changes',
            '### 4. Test Your Changes',
            '### 5. Submit Pull Request',
        ];

        foreach ($workflowSteps as $step) {
            $this->assertStringContainsString($step, $contributing, "Should contain workflow step: {$step}");
        }

        // Check for coding standards
        $this->assertStringContainsString('PSR-12', $contributing);
        $this->assertStringContainsString('PHPUnit', $contributing);
        $this->assertStringContainsString('90% code coverage', $contributing);
    }

    #[Test]
    public function it_generates_architecture_documentation_with_diagrams(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator;

        // Act
        $architecture = $generator->generateArchitectureDocumentation();

        // Assert
        // Check for Mermaid diagrams
        $this->assertStringContainsString('```mermaid', $architecture);
        $this->assertStringContainsString('sequenceDiagram', $architecture);
        $this->assertStringContainsString('graph TD', $architecture);

        // Check for architecture layers
        $layers = [
            '### Transport Layer',
            '### Protocol Layer',
            '### Registry Layer',
            '### Application Layer',
        ];

        foreach ($layers as $layer) {
            $this->assertStringContainsString($layer, $architecture, "Should contain layer: {$layer}");
        }

        // Check for design patterns
        $patterns = [
            'Factory Pattern',
            'Registry Pattern',
            'Strategy Pattern',
            'Observer Pattern',
            'Facade Pattern',
        ];

        foreach ($patterns as $pattern) {
            $this->assertStringContainsString($pattern, $architecture, "Should mention pattern: {$pattern}");
        }
    }

    #[Test]
    public function it_handles_documentation_generation_errors_gracefully(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator;
        $invalidPath = '/invalid/path/that/definitely/does/not/exist/ever';

        // Act
        $documentation = ['test' => 'content'];
        $success = $generator->saveAdvancedDocumentation($documentation, $invalidPath);

        // Assert
        $this->assertFalse($success, 'Should return false when unable to save');
    }

    /**
     * Helper method to recursively delete a directory.
     */
    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
