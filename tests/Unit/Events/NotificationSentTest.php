<?php

/**
 * @file tests/Unit/Events/NotificationSentTest.php
 *
 * @description Unit tests for NotificationSent event
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Events\NotificationSent
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

use JTD\LaravelMCP\Events\NotificationSent;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(NotificationSent::class)]
#[Group('ticket-027')]
#[Group('events')]
class NotificationSentTest extends UnitTestCase
{
    #[Test]
    public function it_constructs_with_required_data(): void
    {
        $params = [new \stdClass, 'mail', ['status' => 'sent']];
        $event = new NotificationSent(...$params);

        $this->assertInstanceOf(NotificationSent::class, $event);
        $this->assertSame($params[0], $event->notification);
        $this->assertSame($params[1], $event->channel);
        $this->assertSame($params[2], $event->response);
    }

    #[Test]
    public function it_has_expected_properties(): void
    {
        $params = [new \stdClass, 'mail', ['status' => 'sent']];
        $event = new NotificationSent(...$params);

        $properties = ['notification', 'channel', 'response'];
        foreach ($properties as $property) {
            $this->assertTrue(property_exists($event, $property));
        }
    }

    #[Test]
    public function it_can_be_serialized(): void
    {
        $params = [new \stdClass, 'mail', ['status' => 'sent']];
        $event = new NotificationSent(...$params);

        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(NotificationSent::class, $unserialized);
    }
}
