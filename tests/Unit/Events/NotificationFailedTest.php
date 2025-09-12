<?php

/**
 * @file tests/Unit/Events/NotificationFailedTest.php
 *
 * @description Unit tests for NotificationFailed event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\NotificationFailed
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

use JTD\LaravelMCP\Events\NotificationFailed;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(NotificationFailed::class)]
#[Group('ticket-027')]
#[Group('events')]
class NotificationFailedTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = [new \stdClass, 'mail', 'user@example.com', new \Exception('Failed')];
        $event = new NotificationFailed(...$params);

        $this->assertInstanceOf(NotificationFailed::class, $event);
        $this->assertSame($params[0], $event->notification);
        $this->assertSame($params[1], $event->channel);
        $this->assertSame($params[2], $event->recipient);
        $this->assertSame($params[3], $event->exception);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = [new \stdClass, 'mail', 'user@example.com', new \Exception('Failed')];
        $event = new NotificationFailed(...$params);

        $properties = ['notification', 'channel', 'recipient', 'exception'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = [new \stdClass, 'mail', 'user@example.com', new \Exception('Failed')];
        $event = new NotificationFailed(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(NotificationFailed::class, $unserialized);
    }
}
