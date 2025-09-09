<?php

/**
 * @file tests/Unit/Traits/ManagesCapabilitiesTest.php
 *
 * @description Unit tests for ManagesCapabilities trait
 *
 * @ticket BASECLASSES-014
 *
 * @epic BaseClasses
 *
 * @sprint Sprint-2
 */

namespace JTD\LaravelMCP\Tests\Unit\Traits;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('base-classes')]
#[Group('manages-capabilities')]
#[Group('ticket-014')]
class ManagesCapabilitiesTest extends TestCase
{
    private $traitObject;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->traitObject = new class
        {
            use ManagesCapabilities;
        };
    }

    #[Test]
    public function it_gets_default_capabilities()
    {
        $capabilities = $this->traitObject->getCapabilities();
        $this->assertIsArray($capabilities);
        $this->assertEmpty($capabilities); // No default for unknown type
    }

    #[Test]
    public function it_adds_capability()
    {
        $this->traitObject->addCapability('custom');
        
        $this->assertTrue($this->traitObject->hasCapability('custom'));
        $this->assertContains('custom', $this->traitObject->getCapabilities());
    }

    #[Test]
    public function it_prevents_duplicate_capabilities()
    {
        $this->traitObject->addCapability('custom');
        $this->traitObject->addCapability('custom');
        
        $capabilities = $this->traitObject->getCapabilities();
        $count = array_count_values($capabilities);
        $this->assertEquals(1, $count['custom']);
    }

    #[Test]
    public function it_removes_capability()
    {
        $this->traitObject->addCapability('custom');
        $this->traitObject->addCapability('another');
        
        $this->traitObject->removeCapability('custom');
        
        $this->assertFalse($this->traitObject->hasCapability('custom'));
        $this->assertTrue($this->traitObject->hasCapability('another'));
    }

    #[Test]
    public function it_sets_capabilities_replacing_existing()
    {
        $this->traitObject->addCapability('old');
        $this->traitObject->setCapabilities(['new1', 'new2']);
        
        $capabilities = $this->traitObject->getCapabilities();
        $this->assertContains('new1', $capabilities);
        $this->assertContains('new2', $capabilities);
        $this->assertNotContains('old', $capabilities);
    }

    #[Test]
    public function it_gets_tool_default_capabilities()
    {
        $tool = new class extends McpTool
        {
            use ManagesCapabilities;

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $capabilities = $tool->getCapabilities();
        $this->assertContains('execute', $capabilities);
    }

    #[Test]
    public function it_gets_resource_default_capabilities()
    {
        $resource = new class extends McpResource
        {
            use ManagesCapabilities;

            public function read(array $params): mixed
            {
                return [];
            }

            public function list(array $params = []): array
            {
                return [];
            }
        };

        $capabilities = $resource->getCapabilities();
        $this->assertContains('read', $capabilities);
        $this->assertContains('list', $capabilities);
    }

    #[Test]
    public function it_gets_prompt_default_capabilities()
    {
        $prompt = new class extends McpPrompt
        {
            use ManagesCapabilities;

            protected function generateContent(array $arguments): string
            {
                return 'test';
            }
        };

        $capabilities = $prompt->getCapabilities();
        $this->assertContains('get', $capabilities);
    }

    #[Test]
    public function it_checks_capability_existence()
    {
        $this->traitObject->addCapability('test');
        
        $this->assertTrue($this->traitObject->hasCapability('test'));
        $this->assertFalse($this->traitObject->hasCapability('nonexistent'));
    }

    #[Test]
    public function it_gets_capabilities_array()
    {
        $this->traitObject->addCapability('test');
        $array = $this->traitObject->getCapabilitiesArray();
        
        $this->assertArrayHasKey('capabilities', $array);
        $this->assertArrayHasKey('supports', $array);
        $this->assertIsArray($array['capabilities']);
        $this->assertIsArray($array['supports']);
    }

    #[Test]
    public function it_gets_supported_operations_for_tool()
    {
        $tool = new class extends McpTool
        {
            use ManagesCapabilities;

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $operations = $this->callProtectedMethod($tool, 'getSupportedOperations');
        $this->assertContains('execute', $operations);
    }

    #[Test]
    public function it_gets_supported_operations_for_resource()
    {
        $resource = new class extends McpResource
        {
            use ManagesCapabilities;

            public function read(array $params): mixed
            {
                return [];
            }

            public function list(array $params = []): array
            {
                return [];
            }
        };

        $operations = $this->callProtectedMethod($resource, 'getSupportedOperations');
        $this->assertContains('read', $operations);
        $this->assertContains('list', $operations);
    }

    #[Test]
    public function it_checks_operation_support()
    {
        $this->traitObject->addCapability('execute');
        
        $this->assertTrue($this->traitObject->supportsOperation('execute'));
        $this->assertFalse($this->traitObject->supportsOperation('read'));
    }

    #[Test]
    public function it_enables_subscription_capability()
    {
        $this->traitObject->enableSubscription();
        
        $this->assertTrue($this->traitObject->hasCapability('subscribe'));
        $this->assertTrue($this->traitObject->supportsSubscription());
    }

    #[Test]
    public function it_disables_subscription_capability()
    {
        $this->traitObject->enableSubscription();
        $this->traitObject->disableSubscription();
        
        $this->assertFalse($this->traitObject->hasCapability('subscribe'));
        $this->assertFalse($this->traitObject->supportsSubscription());
    }

    #[Test]
    public function it_gets_capability_metadata()
    {
        $this->traitObject->addCapability('custom');
        $metadata = $this->traitObject->getCapabilityMetadata();
        
        $this->assertArrayHasKey('type', $metadata);
        $this->assertArrayHasKey('capabilities', $metadata);
        $this->assertArrayHasKey('operations', $metadata);
        $this->assertArrayHasKey('default_capabilities', $metadata);
        $this->assertArrayHasKey('custom_capabilities', $metadata);
        
        $this->assertContains('custom', $metadata['custom_capabilities']);
    }

    #[Test]
    public function it_detects_tool_component_type()
    {
        $tool = new class extends McpTool
        {
            use ManagesCapabilities;

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $type = $this->callProtectedMethod($tool, 'getComponentType');
        $this->assertEquals('tool', $type);
    }

    #[Test]
    public function it_detects_resource_component_type()
    {
        $resource = new class extends McpResource
        {
            use ManagesCapabilities;

            public function read(array $params): mixed
            {
                return [];
            }

            public function list(array $params = []): array
            {
                return [];
            }
        };

        $type = $this->callProtectedMethod($resource, 'getComponentType');
        $this->assertEquals('resource', $type);
    }

    #[Test]
    public function it_detects_prompt_component_type()
    {
        $prompt = new class extends McpPrompt
        {
            use ManagesCapabilities;

            protected function generateContent(array $arguments): string
            {
                return 'test';
            }
        };

        $type = $this->callProtectedMethod($prompt, 'getComponentType');
        $this->assertEquals('prompt', $type);
    }

    #[Test]
    public function it_validates_tool_capabilities()
    {
        $tool = new class extends McpTool
        {
            use ManagesCapabilities;

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        // Valid capability
        $tool->addCapability('execute');
        $errors = $tool->validateCapabilities();
        $this->assertEmpty($errors);

        // Invalid capability for tool
        $tool->addCapability('read');
        $errors = $tool->validateCapabilities();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("Invalid capability 'read' for tool", $errors[0]);
    }

    #[Test]
    public function it_validates_resource_capabilities()
    {
        $resource = new class extends McpResource
        {
            use ManagesCapabilities;

            public function read(array $params): mixed
            {
                return [];
            }

            public function list(array $params = []): array
            {
                return [];
            }
        };

        // Valid capabilities
        $resource->addCapability('subscribe');
        $errors = $resource->validateCapabilities();
        $this->assertEmpty($errors);

        // Invalid capability for resource
        $resource->addCapability('execute');
        $errors = $resource->validateCapabilities();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("Invalid capability 'execute' for resource", $errors[0]);
    }

    #[Test]
    public function it_validates_prompt_capabilities()
    {
        $prompt = new class extends McpPrompt
        {
            use ManagesCapabilities;

            protected function generateContent(array $arguments): string
            {
                return 'test';
            }
        };

        // Valid capability
        $prompt->addCapability('get');
        $errors = $prompt->validateCapabilities();
        $this->assertEmpty($errors);

        // Invalid capability for prompt
        $prompt->addCapability('execute');
        $errors = $prompt->validateCapabilities();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString("Invalid capability 'execute' for prompt", $errors[0]);
    }

    #[Test]
    public function it_handles_unknown_component_type()
    {
        $unknown = new class
        {
            use ManagesCapabilities;
        };

        $type = $this->callProtectedMethod($unknown, 'getComponentType');
        $this->assertEquals('unknown', $type);

        $errors = $unknown->validateCapabilities();
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Unknown component type', $errors[0]);
    }

    #[Test]
    public function it_merges_default_and_custom_capabilities()
    {
        $tool = new class extends McpTool
        {
            use ManagesCapabilities;

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $tool->addCapability('custom');
        $capabilities = $tool->getCapabilities();
        
        $this->assertContains('execute', $capabilities); // Default
        $this->assertContains('custom', $capabilities); // Custom
    }

    #[Test]
    public function it_returns_self_for_fluent_interface()
    {
        $result = $this->traitObject->addCapability('test');
        $this->assertSame($this->traitObject, $result);

        $result = $this->traitObject->removeCapability('test');
        $this->assertSame($this->traitObject, $result);

        $result = $this->traitObject->setCapabilities([]);
        $this->assertSame($this->traitObject, $result);

        $result = $this->traitObject->enableSubscription();
        $this->assertSame($this->traitObject, $result);

        $result = $this->traitObject->disableSubscription();
        $this->assertSame($this->traitObject, $result);
    }

    /**
     * Helper method to call protected methods
     */
    protected function callProtectedMethod($object, string $method, array $params = [])
    {
        $reflection = new \ReflectionObject($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        
        return $method->invokeArgs($object, $params);
    }
}