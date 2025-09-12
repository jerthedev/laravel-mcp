<?php

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\Notifications\McpErrorNotification;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Feature tests for Laravel Facade Integration.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Facades\Mcp
 * @covers \JTD\LaravelMCP\McpManager
 */
#[Group('ticket-022')]
#[Group('feature')]
#[Group('integration')]
class LaravelFacadeIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset MCP components
        if (method_exists(Mcp::class, 'reset')) {
            Mcp::reset();
        }
    }

    #[Test]
    public function it_registers_component_and_fires_event(): void
    {
        Event::fake();

        // Register a tool
        Mcp::tool('test_tool', function () {
            return 'tool result';
        }, ['version' => '1.0']);

        // Verify tool is registered
        $this->assertTrue(Mcp::hasTool('test_tool'));

        // Check event was fired
        Event::assertDispatched(McpComponentRegistered::class, function ($event) {
            return $event->componentType === 'tool' &&
                   $event->componentName === 'test_tool' &&
                   $event->metadata === ['version' => '1.0'];
        });
    }

    #[Test]
    public function it_dispatches_async_job(): void
    {
        Queue::fake();

        // Dispatch async MCP request
        $requestId = Mcp::async('tools/calculator', [
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);

        $this->assertNotEmpty($requestId);

        // Verify job was dispatched
        Queue::assertPushed(ProcessMcpRequest::class, function ($job) {
            return $job->method === 'tools/calculator' &&
                   $job->parameters === ['operation' => 'add', 'a' => 5, 'b' => 3];
        });
    }

    #[Test]
    public function it_retrieves_async_result_from_cache(): void
    {
        $requestId = 'test-req-123';
        $result = [
            'status' => 'completed',
            'result' => ['sum' => 8],
            'execution_time_ms' => 150,
            'completed_at' => now()->toISOString(),
        ];

        // Store result in cache
        Cache::put("mcp:async:result:{$requestId}", $result, 3600);

        // Retrieve via facade
        $retrieved = Mcp::asyncResult($requestId);

        $this->assertEquals($result, $retrieved);
    }

    #[Test]
    public function it_retrieves_async_status_from_cache(): void
    {
        $requestId = 'test-req-456';
        $status = [
            'status' => 'processing',
            'updated_at' => now()->toISOString(),
            'attempts' => 1,
        ];

        // Store status in cache
        Cache::put("mcp:async:status:{$requestId}", $status, 300);

        // Retrieve via facade
        $retrieved = Mcp::asyncStatus($requestId);

        $this->assertEquals($status, $retrieved);
    }

    #[Test]
    public function it_sends_error_notification(): void
    {
        Notification::fake();

        // Configure admin email for notifications
        config(['laravel-mcp.notifications.admin_email' => 'admin@example.com']);

        // Send error notification
        Mcp::notifyError(
            'DatabaseError',
            'Failed to connect to database',
            'resources/database',
            ['query' => 'SELECT * FROM users'],
            null,
            'critical'
        );

        // Verify notification was sent
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(['mail' => 'admin@example.com']),
            McpErrorNotification::class,
            function ($notification) {
                return $notification->errorType === 'DatabaseError' &&
                       $notification->severity === 'critical';
            }
        );
    }

    #[Test]
    public function it_fires_request_processed_event(): void
    {
        Event::fake();

        // Fire request processed event
        Mcp::fireRequestProcessed(
            'req-789',
            'tools/calculator',
            ['operation' => 'multiply', 'a' => 4, 'b' => 5],
            ['result' => 20],
            75.5,
            'http'
        );

        // Verify event was dispatched
        Event::assertDispatched(McpRequestProcessed::class, function ($event) {
            return $event->requestId === 'req-789' &&
                   $event->method === 'tools/calculator' &&
                   $event->result === ['result' => 20] &&
                   $event->executionTime === 75.5;
        });
    }

    #[Test]
    public function it_registers_event_listeners(): void
    {
        Event::fake();

        $callbackCalled = false;

        // Register listener for component registration
        Mcp::onComponentRegistered(function ($event) use (&$callbackCalled) {
            $callbackCalled = true;
        });

        // Fire component registered event
        Mcp::fireComponentRegistered('tool', 'test', new \stdClass);

        Event::assertDispatched(McpComponentRegistered::class);
    }

    #[Test]
    public function it_configures_events_jobs_and_notifications(): void
    {
        // Enable events
        Mcp::withEvents();
        $this->assertTrue(config('laravel-mcp.events.enabled'));

        // Disable events
        Mcp::withoutEvents();
        $this->assertFalse(config('laravel-mcp.events.enabled'));

        // Enable queue with custom name
        Mcp::withQueue('mcp-priority');
        $this->assertTrue(config('laravel-mcp.queue.enabled'));
        $this->assertEquals('mcp-priority', config('laravel-mcp.queue.default'));

        // Disable queue
        Mcp::withoutQueue();
        $this->assertFalse(config('laravel-mcp.queue.enabled'));

        // Enable notifications with channels
        Mcp::withNotifications(['mail', 'slack']);
        $this->assertTrue(config('laravel-mcp.notifications.enabled'));
        $this->assertEquals(['mail', 'slack'], config('laravel-mcp.notifications.channels'));

        // Disable notifications
        Mcp::withoutNotifications();
        $this->assertFalse(config('laravel-mcp.notifications.enabled'));
    }

    #[Test]
    public function it_provides_server_information(): void
    {
        // Register some components
        Mcp::tool('tool1', function () {});
        Mcp::tool('tool2', function () {});
        Mcp::resource('resource1', function () {});

        // Get server info
        $info = Mcp::getServerInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('version', $info);
        $this->assertArrayHasKey('protocol_version', $info);
        $this->assertArrayHasKey('capabilities', $info);
        $this->assertArrayHasKey('components', $info);

        $this->assertEquals(2, $info['components']['tools']);
        $this->assertEquals(1, $info['components']['resources']);
        $this->assertEquals(0, $info['components']['prompts']);
    }

    #[Test]
    public function it_provides_server_statistics(): void
    {
        // Set some stats in cache
        Cache::put('mcp:stats:requests_processed', 100);
        Cache::put('mcp:stats:errors_count', 5);
        Cache::put('mcp:stats:avg_response_time', 150.5);

        // Get server stats
        $stats = Mcp::getServerStats();

        $this->assertIsArray($stats);
        $this->assertEquals(100, $stats['requests_processed']);
        $this->assertEquals(5, $stats['errors_count']);
        $this->assertEquals(150.5, $stats['average_response_time']);
        $this->assertGreaterThan(0, $stats['memory_usage']);
        $this->assertGreaterThan(0, $stats['peak_memory']);
    }

    #[Test]
    public function it_manages_debug_mode(): void
    {
        // Enable debug mode
        Mcp::enableDebugMode();
        $this->assertTrue(Mcp::isDebugMode());
        $this->assertTrue(config('laravel-mcp.debug'));

        // Disable debug mode
        Mcp::disableDebugMode();
        $this->assertFalse(Mcp::isDebugMode());
        $this->assertFalse(config('laravel-mcp.debug'));
    }

    #[Test]
    public function it_chains_fluent_methods(): void
    {
        Event::fake();

        // Chain multiple operations
        Mcp::withEvents()
            ->withQueue('high-priority')
            ->withNotifications(['database', 'mail'])
            ->tool('chained_tool', function () {
                return 'result';
            })
            ->resource('chained_resource', function () {
                return [];
            })
            ->enableDebugMode();

        // Verify all operations took effect
        $this->assertTrue(config('laravel-mcp.events.enabled'));
        $this->assertEquals('high-priority', config('laravel-mcp.queue.default'));
        $this->assertEquals(['database', 'mail'], config('laravel-mcp.notifications.channels'));
        $this->assertTrue(Mcp::hasTool('chained_tool'));
        $this->assertTrue(Mcp::hasResource('chained_resource'));
        $this->assertTrue(Mcp::isDebugMode());

        // Verify events were dispatched
        Event::assertDispatched(McpComponentRegistered::class, 2);
    }

    #[Test]
    public function it_handles_error_notification_with_exception(): void
    {
        Notification::fake();
        config(['laravel-mcp.notifications.admin_email' => 'admin@example.com']);

        $exception = new \RuntimeException('Database connection failed');

        // Send error notification with exception
        Mcp::notifyError(
            'ConnectionError',
            'Unable to establish database connection',
            'resources/database',
            ['host' => 'localhost', 'port' => 3306],
            $exception,
            'critical'
        );

        // Verify notification includes exception details
        Notification::assertSentTo(
            new \Illuminate\Notifications\AnonymousNotifiable(['mail' => 'admin@example.com']),
            McpErrorNotification::class,
            function ($notification) use ($exception) {
                return $notification->exception === $exception &&
                       $notification->errorType === 'ConnectionError';
            }
        );
    }
}
