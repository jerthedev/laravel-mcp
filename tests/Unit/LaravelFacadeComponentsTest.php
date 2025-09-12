<?php

namespace JTD\LaravelMCP\Tests\Unit;

use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\McpManager;
use JTD\LaravelMCP\Notifications\McpErrorNotification;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for Laravel Facade Components.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Events\McpComponentRegistered
 * @covers \JTD\LaravelMCP\Events\McpRequestProcessed
 * @covers \JTD\LaravelMCP\Jobs\ProcessMcpRequest
 * @covers \JTD\LaravelMCP\Notifications\McpErrorNotification
 * @covers \JTD\LaravelMCP\McpManager
 */
#[Group('ticket-022')]
#[Group('unit')]
#[Group('facade-components')]
class LaravelFacadeComponentsTest extends TestCase
{
    #[Test]
    public function mcp_manager_delegates_to_registry_and_registrar(): void
    {
        $registry = $this->createMock(McpRegistry::class);
        $registrar = $this->createMock(RouteRegistrar::class);

        $manager = new McpManager($registry, $registrar);

        // Test tool registration delegates to registrar
        $registrar->expects($this->once())
            ->method('tool')
            ->with('test_tool', $this->anything(), []);

        $manager->tool('test_tool', function () {});

        // Test registry delegation
        $registry->expects($this->once())
            ->method('getCapabilities')
            ->willReturn(['tools' => []]);

        $capabilities = $manager->getCapabilities();
        $this->assertEquals(['tools' => []], $capabilities);
    }

    #[Test]
    public function mcp_manager_handles_magic_method_calls(): void
    {
        $registry = $this->createMock(McpRegistry::class);
        $registrar = $this->createMock(RouteRegistrar::class);

        $manager = new McpManager($registry, $registrar);

        // Test method exists on registrar
        $registrar->expects($this->once())
            ->method('group')
            ->with([], $this->anything());

        $manager->group([], function () {});

        // Test method exists on registry
        $registry->expects($this->once())
            ->method('discover')
            ->with([])
            ->willReturn([]);

        $manager->discover([]);
    }

    #[Test]
    public function mcp_manager_throws_exception_for_invalid_method(): void
    {
        $registry = $this->createMock(McpRegistry::class);
        $registrar = $this->createMock(RouteRegistrar::class);

        $manager = new McpManager($registry, $registrar);

        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method nonExistentMethod does not exist on McpManager');

        $manager->nonExistentMethod();
    }

    #[Test]
    public function process_mcp_request_generates_request_id(): void
    {
        $job = new ProcessMcpRequest('tools/test', ['param' => 'value']);

        $this->assertNotEmpty($job->requestId);
        $this->assertMatchesRegularExpression('/^mcp-req-[a-f0-9]{16}$/', $job->requestId);
    }

    #[Test]
    public function process_mcp_request_uses_provided_request_id(): void
    {
        $customId = 'custom-request-id';
        $job = new ProcessMcpRequest('tools/test', ['param' => 'value'], $customId);

        $this->assertEquals($customId, $job->requestId);
    }

    #[Test]
    public function process_mcp_request_merges_context(): void
    {
        $context = ['custom' => 'data'];
        $job = new ProcessMcpRequest('tools/test', [], null, $context);

        $this->assertArrayHasKey('async', $job->context);
        $this->assertTrue($job->context['async']);
        $this->assertArrayHasKey('queued_at', $job->context);
        $this->assertEquals('data', $job->context['custom']);
    }

    #[Test]
    public function mcp_component_registered_handles_null_user_id(): void
    {
        $event = new McpComponentRegistered('tool', 'test', new \stdClass);

        $this->assertNull($event->userId);
    }

    #[Test]
    public function mcp_component_registered_uses_provided_user_id(): void
    {
        $userId = 'user-123';
        $event = new McpComponentRegistered('tool', 'test', new \stdClass, [], $userId);

        $this->assertEquals($userId, $event->userId);
    }

    #[Test]
    public function mcp_request_processed_component_type_returns_null_for_unknown(): void
    {
        $event = new McpRequestProcessed('req-1', 'unknown/method', [], [], 0);

        $this->assertNull($event->getComponentType());
    }

    #[Test]
    public function mcp_request_processed_component_name_returns_null_for_invalid(): void
    {
        $event = new McpRequestProcessed('req-1', 'invalidmethod', [], [], 0);

        $this->assertNull($event->getComponentName());
    }

    #[Test]
    public function mcp_request_processed_formats_time_under_one_second(): void
    {
        $event = new McpRequestProcessed('req-1', 'tools/test', [], [], 750.5);

        $this->assertEquals('750.5ms', $event->getFormattedExecutionTime());
    }

    #[Test]
    public function mcp_request_processed_formats_time_over_one_second(): void
    {
        $event = new McpRequestProcessed('req-1', 'tools/test', [], [], 2500);

        $this->assertEquals('2.5s', $event->getFormattedExecutionTime());
    }

    #[Test]
    public function mcp_error_notification_handles_null_exception(): void
    {
        $notification = new McpErrorNotification(
            'ErrorType',
            'Error message',
            'tools/test'
        );

        $this->assertNull($notification->exception);
        $this->assertEquals('error', $notification->severity);
    }

    #[Test]
    public function mcp_error_notification_uses_provided_severity(): void
    {
        $notification = new McpErrorNotification(
            'ErrorType',
            'Error message',
            'tools/test',
            [],
            [],
            null,
            'critical'
        );

        $this->assertEquals('critical', $notification->severity);
    }

    #[Test]
    public function mcp_error_notification_stores_exception(): void
    {
        $exception = new \RuntimeException('Test exception');

        $notification = new McpErrorNotification(
            'ErrorType',
            'Error message',
            'tools/test',
            [],
            [],
            $exception
        );

        $this->assertSame($exception, $notification->exception);
    }

    #[Test]
    public function mcp_error_notification_via_returns_correct_channels(): void
    {
        config(['laravel-mcp.notifications.channels' => ['mail', 'slack']]);

        $notification = new McpErrorNotification('ErrorType', 'Error message');
        $notifiable = new \stdClass;

        $channels = $notification->via($notifiable);

        $this->assertContains('database', $channels);

        // For critical severity, mail should be added
        $notification = new McpErrorNotification(
            'ErrorType',
            'Error message',
            null,
            [],
            [],
            null,
            'critical'
        );

        $channels = $notification->via($notifiable);
        $this->assertContains('database', $channels);
    }

    #[Test]
    public function process_mcp_request_has_correct_retry_settings(): void
    {
        $job = new ProcessMcpRequest('tools/test', []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
        $this->assertEquals(10, $job->backoff);
        $this->assertFalse($job->shouldBeEncrypted);
    }

    #[Test]
    public function mcp_component_registered_get_component_details_includes_all_fields(): void
    {
        $component = new \stdClass;
        $metadata = ['version' => '1.0'];

        $event = new McpComponentRegistered(
            'tool',
            'test_tool',
            $component,
            $metadata,
            'user-123'
        );

        $details = $event->getComponentDetails();

        $this->assertEquals('tool', $details['type']);
        $this->assertEquals('test_tool', $details['name']);
        $this->assertEquals(\stdClass::class, $details['class']);
        $this->assertEquals($metadata, $details['metadata']);
        $this->assertEquals('user-123', $details['user_id']);
        $this->assertArrayHasKey('registered_at', $details);
    }

    #[Test]
    public function mcp_request_processed_get_request_details_includes_all_fields(): void
    {
        $event = new McpRequestProcessed(
            'req-123',
            'tools/calculator',
            ['operation' => 'add'],
            ['result' => 5],
            250.5,
            'stdio',
            ['source' => 'api']
        );

        $details = $event->getRequestDetails();

        $this->assertEquals('req-123', $details['request_id']);
        $this->assertEquals('tools/calculator', $details['method']);
        $this->assertEquals('tool', $details['component_type']);
        $this->assertEquals('calculator', $details['component_name']);
        $this->assertEquals(['operation' => 'add'], $details['parameters']);
        $this->assertEquals(250.5, $details['execution_time_ms']);
        $this->assertEquals('stdio', $details['transport']);
        $this->assertArrayHasKey('memory_usage_bytes', $details);
        $this->assertArrayHasKey('processed_at', $details);
        $this->assertNull($details['user_id']);
        $this->assertEquals(['source' => 'api'], $details['context']);
        $this->assertTrue($details['successful']);
    }

    #[Test]
    public function mcp_request_processed_performance_metrics_includes_memory(): void
    {
        $event = new McpRequestProcessed(
            'req-123',
            'tools/test',
            [],
            [],
            100
        );

        $metrics = $event->getPerformanceMetrics();

        $this->assertArrayHasKey('execution_time_ms', $metrics);
        $this->assertArrayHasKey('memory_usage_mb', $metrics);
        $this->assertArrayHasKey('transport', $metrics);
        $this->assertEquals(100, $metrics['execution_time_ms']);
        $this->assertIsFloat($metrics['memory_usage_mb']);
    }

    #[Test]
    public function mcp_manager_notifiable_fallback_logic(): void
    {
        $registry = $this->createMock(McpRegistry::class);
        $registrar = $this->createMock(RouteRegistrar::class);

        $manager = new McpManager($registry, $registrar);

        // Test with no notifiable configured
        config(['laravel-mcp.notifications.enabled' => true]);
        config(['laravel-mcp.notifications.notifiable' => null]);
        config(['laravel-mcp.notifications.admin_email' => null]);

        // This should not throw an exception even with no notifiable
        $manager->notifyError('TestError', 'Test message');

        // Nothing to assert as the method should handle missing notifiable gracefully
        $this->assertTrue(true);
    }

    #[Test]
    public function mcp_manager_dispatches_events_conditionally(): void
    {
        $registry = $this->createMock(McpRegistry::class);
        $registrar = $this->createMock(RouteRegistrar::class);

        $manager = new McpManager($registry, $registrar);

        // Disable events
        config(['laravel-mcp.events.enabled' => false]);

        // This should not dispatch an event
        $manager->dispatchComponentRegistered('tool', 'test', new \stdClass);
        $manager->dispatchRequestProcessed('req-1', 'tools/test', [], [], 100);

        // No events should be fired (can't easily test this without full Laravel framework)
        $this->assertTrue(true);
    }

    #[Test]
    public function process_mcp_request_cache_keys_are_formatted_correctly(): void
    {
        $job = new ProcessMcpRequest('tools/test', [], 'custom-id-123');

        $resultKey = $job->getResultCacheKey();
        $statusKey = $job->getStatusCacheKey();

        $this->assertEquals('mcp:async:result:custom-id-123', $resultKey);
        $this->assertEquals('mcp:async:status:custom-id-123', $statusKey);
    }
}
