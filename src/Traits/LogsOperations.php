<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Psr\Log\LogLevel;

/**
 * Trait for logging and debugging MCP operations.
 *
 * This trait provides comprehensive logging functionality for MCP components,
 * including request/response logging, performance tracking, error logging,
 * and debug information collection.
 */
trait LogsOperations
{
    /**
     * Log channel for MCP operations.
     */
    protected ?string $logChannel = null;

    /**
     * Performance tracking data.
     */
    protected array $performanceData = [];

    /**
     * Operation start time.
     */
    protected ?float $operationStartTime = null;

    /**
     * Log an MCP operation.
     */
    protected function logOperation(string $operation, array $data = [], string $level = LogLevel::INFO): void
    {
        if (! $this->shouldLog($level)) {
            return;
        }

        $context = $this->buildLogContext($operation, $data);

        $this->getLogger()->log($level, "MCP Operation: {$operation}", $context);
    }

    /**
     * Log the start of an operation.
     */
    protected function logOperationStart(string $operation, array $params = []): void
    {
        $this->operationStartTime = microtime(true);

        $this->logOperation("{$operation}.start", [
            'parameters' => $this->sanitizeForLogging($params),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::DEBUG);
    }

    /**
     * Log the completion of an operation.
     */
    protected function logOperationComplete(string $operation, $result = null): void
    {
        $duration = $this->operationStartTime
            ? (microtime(true) - $this->operationStartTime) * 1000
            : null;

        $this->logOperation("{$operation}.complete", [
            'duration_ms' => $duration,
            'result_type' => gettype($result),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::DEBUG);

        // Track performance if enabled
        if ($duration !== null) {
            $this->trackPerformance($operation, $duration);
        }
    }

    /**
     * Log an operation error.
     */
    protected function logOperationError(string $operation, \Throwable $error, array $context = []): void
    {
        $duration = $this->operationStartTime
            ? (microtime(true) - $this->operationStartTime) * 1000
            : null;

        $this->logOperation("{$operation}.error", array_merge([
            'error_message' => $error->getMessage(),
            'error_code' => $error->getCode(),
            'error_type' => get_class($error),
            'duration_ms' => $duration,
            'component' => $this->getComponentIdentifier(),
            'trace' => $this->shouldLogStackTrace() ? $error->getTraceAsString() : null,
        ], $context), LogLevel::ERROR);
    }

    /**
     * Log a request.
     */
    protected function logRequest(string $method, array $params = []): void
    {
        if (! $this->shouldLogRequests()) {
            return;
        }

        $this->logOperation('request', [
            'method' => $method,
            'parameters' => $this->sanitizeForLogging($params),
            'component' => $this->getComponentIdentifier(),
            'request_id' => $this->generateRequestId(),
        ], LogLevel::INFO);
    }

    /**
     * Log a response.
     */
    protected function logResponse(string $method, $response = null): void
    {
        if (! $this->shouldLogResponses()) {
            return;
        }

        $this->logOperation('response', [
            'method' => $method,
            'response_type' => gettype($response),
            'response_size' => $this->getDataSize($response),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::INFO);
    }

    /**
     * Log validation failure.
     */
    protected function logValidationFailure(array $errors, array $params = []): void
    {
        $this->logOperation('validation.failed', [
            'errors' => $errors,
            'parameters' => $this->sanitizeForLogging($params),
            'component' => $this->getComponentIdentifier(),
        ], LogLevel::WARNING);
    }

    /**
     * Log authorization failure.
     */
    protected function logAuthorizationFailure(string $action, array $context = []): void
    {
        $this->logOperation('authorization.failed', array_merge([
            'action' => $action,
            'component' => $this->getComponentIdentifier(),
            'user' => $this->getCurrentUserId(),
        ], $context), LogLevel::WARNING);
    }

    /**
     * Log performance metrics.
     */
    protected function logPerformanceMetrics(): void
    {
        if (empty($this->performanceData)) {
            return;
        }

        $metrics = $this->calculatePerformanceMetrics();

        $this->logOperation('performance.metrics', $metrics, LogLevel::INFO);
    }

    /**
     * Track performance data.
     */
    protected function trackPerformance(string $operation, float $duration): void
    {
        if (! $this->shouldTrackPerformance()) {
            return;
        }

        if (! isset($this->performanceData[$operation])) {
            $this->performanceData[$operation] = [];
        }

        $this->performanceData[$operation][] = $duration;

        // Keep only recent measurements
        $maxSamples = Config::get('laravel-mcp.logging.performance.max_samples', 100);
        if (count($this->performanceData[$operation]) > $maxSamples) {
            array_shift($this->performanceData[$operation]);
        }
    }

    /**
     * Calculate performance metrics.
     */
    protected function calculatePerformanceMetrics(): array
    {
        $metrics = [];

        foreach ($this->performanceData as $operation => $durations) {
            if (empty($durations)) {
                continue;
            }

            $metrics[$operation] = [
                'count' => count($durations),
                'min_ms' => min($durations),
                'max_ms' => max($durations),
                'avg_ms' => array_sum($durations) / count($durations),
                'median_ms' => $this->calculateMedian($durations),
                'p95_ms' => $this->calculatePercentile($durations, 95),
                'p99_ms' => $this->calculatePercentile($durations, 99),
            ];
        }

        return $metrics;
    }

    /**
     * Log debug information.
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (! $this->isDebugEnabled()) {
            return;
        }

        $this->getLogger()->debug($message, array_merge([
            'component' => $this->getComponentIdentifier(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ], $context));
    }

    /**
     * Log component registration.
     */
    protected function logRegistration(): void
    {
        $this->logOperation('registration', [
            'component' => $this->getComponentIdentifier(),
            'type' => $this->getComponentType(),
            'capabilities' => $this->getCapabilities(),
        ], LogLevel::INFO);
    }

    /**
     * Build log context.
     */
    protected function buildLogContext(string $operation, array $data = []): array
    {
        $context = [
            'operation' => $operation,
            'component_type' => $this->getComponentType(),
            'component_name' => $this->getName(),
            'timestamp' => now()->toIso8601String(),
        ];

        // Add request context if available
        if (app()->has('request')) {
            $request = app('request');
            $context['request_id'] = $request->header('X-Request-ID');
            $context['user_agent'] = $request->userAgent();
            $context['ip'] = $request->ip();
        }

        // Add user context if authenticated
        if ($userId = $this->getCurrentUserId()) {
            $context['user_id'] = $userId;
        }

        return array_merge($context, $data);
    }

    /**
     * Sanitize data for logging.
     */
    protected function sanitizeForLogging($data): array
    {
        if (! is_array($data)) {
            return ['value' => '[non-array data]'];
        }

        $sensitiveFields = Config::get('laravel-mcp.logging.sensitive_fields', [
            'password',
            'token',
            'secret',
            'api_key',
            'private_key',
            'access_token',
            'refresh_token',
            'credit_card',
            'ssn',
        ]);

        $sanitized = $data;

        array_walk_recursive($sanitized, function (&$value, $key) use ($sensitiveFields) {
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $value = '[REDACTED]';
                    break;
                }
            }
        });

        return $sanitized;
    }

    /**
     * Get the logger instance.
     */
    protected function getLogger(): \Psr\Log\LoggerInterface
    {
        $channel = $this->logChannel ?? Config::get('laravel-mcp.logging.channel', 'mcp');

        // Create channel if it doesn't exist
        if (! Config::has("logging.channels.{$channel}")) {
            Config::set("logging.channels.{$channel}", [
                'driver' => 'daily',
                'path' => storage_path("logs/{$channel}.log"),
                'level' => 'debug',
                'days' => 14,
            ]);
        }

        return Log::channel($channel);
    }

    /**
     * Get component identifier for logging.
     */
    protected function getComponentIdentifier(): string
    {
        return sprintf(
            '%s:%s',
            $this->getComponentType(),
            $this->getName()
        );
    }

    /**
     * Get current user ID.
     */
    protected function getCurrentUserId()
    {
        if (function_exists('auth') && auth()->check()) {
            return auth()->id();
        }

        return null;
    }

    /**
     * Generate a unique request ID.
     */
    protected function generateRequestId(): string
    {
        return uniqid('mcp_', true);
    }

    /**
     * Get data size in bytes.
     */
    protected function getDataSize($data): int
    {
        if (is_string($data)) {
            return strlen($data);
        }

        if (is_array($data) || is_object($data)) {
            return strlen(json_encode($data));
        }

        return 0;
    }

    /**
     * Calculate median value.
     */
    protected function calculateMedian(array $values): float
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0;
        }

        $middle = floor(($count - 1) / 2);

        if ($count % 2 === 0) {
            return ($values[$middle] + $values[$middle + 1]) / 2;
        }

        return $values[$middle];
    }

    /**
     * Calculate percentile value.
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $count = count($values);

        if ($count === 0) {
            return 0;
        }

        $index = ($percentile / 100) * ($count - 1);
        $lower = floor($index);
        $upper = ceil($index);

        if ($lower === $upper) {
            return $values[$index];
        }

        $fraction = $index - $lower;

        return $values[$lower] + ($values[$upper] - $values[$lower]) * $fraction;
    }

    /**
     * Check if should log at given level.
     */
    protected function shouldLog(string $level): bool
    {
        if (! Config::get('laravel-mcp.logging.enabled', true)) {
            return false;
        }

        $configuredLevel = Config::get('laravel-mcp.logging.level', LogLevel::INFO);
        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        return ($levels[$level] ?? 6) <= ($levels[$configuredLevel] ?? 6);
    }

    /**
     * Check if should log requests.
     */
    protected function shouldLogRequests(): bool
    {
        return Config::get('laravel-mcp.logging.log_requests', true);
    }

    /**
     * Check if should log responses.
     */
    protected function shouldLogResponses(): bool
    {
        return Config::get('laravel-mcp.logging.log_responses', false);
    }

    /**
     * Check if should log stack traces.
     */
    protected function shouldLogStackTrace(): bool
    {
        return Config::get('laravel-mcp.logging.log_stack_trace', Config::get('app.debug', false));
    }

    /**
     * Check if should track performance.
     */
    protected function shouldTrackPerformance(): bool
    {
        return Config::get('laravel-mcp.logging.track_performance', true);
    }

    /**
     * Check if debug is enabled.
     */
    protected function isDebugEnabled(): bool
    {
        return Config::get('laravel-mcp.debug', Config::get('app.debug', false));
    }

    /**
     * Set custom log channel.
     */
    public function setLogChannel(string $channel): self
    {
        $this->logChannel = $channel;

        return $this;
    }

    /**
     * Clear performance data.
     */
    public function clearPerformanceData(): void
    {
        $this->performanceData = [];
    }
}
