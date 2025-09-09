<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a notification has been sent.
 *
 * This event is dispatched when a notification has been sent to
 * the transport layer, but before delivery confirmation is received.
 * It indicates that the notification is in transit.
 */
class NotificationSent
{
    use Dispatchable, SerializesModels;

    /**
     * The notification data.
     */
    public array $notification;

    /**
     * The client ID that received the notification.
     */
    public string $clientId;

    /**
     * Create a new event instance.
     *
     * @param  array  $notification  The notification data
     * @param  string  $clientId  The client ID
     */
    public function __construct(array $notification, string $clientId)
    {
        $this->notification = $notification;
        $this->clientId = $clientId;
    }

    /**
     * Get the notification ID.
     */
    public function getNotificationId(): string
    {
        return $this->notification['id'];
    }

    /**
     * Get the client ID.
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Get the notification type.
     */
    public function getType(): string
    {
        return $this->notification['type'];
    }

    /**
     * Get the notification parameters.
     */
    public function getParams(): array
    {
        return $this->notification['params'] ?? [];
    }
}