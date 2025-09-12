<?php

/**
 * @file tests/Unit/Events/McpPromptGeneratedTest.php
 *
 * @description Unit tests for McpPromptGenerated event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\McpPromptGenerated
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

use JTD\LaravelMCP\Events\McpPromptGenerated;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpPromptGenerated::class)]
#[Group('ticket-027')]
#[Group('events')]
class McpPromptGeneratedTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = ['greeting', ['name' => 'World'], 'Hello World'];
        $event = new McpPromptGenerated(...$params);

        $this->assertInstanceOf(McpPromptGenerated::class, $event);
        $this->assertSame($params[0], $event->prompt);
        $this->assertSame($params[1], $event->parameters);
        $this->assertSame($params[2], $event->result);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = ['greeting', ['name' => 'World'], 'Hello World'];
        $event = new McpPromptGenerated(...$params);

        $properties = ['prompt', 'parameters', 'result'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = ['greeting', ['name' => 'World'], 'Hello World'];
        $event = new McpPromptGenerated(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(McpPromptGenerated::class, $unserialized);
    }
}
