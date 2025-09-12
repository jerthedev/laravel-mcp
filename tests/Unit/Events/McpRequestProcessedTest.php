<?php

namespace JTD\LaravelMCP\Tests\Unit\Events;

use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for McpRequestProcessed event.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Events\McpRequestProcessed
 */
#[Group('ticket-022')]
#[Group('events')]
#[Group('unit')]
class McpRequestProcessedTest extends TestCase
{
    #[Test]
    public function it_creates_event_with_required_parameters(): void
    {
        $event = new McpRequestProcessed(
            'req-123',
            'tools/calculator',
            ['operation' => 'add', 'a' => 1, 'b' => 2],
            ['result' => 3],
            150.5
        );

        $this->assertEquals('req-123', $event->requestId);
        $this->assertEquals('tools/calculator', $event->method);
        $this->assertEquals(['operation' => 'add', 'a' => 1, 'b' => 2], $event->parameters);
        $this->assertEquals(['result' => 3], $event->result);
        $this->assertEquals(150.5, $event->executionTime);
        $this->assertEquals('http', $event->transport); // default
        $this->assertIsArray($event->context);
        $this->assertNotEmpty($event->processedAt);
        $this->assertNull($event->userId);
        $this->assertGreaterThan(0, $event->memoryUsage);
    }

    #[Test]
    public function it_creates_event_with_all_parameters(): void
    {
        $context = ['source' => 'api', 'version' => '1.0'];

        $event = new McpRequestProcessed(
            123, // numeric ID
            'resources/database',
            ['table' => 'users'],
            ['data' => []],
            75.25,
            'stdio',
            $context,
            'user456'
        );

        $this->assertEquals(123, $event->requestId);
        $this->assertEquals('stdio', $event->transport);
        $this->assertEquals($context, $event->context);
        $this->assertEquals('user456', $event->userId);
    }

    #[Test]
    public function it_gets_component_type_from_method(): void
    {
        $toolEvent = new McpRequestProcessed('1', 'tools/calculator', [], [], 10);
        $this->assertEquals('tool', $toolEvent->getComponentType());

        $resourceEvent = new McpRequestProcessed('2', 'resources/database', [], [], 10);
        $this->assertEquals('resource', $resourceEvent->getComponentType());

        $promptEvent = new McpRequestProcessed('3', 'prompts/template', [], [], 10);
        $this->assertEquals('prompt', $promptEvent->getComponentType());

        $otherEvent = new McpRequestProcessed('4', 'other/method', [], [], 10);
        $this->assertNull($otherEvent->getComponentType());
    }

    #[Test]
    public function it_gets_component_name_from_method(): void
    {
        $event1 = new McpRequestProcessed('1', 'tools/calculator', [], [], 10);
        $this->assertEquals('calculator', $event1->getComponentName());

        $event2 = new McpRequestProcessed('2', 'resources/user_database', [], [], 10);
        $this->assertEquals('user_database', $event2->getComponentName());

        $event3 = new McpRequestProcessed('3', 'invalid', [], [], 10);
        $this->assertNull($event3->getComponentName());
    }

    #[Test]
    public function it_checks_if_request_was_successful(): void
    {
        $successEvent = new McpRequestProcessed('1', 'tools/test', [], ['result' => 'ok'], 10);
        $this->assertTrue($successEvent->wasSuccessful());

        $errorEvent = new McpRequestProcessed('2', 'tools/test', [], ['error' => 'Failed'], 10);
        $this->assertFalse($errorEvent->wasSuccessful());
    }

    #[Test]
    public function it_gets_request_details(): void
    {
        $event = new McpRequestProcessed(
            'req-123',
            'tools/calculator',
            ['a' => 1, 'b' => 2],
            ['result' => 3],
            150.5,
            'http',
            ['version' => '1.0'],
            'user123'
        );

        $details = $event->getRequestDetails();

        $this->assertIsArray($details);
        $this->assertEquals('req-123', $details['request_id']);
        $this->assertEquals('tools/calculator', $details['method']);
        $this->assertEquals('tool', $details['component_type']);
        $this->assertEquals('calculator', $details['component_name']);
        $this->assertEquals(['a' => 1, 'b' => 2], $details['parameters']);
        $this->assertEquals(150.5, $details['execution_time_ms']);
        $this->assertEquals('http', $details['transport']);
        $this->assertGreaterThan(0, $details['memory_usage_bytes']);
        $this->assertEquals($event->processedAt, $details['processed_at']);
        $this->assertEquals('user123', $details['user_id']);
        $this->assertEquals(['version' => '1.0'], $details['context']);
        $this->assertTrue($details['successful']);
    }

    #[Test]
    public function it_gets_performance_metrics(): void
    {
        $event = new McpRequestProcessed(
            '1',
            'tools/test',
            [],
            [],
            250.75,
            'stdio'
        );

        $metrics = $event->getPerformanceMetrics();

        $this->assertIsArray($metrics);
        $this->assertEquals(250.75, $metrics['execution_time_ms']);
        $this->assertGreaterThan(0, $metrics['memory_usage_mb']);
        $this->assertEquals('stdio', $metrics['transport']);
    }

    #[Test]
    public function it_checks_if_execution_time_exceeded_threshold(): void
    {
        $event = new McpRequestProcessed('1', 'tools/test', [], [], 500);

        $this->assertFalse($event->exceededExecutionTime(1000));
        $this->assertFalse($event->exceededExecutionTime(500));
        $this->assertTrue($event->exceededExecutionTime(499));
        $this->assertTrue($event->exceededExecutionTime(100));
    }

    #[Test]
    public function it_formats_execution_time(): void
    {
        $event1 = new McpRequestProcessed('1', 'test', [], [], 150.75);
        $this->assertEquals('150.75ms', $event1->getFormattedExecutionTime());

        $event2 = new McpRequestProcessed('2', 'test', [], [], 1500);
        $this->assertEquals('1.5s', $event2->getFormattedExecutionTime());

        $event3 = new McpRequestProcessed('3', 'test', [], [], 2250.5);
        $this->assertEquals('2.25s', $event3->getFormattedExecutionTime());

        $event4 = new McpRequestProcessed('4', 'test', [], [], 50.25);
        $this->assertEquals('50.25ms', $event4->getFormattedExecutionTime());
    }

    #[Test]
    public function it_serializes_for_broadcasting(): void
    {
        $event = new McpRequestProcessed(
            'req-123',
            'tools/test',
            ['param' => 'value'],
            ['result' => 'success'],
            100,
            'http',
            ['context' => 'test']
        );

        // Test that the event can be serialized (for queue/broadcasting)
        $serialized = serialize($event);
        $this->assertIsString($serialized);

        $unserialized = unserialize($serialized);
        $this->assertEquals($event->requestId, $unserialized->requestId);
        $this->assertEquals($event->method, $unserialized->method);
        $this->assertEquals($event->parameters, $unserialized->parameters);
        $this->assertEquals($event->result, $unserialized->result);
        $this->assertEquals($event->executionTime, $unserialized->executionTime);
    }
}
