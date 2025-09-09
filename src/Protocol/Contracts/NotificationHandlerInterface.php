<?php

namespace JTD\LaravelMCP\Protocol\Contracts;

use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Interface for MCP notification handling.
 *
 * This interface defines the contract for handling real-time notifications
 * in the MCP protocol. It provides methods for broadcasting notifications,
 * managing subscriptions, and tracking delivery status.
 */
interface NotificationHandlerInterface
{
    /**
     * Send a notification to all subscribed clients.
     *
     * @param  string  $type  The notification type
     * @param  array  $params  The notification parameters
     * @param  array  $options  Additional options
     * @return string The notification ID
     */
    public function broadcast(string $type, array $params = [], array $options = []): string;

    /**
     * Send a notification to a specific client.
     *
     * @param  string  $clientId  The client identifier
     * @param  string  $type  The notification type
     * @param  array  $params  The notification parameters
     * @param  array  $options  Additional options
     * @return string The notification ID
     */
    public function notify(string $clientId, string $type, array $params = [], array $options = []): string;

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
    ): void;

    /**
     * Unsubscribe a client from notifications.
     *
     * @param  string  $clientId  The client identifier
     */
    public function unsubscribe(string $clientId): void;

    /**
     * Create a Server-Sent Events (SSE) response for HTTP transport.
     *
     * @param  string  $clientId  The client identifier
     * @param  array  $types  Notification types to stream
     * @return StreamedResponse
     */
    public function createSseResponse(string $clientId, array $types = []): StreamedResponse;

    /**
     * Get notification delivery status.
     *
     * @param  string  $notificationId  The notification ID
     * @return array|null
     */
    public function getDeliveryStatus(string $notificationId): ?array;

    /**
     * Get active subscriptions.
     *
     * @return array
     */
    public function getActiveSubscriptions(): array;

    /**
     * Get pending notifications count.
     *
     * @return int
     */
    public function getPendingNotificationsCount(): int;

    /**
     * Clear pending notifications for a client.
     *
     * @param  string  $clientId  The client identifier
     * @return int The number of cleared notifications
     */
    public function clearPendingNotifications(string $clientId): int;

    /**
     * Update notification filter for a client.
     *
     * @param  string  $clientId  The client identifier
     * @param  array  $filter  The filter criteria
     */
    public function updateFilter(string $clientId, array $filter): void;
}