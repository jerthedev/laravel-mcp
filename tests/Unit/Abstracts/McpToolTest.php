<?php

/**
 * @file tests/Unit/Abstracts/McpToolTest.php
 *
 * @description Unit tests for McpTool abstract base class
 *
 * @ticket BASECLASSES-014
 *
 * @epic BaseClasses
 *
 * @sprint Sprint-2
 */

namespace JTD\LaravelMCP\Tests\Unit\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use JTD\LaravelMCP\Abstracts\McpTool;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use JTD\LaravelMCP\Tests\TestCase;

#[Group('base-classes')]
#[Group('mcp-tool')]
#[Group('ticket-014')]
class McpToolTest extends TestCase
{
    private McpTool $tool;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a concrete implementation for testing
        $this->tool = new class extends McpTool
        {
            protected string $name = 'test_tool';

            protected string $description = 'A test tool';

            protected array $parameterSchema = [
                'param1' => [
                    'type' => 'string',
                    'description' => 'First parameter',
                    'required' => true,
                ],
                'param2' => [
                    'type' => 'integer',
                    'description' => 'Second parameter',
                    'required' => false,
                ],
            ];

            protected function handle(array $parameters): mixed
            {
                return ['result' => 'success', 'params' => $parameters];
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_gets_tool_name()
    {
        $this->assertEquals('test_tool', $this->tool->getName());
    }

    #[Test]
    public function it_generates_name_from_class_when_not_set()
    {
        $tool = new class extends McpTool
        {
            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $this->assertIsString($tool->getName());
        $this->assertNotEmpty($tool->getName());
    }

    #[Test]
    public function it_gets_tool_description()
    {
        $this->assertEquals('A test tool', $this->tool->getDescription());
    }

    #[Test]
    public function it_gets_default_description_when_not_set()
    {
        $tool = new class extends McpTool
        {
            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $this->assertEquals('MCP Tool', $tool->getDescription());
    }

    #[Test]
    public function it_gets_input_schema()
    {
        $schema = $this->tool->getInputSchema();

        $this->assertIsArray($schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('param1', $schema['required']);
        $this->assertNotContains('param2', $schema['required']);
    }

    #[Test]
    public function it_executes_tool_with_valid_parameters()
    {
        $params = ['param1' => 'test', 'param2' => 42];
        $result = $this->tool->execute($params);

        $this->assertEquals(['result' => 'success', 'params' => $params], $result);
    }

    #[Test]
    public function it_throws_exception_when_authorization_fails()
    {
        $tool = new class extends McpTool
        {
            protected bool $requiresAuth = true;

            protected function handle(array $parameters): mixed
            {
                return [];
            }

            protected function authorize(array $parameters): bool
            {
                return false;
            }
        };

        $this->expectException(UnauthorizedHttpException::class);
        $tool->execute([]);
    }

    #[Test]
    public function it_validates_parameters_before_execution()
    {
        $tool = new class extends McpTool
        {
            protected array $parameterSchema = [
                'required_param' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ];

            protected function handle(array $parameters): mixed
            {
                return $parameters;
            }
        };

        // Should throw validation exception for missing required parameter
        try {
            $tool->execute([]);
            $this->fail('Should have thrown validation exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('validation', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_applies_middleware_to_parameters()
    {
        $tool = new class extends McpTool
        {
            protected array $middleware = ['test_middleware'];

            protected function handle(array $parameters): mixed
            {
                return $parameters;
            }

            protected function applyMiddleware(string $middleware, array $params): array
            {
                // Mock middleware that adds a flag
                $params['middleware_applied'] = true;

                return $params;
            }
        };

        $result = $tool->execute(['test' => 'value']);
        $this->assertArrayHasKey('middleware_applied', $result);
        $this->assertTrue($result['middleware_applied']);
    }

    #[Test]
    public function it_resolves_dependencies_from_container()
    {
        $tool = new class extends McpTool
        {
            protected function handle(array $parameters): mixed
            {
                $config = $this->make('config');

                return ['has_config' => $config !== null];
            }
        };

        $result = $tool->execute([]);
        $this->assertArrayHasKey('has_config', $result);
        $this->assertTrue($result['has_config']);
    }

    #[Test]
    public function it_converts_to_array_representation()
    {
        $array = $this->tool->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('inputSchema', $array);
        $this->assertEquals('test_tool', $array['name']);
        $this->assertEquals('A test tool', $array['description']);
    }

    #[Test]
    public function it_handles_boot_lifecycle()
    {
        $tool = new class extends McpTool
        {
            public bool $booted = false;

            protected function boot(): void
            {
                $this->booted = true;
            }

            protected function handle(array $parameters): mixed
            {
                return [];
            }
        };

        $this->assertTrue($tool->booted);
    }

    #[Test]
    public function it_handles_empty_parameter_schema()
    {
        $tool = new class extends McpTool
        {
            protected function handle(array $parameters): mixed
            {
                return $parameters;
            }
        };

        $schema = $tool->getInputSchema();
        $this->assertEmpty($schema['properties']);
        $this->assertEmpty($schema['required']);

        $result = $tool->execute(['any' => 'param']);
        $this->assertEquals(['any' => 'param'], $result);
    }

    #[Test]
    public function it_does_not_require_auth_by_default()
    {
        $tool = new class extends McpTool
        {
            protected function handle(array $parameters): mixed
            {
                return ['executed' => true];
            }
        };

        $result = $tool->execute([]);
        $this->assertEquals(['executed' => true], $result);
    }

    #[Test]
    public function it_uses_validation_factory_for_parameter_validation()
    {
        // This test verifies that the validation factory is properly initialized
        $reflection = new \ReflectionObject($this->tool);
        $property = $reflection->getProperty('validator');
        $property->setAccessible(true);
        $validator = $property->getValue($this->tool);

        $this->assertInstanceOf(ValidationFactory::class, $validator);
    }

    #[Test]
    public function it_uses_container_for_dependency_injection()
    {
        // This test verifies that the container is properly initialized
        $reflection = new \ReflectionObject($this->tool);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $container = $property->getValue($this->tool);

        $this->assertInstanceOf(Container::class, $container);
    }
}
