<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Protocol\Contracts\NotificationHandlerInterface;

/**
 * Job for processing queued notification delivery.
 *
 * This job handles the asynchronous delivery of MCP notifications
 * when queue-based processing is enabled. It ensures reliable
 * delivery with proper error handling and retry logic.
 */
class ProcessNotificationDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The notification data to process.
     */
    protected array $notification;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     *
     * @param  array  $notification  The notification data
     */
    public function __construct(array $notification)
    {
        $this->notification = $notification;

        // Set job properties based on notification options
        if (isset($notification['options']['tries'])) {
            $this->tries = (int) $notification['options']['tries'];
        }

        if (isset($notification['options']['backoff'])) {
            $this->backoff = (int) $notification['options']['backoff'];
        }

        // Set queue name if specified
        if (isset($notification['options']['queue'])) {
            $this->onQueue($notification['options']['queue']);
        }
    }

    /**
     * Execute the job.
     */
    public function handle(NotificationHandlerInterface $notificationHandler): void
    {
        try {
            Log::debug('Processing queued MCP notification', [
                'notification_id' => $this->notification['id'],
                'type' => $this->notification['type'],
                'attempt' => $this->attempts(),
            ]);

            // Use reflection to access the protected method for sync delivery
            $reflection = new \ReflectionClass($notificationHandler);
            $method = $reflection->getMethod('deliverNotificationSync');
            $method->setAccessible(true);
            $method->invoke($notificationHandler, $this->notification);

            Log::info('Successfully processed queued MCP notification', [
                'notification_id' => $this->notification['id'],
                'type' => $this->notification['type'],
            ]);

        } catch (\Throwable $e) {
            Log::error('Failed to process queued MCP notification', [
                'notification_id' => $this->notification['id'],
                'type' => $this->notification['type'],
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
                'max_tries' => $this->tries,
            ]);

            // If we've exhausted all attempts, mark as permanently failed
            if ($this->attempts() >= $this->tries) {
                $this->handleFailedDelivery($e);
            }

            // Re-throw to trigger retry logic
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('MCP notification delivery job permanently failed', [
            'notification_id' => $this->notification['id'],
            'type' => $this->notification['type'],
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->handleFailedDelivery($exception);
    }

    /**
     * Handle failed notification delivery.
     */
    protected function handleFailedDelivery(\Throwable $exception): void
    {
        // Here you could implement additional failure handling logic
        // such as storing failed notifications for later analysis,
        // sending alerts, or attempting alternative delivery methods

        // For now, we just log the permanent failure
        Log::critical('MCP notification permanently failed delivery', [
            'notification' => $this->notification,
            'exception' => [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'mcp-notification',
            'notification:'.$this->notification['id'],
            'type:'.$this->notification['type'],
        ];
    }

    /**
     * Calculate the number of seconds to wait before retrying the job.
     */
    public function backoff(): int
    {
        // Exponential backoff: 5, 15, 45 seconds for attempts 1, 2, 3
        return $this->backoff * pow(3, $this->attempts() - 1);
    }

    /**
     * Determine if the job should be retried based on the exception.
     *
     * @param  \Throwable  $exception
     * @return bool
     */
    public function retryUntil(): \DateTime
    {
        // Allow retries for up to 5 minutes from the first attempt
        return now()->addMinutes(5);
    }
}
