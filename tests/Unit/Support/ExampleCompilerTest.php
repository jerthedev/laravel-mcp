<?php

/**
 * EPIC: DOCUMENTATION-025
 * SPEC: 11-Documentation.md
 * SPRINT: Implementation Phase
 * TICKET: Advanced Documentation Features - Example Compiler
 *
 * Comprehensive unit tests for ExampleCompiler.
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\ExampleCompiler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExampleCompiler::class)]
#[Group('unit')]
#[Group('support')]
#[Group('documentation')]
#[Group('example-compiler')]
#[Group('ticket-025')]
class ExampleCompilerTest extends TestCase
{
    protected ExampleCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->compiler = new ExampleCompiler;
    }

    #[Test]
    public function it_compiles_valid_tool_example(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test tool'; }
    public function execute(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('TestTool', $result['class']);
        $this->assertEquals('App\Mcp\Tools', $result['namespace']);
        $this->assertEquals('tool', $result['type']);
        $this->assertTrue($result['validated']);
    }

    #[Test]
    public function it_compiles_valid_resource_example(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Resources;
use JTD\LaravelMCP\Abstracts\McpResource;

class TestResource extends McpResource
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test resource'; }
    public function getUri(): string { return 'test://resource'; }
    public function read(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'resource');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('TestResource', $result['class']);
        $this->assertEquals('App\Mcp\Resources', $result['namespace']);
        $this->assertEquals('resource', $result['type']);
    }

    #[Test]
    public function it_compiles_valid_prompt_example(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Prompts;
use JTD\LaravelMCP\Abstracts\McpPrompt;

class TestPrompt extends McpPrompt
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test prompt'; }
    public function render(array $arguments): array { return ['messages' => []]; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'prompt');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('TestPrompt', $result['class']);
        $this->assertEquals('App\Mcp\Prompts', $result['namespace']);
        $this->assertEquals('prompt', $result['type']);
    }

    #[Test]
    public function it_detects_syntax_errors(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool
{
    public function getName(): string { return 'test' } // Missing semicolon
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals('TestTool', $result['class']);
    }

    #[Test]
    public function it_detects_missing_base_class(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;

class TestTool // Not extending McpTool
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test tool'; }
    public function execute(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertContains('Class must extend McpTool', $result['errors']);
    }

    #[Test]
    public function it_detects_missing_required_methods_for_tool(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool
{
    public function getName(): string { return 'test'; }
    // Missing getDescription and execute methods
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertContains('Missing required method: getDescription()', $result['errors']);
        $this->assertContains('Missing required method: execute()', $result['errors']);
    }

    #[Test]
    public function it_detects_missing_required_methods_for_resource(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Resources;
use JTD\LaravelMCP\Abstracts\McpResource;

class TestResource extends McpResource
{
    public function getName(): string { return 'test'; }
    // Missing getDescription, getUri, and read methods
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'resource');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertContains('Missing required method: getDescription()', $result['errors']);
        $this->assertContains('Missing required method: getUri()', $result['errors']);
        $this->assertContains('Missing required method: read()', $result['errors']);
    }

    #[Test]
    public function it_detects_missing_required_methods_for_prompt(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Prompts;
use JTD\LaravelMCP\Abstracts\McpPrompt;

class TestPrompt extends McpPrompt
{
    public function getName(): string { return 'test'; }
    // Missing getDescription and render methods
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'prompt');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertContains('Missing required method: getDescription()', $result['errors']);
        $this->assertContains('Missing required method: render()', $result['errors']);
    }

    #[Test]
    public function it_extracts_class_name_correctly(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;
class MyCustomTool extends McpTool {}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertEquals('MyCustomTool', $result['class']);
    }

    #[Test]
    public function it_extracts_namespace_correctly(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Custom\Namespace;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool {
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test'; }
    public function execute(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertEquals('App\Custom\Namespace', $result['namespace']);
    }

    #[Test]
    public function it_compiles_advanced_examples(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertIsArray($examples);
        $this->assertArrayHasKey('database-tool', $examples);
        $this->assertArrayHasKey('api-integration', $examples);
        $this->assertArrayHasKey('file-processor', $examples);
        $this->assertArrayHasKey('cache-resource', $examples);
        $this->assertArrayHasKey('complex-prompt', $examples);
        $this->assertArrayHasKey('custom-transport', $examples);
        $this->assertArrayHasKey('middleware-integration', $examples);
        $this->assertArrayHasKey('event-driven', $examples);

        // Verify each example is a string containing PHP code
        foreach ($examples as $name => $code) {
            $this->assertIsString($code, "Example {$name} should be a string");
            $this->assertStringStartsWith('<?php', $code, "Example {$name} should start with <?php");
            $this->assertStringContainsString('namespace', $code, "Example {$name} should have namespace");
            $this->assertStringContainsString('class', $code, "Example {$name} should have class definition");
        }
    }

    #[Test]
    public function it_validates_all_advanced_examples(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();
        $validationResults = $this->compiler->getValidationResults();

        // Assert
        $this->assertNotEmpty($validationResults);

        foreach ($validationResults as $name => $result) {
            $this->assertArrayHasKey('success', $result, "Validation result for {$name} should have 'success' key");
            $this->assertArrayHasKey('class', $result, "Validation result for {$name} should have 'class' key");
            $this->assertArrayHasKey('type', $result, "Validation result for {$name} should have 'type' key");
        }
    }

    #[Test]
    public function it_generates_database_tool_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('database-tool', $examples);
        $code = $examples['database-tool'];

        $this->assertStringContainsString('DatabaseQueryTool', $code);
        $this->assertStringContainsString('extends McpTool', $code);
        $this->assertStringContainsString('database_query', $code);
        $this->assertStringContainsString('DB::table', $code);
        $this->assertStringContainsString('Validator::make', $code);
    }

    #[Test]
    public function it_generates_api_integration_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('api-integration', $examples);
        $code = $examples['api-integration'];

        $this->assertStringContainsString('ApiIntegrationTool', $code);
        $this->assertStringContainsString('Http::', $code);
        $this->assertStringContainsString('Cache::', $code);
        $this->assertStringContainsString('retry', $code);
        $this->assertStringContainsString('cache_ttl', $code);
    }

    #[Test]
    public function it_generates_file_processor_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('file-processor', $examples);
        $code = $examples['file-processor'];

        $this->assertStringContainsString('FileProcessorResource', $code);
        $this->assertStringContainsString('extends McpResource', $code);
        $this->assertStringContainsString('Storage::', $code);
        $this->assertStringContainsString('file://processor', $code);
    }

    #[Test]
    public function it_generates_cache_resource_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('cache-resource', $examples);
        $code = $examples['cache-resource'];

        $this->assertStringContainsString('CacheManagementResource', $code);
        $this->assertStringContainsString('Redis::', $code);
        $this->assertStringContainsString('cache://management', $code);
        $this->assertStringContainsString('getCacheStats', $code);
    }

    #[Test]
    public function it_generates_complex_prompt_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('complex-prompt', $examples);
        $code = $examples['complex-prompt'];

        $this->assertStringContainsString('ComplexAnalysisPrompt', $code);
        $this->assertStringContainsString('extends McpPrompt', $code);
        $this->assertStringContainsString('buildSystemPrompt', $code);
        $this->assertStringContainsString('getExampleMessages', $code);
    }

    #[Test]
    public function it_generates_custom_transport_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('custom-transport', $examples);
        $code = $examples['custom-transport'];

        $this->assertStringContainsString('WebSocketTransport', $code);
        $this->assertStringContainsString('implements TransportInterface', $code);
        $this->assertStringContainsString('initialize', $code);
        $this->assertStringContainsString('send', $code);
        $this->assertStringContainsString('receive', $code);
    }

    #[Test]
    public function it_generates_middleware_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('middleware-integration', $examples);
        $code = $examples['middleware-integration'];

        $this->assertStringContainsString('McpRateLimitMiddleware', $code);
        $this->assertStringContainsString('RateLimiter::', $code);
        $this->assertStringContainsString('resolveRequestKey', $code);
        $this->assertStringContainsString('getMaxAttempts', $code);
    }

    #[Test]
    public function it_generates_event_driven_example(): void
    {
        // Act
        $examples = $this->compiler->compileAdvancedExamples();

        // Assert
        $this->assertArrayHasKey('event-driven', $examples);
        $code = $examples['event-driven'];

        $this->assertStringContainsString('EventDrivenTool', $code);
        $this->assertStringContainsString('Event::dispatch', $code);
        $this->assertStringContainsString('Queue::', $code);
        $this->assertStringContainsString('dispatchAsyncJob', $code);
        $this->assertStringContainsString('ProcessMcpResult', $code);
    }

    #[Test]
    #[DataProvider('exampleTypeProvider')]
    public function it_detects_example_type_correctly(string $code, string $expectedType): void
    {
        // Act
        $result = $this->compiler->compile($code, $expectedType);

        // Assert
        $this->assertEquals($expectedType, $result['type']);
    }

    public static function exampleTypeProvider(): array
    {
        return [
            'tool example' => [
                '<?php class Test extends McpTool {}',
                'tool',
            ],
            'resource example' => [
                '<?php class Test extends McpResource {}',
                'resource',
            ],
            'prompt example' => [
                '<?php class Test extends McpPrompt {}',
                'prompt',
            ],
        ];
    }

    #[Test]
    public function it_handles_code_without_class(): void
    {
        // Arrange
        $code = '<?php echo "Hello World";';

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['class']);
    }

    #[Test]
    public function it_handles_code_without_namespace(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
class TestTool extends McpTool
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test'; }
    public function execute(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertNull($result['namespace']);
        $this->assertEquals('TestTool', $result['class']);
    }

    #[Test]
    public function it_accepts_fully_qualified_base_class(): void
    {
        // Arrange
        $code = <<<'PHP'
<?php
namespace App\Mcp\Tools;

class TestTool extends \JTD\LaravelMCP\Abstracts\McpTool
{
    public function getName(): string { return 'test'; }
    public function getDescription(): string { return 'Test'; }
    public function execute(array $parameters): array { return []; }
}
PHP;

        // Act
        $result = $this->compiler->compile($code, 'tool');

        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('TestTool', $result['class']);
    }
}
