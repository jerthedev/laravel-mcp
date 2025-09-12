<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Debugger for MCP operations.
 *
 * This class provides debugging utilities for MCP operations, including
 * request/response logging, performance profiling, memory tracking, and
 * integration with Laravel's debugging tools.
 */
class Debugger
{
    /**
     * Whether debug mode is enabled.
     */
    protected bool $enabled = false;

    /**
     * Debug log channel.
     */
    protected string $channel = 'mcp-debug';

    /**
     * Maximum log entry size in bytes.
     */
    protected int $maxLogSize = 10240; // 10KB

    /**
     * Debug data storage.
     */
    protected array $debugData = [];

    /**
     * Performance timers.
     */
    protected array $timers = [];

    /**
     * Memory checkpoints.
     */
    protected array $memoryCheckpoints = [];

    /**
     * Request/response history.
     */
    protected array $history = [];

    /**
     * Maximum history entries.
     */
    protected int $maxHistorySize = 100;

    /**
     * Exception handler instance.
     */
    protected ?ExceptionHandler $exceptionHandler = null;

    /**
     * Create a new debugger instance.
     *
     * @param  bool  $enabled  Whether debugging is enabled
     * @param  string  $channel  Log channel to use
     */
    public function __construct(bool $enabled = false, string $channel = 'mcp-debug')
    {
        $this->enabled = $enabled || config('laravel-mcp.debug.enabled', false);
        $this->channel = $channel;
        $this->maxLogSize = config('laravel-mcp.debug.max_log_size', 10240);
        $this->maxHistorySize = config('laravel-mcp.debug.max_history_size', 100);

        if (app()->bound(ExceptionHandler::class)) {
            $this->exceptionHandler = app(ExceptionHandler::class);
        }
    }

    /**
     * Enable debug mode.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable debug mode.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Log a debug message.
     *
     * @param  string  $message  The message to log
     * @param  array  $context  Additional context
     */
    public function log(string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        // Add debug metadata
        $context['timestamp'] = now()->toISOString();
        $context['memory'] = memory_get_usage(true);
        $context['peak_memory'] = memory_get_peak_usage(true);

        // Truncate large data
        $context = $this->truncateLargeData($context);

        Log::channel($this->channel)->debug($message, $context);

        // Store in debug data
        $this->debugData[] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
        ];

        // Limit debug data size
        if (count($this->debugData) > 1000) {
            array_shift($this->debugData);
        }
    }

    /**
     * Log an MCP request.
     *
     * @param  string  $method  The MCP method
     * @param  array  $parameters  Request parameters
     * @param  string|int|null  $id  Request ID
     */
    public function logRequest(string $method, array $parameters = [], $id = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $request = [
            'type' => 'request',
            'method' => $method,
            'parameters' => $this->truncateLargeData($parameters),
            'id' => $id,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
        ];

        $this->log("MCP Request: {$method}", $request);

        // Add to history
        $this->addToHistory($request);

        // Start timer for this request
        if ($id !== null) {
            $this->startTimer("request.{$id}");
        }
    }

    /**
     * Log an MCP response.
     *
     * @param  mixed  $result  The response result
     * @param  string|int|null  $id  Request ID
     * @param  float|null  $executionTime  Execution time in milliseconds
     */
    public function logResponse($result, $id = null, ?float $executionTime = null): void
    {
        if (! $this->enabled) {
            return;
        }

        // Stop timer if exists
        if ($id !== null && isset($this->timers["request.{$id}"])) {
            $executionTime = $executionTime ?? $this->stopTimer("request.{$id}");
        }

        $response = [
            'type' => 'response',
            'result' => $this->truncateLargeData($result),
            'id' => $id,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
        ];

        $this->log('MCP Response', $response);

        // Add to history
        $this->addToHistory($response);
    }

    /**
     * Log an MCP error.
     *
     * @param  int  $code  Error code
     * @param  string  $message  Error message
     * @param  mixed  $data  Additional error data
     * @param  string|int|null  $id  Request ID
     * @param  \Throwable|null  $exception  The exception if available
     */
    public function logError(int $code, string $message, $data = null, $id = null, ?\Throwable $exception = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $error = [
            'type' => 'error',
            'code' => $code,
            'message' => $message,
            'data' => $this->truncateLargeData($data),
            'id' => $id,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
        ];

        if ($exception) {
            $error['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatStackTrace($exception),
            ];
        }

        Log::channel($this->channel)->error("MCP Error: {$message}", $error);

        // Add to history
        $this->addToHistory($error);

        // Report to exception handler if available
        if ($exception && $this->exceptionHandler) {
            $this->exceptionHandler->report($exception);
        }
    }

    /**
     * Start a performance timer.
     *
     * @param  string  $name  Timer name
     */
    public function startTimer(string $name): void
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * Stop a performance timer and get elapsed time.
     *
     * @param  string  $name  Timer name
     * @return float|null Elapsed time in milliseconds
     */
    public function stopTimer(string $name): ?float
    {
        if (! isset($this->timers[$name])) {
            return null;
        }

        $elapsed = (microtime(true) - $this->timers[$name]) * 1000;
        unset($this->timers[$name]);

        return $elapsed;
    }

    /**
     * Get elapsed time for a running timer.
     *
     * @param  string  $name  Timer name
     * @return float|null Elapsed time in milliseconds
     */
    public function getElapsedTime(string $name): ?float
    {
        if (! isset($this->timers[$name])) {
            return null;
        }

        return (microtime(true) - $this->timers[$name]) * 1000;
    }

    /**
     * Create a memory checkpoint.
     *
     * @param  string  $name  Checkpoint name
     */
    public function memoryCheckpoint(string $name): void
    {
        $this->memoryCheckpoints[$name] = [
            'usage' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'timestamp' => microtime(true),
        ];

        $this->log("Memory checkpoint: {$name}", $this->memoryCheckpoints[$name]);
    }

    /**
     * Get memory usage since checkpoint.
     *
     * @param  string  $name  Checkpoint name
     * @return array|null Memory usage data
     */
    public function getMemoryDelta(string $name): ?array
    {
        if (! isset($this->memoryCheckpoints[$name])) {
            return null;
        }

        $checkpoint = $this->memoryCheckpoints[$name];
        $current = memory_get_usage(true);
        $peak = memory_get_peak_usage(true);

        return [
            'delta' => $current - $checkpoint['usage'],
            'current' => $current,
            'peak' => $peak,
            'checkpoint' => $checkpoint['usage'],
            'elapsed_time' => (microtime(true) - $checkpoint['timestamp']) * 1000,
        ];
    }

    /**
     * Profile a callback execution.
     *
     * @param  callable  $callback  The callback to profile
     * @param  string  $label  Profile label
     * @return mixed The callback result
     */
    public function profile(callable $callback, string $label = 'profile')
    {
        if (! $this->enabled) {
            return $callback();
        }

        $this->startTimer($label);
        $this->memoryCheckpoint($label);

        try {
            $result = $callback();

            $executionTime = $this->stopTimer($label);
            $memoryDelta = $this->getMemoryDelta($label);

            $this->log("Profile: {$label}", [
                'execution_time' => $executionTime,
                'memory_delta' => $memoryDelta,
            ]);

            return $result;
        } catch (\Throwable $e) {
            $executionTime = $this->stopTimer($label);
            $memoryDelta = $this->getMemoryDelta($label);

            $this->log("Profile failed: {$label}", [
                'execution_time' => $executionTime,
                'memory_delta' => $memoryDelta,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get debug data.
     *
     * @param  int|null  $limit  Maximum entries to return
     * @return array Debug data
     */
    public function getDebugData(?int $limit = null): array
    {
        if ($limit === null) {
            return $this->debugData;
        }

        return array_slice($this->debugData, -$limit);
    }

    /**
     * Get request/response history.
     *
     * @param  int|null  $limit  Maximum entries to return
     * @return array History entries
     */
    public function getHistory(?int $limit = null): array
    {
        if ($limit === null) {
            return $this->history;
        }

        return array_slice($this->history, -$limit);
    }

    /**
     * Clear debug data.
     */
    public function clear(): void
    {
        $this->debugData = [];
        $this->history = [];
        $this->timers = [];
        $this->memoryCheckpoints = [];
    }

    /**
     * Get current memory usage.
     *
     * @return array Memory usage information
     */
    public function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
            'percentage' => $this->calculateMemoryPercentage(),
        ];
    }

    /**
     * Get system information.
     *
     * @return array System information
     */
    public function getSystemInfo(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'mcp_version' => config('laravel-mcp.version', 'unknown'),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'queue_driver' => config('queue.default'),
            'loaded_extensions' => get_loaded_extensions(),
        ];
    }

    /**
     * Dump debug information to file.
     *
     * @param  string  $filename  Output filename
     * @return bool True if successful
     */
    public function dumpToFile(string $filename): bool
    {
        $data = [
            'timestamp' => now()->toISOString(),
            'system' => $this->getSystemInfo(),
            'memory' => $this->getMemoryUsage(),
            'debug_data' => $this->debugData,
            'history' => $this->history,
            'timers' => $this->timers,
            'checkpoints' => $this->memoryCheckpoints,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return file_put_contents($filename, $json) !== false;
    }

    /**
     * Add entry to history.
     *
     * @param  array  $entry  History entry
     */
    protected function addToHistory(array $entry): void
    {
        $this->history[] = $entry;

        // Limit history size
        if (count($this->history) > $this->maxHistorySize) {
            array_shift($this->history);
        }
    }

    /**
     * Truncate large data for logging.
     *
     * @param  mixed  $data  Data to truncate
     * @return mixed Truncated data
     */
    protected function truncateLargeData($data)
    {
        $json = json_encode($data);

        if ($json === false || strlen($json) <= $this->maxLogSize) {
            return $data;
        }

        // For arrays/objects, try to truncate intelligently
        if (is_array($data) || is_object($data)) {
            return $this->truncateStructure($data);
        }

        // For strings, truncate directly
        if (is_string($data)) {
            return Str::limit($data, $this->maxLogSize, '...[truncated]');
        }

        return '[Data too large to log]';
    }

    /**
     * Truncate a data structure.
     *
     * @param  mixed  $data  Data structure to truncate
     * @param  int  $depth  Current depth
     * @return mixed Truncated structure
     */
    protected function truncateStructure($data, int $depth = 0)
    {
        if ($depth > 3) {
            return '[Max depth exceeded]';
        }

        if (is_array($data)) {
            $truncated = [];
            $count = 0;

            foreach ($data as $key => $value) {
                if ($count++ > 10) {
                    $truncated['...'] = '[More items truncated]';
                    break;
                }

                if (is_string($value) && strlen($value) > 1000) {
                    $truncated[$key] = Str::limit($value, 1000, '...[truncated]');
                } elseif (is_array($value) || is_object($value)) {
                    $truncated[$key] = $this->truncateStructure($value, $depth + 1);
                } else {
                    $truncated[$key] = $value;
                }
            }

            return $truncated;
        }

        if (is_object($data)) {
            if (method_exists($data, 'toArray')) {
                return $this->truncateStructure($data->toArray(), $depth);
            }

            return '[Object: '.get_class($data).']';
        }

        return $data;
    }

    /**
     * Format stack trace for logging.
     *
     * @param  \Throwable  $exception  The exception
     * @return array Formatted stack trace
     */
    protected function formatStackTrace(\Throwable $exception): array
    {
        $trace = [];
        $frames = $exception->getTrace();

        foreach (array_slice($frames, 0, 10) as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? 'unknown',
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? 'unknown',
                'class' => $frame['class'] ?? null,
            ];
        }

        return $trace;
    }

    /**
     * Calculate memory usage percentage.
     *
     * @return float Memory usage percentage
     */
    protected function calculateMemoryPercentage(): float
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return 0;
        }

        $limitBytes = $this->convertToBytes($limit);
        $usage = memory_get_usage(true);

        return round(($usage / $limitBytes) * 100, 2);
    }

    /**
     * Convert memory limit string to bytes.
     *
     * @param  string  $value  Memory limit value
     * @return int Bytes
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
