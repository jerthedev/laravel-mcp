<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a notification is queued for delivery.
 *
 * This event is dispatched when a notification is added to the
 * queue for asynchronous processing. It can be used for tracking
 * notification processing metrics.
 */
class NotificationQueued
{
    use Dispatchable, SerializesModels;

    /**
     * The notification data.
     */
    public array $notification;

    /**
     * Create a new event instance.
     *
     * @param  array  $notification  The notification data
     */
    public function __construct(array $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Get the notification ID.
     */
    public function getNotificationId(): string
    {
        return $this->notification['id'];
    }

    /**
     * Get the notification type.
     */
    public function getType(): string
    {
        return $this->notification['type'];
    }

    /**
     * Get the target client ID if specified.
     */
    public function getClientId(): ?string
    {
        return $this->notification['client_id'] ?? null;
    }

    /**
     * Check if this is a broadcast notification.
     */
    public function isBroadcast(): bool
    {
        return ! isset($this->notification['client_id']);
    }
}
