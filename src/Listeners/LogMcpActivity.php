<?php

namespace JTD\LaravelMCP\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpPromptGenerated;
use JTD\LaravelMCP\Events\McpResourceAccessed;
use JTD\LaravelMCP\Events\McpToolExecuted;

/**
 * Logs MCP activity for auditing and monitoring purposes.
 *
 * This listener handles all MCP activity events and logs them with appropriate
 * context and severity levels. It implements ShouldQueue for async processing.
 */
class LogMcpActivity implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'mcp-logs';

    /**
     * The time (seconds) before the job should be processed.
     *
     * @var int
     */
    public $delay = 0;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * Handle the McpToolExecuted event.
     */
    public function handleToolExecuted(McpToolExecuted $event): void
    {
        $context = [
            'tool' => $event->toolName,
            'parameters' => $this->sanitizeParameters($event->parameters),
            'execution_time' => $event->executionTime,
            'user_id' => $event->userId,
            'timestamp' => $event->timestamp,
            'metadata' => $event->metadata,
            'tags' => $event->tags(),
        ];

        // Log with appropriate level based on execution time
        if ($event->executionTime > 5000) { // More than 5 seconds
            Log::warning('MCP Tool executed with high latency', $context);
        } else {
            Log::info('MCP Tool executed', $context);
        }

        // Log to dedicated MCP channel if configured
        if (config('laravel-mcp.logging.dedicated_channel')) {
            Log::channel('mcp')->info('Tool executed: '.$event->toolName, $context);
        }
    }

    /**
     * Handle the McpResourceAccessed event.
     */
    public function handleResourceAccessed(McpResourceAccessed $event): void
    {
        $context = [
            'resource' => $event->resourceName,
            'action' => $event->action,
            'parameters' => $this->sanitizeParameters($event->parameters),
            'user_id' => $event->userId,
            'timestamp' => $event->timestamp,
            'metadata' => $event->metadata,
            'tags' => $event->tags(),
        ];

        // Different log levels based on action type
        $logLevel = match (true) {
            $event->isSubscriptionAction() => 'notice',
            $event->isListAction() => 'info',
            $event->isReadAction() => 'info',
            default => 'debug',
        };

        Log::log($logLevel, 'MCP Resource accessed', $context);

        // Log to dedicated MCP channel if configured
        if (config('laravel-mcp.logging.dedicated_channel')) {
            Log::channel('mcp')->log($logLevel, 'Resource accessed: '.$event->resourceName, $context);
        }
    }

    /**
     * Handle the McpPromptGenerated event.
     */
    public function handlePromptGenerated(McpPromptGenerated $event): void
    {
        $context = [
            'prompt' => $event->promptName,
            'arguments' => $this->sanitizeParameters($event->arguments),
            'content_length' => $event->contentLength,
            'user_id' => $event->userId,
            'timestamp' => $event->timestamp,
            'metadata' => $event->metadata,
            'tags' => $event->tags(),
            'preview' => $event->getContentPreview(50),
        ];

        // Log with different levels based on content size
        if ($event->isLargeContent()) {
            Log::notice('MCP Prompt generated large content', $context);
        } else {
            Log::info('MCP Prompt generated', $context);
        }

        // Log to dedicated MCP channel if configured
        if (config('laravel-mcp.logging.dedicated_channel')) {
            Log::channel('mcp')->info('Prompt generated: '.$event->promptName, $context);
        }
    }

    /**
     * Handle any MCP event (generic handler).
     */
    public function handle(object $event): void
    {
        // Route to specific handler based on event type
        match (true) {
            $event instanceof McpToolExecuted => $this->handleToolExecuted($event),
            $event instanceof McpResourceAccessed => $this->handleResourceAccessed($event),
            $event instanceof McpPromptGenerated => $this->handlePromptGenerated($event),
            default => $this->handleUnknownEvent($event),
        };
    }

    /**
     * Handle unknown MCP events.
     */
    protected function handleUnknownEvent(object $event): void
    {
        Log::debug('Unknown MCP event', [
            'event_class' => get_class($event),
            'event_data' => method_exists($event, 'toArray') ? $event->toArray() : [],
        ]);
    }

    /**
     * Sanitize parameters to remove sensitive information.
     */
    protected function sanitizeParameters(array $parameters): array
    {
        $sensitive = config('laravel-mcp.logging.sensitive_keys', [
            'password',
            'token',
            'secret',
            'api_key',
            'private_key',
            'credit_card',
            'ssn',
        ]);

        return $this->recursiveSanitize($parameters, $sensitive);
    }

    /**
     * Recursively sanitize an array.
     */
    protected function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            // Check if key contains any sensitive keyword
            $isSensitive = false;
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            }
        }

        return $data;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to log MCP activity', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(object $event): bool
    {
        // Don't queue in testing environment
        if (app()->environment('testing')) {
            return false;
        }

        // Check if queuing is enabled for this event type
        return config('laravel-mcp.events.queue_listeners', true);
    }

    /**
     * Get the name of the listener's queue.
     */
    public function viaQueue(): string
    {
        return config('laravel-mcp.events.queue_name', 'mcp-logs');
    }

    /**
     * Get the name of the listener's connection.
     */
    public function viaConnection(): string
    {
        return config('laravel-mcp.events.queue_connection', 'redis');
    }
}
