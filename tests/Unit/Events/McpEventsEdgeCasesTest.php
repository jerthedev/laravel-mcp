<?php

namespace JTD\LaravelMCP\Tests\Unit\Events;

use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Edge case tests for MCP Events.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Events\McpComponentRegistered
 * @covers \JTD\LaravelMCP\Events\McpRequestProcessed
 */
#[Group('ticket-022')]
#[Group('unit')]
#[Group('events')]
class McpEventsEdgeCasesTest extends TestCase
{
    #[Test]
    public function mcp_component_registered_handles_string_component(): void
    {
        $event = new McpComponentRegistered('tool', 'test_tool', 'App\Tools\TestTool');

        $this->assertEquals('App\Tools\TestTool', $event->component);

        $details = $event->getComponentDetails();
        $this->assertEquals('App\Tools\TestTool', $details['class']);
    }

    #[Test]
    public function mcp_component_registered_handles_object_component(): void
    {
        $object = new \stdClass;
        $object->property = 'value';

        $event = new McpComponentRegistered('resource', 'test_resource', $object);

        $this->assertSame($object, $event->component);

        $details = $event->getComponentDetails();
        $this->assertEquals(\stdClass::class, $details['class']);
    }

    #[Test]
    public function mcp_component_registered_handles_unknown_component_type(): void
    {
        $event = new McpComponentRegistered('custom_type', 'test', 'TestClass');

        $this->assertEquals('Custom_type', $event->getComponentTypeLabel());
    }

    #[Test]
    public function mcp_component_registered_metadata_methods_work(): void
    {
        $metadata = [
            'version' => '1.0.0',
            'author' => 'Test Author',
            'nested' => ['key' => 'value'],
        ];

        $event = new McpComponentRegistered('tool', 'test', 'TestClass', $metadata);

        // Test hasMetadata
        $this->assertTrue($event->hasMetadata('version'));
        $this->assertTrue($event->hasMetadata('author'));
        $this->assertTrue($event->hasMetadata('nested'));
        $this->assertFalse($event->hasMetadata('nonexistent'));

        // Test getMetadata with existing keys
        $this->assertEquals('1.0.0', $event->getMetadata('version'));
        $this->assertEquals('Test Author', $event->getMetadata('author'));
        $this->assertEquals(['key' => 'value'], $event->getMetadata('nested'));

        // Test getMetadata with default value
        $this->assertEquals('default', $event->getMetadata('nonexistent', 'default'));
        $this->assertNull($event->getMetadata('nonexistent'));
    }

    #[Test]
    public function mcp_request_processed_handles_various_request_ids(): void
    {
        // String request ID
        $event1 = new McpRequestProcessed('string-id', 'tools/test', [], [], 0);
        $this->assertEquals('string-id', $event1->requestId);

        // Numeric request ID
        $event2 = new McpRequestProcessed(12345, 'tools/test', [], [], 0);
        $this->assertEquals(12345, $event2->requestId);

        // UUID-style request ID
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $event3 = new McpRequestProcessed($uuid, 'tools/test', [], [], 0);
        $this->assertEquals($uuid, $event3->requestId);
    }

    #[Test]
    public function mcp_request_processed_handles_various_result_types(): void
    {
        // Array result
        $event1 = new McpRequestProcessed('req-1', 'tools/test', [], ['data' => 'value'], 0);
        $this->assertEquals(['data' => 'value'], $event1->result);
        $this->assertTrue($event1->wasSuccessful());

        // String result
        $event2 = new McpRequestProcessed('req-2', 'tools/test', [], 'string result', 0);
        $this->assertEquals('string result', $event2->result);

        // Null result
        $event3 = new McpRequestProcessed('req-3', 'tools/test', [], null, 0);
        $this->assertNull($event3->result);

        // Object result
        $object = new \stdClass;
        $event4 = new McpRequestProcessed('req-4', 'tools/test', [], $object, 0);
        $this->assertSame($object, $event4->result);
    }

    #[Test]
    public function mcp_request_processed_transport_types(): void
    {
        // Default transport
        $event1 = new McpRequestProcessed('req-1', 'tools/test', [], [], 0);
        $this->assertEquals('http', $event1->transport);

        // Stdio transport
        $event2 = new McpRequestProcessed('req-2', 'tools/test', [], [], 0, 'stdio');
        $this->assertEquals('stdio', $event2->transport);

        // Custom transport
        $event3 = new McpRequestProcessed('req-3', 'tools/test', [], [], 0, 'websocket');
        $this->assertEquals('websocket', $event3->transport);
    }

    #[Test]
    public function mcp_request_processed_execution_time_edge_cases(): void
    {
        // Zero execution time
        $event1 = new McpRequestProcessed('req-1', 'tools/test', [], [], 0);
        $this->assertEquals(0, $event1->executionTime);
        $this->assertEquals('0ms', $event1->getFormattedExecutionTime());
        $this->assertFalse($event1->exceededExecutionTime(1));

        // Very small execution time
        $event2 = new McpRequestProcessed('req-2', 'tools/test', [], [], 0.001);
        $this->assertEquals(0.001, $event2->executionTime);
        $this->assertEquals('0ms', $event2->getFormattedExecutionTime());

        // Large execution time
        $event3 = new McpRequestProcessed('req-3', 'tools/test', [], [], 999999.99);
        $this->assertEquals(999999.99, $event3->executionTime);
        $this->assertEquals('1000s', $event3->getFormattedExecutionTime());
        $this->assertTrue($event3->exceededExecutionTime(1000));
    }

    #[Test]
    public function mcp_request_processed_context_handling(): void
    {
        $context = [
            'source' => 'api',
            'version' => '2.0',
            'nested' => ['data' => ['deep' => 'value']],
        ];

        $event = new McpRequestProcessed(
            'req-1',
            'tools/test',
            [],
            [],
            0,
            'http',
            $context
        );

        $this->assertEquals($context, $event->context);

        $details = $event->getRequestDetails();
        $this->assertEquals($context, $details['context']);
    }

    #[Test]
    public function mcp_request_processed_component_extraction(): void
    {
        // Standard tool method
        $event1 = new McpRequestProcessed('req-1', 'tools/calculator', [], [], 0);
        $this->assertEquals('tool', $event1->getComponentType());
        $this->assertEquals('calculator', $event1->getComponentName());

        // Standard resource method
        $event2 = new McpRequestProcessed('req-2', 'resources/database', [], [], 0);
        $this->assertEquals('resource', $event2->getComponentType());
        $this->assertEquals('database', $event2->getComponentName());

        // Standard prompt method
        $event3 = new McpRequestProcessed('req-3', 'prompts/template', [], [], 0);
        $this->assertEquals('prompt', $event3->getComponentType());
        $this->assertEquals('template', $event3->getComponentName());

        // Method with multiple slashes
        $event4 = new McpRequestProcessed('req-4', 'tools/category/subcategory/item', [], [], 0);
        $this->assertEquals('tool', $event4->getComponentType());
        $this->assertEquals('item', $event4->getComponentName());

        // Method without slash
        $event5 = new McpRequestProcessed('req-5', 'invalidmethod', [], [], 0);
        $this->assertNull($event5->getComponentType());
        $this->assertNull($event5->getComponentName());

        // Empty method
        $event6 = new McpRequestProcessed('req-6', '', [], [], 0);
        $this->assertNull($event6->getComponentType());
        $this->assertNull($event6->getComponentName());
    }

    #[Test]
    public function mcp_request_processed_error_detection(): void
    {
        // Success - no error property
        $event1 = new McpRequestProcessed('req-1', 'tools/test', [], ['result' => 'success'], 0);
        $this->assertTrue($event1->wasSuccessful());

        // Error - has error property
        $event2 = new McpRequestProcessed('req-2', 'tools/test', [], ['error' => 'Failed'], 0);
        $this->assertFalse($event2->wasSuccessful());

        // Error - has error key with null value (still counts as error)
        $event3 = new McpRequestProcessed('req-3', 'tools/test', [], ['error' => null], 0);
        $this->assertFalse($event3->wasSuccessful());

        // Success - has other properties including 'error' in nested data
        $event4 = new McpRequestProcessed('req-4', 'tools/test', [], ['data' => ['error' => 'nested']], 0);
        $this->assertTrue($event4->wasSuccessful());
    }

    #[Test]
    public function event_timestamps_are_valid_iso8601(): void
    {
        $event1 = new McpComponentRegistered('tool', 'test', 'TestClass');
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([.]\d{3,6})?(Z|[+-]\d{2}:\d{2})$/',
            $event1->registeredAt
        );

        $event2 = new McpRequestProcessed('req-1', 'tools/test', [], [], 0);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([.]\d{3,6})?(Z|[+-]\d{2}:\d{2})$/',
            $event2->processedAt
        );
    }

    #[Test]
    public function mcp_request_processed_memory_usage_is_positive(): void
    {
        $event = new McpRequestProcessed('req-1', 'tools/test', [], [], 0);

        $this->assertGreaterThan(0, $event->memoryUsage);

        $metrics = $event->getPerformanceMetrics();
        $this->assertGreaterThan(0, $metrics['memory_usage_mb']);
    }
}
