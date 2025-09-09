<?php

/**
 * Test file for DocumentationGenerator comprehensive unit tests.
 * Tests documentation generation functionality for MCP server components.
 */

namespace Tests\Unit\Support;

// use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('support')]
#[Group('documentation-generator')]
#[Group('ticket-019')]
class DocumentationGeneratorTest extends TestCase
{
    protected DocumentationGenerator $generator;

    protected McpRegistry&MockObject $mockMcpRegistry;

    protected ToolRegistry&MockObject $mockToolRegistry;

    protected ResourceRegistry&MockObject $mockResourceRegistry;

    protected PromptRegistry&MockObject $mockPromptRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockMcpRegistry = $this->createMock(McpRegistry::class);
        $this->mockToolRegistry = $this->createMock(ToolRegistry::class);
        $this->mockResourceRegistry = $this->createMock(ResourceRegistry::class);
        $this->mockPromptRegistry = $this->createMock(PromptRegistry::class);

        $this->generator = new DocumentationGenerator(
            $this->mockMcpRegistry,
            $this->mockToolRegistry,
            $this->mockResourceRegistry,
            $this->mockPromptRegistry
        );
    }

    #[Test]
    public function it_generates_overview_with_default_options(): void
    {
        $this->setupEmptyRegistries();

        $overview = $this->generator->generateOverview();

        $this->assertStringContainsString('Laravel MCP Server', $overview);
        $this->assertStringContainsString('A Model Context Protocol server built with Laravel', $overview);
        $this->assertStringContainsString('1.0.0', $overview);
        $this->assertStringContainsString('**Tools:** 0', $overview);
        $this->assertStringContainsString('**Resources:** 0', $overview);
        $this->assertStringContainsString('**Prompts:** 0', $overview);
        $this->assertStringContainsString('**Total Components:** 0', $overview);
        $this->assertStringContainsString('## Features', $overview);
        $this->assertStringContainsString('## Quick Start', $overview);
    }

    #[Test]
    public function it_generates_overview_with_custom_options(): void
    {
        $this->setupEmptyRegistries();

        $options = [
            'name' => 'Custom MCP Server',
            'description' => 'A custom MCP implementation',
            'version' => '2.0.0',
        ];

        $overview = $this->generator->generateOverview($options);

        $this->assertStringContainsString('Custom MCP Server', $overview);
        $this->assertStringContainsString('A custom MCP implementation', $overview);
        $this->assertStringContainsString('2.0.0', $overview);
    }

    #[Test]
    public function it_generates_overview_with_populated_registries(): void
    {
        $this->setupPopulatedRegistries();

        $overview = $this->generator->generateOverview();

        $this->assertStringContainsString('**Tools:** 2', $overview);
        $this->assertStringContainsString('**Resources:** 1', $overview);
        $this->assertStringContainsString('**Prompts:** 1', $overview);
        $this->assertStringContainsString('**Total Components:** 4', $overview);
    }

    #[Test]
    public function it_generates_component_documentation(): void
    {
        $this->setupEmptyRegistries();

        $componentDocs = $this->generator->generateComponentDocumentation();

        $this->assertIsArray($componentDocs);
        $this->assertArrayHasKey('tools', $componentDocs);
        $this->assertArrayHasKey('resources', $componentDocs);
        $this->assertArrayHasKey('prompts', $componentDocs);
    }

    #[Test]
    public function it_generates_tools_documentation_when_empty(): void
    {
        $this->mockToolRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $toolsDocs = $this->generator->generateToolsDocumentation();

        $this->assertStringContainsString('# Tools', $toolsDocs);
        $this->assertStringContainsString('_No tools are currently registered._', $toolsDocs);
    }

    #[Test]
    public function it_generates_tools_documentation_with_populated_registry(): void
    {
        $mockTool = $this->createMock(\stdClass::class);

        $this->mockToolRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->mockToolRegistry
            ->expects($this->once())
            ->method('all')
            ->willReturn(['calculator' => $mockTool]);

        $this->mockToolRegistry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('calculator')
            ->willReturn([
                'description' => 'Performs arithmetic calculations',
                'parameters' => [
                    'a' => [
                        'type' => 'number',
                        'required' => true,
                        'description' => 'First operand',
                    ],
                    'b' => [
                        'type' => 'number',
                        'required' => true,
                        'description' => 'Second operand',
                    ],
                    'operation' => [
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Operation type',
                    ],
                ],
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'a' => ['type' => 'number'],
                        'b' => ['type' => 'number'],
                    ],
                ],
                'class' => 'App\\Mcp\\Tools\\CalculatorTool',
            ]);

        $toolsDocs = $this->generator->generateToolsDocumentation();

        $this->assertStringContainsString('# Tools', $toolsDocs);
        $this->assertStringContainsString('## calculator', $toolsDocs);
        $this->assertStringContainsString('Performs arithmetic calculations', $toolsDocs);
        $this->assertStringContainsString('### Parameters', $toolsDocs);
        $this->assertStringContainsString('- **a** (`number`) _(required)_: First operand', $toolsDocs);
        $this->assertStringContainsString('- **b** (`number`) _(required)_: Second operand', $toolsDocs);
        $this->assertStringContainsString('- **operation** (`string`) _(optional)_: Operation type', $toolsDocs);
        $this->assertStringContainsString('### Input Schema', $toolsDocs);
        $this->assertStringContainsString('**Class:** `App\\Mcp\\Tools\\CalculatorTool`', $toolsDocs);
    }

    #[Test]
    public function it_generates_resources_documentation_when_empty(): void
    {
        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $resourcesDocs = $this->generator->generateResourcesDocumentation();

        $this->assertStringContainsString('# Resources', $resourcesDocs);
        $this->assertStringContainsString('_No resources are currently registered._', $resourcesDocs);
    }

    #[Test]
    public function it_generates_resources_documentation_with_populated_registry(): void
    {
        $mockResource = $this->createMock(\stdClass::class);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('all')
            ->willReturn(['users' => $mockResource]);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('users')
            ->willReturn([
                'description' => 'Access user data from the database',
                'uri' => 'users://',
                'mime_type' => 'application/json',
                'annotations' => ['Readonly', 'Cached'],
                'class' => 'App\\Mcp\\Resources\\UserResource',
            ]);

        $resourcesDocs = $this->generator->generateResourcesDocumentation();

        $this->assertStringContainsString('# Resources', $resourcesDocs);
        $this->assertStringContainsString('## users', $resourcesDocs);
        $this->assertStringContainsString('Access user data from the database', $resourcesDocs);
        $this->assertStringContainsString('**URI:** `users://`', $resourcesDocs);
        $this->assertStringContainsString('**MIME Type:** `application/json`', $resourcesDocs);
        $this->assertStringContainsString('### Annotations', $resourcesDocs);
        $this->assertStringContainsString('- Readonly', $resourcesDocs);
        $this->assertStringContainsString('- Cached', $resourcesDocs);
        $this->assertStringContainsString('**Class:** `App\\Mcp\\Resources\\UserResource`', $resourcesDocs);
    }

    #[Test]
    public function it_generates_prompts_documentation_when_empty(): void
    {
        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $promptsDocs = $this->generator->generatePromptsDocumentation();

        $this->assertStringContainsString('# Prompts', $promptsDocs);
        $this->assertStringContainsString('_No prompts are currently registered._', $promptsDocs);
    }

    #[Test]
    public function it_generates_prompts_documentation_with_populated_registry(): void
    {
        $mockPrompt = $this->createMock(\stdClass::class);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('all')
            ->willReturn(['email_template' => $mockPrompt]);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('email_template')
            ->willReturn([
                'description' => 'Generate professional email templates',
                'arguments' => [
                    [
                        'name' => 'subject',
                        'type' => 'string',
                        'required' => true,
                        'description' => 'Email subject line',
                    ],
                    [
                        'name' => 'tone',
                        'type' => 'string',
                        'required' => false,
                        'description' => 'Email tone (formal, casual)',
                    ],
                ],
                'class' => 'App\\Mcp\\Prompts\\EmailTemplate',
            ]);

        $promptsDocs = $this->generator->generatePromptsDocumentation();

        $this->assertStringContainsString('# Prompts', $promptsDocs);
        $this->assertStringContainsString('## email_template', $promptsDocs);
        $this->assertStringContainsString('Generate professional email templates', $promptsDocs);
        $this->assertStringContainsString('### Arguments', $promptsDocs);
        $this->assertStringContainsString('- **subject** (`string`) _(required)_: Email subject line', $promptsDocs);
        $this->assertStringContainsString('- **tone** (`string`) _(optional)_: Email tone (formal, casual)', $promptsDocs);
        $this->assertStringContainsString('**Class:** `App\\Mcp\\Prompts\\EmailTemplate`', $promptsDocs);
    }

    #[Test]
    public function it_generates_api_reference(): void
    {
        $this->setupEmptyRegistries();

        $apiDocs = $this->generator->generateApiReference();

        $this->assertStringContainsString('# API Reference', $apiDocs);
        $this->assertStringContainsString('## Core Methods', $apiDocs);
        $this->assertStringContainsString('### initialize', $apiDocs);
        $this->assertStringContainsString('### ping', $apiDocs);
        $this->assertStringContainsString('"jsonrpc": "2.0"', $apiDocs);
        $this->assertStringContainsString('"protocolVersion": "2024-11-05"', $apiDocs);
    }

    #[Test]
    public function it_includes_tool_methods_in_api_reference_when_tools_exist(): void
    {
        $this->mockToolRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $apiDocs = $this->generator->generateApiReference();

        $this->assertStringContainsString('## Tool Methods', $apiDocs);
        $this->assertStringContainsString('### tools/list', $apiDocs);
        $this->assertStringContainsString('### tools/call', $apiDocs);
    }

    #[Test]
    public function it_includes_resource_methods_in_api_reference_when_resources_exist(): void
    {
        $this->mockToolRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $apiDocs = $this->generator->generateApiReference();

        $this->assertStringContainsString('## Resource Methods', $apiDocs);
        $this->assertStringContainsString('### resources/list', $apiDocs);
        $this->assertStringContainsString('### resources/read', $apiDocs);
        $this->assertStringContainsString('### resources/templates/list', $apiDocs);
    }

    #[Test]
    public function it_includes_prompt_methods_in_api_reference_when_prompts_exist(): void
    {
        $this->mockToolRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this->mockResourceRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $this->mockPromptRegistry
            ->expects($this->once())
            ->method('count')
            ->willReturn(1);

        $apiDocs = $this->generator->generateApiReference();

        $this->assertStringContainsString('## Prompt Methods', $apiDocs);
        $this->assertStringContainsString('### prompts/list', $apiDocs);
        $this->assertStringContainsString('### prompts/get', $apiDocs);
    }

    #[Test]
    public function it_generates_usage_guide(): void
    {
        $usageGuide = $this->generator->generateUsageGuide();

        $this->assertStringContainsString('# Usage Guide', $usageGuide);
        $this->assertStringContainsString('## Installation', $usageGuide);
        $this->assertStringContainsString('composer require jtd/laravel-mcp', $usageGuide);
        $this->assertStringContainsString('## Running the Server', $usageGuide);
        $this->assertStringContainsString('### Stdio Transport (Recommended)', $usageGuide);
        $this->assertStringContainsString('### HTTP Transport', $usageGuide);
        $this->assertStringContainsString('## Claude Desktop Integration', $usageGuide);
        $this->assertStringContainsString('## Development', $usageGuide);
        $this->assertStringContainsString('php artisan make:mcp-tool', $usageGuide);
        $this->assertStringContainsString('php artisan make:mcp-resource', $usageGuide);
        $this->assertStringContainsString('php artisan make:mcp-prompt', $usageGuide);
    }

    #[Test]
    public function it_generates_configuration_guide(): void
    {
        $configGuide = $this->generator->generateConfigurationGuide();

        $this->assertStringContainsString('# Configuration Guide', $configGuide);
        $this->assertStringContainsString('## Main Configuration', $configGuide);
        $this->assertStringContainsString('config/laravel-mcp.php', $configGuide);
        $this->assertStringContainsString('## Transport Configuration', $configGuide);
        $this->assertStringContainsString('config/mcp-transports.php', $configGuide);
        $this->assertStringContainsString('## Environment Variables', $configGuide);
        $this->assertStringContainsString('MCP_DEFAULT_TRANSPORT', $configGuide);
        $this->assertStringContainsString('MCP_HTTP_ENABLED', $configGuide);
    }

    #[Test]
    public function it_generates_examples(): void
    {
        $examples = $this->generator->generateExamples();

        $this->assertStringContainsString('# Examples', $examples);
        $this->assertStringContainsString('## Simple Tool Example', $examples);
        $this->assertStringContainsString('## Simple Resource Example', $examples);
        $this->assertStringContainsString('## Simple Prompt Example', $examples);
        $this->assertStringContainsString('class CalculatorTool extends McpTool', $examples);
        $this->assertStringContainsString('class UserResource extends McpResource', $examples);
        $this->assertStringContainsString('class EmailTemplate extends McpPrompt', $examples);
    }

    // Note: File system tests removed as they require Laravel environment
    // The saveDocumentation method would need integration testing in a Laravel context

    #[Test]
    public function it_generates_readme(): void
    {
        $this->setupEmptyRegistries();

        $readme = $this->generator->generateReadme();

        $this->assertStringContainsString('Laravel MCP Server', $readme);
        $this->assertStringContainsString('# Usage Guide', $readme);
        $this->assertStringContainsString('## Installation', $readme);
    }

    #[Test]
    public function it_generates_readme_with_custom_options(): void
    {
        $this->setupEmptyRegistries();

        $options = [
            'name' => 'My Custom Server',
            'description' => 'A custom implementation',
        ];

        $readme = $this->generator->generateReadme($options);

        $this->assertStringContainsString('My Custom Server', $readme);
        $this->assertStringContainsString('A custom implementation', $readme);
    }

    #[Test]
    public function it_generates_complete_documentation(): void
    {
        $this->setupEmptyRegistries();

        $documentation = $this->generator->generateCompleteDocumentation();

        $this->assertIsArray($documentation);
        $this->assertArrayHasKey('overview', $documentation);
        $this->assertArrayHasKey('components', $documentation);
        $this->assertArrayHasKey('api_reference', $documentation);
        $this->assertArrayHasKey('usage_guide', $documentation);
        $this->assertArrayHasKey('configuration', $documentation);
        $this->assertArrayHasKey('examples', $documentation);

        $this->assertIsArray($documentation['components']);
        $this->assertArrayHasKey('tools', $documentation['components']);
        $this->assertArrayHasKey('resources', $documentation['components']);
        $this->assertArrayHasKey('prompts', $documentation['components']);
    }

    #[Test]
    public function it_gets_and_sets_templates(): void
    {
        $templates = $this->generator->getTemplates();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('overview', $templates);
        $this->assertArrayHasKey('component', $templates);
        $this->assertArrayHasKey('method', $templates);

        $this->generator->setTemplate('custom', 'Custom template: {name}');

        $updatedTemplates = $this->generator->getTemplates();
        $this->assertArrayHasKey('custom', $updatedTemplates);
        $this->assertEquals('Custom template: {name}', $updatedTemplates['custom']);
    }

    protected function setupEmptyRegistries(): void
    {
        $this->mockToolRegistry->method('count')->willReturn(0);
        $this->mockResourceRegistry->method('count')->willReturn(0);
        $this->mockPromptRegistry->method('count')->willReturn(0);
        $this->mockMcpRegistry->method('count')->willReturn(0);
    }

    protected function setupPopulatedRegistries(): void
    {
        $this->mockToolRegistry->method('count')->willReturn(2);
        $this->mockResourceRegistry->method('count')->willReturn(1);
        $this->mockPromptRegistry->method('count')->willReturn(1);
        $this->mockMcpRegistry->method('count')->willReturn(4);
    }
}
