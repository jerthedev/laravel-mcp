<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

/**
 * Trait for formatting MCP responses.
 *
 * This trait provides comprehensive response formatting functionality
 * for MCP components, ensuring consistent response structures across
 * all tools, resources, and prompts, compliant with MCP protocol.
 */
trait FormatsResponses
{
    /**
     * Format a successful MCP response.
     */
    protected function formatSuccess($data = null, array $meta = []): array
    {
        $response = [
            'success' => true,
        ];

        if ($data !== null) {
            $response['data'] = $this->formatData($data);
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        // Add timestamp if configured
        if ($this->shouldIncludeTimestamp()) {
            $response['timestamp'] = now()->toIso8601String();
        }

        // Add component info if in debug mode
        if ($this->isDebugMode()) {
            $response['_debug'] = $this->getDebugInfo();
        }

        return $response;
    }

    /**
     * Format an error MCP response.
     */
    protected function formatError(
        string $message,
        int $code = -32603,
        $data = null,
        array $meta = []
    ): array {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($data !== null) {
            $response['error']['data'] = $this->formatData($data);
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        // Add timestamp if configured
        if ($this->shouldIncludeTimestamp()) {
            $response['timestamp'] = now()->toIso8601String();
        }

        // Add debug info if in debug mode
        if ($this->isDebugMode()) {
            $response['_debug'] = $this->getDebugInfo();
        }

        return $response;
    }

    /**
     * Format data for response.
     */
    protected function formatData($data)
    {
        // Handle different data types
        if (is_object($data)) {
            // Handle Laravel models
            if (method_exists($data, 'toArray')) {
                return $data->toArray();
            }

            // Handle JsonSerializable objects
            if ($data instanceof \JsonSerializable) {
                return $data->jsonSerialize();
            }

            // Convert to array
            return (array) $data;
        }

        // Handle collections
        if (is_iterable($data) && ! is_array($data)) {
            return collect($data)->toArray();
        }

        return $data;
    }

    /**
     * Format a tool execution response.
     */
    protected function formatToolResponse($result, array $meta = []): array
    {
        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $this->formatToolResult($result),
                ],
            ],
            'meta' => array_merge([
                'tool' => $this->getName(),
                'executed_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    /**
     * Format tool execution result.
     */
    protected function formatToolResult($result): string
    {
        if (is_string($result)) {
            return $result;
        }

        if (is_bool($result)) {
            return $result ? 'true' : 'false';
        }

        if (is_numeric($result)) {
            return (string) $result;
        }

        if (is_array($result) || is_object($result)) {
            return json_encode($this->formatData($result), JSON_PRETTY_PRINT);
        }

        return (string) $result;
    }

    /**
     * Format a resource read response.
     */
    protected function formatResourceReadResponse($data, string $uri, array $meta = []): array
    {
        return [
            'contents' => [
                [
                    'uri' => $uri,
                    'mimeType' => $this->getMimeType($data),
                    'text' => $this->formatResourceData($data),
                ],
            ],
            'meta' => array_merge([
                'resource' => $this->getName(),
                'read_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    /**
     * Format a resource list response.
     */
    protected function formatResourceListResponse(array $items, array $meta = []): array
    {
        $formattedItems = [];

        foreach ($items as $item) {
            $formattedItems[] = $this->formatResourceListItem($item);
        }

        return [
            'resources' => $formattedItems,
            'meta' => array_merge([
                'resource' => $this->getName(),
                'count' => count($formattedItems),
                'listed_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    /**
     * Format a single resource list item.
     */
    protected function formatResourceListItem($item): array
    {
        if (is_array($item)) {
            return [
                'uri' => $item['uri'] ?? $item['id'] ?? '',
                'name' => $item['name'] ?? $item['title'] ?? '',
                'description' => $item['description'] ?? '',
                'mimeType' => $item['mimeType'] ?? 'application/json',
            ];
        }

        if (is_object($item) && method_exists($item, 'toArray')) {
            $array = $item->toArray();

            return $this->formatResourceListItem($array);
        }

        return [
            'uri' => (string) $item,
            'name' => (string) $item,
            'description' => '',
            'mimeType' => 'text/plain',
        ];
    }

    /**
     * Format resource data for response.
     */
    protected function formatResourceData($data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_array($data) || is_object($data)) {
            return json_encode($this->formatData($data), JSON_PRETTY_PRINT);
        }

        return (string) $data;
    }

    /**
     * Format a prompt response.
     */
    protected function formatPromptResponse(string $content, array $meta = []): array
    {
        return [
            'description' => $this->getDescription(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $content,
                    ],
                ],
            ],
            'meta' => array_merge([
                'prompt' => $this->getName(),
                'generated_at' => now()->toIso8601String(),
            ], $meta),
        ];
    }

    /**
     * Format a paginated response.
     */
    protected function formatPaginatedResponse(
        array $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $meta = []
    ): array {
        return [
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => (int) ceil($total / $perPage),
                'from' => ($currentPage - 1) * $perPage + 1,
                'to' => min($currentPage * $perPage, $total),
            ],
            'meta' => $meta,
        ];
    }

    /**
     * Format a JSON-RPC response.
     */
    protected function formatJsonRpcResponse($result, $id = null): array
    {
        $response = [
            'jsonrpc' => '2.0',
        ];

        if ($result instanceof \Throwable) {
            $response['error'] = [
                'code' => $result->getCode() ?: -32603,
                'message' => $result->getMessage(),
            ];

            if ($this->isDebugMode()) {
                $response['error']['data'] = [
                    'type' => get_class($result),
                    'trace' => $result->getTraceAsString(),
                ];
            }
        } else {
            $response['result'] = $result;
        }

        if ($id !== null) {
            $response['id'] = $id;
        }

        return $response;
    }

    /**
     * Format a batch response.
     */
    protected function formatBatchResponse(array $responses): array
    {
        return array_map(function ($response) {
            if ($response instanceof \Throwable) {
                return $this->formatJsonRpcResponse($response);
            }

            return $response;
        }, $responses);
    }

    /**
     * Convert response to Laravel JSON response.
     */
    protected function toJsonResponse(array $response, int $statusCode = 200): JsonResponse
    {
        return response()->json($response, $statusCode, [
            'Content-Type' => 'application/json',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Convert response to Laravel response.
     */
    protected function toResponse($content, int $statusCode = 200, array $headers = []): Response
    {
        return response($content, $statusCode, $headers);
    }

    /**
     * Get MIME type for data.
     */
    protected function getMimeType($data): string
    {
        if (is_string($data)) {
            // Check if it's JSON
            if (json_decode($data) !== null) {
                return 'application/json';
            }

            // Check if it's XML (must check before HTML)
            if (preg_match('/<\?xml/', $data)) {
                return 'application/xml';
            }

            // Check if it's HTML
            if (preg_match('/<[^>]+>/', $data)) {
                return 'text/html';
            }

            return 'text/plain';
        }

        if (is_array($data) || is_object($data)) {
            return 'application/json';
        }

        return 'text/plain';
    }

    /**
     * Check if timestamp should be included.
     */
    protected function shouldIncludeTimestamp(): bool
    {
        return Config::get('laravel-mcp.response.include_timestamp', true);
    }

    /**
     * Check if in debug mode.
     */
    protected function isDebugMode(): bool
    {
        return Config::get('app.debug', false) && Config::get('laravel-mcp.debug', false);
    }

    /**
     * Get debug information.
     */
    protected function getDebugInfo(): array
    {
        return [
            'component' => static::class,
            'type' => $this->getComponentType(),
            'name' => $this->getName(),
            'memory_usage' => memory_get_usage(true),
            'execution_time' => defined('LARAVEL_START')
                ? microtime(true) - LARAVEL_START
                : null,
        ];
    }

    /**
     * Format capabilities response.
     */
    protected function formatCapabilitiesResponse(): array
    {
        return [
            'capabilities' => $this->getCapabilities(),
            'supported_operations' => $this->getSupportedOperations(),
            'metadata' => $this->getCapabilityMetadata(),
        ];
    }

    /**
     * Format validation errors response.
     */
    protected function formatValidationErrors(array $errors): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => -32602,
                'message' => 'Validation failed',
                'data' => [
                    'validation_errors' => $errors,
                ],
            ],
        ];
    }

    /**
     * Strip sensitive data from response.
     */
    protected function stripSensitiveData(array $data, array $sensitiveFields = []): array
    {
        $defaultSensitiveFields = [
            'password',
            'token',
            'secret',
            'api_key',
            'private_key',
            'access_token',
            'refresh_token',
        ];

        $fieldsToStrip = array_merge($defaultSensitiveFields, $sensitiveFields);

        array_walk_recursive($data, function (&$value, $key) use ($fieldsToStrip) {
            if (in_array($key, $fieldsToStrip)) {
                $value = '[REDACTED]';
            }
        });

        return $data;
    }

    /**
     * Add CORS headers to response.
     */
    protected function addCorsHeaders(array $response): array
    {
        if (! Config::get('laravel-mcp.cors.enabled', false)) {
            return $response;
        }

        return array_merge($response, [
            '_cors' => [
                'origin' => Config::get('laravel-mcp.cors.allowed_origins', '*'),
                'methods' => Config::get('laravel-mcp.cors.allowed_methods', ['POST']),
                'headers' => Config::get('laravel-mcp.cors.allowed_headers', ['Content-Type']),
            ],
        ]);
    }
}
