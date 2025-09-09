<?php

namespace JTD\LaravelMCP\Tests\Unit\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Server\Handlers\PromptHandler;
use JTD\LaravelMCP\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for PromptHandler class.
 *
 * This test suite ensures the PromptHandler properly handles prompt-related MCP operations,
 * including prompts/list and prompts/get methods, with proper validation, error handling,
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
#[CoversClass(PromptHandler::class)]
class PromptHandlerTest extends TestCase
{
    private PromptRegistry $promptRegistry;

    private PromptHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->promptRegistry = Mockery::mock(PromptRegistry::class);
        $this->handler = new PromptHandler($this->promptRegistry, false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructor_sets_dependencies_and_handler_name(): void
    {
        $handler = new PromptHandler($this->promptRegistry, true);

        $this->assertTrue($handler->isDebug());
        $this->assertSame('PromptHandler', $handler->getHandlerName());
    }

    #[Test]
    public function get_supported_methods_returns_prompt_methods(): void
    {
        $expected = ['prompts/list', 'prompts/get'];

        $this->assertSame($expected, $this->handler->getSupportedMethods());
    }

    #[Test]
    public function supports_method_returns_true_for_supported_methods(): void
    {
        $this->assertTrue($this->handler->supportsMethod('prompts/list'));
        $this->assertTrue($this->handler->supportsMethod('prompts/get'));
    }

    #[Test]
    public function supports_method_returns_false_for_unsupported_methods(): void
    {
        $this->assertFalse($this->handler->supportsMethod('prompts/unsupported'));
        $this->assertFalse($this->handler->supportsMethod('tools/list'));
    }

    #[Test]
    public function handle_throws_protocol_exception_for_unsupported_method(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Unsupported method: prompts/unsupported');

        $this->handler->handle('prompts/unsupported', []);
    }

    #[Test]
    public function handle_prompts_list_returns_empty_prompts_array_when_no_prompts(): void
    {
        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([]);

        $response = $this->handler->handle('prompts/list', []);

        $this->assertArrayHasKey('prompts', $response);
        $this->assertIsArray($response['prompts']);
        $this->assertEmpty($response['prompts']);
        $this->assertArrayNotHasKey('nextCursor', $response);
    }

    #[Test]
    public function handle_prompts_list_returns_prompt_definitions(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt description');
        $mockPrompt->shouldReceive('getArguments')->andReturn([
            ['name' => 'topic', 'description' => 'The topic', 'required' => true],
        ]);

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-prompt' => $mockPrompt]);

        $response = $this->handler->handle('prompts/list', []);

        $this->assertArrayHasKey('prompts', $response);
        $this->assertCount(1, $response['prompts']);

        $promptDef = $response['prompts'][0];
        $this->assertSame('test-prompt', $promptDef['name']);
        $this->assertSame('Test prompt description', $promptDef['description']);
        $this->assertSame([
            ['name' => 'topic', 'description' => 'The topic', 'required' => true],
        ], $promptDef['arguments']);
    }

    #[Test]
    public function handle_prompts_list_handles_prompt_definition_failures_gracefully(): void
    {
        $goodPrompt = Mockery::mock();
        $goodPrompt->shouldReceive('getDescription')->andReturn('Good prompt');
        $goodPrompt->shouldReceive('getArguments')->andReturn([]);

        $badPrompt = Mockery::mock();
        $badPrompt->shouldReceive('getDescription')->andThrow(new \RuntimeException('Bad prompt'));

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'good-prompt' => $goodPrompt,
                'bad-prompt' => $badPrompt,
            ]);

        $response = $this->handler->handle('prompts/list', []);

        $this->assertArrayHasKey('prompts', $response);
        $this->assertCount(1, $response['prompts']); // Only the good prompt
        $this->assertSame('good-prompt', $response['prompts'][0]['name']);
    }

    #[Test]
    public function handle_prompts_list_validates_cursor_parameter(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('prompts/list', ['cursor' => 123]); // Should be string
    }

    #[Test]
    public function handle_prompts_list_applies_cursor_pagination(): void
    {
        $mockPrompt1 = $this->createMockPrompt('Prompt 1');
        $mockPrompt2 = $this->createMockPrompt('Prompt 2');
        $mockPrompt3 = $this->createMockPrompt('Prompt 3');

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'prompt1' => $mockPrompt1,
                'prompt2' => $mockPrompt2,
                'prompt3' => $mockPrompt3,
            ]);

        // Create cursor for pagination (skip first 1, limit 1)
        $cursor = base64_encode(json_encode(['offset' => 1, 'limit' => 1]));

        $response = $this->handler->handle('prompts/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('prompts', $response);
        $this->assertCount(1, $response['prompts']);
        $this->assertSame('prompt2', $response['prompts'][0]['name']); // Second prompt
    }

    #[Test]
    public function handle_prompts_list_includes_next_cursor_when_more_prompts_available(): void
    {
        $prompts = [];
        for ($i = 1; $i <= 60; $i++) {
            $prompts["prompt{$i}"] = $this->createMockPrompt("Prompt {$i}");
        }

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn($prompts);

        $cursor = base64_encode(json_encode(['offset' => 0, 'limit' => 50]));

        $response = $this->handler->handle('prompts/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('nextCursor', $response);
        $this->assertIsString($response['nextCursor']);

        $decodedCursor = json_decode(base64_decode($response['nextCursor']), true);
        $this->assertSame(50, $decodedCursor['offset']);
        $this->assertSame(50, $decodedCursor['limit']);
    }

    #[Test]
    public function handle_prompts_get_validates_required_parameters(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('prompts/get', []); // Missing 'name' parameter
    }

    #[Test]
    public function handle_prompts_get_validates_parameter_types(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('prompts/get', ['name' => 123]); // name should be string
    }

    #[Test]
    public function handle_prompts_get_throws_error_for_non_existent_prompt(): void
    {
        $this->promptRegistry
            ->shouldReceive('has')
            ->with('non-existent-prompt')
            ->once()
            ->andReturn(false);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Prompt not found: non-existent-prompt');

        $this->handler->handle('prompts/get', ['name' => 'non-existent-prompt']);
    }

    #[Test]
    public function handle_prompts_get_processes_prompt_with_process_method(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('process')
            ->with(['topic' => 'test'])
            ->once()
            ->andReturn([
                [
                    'role' => 'user',
                    'content' => [['type' => 'text', 'text' => 'Tell me about test']],
                ],
            ]);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            'arguments' => ['topic' => 'test'],
        ]);

        $this->assertArrayHasKey('description', $response);
        $this->assertArrayHasKey('messages', $response);
        $this->assertSame('Test prompt', $response['description']);
        $this->assertCount(1, $response['messages']);
        $this->assertSame('user', $response['messages'][0]['role']);
        $this->assertSame('Tell me about test', $response['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function handle_prompts_get_processes_prompt_with_get_method(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('get')
            ->with(['topic' => 'test'])
            ->once()
            ->andReturn([
                'role' => 'assistant',
                'content' => [['type' => 'text', 'text' => 'Here is about test']],
            ]);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            'arguments' => ['topic' => 'test'],
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertCount(1, $response['messages']);
        $this->assertSame('assistant', $response['messages'][0]['role']);
    }

    #[Test]
    public function handle_prompts_get_processes_prompt_with_invoke_method(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('__invoke')
            ->with(['topic' => 'test'])
            ->once()
            ->andReturn('Simple text response');

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            'arguments' => ['topic' => 'test'],
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertCount(1, $response['messages']);
        $this->assertSame('user', $response['messages'][0]['role']); // Default role
        $this->assertSame('Simple text response', $response['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function handle_prompts_get_processes_callable_prompt(): void
    {
        $callablePrompt = function ($args) {
            return 'Callable prompt result: '.$args['topic'];
        };

        // Mock getDescription by wrapping in an object
        $promptWrapper = new class($callablePrompt)
        {
            private $callable;

            public function __construct($callable)
            {
                $this->callable = $callable;
            }

            public function getDescription(): string
            {
                return 'Callable prompt';
            }

            public function __invoke($args)
            {
                return call_user_func($this->callable, $args);
            }
        };

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('callable-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('callable-prompt')
            ->once()
            ->andReturn($promptWrapper);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'callable-prompt',
            'arguments' => ['topic' => 'AI'],
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertSame('Callable prompt result: AI', $response['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function handle_prompts_get_throws_error_for_non_processable_prompt(): void
    {
        $nonProcessablePrompt = new class
        {
            public function getDescription(): string
            {
                return 'Non-processable';
            }
            // No process, get, __invoke methods or callable
        };

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('non-processable')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('non-processable')
            ->once()
            ->andReturn($nonProcessablePrompt);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32603);
        $this->expectExceptionMessage('Failed to process prompt: Prompt is not processable');

        $this->handler->handle('prompts/get', [
            'name' => 'non-processable',
            'arguments' => [],
        ]);
    }

    #[Test]
    public function handle_prompts_get_validates_arguments_if_prompt_supports_it(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('validateArguments')
            ->with(['invalid' => 'args'])
            ->once()
            ->andReturn(false);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('validating-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('validating-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);
        $this->expectExceptionMessage('Invalid arguments for prompt: validating-prompt');

        $this->handler->handle('prompts/get', [
            'name' => 'validating-prompt',
            'arguments' => ['invalid' => 'args'],
        ]);
    }

    #[Test]
    public function handle_prompts_get_skips_validation_if_prompt_does_not_support_it(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Non-validating prompt');
        $mockPrompt->shouldReceive('process')
            ->with(['any' => 'args'])
            ->once()
            ->andReturn([['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Success']]]]);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('non-validating-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('non-validating-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'non-validating-prompt',
            'arguments' => ['any' => 'args'],
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertSame('Success', $response['messages'][0]['content'][0]['text']);
    }

    #[Test]
    public function handle_prompts_get_handles_processing_failures(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Failing prompt');
        $mockPrompt->shouldReceive('process')
            ->andThrow(new \RuntimeException('Processing failed'));

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('failing-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('failing-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32603);
        $this->expectExceptionMessage('Failed to process prompt: Processing failed');

        $this->handler->handle('prompts/get', [
            'name' => 'failing-prompt',
            'arguments' => [],
        ]);
    }

    #[Test]
    public function handle_prompts_get_uses_default_empty_arguments_when_not_provided(): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('process')
            ->with([])  // Empty arguments
            ->once()
            ->andReturn([['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Success']]]]);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            // No 'arguments' key
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertSame('Success', $response['messages'][0]['content'][0]['text']);
    }

    #[Test]
    #[DataProvider('promptDescriptionProvider')]
    public function get_prompt_description_handles_different_description_methods($prompt, string $expectedDescription): void
    {
        // Ensure all prompts have getArguments method for listing
        if (method_exists($prompt, 'shouldReceive')) {
            $prompt->shouldReceive('getArguments')->andReturn([]);
        }

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-prompt' => $prompt]);

        $response = $this->handler->handle('prompts/list', []);

        $this->assertSame($expectedDescription, $response['prompts'][0]['description']);
    }

    public static function promptDescriptionProvider(): array
    {
        $promptWithGetDescription = Mockery::mock();
        $promptWithGetDescription->shouldReceive('getDescription')->andReturn('From getDescription');

        $promptWithDescriptionMethod = Mockery::mock();
        $promptWithDescriptionMethod->shouldReceive('description')->andReturn('From description method');

        $promptWithDescriptionProperty = new class
        {
            public string $description = 'From description property';

            public function getArguments(): array
            {
                return [];
            }
        };

        $promptWithoutDescription = Mockery::mock();
        $className = get_class($promptWithoutDescription);

        return [
            'getDescription method' => [$promptWithGetDescription, 'From getDescription'],
            'description method' => [$promptWithDescriptionMethod, 'From description method'],
            'description property' => [$promptWithDescriptionProperty, 'From description property'],
            'no description' => [$promptWithoutDescription, "Prompt: {$className}"],
        ];
    }

    #[Test]
    #[DataProvider('promptArgumentsProvider')]
    public function get_prompt_arguments_handles_different_arguments_methods($prompt, array $expectedArguments): void
    {
        // Ensure all prompts have getDescription method for listing
        if (method_exists($prompt, 'shouldReceive')) {
            $prompt->shouldReceive('getDescription')->andReturn('Test prompt');
        }

        $this->promptRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-prompt' => $prompt]);

        $response = $this->handler->handle('prompts/list', []);

        $this->assertSame($expectedArguments, $response['prompts'][0]['arguments']);
    }

    public static function promptArgumentsProvider(): array
    {
        $promptWithGetArguments = Mockery::mock();
        $promptWithGetArguments->shouldReceive('getArguments')->andReturn([
            ['name' => 'topic', 'type' => 'string', 'required' => true],
        ]);

        $promptWithArgumentsMethod = Mockery::mock();
        $promptWithArgumentsMethod->shouldReceive('arguments')->andReturn([
            ['name' => 'query', 'type' => 'string'],
        ]);

        $promptWithArgumentsProperty = new class
        {
            public array $arguments = [['name' => 'data', 'type' => 'object']];

            public function getDescription(): string
            {
                return 'Test prompt';
            }
        };

        $promptWithoutArguments = Mockery::mock();

        return [
            'getArguments method' => [
                $promptWithGetArguments,
                [['name' => 'topic', 'type' => 'string', 'required' => true]],
            ],
            'arguments method' => [
                $promptWithArgumentsMethod,
                [['name' => 'query', 'type' => 'string']],
            ],
            'arguments property' => [
                $promptWithArgumentsProperty,
                [['name' => 'data', 'type' => 'object']],
            ],
            'no arguments' => [$promptWithoutArguments, []],
        ];
    }

    #[Test]
    #[DataProvider('messageFormattingProvider')]
    public function format_prompt_messages_handles_different_result_formats($result, array $expectedMessages): void
    {
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('process')
            ->once()
            ->andReturn($result);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            'arguments' => [],
        ]);

        $this->assertArrayHasKey('messages', $response);
        $this->assertSame($expectedMessages, $response['messages']);
    }

    public static function messageFormattingProvider(): array
    {
        return [
            'array of messages' => [
                [
                    ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi']]],
                ],
                [
                    ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi']]],
                ],
            ],
            'single message object' => [
                ['role' => 'system', 'content' => [['type' => 'text', 'text' => 'System message']]],
                [['role' => 'system', 'content' => [['type' => 'text', 'text' => 'System message']]]],
            ],
            'string result' => [
                'Simple text response',
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Simple text response']]]],
            ],
            'array result (not messages)' => [
                ['key' => 'value', 'number' => 42],
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => "{\n    \"key\": \"value\",\n    \"number\": 42\n}"]]]],
            ],
            'object result' => [
                (object) ['prop' => 'value'],
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => "{\n    \"prop\": \"value\"\n}"]]]],
            ],
            'number result' => [
                42,
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => '42']]]],
            ],
            'empty array result' => [
                [],
                [['role' => 'user', 'content' => [['type' => 'text', 'text' => '[]']]]],
            ],
        ];
    }

    #[Test]
    #[DataProvider('isMessageArrayProvider')]
    public function is_message_array_correctly_identifies_message_arrays(array $input, bool $expected): void
    {
        // We need to test this through the message formatting since isMessageArray is protected
        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('getDescription')->andReturn('Test prompt');
        $mockPrompt->shouldReceive('process')
            ->once()
            ->andReturn($input);

        $this->promptRegistry
            ->shouldReceive('has')
            ->with('test-prompt')
            ->once()
            ->andReturn(true);

        $this->promptRegistry
            ->shouldReceive('get')
            ->with('test-prompt')
            ->once()
            ->andReturn($mockPrompt);

        $response = $this->handler->handle('prompts/get', [
            'name' => 'test-prompt',
            'arguments' => [],
        ]);

        if ($expected) {
            // Should preserve the original structure
            $this->assertSame($input, $response['messages']);
        } else {
            // Should wrap in user message
            $this->assertCount(1, $response['messages']);
            $this->assertSame('user', $response['messages'][0]['role']);
        }
    }

    public static function isMessageArrayProvider(): array
    {
        return [
            'valid message array' => [
                [
                    ['role' => 'user', 'content' => [['type' => 'text', 'text' => 'Hello']]],
                    ['role' => 'assistant', 'content' => [['type' => 'text', 'text' => 'Hi']]],
                ],
                true,
            ],
            'array with content only' => [
                [['content' => [['type' => 'text', 'text' => 'Hello']]]],
                true,
            ],
            'array with role only' => [
                [['role' => 'user']],
                true,
            ],
            'empty array' => [[], false],
            'array of strings' => [['hello', 'world'], false],
            'array of numbers' => [[1, 2, 3], false],
            'mixed array' => [['string', ['role' => 'user']], false],
        ];
    }

    private function createMockPrompt(string $description): object
    {
        $prompt = Mockery::mock();
        $prompt->shouldReceive('getDescription')->andReturn($description);
        $prompt->shouldReceive('getArguments')->andReturn([]);

        return $prompt;
    }
}
