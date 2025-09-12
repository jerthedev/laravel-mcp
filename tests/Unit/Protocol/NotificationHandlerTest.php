<?php

namespace Tests\Unit\Protocol;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use JTD\LaravelMCP\Events\NotificationBroadcast;
use JTD\LaravelMCP\Events\NotificationDelivered;
use JTD\LaravelMCP\Events\NotificationQueued;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Protocol\NotificationHandler;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use Mockery;

/**
 * Test cases for NotificationHandler.
 */
class NotificationHandlerTest extends TestCase
{
    protected NotificationHandler $handler;

    protected $eventDispatcher;

    protected $jsonRpcHandler;

    protected $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = Mockery::mock(Dispatcher::class);
        $this->jsonRpcHandler = Mockery::mock(JsonRpcHandlerInterface::class);
        $this->queue = Mockery::mock(Queue::class);

        // Set up expectations for the listen() calls made in setupEventListeners()
        $this->eventDispatcher->shouldReceive('listen')
            ->with('mcp.tools.registered', Mockery::type('callable'))
            ->once();
        $this->eventDispatcher->shouldReceive('listen')
            ->with('mcp.resources.registered', Mockery::type('callable'))
            ->once();
        $this->eventDispatcher->shouldReceive('listen')
            ->with('mcp.resources.updated', Mockery::type('callable'))
            ->once();
        $this->eventDispatcher->shouldReceive('listen')
            ->with('mcp.prompts.registered', Mockery::type('callable'))
            ->once();

        $this->handler = new NotificationHandler(
            $this->eventDispatcher,
            $this->jsonRpcHandler,
            $this->queue,
            ['log_notifications' => false] // Disable logging for tests
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_broadcast_notification_fires_events(): void
    {
        // Arrange
        $this->eventDispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(NotificationBroadcast::class))
            ->once();

        // Act
        $notificationId = $this->handler->broadcast('test/notification', ['key' => 'value']);

        // Assert
        $this->assertNotEmpty($notificationId);
        $this->assertStringStartsWith('mcp_', $notificationId);
    }

    public function test_subscribe_and_unsubscribe(): void
    {
        // Arrange
        $clientId = 'test-client';
        $types = ['test/notification'];

        // Act - Subscribe
        $this->handler->subscribe($clientId, $types);

        // Assert - Client is subscribed
        $subscriptions = $this->handler->getActiveSubscriptions();
        $this->assertArrayHasKey($clientId, $subscriptions);
        $this->assertEquals($types, $subscriptions[$clientId]['types']);
        $this->assertTrue($subscriptions[$clientId]['active']);

        // Act - Unsubscribe
        $this->handler->unsubscribe($clientId);

        // Assert - Client is unsubscribed
        $subscriptions = $this->handler->getActiveSubscriptions();
        $this->assertArrayNotHasKey($clientId, $subscriptions);
    }

    public function test_notification_filtering(): void
    {
        // Arrange
        $clientId = 'test-client';
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('isConnected')->andReturn(true);

        // Subscribe with type filter
        $this->handler->subscribe($clientId, ['allowed/type'], $transport);

        $this->jsonRpcHandler->shouldReceive('createRequest')
            ->with('allowed/type', ['data' => 'test'])
            ->once()
            ->andReturn(['jsonrpc' => '2.0', 'method' => 'allowed/type', 'params' => ['data' => 'test']]);

        $transport->shouldReceive('send')
            ->with('{"jsonrpc":"2.0","method":"allowed\/type","params":{"data":"test"}}')
            ->once();

        $this->eventDispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(NotificationDelivered::class))
            ->once();

        // Act - Send allowed notification
        $notificationId = $this->handler->notify($clientId, 'allowed/type', ['data' => 'test']);

        // Assert
        $this->assertNotEmpty($notificationId);
        $this->assertStringStartsWith('mcp_', $notificationId);
        // The transport should receive the notification (verified by mock expectations)
    }

    public function test_queued_notification_delivery(): void
    {
        // Arrange - Create a separate handler with queueing enabled
        // We need to set up the listen expectations again for this new handler instance
        $eventDispatcher = Mockery::mock(Dispatcher::class);
        $eventDispatcher->shouldReceive('listen')
            ->with('mcp.tools.registered', Mockery::type('callable'))
            ->once();
        $eventDispatcher->shouldReceive('listen')
            ->with('mcp.resources.registered', Mockery::type('callable'))
            ->once();
        $eventDispatcher->shouldReceive('listen')
            ->with('mcp.resources.updated', Mockery::type('callable'))
            ->once();
        $eventDispatcher->shouldReceive('listen')
            ->with('mcp.prompts.registered', Mockery::type('callable'))
            ->once();

        $handler = new NotificationHandler(
            $eventDispatcher,
            $this->jsonRpcHandler,
            $this->queue,
            [
                'queue_notifications' => true,
                'queue_name' => 'test-queue',
                'log_notifications' => false,
            ]
        );

        $eventDispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(NotificationBroadcast::class))
            ->once();
        $eventDispatcher->shouldReceive('dispatch')
            ->with(Mockery::type(NotificationQueued::class))
            ->once();

        $this->queue->shouldReceive('push')
            ->with(
                'mcp-notification-delivery',
                Mockery::on(function ($data) {
                    return isset($data['notification']) &&
                           $data['notification']['type'] === 'test/queued';
                }),
                'test-queue'
            )
            ->once();

        // Act
        $notificationId = $handler->broadcast('test/queued', ['queued' => true]);

        // Assert
        $this->assertNotEmpty($notificationId);
    }

    public function test_sse_response_creation(): void
    {
        // Arrange
        $clientId = 'sse-client';
        $types = ['sse/notification'];

        // Act
        $response = $this->handler->createSseResponse($clientId, $types);

        // Assert
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_delivery_status_tracking(): void
    {
        // Arrange
        $this->eventDispatcher->shouldReceive('dispatch')->andReturn(null);

        $clientId = 'tracked-client';
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('isConnected')->andReturn(true);
        $transport->shouldReceive('send')->andReturn(null);

        $this->handler->subscribe($clientId, [], $transport);

        $this->jsonRpcHandler->shouldReceive('createRequest')
            ->andReturn(['jsonrpc' => '2.0', 'method' => 'test/tracked', 'params' => []]);

        // Act
        $notificationId = $this->handler->notify($clientId, 'test/tracked');

        // Assert
        $status = $this->handler->getDeliveryStatus($notificationId);
        $this->assertNotNull($status);
        $this->assertEquals($notificationId, $status['id']);
        $this->assertArrayHasKey('clients', $status);
    }

    public function test_pending_notifications_management(): void
    {
        // Arrange
        $this->eventDispatcher->shouldReceive('dispatch')->andReturn(null);

        $clientId = 'pending-client';

        // Subscribe without transport (will create pending notifications)
        $this->handler->subscribe($clientId);

        $this->jsonRpcHandler->shouldReceive('createRequest')
            ->andReturn(['jsonrpc' => '2.0', 'method' => 'test/pending', 'params' => []]);

        // Act - Send notification (will be pending due to no transport)
        $this->handler->notify($clientId, 'test/pending');

        // Assert - Notification is pending
        $this->assertGreaterThan(0, $this->handler->getPendingNotificationsCount());

        // Act - Clear pending notifications
        $cleared = $this->handler->clearPendingNotifications($clientId);

        // Assert - Notifications were cleared
        $this->assertGreaterThan(0, $cleared);
        $this->assertEquals(0, $this->handler->getPendingNotificationsCount());
    }

    public function test_filter_update(): void
    {
        // Arrange
        $clientId = 'filtered-client';

        $this->handler->subscribe($clientId);

        // Act - Set filter
        $filter = ['params.priority' => 'high'];
        $this->handler->updateFilter($clientId, $filter);

        // Act - Clear filter
        $this->handler->updateFilter($clientId, []);

        // Assert - No exception thrown, filter operations completed
        $this->assertTrue(true);
    }

    public function test_notification_types(): void
    {
        // Test that all notification type constants are defined
        $this->assertEquals('notifications/tools/list_changed', NotificationHandler::TYPE_TOOLS_LIST_CHANGED);
        $this->assertEquals('notifications/resources/list_changed', NotificationHandler::TYPE_RESOURCES_LIST_CHANGED);
        $this->assertEquals('notifications/resources/updated', NotificationHandler::TYPE_RESOURCES_UPDATED);
        $this->assertEquals('notifications/prompts/list_changed', NotificationHandler::TYPE_PROMPTS_LIST_CHANGED);
        $this->assertEquals('notifications/message', NotificationHandler::TYPE_LOGGING_MESSAGE);
        $this->assertEquals('notifications/progress', NotificationHandler::TYPE_PROGRESS);
        $this->assertEquals('notifications/cancelled', NotificationHandler::TYPE_CANCELLED);
    }
}
