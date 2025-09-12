<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP request has been processed.
 *
 * This event is dispatched after an MCP request (tool execution, resource access,
 * or prompt generation) has been successfully processed. It includes details about
 * the request, response, and execution metrics.
 */
class McpRequestProcessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The JSON-RPC request ID.
     */
    public string|int $requestId;

    /**
     * The MCP method that was called.
     */
    public string $method;

    /**
     * The request parameters.
     */
    public array $parameters;

    /**
     * The response result.
     */
    public mixed $result;

    /**
     * The execution time in milliseconds.
     */
    public float $executionTime;

    /**
     * The transport type used (http or stdio).
     */
    public string $transport;

    /**
     * Additional context about the request.
     */
    public array $context;

    /**
     * The timestamp when the request was processed.
     */
    public string $processedAt;

    /**
     * The user ID who made the request (if applicable).
     */
    public ?string $userId;

    /**
     * Memory usage in bytes.
     */
    public int $memoryUsage;

    /**
     * Create a new event instance.
     *
     * @param  string|int  $requestId  The JSON-RPC request ID
     * @param  string  $method  The MCP method called
     * @param  array  $parameters  The request parameters
     * @param  mixed  $result  The response result
     * @param  float  $executionTime  Execution time in milliseconds
     * @param  string  $transport  Transport type (http or stdio)
     * @param  array  $context  Additional context
     * @param  string|null  $userId  The user ID who made the request
     */
    public function __construct(
        string|int $requestId,
        string $method,
        array $parameters,
        mixed $result,
        float $executionTime,
        string $transport = 'http',
        array $context = [],
        ?string $userId = null
    ) {
        $this->requestId = $requestId;
        $this->method = $method;
        $this->parameters = $parameters;
        $this->result = $result;
        $this->executionTime = $executionTime;
        $this->transport = $transport;
        $this->context = $context;
        $this->processedAt = $this->getCurrentTimestamp();
        $this->userId = $userId ?? $this->getCurrentUserId();
        $this->memoryUsage = memory_get_peak_usage(true);
    }

    /**
     * Get the current timestamp.
     */
    protected function getCurrentTimestamp(): string
    {
        // Use now() if available (Laravel is bootstrapped)
        if (function_exists('now')) {
            return now()->toISOString();
        }

        // Fallback to native PHP
        return (new \DateTime)->format('c');
    }

    /**
     * Get the current authenticated user ID if available.
     */
    protected function getCurrentUserId(): ?string
    {
        // Check if Laravel auth is available and user is authenticated
        if (function_exists('app') && app()->has('auth') && app('auth')->check()) {
            return (string) app('auth')->id();
        }

        return null;
    }

    /**
     * Get the component type from the method name.
     */
    public function getComponentType(): ?string
    {
        if (str_starts_with($this->method, 'tools/')) {
            return 'tool';
        } elseif (str_starts_with($this->method, 'resources/')) {
            return 'resource';
        } elseif (str_starts_with($this->method, 'prompts/')) {
            return 'prompt';
        }

        return null;
    }

    /**
     * Get the component name from the method.
     */
    public function getComponentName(): ?string
    {
        $parts = explode('/', $this->method);

        return count($parts) > 1 ? end($parts) : null;
    }

    /**
     * Check if the request was successful.
     */
    public function wasSuccessful(): bool
    {
        return ! array_key_exists('error', $this->result);
    }

    /**
     * Get request details for logging.
     */
    public function getRequestDetails(): array
    {
        return [
            'request_id' => $this->requestId,
            'method' => $this->method,
            'component_type' => $this->getComponentType(),
            'component_name' => $this->getComponentName(),
            'parameters' => $this->parameters,
            'execution_time_ms' => $this->executionTime,
            'transport' => $this->transport,
            'memory_usage_bytes' => $this->memoryUsage,
            'processed_at' => $this->processedAt,
            'user_id' => $this->userId,
            'context' => $this->context,
            'successful' => $this->wasSuccessful(),
        ];
    }

    /**
     * Get performance metrics.
     */
    public function getPerformanceMetrics(): array
    {
        return [
            'execution_time_ms' => $this->executionTime,
            'memory_usage_mb' => round($this->memoryUsage / 1024 / 1024, 2),
            'transport' => $this->transport,
        ];
    }

    /**
     * Check if execution time exceeded threshold.
     *
     * @param  float  $threshold  Threshold in milliseconds
     */
    public function exceededExecutionTime(float $threshold): bool
    {
        return $this->executionTime > $threshold;
    }

    /**
     * Get formatted execution time.
     */
    public function getFormattedExecutionTime(): string
    {
        if ($this->executionTime < 1000) {
            return round($this->executionTime, 2).'ms';
        }

        return round($this->executionTime / 1000, 2).'s';
    }
}
