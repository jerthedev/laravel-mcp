<?php

/**
 * EPIC: DOCUMENTATION-025
 * SPEC: 11-Documentation.md
 * SPRINT: Implementation Phase
 * TICKET: Advanced Documentation Features - Extension Guide Builder
 *
 * Comprehensive unit tests for ExtensionGuideBuilder.
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\ExtensionGuideBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExtensionGuideBuilder::class)]
#[Group('unit')]
#[Group('support')]
#[Group('documentation')]
#[Group('extension-guide')]
#[Group('ticket-025')]
class ExtensionGuideBuilderTest extends TestCase
{
    protected ExtensionGuideBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ExtensionGuideBuilder;
    }

    #[Test]
    public function it_builds_complete_extension_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertIsString($guide);
        $this->assertStringContainsString('# Extending Laravel MCP', $guide);
        $this->assertStringContainsString('## Overview', $guide);
        $this->assertStringContainsString('## Extension Points', $guide);
        $this->assertStringContainsString('## Creating Custom Tools', $guide);
        $this->assertStringContainsString('## Creating Custom Resources', $guide);
        $this->assertStringContainsString('## Creating Custom Prompts', $guide);
        $this->assertStringContainsString('## Creating Custom Transports', $guide);
        $this->assertStringContainsString('## Adding Custom Middleware', $guide);
        $this->assertStringContainsString('## Creating Event Listeners', $guide);
        $this->assertStringContainsString('## Creating MCP Extension Packages', $guide);
    }

    #[Test]
    public function it_includes_extension_points_overview(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('1. **Custom Tools**', $guide);
        $this->assertStringContainsString('2. **Custom Resources**', $guide);
        $this->assertStringContainsString('3. **Custom Prompts**', $guide);
        $this->assertStringContainsString('4. **Transport Layers**', $guide);
        $this->assertStringContainsString('5. **Middleware**', $guide);
        $this->assertStringContainsString('6. **Event Listeners**', $guide);
        $this->assertStringContainsString('7. **Package Extensions**', $guide);
    }

    #[Test]
    public function it_includes_custom_tool_creation_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Step 1: Generate Tool Class', $guide);
        $this->assertStringContainsString('php artisan make:mcp-tool', $guide);
        $this->assertStringContainsString('### Step 2: Implement Tool Logic', $guide);
        $this->assertStringContainsString('class MyCustomTool extends McpTool', $guide);
        $this->assertStringContainsString('### Step 3: Register Tool (Optional)', $guide);
        $this->assertStringContainsString('Mcp::registerTool', $guide);
    }

    #[Test]
    public function it_includes_custom_resource_creation_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Resource with Dynamic URIs', $guide);
        $this->assertStringContainsString('getUriTemplates', $guide);
        $this->assertStringContainsString('### Streaming Resource', $guide);
        $this->assertStringContainsString('supportsStreaming', $guide);
        $this->assertStringContainsString('Generator', $guide);
    }

    #[Test]
    public function it_includes_custom_prompt_creation_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Dynamic Prompt with Context', $guide);
        $this->assertStringContainsString('gatherContext', $guide);
        $this->assertStringContainsString('### Multi-Modal Prompt', $guide);
        $this->assertStringContainsString('type\' => \'image', $guide);
        $this->assertStringContainsString('base64_encode', $guide);
    }

    #[Test]
    public function it_includes_transport_extension_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Implementing Transport Interface', $guide);
        $this->assertStringContainsString('implements TransportInterface', $guide);
        $this->assertStringContainsString('### Registering Custom Transport', $guide);
        $this->assertStringContainsString('TransportManager', $guide);
        $this->assertStringContainsString('$manager->extend', $guide);
    }

    #[Test]
    public function it_includes_middleware_integration_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Request Middleware', $guide);
        $this->assertStringContainsString('ValidateApiKeyMiddleware', $guide);
        $this->assertStringContainsString('### Registering Middleware', $guide);
        $this->assertStringContainsString('config/laravel-mcp.php', $guide);
    }

    #[Test]
    public function it_includes_event_listeners_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Available Events', $guide);
        $this->assertStringContainsString('McpInitialized', $guide);
        $this->assertStringContainsString('McpRequestReceived', $guide);
        $this->assertStringContainsString('McpToolExecuted', $guide);
        $this->assertStringContainsString('### Creating a Listener', $guide);
        $this->assertStringContainsString('LogToolExecution', $guide);
        $this->assertStringContainsString('### Registering Listeners', $guide);
        $this->assertStringContainsString('EventServiceProvider', $guide);
    }

    #[Test]
    public function it_includes_package_extension_guide(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('### Package Structure', $guide);
        $this->assertStringContainsString('my-mcp-extension/', $guide);
        $this->assertStringContainsString('### Service Provider', $guide);
        $this->assertStringContainsString('MyMcpExtensionServiceProvider', $guide);
        $this->assertStringContainsString('### Composer Configuration', $guide);
        $this->assertStringContainsString('extra', $guide);
        $this->assertStringContainsString('laravel', $guide);
    }

    #[Test]
    public function it_validates_valid_guide(): void
    {
        // Arrange
        $guide = $this->builder->buildGuide();

        // Act
        $validation = $this->builder->validateGuide($guide);

        // Assert
        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertEmpty($validation['warnings']);
    }

    #[Test]
    public function it_detects_missing_sections_in_guide(): void
    {
        // Arrange
        $incompleteGuide = "# Extending Laravel MCP\n## Overview\nSome content here.";

        // Act
        $validation = $this->builder->validateGuide($incompleteGuide);

        // Assert
        $this->assertTrue($validation['valid']); // Still valid but with warnings
        $this->assertNotEmpty($validation['warnings']);
        $this->assertContains('Missing section: ## Creating Custom Tools', $validation['warnings']);
        $this->assertContains('Missing section: ## Creating Custom Resources', $validation['warnings']);
        $this->assertContains('Missing section: ## Creating Custom Prompts', $validation['warnings']);
    }

    #[Test]
    public function it_detects_insufficient_code_examples(): void
    {
        // Arrange
        $guideWithFewExamples = "# Guide\n```php\ncode\n```\n```php\nmore code\n```";

        // Act
        $validation = $this->builder->validateGuide($guideWithFewExamples);

        // Assert
        $this->assertContains('Guide should contain more PHP code examples', $validation['warnings']);
    }

    #[Test]
    public function it_detects_missing_command_examples(): void
    {
        // Arrange
        $guideWithoutCommands = "# Guide\n```php\ncode\n```";

        // Act
        $validation = $this->builder->validateGuide($guideWithoutCommands);

        // Assert
        $this->assertContains('Guide should contain command-line examples', $validation['warnings']);
    }

    #[Test]
    public function it_detects_unclosed_code_blocks(): void
    {
        // Arrange
        $guideWithUnclosedBlock = "# Guide\n```php\ncode without closing";

        // Act
        $validation = $this->builder->validateGuide($guideWithUnclosedBlock);

        // Assert
        $this->assertFalse($validation['valid']);
        $this->assertContains('Unclosed code block detected', $validation['errors']);
    }

    #[Test]
    #[DataProvider('extensionTemplateProvider')]
    public function it_generates_extension_templates(string $type, string $name, array $expectations): void
    {
        // Act
        $template = $this->builder->generateExtensionTemplate($type, $name);

        // Assert
        $this->assertIsString($template);
        $this->assertStringStartsWith('<?php', $template);

        foreach ($expectations as $expected) {
            $this->assertStringContainsString($expected, $template);
        }
    }

    public static function extensionTemplateProvider(): array
    {
        return [
            'tool template' => [
                'tool',
                'calculator',
                [
                    'namespace App\\Mcp\\Tools;',
                    'class Calculator extends McpTool',
                    'public function getName(): string',
                    'public function getDescription(): string',
                    'public function getInputSchema(): array',
                    'public function execute(array $parameters): array',
                ],
            ],
            'resource template' => [
                'resource',
                'database',
                [
                    'namespace App\\Mcp\\Resources;',
                    'class Database extends McpResource',
                    'public function getName(): string',
                    'public function getDescription(): string',
                    'public function getUri(): string',
                    'public function read(array $parameters): array',
                ],
            ],
            'prompt template' => [
                'prompt',
                'email_writer',
                [
                    'namespace App\\Mcp\\Prompts;',
                    'class EmailWriter extends McpPrompt',
                    'public function getName(): string',
                    'public function getDescription(): string',
                    'public function getArguments(): array',
                    'public function render(array $arguments): array',
                ],
            ],
        ];
    }

    #[Test]
    public function it_generates_tool_template_with_proper_structure(): void
    {
        // Act
        $template = $this->builder->generateExtensionTemplate('tool', 'test_tool');

        // Assert
        $this->assertStringContainsString('class TestTool extends McpTool', $template);
        $this->assertStringContainsString('return Str::snake(class_basename($this));', $template);
        $this->assertStringContainsString("'type' => 'object'", $template);
        $this->assertStringContainsString("'properties' => [", $template);
        $this->assertStringContainsString("'required' => []", $template);
        $this->assertStringContainsString("'success' => true", $template);
    }

    #[Test]
    public function it_generates_resource_template_with_proper_structure(): void
    {
        // Act
        $template = $this->builder->generateExtensionTemplate('resource', 'custom_resource');

        // Assert
        $this->assertStringContainsString('class CustomResource extends McpResource', $template);
        $this->assertStringContainsString("return 'custom://' . \$this->getName();", $template);
        $this->assertStringContainsString("'content' => 'Resource content'", $template);
        $this->assertStringContainsString("'metadata' => []", $template);
    }

    #[Test]
    public function it_generates_prompt_template_with_proper_structure(): void
    {
        // Act
        $template = $this->builder->generateExtensionTemplate('prompt', 'ai_assistant');

        // Assert
        $this->assertStringContainsString('class AiAssistant extends McpPrompt', $template);
        $this->assertStringContainsString("'name' => 'example_arg'", $template);
        $this->assertStringContainsString("'type' => 'string'", $template);
        $this->assertStringContainsString("'required' => false", $template);
        $this->assertStringContainsString("'messages' => [", $template);
        $this->assertStringContainsString("'role' => 'user'", $template);
    }

    #[Test]
    public function it_throws_exception_for_unknown_template_type(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown type: invalid');

        // Act
        $this->builder->generateExtensionTemplate('invalid', 'test');
    }

    #[Test]
    public function it_includes_all_transport_interface_methods(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('public function initialize(array $config = []): void', $guide);
        $this->assertStringContainsString('public function start(): void', $guide);
        $this->assertStringContainsString('public function stop(): void', $guide);
        $this->assertStringContainsString('public function send(array $message): void', $guide);
        $this->assertStringContainsString('public function receive(): ?array', $guide);
        $this->assertStringContainsString('public function isRunning(): bool', $guide);
    }

    #[Test]
    public function it_includes_code_examples_for_each_section(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $codeBlockCount = substr_count($guide, '```php');
        $this->assertGreaterThanOrEqual(10, $codeBlockCount, 'Guide should contain at least 10 PHP code examples');

        $bashBlockCount = substr_count($guide, '```bash');
        $this->assertGreaterThanOrEqual(1, $bashBlockCount, 'Guide should contain at least 1 bash example');

        $jsonBlockCount = substr_count($guide, '```json');
        $this->assertGreaterThanOrEqual(1, $jsonBlockCount, 'Guide should contain at least 1 JSON example');
    }

    #[Test]
    public function it_includes_package_structure_example(): void
    {
        // Act
        $guide = $this->builder->buildGuide();

        // Assert
        $this->assertStringContainsString('├── src/', $guide);
        $this->assertStringContainsString('│   ├── Tools/', $guide);
        $this->assertStringContainsString('│   ├── Resources/', $guide);
        $this->assertStringContainsString('│   ├── Prompts/', $guide);
        $this->assertStringContainsString('├── config/', $guide);
        $this->assertStringContainsString('├── tests/', $guide);
        $this->assertStringContainsString('└── composer.json', $guide);
    }
}
