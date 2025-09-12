<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP tool is executed.
 *
 * This event is dispatched after a tool has been successfully executed,
 * containing information about the tool, parameters, result, and performance metrics.
 */
class McpToolExecuted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The name of the executed tool.
     */
    public string $toolName;

    /**
     * The parameters passed to the tool.
     */
    public array $parameters;

    /**
     * The result returned by the tool.
     */
    public mixed $result;

    /**
     * The execution time in milliseconds.
     */
    public float $executionTime;

    /**
     * The user ID who executed the tool (if authenticated).
     */
    public ?string $userId;

    /**
     * Additional metadata about the execution.
     */
    public array $metadata;

    /**
     * The timestamp when the tool was executed.
     */
    public string $timestamp;

    /**
     * Create a new event instance.
     *
     * @param  string  $toolName  The name of the executed tool
     * @param  array  $parameters  The parameters passed to the tool
     * @param  mixed  $result  The result returned by the tool
     * @param  float  $executionTime  The execution time in milliseconds
     * @param  string|null  $userId  The user ID who executed the tool
     * @param  array  $metadata  Additional metadata about the execution
     */
    public function __construct(
        string $toolName,
        array $parameters,
        mixed $result,
        float $executionTime,
        ?string $userId = null,
        array $metadata = []
    ) {
        $this->toolName = $toolName;
        $this->parameters = $parameters;
        $this->result = $result;
        $this->executionTime = $executionTime;
        $this->userId = $userId ?? (auth()->check() ? auth()->id() : null);
        $this->metadata = $metadata;
        $this->timestamp = now()->toISOString();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        // Broadcast to private user channel if user is authenticated
        if ($this->userId) {
            $channels[] = new PrivateChannel('mcp.user.'.$this->userId);
        }

        // Broadcast to tool-specific channel
        $channels[] = new PrivateChannel('mcp.tool.'.$this->toolName);

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'tool' => $this->toolName,
            'execution_time' => $this->executionTime,
            'timestamp' => $this->timestamp,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'tool.executed';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return config('laravel-mcp.events.broadcast.tool_executed', false);
    }

    /**
     * Get a summary of the execution for logging.
     */
    public function toArray(): array
    {
        return [
            'tool' => $this->toolName,
            'parameters' => $this->parameters,
            'execution_time' => $this->executionTime,
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
            'result_type' => gettype($this->result),
        ];
    }

    /**
     * Get the tags for logging and monitoring.
     */
    public function tags(): array
    {
        return [
            'mcp',
            'tool',
            'tool:'.$this->toolName,
            $this->userId ? 'user:'.$this->userId : 'anonymous',
        ];
    }
}
