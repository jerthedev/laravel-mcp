<?php

namespace JTD\LaravelMCP\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpComponentRegistered;

/**
 * Listener that logs MCP component registrations.
 */
class LogMcpComponentRegistration implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(McpComponentRegistered $event): void
    {
        Log::info('MCP component registered', [
            'type' => $event->componentType,
            'name' => $event->componentName,
            'class' => is_object($event->component) ? get_class($event->component) : $event->component,
            'metadata' => $event->metadata,
            'registered_at' => $event->registeredAt,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(McpComponentRegistered $event): bool
    {
        // Only queue for non-critical components
        return ! ($event->metadata['critical'] ?? false);
    }
}
