<?php

/**
 * @file tests/Unit/Events/NotificationBroadcastTest.php
 *
 * @description Unit tests for NotificationBroadcast event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\NotificationBroadcast
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

use JTD\LaravelMCP\Events\NotificationBroadcast;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(NotificationBroadcast::class)]
#[Group('ticket-027')]
#[Group('events')]
class NotificationBroadcastTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = [new \stdClass, ['mail', 'database'], ['message' => 'test']];
        $event = new NotificationBroadcast(...$params);

        $this->assertInstanceOf(NotificationBroadcast::class, $event);
        $this->assertSame($params[0], $event->notification);
        $this->assertSame($params[1], $event->channels);
        $this->assertSame($params[2], $event->data);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = [new \stdClass, ['mail', 'database'], ['message' => 'test']];
        $event = new NotificationBroadcast(...$params);

        $properties = ['notification', 'channels', 'data'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = [new \stdClass, ['mail', 'database'], ['message' => 'test']];
        $event = new NotificationBroadcast(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(NotificationBroadcast::class, $unserialized);
    }
}
