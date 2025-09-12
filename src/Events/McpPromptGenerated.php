<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP prompt is generated.
 *
 * This event is dispatched after a prompt template has been processed and generated,
 * containing information about the prompt, arguments, and generated content.
 */
class McpPromptGenerated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The name of the generated prompt.
     */
    public string $promptName;

    /**
     * The arguments passed to the prompt template.
     */
    public array $arguments;

    /**
     * The generated content from the prompt.
     */
    public string $generatedContent;

    /**
     * The user ID who generated the prompt (if authenticated).
     */
    public ?string $userId;

    /**
     * Additional metadata about the generation.
     */
    public array $metadata;

    /**
     * The timestamp when the prompt was generated.
     */
    public string $timestamp;

    /**
     * The length of the generated content.
     */
    public int $contentLength;

    /**
     * Create a new event instance.
     *
     * @param  string  $promptName  The name of the generated prompt
     * @param  array  $arguments  The arguments passed to the prompt
     * @param  string  $generatedContent  The generated content
     * @param  string|null  $userId  The user ID who generated the prompt
     * @param  array  $metadata  Additional metadata about the generation
     */
    public function __construct(
        string $promptName,
        array $arguments,
        string $generatedContent,
        ?string $userId = null,
        array $metadata = []
    ) {
        $this->promptName = $promptName;
        $this->arguments = $arguments;
        $this->generatedContent = $generatedContent;
        $this->userId = $userId ?? (auth()->check() ? auth()->id() : null);
        $this->metadata = $metadata;
        $this->timestamp = now()->toISOString();
        $this->contentLength = strlen($generatedContent);
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

        // Broadcast to prompt-specific channel
        $channels[] = new PrivateChannel('mcp.prompt.'.$this->promptName);

        return $channels;
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'prompt' => $this->promptName,
            'content_length' => $this->contentLength,
            'timestamp' => $this->timestamp,
            'user_id' => $this->userId,
            'metadata' => $this->metadata,
            'argument_count' => count($this->arguments),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'prompt.generated';
    }

    /**
     * Determine if this event should broadcast.
     */
    public function shouldBroadcast(): bool
    {
        return config('laravel-mcp.events.broadcast.prompt_generated', false);
    }

    /**
     * Get a summary of the generation for logging.
     */
    public function toArray(): array
    {
        return [
            'prompt' => $this->promptName,
            'arguments' => $this->arguments,
            'content_length' => $this->contentLength,
            'user_id' => $this->userId,
            'timestamp' => $this->timestamp,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Get the tags for logging and monitoring.
     */
    public function tags(): array
    {
        return [
            'mcp',
            'prompt',
            'prompt:'.$this->promptName,
            $this->userId ? 'user:'.$this->userId : 'anonymous',
        ];
    }

    /**
     * Get a truncated preview of the generated content.
     *
     * @param  int  $length  Maximum length of the preview
     */
    public function getContentPreview(int $length = 100): string
    {
        if ($this->contentLength <= $length) {
            return $this->generatedContent;
        }

        return substr($this->generatedContent, 0, $length).'...';
    }

    /**
     * Check if the generated content is large.
     *
     * @param  int  $threshold  Size threshold in bytes
     */
    public function isLargeContent(int $threshold = 10000): bool
    {
        return $this->contentLength > $threshold;
    }

    /**
     * Get the number of arguments used.
     */
    public function getArgumentCount(): int
    {
        return count($this->arguments);
    }

    /**
     * Check if specific arguments were provided.
     *
     * @param  array  $keys  Argument keys to check
     */
    public function hasArguments(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $this->arguments)) {
                return false;
            }
        }

        return true;
    }
}
