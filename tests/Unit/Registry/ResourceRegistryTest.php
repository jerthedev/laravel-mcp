<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use Tests\TestCase;

/**
 * Test suite for ResourceRegistry functionality.
 *
 * Tests the resource-specific registry that manages registration,
 * validation, and retrieval of MCP resources.
 */
class ResourceRegistryTest extends TestCase
{
    private ResourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ResourceRegistry;
    }

    /**
     * Test successful resource registration.
     */
    public function test_register_resource_successfully(): void
    {
        $resourceName = 'user_profile';
        $resource = $this->createTestResource($resourceName, [
            'uri' => 'user://profile/{id}',
            'description' => 'User profile data',
            'mimeType' => 'application/json',
        ]);
        $metadata = [
            'description' => 'User profile data',
            'uri' => 'user://profile/{id}',
            'mime_type' => 'application/json',
            'annotations' => ['cacheable' => true],
        ];

        $this->registry->register($resourceName, $resource, $metadata);

        $this->assertTrue($this->registry->has($resourceName));
        $this->assertSame($resource, $this->registry->get($resourceName));
    }

    /**
     * Test registration with duplicate resource name throws exception.
     */
    public function test_register_duplicate_resource_throws_exception(): void
    {
        $resourceName = 'duplicate_resource';
        $resource = $this->createTestResource($resourceName);

        $this->registry->register($resourceName, $resource);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Resource '{$resourceName}' is already registered");

        $this->registry->register($resourceName, $resource);
    }

    /**
     * Test getting non-existent resource throws exception.
     */
    public function test_get_non_existent_resource_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Resource 'nonexistent' is not registered");

        $this->registry->get('nonexistent');
    }

    /**
     * Test successful resource unregistration.
     */
    public function test_unregister_resource_successfully(): void
    {
        $resourceName = 'test_resource';
        $resource = $this->createTestResource($resourceName);

        $this->registry->register($resourceName, $resource);
        $this->assertTrue($this->registry->has($resourceName));

        $result = $this->registry->unregister($resourceName);

        $this->assertTrue($result);
        $this->assertFalse($this->registry->has($resourceName));
    }

    /**
     * Test unregistering non-existent resource returns false.
     */
    public function test_unregister_non_existent_resource(): void
    {
        $result = $this->registry->unregister('nonexistent');

        $this->assertFalse($result);
    }

    /**
     * Test checking if resource exists.
     */
    public function test_has_resource(): void
    {
        $resourceName = 'test_resource';
        $resource = $this->createTestResource($resourceName);

        $this->assertFalse($this->registry->has($resourceName));

        $this->registry->register($resourceName, $resource);

        $this->assertTrue($this->registry->has($resourceName));
    }

    /**
     * Test getting all registered resources.
     */
    public function test_get_all_resources(): void
    {
        $resource1 = $this->createTestResource('resource1');
        $resource2 = $this->createTestResource('resource2');

        $this->registry->register('resource1', $resource1);
        $this->registry->register('resource2', $resource2);

        $all = $this->registry->getAll();

        $this->assertCount(2, $all);
        $this->assertSame($resource1, $all['resource1']);
        $this->assertSame($resource2, $all['resource2']);

        // Test alias method
        $this->assertEquals($all, $this->registry->all());
    }

    /**
     * Test getting resource names.
     */
    public function test_get_resource_names(): void
    {
        $this->registry->register('resource1', $this->createTestResource('resource1'));
        $this->registry->register('resource2', $this->createTestResource('resource2'));

        $names = $this->registry->names();

        $this->assertEquals(['resource1', 'resource2'], $names);
    }

    /**
     * Test counting registered resources.
     */
    public function test_count_resources(): void
    {
        $this->assertEquals(0, $this->registry->count());

        $this->registry->register('resource1', $this->createTestResource('resource1'));
        $this->assertEquals(1, $this->registry->count());

        $this->registry->register('resource2', $this->createTestResource('resource2'));
        $this->assertEquals(2, $this->registry->count());

        $this->registry->unregister('resource1');
        $this->assertEquals(1, $this->registry->count());
    }

    /**
     * Test clearing all resources.
     */
    public function test_clear_all_resources(): void
    {
        $this->registry->register('resource1', $this->createTestResource('resource1'));
        $this->registry->register('resource2', $this->createTestResource('resource2'));

        $this->assertEquals(2, $this->registry->count());

        $this->registry->clear();

        $this->assertEquals(0, $this->registry->count());
        $this->assertFalse($this->registry->has('resource1'));
        $this->assertFalse($this->registry->has('resource2'));
    }

    /**
     * Test getting resource metadata.
     */
    public function test_get_resource_metadata(): void
    {
        $resourceName = 'test_resource';
        $resource = $this->createTestResource($resourceName);
        $metadata = [
            'description' => 'Test resource description',
            'uri' => 'test://resource/{id}',
            'mime_type' => 'text/plain',
            'annotations' => ['version' => '1.0'],
        ];

        $this->registry->register($resourceName, $resource, $metadata);

        $retrievedMetadata = $this->registry->getMetadata($resourceName);

        $this->assertEquals($resourceName, $retrievedMetadata['name']);
        $this->assertEquals('resource', $retrievedMetadata['type']);
        $this->assertEquals('Test resource description', $retrievedMetadata['description']);
        $this->assertEquals('test://resource/{id}', $retrievedMetadata['uri']);
        $this->assertEquals('text/plain', $retrievedMetadata['mime_type']);
        $this->assertEquals(['version' => '1.0'], $retrievedMetadata['annotations']);
        $this->assertNotEmpty($retrievedMetadata['registered_at']);
    }

    /**
     * Test getting metadata for non-existent resource throws exception.
     */
    public function test_get_metadata_for_non_existent_resource_throws_exception(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Resource 'nonexistent' is not registered");

        $this->registry->getMetadata('nonexistent');
    }

    /**
     * Test resource filtering by metadata criteria.
     */
    public function test_filter_resources_by_metadata(): void
    {
        $resource1 = $this->createTestResource('resource1');
        $resource2 = $this->createTestResource('resource2');
        $resource3 = $this->createTestResource('resource3');

        $this->registry->register('resource1', $resource1, ['mime_type' => 'application/json']);
        $this->registry->register('resource2', $resource2, ['mime_type' => 'text/plain']);
        $this->registry->register('resource3', $resource3, ['mime_type' => 'application/json']);

        $jsonResources = $this->registry->filter(['mime_type' => 'application/json']);

        $this->assertCount(2, $jsonResources);
        $this->assertArrayHasKey('resource1', $jsonResources);
        $this->assertArrayHasKey('resource3', $jsonResources);
        $this->assertArrayNotHasKey('resource2', $jsonResources);
    }

    /**
     * Test resource searching by name pattern.
     */
    public function test_search_resources_by_pattern(): void
    {
        $this->registry->register('user_profile', $this->createTestResource('user_profile'));
        $this->registry->register('user_settings', $this->createTestResource('user_settings'));
        $this->registry->register('system_config', $this->createTestResource('system_config'));

        $userResources = $this->registry->search('user_*');

        $this->assertCount(2, $userResources);
        $this->assertArrayHasKey('user_profile', $userResources);
        $this->assertArrayHasKey('user_settings', $userResources);
        $this->assertArrayNotHasKey('system_config', $userResources);
    }

    /**
     * Test getting registry type.
     */
    public function test_get_registry_type(): void
    {
        $this->assertEquals('resources', $this->registry->getType());
    }

    /**
     * Test getting resource templates for MCP protocol.
     */
    public function test_get_resource_templates(): void
    {
        $resource1 = $this->createTestResource('resource1');
        $resource2 = $this->createTestResource('resource2');

        $this->registry->register('resource1', $resource1, [
            'uri' => 'test://resource1/{id}',
            'description' => 'First test resource',
            'mime_type' => 'application/json',
        ]);

        $this->registry->register('resource2', $resource2, [
            'uri' => 'test://resource2/{id}',
            'description' => 'Second test resource',
            'mime_type' => 'text/plain',
        ]);

        $templates = $this->registry->getResourceTemplates();

        $this->assertCount(2, $templates);

        $this->assertEquals('test://resource1/{id}', $templates[0]['uriTemplate']);
        $this->assertEquals('resource1', $templates[0]['name']);
        $this->assertEquals('First test resource', $templates[0]['description']);
        $this->assertEquals('application/json', $templates[0]['mimeType']);

        $this->assertEquals('test://resource2/{id}', $templates[1]['uriTemplate']);
        $this->assertEquals('resource2', $templates[1]['name']);
        $this->assertEquals('Second test resource', $templates[1]['description']);
        $this->assertEquals('text/plain', $templates[1]['mimeType']);
    }

    /**
     * Test resource reading.
     */
    public function test_read_resource(): void
    {
        $resourceName = 'test_resource';
        $resource = $this->createTestResource($resourceName);

        $this->registry->register($resourceName, $resource);

        $result = $this->registry->readResource($resourceName, ['param' => 'value']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('contents', $result);
    }

    /**
     * Test resource reading with class name.
     */
    public function test_read_resource_with_class_name(): void
    {
        $resourceName = 'test_resource';
        $resourceClass = get_class($this->createTestResource($resourceName));

        $this->registry->register($resourceName, $resourceClass);

        $result = $this->registry->readResource($resourceName, ['param' => 'value']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('contents', $result);
    }

    /**
     * Test reading resource with invalid resource throws exception.
     */
    public function test_read_invalid_resource_throws_exception(): void
    {
        $resourceName = 'invalid_resource';
        $invalidResource = new class
        {
            // No read method
        };

        $this->registry->register($resourceName, $invalidResource);

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage("Resource '{$resourceName}' does not have a read method");

        $this->registry->readResource($resourceName);
    }

    /**
     * Test getting resource content with metadata.
     */
    public function test_get_resource_content(): void
    {
        $resourceName = 'test_resource';
        $resource = $this->createTestResource($resourceName);

        $this->registry->register($resourceName, $resource, [
            'uri' => 'test://resource',
            'mime_type' => 'application/json',
        ]);

        $content = $this->registry->getResourceContent($resourceName);

        $this->assertArrayHasKey('contents', $content);
        $this->assertCount(1, $content['contents']);
        $this->assertEquals('test://resource', $content['contents'][0]['uri']);
        $this->assertEquals('application/json', $content['contents'][0]['mimeType']);
        $this->assertArrayHasKey('text', $content['contents'][0]);
    }

    /**
     * Test listing resources for MCP protocol.
     */
    public function test_list_resources(): void
    {
        $resource1 = $this->createTestResource('resource1');
        $resource2 = $this->createTestResource('resource2');

        $this->registry->register('resource1', $resource1, [
            'uri' => 'test://resource1',
            'description' => 'First resource',
            'mime_type' => 'application/json',
            'annotations' => ['version' => '1.0'],
        ]);

        $this->registry->register('resource2', $resource2, [
            'uri' => 'test://resource2',
            'description' => 'Second resource',
            'mime_type' => 'text/plain',
        ]);

        $list = $this->registry->listResources();

        $this->assertArrayHasKey('resources', $list);
        $this->assertCount(2, $list['resources']);

        $first = $list['resources'][0];
        $this->assertEquals('test://resource1', $first['uri']);
        $this->assertEquals('resource1', $first['name']);
        $this->assertEquals('First resource', $first['description']);
        $this->assertEquals('application/json', $first['mimeType']);
        $this->assertEquals(['version' => '1.0'], $first['annotations']);

        $second = $list['resources'][1];
        $this->assertEquals('test://resource2', $second['uri']);
        $this->assertEquals('resource2', $second['name']);
        $this->assertEquals('Second resource', $second['description']);
        $this->assertEquals('text/plain', $second['mimeType']);
        $this->assertEquals([], $second['annotations']);
    }

    /**
     * Test getting resources by URI pattern.
     */
    public function test_get_resources_by_uri(): void
    {
        $this->registry->register('resource1', $this->createTestResource('resource1'), [
            'uri' => 'user://profile/{id}',
        ]);

        $this->registry->register('resource2', $this->createTestResource('resource2'), [
            'uri' => 'user://settings/{id}',
        ]);

        $this->registry->register('resource3', $this->createTestResource('resource3'), [
            'uri' => 'system://config/{key}',
        ]);

        $userResources = $this->registry->getResourcesByUri('user://*');

        $this->assertCount(2, $userResources);
        $this->assertArrayHasKey('resource1', $userResources);
        $this->assertArrayHasKey('resource2', $userResources);
        $this->assertArrayNotHasKey('resource3', $userResources);
    }

    /**
     * Test getting resources by MIME type.
     */
    public function test_get_resources_by_mime_type(): void
    {
        $this->registry->register('resource1', $this->createTestResource('resource1'), [
            'mime_type' => 'application/json',
        ]);

        $this->registry->register('resource2', $this->createTestResource('resource2'), [
            'mime_type' => 'text/plain',
        ]);

        $this->registry->register('resource3', $this->createTestResource('resource3'), [
            'mime_type' => 'application/json',
        ]);

        $jsonResources = $this->registry->getResourcesByMimeType('application/json');

        $this->assertCount(2, $jsonResources);
        $this->assertArrayHasKey('resource1', $jsonResources);
        $this->assertArrayHasKey('resource3', $jsonResources);
        $this->assertArrayNotHasKey('resource2', $jsonResources);
    }

    /**
     * Test checking if resource has annotations.
     */
    public function test_has_annotations(): void
    {
        $this->registry->register('resource_with_annotations', $this->createTestResource('resource_with_annotations'), [
            'annotations' => ['version' => '1.0', 'cacheable' => true],
        ]);

        $this->registry->register('resource_without_annotations', $this->createTestResource('resource_without_annotations'), [
            'annotations' => [],
        ]);

        $this->registry->register('resource_null_annotations', $this->createTestResource('resource_null_annotations'));

        $this->assertTrue($this->registry->hasAnnotations('resource_with_annotations'));
        $this->assertFalse($this->registry->hasAnnotations('resource_without_annotations'));
        $this->assertFalse($this->registry->hasAnnotations('resource_null_annotations'));
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
        $resourceName = 'default_resource';
        $resource = $this->createTestResource($resourceName);

        $this->registry->register($resourceName, $resource);

        $metadata = $this->registry->getMetadata($resourceName);

        $this->assertEquals($resourceName, $metadata['name']);
        $this->assertEquals('resource', $metadata['type']);
        $this->assertEquals('', $metadata['description']);
        $this->assertEquals('', $metadata['uri']);
        $this->assertEquals('application/json', $metadata['mime_type']);
        $this->assertEquals([], $metadata['annotations']);
        $this->assertNotEmpty($metadata['registered_at']);
    }

    /**
     * Test complex filtering scenarios.
     */
    public function test_complex_filtering(): void
    {
        $this->registry->register('resource1', $this->createTestResource('resource1'), [
            'mime_type' => 'application/json',
            'category' => 'user_data',
        ]);

        $this->registry->register('resource2', $this->createTestResource('resource2'), [
            'mime_type' => 'application/json',
            'category' => 'system_data',
        ]);

        $this->registry->register('resource3', $this->createTestResource('resource3'), [
            'mime_type' => 'text/plain',
            'category' => 'user_data',
        ]);

        // Filter by multiple criteria
        $jsonUserResources = $this->registry->filter([
            'mime_type' => 'application/json',
            'category' => 'user_data',
        ]);

        $this->assertCount(1, $jsonUserResources);
        $this->assertArrayHasKey('resource1', $jsonUserResources);

        // Filter with non-matching criteria
        $nonExistentResources = $this->registry->filter([
            'category' => 'nonexistent',
        ]);

        $this->assertCount(0, $nonExistentResources);
    }

    /**
     * Test edge cases for resource names.
     */
    public function test_resource_name_edge_cases(): void
    {
        // Test with special characters in name
        $specialName = 'resource-with_special.chars';
        $resource = $this->createTestResource($specialName);

        $this->registry->register($specialName, $resource);
        $this->assertTrue($this->registry->has($specialName));

        // Test with numeric name
        $numericName = '123';
        $numericResource = $this->createTestResource($numericName);

        $this->registry->register($numericName, $numericResource);
        $this->assertTrue($this->registry->has($numericName));
    }

    /**
     * Test resource templates with empty metadata.
     */
    public function test_resource_templates_with_defaults(): void
    {
        $resource = $this->createTestResource('minimal_resource');
        $this->registry->register('minimal_resource', $resource);

        $templates = $this->registry->getResourceTemplates();

        $this->assertCount(1, $templates);
        $this->assertEquals('', $templates[0]['uriTemplate']);
        $this->assertEquals('minimal_resource', $templates[0]['name']);
        $this->assertEquals('', $templates[0]['description']);
        $this->assertEquals('application/json', $templates[0]['mimeType']);
    }
}
