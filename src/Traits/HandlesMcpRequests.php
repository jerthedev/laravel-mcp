<?php

namespace JTD\LaravelMCP\Traits;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Exceptions\ProtocolException;

/**
 * Trait for handling MCP request processing.
 *
 * This trait provides common functionality for processing MCP requests,
 * including request validation, response formatting, error handling,
 * and lifecycle management for MCP components.
 */
trait HandlesMcpRequests
{
    /**
     * Process an MCP request with error handling.
     *
     * @param  callable  $handler  The request handler function
     * @param  array  $params  Request parameters
     * @return array The response array
     */
    protected function processRequest(callable $handler, array $params = []): array
    {
        try {
            $result = call_user_func($handler, $params);

            return $this->createSuccessResponse($result);
        } catch (McpException $e) {
            return $this->createErrorResponse($e->getCode(), $e->getMessage(), $e->getData());
        } catch (\Throwable $e) {
            return $this->createErrorResponse(-32603, 'Internal error', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a success response array.
     *
     * @param  mixed  $result  The result data
     */
    protected function createSuccessResponse($result): array
    {
        return [
            'result' => $result,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Create an error response array.
     *
     * @param  int  $code  Error code
     * @param  string  $message  Error message
     * @param  mixed  $data  Additional error data
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
     * Validate required parameters are present.
     *
     * @param  array  $params  Parameters to validate
     * @param  array  $required  Required parameter names
     *
     * @throws ProtocolException If required parameters are missing
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
            throw new ProtocolException(
                'Missing required parameters: '.implode(', ', $missing),
                -32602
            );
        }
    }

    /**
     * Extract and validate specific parameters from request.
     *
     * @param  array  $params  Source parameters
     * @param  array  $schema  Parameter schema (name => validation rules)
     * @return array Validated parameters
     */
    protected function extractParams(array $params, array $schema): array
    {
        $extracted = [];

        foreach ($schema as $name => $rules) {
            if (isset($params[$name])) {
                $extracted[$name] = $this->validateParam($params[$name], $rules);
            } elseif (isset($rules['required']) && $rules['required']) {
                throw new ProtocolException("Missing required parameter: {$name}", -32602);
            } elseif (isset($rules['default'])) {
                $extracted[$name] = $rules['default'];
            }
        }

        return $extracted;
    }

    /**
     * Validate a single parameter value.
     *
     * @param  mixed  $value  Parameter value
     * @param  array  $rules  Validation rules
     * @return mixed Validated value
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
     *
     * @param  mixed  $value  Parameter value
     * @param  string  $expectedType  Expected type
     *
     * @throws ProtocolException If type validation fails
     */
    protected function validateParamType($value, string $expectedType): void
    {
        $actualType = gettype($value);

        if ($expectedType === 'int' && ! is_int($value)) {
            throw new ProtocolException("Expected integer, got {$actualType}", -32602);
        }

        if ($expectedType === 'string' && ! is_string($value)) {
            throw new ProtocolException("Expected string, got {$actualType}", -32602);
        }

        if ($expectedType === 'array' && ! is_array($value)) {
            throw new ProtocolException("Expected array, got {$actualType}", -32602);
        }

        if ($expectedType === 'object' && ! is_object($value) && ! is_array($value)) {
            throw new ProtocolException("Expected object, got {$actualType}", -32602);
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
     * Log an MCP request for debugging.
     *
     * @param  string  $method  MCP method name
     * @param  array  $params  Request parameters
     */
    protected function logRequest(string $method, array $params): void
    {
        if (config('laravel-mcp.debug', false)) {
            logger('MCP Request', [
                'component' => $this->getComponentName(),
                'method' => $method,
                'params' => $params,
            ]);
        }
    }

    /**
     * Log an MCP response for debugging.
     *
     * @param  string  $method  MCP method name
     * @param  array  $response  Response data
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
}
