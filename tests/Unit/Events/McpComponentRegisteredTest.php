<?php

namespace JTD\LaravelMCP\Tests\Unit\Events;

use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for McpComponentRegistered event.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Events\McpComponentRegistered
 */
#[Group('ticket-022')]
#[Group('events')]
#[Group('unit')]
class McpComponentRegisteredTest extends TestCase
{
    #[Test]
    public function it_creates_event_with_required_parameters(): void
    {
        $event = new McpComponentRegistered(
            'tool',
            'test_tool',
            $this->createMock(\JTD\LaravelMCP\Abstracts\McpTool::class)
        );

        $this->assertEquals('tool', $event->componentType);
        $this->assertEquals('test_tool', $event->componentName);
        $this->assertInstanceOf(\JTD\LaravelMCP\Abstracts\McpTool::class, $event->component);
        $this->assertIsArray($event->metadata);
        $this->assertEmpty($event->metadata);
        $this->assertNotEmpty($event->registeredAt);
        $this->assertNull($event->userId);
    }

    #[Test]
    public function it_creates_event_with_metadata(): void
    {
        $metadata = [
            'version' => '1.0.0',
            'author' => 'Test Author',
            'critical' => true,
        ];

        $event = new McpComponentRegistered(
            'resource',
            'test_resource',
            'TestResourceClass',
            $metadata
        );

        $this->assertEquals('resource', $event->componentType);
        $this->assertEquals('test_resource', $event->componentName);
        $this->assertEquals('TestResourceClass', $event->component);
        $this->assertEquals($metadata, $event->metadata);
    }

    #[Test]
    public function it_creates_event_with_user_id(): void
    {
        $event = new McpComponentRegistered(
            'prompt',
            'test_prompt',
            new \stdClass,
            [],
            'user123'
        );

        $this->assertEquals('user123', $event->userId);
    }

    #[Test]
    public function it_gets_component_type_label(): void
    {
        $toolEvent = new McpComponentRegistered('tool', 'test', new \stdClass);
        $this->assertEquals('Tool', $toolEvent->getComponentTypeLabel());

        $resourceEvent = new McpComponentRegistered('resource', 'test', new \stdClass);
        $this->assertEquals('Resource', $resourceEvent->getComponentTypeLabel());

        $promptEvent = new McpComponentRegistered('prompt', 'test', new \stdClass);
        $this->assertEquals('Prompt', $promptEvent->getComponentTypeLabel());

        $customEvent = new McpComponentRegistered('custom', 'test', new \stdClass);
        $this->assertEquals('Custom', $customEvent->getComponentTypeLabel());
    }

    #[Test]
    public function it_gets_component_details(): void
    {
        $metadata = ['version' => '1.0.0'];
        $component = new \stdClass;

        $event = new McpComponentRegistered(
            'tool',
            'test_tool',
            $component,
            $metadata,
            'user123'
        );

        $details = $event->getComponentDetails();

        $this->assertIsArray($details);
        $this->assertEquals('tool', $details['type']);
        $this->assertEquals('test_tool', $details['name']);
        $this->assertEquals(\stdClass::class, $details['class']);
        $this->assertEquals($metadata, $details['metadata']);
        $this->assertEquals($event->registeredAt, $details['registered_at']);
        $this->assertEquals('user123', $details['user_id']);
    }

    #[Test]
    public function it_checks_metadata_existence(): void
    {
        $event = new McpComponentRegistered(
            'tool',
            'test',
            new \stdClass,
            ['version' => '1.0.0', 'critical' => true]
        );

        $this->assertTrue($event->hasMetadata('version'));
        $this->assertTrue($event->hasMetadata('critical'));
        $this->assertFalse($event->hasMetadata('author'));
    }

    #[Test]
    public function it_gets_metadata_value(): void
    {
        $event = new McpComponentRegistered(
            'tool',
            'test',
            new \stdClass,
            ['version' => '1.0.0', 'critical' => true]
        );

        $this->assertEquals('1.0.0', $event->getMetadata('version'));
        $this->assertTrue($event->getMetadata('critical'));
        $this->assertNull($event->getMetadata('author'));
        $this->assertEquals('default', $event->getMetadata('author', 'default'));
    }

    #[Test]
    public function it_serializes_for_broadcasting(): void
    {
        $event = new McpComponentRegistered(
            'tool',
            'test_tool',
            new \stdClass,
            ['version' => '1.0.0']
        );

        // Test that the event can be serialized (for queue/broadcasting)
        $serialized = serialize($event);
        $this->assertIsString($serialized);

        $unserialized = unserialize($serialized);
        $this->assertEquals($event->componentType, $unserialized->componentType);
        $this->assertEquals($event->componentName, $unserialized->componentName);
        $this->assertEquals($event->metadata, $unserialized->metadata);
    }
}
