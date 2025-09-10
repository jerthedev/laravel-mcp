<?php

namespace JTD\LaravelMCP\Server\Handlers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Traits\ValidatesParameters;

/**
 * Base handler for MCP message handlers.
 *
 * This abstract class provides common functionality for all MCP message handlers,
 * including request validation, error handling, response formatting, and logging.
 * It implements the JSON-RPC 2.0 protocol requirements and MCP 1.0 compliance.
 */
abstract class BaseHandler
{
    use ValidatesParameters;

    /**
     * Handler name for logging and identification.
     */
    protected string $handlerName;

    /**
     * Debug mode flag.
     */
    protected bool $debug = false;

    /**
     * Create a new base handler instance.
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
        $this->handlerName = static::class;

        if ($this->debug) {
            try {
                Log::debug("Initializing {$this->handlerName}");
            } catch (\Throwable $e) {
                // Gracefully handle missing Log facade in tests
                error_log("Initializing {$this->handlerName}");
            }
        }
    }

    /**
     * Handle a MCP request message.
     *
     * @param  string  $method  The MCP method being called
     * @param  array  $params  Request parameters
     * @param  array  $context  Additional context (request ID, etc.)
     * @return array Response data
     *
     * @throws ProtocolException If the request is invalid or processing fails
     */
    abstract public function handle(string $method, array $params, array $context = []): array;

    /**
     * Get supported methods for this handler.
     *
     * @return array Array of supported method names
     */
    abstract public function getSupportedMethods(): array;

    /**
     * Validate request parameters using Laravel validation.
     *
     * @param  array  $params  Parameters to validate
     * @param  array  $rules  Laravel validation rules
     * @param  array  $messages  Custom error messages
     *
     * @throws ProtocolException If validation fails
     */
    protected function validateRequest(array $params, array $rules, array $messages = []): void
    {
        if (empty($rules)) {
            return;
        }

        try {
            $validator = Validator::make($params, $rules, $messages);
        } catch (\Throwable $e) {
            // Fallback for tests without Laravel app context
            throw new ProtocolException('Validation service not available', -32603);
        }

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
            $errorMessage = 'Invalid parameters: '.implode(', ', $errors);

            $this->logError('Request validation failed', [
                'errors' => $errors,
                'params' => $params,
                'rules' => $rules,
            ]);

            throw new ProtocolException($errorMessage, -32602);
        }
    }

    /**
     * Validate required parameters exist.
     *
     * @param  array  $params  Parameters to check
     * @param  array  $required  Required parameter names
     *
     * @throws ProtocolException If required parameters are missing
     */
    protected function validateRequiredParams(array $params, array $required): void
    {
        $missing = array_diff($required, array_keys($params));

        if (! empty($missing)) {
            $missingList = implode(', ', $missing);
            $this->logError('Missing required parameters', [
                'missing' => $missing,
                'provided' => array_keys($params),
            ]);

            throw new ProtocolException("Missing required parameters: {$missingList}", -32602);
        }
    }

    /**
     * Create MCP-compliant success response.
     *
     * @param  array  $result  Response data
     * @param  array  $context  Additional context
     * @return array Formatted response
     */
    protected function createSuccessResponse(array $result, array $context = []): array
    {
        $response = $result;

        // Add standard MCP response metadata if needed
        if (! empty($context['add_metadata'])) {
            $response['_meta'] = [
                'handler' => $this->handlerName,
                'timestamp' => now()->toISOString(),
                'request_id' => $context['request_id'] ?? null,
            ];
        }

        $this->logInfo('Success response created', [
            'response_keys' => array_keys($response),
            'request_id' => $context['request_id'] ?? null,
        ]);

        return $response;
    }

    /**
     * Create MCP-compliant error response.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code (JSON-RPC 2.0 compatible)
     * @param  mixed  $data  Additional error data
     * @return array Error response
     */
    protected function createErrorResponse(string $message, int $code = -32603, $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        $this->logError('Error response created', [
            'error' => $error,
        ]);

        return ['error' => $error];
    }

    /**
     * Check if method is supported by this handler.
     *
     * @param  string  $method  Method name to check
     * @return bool True if method is supported
     */
    public function supportsMethod(string $method): bool
    {
        return in_array($method, $this->getSupportedMethods());
    }

    /**
     * Log informational message with handler context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logInfo(string $message, array $context = []): void
    {
        try {
            Log::info("[{$this->handlerName}] {$message}", $context);
        } catch (\Throwable $e) {
            // Gracefully handle missing Log facade in tests
            if ($this->debug) {
                error_log("[{$this->handlerName}] INFO: {$message}");
            }
        }
    }

    /**
     * Log debug message with handler context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if ($this->debug) {
            try {
                Log::debug("[{$this->handlerName}] {$message}", $context);
            } catch (\Throwable $e) {
                // Gracefully handle missing Log facade in tests
                error_log("[{$this->handlerName}] DEBUG: {$message}");
            }
        }
    }

    /**
     * Log warning message with handler context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logWarning(string $message, array $context = []): void
    {
        try {
            Log::warning("[{$this->handlerName}] {$message}", $context);
        } catch (\Throwable $e) {
            // Gracefully handle missing Log facade in tests
            if ($this->debug) {
                error_log("[{$this->handlerName}] WARNING: {$message}");
            }
        }
    }

    /**
     * Log error message with handler context.
     *
     * @param  string  $message  Log message
     * @param  array  $context  Additional context
     */
    protected function logError(string $message, array $context = []): void
    {
        try {
            Log::error("[{$this->handlerName}] {$message}", $context);
        } catch (\Throwable $e) {
            // Gracefully handle missing Log facade in tests
            if ($this->debug) {
                error_log("[{$this->handlerName}] ERROR: {$message}");
            }
        }
    }

    /**
     * Sanitize parameters for logging (remove sensitive data).
     *
     * @param  array  $params  Parameters to sanitize
     * @return array Sanitized parameters
     */
    protected function sanitizeForLogging(array $params): array
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential'];
        $sanitized = $params;

        foreach ($sensitiveKeys as $sensitiveKey) {
            if (isset($sanitized[$sensitiveKey])) {
                $sanitized[$sensitiveKey] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Handle exceptions and convert to appropriate protocol errors.
     *
     * @param  \Throwable  $e  Exception to handle
     * @param  string  $method  Method name where error occurred
     * @param  array  $context  Additional context
     * @return array Error response
     */
    protected function handleException(\Throwable $e, string $method, array $context = []): array
    {
        // If it's already a ProtocolException, preserve the error code
        if ($e instanceof ProtocolException) {
            $this->logError("Protocol error in {$method}", [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $context,
            ]);

            return $this->createErrorResponse($e->getMessage(), $e->getCode(), $e->getData());
        }

        // Convert other exceptions to internal error
        $this->logError("Unexpected error in {$method}", [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $this->debug ? $e->getTraceAsString() : '[TRACE HIDDEN]',
            'context' => $context,
        ]);

        $errorData = null;
        if ($this->debug) {
            $errorData = [
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return $this->createErrorResponse(
            'Internal server error',
            -32603,
            $errorData
        );
    }

    /**
     * Format content for MCP response.
     *
     * @param  mixed  $content  Content to format
     * @param  string  $type  Content type (text, json, etc.)
     * @return array Formatted content array
     */
    protected function formatContent($content, string $type = 'text'): array
    {
        switch ($type) {
            case 'text':
                return [
                    'type' => 'text',
                    'text' => is_string($content) ? $content : json_encode($content),
                ];

            case 'json':
                return [
                    'type' => 'text',
                    'text' => json_encode($content, JSON_PRETTY_PRINT),
                ];

            case 'resource':
                return [
                    'type' => 'resource',
                    'resource' => $content,
                ];

            default:
                return [
                    'type' => 'text',
                    'text' => (string) $content,
                ];
        }
    }

    /**
     * Set debug mode.
     *
     * @param  bool  $debug  Debug mode flag
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get debug mode status.
     *
     * @return bool Debug mode flag
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Get handler name.
     *
     * @return string Handler name
     */
    public function getHandlerName(): string
    {
        return $this->handlerName;
    }
}
