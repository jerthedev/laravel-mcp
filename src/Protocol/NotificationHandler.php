<?php

namespace JTD\LaravelMCP\Protocol;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Events\NotificationBroadcast;
use JTD\LaravelMCP\Events\NotificationDelivered;
use JTD\LaravelMCP\Events\NotificationFailed;
use JTD\LaravelMCP\Events\NotificationQueued;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Protocol\Contracts\NotificationHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Real-time notification handler for MCP protocol.
 *
 * This class manages real-time notifications in the MCP Laravel package,
 * providing Server-Sent Events (SSE) for HTTP transport and stdio
 * notifications for direct transport. It integrates with Laravel's event
 * system, supports notification queuing, subscription management, and
 * tracks notification delivery status.
 */
class NotificationHandler implements NotificationHandlerInterface
{
    /**
     * Notification statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * Notification types.
     */
    public const TYPE_TOOLS_LIST_CHANGED = 'notifications/tools/list_changed';

    public const TYPE_RESOURCES_LIST_CHANGED = 'notifications/resources/list_changed';

    public const TYPE_RESOURCES_UPDATED = 'notifications/resources/updated';

    public const TYPE_PROMPTS_LIST_CHANGED = 'notifications/prompts/list_changed';

    public const TYPE_LOGGING_MESSAGE = 'notifications/message';

    public const TYPE_PROGRESS = 'notifications/progress';

    public const TYPE_CANCELLED = 'notifications/cancelled';

    /**
     * Event dispatcher instance.
     */
    protected Dispatcher $eventDispatcher;

    /**
     * Queue instance for async notification processing.
     */
    protected ?Queue $queue = null;

    /**
     * JSON-RPC handler for creating notification messages.
     */
    protected JsonRpcHandlerInterface $jsonRpcHandler;

    /**
     * Active subscriptions keyed by client identifier.
     */
    protected array $subscriptions = [];

    /**
     * Notification filters keyed by client identifier.
     */
    protected array $filters = [];

    /**
     * Pending notifications to be delivered.
     */
    protected Collection $pendingNotifications;

    /**
     * Notification delivery tracking.
     */
    protected array $deliveryTracking = [];

    /**
     * SSE connections for HTTP transport.
     */
    protected array $sseConnections = [];

    /**
     * Transport instances for direct notification delivery.
     */
    protected array $transports = [];

    /**
     * Configuration options.
     */
    protected array $config = [
        'queue_notifications' => false,
        'queue_connection' => 'default',
        'queue_name' => 'mcp-notifications',
        'delivery_timeout' => 30,
        'max_pending_notifications' => 1000,
        'sse_heartbeat_interval' => 30,
        'enable_delivery_tracking' => true,
        'log_notifications' => true,
    ];

    /**
     * Create a new notification handler instance.
     */
    public function __construct(
        Dispatcher $eventDispatcher,
        JsonRpcHandlerInterface $jsonRpcHandler,
        ?Queue $queue = null,
        array $config = []
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->jsonRpcHandler = $jsonRpcHandler;
        $this->queue = $queue;
        $this->config = array_merge($this->config, $config);
        $this->pendingNotifications = new Collection;

        $this->setupEventListeners();
    }

    /**
     * Send a notification to all subscribed clients.
     *
     * @param  string  $type  The notification type
     * @param  array  $params  The notification parameters
     * @param  array  $options  Additional options
     * @return string The notification ID
     */
    public function broadcast(string $type, array $params = [], array $options = []): string
    {
        $notificationId = $this->generateNotificationId();

        $notification = [
            'id' => $notificationId,
            'type' => $type,
            'params' => $params,
            'timestamp' => now()->toISOString(),
            'options' => $options,
        ];

        if ($this->config['log_notifications']) {
            Log::info('Broadcasting MCP notification', [
                'id' => $notificationId,
                'type' => $type,
                'subscriber_count' => count($this->subscriptions),
            ]);
        }

        // Fire broadcast event
        $this->eventDispatcher->dispatch(new NotificationBroadcast($notification));

        // Process notification delivery
        if ($this->config['queue_notifications'] && $this->queue !== null) {
            $this->queueNotification($notification);
        } else {
            $this->deliverNotificationSync($notification);
        }

        return $notificationId;
    }

    /**
     * Send a notification to a specific client.
     *
     * @param  string  $clientId  The client identifier
     * @param  string  $type  The notification type
     * @param  array  $params  The notification parameters
     * @param  array  $options  Additional options
     * @return string The notification ID
     */
    public function notify(string $clientId, string $type, array $params = [], array $options = []): string
    {
        $notificationId = $this->generateNotificationId();

        $notification = [
            'id' => $notificationId,
            'type' => $type,
            'params' => $params,
            'timestamp' => now()->toISOString(),
            'client_id' => $clientId,
            'options' => $options,
        ];

        if ($this->config['log_notifications']) {
            Log::info('Sending MCP notification', [
                'id' => $notificationId,
                'type' => $type,
                'client_id' => $clientId,
            ]);
        }

        // Process notification delivery
        if ($this->config['queue_notifications'] && $this->queue !== null) {
            $this->queueNotification($notification);
        } else {
            $this->deliverToClient($clientId, $notification);
        }

        return $notificationId;
    }

    /**
     * Subscribe a client to notifications with optional filter.
     *
     * @param  string  $clientId  The client identifier
     * @param  array  $types  Notification types to subscribe to (empty for all)
     * @param  TransportInterface|null  $transport  Optional transport for direct delivery
     * @param  array  $filter  Optional filter criteria
     */
    public function subscribe(
        string $clientId,
        array $types = [],
        ?TransportInterface $transport = null,
        array $filter = []
    ): void {
        $subscription = [
            'client_id' => $clientId,
            'types' => $types,
            'transport' => $transport,
            'subscribed_at' => now(),
            'active' => true,
        ];

        $this->subscriptions[$clientId] = $subscription;

        if (! empty($filter)) {
            $this->filters[$clientId] = $filter;
        }

        if ($transport !== null) {
            $this->transports[$clientId] = $transport;
        }

        if ($this->config['log_notifications']) {
            Log::info('Client subscribed to MCP notifications', [
                'client_id' => $clientId,
                'types' => $types,
                'has_transport' => $transport !== null,
                'has_filter' => ! empty($filter),
            ]);
        }
    }

    /**
     * Unsubscribe a client from notifications.
     *
     * @param  string  $clientId  The client identifier
     */
    public function unsubscribe(string $clientId): void
    {
        unset(
            $this->subscriptions[$clientId],
            $this->filters[$clientId],
            $this->transports[$clientId],
            $this->sseConnections[$clientId]
        );

        if ($this->config['log_notifications']) {
            Log::info('Client unsubscribed from MCP notifications', [
                'client_id' => $clientId,
            ]);
        }
    }

    /**
     * Create a Server-Sent Events (SSE) response for HTTP transport.
     *
     * @param  string  $clientId  The client identifier
     * @param  array  $types  Notification types to stream
     */
    public function createSseResponse(string $clientId, array $types = []): StreamedResponse
    {
        return new StreamedResponse(function () use ($clientId, $types) {
            // Set SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable Nginx buffering

            // Store SSE connection
            $this->sseConnections[$clientId] = [
                'started_at' => now(),
                'last_heartbeat' => now(),
            ];

            // Subscribe client
            $this->subscribe($clientId, $types);

            // Send initial connection event
            $this->sendSseEvent('connected', [
                'client_id' => $clientId,
                'server_time' => now()->toISOString(),
            ]);

            // Keep connection alive and send pending notifications
            $lastHeartbeat = time();
            $heartbeatInterval = $this->config['sse_heartbeat_interval'];

            while (connection_status() === CONNECTION_NORMAL) {
                // Send heartbeat
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    $this->sendSseHeartbeat();
                    $lastHeartbeat = time();
                    $this->sseConnections[$clientId]['last_heartbeat'] = now();
                }

                // Send pending notifications
                $this->processPendingNotificationsForClient($clientId);

                // Small delay to prevent CPU spinning
                usleep(100000); // 100ms

                // Flush output
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
            }

            // Clean up when connection closes
            $this->unsubscribe($clientId);
        });
    }

    /**
     * Get notification delivery status.
     *
     * @param  string  $notificationId  The notification ID
     */
    public function getDeliveryStatus(string $notificationId): ?array
    {
        return $this->deliveryTracking[$notificationId] ?? null;
    }

    /**
     * Get active subscriptions.
     */
    public function getActiveSubscriptions(): array
    {
        return array_filter($this->subscriptions, fn ($sub) => $sub['active']);
    }

    /**
     * Get pending notifications count.
     */
    public function getPendingNotificationsCount(): int
    {
        return $this->pendingNotifications->count();
    }

    /**
     * Clear pending notifications for a client.
     *
     * @param  string  $clientId  The client identifier
     */
    public function clearPendingNotifications(string $clientId): int
    {
        $cleared = $this->pendingNotifications
            ->where('client_id', $clientId)
            ->count();

        $this->pendingNotifications = $this->pendingNotifications
            ->reject(fn ($notification) => ($notification['client_id'] ?? null) === $clientId);

        return $cleared;
    }

    /**
     * Update notification filter for a client.
     *
     * @param  string  $clientId  The client identifier
     * @param  array  $filter  The filter criteria
     */
    public function updateFilter(string $clientId, array $filter): void
    {
        if (empty($filter)) {
            unset($this->filters[$clientId]);
        } else {
            $this->filters[$clientId] = $filter;
        }
    }

    /**
     * Set up event listeners for Laravel events.
     */
    protected function setupEventListeners(): void
    {
        // Listen for component registry changes to send notifications
        $this->eventDispatcher->listen('mcp.tools.registered', function ($event) {
            $this->broadcast(self::TYPE_TOOLS_LIST_CHANGED, [
                'tools' => $event->tools ?? [],
            ]);
        });

        $this->eventDispatcher->listen('mcp.resources.registered', function ($event) {
            $this->broadcast(self::TYPE_RESOURCES_LIST_CHANGED, [
                'resources' => $event->resources ?? [],
            ]);
        });

        $this->eventDispatcher->listen('mcp.resources.updated', function ($event) {
            $this->broadcast(self::TYPE_RESOURCES_UPDATED, [
                'resource' => $event->resource ?? null,
                'changes' => $event->changes ?? [],
            ]);
        });

        $this->eventDispatcher->listen('mcp.prompts.registered', function ($event) {
            $this->broadcast(self::TYPE_PROMPTS_LIST_CHANGED, [
                'prompts' => $event->prompts ?? [],
            ]);
        });
    }

    /**
     * Queue a notification for asynchronous delivery.
     */
    protected function queueNotification(array $notification): void
    {
        $this->updateDeliveryTracking($notification['id'], self::STATUS_QUEUED);

        $this->eventDispatcher->dispatch(new NotificationQueued($notification));

        if ($this->queue !== null) {
            $this->queue->push(
                'mcp-notification-delivery',
                ['notification' => $notification],
                $this->config['queue_name']
            );
        }
    }

    /**
     * Deliver notification synchronously to all subscribers.
     */
    protected function deliverNotificationSync(array $notification): void
    {
        $this->updateDeliveryTracking($notification['id'], self::STATUS_SENT);

        foreach ($this->getEligibleClients($notification) as $clientId) {
            $this->deliverToClient($clientId, $notification);
        }
    }

    /**
     * Deliver notification to a specific client.
     */
    protected function deliverToClient(string $clientId, array $notification): void
    {
        try {
            // Check if client should receive this notification
            if (! $this->shouldDeliverToClient($clientId, $notification)) {
                return;
            }

            // Create JSON-RPC notification message
            $message = $this->jsonRpcHandler->createRequest(
                $notification['type'],
                $notification['params']
                // No ID makes it a notification
            );

            // Try direct transport delivery first
            if (isset($this->transports[$clientId])) {
                $transport = $this->transports[$clientId];
                if ($transport->isConnected()) {
                    $transport->send(json_encode($message));
                    $this->markDelivered($notification['id'], $clientId);

                    return;
                }
            }

            // For SSE connections, add to pending notifications
            if (isset($this->sseConnections[$clientId])) {
                $this->addToPendingNotifications($clientId, $message);

                return;
            }

            // If no transport available, add to pending
            $this->addToPendingNotifications($clientId, $message);

        } catch (\Throwable $e) {
            $this->markFailed($notification['id'], $clientId, $e->getMessage());

            Log::error('Failed to deliver MCP notification', [
                'notification_id' => $notification['id'],
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            $this->eventDispatcher->dispatch(new NotificationFailed($notification, $clientId, $e));
        }
    }

    /**
     * Get eligible clients for a notification.
     */
    protected function getEligibleClients(array $notification): array
    {
        $clientId = $notification['client_id'] ?? null;

        // If targeted to specific client
        if ($clientId !== null) {
            return isset($this->subscriptions[$clientId]) ? [$clientId] : [];
        }

        // For broadcast notifications, filter by type subscription
        $eligibleClients = [];
        foreach ($this->subscriptions as $id => $subscription) {
            if (! $subscription['active']) {
                continue;
            }

            // If no types specified, client gets all notifications
            if (empty($subscription['types'])) {
                $eligibleClients[] = $id;

                continue;
            }

            // Check if client subscribed to this notification type
            if (in_array($notification['type'], $subscription['types'])) {
                $eligibleClients[] = $id;
            }
        }

        return $eligibleClients;
    }

    /**
     * Check if notification should be delivered to client.
     */
    protected function shouldDeliverToClient(string $clientId, array $notification): bool
    {
        // Check if client is subscribed
        if (! isset($this->subscriptions[$clientId]) || ! $this->subscriptions[$clientId]['active']) {
            return false;
        }

        // Apply client-specific filters
        if (isset($this->filters[$clientId])) {
            return $this->applyFilter($this->filters[$clientId], $notification);
        }

        return true;
    }

    /**
     * Apply notification filter.
     */
    protected function applyFilter(array $filter, array $notification): bool
    {
        foreach ($filter as $key => $value) {
            $notificationValue = data_get($notification, $key);

            // Handle array values (e.g., checking if value is in array)
            if (is_array($value)) {
                if (! in_array($notificationValue, $value)) {
                    return false;
                }

                continue;
            }

            // Handle callback filters
            if ($value instanceof Closure) {
                if (! $value($notificationValue, $notification)) {
                    return false;
                }

                continue;
            }

            // Handle exact matches
            if ($notificationValue !== $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * Add notification to pending queue for client.
     */
    protected function addToPendingNotifications(string $clientId, array $message): void
    {
        // Check pending notifications limit
        if ($this->pendingNotifications->count() >= $this->config['max_pending_notifications']) {
            // Remove oldest notification
            $this->pendingNotifications->shift();
        }

        $this->pendingNotifications->push([
            'client_id' => $clientId,
            'message' => $message,
            'queued_at' => now(),
        ]);
    }

    /**
     * Process pending notifications for SSE client.
     */
    protected function processPendingNotificationsForClient(string $clientId): void
    {
        $clientNotifications = $this->pendingNotifications
            ->where('client_id', $clientId)
            ->take(10); // Process up to 10 at a time

        foreach ($clientNotifications as $index => $pendingNotification) {
            $this->sendSseEvent('notification', $pendingNotification['message']);

            // Remove from pending
            $this->pendingNotifications = $this->pendingNotifications
                ->reject(fn ($item, $key) => $key === $index);
        }
    }

    /**
     * Send Server-Sent Event.
     */
    protected function sendSseEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";
    }

    /**
     * Send SSE heartbeat.
     */
    protected function sendSseHeartbeat(): void
    {
        $this->sendSseEvent('heartbeat', [
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Update delivery tracking for a notification.
     */
    protected function updateDeliveryTracking(string $notificationId, string $status): void
    {
        if (! $this->config['enable_delivery_tracking']) {
            return;
        }

        if (! isset($this->deliveryTracking[$notificationId])) {
            $this->deliveryTracking[$notificationId] = [
                'id' => $notificationId,
                'status' => $status,
                'created_at' => now(),
                'clients' => [],
            ];
        } else {
            $this->deliveryTracking[$notificationId]['status'] = $status;
        }

        $this->deliveryTracking[$notificationId]['updated_at'] = now();
    }

    /**
     * Mark notification as delivered to a client.
     */
    protected function markDelivered(string $notificationId, string $clientId): void
    {
        if (! $this->config['enable_delivery_tracking']) {
            return;
        }

        if (! isset($this->deliveryTracking[$notificationId])) {
            $this->updateDeliveryTracking($notificationId, self::STATUS_DELIVERED);
        }

        $this->deliveryTracking[$notificationId]['clients'][$clientId] = [
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ];

        $this->eventDispatcher->dispatch(new NotificationDelivered([
            'id' => $notificationId,
        ], $clientId));
    }

    /**
     * Mark notification as failed for a client.
     */
    protected function markFailed(string $notificationId, string $clientId, string $error): void
    {
        if (! $this->config['enable_delivery_tracking']) {
            return;
        }

        if (! isset($this->deliveryTracking[$notificationId])) {
            $this->updateDeliveryTracking($notificationId, self::STATUS_FAILED);
        }

        $this->deliveryTracking[$notificationId]['clients'][$clientId] = [
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'error' => $error,
        ];
    }

    /**
     * Generate unique notification ID.
     */
    protected function generateNotificationId(): string
    {
        return 'mcp_'.Str::uuid()->toString();
    }
}
