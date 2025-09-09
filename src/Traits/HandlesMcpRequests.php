<?php

namespace JTD\LaravelMCP\Traits;

use JTD\LaravelMCP\Exceptions\McpException;

/**
 * Trait for handling MCP request processing.
 *
 * This trait provides common functionality for processing MCP requests,
 * including error handling, response formatting, middleware application,
 * and Laravel integration.
 */
trait HandlesMcpRequests
{
    /**
     * Handle error and format for MCP response.
     */
    protected function handleError(\Throwable $e): void
    {
        logger()->error('MCP Component Error', [
            'component' => static::class,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        throw new McpException($e->getMessage(), $e->getCode() ?: -32603, $e);
    }

    /**
     * Log MCP request if logging is enabled.
     */
    protected function logRequest(string $action, array $params): void
    {
        if (config('laravel-mcp.logging.requests', false)) {
            logger()->info('MCP Request', [
                'component' => static::class,
                'action' => $action,
                'parameters' => $params,
            ]);
        }
    }

    /**
     * Apply middleware to request parameters.
     */
    protected function applyMiddleware(string $middleware, array $params): array
    {
        $middlewareClass = $this->resolveMiddleware($middleware);

        if (! $middlewareClass) {
            return $params;
        }

        return $middlewareClass->handle($params, function ($params) {
            return $params;
        });
    }

    /**
     * Resolve middleware from class name or alias.
     */
    private function resolveMiddleware(string $middleware): ?object
    {
        if (class_exists($middleware)) {
            return $this->make($middleware);
        }

        // Check middleware aliases
        $aliases = config('laravel-mcp.middleware.aliases', []);
        if (isset($aliases[$middleware])) {
            return $this->make($aliases[$middleware]);
        }

        return null;
    }

    /**
     * Create a standardized success response.
     */
    protected function createSuccessResponse($result): array
    {
        return [
            'result' => $result,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Create a standardized error response.
     */
    protected function createErrorResponse(int $code, string $message, $data = null): array
    {
        $error = [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $error['error']['data'] = $data;
        }

        return $error;
    }

    /**
     * Process an MCP request with comprehensive error handling.
     */
    protected function processRequest(callable $handler, array $params = []): array
    {
        try {
            $result = call_user_func($handler, $params);

            return $this->createSuccessResponse($result);
        } catch (McpException $e) {
            return $this->createErrorResponse($e->getCode(), $e->getMessage(), $e->getData());
        } catch (\Throwable $e) {
            // Log the error but don't re-throw
            logger()->error('MCP Component Error', [
                'component' => static::class,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->createErrorResponse(-32603, 'Internal error', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate that required parameters are present.
     */
    protected function validateRequiredParams(array $params, array $required): void
    {
        $missing = [];

        foreach ($required as $param) {
            if (! isset($params[$param])) {
                $missing[] = $param;
            }
        }

        if (! empty($missing)) {
            throw new McpException(
                'Missing required parameters: '.implode(', ', $missing),
                -32602
            );
        }
    }

    /**
     * Extract and validate specific parameters from request.
     */
    protected function extractParams(array $params, array $schema): array
    {
        $extracted = [];

        foreach ($schema as $name => $rules) {
            if (isset($params[$name])) {
                $extracted[$name] = $this->validateParam($params[$name], $rules);
            } elseif (isset($rules['required']) && $rules['required']) {
                throw new McpException("Missing required parameter: {$name}", -32602);
            } elseif (isset($rules['default'])) {
                $extracted[$name] = $rules['default'];
            }
        }

        return $extracted;
    }

    /**
     * Validate a single parameter value.
     */
    protected function validateParam($value, array $rules)
    {
        if (isset($rules['type'])) {
            $this->validateParamType($value, $rules['type']);
        }

        if (isset($rules['validator']) && is_callable($rules['validator'])) {
            $value = call_user_func($rules['validator'], $value);
        }

        return $value;
    }

    /**
     * Validate parameter type.
     */
    protected function validateParamType($value, string $expectedType): void
    {
        $actualType = gettype($value);

        if ($expectedType === 'int' && ! is_int($value)) {
            throw new McpException("Expected integer, got {$actualType}", -32602);
        }

        if ($expectedType === 'string' && ! is_string($value)) {
            throw new McpException("Expected string, got {$actualType}", -32602);
        }

        if ($expectedType === 'array' && ! is_array($value)) {
            throw new McpException("Expected array, got {$actualType}", -32602);
        }

        if ($expectedType === 'object' && ! is_object($value) && ! is_array($value)) {
            throw new McpException("Expected object, got {$actualType}", -32602);
        }
    }

    /**
     * Get the component name for logging/debugging.
     */
    protected function getComponentName(): string
    {
        return class_basename(static::class);
    }

    /**
     * Log an MCP response for debugging.
     */
    protected function logResponse(string $method, array $response): void
    {
        if (config('laravel-mcp.debug', false)) {
            logger('MCP Response', [
                'component' => $this->getComponentName(),
                'method' => $method,
                'response' => $response,
            ]);
        }
    }

    /**
     * Fire Laravel event for MCP operations.
     */
    protected function fireEvent(string $eventName, array $payload = []): void
    {
        if (function_exists('event')) {
            event($eventName, array_merge(['component' => $this], $payload));
        }
    }

    /**
     * Get current authenticated user if available.
     */
    protected function getAuthUser()
    {
        if (function_exists('auth')) {
            return auth()->user();
        }

        return null;
    }

    /**
     * Check if user is authenticated.
     */
    protected function isAuthenticated(): bool
    {
        if (function_exists('auth')) {
            return auth()->check();
        }

        return false;
    }
}
