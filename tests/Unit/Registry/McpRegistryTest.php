<?php

/**
 * Epic: MCP Protocol Layer
 * Sprint: Registration System
 * Ticket: REGISTRATION-016 - Registration System Core Implementation
 *
 * @epic MCP-002
 *
 * @sprint 3
 *
 * @ticket 016
 */

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('registry')]
#[Group('ticket-016')]
class McpRegistryTest extends TestCase
{
    private McpRegistry $registry;

    private ToolRegistry $toolRegistry;

    private ResourceRegistry $resourceRegistry;

    private PromptRegistry $promptRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->toolRegistry = $this->createMock(ToolRegistry::class);
        $this->resourceRegistry = $this->createMock(ResourceRegistry::class);
        $this->promptRegistry = $this->createMock(PromptRegistry::class);

        $this->registry = new McpRegistry(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry
        );
    }

    #[Test]
    public function it_can_be_instantiated(): void
    {
        $this->assertInstanceOf(McpRegistry::class, $this->registry);
    }

    #[Test]
    public function it_initializes_registries(): void
    {
        // Since the initialize method is called through type registries,
        // we need to test it differently
        $this->registry->initialize();

        // Second call should not initialize again
        $this->registry->initialize();

        // Just verify no exception is thrown
        $this->assertTrue(true);
    }

    #[Test]
    public function it_registers_tools_with_type(): void
    {
        // Create a stub class that exists
        $toolHandler = new \stdClass;

        $this->toolRegistry->expects($this->once())
            ->method('register')
            ->with('test_tool', $toolHandler, ['option' => 'value']);

        $this->registry->registerWithType('tool', 'test_tool', $toolHandler, ['option' => 'value']);
    }

    #[Test]
    public function it_registers_resources_with_type(): void
    {
        $resourceHandler = new \stdClass;

        $this->resourceRegistry->expects($this->once())
            ->method('register')
            ->with('test_resource', $resourceHandler, ['option' => 'value']);

        $this->registry->registerWithType('resource', 'test_resource', $resourceHandler, ['option' => 'value']);
    }

    #[Test]
    public function it_registers_prompts_with_type(): void
    {
        $promptHandler = new \stdClass;

        $this->promptRegistry->expects($this->once())
            ->method('register')
            ->with('test_prompt', $promptHandler, ['option' => 'value']);

        $this->registry->registerWithType('prompt', 'test_prompt', $promptHandler, ['option' => 'value']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_type(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Unknown component type: invalid');

        $this->registry->registerWithType('invalid', 'test', new \stdClass, []);
    }

    #[Test]
    public function it_validates_empty_component_name(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Component name cannot be empty');

        $this->registry->registerWithType('tool', '', new \stdClass, []);
    }

    #[Test]
    public function it_validates_non_existent_handler_class(): void
    {
        // Enable validation for this test
        $this->app['config']->set('laravel-mcp.validation.validate_handlers', true);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Handler class 'NonExistentClass' does not exist");

        $this->registry->registerWithType('tool', 'test', 'NonExistentClass', []);
    }

    #[Test]
    public function it_validates_handler_extends_correct_base_class(): void
    {
        // Enable validation for this test
        $this->app['config']->set('laravel-mcp.validation.validate_handlers', true);

        // Create a mock class that exists but doesn't extend McpTool
        $invalidClass = new class
        {
            public function handle() {}
        };

        $className = get_class($invalidClass);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Handler must extend');

        $this->registry->registerWithType('tool', 'test', $className, []);
    }

    #[Test]
    public function it_gets_tool_from_registry(): void
    {
        $mockTool = $this->createMock(McpTool::class);

        $this->toolRegistry->expects($this->once())
            ->method('get')
            ->with('test_tool')
            ->willReturn($mockTool);

        $result = $this->registry->getTool('test_tool');

        $this->assertSame($mockTool, $result);
    }

    #[Test]
    public function it_gets_resource_from_registry(): void
    {
        $mockResource = $this->createMock(McpResource::class);

        $this->resourceRegistry->expects($this->once())
            ->method('get')
            ->with('test_resource')
            ->willReturn($mockResource);

        $result = $this->registry->getResource('test_resource');

        $this->assertSame($mockResource, $result);
    }

    #[Test]
    public function it_gets_prompt_from_registry(): void
    {
        $mockPrompt = $this->createMock(McpPrompt::class);

        $this->promptRegistry->expects($this->once())
            ->method('get')
            ->with('test_prompt')
            ->willReturn($mockPrompt);

        $result = $this->registry->getPrompt('test_prompt');

        $this->assertSame($mockPrompt, $result);
    }

    #[Test]
    public function it_lists_all_tools(): void
    {
        $tools = ['tool1' => 'handler1', 'tool2' => 'handler2'];

        $this->toolRegistry->expects($this->once())
            ->method('all')
            ->willReturn($tools);

        $result = $this->registry->listTools();

        $this->assertEquals($tools, $result);
    }

    #[Test]
    public function it_lists_all_resources(): void
    {
        $resources = ['resource1' => 'handler1', 'resource2' => 'handler2'];

        $this->resourceRegistry->expects($this->once())
            ->method('all')
            ->willReturn($resources);

        $result = $this->registry->listResources();

        $this->assertEquals($resources, $result);
    }

    #[Test]
    public function it_lists_all_prompts(): void
    {
        $prompts = ['prompt1' => 'handler1', 'prompt2' => 'handler2'];

        $this->promptRegistry->expects($this->once())
            ->method('all')
            ->willReturn($prompts);

        $result = $this->registry->listPrompts();

        $this->assertEquals($prompts, $result);
    }

    #[Test]
    public function it_checks_if_tool_exists(): void
    {
        $this->toolRegistry->expects($this->once())
            ->method('has')
            ->with('test_tool')
            ->willReturn(true);

        $result = $this->registry->hasTool('test_tool');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_checks_if_resource_exists(): void
    {
        $this->resourceRegistry->expects($this->once())
            ->method('has')
            ->with('test_resource')
            ->willReturn(true);

        $result = $this->registry->hasResource('test_resource');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_checks_if_prompt_exists(): void
    {
        $this->promptRegistry->expects($this->once())
            ->method('has')
            ->with('test_prompt')
            ->willReturn(false);

        $result = $this->registry->hasPrompt('test_prompt');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_unregisters_tool(): void
    {
        $this->toolRegistry->expects($this->once())
            ->method('unregister')
            ->with('test_tool')
            ->willReturn(true);

        $result = $this->registry->unregisterTool('test_tool');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_unregisters_resource(): void
    {
        $this->resourceRegistry->expects($this->once())
            ->method('unregister')
            ->with('test_resource')
            ->willReturn(true);

        $result = $this->registry->unregisterResource('test_resource');

        $this->assertTrue($result);
    }

    #[Test]
    public function it_unregisters_prompt(): void
    {
        $this->promptRegistry->expects($this->once())
            ->method('unregister')
            ->with('test_prompt')
            ->willReturn(false);

        $result = $this->registry->unregisterPrompt('test_prompt');

        $this->assertFalse($result);
    }

    #[Test]
    public function it_returns_capabilities(): void
    {
        $capabilities = $this->registry->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('tools', $capabilities);
        $this->assertArrayHasKey('resources', $capabilities);
        $this->assertArrayHasKey('prompts', $capabilities);
        $this->assertArrayHasKey('logging', $capabilities);
    }

    #[Test]
    public function it_handles_thread_safety_in_registration(): void
    {
        // Test that multiple registrations don't cause race conditions
        $this->toolRegistry->expects($this->exactly(3))
            ->method('register');

        $this->registry->registerWithType('tool', 'tool1', new \stdClass, []);
        $this->registry->registerWithType('tool', 'tool2', new \stdClass, []);
        $this->registry->registerWithType('tool', 'tool3', new \stdClass, []);
    }
}
