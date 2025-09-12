<?php

/**
 * EPIC: DOCUMENTATION-025
 * SPEC: 11-Documentation.md
 * SPRINT: Implementation Phase
 * TICKET: Advanced Documentation Features
 *
 * Comprehensive unit tests for AdvancedDocumentationGenerator.
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\AdvancedDocumentationGenerator;
use JTD\LaravelMCP\Support\ExampleCompiler;
use JTD\LaravelMCP\Support\ExtensionGuideBuilder;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdvancedDocumentationGenerator::class)]
#[Group('unit')]
#[Group('support')]
#[Group('documentation')]
#[Group('ticket-025')]
class AdvancedDocumentationGeneratorTest extends TestCase
{
    protected AdvancedDocumentationGenerator $generator;

    protected PerformanceMonitor&MockObject $mockPerformanceMonitor;

    protected ExampleCompiler&MockObject $mockExampleCompiler;

    protected ExtensionGuideBuilder&MockObject $mockExtensionBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockPerformanceMonitor = $this->createMock(PerformanceMonitor::class);
        $this->mockExampleCompiler = $this->createMock(ExampleCompiler::class);
        $this->mockExtensionBuilder = $this->createMock(ExtensionGuideBuilder::class);

        $this->generator = new AdvancedDocumentationGenerator(
            $this->mockPerformanceMonitor,
            $this->mockExampleCompiler,
            $this->mockExtensionBuilder
        );
    }

    #[Test]
    public function it_generates_architecture_documentation(): void
    {
        // Act
        $documentation = $this->generator->generateArchitectureDocumentation();

        // Assert
        $this->assertIsString($documentation);
        $this->assertStringContainsString('# Laravel MCP Architecture', $documentation);
        $this->assertStringContainsString('## Overview', $documentation);
        $this->assertStringContainsString('## Layered Architecture', $documentation);
        $this->assertStringContainsString('## Component Diagrams', $documentation);
        $this->assertStringContainsString('## Data Flow', $documentation);
        $this->assertStringContainsString('## Design Patterns', $documentation);
        $this->assertStringContainsString('## Scalability Considerations', $documentation);
    }

    #[Test]
    public function it_generates_architecture_documentation_with_custom_options(): void
    {
        // Arrange
        $options = ['package_name' => 'Custom MCP Package'];

        // Act
        $documentation = $this->generator->generateArchitectureDocumentation($options);

        // Assert
        $this->assertStringContainsString('# Custom MCP Package Architecture', $documentation);
    }

    #[Test]
    public function it_includes_core_design_principles_in_architecture(): void
    {
        // Act
        $documentation = $this->generator->generateArchitectureDocumentation();

        // Assert
        $this->assertStringContainsString('### Core Design Principles', $documentation);
        $this->assertStringContainsString('Separation of Concerns', $documentation);
        $this->assertStringContainsString('Extensibility', $documentation);
        $this->assertStringContainsString('Laravel Integration', $documentation);
        $this->assertStringContainsString('Protocol Compliance', $documentation);
        $this->assertStringContainsString('Performance', $documentation);
    }

    #[Test]
    public function it_includes_layered_architecture_details(): void
    {
        // Act
        $documentation = $this->generator->generateArchitectureDocumentation();

        // Assert
        $this->assertStringContainsString('### Transport Layer', $documentation);
        $this->assertStringContainsString('StdioTransport', $documentation);
        $this->assertStringContainsString('HttpTransport', $documentation);
        $this->assertStringContainsString('### Protocol Layer', $documentation);
        $this->assertStringContainsString('JsonRpcHandler', $documentation);
        $this->assertStringContainsString('### Registry Layer', $documentation);
        $this->assertStringContainsString('McpRegistry', $documentation);
        $this->assertStringContainsString('### Application Layer', $documentation);
        $this->assertStringContainsString('McpServer', $documentation);
    }

    #[Test]
    public function it_includes_mermaid_diagrams_in_architecture(): void
    {
        // Act
        $documentation = $this->generator->generateArchitectureDocumentation();

        // Assert
        $this->assertStringContainsString('```mermaid', $documentation);
        $this->assertStringContainsString('sequenceDiagram', $documentation);
        $this->assertStringContainsString('graph TD', $documentation);
    }

    #[Test]
    public function it_generates_extension_guide_using_builder(): void
    {
        // Arrange
        $expectedGuide = 'Extension guide content';
        $this->mockExtensionBuilder
            ->expects($this->once())
            ->method('buildGuide')
            ->with([])
            ->willReturn($expectedGuide);

        // Act
        $guide = $this->generator->generateExtensionGuide();

        // Assert
        $this->assertEquals($expectedGuide, $guide);
    }

    #[Test]
    public function it_generates_performance_optimization_documentation(): void
    {
        // Arrange
        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(false);

        // Act
        $documentation = $this->generator->generatePerformanceOptimization();

        // Assert
        $this->assertIsString($documentation);
        $this->assertStringContainsString('# Performance Optimization Guide', $documentation);
        $this->assertStringContainsString('## Performance Goals', $documentation);
        $this->assertStringContainsString('## Performance Benchmarks', $documentation);
        $this->assertStringContainsString('## Optimization Techniques', $documentation);
        $this->assertStringContainsString('## Caching Strategies', $documentation);
        $this->assertStringContainsString('## Performance Monitoring', $documentation);
    }

    #[Test]
    public function it_includes_current_metrics_when_performance_monitor_is_enabled(): void
    {
        // Arrange
        $metrics = [
            'enabled' => true,
            'total_metrics' => 100,
            'unique_metrics' => 10,
        ];

        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(true);

        $this->mockPerformanceMonitor
            ->method('getSummary')
            ->willReturn($metrics);

        // Act
        $documentation = $this->generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('## Current Performance Metrics', $documentation);
        $this->assertStringContainsString('```json', $documentation);
        $this->assertStringContainsString('"enabled": true', $documentation);
        $this->assertStringContainsString('"total_metrics": 100', $documentation);
    }

    #[Test]
    public function it_includes_optimization_techniques_with_code_examples(): void
    {
        // Arrange
        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(false);

        // Act
        $documentation = $this->generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('### 1. Component Loading', $documentation);
        $this->assertStringContainsString('### 2. Database Optimization', $documentation);
        $this->assertStringContainsString('### 3. Response Optimization', $documentation);
        $this->assertStringContainsString('### 4. Memory Management', $documentation);
        $this->assertStringContainsString('```php', $documentation);
    }

    #[Test]
    public function it_includes_caching_strategies(): void
    {
        // Arrange
        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(false);

        // Act
        $documentation = $this->generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('### Response Caching', $documentation);
        $this->assertStringContainsString('Cache::remember', $documentation);
        $this->assertStringContainsString('### Component Registry Caching', $documentation);
        $this->assertStringContainsString('### Redis Configuration', $documentation);
        $this->assertStringContainsString('CACHE_DRIVER=redis', $documentation);
    }

    #[Test]
    public function it_includes_monitoring_guide(): void
    {
        // Arrange
        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(false);

        // Act
        $documentation = $this->generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('### Built-in Monitoring', $documentation);
        $this->assertStringContainsString('### Laravel Telescope Integration', $documentation);
        $this->assertStringContainsString('### Custom Metrics', $documentation);
        $this->assertStringContainsString('PerformanceMonitor', $documentation);
    }

    #[Test]
    public function it_generates_security_best_practices_documentation(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertIsString($documentation);
        $this->assertStringContainsString('# Security Best Practices', $documentation);
        $this->assertStringContainsString('## Security Principles', $documentation);
        $this->assertStringContainsString('## Authentication', $documentation);
        $this->assertStringContainsString('## Authorization', $documentation);
        $this->assertStringContainsString('## Input Validation', $documentation);
        $this->assertStringContainsString('## Secure Configuration', $documentation);
        $this->assertStringContainsString('## Security Auditing', $documentation);
    }

    #[Test]
    public function it_includes_authentication_methods(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertStringContainsString('### API Token Authentication', $documentation);
        $this->assertStringContainsString('### OAuth 2.0 Integration', $documentation);
        $this->assertStringContainsString('### mTLS (Mutual TLS)', $documentation);
        $this->assertStringContainsString('Laravel Passport', $documentation);
    }

    #[Test]
    public function it_includes_authorization_patterns(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertStringContainsString('### Role-Based Access Control', $documentation);
        $this->assertStringContainsString('### Permission-Based Access', $documentation);
        $this->assertStringContainsString('### Resource-Level Security', $documentation);
        $this->assertStringContainsString('hasRole', $documentation);
        $this->assertStringContainsString('hasPermission', $documentation);
    }

    #[Test]
    public function it_includes_input_validation_security(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertStringContainsString('### Parameter Validation', $documentation);
        $this->assertStringContainsString('### SQL Injection Prevention', $documentation);
        $this->assertStringContainsString('### XSS Prevention', $documentation);
        $this->assertStringContainsString('getInputSchema', $documentation);
        $this->assertStringContainsString('parameterized queries', $documentation);
    }

    #[Test]
    public function it_includes_secure_configuration_practices(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertStringContainsString('### Environment Variables', $documentation);
        $this->assertStringContainsString('### Encryption', $documentation);
        $this->assertStringContainsString('### Rate Limiting', $documentation);
        $this->assertStringContainsString('MCP_API_KEY', $documentation);
        $this->assertStringContainsString('Crypt::encryptString', $documentation);
        $this->assertStringContainsString('throttle:api', $documentation);
    }

    #[Test]
    public function it_includes_security_auditing(): void
    {
        // Act
        $documentation = $this->generator->generateSecurityBestPractices();

        // Assert
        $this->assertStringContainsString('### Audit Logging', $documentation);
        $this->assertStringContainsString('### Security Headers', $documentation);
        $this->assertStringContainsString('Log::channel', $documentation);
        $this->assertStringContainsString('X-Content-Type-Options', $documentation);
        $this->assertStringContainsString('Strict-Transport-Security', $documentation);
    }

    #[Test]
    public function it_generates_contribution_guidelines(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertIsString($documentation);
        $this->assertStringContainsString('# Contributing to Laravel MCP', $documentation);
        $this->assertStringContainsString('## Ways to Contribute', $documentation);
        $this->assertStringContainsString('## Code of Conduct', $documentation);
        $this->assertStringContainsString('## Development Setup', $documentation);
        $this->assertStringContainsString('## Contribution Process', $documentation);
        $this->assertStringContainsString('## Coding Standards', $documentation);
        $this->assertStringContainsString('## Testing Requirements', $documentation);
    }

    #[Test]
    public function it_includes_code_of_conduct(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertStringContainsString('### Our Pledge', $documentation);
        $this->assertStringContainsString('### Expected Behavior', $documentation);
        $this->assertStringContainsString('### Unacceptable Behavior', $documentation);
        $this->assertStringContainsString('harassment-free experience', $documentation);
    }

    #[Test]
    public function it_includes_development_setup_instructions(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertStringContainsString('### Prerequisites', $documentation);
        $this->assertStringContainsString('PHP 8.2 or higher', $documentation);
        $this->assertStringContainsString('Laravel 11.x', $documentation);
        $this->assertStringContainsString('### Installation', $documentation);
        $this->assertStringContainsString('composer install', $documentation);
        $this->assertStringContainsString('composer test', $documentation);
    }

    #[Test]
    public function it_includes_contribution_process(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertStringContainsString('### 1. Create an Issue', $documentation);
        $this->assertStringContainsString('### 2. Fork and Branch', $documentation);
        $this->assertStringContainsString('### 3. Make Changes', $documentation);
        $this->assertStringContainsString('### 4. Test Your Changes', $documentation);
        $this->assertStringContainsString('### 5. Submit Pull Request', $documentation);
    }

    #[Test]
    public function it_includes_coding_standards(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertStringContainsString('### PHP Standards', $documentation);
        $this->assertStringContainsString('PSR-12', $documentation);
        $this->assertStringContainsString('### Documentation Standards', $documentation);
        $this->assertStringContainsString('### Git Commit Messages', $documentation);
        $this->assertStringContainsString('present tense', $documentation);
        $this->assertStringContainsString('imperative mood', $documentation);
    }

    #[Test]
    public function it_includes_testing_requirements(): void
    {
        // Act
        $documentation = $this->generator->generateContributionGuidelines();

        // Assert
        $this->assertStringContainsString('### Test Coverage', $documentation);
        $this->assertStringContainsString('Minimum 90% code coverage', $documentation);
        $this->assertStringContainsString('### Test Organization', $documentation);
        $this->assertStringContainsString('### Integration Tests', $documentation);
        $this->assertStringContainsString('PHPUnit\\Framework\\Attributes', $documentation);
    }

    #[Test]
    public function it_generates_advanced_examples_using_compiler(): void
    {
        // Arrange
        $expectedExamples = [
            'database-tool' => 'database tool code',
            'api-integration' => 'api integration code',
        ];

        $this->mockExampleCompiler
            ->expects($this->once())
            ->method('compileAdvancedExamples')
            ->with([])
            ->willReturn($expectedExamples);

        // Act
        $examples = $this->generator->generateAdvancedExamples();

        // Assert
        $this->assertEquals($expectedExamples, $examples);
    }

    #[Test]
    public function it_validates_extension_guide_using_builder(): void
    {
        // Arrange
        $guide = 'Extension guide content';
        $expectedValidation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $this->mockExtensionBuilder
            ->expects($this->once())
            ->method('validateGuide')
            ->with($guide)
            ->willReturn($expectedValidation);

        // Act
        $validation = $this->generator->validateExtensionGuide($guide);

        // Assert
        $this->assertEquals($expectedValidation, $validation);
    }

    #[Test]
    public function it_compiles_example_code_using_compiler(): void
    {
        // Arrange
        $code = '<?php echo "test";';
        $type = 'tool';
        $expectedResult = [
            'success' => true,
            'class' => 'TestTool',
            'type' => 'tool',
        ];

        $this->mockExampleCompiler
            ->expects($this->once())
            ->method('compile')
            ->with($code, $type)
            ->willReturn($expectedResult);

        // Act
        $result = $this->generator->compileExample($code, $type);

        // Assert
        $this->assertEquals($expectedResult, $result);
    }

    #[Test]
    public function it_generates_complete_advanced_documentation(): void
    {
        // Arrange
        $this->mockPerformanceMonitor
            ->method('isEnabled')
            ->willReturn(false);

        $this->mockExtensionBuilder
            ->method('buildGuide')
            ->willReturn('Extension guide');

        $this->mockExampleCompiler
            ->method('compileAdvancedExamples')
            ->willReturn(['example1' => 'code1']);

        // Act
        $documentation = $this->generator->generateCompleteAdvancedDocumentation();

        // Assert
        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('architecture', $documentation);
        $this->assertArrayHasKey('extension_guide', $documentation);
        $this->assertArrayHasKey('performance', $documentation);
        $this->assertArrayHasKey('security', $documentation);
        $this->assertArrayHasKey('contributing', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        $this->assertStringContainsString('# Laravel MCP Architecture', $documentation['architecture']);
        $this->assertEquals('Extension guide', $documentation['extension_guide']);
        $this->assertStringContainsString('# Performance Optimization Guide', $documentation['performance']);
        $this->assertStringContainsString('# Security Best Practices', $documentation['security']);
        $this->assertStringContainsString('# Contributing to Laravel MCP', $documentation['contributing']);
        $this->assertEquals(['example1' => 'code1'], $documentation['examples']);
    }

    #[Test]
    public function it_can_be_created_without_dependencies(): void
    {
        // Act
        $generator = new AdvancedDocumentationGenerator;

        // Assert
        $this->assertInstanceOf(AdvancedDocumentationGenerator::class, $generator);

        // Test that it still works without mocks
        $documentation = $generator->generateArchitectureDocumentation();
        $this->assertStringContainsString('# Laravel MCP Architecture', $documentation);
    }

    #[Test]
    public function it_generates_performance_documentation_without_monitor(): void
    {
        // Arrange
        $generator = new AdvancedDocumentationGenerator(null);

        // Act
        $documentation = $generator->generatePerformanceOptimization();

        // Assert
        $this->assertStringContainsString('## Performance Benchmarks', $documentation);
        $this->assertStringContainsString('### Baseline Metrics', $documentation);
        $this->assertStringNotContainsString('## Current Performance Metrics', $documentation);
    }
}
