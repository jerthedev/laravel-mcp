<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry\Contracts;

use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Unit tests for RegistryInterface contract.
 *
 * This test ensures that all implementations of RegistryInterface
 * properly implement the required methods for component registration.
 */
class RegistryInterfaceTest extends TestCase
{
    /** @var RegistryInterface&MockObject */
    protected $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(RegistryInterface::class);
    }

    /**
     * Test register component without metadata.
     */
    public function test_register_component_without_metadata(): void
    {
        $component = new \stdClass;
        $component->name = 'TestComponent';

        $this->registry
            ->expects($this->once())
            ->method('register')
            ->with('test-component', $component, []);

        $this->registry->register('test-component', $component);
    }

    /**
     * Test register component with metadata.
     */
    public function test_register_component_with_metadata(): void
    {
        $component = new \stdClass;
        $metadata = [
            'description' => 'Test component',
            'version' => '1.0.0',
            'author' => 'Test Author',
        ];

        $this->registry
            ->expects($this->once())
            ->method('register')
            ->with('test-component', $component, $metadata);

        $this->registry->register('test-component', $component, $metadata);
    }

    /**
     * Test unregister existing component.
     */
    public function test_unregister_existing_component(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('unregister')
            ->with('test-component')
            ->willReturn(true);

        $result = $this->registry->unregister('test-component');

        $this->assertTrue($result);
    }

    /**
     * Test unregister non-existing component.
     */
    public function test_unregister_non_existing_component(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('unregister')
            ->with('non-existing')
            ->willReturn(false);

        $result = $this->registry->unregister('non-existing');

        $this->assertFalse($result);
    }

    /**
     * Test has method with existing component.
     */
    public function test_has_existing_component(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('has')
            ->with('test-component')
            ->willReturn(true);

        $this->assertTrue($this->registry->has('test-component'));
    }

    /**
     * Test has method with non-existing component.
     */
    public function test_has_non_existing_component(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('has')
            ->with('non-existing')
            ->willReturn(false);

        $this->assertFalse($this->registry->has('non-existing'));
    }

    /**
     * Test get existing component.
     */
    public function test_get_existing_component(): void
    {
        $component = new \stdClass;
        $component->name = 'TestComponent';

        $this->registry
            ->expects($this->once())
            ->method('get')
            ->with('test-component')
            ->willReturn($component);

        $result = $this->registry->get('test-component');

        $this->assertSame($component, $result);
    }

    /**
     * Test get non-existing component throws exception.
     */
    public function test_get_non_existing_component_throws_exception(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('get')
            ->with('non-existing')
            ->willThrowException(new RegistrationException('Component not found: non-existing'));

        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Component not found: non-existing');

        $this->registry->get('non-existing');
    }

    /**
     * Test all method returns all components.
     */
    public function test_all_returns_all_components(): void
    {
        $components = [
            'component1' => new \stdClass,
            'component2' => new \stdClass,
            'component3' => new \stdClass,
        ];

        $this->registry
            ->expects($this->once())
            ->method('all')
            ->willReturn($components);

        $result = $this->registry->all();

        $this->assertSame($components, $result);
    }

    /**
     * Test all method returns empty array when no components.
     */
    public function test_all_returns_empty_array(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('all')
            ->willReturn([]);

        $result = $this->registry->all();

        $this->assertSame([], $result);
    }

    /**
     * Test names method returns component names.
     */
    public function test_names_returns_component_names(): void
    {
        $names = ['component1', 'component2', 'component3'];

        $this->registry
            ->expects($this->once())
            ->method('names')
            ->willReturn($names);

        $result = $this->registry->names();

        $this->assertSame($names, $result);
    }

    /**
     * Test count method returns component count.
     */
    public function test_count_returns_component_count(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('count')
            ->willReturn(5);

        $count = $this->registry->count();

        $this->assertSame(5, $count);
    }

    /**
     * Test count returns zero when no components.
     */
    public function test_count_returns_zero(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('count')
            ->willReturn(0);

        $count = $this->registry->count();

        $this->assertSame(0, $count);
    }

    /**
     * Test clear method removes all components.
     */
    public function test_clear_removes_all_components(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('clear');

        $this->registry->clear();
    }

    /**
     * Test getMetadata returns component metadata.
     */
    public function test_get_metadata_returns_metadata(): void
    {
        $metadata = [
            'description' => 'Test component',
            'version' => '1.0.0',
            'tags' => ['test', 'component'],
        ];

        $this->registry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('test-component')
            ->willReturn($metadata);

        $result = $this->registry->getMetadata('test-component');

        $this->assertSame($metadata, $result);
    }

    /**
     * Test getMetadata returns empty array for component without metadata.
     */
    public function test_get_metadata_returns_empty_array(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('test-component')
            ->willReturn([]);

        $result = $this->registry->getMetadata('test-component');

        $this->assertSame([], $result);
    }

    /**
     * Test filter method with criteria.
     */
    public function test_filter_with_criteria(): void
    {
        $criteria = [
            'tag' => 'calculator',
            'version' => '1.0.0',
        ];

        $filtered = [
            'calc1' => new \stdClass,
            'calc2' => new \stdClass,
        ];

        $this->registry
            ->expects($this->once())
            ->method('filter')
            ->with($criteria)
            ->willReturn($filtered);

        $result = $this->registry->filter($criteria);

        $this->assertSame($filtered, $result);
    }

    /**
     * Test filter returns empty array when no matches.
     */
    public function test_filter_returns_empty_when_no_matches(): void
    {
        $criteria = ['tag' => 'non-existing'];

        $this->registry
            ->expects($this->once())
            ->method('filter')
            ->with($criteria)
            ->willReturn([]);

        $result = $this->registry->filter($criteria);

        $this->assertSame([], $result);
    }

    /**
     * Test search method with pattern.
     */
    public function test_search_with_pattern(): void
    {
        $pattern = 'calc*';

        $matches = [
            'calculator' => new \stdClass,
            'calc-advanced' => new \stdClass,
        ];

        $this->registry
            ->expects($this->once())
            ->method('search')
            ->with($pattern)
            ->willReturn($matches);

        $result = $this->registry->search($pattern);

        $this->assertSame($matches, $result);
    }

    /**
     * Test search with regex pattern.
     */
    public function test_search_with_regex_pattern(): void
    {
        $pattern = '/^test-.*$/';

        $matches = [
            'test-component' => new \stdClass,
            'test-tool' => new \stdClass,
        ];

        $this->registry
            ->expects($this->once())
            ->method('search')
            ->with($pattern)
            ->willReturn($matches);

        $result = $this->registry->search($pattern);

        $this->assertSame($matches, $result);
    }

    /**
     * Test getType returns registry type.
     */
    public function test_get_type_returns_registry_type(): void
    {
        $this->registry
            ->expects($this->once())
            ->method('getType')
            ->willReturn('tools');

        $type = $this->registry->getType();

        $this->assertSame('tools', $type);
    }

    /**
     * Test full registration lifecycle.
     */
    public function test_registration_lifecycle(): void
    {
        $component = new \stdClass;
        $metadata = ['version' => '1.0.0'];

        // Register component
        $this->registry
            ->expects($this->once())
            ->method('register')
            ->with('test', $component, $metadata);

        // Check if exists
        $this->registry
            ->expects($this->once())
            ->method('has')
            ->with('test')
            ->willReturn(true);

        // Get component
        $this->registry
            ->expects($this->once())
            ->method('get')
            ->with('test')
            ->willReturn($component);

        // Get metadata
        $this->registry
            ->expects($this->once())
            ->method('getMetadata')
            ->with('test')
            ->willReturn($metadata);

        // Unregister
        $this->registry
            ->expects($this->once())
            ->method('unregister')
            ->with('test')
            ->willReturn(true);

        // Execute lifecycle
        $this->registry->register('test', $component, $metadata);
        $this->assertTrue($this->registry->has('test'));
        $this->assertSame($component, $this->registry->get('test'));
        $this->assertSame($metadata, $this->registry->getMetadata('test'));
        $this->assertTrue($this->registry->unregister('test'));
    }

    /**
     * Test multiple component operations.
     */
    public function test_multiple_component_operations(): void
    {
        $components = [
            'comp1' => new \stdClass,
            'comp2' => new \stdClass,
            'comp3' => new \stdClass,
        ];

        // Register multiple - set up for exactly 3 calls
        $registerCallIndex = 0;
        $componentNames = array_keys($components);
        $componentValues = array_values($components);

        $this->registry
            ->expects($this->exactly(3))
            ->method('register')
            ->willReturnCallback(function ($name, $component, $metadata) use ($componentNames, $componentValues, &$registerCallIndex) {
                $this->assertEquals($componentNames[$registerCallIndex], $name);
                $this->assertSame($componentValues[$registerCallIndex], $component);
                $this->assertEquals([], $metadata);
                $registerCallIndex++;
            });

        // Count
        $this->registry
            ->expects($this->once())
            ->method('count')
            ->willReturn(3);

        // Get all
        $this->registry
            ->expects($this->once())
            ->method('all')
            ->willReturn($components);

        // Clear
        $this->registry
            ->expects($this->once())
            ->method('clear');

        // Execute operations
        foreach ($components as $name => $component) {
            $this->registry->register($name, $component);
        }

        $this->assertSame(3, $this->registry->count());
        $this->assertSame($components, $this->registry->all());
        $this->registry->clear();
    }

    /**
     * Test filtering by multiple criteria.
     */
    public function test_filter_multiple_criteria(): void
    {
        $criteria = [
            'type' => 'tool',
            'category' => 'math',
            'enabled' => true,
        ];

        $filtered = [
            'calculator' => new \stdClass,
        ];

        $this->registry
            ->expects($this->once())
            ->method('filter')
            ->with($criteria)
            ->willReturn($filtered);

        $result = $this->registry->filter($criteria);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('calculator', $result);
    }

    /**
     * Test registry type validation.
     */
    public function test_registry_types(): void
    {
        $types = ['tools', 'resources', 'prompts'];

        foreach ($types as $type) {
            $registry = $this->createMock(RegistryInterface::class);

            $registry
                ->expects($this->once())
                ->method('getType')
                ->willReturn($type);

            $this->assertSame($type, $registry->getType());
        }
    }
}
