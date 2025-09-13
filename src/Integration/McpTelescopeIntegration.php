<?php

namespace JTD\LaravelMCP\Integration;

use Laravel\Telescope\EntryType;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeServiceProvider;

class McpTelescopeIntegration
{
    public function register(): void
    {
        if (! class_exists(TelescopeServiceProvider::class)) {
            return;
        }

        Telescope::filter(function ($entry) {
            // Add MCP-specific filtering
            return $entry->type !== EntryType::MCP_REQUEST ||
                   config('laravel-mcp.telescope.enabled', true);
        });

        // Register MCP entry type
        Telescope::tag(function ($entry) {
            if ($entry->type === EntryType::MCP_REQUEST) {
                return ['mcp:'.$entry->content['method']];
            }
        });
    }

    public function recordMcpRequest(string $method, array $parameters, mixed $result, float $time): void
    {
        if (! class_exists(Telescope::class)) {
            return;
        }

        Telescope::recordMcpRequest([
            'method' => $method,
            'parameters' => $parameters,
            'result' => $result,
            'duration' => $time,
            'timestamp' => now(),
        ]);
    }
}
