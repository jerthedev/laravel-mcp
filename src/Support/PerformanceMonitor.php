<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Performance monitor for MCP operations.
 *
 * This class provides performance monitoring utilities for MCP operations,
 * including metric collection, timing measurements, resource usage tracking,
 * and aggregated statistics.
 */
class PerformanceMonitor
{
    /**
     * Whether monitoring is enabled.
     */
    protected bool $enabled = true;

    /**
     * Storage driver for metrics.
     */
    protected string $storage = 'cache';

    /**
     * Metrics data.
     */
    protected array $metrics = [];

    /**
     * Active timers.
     */
    protected array $timers = [];

    /**
     * Metric aggregates.
     */
    protected array $aggregates = [];

    /**
     * Cache TTL for metrics (in seconds).
     */
    protected int $ttl = 3600;

    /**
     * Maximum metrics to keep in memory.
     */
    protected int $maxMetrics = 1000;

    /**
     * Metric export handlers.
     */
    protected array $exportHandlers = [];

    /**
     * Create a new performance monitor instance.
     *
     * @param  bool  $enabled  Whether monitoring is enabled
     * @param  string  $storage  Storage driver
     * @param  int  $ttl  Cache TTL in seconds
     */
    public function __construct(bool $enabled = true, string $storage = 'cache', int $ttl = 3600)
    {
        $this->enabled = $enabled && config('laravel-mcp.performance.enabled', true);
        $this->storage = $storage;
        $this->ttl = $ttl;
        $this->maxMetrics = config('laravel-mcp.performance.max_metrics', 1000);

        $this->loadMetrics();
    }

    /**
     * Enable performance monitoring.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable performance monitoring.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if monitoring is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Record a metric value.
     *
     * @param  string  $name  Metric name
     * @param  float  $value  Metric value
     * @param  array  $tags  Optional tags
     * @param  string  $type  Metric type (counter, gauge, histogram, summary)
     */
    public function record(string $name, float $value, array $tags = [], string $type = 'gauge'): void
    {
        if (! $this->enabled) {
            return;
        }

        $metric = [
            'name' => $name,
            'value' => $value,
            'tags' => $tags,
            'type' => $type,
            'timestamp' => microtime(true),
        ];

        $this->metrics[] = $metric;
        $this->updateAggregate($name, $value, $type);

        // Limit metrics in memory
        if (count($this->metrics) > $this->maxMetrics) {
            array_shift($this->metrics);
        }

        // Persist if needed
        if ($this->storage === 'cache') {
            $this->persistMetrics();
        }

        // Call export handlers
        foreach ($this->exportHandlers as $handler) {
            $handler($metric);
        }
    }

    /**
     * Increment a counter metric.
     *
     * @param  string  $name  Counter name
     * @param  float  $value  Increment value
     * @param  array  $tags  Optional tags
     */
    public function increment(string $name, float $value = 1, array $tags = []): void
    {
        $this->record($name, $value, $tags, 'counter');
    }

    /**
     * Decrement a counter metric.
     *
     * @param  string  $name  Counter name
     * @param  float  $value  Decrement value
     * @param  array  $tags  Optional tags
     */
    public function decrement(string $name, float $value = 1, array $tags = []): void
    {
        $this->record($name, -$value, $tags, 'counter');
    }

    /**
     * Set a gauge metric.
     *
     * @param  string  $name  Gauge name
     * @param  float  $value  Gauge value
     * @param  array  $tags  Optional tags
     */
    public function gauge(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, $tags, 'gauge');
    }

    /**
     * Record a histogram value.
     *
     * @param  string  $name  Histogram name
     * @param  float  $value  Value to record
     * @param  array  $tags  Optional tags
     */
    public function histogram(string $name, float $value, array $tags = []): void
    {
        $this->record($name, $value, $tags, 'histogram');
    }

    /**
     * Start a timer for measuring execution time.
     *
     * @param  string  $name  Timer name
     * @param  array  $tags  Optional tags
     */
    public function startTimer(string $name, array $tags = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->timers[$name] = [
            'start' => microtime(true),
            'tags' => $tags,
        ];
    }

    /**
     * Stop a timer and record the elapsed time.
     *
     * @param  string  $name  Timer name
     * @param  string  $metric  Metric name to record (defaults to timer name)
     * @return float|null Elapsed time in milliseconds
     */
    public function stopTimer(string $name, ?string $metric = null): ?float
    {
        if (! isset($this->timers[$name])) {
            return null;
        }

        $timer = $this->timers[$name];
        $elapsed = (microtime(true) - $timer['start']) * 1000;

        unset($this->timers[$name]);

        if ($this->enabled) {
            $metricName = $metric ?? "{$name}.duration";
            $this->histogram($metricName, $elapsed, $timer['tags']);
        }

        return $elapsed;
    }

    /**
     * Measure the execution time of a callback.
     *
     * @param  callable  $callback  The callback to measure
     * @param  string  $metric  Metric name
     * @param  array  $tags  Optional tags
     * @return mixed The callback result
     */
    public function measure(callable $callback, string $metric, array $tags = [])
    {
        $this->startTimer($metric, $tags);

        try {
            $result = $callback();
            $this->stopTimer($metric);

            return $result;
        } catch (\Throwable $e) {
            $this->stopTimer($metric);
            $this->increment("{$metric}.errors", 1, array_merge($tags, [
                'exception' => get_class($e),
            ]));
            throw $e;
        }
    }

    /**
     * Record memory usage.
     *
     * @param  string  $label  Label for the measurement
     * @param  array  $tags  Optional tags
     */
    public function recordMemory(string $label = 'memory', array $tags = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->gauge("{$label}.usage", memory_get_usage(true), $tags);
        $this->gauge("{$label}.peak", memory_get_peak_usage(true), $tags);
    }

    /**
     * Get metrics data.
     *
     * @param  string|null  $name  Filter by metric name
     * @param  int|null  $limit  Maximum entries to return
     * @return array Metrics data
     */
    public function getMetrics(?string $name = null, ?int $limit = null): array
    {
        $metrics = $this->metrics;

        if ($name !== null) {
            $metrics = array_filter($metrics, function ($metric) use ($name) {
                return $metric['name'] === $name;
            });
        }

        if ($limit !== null) {
            $metrics = array_slice($metrics, -$limit);
        }

        return array_values($metrics);
    }

    /**
     * Get aggregated statistics for a metric.
     *
     * @param  string  $name  Metric name
     * @return array|null Aggregated statistics
     */
    public function getAggregate(string $name): ?array
    {
        return $this->aggregates[$name] ?? null;
    }

    /**
     * Get all aggregates.
     *
     * @return array All aggregated statistics
     */
    public function getAggregates(): array
    {
        return $this->aggregates;
    }

    /**
     * Calculate percentile for a metric.
     *
     * @param  string  $name  Metric name
     * @param  float  $percentile  Percentile to calculate (0-100)
     * @return float|null Percentile value
     */
    public function getPercentile(string $name, float $percentile): ?float
    {
        $values = array_column(
            array_filter($this->metrics, function ($m) use ($name) {
                return $m['name'] === $name;
            }),
            'value'
        );

        if (empty($values)) {
            return null;
        }

        sort($values);
        $index = (int) ceil((count($values) * $percentile) / 100) - 1;

        return $values[$index] ?? null;
    }

    /**
     * Get rate of change for a counter metric.
     *
     * @param  string  $name  Counter name
     * @param  int  $seconds  Time window in seconds
     * @return float|null Rate per second
     */
    public function getRate(string $name, int $seconds = 60): ?float
    {
        $now = microtime(true);
        $cutoff = $now - $seconds;

        $metrics = array_filter($this->metrics, function ($m) use ($name, $cutoff) {
            return $m['name'] === $name && $m['timestamp'] >= $cutoff;
        });

        if (empty($metrics)) {
            return null;
        }

        $total = array_sum(array_column($metrics, 'value'));

        return $total / $seconds;
    }

    /**
     * Export metrics in a specific format.
     *
     * @param  string  $format  Export format (json, prometheus, graphite)
     * @return string Exported metrics
     */
    public function export(string $format = 'json'): string
    {
        switch ($format) {
            case 'json':
                return $this->exportJson();
            case 'prometheus':
                return $this->exportPrometheus();
            case 'graphite':
                return $this->exportGraphite();
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Export metrics as JSON.
     *
     * @return string JSON-encoded metrics
     */
    protected function exportJson(): string
    {
        $data = [
            'timestamp' => now()->toISOString(),
            'metrics' => $this->metrics,
            'aggregates' => $this->aggregates,
        ];

        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Export metrics in Prometheus format.
     *
     * @return string Prometheus-formatted metrics
     */
    protected function exportPrometheus(): string
    {
        $output = [];
        $grouped = [];

        // Group metrics by name
        foreach ($this->metrics as $metric) {
            $grouped[$metric['name']][] = $metric;
        }

        foreach ($grouped as $name => $metrics) {
            $latest = end($metrics);
            $type = $latest['type'];

            // Add type comment
            $output[] = "# TYPE {$name} {$type}";

            // Add help comment if available
            $output[] = "# HELP {$name} MCP metric";

            // Add metric value with tags
            $tags = $this->formatPrometheusTags($latest['tags']);
            $output[] = "{$name}{$tags} {$latest['value']}";
        }

        return implode("\n", $output);
    }

    /**
     * Export metrics in Graphite format.
     *
     * @return string Graphite-formatted metrics
     */
    protected function exportGraphite(): string
    {
        $output = [];

        foreach ($this->metrics as $metric) {
            $path = $this->formatGraphitePath($metric['name'], $metric['tags']);
            $timestamp = (int) $metric['timestamp'];
            $output[] = "{$path} {$metric['value']} {$timestamp}";
        }

        return implode("\n", $output);
    }

    /**
     * Format tags for Prometheus export.
     *
     * @param  array  $tags  Tags to format
     * @return string Formatted tags
     */
    protected function formatPrometheusTags(array $tags): string
    {
        if (empty($tags)) {
            return '';
        }

        $formatted = [];
        foreach ($tags as $key => $value) {
            $formatted[] = "{$key}=\"{$value}\"";
        }

        return '{'.implode(',', $formatted).'}';
    }

    /**
     * Format metric path for Graphite export.
     *
     * @param  string  $name  Metric name
     * @param  array  $tags  Tags
     * @return string Formatted path
     */
    protected function formatGraphitePath(string $name, array $tags): string
    {
        $parts = ['mcp', str_replace('.', '_', $name)];

        foreach ($tags as $key => $value) {
            $parts[] = "{$key}_{$value}";
        }

        return implode('.', $parts);
    }

    /**
     * Register an export handler.
     *
     * @param  callable  $handler  Export handler callback
     */
    public function registerExportHandler(callable $handler): void
    {
        $this->exportHandlers[] = $handler;
    }

    /**
     * Clear all metrics.
     */
    public function clear(): void
    {
        $this->metrics = [];
        $this->aggregates = [];
        $this->timers = [];

        if ($this->storage === 'cache') {
            Cache::forget('mcp:performance:metrics');
            Cache::forget('mcp:performance:aggregates');
        }
    }

    /**
     * Update aggregate statistics for a metric.
     *
     * @param  string  $name  Metric name
     * @param  float  $value  Metric value
     * @param  string  $type  Metric type
     */
    protected function updateAggregate(string $name, float $value, string $type): void
    {
        if (! isset($this->aggregates[$name])) {
            $this->aggregates[$name] = [
                'type' => $type,
                'count' => 0,
                'sum' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => PHP_FLOAT_MIN,
                'values' => [],
            ];
        }

        $agg = &$this->aggregates[$name];
        $agg['count']++;
        $agg['sum'] += $value;
        $agg['min'] = min($agg['min'], $value);
        $agg['max'] = max($agg['max'], $value);
        $agg['avg'] = $agg['sum'] / $agg['count'];

        // Keep last 100 values for percentile calculations
        $agg['values'][] = $value;
        if (count($agg['values']) > 100) {
            array_shift($agg['values']);
        }

        // Calculate percentiles
        if (count($agg['values']) >= 10) {
            $sorted = $agg['values'];
            sort($sorted);
            $agg['p50'] = $this->calculatePercentileFromArray($sorted, 50);
            $agg['p95'] = $this->calculatePercentileFromArray($sorted, 95);
            $agg['p99'] = $this->calculatePercentileFromArray($sorted, 99);
        }
    }

    /**
     * Calculate percentile from sorted array.
     *
     * @param  array  $values  Sorted values
     * @param  float  $percentile  Percentile to calculate
     * @return float Percentile value
     */
    protected function calculatePercentileFromArray(array $values, float $percentile): float
    {
        $index = (int) ceil((count($values) * $percentile) / 100) - 1;

        return $values[$index] ?? 0;
    }

    /**
     * Load metrics from storage.
     */
    protected function loadMetrics(): void
    {
        if ($this->storage !== 'cache') {
            return;
        }

        $this->metrics = Cache::get('mcp:performance:metrics', []);
        $this->aggregates = Cache::get('mcp:performance:aggregates', []);
    }

    /**
     * Persist metrics to storage.
     */
    protected function persistMetrics(): void
    {
        if ($this->storage !== 'cache') {
            return;
        }

        Cache::put('mcp:performance:metrics', $this->metrics, $this->ttl);
        Cache::put('mcp:performance:aggregates', $this->aggregates, $this->ttl);
    }

    /**
     * Get a summary report.
     *
     * @return array Summary report
     */
    public function getSummary(): array
    {
        return [
            'enabled' => $this->enabled,
            'total_metrics' => count($this->metrics),
            'unique_metrics' => count($this->aggregates),
            'active_timers' => count($this->timers),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'aggregates' => $this->aggregates,
        ];
    }
}
