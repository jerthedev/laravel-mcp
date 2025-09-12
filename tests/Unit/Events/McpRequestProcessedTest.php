<?php

/**
 * @file tests/Unit/Events/McpRequestProcessedTest.php
 *
 * @description Unit tests for McpRequestProcessed event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\McpRequestProcessed
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

use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpRequestProcessed::class)]
#[Group('ticket-027')]
#[Group('events')]
class McpRequestProcessedTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = ['req-123', 'tools/execute', ['tool' => 'calc'], ['result' => 42], 1.23, 'http', ['user' => 1]];
        $event = new McpRequestProcessed(...$params);

        $this->assertInstanceOf(McpRequestProcessed::class, $event);
        $this->assertSame($params[0], $event->requestId);
        $this->assertSame($params[1], $event->method);
        $this->assertSame($params[2], $event->parameters);
        $this->assertSame($params[3], $event->result);
        $this->assertSame($params[4], $event->executionTime);
        $this->assertSame($params[5], $event->transport);
        $this->assertSame($params[6], $event->context);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = ['req-123', 'tools/execute', ['tool' => 'calc'], ['result' => 42], 1.23, 'http', ['user' => 1]];
        $event = new McpRequestProcessed(...$params);

        $properties = ['requestId', 'method', 'parameters', 'result', 'executionTime', 'transport', 'context'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = ['req-123', 'tools/execute', ['tool' => 'calc'], ['result' => 42], 1.23, 'http', ['user' => 1]];
        $event = new McpRequestProcessed(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(McpRequestProcessed::class, $unserialized);
    }
}
