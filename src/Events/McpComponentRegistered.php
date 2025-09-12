<?php

namespace JTD\LaravelMCP\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an MCP component is registered.
 *
 * This event is dispatched whenever a new tool, resource, or prompt
 * is registered with the MCP registry. It can be used for logging,
 * tracking, or triggering additional actions upon component registration.
 */
class McpComponentRegistered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The type of component registered (tool, resource, or prompt).
     */
    public string $componentType;

    /**
     * The name of the registered component.
     */
    public string $componentName;

    /**
     * The component instance or class name.
     */
    public mixed $component;

    /**
     * Additional metadata about the component.
     */
    public array $metadata;

    /**
     * The timestamp when the component was registered.
     */
    public string $registeredAt;

    /**
     * The user ID who registered the component (if applicable).
     */
    public ?string $userId;

    /**
     * Create a new event instance.
     *
     * @param  string  $componentType  The type of component (tool, resource, prompt)
     * @param  string  $componentName  The name of the component
     * @param  mixed  $component  The component instance or class name
     * @param  array  $metadata  Additional metadata
     * @param  string|null  $userId  The user ID who registered the component
     */
    public function __construct(
        string $componentType,
        string $componentName,
        mixed $component,
        array $metadata = [],
        ?string $userId = null
    ) {
        $this->componentType = $componentType;
        $this->componentName = $componentName;
        $this->component = $component;
        $this->metadata = $metadata;
        $this->registeredAt = $this->getCurrentTimestamp();
        $this->userId = $userId ?? $this->getCurrentUserId();
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
     * Get the component type as a readable string.
     */
    public function getComponentTypeLabel(): string
    {
        return match ($this->componentType) {
            'tool' => 'Tool',
            'resource' => 'Resource',
            'prompt' => 'Prompt',
            default => ucfirst($this->componentType),
        };
    }

    /**
     * Get component details for logging or tracking.
     */
    public function getComponentDetails(): array
    {
        return [
            'type' => $this->componentType,
            'name' => $this->componentName,
            'class' => is_object($this->component) ? get_class($this->component) : $this->component,
            'metadata' => $this->metadata,
            'registered_at' => $this->registeredAt,
            'user_id' => $this->userId,
        ];
    }

    /**
     * Check if the component has specific metadata.
     *
     * @param  string  $key  The metadata key to check
     */
    public function hasMetadata(string $key): bool
    {
        return isset($this->metadata[$key]);
    }

    /**
     * Get specific metadata value.
     *
     * @param  string  $key  The metadata key
     * @param  mixed  $default  Default value if key doesn't exist
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }
}
