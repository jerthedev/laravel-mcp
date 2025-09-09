<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Event fired when a notification delivery has failed.
 *
 * This event is dispatched when a notification could not be
 * delivered to a client due to an error. It includes the
 * error details for debugging and monitoring purposes.
 */
class NotificationFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The notification data.
     */
    public array $notification;

    /**
     * The client ID that should have received the notification.
     */
    public string $clientId;

    /**
     * The exception that caused the failure.
     */
    public Throwable $exception;

    /**
     * Create a new event instance.
     *
     * @param  array  $notification  The notification data
     * @param  string  $clientId  The client ID
     * @param  Throwable  $exception  The failure exception
     */
    public function __construct(array $notification, string $clientId, Throwable $exception)
    {
        $this->notification = $notification;
        $this->clientId = $clientId;
        $this->exception = $exception;
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
        return $this->notification['type'] ?? 'unknown';
    }

    /**
     * Get the failure exception.
     */
    public function getException(): Throwable
    {
        return $this->exception;
    }

    /**
     * Get the error message.
     */
    public function getErrorMessage(): string
    {
        return $this->exception->getMessage();
    }

    /**
     * Get the notification parameters.
     */
    public function getParams(): array
    {
        return $this->notification['params'] ?? [];
    }

    /**
     * Check if the failure is retryable.
     */
    public function isRetryable(): bool
    {
        // Consider network and transport errors as retryable
        $retryableExceptions = [
            'Connection refused',
            'Connection timeout',
            'Network unreachable',
            'Temporary failure',
        ];

        $message = $this->exception->getMessage();

        foreach ($retryableExceptions as $retryable) {
            if (stripos($message, $retryable) !== false) {
                return true;
            }
        }

        return false;
    }
}