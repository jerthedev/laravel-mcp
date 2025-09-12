<?php

/**
 * @file tests/Unit/Events/McpToolExecutedTest.php
 *
 * @description Unit tests for McpToolExecuted event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\McpToolExecuted
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

use JTD\LaravelMCP\Events\McpToolExecuted;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpToolExecuted::class)]
#[Group('ticket-027')]
#[Group('events')]
class McpToolExecutedTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = ['calculator', ['a' => 1, 'b' => 2], ['sum' => 3], 0.5];
        $event = new McpToolExecuted(...$params);

        $this->assertInstanceOf(McpToolExecuted::class, $event);
        $this->assertSame($params[0], $event->tool);
        $this->assertSame($params[1], $event->parameters);
        $this->assertSame($params[2], $event->result);
        $this->assertSame($params[3], $event->executionTime);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = ['calculator', ['a' => 1, 'b' => 2], ['sum' => 3], 0.5];
        $event = new McpToolExecuted(...$params);

        $properties = ['tool', 'parameters', 'result', 'executionTime'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = ['calculator', ['a' => 1, 'b' => 2], ['sum' => 3], 0.5];
        $event = new McpToolExecuted(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(McpToolExecuted::class, $unserialized);
    }
}
