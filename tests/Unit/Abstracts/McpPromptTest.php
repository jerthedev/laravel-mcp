<?php

namespace JTD\LaravelMCP\Tests\Unit\Abstracts;

use Illuminate\Container\Container;
use Illuminate\View\Factory as ViewFactory;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class McpPromptTest extends TestCase
{
    protected function createCustomTestPrompt(): McpPrompt
    {
        return new class extends McpPrompt
        {
            protected string $name = 'test_prompt';

            protected string $description = 'Test prompt';

            protected array $arguments = [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'age' => [
                    'type' => 'integer',
                    'required' => false,
                ],
            ];

            protected function customContent(array $arguments): string
            {
                $name = $arguments['name'] ?? 'Unknown';
                $age = $arguments['age'] ?? 'unknown';

                return "Hello {$name}, you are {$age} years old.";
            }
        };
    }

    public function test_it_initializes_with_container_and_view()
    {
        $prompt = $this->createCustomTestPrompt();

        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($prompt);

        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $this->assertInstanceOf(Container::class, $containerProperty->getValue($prompt));

        $viewProperty = $reflection->getProperty('view');
        $viewProperty->setAccessible(true);
        $this->assertInstanceOf(ViewFactory::class, $viewProperty->getValue($prompt));
    }

    public function test_it_returns_configured_name()
    {
        $prompt = $this->createCustomTestPrompt();

        $this->assertEquals('test_prompt', $prompt->getName());
    }

    public function test_it_generates_name_from_class_when_not_set()
    {
        $prompt = new class extends McpPrompt
        {
            protected function customContent(array $arguments): string
            {
                return 'test content';
            }
        };

        $name = $prompt->getName();
        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    public function test_it_returns_configured_description()
    {
        $prompt = $this->createCustomTestPrompt();

        $this->assertEquals('Test prompt', $prompt->getDescription());
    }

    public function test_it_returns_default_description_when_not_set()
    {
        $prompt = new class extends McpPrompt
        {
            protected function customContent(array $arguments): string
            {
                return 'test content';
            }
        };

        $this->assertEquals('MCP Prompt', $prompt->getDescription());
    }

    public function test_it_returns_configured_arguments()
    {
        $prompt = $this->createCustomTestPrompt();
        $arguments = $prompt->getArguments();

        $this->assertIsArray($arguments);
        $this->assertArrayHasKey('name', $arguments);
        $this->assertArrayHasKey('age', $arguments);
    }

    public function test_it_generates_prompt_messages()
    {
        $prompt = $this->createCustomTestPrompt();

        $result = $prompt->get(['name' => 'John', 'age' => 30]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('messages', $result);

        $this->assertEquals('Test prompt', $result['description']);
        $this->assertCount(1, $result['messages']);

        $message = $result['messages'][0];
        $this->assertEquals('user', $message['role']);
        $this->assertArrayHasKey('content', $message);
        $this->assertEquals('text', $message['content']['type']);
        $this->assertStringContainsString('Hello John', $message['content']['text']);
    }

    public function test_it_handles_authorization()
    {
        $prompt = new class extends McpPrompt
        {
            protected bool $requiresAuth = true;

            protected function authorize(array $arguments): bool
            {
                return false; // Deny access
            }

            protected function customContent(array $arguments): string
            {
                return 'test content';
            }
        };

        $this->expectException(UnauthorizedHttpException::class);
        $prompt->get(['test' => 'data']);
    }

    public function test_it_validates_arguments()
    {
        $prompt = $this->createCustomTestPrompt();

        $this->expectException(\Exception::class);
        $prompt->get([]); // Missing required 'name' argument
    }

    public function test_it_uses_template_when_provided()
    {
        $prompt = new class extends McpPrompt
        {
            protected ?string $template = 'Hello {{name}}, welcome!';

            protected array $arguments = [
                'name' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ];
        };

        $result = $prompt->get(['name' => 'John']);

        $this->assertStringContainsString('Hello John, welcome!', $result['messages'][0]['content']['text']);
    }

    public function test_it_converts_to_array()
    {
        $prompt = $this->createCustomTestPrompt();
        $array = $prompt->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('arguments', $array);

        $this->assertEquals('test_prompt', $array['name']);
        $this->assertEquals('Test prompt', $array['description']);
        $this->assertIsArray($array['arguments']);
    }

    public function test_it_creates_message_structure()
    {
        $prompt = $this->createCustomTestPrompt();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($prompt);
        $method = $reflection->getMethod('createMessage');
        $method->setAccessible(true);

        $result = $method->invoke($prompt, 'user', 'Hello world');

        $this->assertIsArray($result);
        $this->assertEquals('user', $result['role']);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('text', $result['content']['type']);
        $this->assertEquals('Hello world', $result['content']['text']);
    }

    public function test_it_formats_messages()
    {
        $prompt = $this->createCustomTestPrompt();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($prompt);
        $method = $reflection->getMethod('formatMessages');
        $method->setAccessible(true);

        $messages = [['role' => 'user', 'content' => ['type' => 'text', 'text' => 'Hello']]];
        $result = $method->invoke($prompt, $messages);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals($messages, $result['messages']);
    }

    public function test_it_applies_template_variables()
    {
        $prompt = $this->createCustomTestPrompt();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($prompt);
        $method = $reflection->getMethod('applyTemplate');
        $method->setAccessible(true);

        $result = $method->invoke($prompt, 'Hello {{name}}, you are {{age}}!', [
            'name' => 'John',
            'age' => 30,
        ]);

        $this->assertEquals('Hello John, you are 30!', $result);
    }

    public function test_it_provides_container_helpers()
    {
        $prompt = $this->createCustomTestPrompt();

        // Test that the container is properly initialized
        $reflection = new \ReflectionObject($prompt);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $container = $property->getValue($prompt);

        $this->assertInstanceOf(Container::class, $container);
    }

    public function test_it_uses_traits()
    {
        $prompt = $this->createCustomTestPrompt();

        $this->assertTrue(method_exists($prompt, 'validateParameters'));
        $this->assertTrue(method_exists($prompt, 'logRequest'));
    }

    public function test_it_throws_exception_for_unimplemented_custom_content()
    {
        $prompt = new class extends McpPrompt
        {
            // No custom content method implemented
        };

        $this->expectException(\BadMethodCallException::class);
        $prompt->get([]);
    }

    public function test_it_handles_empty_arguments()
    {
        $prompt = new class extends McpPrompt
        {
            protected array $arguments = []; // No arguments defined

            protected function customContent(array $arguments): string
            {
                return 'No arguments needed';
            }
        };

        $result = $prompt->get([]);

        $this->assertIsArray($result);
        $this->assertStringContainsString('No arguments needed', $result['messages'][0]['content']['text']);
    }
}
