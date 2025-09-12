<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MCP Logging Middleware
 *
 * Provides comprehensive logging for MCP requests and responses:
 * - Request logging with parameters and metadata
 * - Response logging with execution time
 * - Structured logging for analysis
 * - Configurable log levels and channels
 * - Privacy-aware parameter filtering
 */
class McpLoggingMiddleware
{
    /**
     * @var array Sensitive fields to redact from logs
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'api_key',
        'access_token',
        'refresh_token',
        'authorization',
        'cookie',
        'session',
        'credit_card',
        'cvv',
        'ssn',
    ];

    /**
     * @var string Request ID for correlation
     */
    protected string $requestId;

    /**
     * @var float Request start time
     */
    protected float $startTime;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Skip logging if disabled
        if (! $this->isLoggingEnabled()) {
            return $next($request);
        }

        // Initialize request tracking
        $this->initializeRequest($request);

        // Log incoming request
        $this->logRequest($request);

        try {
            // Process the request
            $response = $next($request);

            // Log successful response
            $this->logResponse($request, $response, null);

            return $response;
        } catch (\Throwable $exception) {
            // Log error response
            $this->logResponse($request, null, $exception);

            // Re-throw the exception for upstream handling
            throw $exception;
        }
    }

    /**
     * Check if logging is enabled.
     */
    protected function isLoggingEnabled(): bool
    {
        return config('laravel-mcp.logging.enabled', true);
    }

    /**
     * Initialize request tracking.
     */
    protected function initializeRequest(Request $request): void
    {
        // Generate or extract request ID for correlation
        $this->requestId = $request->header('X-Request-ID', Str::uuid()->toString());
        $request->headers->set('X-Request-ID', $this->requestId);

        // Track request start time
        $this->startTime = microtime(true);

        // Add request ID to log context
        Log::withContext([
            'mcp_request_id' => $this->requestId,
        ]);
    }

    /**
     * Log incoming request.
     */
    protected function logRequest(Request $request): void
    {
        $logData = $this->buildRequestLogData($request);
        $logLevel = $this->getRequestLogLevel();
        $channel = $this->getLogChannel();

        Log::channel($channel)->log($logLevel, 'MCP Request', $logData);
    }

    /**
     * Log response.
     */
    protected function logResponse(Request $request, ?SymfonyResponse $response, ?\Throwable $exception): void
    {
        $executionTime = $this->calculateExecutionTime();
        $logData = $this->buildResponseLogData($request, $response, $exception, $executionTime);

        // Determine log level based on response
        $logLevel = $this->getResponseLogLevel($response, $exception);
        $channel = $this->getLogChannel();

        Log::channel($channel)->log($logLevel, 'MCP Response', $logData);

        // Log performance warning if execution time exceeds threshold
        $this->logPerformanceWarning($executionTime);
    }

    /**
     * Build request log data.
     */
    protected function buildRequestLogData(Request $request): array
    {
        $logData = [
            'request_id' => $this->requestId,
            'timestamp' => now()->toISOString(),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        // Add user context if authenticated
        if ($userId = $request->attributes->get('mcp_user_id')) {
            $logData['user_id'] = $userId;
        }

        // Add MCP-specific data
        if ($request->isJson()) {
            $jsonData = $request->json()->all();

            // Extract JSON-RPC method and params
            if (isset($jsonData['method'])) {
                $logData['mcp_method'] = $jsonData['method'];
            }

            if (isset($jsonData['params'])) {
                $logData['params'] = $this->sanitizeParameters($jsonData['params']);
            }

            if (isset($jsonData['id'])) {
                $logData['jsonrpc_id'] = $jsonData['id'];
            }
        }

        // Add request headers if configured
        if ($this->shouldLogHeaders()) {
            $logData['headers'] = $this->sanitizeHeaders($request->headers->all());
        }

        // Add query parameters
        if ($request->query->count() > 0) {
            $logData['query'] = $this->sanitizeParameters($request->query->all());
        }

        return $logData;
    }

    /**
     * Build response log data.
     */
    protected function buildResponseLogData(
        Request $request,
        ?SymfonyResponse $response,
        ?\Throwable $exception,
        float $executionTime
    ): array {
        $logData = [
            'request_id' => $this->requestId,
            'timestamp' => now()->toISOString(),
            'execution_time_ms' => round($executionTime * 1000, 2),
        ];

        if ($response) {
            $logData['status_code'] = $response->getStatusCode();
            $logData['success'] = $response->isSuccessful();

            // Log response size
            $content = $response->getContent();
            if ($content !== false) {
                $logData['response_size_bytes'] = strlen($content);
            }

            // Add response headers if configured
            if ($this->shouldLogResponseHeaders()) {
                $logData['response_headers'] = $this->sanitizeHeaders($response->headers->all());
            }

            // Add response body preview if configured
            if ($this->shouldLogResponseBody() && $content !== false) {
                $logData['response_preview'] = $this->getResponsePreview($content);
            }
        }

        if ($exception) {
            $logData['error'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ];

            // Add stack trace in debug mode
            if (config('app.debug')) {
                $logData['error']['trace'] = $this->formatStackTrace($exception);
            }
        }

        // Add memory usage
        $logData['memory_usage_mb'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

        return $logData;
    }

    /**
     * Sanitize parameters to remove sensitive data.
     */
    protected function sanitizeParameters(array $parameters): array
    {
        $sanitized = [];

        foreach ($parameters as $key => $value) {
            if ($this->isSensitiveField($key)) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeParameters($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = '[OBJECT]';
            } elseif (is_string($value) && strlen($value) > 1000) {
                $sanitized[$key] = substr($value, 0, 1000).'...[TRUNCATED]';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize headers to remove sensitive data.
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-mcp-api-key'];

        foreach ($headers as $key => $values) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = ['[REDACTED]'];
            } else {
                $sanitized[$key] = $values;
            }
        }

        return $sanitized;
    }

    /**
     * Check if field name is sensitive.
     */
    protected function isSensitiveField(string $fieldName): bool
    {
        $lowerField = strtolower($fieldName);

        foreach ($this->sensitiveFields as $sensitive) {
            if (str_contains($lowerField, $sensitive)) {
                return true;
            }
        }

        // Check custom sensitive fields from config
        $customSensitive = config('laravel-mcp.logging.sensitive_fields', []);
        foreach ($customSensitive as $sensitive) {
            if (str_contains($lowerField, strtolower($sensitive))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get response preview for logging.
     */
    protected function getResponsePreview(string $content): string
    {
        $maxLength = config('laravel-mcp.logging.response_preview_length', 500);

        if (strlen($content) <= $maxLength) {
            return $content;
        }

        return substr($content, 0, $maxLength).'...[TRUNCATED]';
    }

    /**
     * Format exception stack trace.
     */
    protected function formatStackTrace(\Throwable $exception): array
    {
        $trace = [];
        $frames = array_slice($exception->getTrace(), 0, 10); // Limit to 10 frames

        foreach ($frames as $frame) {
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
     * Calculate execution time in seconds.
     */
    protected function calculateExecutionTime(): float
    {
        return microtime(true) - $this->startTime;
    }

    /**
     * Log performance warning if needed.
     */
    protected function logPerformanceWarning(float $executionTime): void
    {
        $threshold = config('laravel-mcp.logging.slow_request_threshold', 5.0); // 5 seconds default

        if ($executionTime > $threshold) {
            Log::channel($this->getLogChannel())->warning('Slow MCP request detected', [
                'request_id' => $this->requestId,
                'execution_time_seconds' => round($executionTime, 2),
                'threshold_seconds' => $threshold,
            ]);
        }
    }

    /**
     * Get log level for requests.
     */
    protected function getRequestLogLevel(): string
    {
        return config('laravel-mcp.logging.request_log_level', 'info');
    }

    /**
     * Get log level for responses.
     */
    protected function getResponseLogLevel(?SymfonyResponse $response, ?\Throwable $exception): string
    {
        if ($exception) {
            return 'error';
        }

        if ($response && $response->getStatusCode() >= 400) {
            return 'warning';
        }

        return config('laravel-mcp.logging.response_log_level', 'info');
    }

    /**
     * Get log channel to use.
     */
    protected function getLogChannel(): string
    {
        return config('laravel-mcp.logging.channel', 'stack');
    }

    /**
     * Check if headers should be logged.
     */
    protected function shouldLogHeaders(): bool
    {
        return config('laravel-mcp.logging.log_headers', false);
    }

    /**
     * Check if response headers should be logged.
     */
    protected function shouldLogResponseHeaders(): bool
    {
        return config('laravel-mcp.logging.log_response_headers', false);
    }

    /**
     * Check if response body should be logged.
     */
    protected function shouldLogResponseBody(): bool
    {
        return config('laravel-mcp.logging.log_response_body', false);
    }
}
