<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\PromptRegistry;
use Tests\TestCase;

/**
 * Test suite for PromptRegistry functionality.
 *
 * Tests the prompt-specific registry that manages registration,
 * validation, and retrieval of MCP prompts.
 */
class PromptRegistryTest extends TestCase
{
    private PromptRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new PromptRegistry;
    }

    /**
     * Test successful prompt registration.
     */
    public function test_register_prompt_successfully(): void
    {
        $promptName = 'email_template';
        $prompt = $this->createTestPrompt($promptName, [
            'description' => 'Generate email templates',
            'argumentsSchema' => [
                'type' => 'object',
                'properties' => [
                    'subject' => ['type' => 'string'],
                    'recipient' => ['type' => 'string'],
                ],
                'required' => ['subject', 'recipient'],
            ],
        ]);
        $metadata = [
            'description' => 'Generate email templates',
            'arguments' => [
                ['name' => 'subject', 'type' => 'string', 'description' => 'Email subject', 'required' => true],
                ['name' => 'recipient', 'type' => 'string', 'description' => 'Email recipient', 'required' => true],
            ],
        ];

        $this->registry->register($promptName, $prompt, $metadata);

        $this->assertTrue($this->registry->has($promptName));
        $this->assertSame($prompt, $this->registry->get($promptName));
    }

    /**
     * Test registration with duplicate prompt name throws exception.
     */
    public function test_register_duplicate_prompt_throws_exception(): void
    {
        $promptName = 'duplicate_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Prompt '{$promptName}' is already registered");

        $this->registry->register($promptName, $prompt);
    }

    /**
     * Test getting non-existent prompt throws exception.
     */
    public function test_get_non_existent_prompt_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Prompt 'nonexistent' is not registered");

        $this->registry->get('nonexistent');
    }

    /**
     * Test successful prompt unregistration.
     */
    public function test_unregister_prompt_successfully(): void
    {
        $promptName = 'test_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt);
        $this->assertTrue($this->registry->has($promptName));

        $result = $this->registry->unregister($promptName);

        $this->assertTrue($result);
        $this->assertFalse($this->registry->has($promptName));
    }

    /**
     * Test unregistering non-existent prompt returns false.
     */
    public function test_unregister_non_existent_prompt(): void
    {
        $result = $this->registry->unregister('nonexistent');

        $this->assertFalse($result);
    }

    /**
     * Test checking if prompt exists.
     */
    public function test_has_prompt(): void
    {
        $promptName = 'test_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->assertFalse($this->registry->has($promptName));

        $this->registry->register($promptName, $prompt);

        $this->assertTrue($this->registry->has($promptName));
    }

    /**
     * Test getting all registered prompts.
     */
    public function test_get_all_prompts(): void
    {
        $prompt1 = $this->createTestPrompt('prompt1');
        $prompt2 = $this->createTestPrompt('prompt2');

        $this->registry->register('prompt1', $prompt1);
        $this->registry->register('prompt2', $prompt2);

        $all = $this->registry->getAll();

        $this->assertCount(2, $all);
        $this->assertSame($prompt1, $all['prompt1']);
        $this->assertSame($prompt2, $all['prompt2']);

        // Test alias method
        $this->assertEquals($all, $this->registry->all());
    }

    /**
     * Test getting prompt names.
     */
    public function test_get_prompt_names(): void
    {
        $this->registry->register('prompt1', $this->createTestPrompt('prompt1'));
        $this->registry->register('prompt2', $this->createTestPrompt('prompt2'));

        $names = $this->registry->names();

        $this->assertEquals(['prompt1', 'prompt2'], $names);
    }

    /**
     * Test counting registered prompts.
     */
    public function test_count_prompts(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register('prompt1', $this->createTestPrompt('prompt1'));
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register('prompt2', $this->createTestPrompt('prompt2'));
        $this->assertEquals(2, $this->registry->count());

        $this->registry->unregister('prompt1');
        $this->assertEquals(1, $this->registry->count());
    }

    /**
     * Test clearing all prompts.
     */
    public function test_clear_all_prompts(): void
    {
        $this->registry->register('prompt1', $this->createTestPrompt('prompt1'));
        $this->registry->register('prompt2', $this->createTestPrompt('prompt2'));

        $this->assertEquals(2, $this->registry->count());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count());
        $this->assertFalse($this->registry->has('prompt1'));
        $this->assertFalse($this->registry->has('prompt2'));
    }

    /**
     * Test getting prompt metadata.
     */
    public function test_get_prompt_metadata(): void
    {
        $promptName = 'test_prompt';
        $prompt = $this->createTestPrompt($promptName);
        $metadata = [
            'description' => 'Test prompt description',
            'arguments' => [
                ['name' => 'topic', 'type' => 'string', 'description' => 'Topic to write about', 'required' => true],
                ['name' => 'length', 'type' => 'integer', 'description' => 'Length in words', 'required' => false],
            ],
        ];

        $this->registry->register($promptName, $prompt, $metadata);

        $retrievedMetadata = $this->registry->getMetadata($promptName);

        $this->assertEquals($promptName, $retrievedMetadata['name']);
        $this->assertEquals('prompt', $retrievedMetadata['type']);
        $this->assertEquals('Test prompt description', $retrievedMetadata['description']);
        $this->assertEquals($metadata['arguments'], $retrievedMetadata['arguments']);
        $this->assertNotEmpty($retrievedMetadata['registered_at']);
    }

    /**
     * Test getting metadata for non-existent prompt throws exception.
     */
    public function test_get_metadata_for_non_existent_prompt_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Prompt 'nonexistent' is not registered");

        $this->registry->getMetadata('nonexistent');
    }

    /**
     * Test prompt filtering by metadata criteria.
     */
    public function test_filter_prompts_by_metadata(): void
    {
        $prompt1 = $this->createTestPrompt('prompt1');
        $prompt2 = $this->createTestPrompt('prompt2');
        $prompt3 = $this->createTestPrompt('prompt3');

        $this->registry->register('prompt1', $prompt1, ['category' => 'email']);
        $this->registry->register('prompt2', $prompt2, ['category' => 'content']);
        $this->registry->register('prompt3', $prompt3, ['category' => 'email']);

        $emailPrompts = $this->registry->filter(['category' => 'email']);

        $this->assertCount(2, $emailPrompts);
        $this->assertArrayHasKey('prompt1', $emailPrompts);
        $this->assertArrayHasKey('prompt3', $emailPrompts);
        $this->assertArrayNotHasKey('prompt2', $emailPrompts);
    }

    /**
     * Test prompt searching by name pattern.
     */
    public function test_search_prompts_by_pattern(): void
    {
        $this->registry->register('email_marketing', $this->createTestPrompt('email_marketing'));
        $this->registry->register('email_support', $this->createTestPrompt('email_support'));
        $this->registry->register('blog_post', $this->createTestPrompt('blog_post'));

        $emailPrompts = $this->registry->search('email_*');

        $this->assertCount(2, $emailPrompts);
        $this->assertArrayHasKey('email_marketing', $emailPrompts);
        $this->assertArrayHasKey('email_support', $emailPrompts);
        $this->assertArrayNotHasKey('blog_post', $emailPrompts);
    }

    /**
     * Test getting registry type.
     */
    public function test_get_registry_type(): void
    {
        $this->assertEquals('prompts', $this->registry->getType());
    }

    /**
     * Test getting prompt definitions for MCP protocol.
     */
    public function test_get_prompt_definitions(): void
    {
        $prompt1 = $this->createTestPrompt('prompt1');
        $prompt2 = $this->createTestPrompt('prompt2');

        $this->registry->register('prompt1', $prompt1, [
            'description' => 'First test prompt',
            'arguments' => [
                ['name' => 'topic', 'description' => 'Topic to discuss', 'required' => true],
                ['name' => 'tone', 'description' => 'Tone of voice', 'required' => false],
            ],
        ]);

        $this->registry->register('prompt2', $prompt2, [
            'description' => 'Second test prompt',
        ]);

        $definitions = $this->registry->getPromptDefinitions();

        $this->assertCount(2, $definitions);

        $this->assertEquals('prompt1', $definitions[0]['name']);
        $this->assertEquals('First test prompt', $definitions[0]['description']);
        $this->assertCount(2, $definitions[0]['arguments']);
        $this->assertEquals('topic', $definitions[0]['arguments'][0]['name']);
        $this->assertEquals('Topic to discuss', $definitions[0]['arguments'][0]['description']);
        $this->assertTrue($definitions[0]['arguments'][0]['required']);

        $this->assertEquals('prompt2', $definitions[1]['name']);
        $this->assertEquals('Second test prompt', $definitions[1]['description']);
        $this->assertEquals([], $definitions[1]['arguments']);
    }

    /**
     * Test getting a prompt with rendered content.
     */
    public function test_get_prompt(): void
    {
        $promptName = 'test_prompt';

        // Create a mock prompt with render method
        $prompt = new class extends McpPrompt
        {
            protected string $name = 'test_prompt';

            protected string $description = 'Test prompt';

            public function getMessages(array $arguments): array
            {
                return [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Test content with topic: '.($arguments['topic'] ?? 'default'),
                            ],
                        ],
                    ],
                ];
            }

            public function render(array $arguments): array
            {
                return $this->getMessages($arguments);
            }
        };

        $this->registry->register($promptName, $prompt, [
            'description' => 'Test prompt for rendering',
        ]);

        $result = $this->registry->getPrompt($promptName, ['topic' => 'testing']);

        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('messages', $result);
        $this->assertEquals('Test prompt for rendering', $result['description']);
        $this->assertIsArray($result['messages']);
    }

    /**
     * Test getting prompt with class name.
     */
    public function test_get_prompt_with_class_name(): void
    {
        $promptName = 'test_prompt';

        // Create a test prompt class
        $promptClass = new class extends McpPrompt
        {
            protected string $name = 'test_prompt';

            public function getMessages(array $arguments): array
            {
                return ['messages' => []];
            }

            public function render(array $arguments): array
            {
                return $this->getMessages($arguments);
            }
        };

        $this->registry->register($promptName, get_class($promptClass));

        $result = $this->registry->getPrompt($promptName);

        $this->assertArrayHasKey('description', $result);
        $this->assertArrayHasKey('messages', $result);
    }

    /**
     * Test getting prompt with invalid prompt throws exception.
     */
    public function test_get_invalid_prompt_throws_exception(): void
    {
        $promptName = 'invalid_prompt';
        $invalidPrompt = new class
        {
            // No render method
        };

        $this->registry->register($promptName, $invalidPrompt);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Prompt '{$promptName}' does not have a render method");

        $this->registry->getPrompt($promptName);
    }

    /**
     * Test listing prompts for MCP protocol.
     */
    public function test_list_prompts(): void
    {
        $prompt1 = $this->createTestPrompt('prompt1');
        $prompt2 = $this->createTestPrompt('prompt2');

        $this->registry->register('prompt1', $prompt1, [
            'description' => 'First prompt',
            'arguments' => [
                ['name' => 'arg1', 'description' => 'First argument', 'required' => true],
            ],
        ]);

        $this->registry->register('prompt2', $prompt2, [
            'description' => 'Second prompt',
        ]);

        $list = $this->registry->listPrompts();

        $this->assertArrayHasKey('prompts', $list);
        $this->assertCount(2, $list['prompts']);

        $first = $list['prompts'][0];
        $this->assertEquals('prompt1', $first['name']);
        $this->assertEquals('First prompt', $first['description']);
        $this->assertCount(1, $first['arguments']);

        $second = $list['prompts'][1];
        $this->assertEquals('prompt2', $second['name']);
        $this->assertEquals('Second prompt', $second['description']);
        $this->assertEquals([], $second['arguments']);
    }

    /**
     * Test argument validation.
     */
    public function test_validate_arguments(): void
    {
        $promptName = 'validation_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt, [
            'arguments' => [
                ['name' => 'required_arg', 'required' => true],
                ['name' => 'optional_arg', 'required' => false],
            ],
        ]);

        // Valid arguments
        $this->assertTrue($this->registry->validateArguments($promptName, [
            'required_arg' => 'value',
            'optional_arg' => 'value',
        ]));

        // Valid with only required
        $this->assertTrue($this->registry->validateArguments($promptName, [
            'required_arg' => 'value',
        ]));

        // Missing required argument
        $this->assertFalse($this->registry->validateArguments($promptName, [
            'optional_arg' => 'value',
        ]));

        // Empty required argument
        $this->assertFalse($this->registry->validateArguments($promptName, [
            'required_arg' => '',
            'optional_arg' => 'value',
        ]));
    }

    /**
     * Test getting prompts by argument requirements.
     */
    public function test_get_prompts_by_arguments(): void
    {
        $this->registry->register('prompt1', $this->createTestPrompt('prompt1'), [
            'arguments' => [
                ['name' => 'topic'],
                ['name' => 'length'],
            ],
        ]);

        $this->registry->register('prompt2', $this->createTestPrompt('prompt2'), [
            'arguments' => [
                ['name' => 'topic'],
            ],
        ]);

        $this->registry->register('prompt3', $this->createTestPrompt('prompt3'), [
            'arguments' => [
                ['name' => 'subject'],
                ['name' => 'recipient'],
            ],
        ]);

        $topicPrompts = $this->registry->getPromptsByArguments(['topic']);
        $this->assertCount(2, $topicPrompts);

        $topicLengthPrompts = $this->registry->getPromptsByArguments(['topic', 'length']);
        $this->assertCount(1, $topicLengthPrompts);
        $this->assertArrayHasKey('prompt1', $topicLengthPrompts);

        $emailPrompts = $this->registry->getPromptsByArguments(['subject', 'recipient']);
        $this->assertCount(1, $emailPrompts);
        $this->assertArrayHasKey('prompt3', $emailPrompts);
    }

    /**
     * Test getting argument schema for a prompt.
     */
    public function test_get_argument_schema(): void
    {
        $promptName = 'schema_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt, [
            'arguments' => [
                [
                    'name' => 'topic',
                    'type' => 'string',
                    'description' => 'Topic to write about',
                    'required' => true,
                ],
                [
                    'name' => 'word_count',
                    'type' => 'integer',
                    'description' => 'Number of words',
                    'required' => false,
                ],
                [
                    'name' => 'style',
                    'type' => 'string',
                    'description' => 'Writing style',
                    'required' => false,
                ],
            ],
        ]);

        $schema = $this->registry->getArgumentSchema($promptName);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);

        // Check properties
        $this->assertArrayHasKey('topic', $schema['properties']);
        $this->assertArrayHasKey('word_count', $schema['properties']);
        $this->assertArrayHasKey('style', $schema['properties']);

        $this->assertEquals('string', $schema['properties']['topic']['type']);
        $this->assertEquals('Topic to write about', $schema['properties']['topic']['description']);

        $this->assertEquals('integer', $schema['properties']['word_count']['type']);
        $this->assertEquals('Number of words', $schema['properties']['word_count']['description']);

        // Check required fields
        $this->assertEquals(['topic'], $schema['required']);
    }

    /**
     * Test registry initialization.
     */
    public function test_initialize(): void
    {
        // This should not throw any exception
        $this->registry->initialize();
        $this->assertTrue(true);
    }

    /**
     * Test metadata defaults are set correctly.
     */
    public function test_metadata_defaults(): void
    {
        $promptName = 'default_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt);

        $metadata = $this->registry->getMetadata($promptName);

        $this->assertEquals($promptName, $metadata['name']);
        $this->assertEquals('prompt', $metadata['type']);
        $this->assertEquals('', $metadata['description']);
        $this->assertEquals([], $metadata['arguments']);
        $this->assertNotEmpty($metadata['registered_at']);
    }

    /**
     * Test complex filtering scenarios.
     */
    public function test_complex_filtering(): void
    {
        $this->registry->register('prompt1', $this->createTestPrompt('prompt1'), [
            'category' => 'email',
            'difficulty' => 'beginner',
        ]);

        $this->registry->register('prompt2', $this->createTestPrompt('prompt2'), [
            'category' => 'email',
            'difficulty' => 'advanced',
        ]);

        $this->registry->register('prompt3', $this->createTestPrompt('prompt3'), [
            'category' => 'content',
            'difficulty' => 'beginner',
        ]);

        // Filter by multiple criteria
        $beginnerEmailPrompts = $this->registry->filter([
            'category' => 'email',
            'difficulty' => 'beginner',
        ]);

        $this->assertCount(1, $beginnerEmailPrompts);
        $this->assertArrayHasKey('prompt1', $beginnerEmailPrompts);

        // Filter with non-matching criteria
        $nonExistentPrompts = $this->registry->filter([
            'category' => 'nonexistent',
        ]);

        $this->assertCount(0, $nonExistentPrompts);
    }

    /**
     * Test edge cases for prompt names.
     */
    public function test_prompt_name_edge_cases(): void
    {
        // Test with special characters in name
        $specialName = 'prompt-with_special.chars';
        $prompt = $this->createTestPrompt($specialName);

        $this->registry->register($specialName, $prompt);
        $this->assertTrue($this->registry->has($specialName));

        // Test with numeric name
        $numericName = '123';
        $numericPrompt = $this->createTestPrompt($numericName);

        $this->registry->register($numericName, $numericPrompt);
        $this->assertTrue($this->registry->has($numericName));
    }

    /**
     * Test argument schema with missing argument names.
     */
    public function test_argument_schema_with_missing_names(): void
    {
        $promptName = 'incomplete_schema_prompt';
        $prompt = $this->createTestPrompt($promptName);

        $this->registry->register($promptName, $prompt, [
            'arguments' => [
                ['type' => 'string'], // Missing name
                ['name' => 'valid_arg', 'type' => 'integer'],
                [], // Empty argument
            ],
        ]);

        $schema = $this->registry->getArgumentSchema($promptName);

        // Should only include arguments with valid names
        $this->assertArrayHasKey('valid_arg', $schema['properties']);
        $this->assertCount(1, $schema['properties']);
    }

    /**
     * Test message formatting with different content types.
     */
    public function test_message_formatting(): void
    {
        $registry = new class extends PromptRegistry
        {
            public function test_format_messages($content): array
            {
                return $this->formatMessages($content);
            }
        };

        // Test string content
        $stringContent = 'Simple text content';
        $result = $registry->test_format_messages($stringContent);
        $this->assertCount(1, $result);
        $this->assertEquals('user', $result[0]['role']);
        $this->assertEquals('text', $result[0]['content']['type']);
        $this->assertEquals($stringContent, $result[0]['content']['text']);

        // Test array with messages
        $arrayContent = [
            'messages' => [
                ['role' => 'system', 'content' => ['type' => 'text', 'text' => 'System message']],
                ['role' => 'user', 'content' => ['type' => 'text', 'text' => 'User message']],
            ],
        ];
        $result = $registry->test_format_messages($arrayContent);
        $this->assertEquals($arrayContent['messages'], $result);

        // Test other array content
        $otherContent = ['key' => 'value'];
        $result = $registry->test_format_messages($otherContent);
        $this->assertCount(1, $result);
        $this->assertEquals('user', $result[0]['role']);
        $this->assertEquals(json_encode($otherContent), $result[0]['content']['text']);
    }
}
