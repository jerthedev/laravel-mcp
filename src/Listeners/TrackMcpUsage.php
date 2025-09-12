<?php

namespace JTD\LaravelMCP\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpPromptGenerated;
use JTD\LaravelMCP\Events\McpResourceAccessed;
use JTD\LaravelMCP\Events\McpToolExecuted;

/**
 * Tracks MCP usage metrics for analytics and monitoring.
 *
 * This listener collects usage statistics, performance metrics, and user activity
 * data for MCP components. It implements ShouldQueue for async processing.
 */
class TrackMcpUsage implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue = 'mcp-metrics';

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
    public $tries = 5;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Handle the McpToolExecuted event.
     */
    public function handleToolExecuted(McpToolExecuted $event): void
    {
        // Track tool usage count
        $this->incrementUsageCounter('tool', $event->toolName);

        // Record execution time
        $this->recordExecutionTime('tool', $event->toolName, $event->executionTime);

        // Track user activity
        if ($event->userId) {
            $this->trackUserActivity($event->userId, 'tool', $event->toolName);
        }

        // Track hourly usage patterns
        $this->trackHourlyUsage('tool', $event->toolName);

        // Store detailed metrics if enabled
        if (config('laravel-mcp.metrics.detailed_tracking', false)) {
            $this->storeDetailedMetrics('tool', $event);
        }

        // Update performance statistics
        $this->updatePerformanceStats('tool', $event->toolName, $event->executionTime);
    }

    /**
     * Handle the McpResourceAccessed event.
     */
    public function handleResourceAccessed(McpResourceAccessed $event): void
    {
        // Track resource access count by action
        $this->incrementUsageCounter('resource', $event->resourceName, $event->action);

        // Track user activity
        if ($event->userId) {
            $this->trackUserActivity($event->userId, 'resource', $event->resourceName);
        }

        // Track hourly usage patterns
        $this->trackHourlyUsage('resource', $event->resourceName);

        // Track action-specific metrics
        $this->trackResourceAction($event->resourceName, $event->action);

        // Store detailed metrics if enabled
        if (config('laravel-mcp.metrics.detailed_tracking', false)) {
            $this->storeDetailedMetrics('resource', $event);
        }
    }

    /**
     * Handle the McpPromptGenerated event.
     */
    public function handlePromptGenerated(McpPromptGenerated $event): void
    {
        // Track prompt usage count
        $this->incrementUsageCounter('prompt', $event->promptName);

        // Track content size metrics
        $this->trackContentSize('prompt', $event->promptName, $event->contentLength);

        // Track user activity
        if ($event->userId) {
            $this->trackUserActivity($event->userId, 'prompt', $event->promptName);
        }

        // Track hourly usage patterns
        $this->trackHourlyUsage('prompt', $event->promptName);

        // Track argument usage patterns
        $this->trackArgumentPatterns($event->promptName, $event->arguments);

        // Store detailed metrics if enabled
        if (config('laravel-mcp.metrics.detailed_tracking', false)) {
            $this->storeDetailedMetrics('prompt', $event);
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
            default => null,
        };
    }

    /**
     * Increment usage counter for a component.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     * @param  string|null  $subtype  Optional subtype (e.g., action for resources)
     */
    protected function incrementUsageCounter(string $type, string $name, ?string $subtype = null): void
    {
        // Overall counter
        Cache::increment("mcp:usage:{$type}:{$name}:count");

        // Daily counter
        $dateKey = now()->format('Y-m-d');
        Cache::increment("mcp:usage:{$type}:{$name}:daily:{$dateKey}");

        // Monthly counter
        $monthKey = now()->format('Y-m');
        Cache::increment("mcp:usage:{$type}:{$name}:monthly:{$monthKey}");

        // Subtype counter if provided
        if ($subtype) {
            Cache::increment("mcp:usage:{$type}:{$name}:{$subtype}:count");
            Cache::increment("mcp:usage:{$type}:{$name}:{$subtype}:daily:{$dateKey}");
        }

        // Global counters
        Cache::increment("mcp:usage:global:{$type}:count");
        Cache::increment('mcp:usage:global:total:count');
    }

    /**
     * Record execution time for performance tracking.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  float  $time  Execution time in milliseconds
     */
    protected function recordExecutionTime(string $type, string $name, float $time): void
    {
        $key = "mcp:performance:{$type}:{$name}:times";

        // Get existing times
        $times = Cache::get($key, []);
        $times[] = $time;

        // Keep only last 1000 execution times
        if (count($times) > 1000) {
            $times = array_slice($times, -1000);
        }

        // Store with 24-hour TTL
        Cache::put($key, $times, now()->addHours(24));

        // Update aggregated stats
        $this->updateAggregatedStats($type, $name, $times);
    }

    /**
     * Update aggregated performance statistics.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  array  $times  Array of execution times
     */
    protected function updateAggregatedStats(string $type, string $name, array $times): void
    {
        if (empty($times)) {
            return;
        }

        $stats = [
            'min' => min($times),
            'max' => max($times),
            'avg' => array_sum($times) / count($times),
            'median' => $this->calculateMedian($times),
            'p95' => $this->calculatePercentile($times, 95),
            'p99' => $this->calculatePercentile($times, 99),
            'count' => count($times),
            'updated_at' => now()->toISOString(),
        ];

        Cache::put("mcp:performance:{$type}:{$name}:stats", $stats, now()->addHours(24));
    }

    /**
     * Calculate median value from array.
     */
    protected function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $values[$middle];
        }

        return ($values[$middle] + $values[$middle + 1]) / 2;
    }

    /**
     * Calculate percentile value from array.
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ceil(($percentile / 100) * count($values)) - 1;

        return $values[$index] ?? 0;
    }

    /**
     * Track user activity.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     */
    protected function trackUserActivity(string $userId, string $type, string $name): void
    {
        // Increment user's usage counter
        Cache::increment("mcp:user:{$userId}:{$type}_usage");
        Cache::increment("mcp:user:{$userId}:total_usage");

        // Track unique components used
        $key = "mcp:user:{$userId}:{$type}s_used";
        $components = Cache::get($key, []);
        $components[$name] = now()->toISOString();

        // Keep only last 100 unique components
        if (count($components) > 100) {
            arsort($components);
            $components = array_slice($components, 0, 100, true);
        }

        Cache::put($key, $components, now()->addDays(30));

        // Update last activity timestamp
        Cache::put("mcp:user:{$userId}:last_activity", now()->toISOString(), now()->addDays(30));

        // Track daily active users
        $dateKey = now()->format('Y-m-d');
        Cache::sadd("mcp:active_users:{$dateKey}", $userId);
        Cache::expire("mcp:active_users:{$dateKey}", 86400 * 7); // Keep for 7 days
    }

    /**
     * Track hourly usage patterns.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     */
    protected function trackHourlyUsage(string $type, string $name): void
    {
        $hour = now()->format('H');
        $dayOfWeek = now()->dayOfWeek;

        // Track by hour of day
        Cache::increment("mcp:patterns:{$type}:{$name}:hour:{$hour}");

        // Track by day of week
        Cache::increment("mcp:patterns:{$type}:{$name}:dow:{$dayOfWeek}");

        // Track by hour and day combination
        Cache::increment("mcp:patterns:{$type}:{$name}:dow_{$dayOfWeek}_hour_{$hour}");
    }

    /**
     * Track resource action patterns.
     */
    protected function trackResourceAction(string $resourceName, string $action): void
    {
        // Track action distribution
        Cache::increment("mcp:resource:{$resourceName}:actions:{$action}");

        // Track global action patterns
        Cache::increment("mcp:resource:global:actions:{$action}");
    }

    /**
     * Track content size metrics.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  int  $size  Content size in bytes
     */
    protected function trackContentSize(string $type, string $name, int $size): void
    {
        $key = "mcp:content_size:{$type}:{$name}:sizes";

        // Get existing sizes
        $sizes = Cache::get($key, []);
        $sizes[] = $size;

        // Keep only last 500 sizes
        if (count($sizes) > 500) {
            $sizes = array_slice($sizes, -500);
        }

        Cache::put($key, $sizes, now()->addHours(24));

        // Update size statistics
        $stats = [
            'min' => min($sizes),
            'max' => max($sizes),
            'avg' => array_sum($sizes) / count($sizes),
            'total' => array_sum($sizes),
            'count' => count($sizes),
        ];

        Cache::put("mcp:content_size:{$type}:{$name}:stats", $stats, now()->addHours(24));
    }

    /**
     * Track argument usage patterns for prompts.
     */
    protected function trackArgumentPatterns(string $promptName, array $arguments): void
    {
        // Track which arguments are used
        foreach (array_keys($arguments) as $argKey) {
            Cache::increment("mcp:prompt:{$promptName}:args:{$argKey}:usage");
        }

        // Track argument combinations
        $combination = implode(',', array_keys($arguments));
        Cache::increment("mcp:prompt:{$promptName}:combinations:{$combination}");
    }

    /**
     * Store detailed metrics in database if configured.
     *
     * @param  string  $type  Component type
     */
    protected function storeDetailedMetrics(string $type, object $event): void
    {
        if (! config('laravel-mcp.metrics.store_in_database', false)) {
            return;
        }

        try {
            $data = [
                'type' => $type,
                'component_name' => $this->getComponentName($event),
                'user_id' => $event->userId ?? null,
                'metadata' => json_encode($event->metadata ?? []),
                'created_at' => now(),
            ];

            // Add type-specific data
            if ($event instanceof McpToolExecuted) {
                $data['execution_time'] = $event->executionTime;
                $data['action'] = 'execute';
            } elseif ($event instanceof McpResourceAccessed) {
                $data['action'] = $event->action;
            } elseif ($event instanceof McpPromptGenerated) {
                $data['content_size'] = $event->contentLength;
                $data['action'] = 'generate';
            }

            // Store in database (assumes mcp_metrics table exists)
            DB::table('mcp_metrics')->insert($data);
        } catch (\Throwable $e) {
            Log::warning('Failed to store MCP metrics in database', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update performance statistics.
     */
    protected function updatePerformanceStats(string $type, string $name, float $executionTime): void
    {
        // Track slow executions
        if ($executionTime > config('laravel-mcp.metrics.slow_threshold', 3000)) {
            Cache::increment("mcp:performance:{$type}:{$name}:slow_count");

            // Store slow execution details
            $slowKey = "mcp:performance:{$type}:{$name}:slow_executions";
            $slowExecutions = Cache::get($slowKey, []);
            $slowExecutions[] = [
                'time' => $executionTime,
                'timestamp' => now()->toISOString(),
            ];

            // Keep only last 20 slow executions
            if (count($slowExecutions) > 20) {
                $slowExecutions = array_slice($slowExecutions, -20);
            }

            Cache::put($slowKey, $slowExecutions, now()->addHours(24));
        }

        // Track very fast executions
        if ($executionTime < 100) {
            Cache::increment("mcp:performance:{$type}:{$name}:fast_count");
        }
    }

    /**
     * Get component name from event.
     */
    protected function getComponentName(object $event): string
    {
        return match (true) {
            $event instanceof McpToolExecuted => $event->toolName,
            $event instanceof McpResourceAccessed => $event->resourceName,
            $event instanceof McpPromptGenerated => $event->promptName,
            default => 'unknown',
        };
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Failed to track MCP usage', [
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

        // Check if queuing is enabled for metrics
        return config('laravel-mcp.metrics.queue_tracking', true);
    }

    /**
     * Get the name of the listener's queue.
     */
    public function viaQueue(): string
    {
        return config('laravel-mcp.metrics.queue_name', 'mcp-metrics');
    }

    /**
     * Get the name of the listener's connection.
     */
    public function viaConnection(): string
    {
        return config('laravel-mcp.metrics.queue_connection', 'redis');
    }
}
