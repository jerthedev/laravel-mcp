<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * EPIC: MCP-016
 * SPEC: Registration System Core
 * SPRINT: Sprint 2
 * TICKET: TASK-016-RegistrationCore
 *
 * Comprehensive integration tests for the MCP Registration System.
 * Tests the complete registration workflow including discovery, validation,
 * storage, and retrieval of MCP components.
 */
class RegistrationSystemTest extends TestCase
{
    private McpRegistry $registry;

    private ComponentDiscovery $discovery;

    private ToolRegistry $toolRegistry;

    private ResourceRegistry $resourceRegistry;

    private PromptRegistry $promptRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create real instances for integration testing
        $this->toolRegistry = new ToolRegistry;
        $this->resourceRegistry = new ResourceRegistry;
        $this->promptRegistry = new PromptRegistry;

        $this->registry = new McpRegistry(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry
        );

        $this->discovery = new ComponentDiscovery(
            $this->registry,
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry
        );
    }

    /**
     * Test complete tool registration workflow.
     */
    public function test_tool_registration_workflow(): void
    {
        // Create a test tool
        $tool = new class('test_calculator') extends McpTool
        {
            protected string $name = 'test_calculator';

            protected string $description = 'Performs mathematical calculations';

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function execute(array $arguments): array
            {
                $operation = $arguments['operation'] ?? 'add';
                $a = $arguments['a'] ?? 0;
                $b = $arguments['b'] ?? 0;

                return match ($operation) {
                    'add' => ['result' => $a + $b],
                    'subtract' => ['result' => $a - $b],
                    'multiply' => ['result' => $a * $b],
                    'divide' => $b != 0 ? ['result' => $a / $b] : ['error' => 'Division by zero'],
                    default => ['error' => 'Unknown operation']
                };
            }
        };

        // Register the tool
        $this->registry->register('tool', 'calculator', $tool, [
            'description' => 'Calculator for basic math operations',
            'version' => '1.0.0',
        ]);

        // Verify registration
        $this->assertTrue($this->registry->has('tool', 'calculator'));
        $this->assertEquals(1, $this->registry->count('tool'));

        // Retrieve and verify metadata
        $metadata = $this->registry->getMetadata('tool', 'calculator');
        $this->assertEquals('Calculator for basic math operations', $metadata['description']);
        $this->assertEquals('1.0.0', $metadata['version']);
        $this->assertArrayHasKey('registered_at', $metadata);

        // Test tool execution
        $retrievedTool = $this->registry->get('tool', 'calculator');
        $this->assertInstanceOf(McpTool::class, $retrievedTool);

        $result = $this->toolRegistry->executeTool('calculator', [
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);
        $this->assertEquals(['result' => 8], $result);

        // Test unregistration
        $this->assertTrue($this->registry->unregister('tool', 'calculator'));
        $this->assertFalse($this->registry->has('tool', 'calculator'));
        $this->assertEquals(0, $this->registry->count('tool'));
    }

    /**
     * Test complete resource registration workflow.
     */
    public function test_resource_registration_workflow(): void
    {
        // Create a test resource
        $resource = new class('user_profile') extends McpResource
        {
            protected string $uri = 'user://profile/{id}';

            protected string $name = 'user_profile';

            protected string $description = 'User profile data resource';

            protected string $mimeType = 'application/json';

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function read(array $options = []): array
            {
                $userId = $options['id'] ?? 1;

                return [
                    'contents' => [
                        [
                            'uri' => str_replace('{id}', $userId, $this->uri),
                            'mimeType' => $this->mimeType,
                            'text' => json_encode([
                                'id' => $userId,
                                'name' => 'John Doe',
                                'email' => 'john@example.com',
                                'created_at' => '2024-01-01',
                            ]),
                        ],
                    ],
                ];
            }
        };

        // Register the resource
        $this->registry->register('resource', 'user_profile', $resource, [
            'description' => 'Access user profile data',
            'cacheable' => true,
        ]);

        // Verify registration
        $this->assertTrue($this->registry->has('resource', 'user_profile'));
        $this->assertEquals(1, $this->registry->count('resource'));

        // Retrieve and verify metadata
        $metadata = $this->registry->getMetadata('resource', 'user_profile');
        $this->assertEquals('Access user profile data', $metadata['description']);
        $this->assertTrue($metadata['cacheable']);

        // Test resource reading
        $retrievedResource = $this->registry->get('resource', 'user_profile');
        $this->assertInstanceOf(McpResource::class, $retrievedResource);

        $content = $this->resourceRegistry->readResource('user_profile', ['id' => 42]);
        $this->assertArrayHasKey('contents', $content);
        $this->assertStringContainsString('user://profile/42', $content['contents'][0]['uri']);

        // Test unregistration
        $this->assertTrue($this->registry->unregister('resource', 'user_profile'));
        $this->assertFalse($this->registry->has('resource', 'user_profile'));
    }

    /**
     * Test complete prompt registration workflow.
     */
    public function test_prompt_registration_workflow(): void
    {
        // Create a test prompt
        $prompt = new class('email_template') extends McpPrompt
        {
            protected string $name = 'email_template';

            protected string $description = 'Generate professional email templates';

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function getMessages(array $arguments): array
            {
                $type = $arguments['type'] ?? 'general';
                $recipient = $arguments['recipient'] ?? 'recipient';
                $subject = $arguments['subject'] ?? 'Subject';

                $templates = [
                    'welcome' => "Dear {$recipient},\n\nWelcome to our service! We're excited to have you on board.",
                    'reminder' => "Dear {$recipient},\n\nThis is a friendly reminder about: {$subject}",
                    'general' => "Dear {$recipient},\n\nRegarding: {$subject}\n\n[Your message here]",
                ];

                return [
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => [
                                'type' => 'text',
                                'text' => 'You are a professional email writer.',
                            ],
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => $templates[$type] ?? $templates['general'],
                            ],
                        ],
                    ],
                ];
            }
        };

        // Register the prompt
        $this->registry->register('prompt', 'email_template', $prompt, [
            'description' => 'Professional email template generator',
            'categories' => ['communication', 'business'],
        ]);

        // Verify registration
        $this->assertTrue($this->registry->has('prompt', 'email_template'));
        $this->assertEquals(1, $this->registry->count('prompt'));

        // Retrieve and verify metadata
        $metadata = $this->registry->getMetadata('prompt', 'email_template');
        $this->assertEquals('Professional email template generator', $metadata['description']);
        $this->assertContains('communication', $metadata['categories']);

        // Test prompt generation
        $retrievedPrompt = $this->registry->get('prompt', 'email_template');
        $this->assertInstanceOf(McpPrompt::class, $retrievedPrompt);

        // Test direct prompt execution
        $messages = $retrievedPrompt->getMessages([
            'type' => 'welcome',
            'recipient' => 'Alice',
        ]);
        $this->assertArrayHasKey('messages', $messages);
        $this->assertCount(2, $messages['messages']);
        $this->assertStringContainsString('Alice', $messages['messages'][1]['content']['text']);

        // Test unregistration
        $this->assertTrue($this->registry->unregister('prompt', 'email_template'));
        $this->assertFalse($this->registry->has('prompt', 'email_template'));
    }

    /**
     * Test registration validation errors.
     */
    public function test_registration_validation_errors(): void
    {
        // Test empty name
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Component name cannot be empty');
        $this->registry->register('tool', '', $this->createTestTool('test'));
    }

    /**
     * Test duplicate registration prevention.
     */
    public function test_duplicate_registration_prevention(): void
    {
        $tool = $this->createTestTool('duplicate_test');

        // First registration should succeed
        $this->registry->register('tool', 'duplicate_test', $tool);
        $this->assertTrue($this->registry->has('tool', 'duplicate_test'));

        // Second registration should fail
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Component 'duplicate_test' of type 'tool' is already registered");
        $this->registry->register('tool', 'duplicate_test', $tool);
    }

    /**
     * Test invalid handler validation.
     */
    public function test_invalid_handler_validation(): void
    {
        // Test with non-existent class
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Handler class 'NonExistentClass' does not exist");
        $this->registry->register('tool', 'test', 'NonExistentClass');
    }

    /**
     * Test invalid handler type validation.
     */
    public function test_invalid_handler_type_validation(): void
    {
        // Create a class that doesn't extend the required base class
        $invalidHandler = new class
        {
            public function execute(array $args): array
            {
                return [];
            }
        };

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Handler must extend '.McpTool::class);
        $this->registry->register('tool', 'test', get_class($invalidHandler));
    }

    /**
     * Test backward compatibility methods.
     */
    public function test_backward_compatibility_methods(): void
    {
        $tool = $this->createTestTool('legacy_tool');
        $resource = $this->createTestResource('legacy_resource');
        $prompt = $this->createTestPrompt('legacy_prompt');

        // Test legacy registration methods
        $this->registry->registerTool('legacy_tool', $tool, ['version' => '1.0']);
        $this->registry->registerResource('legacy_resource', $resource, ['version' => '1.0']);
        $this->registry->registerPrompt('legacy_prompt', $prompt, ['version' => '1.0']);

        // Test legacy has methods
        $this->assertTrue($this->registry->hasTool('legacy_tool'));
        $this->assertTrue($this->registry->hasResource('legacy_resource'));
        $this->assertTrue($this->registry->hasPrompt('legacy_prompt'));

        // Test legacy get methods
        $this->assertSame($tool, $this->registry->getTool('legacy_tool'));
        $this->assertSame($resource, $this->registry->getResource('legacy_resource'));
        $this->assertSame($prompt, $this->registry->getPrompt('legacy_prompt'));

        // Test legacy list methods
        $tools = $this->registry->listTools();
        $resources = $this->registry->listResources();
        $prompts = $this->registry->listPrompts();

        $this->assertArrayHasKey('legacy_tool', $tools);
        $this->assertArrayHasKey('legacy_resource', $resources);
        $this->assertArrayHasKey('legacy_prompt', $prompts);

        // Test legacy unregister methods
        $this->assertTrue($this->registry->unregisterTool('legacy_tool'));
        $this->assertTrue($this->registry->unregisterResource('legacy_resource'));
        $this->assertTrue($this->registry->unregisterPrompt('legacy_prompt'));

        $this->assertFalse($this->registry->hasTool('legacy_tool'));
        $this->assertFalse($this->registry->hasResource('legacy_resource'));
        $this->assertFalse($this->registry->hasPrompt('legacy_prompt'));
    }

    /**
     * Test registry initialization.
     */
    public function test_registry_initialization(): void
    {
        // Initialize the registry
        $this->registry->initialize();

        // Initialize again should be idempotent
        $this->registry->initialize();

        // Registry should still work after initialization
        $tool = $this->createTestTool('init_test');
        $this->registry->register('tool', 'init_test', $tool);
        $this->assertTrue($this->registry->has('tool', 'init_test'));
    }

    /**
     * Test registry clear functionality.
     */
    public function test_registry_clear(): void
    {
        // Register multiple components
        $this->registry->register('tool', 'tool1', $this->createTestTool('tool1'));
        $this->registry->register('resource', 'resource1', $this->createTestResource('resource1'));
        $this->registry->register('prompt', 'prompt1', $this->createTestPrompt('prompt1'));

        $this->assertEquals(1, $this->registry->count('tool'));
        $this->assertEquals(1, $this->registry->count('resource'));
        $this->assertEquals(1, $this->registry->count('prompt'));

        // Clear all registries
        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count('tool'));
        $this->assertEquals(0, $this->registry->count('resource'));
        $this->assertEquals(0, $this->registry->count('prompt'));
        $this->assertEquals(0, $this->registry->count());
    }

    /**
     * Test getting type registries.
     */
    public function test_get_type_registries(): void
    {
        // Get individual type registry
        $toolRegistry = $this->registry->getTypeRegistry('tool');
        $this->assertInstanceOf(ToolRegistry::class, $toolRegistry);

        $resourceRegistry = $this->registry->getTypeRegistry('resource');
        $this->assertInstanceOf(ResourceRegistry::class, $resourceRegistry);

        $promptRegistry = $this->registry->getTypeRegistry('prompt');
        $this->assertInstanceOf(PromptRegistry::class, $promptRegistry);

        // Invalid type should return null
        $this->assertNull($this->registry->getTypeRegistry('invalid'));

        // Get all type registries
        $registries = $this->registry->getTypeRegistries();
        $this->assertCount(3, $registries);
        $this->assertArrayHasKey('tool', $registries);
        $this->assertArrayHasKey('resource', $registries);
        $this->assertArrayHasKey('prompt', $registries);
    }

    /**
     * Test server capabilities.
     */
    public function test_server_capabilities(): void
    {
        $capabilities = $this->registry->getCapabilities();

        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);
        $this->assertArrayHasKey('logging', $capabilities);

        $this->assertTrue($capabilities['tools']['listChanged']);
        $this->assertTrue($capabilities['resources']['subscribe']);
        $this->assertTrue($capabilities['resources']['listChanged']);
        $this->assertTrue($capabilities['prompts']['listChanged']);
    }

    /**
     * Test component discovery configuration.
     */
    public function test_component_discovery_configuration(): void
    {
        // Test default configuration
        $config = $this->discovery->getConfig();
        $this->assertTrue($config['recursive']);
        $this->assertContains('*.php', $config['file_patterns']);

        // Test setting configuration
        $this->discovery->setConfig([
            'recursive' => false,
            'exclude_patterns' => ['*Test.php', '*Stub.php'],
        ]);

        $newConfig = $this->discovery->getConfig();
        $this->assertFalse($newConfig['recursive']);
        $this->assertContains('*Test.php', $newConfig['exclude_patterns']);
    }

    /**
     * Test component discovery filters.
     */
    public function test_component_discovery_filters(): void
    {
        // Add filters
        $filter1 = function ($path) {
            return true;
        };
        $filter2 = function ($path) {
            return false;
        };

        $this->discovery->addFilter($filter1);
        $this->discovery->addFilter($filter2);

        $filters = $this->discovery->getFilters();
        $this->assertCount(2, $filters);
        $this->assertSame($filter1, $filters[0]);
        $this->assertSame($filter2, $filters[1]);
    }

    /**
     * Test getting supported component types.
     */
    public function test_supported_component_types(): void
    {
        $types = $this->discovery->getSupportedTypes();

        $this->assertEquals(['tools', 'resources', 'prompts'], $types);
    }

    /**
     * Test complete registration count across all types.
     */
    public function test_complete_registration_count(): void
    {
        // Register multiple components of each type
        $this->registry->register('tool', 'tool1', $this->createTestTool('tool1'));
        $this->registry->register('tool', 'tool2', $this->createTestTool('tool2'));
        $this->registry->register('resource', 'resource1', $this->createTestResource('resource1'));
        $this->registry->register('resource', 'resource2', $this->createTestResource('resource2'));
        $this->registry->register('resource', 'resource3', $this->createTestResource('resource3'));
        $this->registry->register('prompt', 'prompt1', $this->createTestPrompt('prompt1'));

        // Test individual type counts
        $this->assertEquals(2, $this->registry->count('tool'));
        $this->assertEquals(3, $this->registry->count('resource'));
        $this->assertEquals(1, $this->registry->count('prompt'));

        // Test total count
        $this->assertEquals(6, $this->registry->count());

        // Test invalid type count
        $this->assertEquals(0, $this->registry->count('invalid'));
    }

    /**
     * Test get all components of each type.
     */
    public function test_get_all_components(): void
    {
        // Register components
        $tool1 = $this->createTestTool('tool1');
        $tool2 = $this->createTestTool('tool2');
        $resource1 = $this->createTestResource('resource1');
        $prompt1 = $this->createTestPrompt('prompt1');

        $this->registry->register('tool', 'tool1', $tool1);
        $this->registry->register('tool', 'tool2', $tool2);
        $this->registry->register('resource', 'resource1', $resource1);
        $this->registry->register('prompt', 'prompt1', $prompt1);

        // Get all tools
        $tools = $this->registry->getAll('tool');
        $this->assertCount(2, $tools);
        $this->assertArrayHasKey('tool1', $tools);
        $this->assertArrayHasKey('tool2', $tools);

        // Get all resources
        $resources = $this->registry->getAll('resource');
        $this->assertCount(1, $resources);
        $this->assertArrayHasKey('resource1', $resources);

        // Get all prompts
        $prompts = $this->registry->getAll('prompt');
        $this->assertCount(1, $prompts);
        $this->assertArrayHasKey('prompt1', $prompts);

        // Invalid type returns empty array
        $invalid = $this->registry->getAll('invalid');
        $this->assertEquals([], $invalid);
    }

    /**
     * Test the all() method returns all components.
     */
    public function test_all_method_returns_all_components(): void
    {
        // Register components
        $tool = $this->createTestTool('tool1');
        $resource = $this->createTestResource('resource1');
        $prompt = $this->createTestPrompt('prompt1');

        $this->registry->register('tool', 'tool1', $tool);
        $this->registry->register('resource', 'resource1', $resource);
        $this->registry->register('prompt', 'prompt1', $prompt);

        // Get all components
        $all = $this->registry->all();

        $this->assertCount(3, $all);
        $this->assertArrayHasKey('tool1', $all);
        $this->assertArrayHasKey('resource1', $all);
        $this->assertArrayHasKey('prompt1', $all);
    }
}
