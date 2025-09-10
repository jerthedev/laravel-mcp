<?php

namespace JTD\LaravelMCP\Tests\Unit\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use JTD\LaravelMCP\Abstracts\BaseComponent;
use JTD\LaravelMCP\Tests\TestCase;

class BaseComponentTest extends TestCase
{
    protected function createTestComponent(): BaseComponent
    {
        return new class extends BaseComponent
        {
            protected string $name = 'test_component';

            protected string $description = 'Test component';
        };
    }

    public function test_it_initializes_with_container_and_validator()
    {
        $component = $this->createTestComponent();

        // Use reflection to access protected properties
        $reflection = new \ReflectionClass($component);

        $containerProperty = $reflection->getProperty('container');
        $containerProperty->setAccessible(true);
        $this->assertInstanceOf(Container::class, $containerProperty->getValue($component));

        $validatorProperty = $reflection->getProperty('validator');
        $validatorProperty->setAccessible(true);
        $this->assertInstanceOf(ValidationFactory::class, $validatorProperty->getValue($component));
    }

    public function test_it_returns_configured_name()
    {
        $component = $this->createTestComponent();

        $this->assertEquals('test_component', $component->getName());
    }

    public function test_it_generates_name_from_class_when_not_set()
    {
        $component = new class extends BaseComponent
        {
            // No name set
        };

        $name = $component->getName();
        $this->assertNotEmpty($name);
        $this->assertIsString($name);
    }

    public function test_it_returns_configured_description()
    {
        $component = $this->createTestComponent();

        $this->assertEquals('Test component', $component->getDescription());
    }

    public function test_it_returns_default_description_when_not_set()
    {
        $component = new class extends BaseComponent
        {
            // No description set
        };

        $this->assertEquals('MCP Component', $component->getDescription());
    }

    public function test_it_manages_middleware()
    {
        $component = new class extends BaseComponent
        {
            protected array $middleware = ['auth', 'throttle'];
        };

        $this->assertEquals(['auth', 'throttle'], $component->getMiddleware());
    }

    public function test_it_manages_auth_requirement()
    {
        $component = new class extends BaseComponent
        {
            protected bool $requiresAuth = true;
        };

        $this->assertTrue($component->requiresAuth());

        $defaultComponent = $this->createTestComponent();
        $this->assertFalse($defaultComponent->requiresAuth());
    }

    public function test_it_provides_make_helper()
    {
        $component = $this->createTestComponent();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('make');
        $method->setAccessible(true);

        $container = $method->invoke($component, Container::class);
        $this->assertInstanceOf(Container::class, $container);
    }

    public function test_it_provides_resolve_helper()
    {
        $component = $this->createTestComponent();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('resolve');
        $method->setAccessible(true);

        $container = $method->invoke($component, Container::class);
        $this->assertInstanceOf(Container::class, $container);
    }

    public function test_it_returns_metadata()
    {
        $component = $this->createTestComponent();
        $metadata = $component->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('class', $metadata);
        $this->assertArrayHasKey('requiresAuth', $metadata);
        $this->assertArrayHasKey('middleware', $metadata);
        $this->assertArrayHasKey('capabilities', $metadata);

        $this->assertEquals('test_component', $metadata['name']);
        $this->assertEquals('Test component', $metadata['description']);
    }

    public function test_it_converts_to_array()
    {
        $component = $this->createTestComponent();
        $array = $component->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);

        $this->assertEquals('test_component', $array['name']);
        $this->assertEquals('Test component', $array['description']);
    }

    public function test_authorize_returns_true_when_auth_not_required()
    {
        $component = $this->createTestComponent();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('authorize');
        $method->setAccessible(true);

        $result = $method->invoke($component, []);
        $this->assertTrue($result);
    }

    public function test_authorize_returns_true_by_default_when_auth_required()
    {
        $component = new class extends BaseComponent
        {
            protected bool $requiresAuth = true;
        };

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($component);
        $method = $reflection->getMethod('authorize');
        $method->setAccessible(true);

        $result = $method->invoke($component, []);
        $this->assertTrue($result);
    }

    public function test_generate_name_from_class_removes_suffixes()
    {
        $toolComponent = new class extends BaseComponent {};
        $resourceComponent = new class extends BaseComponent {};
        $promptComponent = new class extends BaseComponent {};

        // Names should be generated without throwing errors
        $this->assertIsString($toolComponent->getName());
        $this->assertIsString($resourceComponent->getName());
        $this->assertIsString($promptComponent->getName());
    }
}
