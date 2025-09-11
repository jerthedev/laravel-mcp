<?php

namespace JTD\LaravelMCP\Examples;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\App;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\Protocol\NotificationHandler;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Example demonstrating NotificationHandler usage.
 *
 * This example shows how to use the NotificationHandler for real-time
 * notifications in an MCP Laravel application.
 */
class NotificationHandlerExample
{
    /**
     * Demonstrate basic notification broadcasting.
     */
    public function basicNotificationExample(): void
    {
        // Create dependencies
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;

        // Create notification handler
        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler,
            null, // No queue
            [
                'log_notifications' => true,
                'enable_delivery_tracking' => true,
            ]
        );

        // Subscribe a client
        $clientId = 'web-client-1';
        $notificationHandler->subscribe($clientId, [
            NotificationHandler::TYPE_TOOLS_LIST_CHANGED,
            NotificationHandler::TYPE_RESOURCES_UPDATED,
        ]);

        // Broadcast a notification to all subscribers
        $notificationId = $notificationHandler->broadcast(
            NotificationHandler::TYPE_TOOLS_LIST_CHANGED,
            [
                'added' => ['calculator', 'file-reader'],
                'removed' => [],
                'changed' => [],
            ],
            ['priority' => 'normal']
        );

        echo "Broadcast notification: {$notificationId}\n";

        // Send targeted notification
        $targetedId = $notificationHandler->notify(
            $clientId,
            NotificationHandler::TYPE_LOGGING_MESSAGE,
            [
                'level' => 'info',
                'message' => 'Tool registration completed',
                'timestamp' => now()->toISOString(),
            ]
        );

        echo "Targeted notification: {$targetedId}\n";

        // Check delivery status
        $status = $notificationHandler->getDeliveryStatus($notificationId);
        echo 'Delivery status: '.json_encode($status, JSON_PRETTY_PRINT)."\n";
    }

    /**
     * Demonstrate Server-Sent Events (SSE) for web clients.
     */
    public function sseExample(): void
    {
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;

        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler
        );

        $clientId = 'sse-client-1';

        // Create SSE response (would be returned from a Laravel route)
        $sseResponse = $notificationHandler->createSseResponse($clientId, [
            NotificationHandler::TYPE_RESOURCES_UPDATED,
            NotificationHandler::TYPE_PROGRESS,
        ]);

        echo "SSE Response created for client: {$clientId}\n";
        echo "Response status: {$sseResponse->getStatusCode()}\n";

        // In a real application, you would return this response:
        // return $sseResponse;
    }

    /**
     * Demonstrate transport integration for direct notifications.
     */
    public function transportIntegrationExample(?TransportInterface $transport = null): void
    {
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;

        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler
        );

        $clientId = 'stdio-client-1';

        // Subscribe with direct transport
        if ($transport !== null) {
            $notificationHandler->subscribe($clientId, [], $transport);

            // Send notification - will be delivered directly via transport
            $notificationId = $notificationHandler->notify(
                $clientId,
                NotificationHandler::TYPE_PROGRESS,
                [
                    'operation' => 'file-processing',
                    'progress' => 0.75,
                    'message' => 'Processing file 3 of 4',
                ]
            );

            echo "Direct notification sent: {$notificationId}\n";
        }
    }

    /**
     * Demonstrate notification filtering.
     */
    public function filteringExample(): void
    {
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;

        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler
        );

        $clientId = 'filtered-client';

        // Subscribe with basic filter
        $notificationHandler->subscribe($clientId);

        // Set filter to only receive high-priority notifications
        $notificationHandler->updateFilter($clientId, [
            'options.priority' => 'high',
        ]);

        // This notification will be delivered (high priority)
        $highPriorityId = $notificationHandler->notify(
            $clientId,
            NotificationHandler::TYPE_LOGGING_MESSAGE,
            ['message' => 'Critical error occurred'],
            ['priority' => 'high']
        );

        // This notification will be filtered out (low priority)
        $lowPriorityId = $notificationHandler->notify(
            $clientId,
            NotificationHandler::TYPE_LOGGING_MESSAGE,
            ['message' => 'Debug information'],
            ['priority' => 'low']
        );

        echo "High priority notification: {$highPriorityId}\n";
        echo "Low priority notification (filtered): {$lowPriorityId}\n";
    }

    /**
     * Demonstrate queued notification processing.
     */
    public function queuedNotificationExample(): void
    {
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;
        $queue = App::make('queue');

        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler,
            $queue,
            [
                'queue_notifications' => true,
                'queue_name' => 'mcp-notifications',
                'log_notifications' => true,
            ]
        );

        // Subscribe clients
        $notificationHandler->subscribe('client-1');
        $notificationHandler->subscribe('client-2');

        // Broadcast notification - will be queued for processing
        $notificationId = $notificationHandler->broadcast(
            NotificationHandler::TYPE_RESOURCES_LIST_CHANGED,
            [
                'resource_uri' => 'file:///app/data/users.json',
                'change_type' => 'modified',
                'timestamp' => now()->toISOString(),
            ],
            [
                'tries' => 5,
                'backoff' => 10,
            ]
        );

        echo "Queued notification for async processing: {$notificationId}\n";
        echo "Process queue with: php artisan queue:work\n";
    }

    /**
     * Demonstrate subscription management.
     */
    public function subscriptionManagementExample(): void
    {
        $eventDispatcher = App::make(Dispatcher::class);
        $jsonRpcHandler = new JsonRpcHandler;

        $notificationHandler = new NotificationHandler(
            $eventDispatcher,
            $jsonRpcHandler
        );

        // Subscribe multiple clients with different preferences
        $notificationHandler->subscribe('desktop-client', [
            NotificationHandler::TYPE_TOOLS_LIST_CHANGED,
            NotificationHandler::TYPE_PROMPTS_LIST_CHANGED,
        ]);

        $notificationHandler->subscribe('web-client', [
            NotificationHandler::TYPE_RESOURCES_UPDATED,
            NotificationHandler::TYPE_PROGRESS,
        ]);

        $notificationHandler->subscribe('monitoring-client'); // All notifications

        // Check active subscriptions
        $subscriptions = $notificationHandler->getActiveSubscriptions();
        echo 'Active subscriptions: '.count($subscriptions)."\n";

        foreach ($subscriptions as $clientId => $subscription) {
            $typeCount = empty($subscription['types']) ? 'all' : count($subscription['types']);
            echo "  {$clientId}: {$typeCount} notification types\n";
        }

        // Clean up
        $notificationHandler->unsubscribe('desktop-client');
        $notificationHandler->unsubscribe('web-client');
        $notificationHandler->unsubscribe('monitoring-client');

        $subscriptions = $notificationHandler->getActiveSubscriptions();
        echo 'Subscriptions after cleanup: '.count($subscriptions)."\n";
    }
}
