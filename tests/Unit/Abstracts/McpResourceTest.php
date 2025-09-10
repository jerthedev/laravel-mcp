<?php

/**
 * @file tests/Unit/Abstracts/McpResourceTest.php
 *
 * @description Unit tests for McpResource abstract base class
 *
 * @ticket BASECLASSES-014
 *
 * @epic BaseClasses
 *
 * @sprint Sprint-2
 */

namespace JTD\LaravelMCP\Tests\Unit\Abstracts;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Abstracts\McpResource;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use JTD\LaravelMCP\Tests\TestCase;

#[Group('base-classes')]
#[Group('mcp-resource')]
#[Group('ticket-014')]
class McpResourceTest extends TestCase
{
    private McpResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a concrete implementation for testing
        $this->resource = new class extends McpResource
        {
            protected string $name = 'test_resource';

            protected string $description = 'A test resource';

            protected string $uriTemplate = 'test/{id}';

            protected function customRead(array $params): mixed
            {
                return ['id' => $params['id'] ?? 1, 'data' => 'test data'];
            }

            protected function customList(array $params): array
            {
                return [
                    ['id' => 1, 'name' => 'Item 1'],
                    ['id' => 2, 'name' => 'Item 2'],
                ];
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_gets_resource_name()
    {
        $this->assertEquals('test_resource', $this->resource->getName());
    }

    #[Test]
    public function it_generates_name_from_class_when_not_set()
    {
        $resource = new class extends McpResource
        {
            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->assertIsString($resource->getName());
        $this->assertNotEmpty($resource->getName());
    }

    #[Test]
    public function it_gets_resource_description()
    {
        $this->assertEquals('A test resource', $this->resource->getDescription());
    }

    #[Test]
    public function it_gets_default_description_when_not_set()
    {
        $resource = new class extends McpResource
        {
            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->assertEquals('MCP Resource', $resource->getDescription());
    }

    #[Test]
    public function it_gets_uri_template()
    {
        $this->assertEquals('test/{id}', $this->resource->getUriTemplate());
    }

    #[Test]
    public function it_generates_uri_template_when_not_set()
    {
        $resource = new class extends McpResource
        {
            protected string $name = 'my_resource';

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->assertEquals('my_resource/*', $resource->getUriTemplate());
    }

    #[Test]
    public function it_reads_resource_with_custom_implementation()
    {
        $result = $this->resource->read(['id' => 123]);

        $this->assertEquals(['id' => 123, 'data' => 'test data'], $result);
    }

    #[Test]
    public function it_lists_resources_with_custom_implementation()
    {
        $result = $this->resource->list();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Item 1', $result[0]['name']);
    }

    #[Test]
    public function it_throws_exception_when_authorization_fails_for_read()
    {
        $resource = new class extends McpResource
        {
            protected bool $requiresAuth = true;

            protected function authorize(string $action, array $params): bool
            {
                return false;
            }

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->expectException(UnauthorizedHttpException::class);
        $resource->read([]);
    }

    #[Test]
    public function it_throws_exception_when_authorization_fails_for_list()
    {
        $resource = new class extends McpResource
        {
            protected bool $requiresAuth = true;

            protected function authorize(string $action, array $params): bool
            {
                return false;
            }

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->expectException(UnauthorizedHttpException::class);
        $resource->list([]);
    }

    #[Test]
    public function it_throws_exception_for_unsupported_subscription()
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Resource does not support subscriptions');

        $this->resource->subscribe([]);
    }

    #[Test]
    public function it_handles_subscription_when_supported()
    {
        $resource = new class extends McpResource
        {
            protected function supportsSubscription(): bool
            {
                return true;
            }

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $result = $resource->subscribe([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('subscribed', $result);
        $this->assertTrue($result['subscribed']);
    }

    #[Test]
    public function it_validates_parameters_for_read()
    {
        $resource = new class extends McpResource
        {
            protected function getReadValidationRules(): array
            {
                return [
                    'id' => 'required|integer',
                ];
            }

            protected function customRead(array $params): mixed
            {
                return ['id' => $params['id']];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        // Should validate successfully with valid params
        $result = $resource->read(['id' => 123]);
        $this->assertEquals(['id' => 123], $result);

        // Should throw validation exception with invalid params
        try {
            $resource->read(['id' => 'not-an-integer']);
            $this->fail('Should have thrown validation exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('validation', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_validates_parameters_for_list()
    {
        $resource = new class extends McpResource
        {
            protected function getListValidationRules(): array
            {
                return [
                    'page' => 'nullable|integer|min:1',
                    'per_page' => 'nullable|integer|min:1|max:100',
                ];
            }

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return ['page' => $params['page'] ?? 1];
            }
        };

        // Should validate successfully with valid params
        $result = $resource->list(['page' => 2]);
        $this->assertEquals(['page' => 2], $result);
    }

    #[Test]
    public function it_converts_to_array_representation()
    {
        $array = $this->resource->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('uri', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('mimeType', $array);
        $this->assertEquals('test/{id}', $array['uri']);
        $this->assertEquals('test_resource', $array['name']);
        $this->assertEquals('application/json', $array['mimeType']);
    }

    #[Test]
    public function it_formats_content_for_mcp_response()
    {
        $reflection = new \ReflectionObject($this->resource);
        $method = $reflection->getMethod('formatContent');
        $method->setAccessible(true);

        $content = ['test' => 'data'];
        $formatted = $method->invoke($this->resource, $content);

        $this->assertArrayHasKey('contents', $formatted);
        $this->assertIsArray($formatted['contents']);
        $this->assertArrayHasKey('uri', $formatted['contents'][0]);
        $this->assertArrayHasKey('mimeType', $formatted['contents'][0]);
        $this->assertArrayHasKey('text', $formatted['contents'][0]);
        $this->assertEquals('application/json', $formatted['contents'][0]['mimeType']);
        $this->assertEquals(json_encode($content), $formatted['contents'][0]['text']);
    }

    #[Test]
    public function it_handles_boot_lifecycle()
    {
        $resource = new class extends McpResource
        {
            public bool $booted = false;

            protected function boot(): void
            {
                $this->booted = true;
            }

            protected function customRead(array $params): mixed
            {
                return [];
            }

            protected function customList(array $params): array
            {
                return [];
            }
        };

        $this->assertTrue($resource->booted);
    }

    #[Test]
    public function it_does_not_require_auth_by_default()
    {
        $result = $this->resource->read(['id' => 1]);
        $this->assertEquals(['id' => 1, 'data' => 'test data'], $result);

        $result = $this->resource->list();
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    #[Test]
    public function it_uses_container_for_dependency_injection()
    {
        $reflection = new \ReflectionObject($this->resource);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        $container = $property->getValue($this->resource);

        $this->assertInstanceOf(Container::class, $container);
    }

    #[Test]
    public function it_throws_exception_when_custom_read_not_implemented()
    {
        $resource = new class extends McpResource
        {
            // Not implementing customRead
        };

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Custom read method not implemented');

        $resource->read([]);
    }

    #[Test]
    public function it_throws_exception_when_custom_list_not_implemented()
    {
        $resource = new class extends McpResource
        {
            // Not implementing customList
        };

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Custom list method not implemented');

        $resource->list([]);
    }
}
