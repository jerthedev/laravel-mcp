<?php

/**
 * @file tests/Unit/Listeners/LogMcpComponentRegistrationTest.php
 *
 * @description Unit tests for LogMcpComponentRegistration listener
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Listeners\LogMcpComponentRegistration
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-Listeners
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Event Listeners
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

namespace JTD\LaravelMCP\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Listeners\LogMcpComponentRegistration;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(LogMcpComponentRegistration::class)]
#[Group('ticket-027')]
#[Group('listeners')]
class LogMcpComponentRegistrationTest extends UnitTestCase
{
    private LogMcpComponentRegistration $listener;

    protected function setUp(): void
    {
        parent::setUp();
        $this->listener = new LogMcpComponentRegistration;
    }

    #[Test]
    public function it_constructs_successfully(): void
    {
        $this->assertInstanceOf(LogMcpComponentRegistration::class, $this->listener);
    }

    #[Test]
    public function it_handles_event(): void
    {
        Log::shouldReceive('info')->once();

        $event = $this->createMock(McpComponentRegistered::class);
        $this->listener->handle($event);
    }

    #[Test]
    public function it_implements_handle_method(): void
    {
        $reflection = new \ReflectionClass($this->listener);
        $this->assertTrue($reflection->hasMethod('handle'));

        $method = $reflection->getMethod('handle');
        $this->assertTrue($method->isPublic());
    }
}
