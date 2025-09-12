<?php

/**
 * @file tests/Unit/Events/McpResourceAccessedTest.php
 *
 * @description Unit tests for McpResourceAccessed event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\McpResourceAccessed
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

use JTD\LaravelMCP\Events\McpResourceAccessed;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpResourceAccessed::class)]
#[Group('ticket-027')]
#[Group('events')]
class McpResourceAccessedTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = ['users', 'read', ['id' => 1], ['name' => 'John']];
        $event = new McpResourceAccessed(...$params);

        $this->assertInstanceOf(McpResourceAccessed::class, $event);
        $this->assertSame($params[0], $event->resource);
        $this->assertSame($params[1], $event->method);
        $this->assertSame($params[2], $event->parameters);
        $this->assertSame($params[3], $event->result);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = ['users', 'read', ['id' => 1], ['name' => 'John']];
        $event = new McpResourceAccessed(...$params);

        $properties = ['resource', 'method', 'parameters', 'result'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = ['users', 'read', ['id' => 1], ['name' => 'John']];
        $event = new McpResourceAccessed(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(McpResourceAccessed::class, $unserialized);
    }
}
