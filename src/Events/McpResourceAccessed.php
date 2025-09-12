<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP resource is accessed.
 *
 * This event is dispatched when a resource is read, listed, or subscribed to,
 * containing information about the resource, action, and parameters.
 */
class McpResourceAccessed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The name of the accessed resource.
     */
    public string $resourceName;

    /**
     * The action performed on the resource (read, list, subscribe, etc.).
     */
    public string $action;

    /**
     * The parameters passed with the resource access.
     */
    public array $parameters;

    /**
     * The user ID who accessed the resource (if authenticated).
     */
    public ?string $userId;

    /**
     * The result of the resource access (optional).
     */
    public mixed $result;

    /**
     * Additional metadata about the access.
     */
    public array $metadata;

    /**
     * The timestamp when the resource was accessed.
     */
    public string $timestamp;

    /**
     * Create a new event instance.
     *
     * @param  string  $resourceName  The name of the accessed resource
     * @param  string  $action  The action performed on the resource
     * @param  array  $parameters  The parameters passed with the access
     * @param  string|null  $userId  The user ID who accessed the resource
     * @param  mixed  $result  The result of the resource access
     * @param  array  $metadata  Additional metadata about the access
     */
    public function __construct(
        string $resourceName,
        string $action,
        array $parameters,
        ?string $userId = null,
        mixed $result = null,
        array $metadata = []
    ) {
        $this->resourceName = $resourceName;
        $this->action = $action;
        $this->parameters = $parameters;
        $this->userId = $userId ?? (auth()->check() ? auth()->id() : null);
        $this->result = $result;
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

        // Broadcast to resource-specific channel
        $channels[] = new PrivateChannel('mcp.resource.'.$this->resourceName);

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'resource' => $this->resourceName,
            'action' => $this->action,
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
        return 'resource.accessed';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return config('laravel-mcp.events.broadcast.resource_accessed', false);
    }

    /**
     * Get a summary of the access for logging.
     */
    public function toArray(): array
    {
        return [
            'resource' => $this->resourceName,
            'action' => $this->action,
            'parameters' => $this->parameters,
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
            'has_result' => $this->result !== null,
        ];
    }

    /**
     * Get the tags for logging and monitoring.
     */
    public function tags(): array
    {
        return [
            'mcp',
            'resource',
            'resource:'.$this->resourceName,
            'action:'.$this->action,
            $this->userId ? 'user:'.$this->userId : 'anonymous',
        ];
    }

    /**
     * Check if this is a read action.
     */
    public function isReadAction(): bool
    {
        return in_array($this->action, ['read', 'get', 'fetch', 'retrieve']);
    }

    /**
     * Check if this is a list action.
     */
    public function isListAction(): bool
    {
        return in_array($this->action, ['list', 'index', 'all']);
    }

    /**
     * Check if this is a subscription action.
     */
    public function isSubscriptionAction(): bool
    {
        return in_array($this->action, ['subscribe', 'watch', 'monitor']);
    }
}
