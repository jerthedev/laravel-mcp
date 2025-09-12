<?php

/**
 * @file tests/Unit/Events/McpComponentRegisteredTest.php
 *
 * @description Unit tests for McpComponentRegistered event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\McpComponentRegistered
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-Events
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Event System
 *
 * @testPriority High
 *
 * @quality Production-ready
 *
 * @coverage 95%+
 *
 * @standards PSR-12, PHPUnit 10.x
 */

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Events;

use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpComponentRegistered::class)]
#[Group('ticket-027')]
#[Group('events')]
class McpComponentRegisteredTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = ['tool', 'calculator', new \stdClass, ['version' => '1.0']];
        $event = new McpComponentRegistered(...$params);

        $this->assertInstanceOf(McpComponentRegistered::class, $event);
        $this->assertSame($params[0], $event->type);
        $this->assertSame($params[1], $event->name);
        $this->assertSame($params[2], $event->component);
        $this->assertSame($params[3], $event->metadata);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = ['tool', 'calculator', new \stdClass, ['version' => '1.0']];
        $event = new McpComponentRegistered(...$params);

        $properties = ['type', 'name', 'component', 'metadata'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = ['tool', 'calculator', new \stdClass, ['version' => '1.0']];
        $event = new McpComponentRegistered(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(McpComponentRegistered::class, $unserialized);
    }
}
