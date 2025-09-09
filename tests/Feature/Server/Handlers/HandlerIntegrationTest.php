<?php

namespace JTD\LaravelMCP\Tests\Feature\Server\Handlers;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Server\Handlers\PromptHandler;
use JTD\LaravelMCP\Server\Handlers\ResourceHandler;
use JTD\LaravelMCP\Server\Handlers\ToolHandler;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Integration tests for MCP Server Handler workflows.
 *
 * This test suite verifies end-to-end message handling workflows through
 * the handler system, testing real registry integration and component
 * interactions to ensure the complete MCP 1.0 compliance.
 *
 * @epic 009-McpServerHandlers
 *
 * @spec docs/Specs/009-McpServerHandlers.md
 *
 * @ticket 009-McpServerHandlers.md
 *
 * @sprint Sprint-2
 */
#[CoversNothing]
class HandlerIntegrationTest extends TestCase
{
    private ToolRegistry $toolRegistry;

    private ResourceRegistry $resourceRegistry;

    private PromptRegistry $promptRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = app(ToolRegistry::class);
        $this->resourceRegistry = app(ResourceRegistry::class);
        $this->promptRegistry = app(PromptRegistry::class);

        // Register test components
        $this->registerTestComponents();
    }

    #[Test]
    public function complete_tool_workflow_lists_and_executes_tools(): void
    {
        $toolHandler = new ToolHandler($this->toolRegistry, false);

        // Test tools/list operation
        $listResponse = $toolHandler->handle('tools/list', []);

        $this->assertArrayHasKey('tools', $listResponse);
        $this->assertCount(2, $listResponse['tools']); // calculator and sample tools

        // Find the calculator tool
        $calculatorTool = collect($listResponse['tools'])
            ->firstWhere('name', 'calculator');

        $this->assertNotNull($calculatorTool);
        $this->assertSame('Calculator Tool', $calculatorTool['description']);
        $this->assertArrayHasKey('inputSchema', $calculatorTool);
        $this->assertSame('object', $calculatorTool['inputSchema']['type']);

        // Test tools/call operation
        $callResponse = $toolHandler->handle('tools/call', [
            'name' => 'calculator',
            'arguments' => [
                'operation' => 'add',
                'operands' => [10, 5],
            ],
        ]);

        $this->assertArrayHasKey('content', $callResponse);
        $this->assertArrayHasKey('isError', $callResponse);
        $this->assertFalse($callResponse['isError']);
        $this->assertCount(1, $callResponse['content']);
        $this->assertSame('text', $callResponse['content'][0]['type']);
        $this->assertStringContainsString('15', $callResponse['content'][0]['text']);
    }

    #[Test]
    public function complete_resource_workflow_lists_and_reads_resources(): void
    {
        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);

        // Test resources/list operation
        $listResponse = $resourceHandler->handle('resources/list', []);

        $this->assertArrayHasKey('resources', $listResponse);
        $this->assertCount(1, $listResponse['resources']); // sample resource

        $sampleResource = $listResponse['resources'][0];
        $this->assertSame('sample-resource', $sampleResource['name']);
        $this->assertSame('resource://sample-resource', $sampleResource['uri']);
        $this->assertSame('Sample Resource', $sampleResource['description']);
        $this->assertSame('text/plain', $sampleResource['mimeType']);

        // Test resources/read operation
        $readResponse = $resourceHandler->handle('resources/read', [
            'uri' => 'resource://sample-resource',
        ]);

        $this->assertArrayHasKey('contents', $readResponse);
        $this->assertCount(1, $readResponse['contents']);
        $this->assertSame('text', $readResponse['contents'][0]['type']);
        $this->assertStringContainsString('Sample resource content', $readResponse['contents'][0]['text']);
    }

    #[Test]
    public function complete_prompt_workflow_lists_and_gets_prompts(): void
    {
        $promptHandler = new PromptHandler($this->promptRegistry, false);

        // Test prompts/list operation
        $listResponse = $promptHandler->handle('prompts/list', []);

        $this->assertArrayHasKey('prompts', $listResponse);
        $this->assertCount(2, $listResponse['prompts']); // sample and email prompts

        // Find the email template prompt
        $emailPrompt = collect($listResponse['prompts'])
            ->firstWhere('name', 'email-template');

        $this->assertNotNull($emailPrompt);
        $this->assertSame('Email Template Prompt', $emailPrompt['description']);
        $this->assertIsArray($emailPrompt['arguments']);
        $this->assertCount(2, $emailPrompt['arguments']);

        // Test prompts/get operation
        $getResponse = $promptHandler->handle('prompts/get', [
            'name' => 'email-template',
            'arguments' => [
                'recipient' => 'john@example.com',
                'subject' => 'Welcome',
            ],
        ]);

        $this->assertArrayHasKey('description', $getResponse);
        $this->assertArrayHasKey('messages', $getResponse);
        $this->assertSame('Email Template Prompt', $getResponse['description']);
        $this->assertIsArray($getResponse['messages']);
        $this->assertCount(1, $getResponse['messages']);
        $this->assertSame('user', $getResponse['messages'][0]['role']);
        $this->assertStringContainsString('john@example.com', $getResponse['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function tool_handler_manages_pagination_correctly(): void
    {
        // Register many tools to test pagination
        for ($i = 1; $i <= 100; $i++) {
            $tool = new class("tool-{$i}") extends McpTool
            {
                public function __construct(private string $toolName) {}

                public function getName(): string
                {
                    return $this->toolName;
                }

                public function getDescription(): string
                {
                    return "Tool {$this->toolName}";
                }

                public function getInputSchema(): array
                {
                    return ['type' => 'object'];
                }

                protected function handle(array $parameters): mixed
                {
                    return ['result' => 'ok'];
                }

                public function execute(array $arguments): array
                {
                    return $this->handle($arguments);
                }
            };
            $this->toolRegistry->register("tool-{$i}", $tool);
        }

        $toolHandler = new ToolHandler($this->toolRegistry, false);

        // Test first page with cursor
        $cursor = base64_encode(json_encode(['offset' => 0, 'limit' => 50]));
        $response = $toolHandler->handle('tools/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('tools', $response);
        $this->assertCount(50, $response['tools']);
        $this->assertArrayHasKey('nextCursor', $response);

        // Test second page
        $nextCursor = $response['nextCursor'];
        $response2 = $toolHandler->handle('tools/list', ['cursor' => $nextCursor]);

        $this->assertArrayHasKey('tools', $response2);
        $this->assertGreaterThan(0, count($response2['tools']));

        // Ensure different tools on different pages
        $firstPageNames = collect($response['tools'])->pluck('name')->toArray();
        $secondPageNames = collect($response2['tools'])->pluck('name')->toArray();
        $this->assertEmpty(array_intersect($firstPageNames, $secondPageNames));
    }

    #[Test]
    public function resource_handler_manages_pagination_correctly(): void
    {
        // Register many resources to test pagination
        for ($i = 1; $i <= 75; $i++) {
            $resource = new class("resource-{$i}") extends McpResource
            {
                public function __construct(private string $resourceName) {}

                public function getName(): string
                {
                    return $this->resourceName;
                }

                public function getUri(): string
                {
                    return "test://{$this->resourceName}";
                }

                public function getDescription(): string
                {
                    return "Resource {$this->resourceName}";
                }

                public function getMimeType(): string
                {
                    return 'text/plain';
                }

                public function read(array $options = []): array
                {
                    return ['content' => 'test'];
                }
            };
            $this->resourceRegistry->register("resource-{$i}", $resource);
        }

        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);

        // Test with cursor for pagination
        $cursor = base64_encode(json_encode(['offset' => 0, 'limit' => 30]));
        $response = $resourceHandler->handle('resources/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('resources', $response);
        $this->assertCount(30, $response['resources']);
        $this->assertArrayHasKey('nextCursor', $response);
    }

    #[Test]
    public function prompt_handler_manages_pagination_correctly(): void
    {
        // Register many prompts to test pagination
        for ($i = 1; $i <= 60; $i++) {
            $prompt = new class("prompt-{$i}") extends McpPrompt
            {
                public function __construct(private string $promptName) {}

                public function getName(): string
                {
                    return $this->promptName;
                }

                public function getDescription(): string
                {
                    return "Prompt {$this->promptName}";
                }

                public function getArguments(): array
                {
                    return [];
                }

                public function getMessages(array $arguments): array
                {
                    return [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'test']]]];
                }
            };
            $this->promptRegistry->register("prompt-{$i}", $prompt);
        }

        $promptHandler = new PromptHandler($this->promptRegistry, false);

        // Test with cursor for pagination
        $cursor = base64_encode(json_encode(['offset' => 10, 'limit' => 20]));
        $response = $promptHandler->handle('prompts/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('prompts', $response);
        $this->assertCount(20, $response['prompts']);
    }

    #[Test]
    public function handlers_handle_error_scenarios_gracefully(): void
    {
        $toolHandler = new ToolHandler($this->toolRegistry, false);
        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);
        $promptHandler = new PromptHandler($this->promptRegistry, false);

        // Test tool not found
        try {
            $toolHandler->handle('tools/call', ['name' => 'non-existent-tool']);
            $this->fail('Expected ProtocolException for non-existent tool');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Tool not found', $e->getMessage());
        }

        // Test resource not found
        try {
            $resourceHandler->handle('resources/read', ['uri' => 'non://existent']);
            $this->fail('Expected ProtocolException for non-existent resource');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Resource not found', $e->getMessage());
        }

        // Test prompt not found
        try {
            $promptHandler->handle('prompts/get', ['name' => 'non-existent-prompt']);
            $this->fail('Expected ProtocolException for non-existent prompt');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Prompt not found', $e->getMessage());
        }
    }

    #[Test]
    public function handlers_validate_request_parameters_correctly(): void
    {
        $toolHandler = new ToolHandler($this->toolRegistry, false);
        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);
        $promptHandler = new PromptHandler($this->promptRegistry, false);

        // Test invalid tool call parameters
        try {
            $toolHandler->handle('tools/call', ['name' => 123]); // name should be string
            $this->fail('Expected validation error for invalid tool name type');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid parameters', $e->getMessage());
        }

        // Test invalid resource read parameters
        try {
            $resourceHandler->handle('resources/read', ['uri' => ['invalid']]); // uri should be string
            $this->fail('Expected validation error for invalid resource uri type');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid parameters', $e->getMessage());
        }

        // Test invalid prompt get parameters
        try {
            $promptHandler->handle('prompts/get', ['name' => null]); // name should be string
            $this->fail('Expected validation error for invalid prompt name');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid parameters', $e->getMessage());
        }
    }

    #[Test]
    public function handlers_return_proper_mcp_response_format(): void
    {
        $toolHandler = new ToolHandler($this->toolRegistry, false);
        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);
        $promptHandler = new PromptHandler($this->promptRegistry, false);

        // Test tool response format
        $toolResponse = $toolHandler->handle('tools/list', []);
        $this->assertArrayHasKey('tools', $toolResponse);
        $this->assertIsArray($toolResponse['tools']);
        foreach ($toolResponse['tools'] as $tool) {
            $this->assertArrayHasKey('name', $tool);
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
        }

        // Test resource response format
        $resourceResponse = $resourceHandler->handle('resources/list', []);
        $this->assertArrayHasKey('resources', $resourceResponse);
        $this->assertIsArray($resourceResponse['resources']);
        foreach ($resourceResponse['resources'] as $resource) {
            $this->assertArrayHasKey('uri', $resource);
            $this->assertArrayHasKey('name', $resource);
            $this->assertArrayHasKey('description', $resource);
            $this->assertArrayHasKey('mimeType', $resource);
        }

        // Test prompt response format
        $promptResponse = $promptHandler->handle('prompts/list', []);
        $this->assertArrayHasKey('prompts', $promptResponse);
        $this->assertIsArray($promptResponse['prompts']);
        foreach ($promptResponse['prompts'] as $prompt) {
            $this->assertArrayHasKey('name', $prompt);
            $this->assertArrayHasKey('description', $prompt);
            $this->assertArrayHasKey('arguments', $prompt);
        }
    }

    #[Test]
    public function tool_execution_handles_different_result_types(): void
    {
        // Register tools that return different types
        $stringTool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'string-tool';
            }

            public function getDescription(): string
            {
                return 'Returns string';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            protected function handle(array $parameters): mixed
            {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'string result',
                        ],
                    ],
                ];
            }

            public function execute(array $arguments): array
            {
                return $this->handle($arguments);
            }
        };

        $arrayTool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'array-tool';
            }

            public function getDescription(): string
            {
                return 'Returns array';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            protected function handle(array $parameters): mixed
            {
                return ['key' => 'value', 'count' => 42];
            }

            public function execute(array $arguments): array
            {
                return $this->handle($arguments);
            }
        };

        $this->toolRegistry->register('string-tool', $stringTool);
        $this->toolRegistry->register('array-tool', $arrayTool);

        $toolHandler = new ToolHandler($this->toolRegistry, false);

        // Test string result
        $stringResponse = $toolHandler->handle('tools/call', [
            'name' => 'string-tool',
            'arguments' => [],
        ]);

        $this->assertFalse($stringResponse['isError']);
        $this->assertSame('text', $stringResponse['content'][0]['type']);
        $this->assertSame('string result', $stringResponse['content'][0]['text']);

        // Test array result (should be JSON-formatted)
        $arrayResponse = $toolHandler->handle('tools/call', [
            'name' => 'array-tool',
            'arguments' => [],
        ]);

        $this->assertFalse($arrayResponse['isError']);
        $this->assertSame('text', $arrayResponse['content'][0]['type']);
        $this->assertStringContainsString('key', $arrayResponse['content'][0]['text']);
        $this->assertStringContainsString('value', $arrayResponse['content'][0]['text']);
    }

    #[Test]
    public function resource_reading_handles_different_content_types(): void
    {
        // Register resources that return different content types
        $textResource = new class extends McpResource
        {
            public function getName(): string
            {
                return 'text-resource';
            }

            public function getUri(): string
            {
                return 'test://text';
            }

            public function getDescription(): string
            {
                return 'Text resource';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function read(array $options = []): array
            {
                return [
                    'contents' => [
                        [
                            'uri' => $this->getUri(),
                            'mimeType' => $this->getMimeType(),
                            'text' => 'Plain text content',
                        ],
                    ],
                ];
            }
        };

        $jsonResource = new class extends McpResource
        {
            public function getName(): string
            {
                return 'json-resource';
            }

            public function getUri(): string
            {
                return 'test://json';
            }

            public function getDescription(): string
            {
                return 'JSON resource';
            }

            public function getMimeType(): string
            {
                return 'application/json';
            }

            public function read(array $options = []): array
            {
                return ['data' => 'value', 'active' => true];
            }
        };

        $this->resourceRegistry->register('text-resource', $textResource);
        $this->resourceRegistry->register('json-resource', $jsonResource);

        $resourceHandler = new ResourceHandler($this->resourceRegistry, false);

        // Test text content
        $textResponse = $resourceHandler->handle('resources/read', [
            'uri' => 'test://text',
        ]);

        $this->assertCount(1, $textResponse['contents']);
        $this->assertSame('text', $textResponse['contents'][0]['type']);
        $this->assertSame('Plain text content', $textResponse['contents'][0]['text']);

        // Test JSON content
        $jsonResponse = $resourceHandler->handle('resources/read', [
            'uri' => 'test://json',
        ]);

        $this->assertCount(1, $jsonResponse['contents']);
        $this->assertSame('text', $jsonResponse['contents'][0]['type']);
        $this->assertStringContainsString('data', $jsonResponse['contents'][0]['text']);
        $this->assertStringContainsString('value', $jsonResponse['contents'][0]['text']);
    }

    private function registerTestComponents(): void
    {
        // Register calculator tool
        $calculatorTool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'calculator';
            }

            public function getDescription(): string
            {
                return 'Calculator Tool';
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [
                        'operation' => ['type' => 'string', 'enum' => ['add', 'subtract', 'multiply', 'divide']],
                        'operands' => ['type' => 'array', 'items' => ['type' => 'number']],
                    ],
                    'required' => ['operation', 'operands'],
                ];
            }

            protected function handle(array $parameters): mixed
            {
                $operation = $parameters['operation'];
                $operands = $parameters['operands'];

                $result = match ($operation) {
                    'add' => array_sum($operands),
                    'subtract' => $operands[0] - $operands[1],
                    'multiply' => array_product($operands),
                    'divide' => $operands[0] / $operands[1],
                    default => throw new \InvalidArgumentException('Unknown operation')
                };

                return ['result' => $result];
            }

            public function execute(array $arguments): array
            {
                return $this->handle($arguments);
            }
        };

        // Register sample tool
        $sampleTool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'sample-tool';
            }

            public function getDescription(): string
            {
                return 'Sample Tool';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            protected function handle(array $parameters): mixed
            {
                return ['sample' => 'response'];
            }

            public function execute(array $arguments): array
            {
                return $this->handle($arguments);
            }
        };

        // Register sample resource
        $sampleResource = new class extends McpResource
        {
            public function getName(): string
            {
                return 'sample-resource';
            }

            public function getUri(): string
            {
                return 'resource://sample-resource';
            }

            public function getDescription(): string
            {
                return 'Sample Resource';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function read(array $options = []): array
            {
                return ['Sample resource content with data: '.json_encode($options)];
            }
        };

        // Register sample prompt
        $samplePrompt = new class extends McpPrompt
        {
            public function getName(): string
            {
                return 'sample-prompt';
            }

            public function getDescription(): string
            {
                return 'Sample Prompt';
            }

            public function getArguments(): array
            {
                return [];
            }

            public function getMessages(array $arguments): array
            {
                return [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Sample prompt']]]];
            }
        };

        // Register email template prompt
        $emailPrompt = new class extends McpPrompt
        {
            public function getName(): string
            {
                return 'email-template';
            }

            public function getDescription(): string
            {
                return 'Email Template Prompt';
            }

            public function getArguments(): array
            {
                return [
                    ['name' => 'recipient', 'description' => 'Email recipient', 'required' => true],
                    ['name' => 'subject', 'description' => 'Email subject', 'required' => true],
                ];
            }

            public function getMessages(array $arguments): array
            {
                $recipient = $arguments['recipient'] ?? 'unknown';
                $subject = $arguments['subject'] ?? 'No Subject';

                return [[
                    'role' => 'user',
                    'content' => [[
                        'type' => 'text',
                        'text' => "Write an email to {$recipient} with subject '{$subject}'",
                    ]],
                ]];
            }
        };

        $this->toolRegistry->register('calculator', $calculatorTool);
        $this->toolRegistry->register('sample-tool', $sampleTool);
        $this->resourceRegistry->register('sample-resource', $sampleResource);
        $this->promptRegistry->register('sample-prompt', $samplePrompt);
        $this->promptRegistry->register('email-template', $emailPrompt);
    }
}
