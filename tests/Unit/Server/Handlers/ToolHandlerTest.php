<?php

namespace JTD\LaravelMCP\Tests\Unit\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Server\Handlers\ToolHandler;
use Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for ToolHandler class.
 *
 * This test suite ensures the ToolHandler properly handles tool-related MCP operations,
 * including tools/list and tools/call methods, with proper validation, error handling,
 * and MCP 1.0 compliance.
 *
 * @epic 009-McpServerHandlers
 *
 * @spec docs/Specs/009-McpServerHandlers.md
 *
 * @ticket 009-McpServerHandlers.md
 *
 * @sprint Sprint-2
 */
#[CoversClass(ToolHandler::class)]
class ToolHandlerTest extends TestCase
{
    private ToolRegistry $toolRegistry;

    private ToolHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = Mockery::mock(ToolRegistry::class);
        $this->handler = new ToolHandler($this->toolRegistry, false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructor_sets_dependencies_and_handler_name(): void
    {
        $handler = new ToolHandler($this->toolRegistry, true);

        $this->assertTrue($handler->isDebug());
        $this->assertSame('ToolHandler', $handler->getHandlerName());
    }

    #[Test]
    public function get_supported_methods_returns_tool_methods(): void
    {
        $expected = ['tools/list', 'tools/call'];

        $this->assertSame($expected, $this->handler->getSupportedMethods());
    }

    #[Test]
    public function supports_method_returns_true_for_supported_methods(): void
    {
        $this->assertTrue($this->handler->supportsMethod('tools/list'));
        $this->assertTrue($this->handler->supportsMethod('tools/call'));
    }

    #[Test]
    public function supports_method_returns_false_for_unsupported_methods(): void
    {
        $this->assertFalse($this->handler->supportsMethod('tools/unsupported'));
        $this->assertFalse($this->handler->supportsMethod('resources/list'));
    }

    #[Test]
    public function handle_throws_protocol_exception_for_unsupported_method(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Unsupported method: tools/unsupported');

        $this->handler->handle('tools/unsupported', []);
    }

    #[Test]
    public function handle_tools_list_returns_empty_tools_array_when_no_tools(): void
    {
        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([]);

        $response = $this->handler->handle('tools/list', []);

        $this->assertArrayHasKey('tools', $response);
        $this->assertIsArray($response['tools']);
        $this->assertEmpty($response['tools']);
        $this->assertArrayNotHasKey('nextCursor', $response);
    }

    #[Test]
    public function handle_tools_list_returns_tool_definitions(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('getDescription')->andReturn('Test tool description');
        $mockTool->shouldReceive('getInputSchema')->andReturn([
            'type' => 'object',
            'properties' => ['input' => ['type' => 'string']],
        ]);

        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-tool' => ['handler' => $mockTool, 'options' => []]]);

        $response = $this->handler->handle('tools/list', []);

        $this->assertArrayHasKey('tools', $response);
        $this->assertCount(1, $response['tools']);

        $toolDef = $response['tools'][0];
        $this->assertSame('test-tool', $toolDef['name']);
        $this->assertSame('Test tool description', $toolDef['description']);
        $this->assertSame([
            'type' => 'object',
            'properties' => ['input' => ['type' => 'string']],
        ], $toolDef['inputSchema']);
    }

    #[Test]
    public function handle_tools_list_handles_tool_definition_failures_gracefully(): void
    {
        $goodTool = Mockery::mock();
        $goodTool->shouldReceive('getDescription')->andReturn('Good tool');
        $goodTool->shouldReceive('getInputSchema')->andReturn(['type' => 'object']);

        $badTool = new class
        {
            public function getDescription()
            {
                throw new \RuntimeException('Bad tool');
            }
        };

        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'good-tool' => ['handler' => $goodTool, 'options' => []],
                'bad-tool' => ['handler' => $badTool, 'options' => []],
            ]);

        $response = $this->handler->handle('tools/list', []);

        $this->assertArrayHasKey('tools', $response);
        $this->assertCount(2, $response['tools']); // Both tools included, bad tool with fallback values

        // Check good tool
        $goodToolDef = $response['tools'][0];
        $this->assertSame('good-tool', $goodToolDef['name']);
        $this->assertSame('Good tool', $goodToolDef['description']);
        $this->assertSame(['type' => 'object'], $goodToolDef['inputSchema']);

        // Check bad tool with fallback values
        $badToolDef = $response['tools'][1];
        $this->assertSame('bad-tool', $badToolDef['name']);
        $this->assertStringContainsString('Tool: class@anonymous', $badToolDef['description']); // Fallback description
        $this->assertSame(['type' => 'object', 'properties' => [], 'additionalProperties' => true], $badToolDef['inputSchema']); // Default schema
    }

    #[Test]
    public function handle_tools_list_validates_cursor_parameter(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('tools/list', ['cursor' => 123]); // Should be string
    }

    #[Test]
    public function handle_tools_list_applies_cursor_pagination(): void
    {
        $mockTool1 = $this->createMockTool('Tool 1');
        $mockTool2 = $this->createMockTool('Tool 2');
        $mockTool3 = $this->createMockTool('Tool 3');

        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'tool1' => ['handler' => $mockTool1, 'options' => []],
                'tool2' => ['handler' => $mockTool2, 'options' => []],
                'tool3' => ['handler' => $mockTool3, 'options' => []],
            ]);

        // Create cursor for pagination (skip first 1, limit 1)
        $cursor = base64_encode(json_encode(['offset' => 1, 'limit' => 1]));

        $response = $this->handler->handle('tools/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('tools', $response);
        $this->assertCount(1, $response['tools']);
        $this->assertSame('tool2', $response['tools'][0]['name']); // Second tool
    }

    #[Test]
    public function handle_tools_list_includes_next_cursor_when_more_tools_available(): void
    {
        $tools = [];
        for ($i = 1; $i <= 60; $i++) {
            $tools["tool{$i}"] = ['handler' => $this->createMockTool("Tool {$i}"), 'options' => []];
        }

        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn($tools);

        $cursor = base64_encode(json_encode(['offset' => 0, 'limit' => 50]));

        $response = $this->handler->handle('tools/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('nextCursor', $response);
        $this->assertIsString($response['nextCursor']);

        $decodedCursor = json_decode(base64_decode($response['nextCursor']), true);
        $this->assertSame(50, $decodedCursor['offset']);
        $this->assertSame(50, $decodedCursor['limit']);
    }

    #[Test]
    public function handle_tools_call_validates_required_parameters(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('tools/call', []); // Missing 'name' parameter
    }

    #[Test]
    public function handle_tools_call_validates_parameter_types(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('tools/call', ['name' => 123]); // name should be string
    }

    #[Test]
    public function handle_tools_call_throws_error_for_non_existent_tool(): void
    {
        $this->toolRegistry
            ->shouldReceive('has')
            ->with('non-existent-tool')
            ->once()
            ->andReturn(false);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Tool not found: non-existent-tool');

        $this->handler->handle('tools/call', ['name' => 'non-existent-tool']);
    }

    #[Test]
    public function handle_tools_call_executes_tool_with_execute_method(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->with(['input' => 'test'])
            ->once()
            ->andReturn('Tool executed successfully');

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('test-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('test-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'test-tool',
            'arguments' => ['input' => 'test'],
        ]);

        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('isError', $response);
        $this->assertFalse($response['isError']);
        $this->assertCount(1, $response['content']);
        $this->assertSame('text', $response['content'][0]['type']);
        $this->assertSame('Tool executed successfully', $response['content'][0]['text']);
    }

    #[Test]
    public function handle_tools_call_executes_tool_with_invoke_method(): void
    {
        $mockTool = new class
        {
            public function execute(array $arguments)
            {
                throw new \BadMethodCallException('Method not found');
            }

            public function __invoke(array $arguments)
            {
                return ['result' => 'success'];
            }
        };

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('test-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('test-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'test-tool',
            'arguments' => ['input' => 'test'],
        ]);

        $this->assertFalse($response['isError']);
        $this->assertSame('text', $response['content'][0]['type']);
        $this->assertStringContainsString('success', $response['content'][0]['text']);
    }

    #[Test]
    public function handle_tools_call_executes_callable_tool(): void
    {
        $callableTool = function ($args) {
            return 'Callable result: '.$args['input'];
        };

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('callable-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('callable-tool')
            ->once()
            ->andReturn(['handler' => $callableTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'callable-tool',
            'arguments' => ['input' => 'test'],
        ]);

        $this->assertFalse($response['isError']);
        $this->assertSame('Callable result: test', $response['content'][0]['text']);
    }

    #[Test]
    public function handle_tools_call_throws_error_for_non_executable_tool(): void
    {
        $nonExecutableTool = new \stdClass;

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('non-executable')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('non-executable')
            ->once()
            ->andReturn(['handler' => $nonExecutableTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'non-executable',
            'arguments' => [],
        ]);

        $this->assertTrue($response['isError']);
        $this->assertStringContainsString('Tool execution failed', $response['content'][0]['text']);
    }

    #[Test]
    public function handle_tools_call_validates_arguments_if_tool_supports_it(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('validateArguments')
            ->with(['invalid' => 'args'])
            ->once()
            ->andReturn(false);

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('validating-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('validating-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);
        $this->expectExceptionMessage('Invalid arguments for tool: validating-tool');

        $this->handler->handle('tools/call', [
            'name' => 'validating-tool',
            'arguments' => ['invalid' => 'args'],
        ]);
    }

    #[Test]
    public function handle_tools_call_skips_validation_if_tool_does_not_support_it(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->with(['any' => 'args'])
            ->once()
            ->andReturn('Success');

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('non-validating-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('non-validating-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'non-validating-tool',
            'arguments' => ['any' => 'args'],
        ]);

        $this->assertFalse($response['isError']);
    }

    #[Test]
    public function handle_tools_call_returns_error_response_for_execution_failures(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->andThrow(new \RuntimeException('Execution failed'));

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('failing-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('failing-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'failing-tool',
            'arguments' => [],
        ]);

        $this->assertTrue($response['isError']);
        $this->assertStringContainsString('Tool execution failed: Execution failed', $response['content'][0]['text']);
    }

    #[Test]
    public function handle_tools_call_uses_default_empty_arguments_when_not_provided(): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->with([])  // Empty arguments
            ->once()
            ->andReturn('Success');

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('test-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('test-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'test-tool',
            // No 'arguments' key
        ]);

        $this->assertFalse($response['isError']);
    }

    #[Test]
    #[DataProvider('toolDescriptionProvider')]
    public function get_tool_description_handles_different_description_methods($tool, string $expectedDescription): void
    {
        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-tool' => ['handler' => $tool, 'options' => []]]);

        $response = $this->handler->handle('tools/list', []);

        $this->assertSame($expectedDescription, $response['tools'][0]['description']);
    }

    public static function toolDescriptionProvider(): array
    {
        $toolWithGetDescription = Mockery::mock();
        $toolWithGetDescription->shouldReceive('getDescription')->andReturn('From getDescription');
        $toolWithGetDescription->shouldReceive('getInputSchema')->andReturn(['type' => 'object']);

        $toolWithDescriptionMethod = Mockery::mock();
        $toolWithDescriptionMethod->shouldReceive('description')->andReturn('From description method');
        $toolWithDescriptionMethod->shouldReceive('getInputSchema')->andReturn(['type' => 'object']);

        $toolWithDescriptionProperty = new class
        {
            public string $description = 'From description property';

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }
        };

        $toolWithoutDescription = Mockery::mock();
        $toolWithoutDescription->shouldReceive('getInputSchema')->andReturn(['type' => 'object']);
        $className = get_class($toolWithoutDescription);

        return [
            'getDescription method' => [$toolWithGetDescription, 'From getDescription'],
            'description method' => [$toolWithDescriptionMethod, 'From description method'],
            'description property' => [$toolWithDescriptionProperty, 'From description property'],
            'no description' => [$toolWithoutDescription, "Tool: {$className}"],
        ];
    }

    #[Test]
    #[DataProvider('toolInputSchemaProvider')]
    public function get_tool_input_schema_handles_different_schema_methods($tool, array $expectedSchema): void
    {
        // Mock the description method for the tool since it's required
        if (method_exists($tool, 'shouldReceive')) {
            $tool->shouldReceive('getDescription')->andReturn('Test tool');
        }

        $this->toolRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-tool' => ['handler' => $tool, 'options' => []]]);

        $response = $this->handler->handle('tools/list', []);

        $this->assertSame($expectedSchema, $response['tools'][0]['inputSchema']);
    }

    public static function toolInputSchemaProvider(): array
    {
        $toolWithGetInputSchema = Mockery::mock();
        $toolWithGetInputSchema->shouldReceive('getInputSchema')->andReturn(['type' => 'custom']);

        $toolWithInputSchemaMethod = Mockery::mock();
        $toolWithInputSchemaMethod->shouldReceive('inputSchema')->andReturn(['type' => 'method']);

        $toolWithInputSchemaProperty = new class
        {
            public array $inputSchema = ['type' => 'property'];

            public function getDescription(): string
            {
                return 'Test tool';
            }
        };

        $toolWithoutInputSchema = Mockery::mock();

        return [
            'getInputSchema method' => [
                $toolWithGetInputSchema,
                ['type' => 'custom'],
            ],
            'inputSchema method' => [
                $toolWithInputSchemaMethod,
                ['type' => 'method'],
            ],
            'inputSchema property' => [
                $toolWithInputSchemaProperty,
                ['type' => 'property'],
            ],
            'no input schema' => [
                $toolWithoutInputSchema,
                [
                    'type' => 'object',
                    'properties' => [],
                    'additionalProperties' => true,
                ],
            ],
        ];
    }

    #[Test]
    #[DataProvider('contentTypeProvider')]
    public function get_content_type_determines_correct_type($content, string $expectedType): void
    {
        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->andReturn($content);

        $this->toolRegistry
            ->shouldReceive('has')
            ->with('test-tool')
            ->once()
            ->andReturn(true);

        $this->toolRegistry
            ->shouldReceive('get')
            ->with('test-tool')
            ->once()
            ->andReturn(['handler' => $mockTool, 'options' => []]);

        $response = $this->handler->handle('tools/call', [
            'name' => 'test-tool',
            'arguments' => [],
        ]);

        $this->assertSame($expectedType, $response['content'][0]['type']);
    }

    public static function contentTypeProvider(): array
    {
        return [
            'string content' => ['Simple string', 'text'],
            'array content' => [['key' => 'value'], 'text'], // JSON formatted as text
            'object content' => [new \stdClass, 'text'], // JSON formatted as text
            'number content' => [42, 'text'],
            'boolean content' => [true, 'text'],
        ];
    }

    private function createMockTool(string $description): object
    {
        $tool = Mockery::mock();
        $tool->shouldReceive('getDescription')->andReturn($description);
        $tool->shouldReceive('getInputSchema')->andReturn(['type' => 'object']);

        return $tool;
    }
}
