<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when a notification is broadcast to all subscribers.
 *
 * This event is dispatched when the NotificationHandler broadcasts
 * a notification to all subscribed clients. It can be used for
 * logging, monitoring, or triggering additional business logic.
 */
class NotificationBroadcast
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
     * Get the notification parameters.
     */
    public function getParams(): array
    {
        return $this->notification['params'] ?? [];
    }

    /**
     * Get the notification timestamp.
     */
    public function getTimestamp(): string
    {
        return $this->notification['timestamp'];
    }

    /**
     * Get the notification options.
     */
    public function getOptions(): array
    {
        return $this->notification['options'] ?? [];
    }
}