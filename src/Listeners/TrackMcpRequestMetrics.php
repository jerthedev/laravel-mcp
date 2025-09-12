<?php

namespace JTD\LaravelMCP\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpRequestProcessed;

/**
 * Listener that tracks MCP request metrics and performance.
 */
class TrackMcpRequestMetrics implements ShouldQueue
{
    /**
     * Handle the event.
     */
    public function handle(McpRequestProcessed $event): void
    {
        // Increment request counter
        Cache::increment('mcp:stats:requests_processed');

        // Track per-method metrics
        $this->trackMethodMetrics($event);

        // Track performance metrics
        $this->trackPerformanceMetrics($event);

        // Track error metrics if applicable
        if (! $event->wasSuccessful()) {
            $this->trackErrorMetrics($event);
        }

        // Log slow requests
        if ($event->exceededExecutionTime(1000)) { // 1 second
            $this->logSlowRequest($event);
        }
    }

    /**
     * Track method-specific metrics.
     */
    protected function trackMethodMetrics(McpRequestProcessed $event): void
    {
        $methodKey = 'mcp:stats:method:'.str_replace('/', ':', $event->method);

        // Increment method call count
        Cache::increment($methodKey.':count');

        // Track daily stats
        $dailyKey = $methodKey.':daily:'.now()->format('Y-m-d');
        Cache::increment($dailyKey, 1);
        Cache::expire($dailyKey, 86400 * 7); // Keep for 7 days

        // Store last execution time
        Cache::put($methodKey.':last_execution', $event->processedAt, 3600);
    }

    /**
     * Track performance metrics.
     */
    protected function trackPerformanceMetrics(McpRequestProcessed $event): void
    {
        // Update average response time
        $this->updateAverageResponseTime($event->executionTime);

        // Track memory usage peaks
        if ($event->memoryUsage > Cache::get('mcp:stats:peak_memory', 0)) {
            Cache::put('mcp:stats:peak_memory', $event->memoryUsage, 86400);
        }

        // Track execution time distribution
        $bucket = $this->getTimeBucket($event->executionTime);
        Cache::increment("mcp:stats:time_distribution:{$bucket}");
    }

    /**
     * Track error metrics.
     */
    protected function trackErrorMetrics(McpRequestProcessed $event): void
    {
        Cache::increment('mcp:stats:errors_count');

        // Track error by method
        $methodKey = 'mcp:stats:errors:'.str_replace('/', ':', $event->method);
        Cache::increment($methodKey);
    }

    /**
     * Log slow request.
     */
    protected function logSlowRequest(McpRequestProcessed $event): void
    {
        Log::warning('Slow MCP request detected', [
            'request_id' => $event->requestId,
            'method' => $event->method,
            'execution_time' => $event->getFormattedExecutionTime(),
            'memory_usage_mb' => round($event->memoryUsage / 1024 / 1024, 2),
            'parameters' => $event->parameters,
        ]);
    }

    /**
     * Update average response time.
     */
    protected function updateAverageResponseTime(float $executionTime): void
    {
        $count = Cache::get('mcp:stats:requests_processed', 1);
        $currentAvg = Cache::get('mcp:stats:avg_response_time', 0);

        // Calculate new average
        $newAvg = (($currentAvg * ($count - 1)) + $executionTime) / $count;

        Cache::put('mcp:stats:avg_response_time', $newAvg, 86400);
    }

    /**
     * Get time bucket for distribution tracking.
     */
    protected function getTimeBucket(float $executionTime): string
    {
        if ($executionTime < 10) {
            return '0-10ms';
        } elseif ($executionTime < 50) {
            return '10-50ms';
        } elseif ($executionTime < 100) {
            return '50-100ms';
        } elseif ($executionTime < 500) {
            return '100-500ms';
        } elseif ($executionTime < 1000) {
            return '500ms-1s';
        } elseif ($executionTime < 5000) {
            return '1s-5s';
        } else {
            return '5s+';
        }
    }
}
