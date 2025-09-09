<?php

namespace JTD\LaravelMCP\Tests\Unit\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Server\Handlers\ResourceHandler;
use JTD\LaravelMCP\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for ResourceHandler class.
 *
 * This test suite ensures the ResourceHandler properly handles resource-related MCP operations,
 * including resources/list and resources/read methods, with proper validation, error handling,
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
#[CoversClass(ResourceHandler::class)]
class ResourceHandlerTest extends TestCase
{
    private ResourceRegistry $resourceRegistry;

    private ResourceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resourceRegistry = Mockery::mock(ResourceRegistry::class);
        $this->handler = new ResourceHandler($this->resourceRegistry, false);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructor_sets_dependencies_and_handler_name(): void
    {
        $handler = new ResourceHandler($this->resourceRegistry, true);

        $this->assertTrue($handler->isDebug());
        $this->assertSame('ResourceHandler', $handler->getHandlerName());
    }

    #[Test]
    public function get_supported_methods_returns_resource_methods(): void
    {
        $expected = ['resources/list', 'resources/read'];

        $this->assertSame($expected, $this->handler->getSupportedMethods());
    }

    #[Test]
    public function supports_method_returns_true_for_supported_methods(): void
    {
        $this->assertTrue($this->handler->supportsMethod('resources/list'));
        $this->assertTrue($this->handler->supportsMethod('resources/read'));
    }

    #[Test]
    public function supports_method_returns_false_for_unsupported_methods(): void
    {
        $this->assertFalse($this->handler->supportsMethod('resources/unsupported'));
        $this->assertFalse($this->handler->supportsMethod('tools/list'));
    }

    #[Test]
    public function handle_throws_protocol_exception_for_unsupported_method(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Unsupported method: resources/unsupported');

        $this->handler->handle('resources/unsupported', []);
    }

    #[Test]
    public function handle_resources_list_returns_empty_resources_array_when_no_resources(): void
    {
        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertArrayHasKey('resources', $response);
        $this->assertIsArray($response['resources']);
        $this->assertEmpty($response['resources']);
        $this->assertArrayNotHasKey('nextCursor', $response);
    }

    #[Test]
    public function handle_resources_list_returns_resource_definitions(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('getDescription')->andReturn('Test resource description');
        $mockResource->shouldReceive('getMimeType')->andReturn('text/plain');
        $mockResource->shouldReceive('getMetadata')->andReturn(['version' => '1.0']);

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertArrayHasKey('resources', $response);
        $this->assertCount(1, $response['resources']);

        $resourceDef = $response['resources'][0];
        $this->assertSame('test://resource', $resourceDef['uri']);
        $this->assertSame('test-resource', $resourceDef['name']);
        $this->assertSame('Test resource description', $resourceDef['description']);
        $this->assertSame('text/plain', $resourceDef['mimeType']);
        $this->assertSame('1.0', $resourceDef['version']);
    }

    #[Test]
    public function handle_resources_list_handles_resource_definition_failures_gracefully(): void
    {
        $goodResource = Mockery::mock();
        $goodResource->shouldReceive('getUri')->andReturn('test://good');
        $goodResource->shouldReceive('getDescription')->andReturn('Good resource');
        $goodResource->shouldReceive('getMimeType')->andReturn('text/plain');
        $goodResource->shouldReceive('getMetadata')->andReturn([]);

        $badResource = Mockery::mock();
        $badResource->shouldReceive('getUri')->andThrow(new \RuntimeException('Bad resource'));

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'good-resource' => $goodResource,
                'bad-resource' => $badResource,
            ]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertArrayHasKey('resources', $response);
        $this->assertCount(1, $response['resources']); // Only the good resource
        $this->assertSame('good-resource', $response['resources'][0]['name']);
    }

    #[Test]
    public function handle_resources_list_validates_cursor_parameter(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('resources/list', ['cursor' => 123]); // Should be string
    }

    #[Test]
    public function handle_resources_list_applies_cursor_pagination(): void
    {
        $mockResource1 = $this->createMockResource('Resource 1');
        $mockResource2 = $this->createMockResource('Resource 2');
        $mockResource3 = $this->createMockResource('Resource 3');

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([
                'resource1' => $mockResource1,
                'resource2' => $mockResource2,
                'resource3' => $mockResource3,
            ]);

        // Create cursor for pagination (skip first 1, limit 1)
        $cursor = base64_encode(json_encode(['offset' => 1, 'limit' => 1]));

        $response = $this->handler->handle('resources/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('resources', $response);
        $this->assertCount(1, $response['resources']);
        $this->assertSame('resource2', $response['resources'][0]['name']); // Second resource
    }

    #[Test]
    public function handle_resources_list_includes_next_cursor_when_more_resources_available(): void
    {
        $resources = [];
        for ($i = 1; $i <= 60; $i++) {
            $resources["resource{$i}"] = $this->createMockResource("Resource {$i}");
        }

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn($resources);

        $cursor = base64_encode(json_encode(['offset' => 0, 'limit' => 50]));

        $response = $this->handler->handle('resources/list', ['cursor' => $cursor]);

        $this->assertArrayHasKey('nextCursor', $response);
        $this->assertIsString($response['nextCursor']);

        $decodedCursor = json_decode(base64_decode($response['nextCursor']), true);
        $this->assertSame(50, $decodedCursor['offset']);
        $this->assertSame(50, $decodedCursor['limit']);
    }

    #[Test]
    public function handle_resources_read_validates_required_parameters(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('resources/read', []); // Missing 'uri' parameter
    }

    #[Test]
    public function handle_resources_read_validates_parameter_types(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->handle('resources/read', ['uri' => 123]); // uri should be string
    }

    #[Test]
    public function handle_resources_read_throws_error_for_non_existent_resource(): void
    {
        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn([]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32601);
        $this->expectExceptionMessage('Resource not found: test://non-existent');

        $this->handler->handle('resources/read', ['uri' => 'test://non-existent']);
    }

    #[Test]
    public function handle_resources_read_reads_resource_with_read_method(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('read')
            ->with(['extra' => 'param'])
            ->once()
            ->andReturn('Resource content');

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
            'extra' => 'param',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(1, $response['contents']);
        $this->assertSame([
            'type' => 'text',
            'text' => 'Resource content',
        ], $response['contents'][0]);
    }

    #[Test]
    public function handle_resources_read_reads_resource_with_get_content_method(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('getContent')
            ->with([])
            ->once()
            ->andReturn(['result' => 'data']);

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(1, $response['contents']);
        $this->assertSame([
            'type' => 'text',
            'text' => "{\n    \"result\": \"data\"\n}",
        ], $response['contents'][0]);
    }

    #[Test]
    public function handle_resources_read_reads_resource_with_invoke_method(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('__invoke')
            ->with([])
            ->once()
            ->andReturn('Invoked result');

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(1, $response['contents']);
        $this->assertSame([
            'type' => 'text',
            'text' => 'Invoked result',
        ], $response['contents'][0]);
    }

    #[Test]
    public function handle_resources_read_reads_callable_resource(): void
    {
        $callableResource = function ($params) {
            return 'Callable result: '.($params['input'] ?? 'none');
        };

        // Mock the getUri method by wrapping in an object
        $resourceWrapper = new class($callableResource)
        {
            private $callable;

            public function __construct($callable)
            {
                $this->callable = $callable;
            }

            public function getUri(): string
            {
                return 'test://callable';
            }

            public function __invoke($params)
            {
                return call_user_func($this->callable, $params);
            }
        };

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['callable-resource' => $resourceWrapper]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://callable',
            'input' => 'test',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertSame('Callable result: test', $response['contents'][0]['text']);
    }

    #[Test]
    public function handle_resources_read_throws_error_for_non_readable_resource(): void
    {
        $nonReadableResource = new class
        {
            public function getUri(): string
            {
                return 'test://non-readable';
            }
            // No read, getContent, __invoke methods or callable
        };

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['non-readable' => $nonReadableResource]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32603);
        $this->expectExceptionMessage('Failed to read resource: Resource is not readable');

        $this->handler->handle('resources/read', [
            'uri' => 'test://non-readable',
        ]);
    }

    #[Test]
    public function handle_resources_read_handles_array_content(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('read')
            ->once()
            ->andReturn(['item1', 'item2', ['nested' => 'data']]);

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(3, $response['contents']);
        $this->assertSame('item1', $response['contents'][0]['text']);
        $this->assertSame('item2', $response['contents'][1]['text']);
        $this->assertStringContainsString('nested', $response['contents'][2]['text']);
    }

    #[Test]
    public function handle_resources_read_handles_pre_formatted_content(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('read')
            ->once()
            ->andReturn([
                ['type' => 'text', 'text' => 'Already formatted'],
                ['type' => 'resource', 'resource' => 'resource-data'],
            ]);

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $response = $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
        ]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertCount(2, $response['contents']);
        $this->assertSame(['type' => 'text', 'text' => 'Already formatted'], $response['contents'][0]);
        $this->assertSame(['type' => 'resource', 'resource' => 'resource-data'], $response['contents'][1]);
    }

    #[Test]
    public function handle_resources_read_handles_execution_failures(): void
    {
        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('getUri')->andReturn('test://resource');
        $mockResource->shouldReceive('read')
            ->andThrow(new \RuntimeException('Read failed'));

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $mockResource]);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32603);
        $this->expectExceptionMessage('Failed to read resource: Read failed');

        $this->handler->handle('resources/read', [
            'uri' => 'test://resource',
        ]);
    }

    #[Test]
    #[DataProvider('resourceUriProvider')]
    public function get_resource_uri_handles_different_uri_methods($resource, string $expectedUri): void
    {
        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $resource]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertSame($expectedUri, $response['resources'][0]['uri']);
    }

    public static function resourceUriProvider(): array
    {
        $resourceWithGetUri = Mockery::mock();
        $resourceWithGetUri->shouldReceive('getUri')->andReturn('custom://uri');
        $resourceWithGetUri->shouldReceive('getDescription')->andReturn('Test');
        $resourceWithGetUri->shouldReceive('getMimeType')->andReturn('text/plain');
        $resourceWithGetUri->shouldReceive('getMetadata')->andReturn([]);

        $resourceWithUriMethod = Mockery::mock();
        $resourceWithUriMethod->shouldReceive('uri')->andReturn('method://uri');
        $resourceWithUriMethod->shouldReceive('getDescription')->andReturn('Test');
        $resourceWithUriMethod->shouldReceive('getMimeType')->andReturn('text/plain');
        $resourceWithUriMethod->shouldReceive('getMetadata')->andReturn([]);

        $resourceWithUriProperty = new class
        {
            public string $uri = 'property://uri';

            public function getDescription(): string
            {
                return 'Test';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getMetadata(): array
            {
                return [];
            }
        };

        $resourceWithoutUri = Mockery::mock();
        $resourceWithoutUri->shouldReceive('getDescription')->andReturn('Test');
        $resourceWithoutUri->shouldReceive('getMimeType')->andReturn('text/plain');
        $resourceWithoutUri->shouldReceive('getMetadata')->andReturn([]);

        return [
            'getUri method' => [$resourceWithGetUri, 'custom://uri'],
            'uri method' => [$resourceWithUriMethod, 'method://uri'],
            'uri property' => [$resourceWithUriProperty, 'property://uri'],
            'no uri (default)' => [$resourceWithoutUri, 'resource://test-resource'],
        ];
    }

    #[Test]
    #[DataProvider('resourceDescriptionProvider')]
    public function get_resource_description_handles_different_description_methods($resource, string $expectedDescription): void
    {
        // Ensure all resources have required methods for listing
        if (method_exists($resource, 'shouldReceive')) {
            $resource->shouldReceive('getUri')->andReturn('test://resource');
            $resource->shouldReceive('getMimeType')->andReturn('text/plain');
            $resource->shouldReceive('getMetadata')->andReturn([]);
        }

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $resource]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertSame($expectedDescription, $response['resources'][0]['description']);
    }

    public static function resourceDescriptionProvider(): array
    {
        $resourceWithGetDescription = Mockery::mock();
        $resourceWithGetDescription->shouldReceive('getDescription')->andReturn('From getDescription');

        $resourceWithDescriptionMethod = Mockery::mock();
        $resourceWithDescriptionMethod->shouldReceive('description')->andReturn('From description method');

        $resourceWithDescriptionProperty = new class
        {
            public string $description = 'From description property';

            public function getUri(): string
            {
                return 'test://resource';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function getMetadata(): array
            {
                return [];
            }
        };

        $resourceWithoutDescription = Mockery::mock();
        $className = get_class($resourceWithoutDescription);

        return [
            'getDescription method' => [$resourceWithGetDescription, 'From getDescription'],
            'description method' => [$resourceWithDescriptionMethod, 'From description method'],
            'description property' => [$resourceWithDescriptionProperty, 'From description property'],
            'no description' => [$resourceWithoutDescription, "Resource: {$className}"],
        ];
    }

    #[Test]
    #[DataProvider('resourceMimeTypeProvider')]
    public function get_resource_mime_type_handles_different_mime_type_methods($resource, string $expectedMimeType): void
    {
        // Ensure all resources have required methods for listing
        if (method_exists($resource, 'shouldReceive')) {
            $resource->shouldReceive('getUri')->andReturn('test://resource');
            $resource->shouldReceive('getDescription')->andReturn('Test');
            $resource->shouldReceive('getMetadata')->andReturn([]);
        }

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $resource]);

        $response = $this->handler->handle('resources/list', []);

        $this->assertSame($expectedMimeType, $response['resources'][0]['mimeType']);
    }

    public static function resourceMimeTypeProvider(): array
    {
        $resourceWithGetMimeType = Mockery::mock();
        $resourceWithGetMimeType->shouldReceive('getMimeType')->andReturn('application/json');

        $resourceWithMimeTypeMethod = Mockery::mock();
        $resourceWithMimeTypeMethod->shouldReceive('mimeType')->andReturn('text/html');

        $resourceWithMimeTypeProperty = new class
        {
            public string $mimeType = 'text/css';

            public function getUri(): string
            {
                return 'test://resource';
            }

            public function getDescription(): string
            {
                return 'Test';
            }

            public function getMetadata(): array
            {
                return [];
            }
        };

        $resourceWithoutMimeType = Mockery::mock();

        return [
            'getMimeType method' => [$resourceWithGetMimeType, 'application/json'],
            'mimeType method' => [$resourceWithMimeTypeMethod, 'text/html'],
            'mimeType property' => [$resourceWithMimeTypeProperty, 'text/css'],
            'no mime type (default)' => [$resourceWithoutMimeType, 'text/plain'],
        ];
    }

    #[Test]
    #[DataProvider('resourceMetadataProvider')]
    public function get_resource_metadata_handles_different_metadata_methods($resource, array $expectedExtraKeys): void
    {
        // Ensure all resources have required methods for listing
        if (method_exists($resource, 'shouldReceive')) {
            $resource->shouldReceive('getUri')->andReturn('test://resource');
            $resource->shouldReceive('getDescription')->andReturn('Test');
            $resource->shouldReceive('getMimeType')->andReturn('text/plain');
        }

        $this->resourceRegistry
            ->shouldReceive('all')
            ->once()
            ->andReturn(['test-resource' => $resource]);

        $response = $this->handler->handle('resources/list', []);

        $resourceDef = $response['resources'][0];

        // Check that extra metadata keys are present
        foreach ($expectedExtraKeys as $key => $value) {
            $this->assertArrayHasKey($key, $resourceDef);
            $this->assertSame($value, $resourceDef[$key]);
        }
    }

    public static function resourceMetadataProvider(): array
    {
        $resourceWithGetMetadata = Mockery::mock();
        $resourceWithGetMetadata->shouldReceive('getMetadata')->andReturn(['version' => '2.0', 'author' => 'test']);

        $resourceWithMetadataProperty = new class
        {
            public array $metadata = ['tags' => ['important'], 'size' => 1024];

            public function getUri(): string
            {
                return 'test://resource';
            }

            public function getDescription(): string
            {
                return 'Test';
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }
        };

        $resourceWithoutMetadata = Mockery::mock();
        $resourceWithoutMetadata->shouldReceive('getMetadata')->andReturn([]);

        return [
            'getMetadata method' => [
                $resourceWithGetMetadata,
                ['version' => '2.0', 'author' => 'test'],
            ],
            'metadata property' => [
                $resourceWithMetadataProperty,
                ['tags' => ['important'], 'size' => 1024],
            ],
            'no metadata' => [$resourceWithoutMetadata, []],
        ];
    }

    private function createMockResource(string $description): object
    {
        $resource = Mockery::mock();
        $resource->shouldReceive('getUri')->andReturn("test://{$description}");
        $resource->shouldReceive('getDescription')->andReturn($description);
        $resource->shouldReceive('getMimeType')->andReturn('text/plain');
        $resource->shouldReceive('getMetadata')->andReturn([]);

        return $resource;
    }
}
