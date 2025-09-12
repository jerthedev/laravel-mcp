<?php

use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Support\Debugger;
use JTD\LaravelMCP\Support\MessageSerializer;
use JTD\LaravelMCP\Support\PerformanceMonitor;

if (! function_exists('mcp')) {
    /**
     * Get the MCP manager instance or register a component.
     *
     * @param  string|null  $type  Component type (tool, resource, prompt)
     * @param  string|null  $name  Component name
     * @param  mixed  $component  Component instance or class name
     * @return \JTD\LaravelMCP\McpManager|mixed
     */
    function mcp(?string $type = null, ?string $name = null, $component = null)
    {
        $manager = app('laravel-mcp');

        if ($type === null) {
            return $manager;
        }

        if ($name === null) {
            throw new InvalidArgumentException('Component name is required');
        }

        if ($component === null) {
            // Get component
            return match ($type) {
                'tool' => $manager->getTool($name),
                'resource' => $manager->getResource($name),
                'prompt' => $manager->getPrompt($name),
                default => throw new InvalidArgumentException("Invalid component type: {$type}")
            };
        }

        // Register component
        match ($type) {
            'tool' => $manager->registerTool($name, $component),
            'resource' => $manager->registerResource($name, $component),
            'prompt' => $manager->registerPrompt($name, $component),
            default => throw new InvalidArgumentException("Invalid component type: {$type}")
        };

        return $manager;
    }
}

if (! function_exists('mcp_tool')) {
    /**
     * Register or get an MCP tool.
     *
     * @param  string  $name  Tool name
     * @param  mixed  $tool  Tool instance or class name (optional)
     * @param  array  $metadata  Tool metadata (optional)
     * @return mixed The tool instance or manager
     */
    function mcp_tool(string $name, $tool = null, array $metadata = [])
    {
        if ($tool === null) {
            return mcp('tool', $name);
        }

        mcp('tool', $name, $tool);

        if (! empty($metadata)) {
            $manager = app('laravel-mcp');
            // Store metadata if needed
            cache()->put("mcp:tool:{$name}:metadata", $metadata, now()->addHours(24));
        }

        return app('laravel-mcp');
    }
}

if (! function_exists('mcp_resource')) {
    /**
     * Register or get an MCP resource.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $resource  Resource instance or class name (optional)
     * @param  array  $metadata  Resource metadata (optional)
     * @return mixed The resource instance or manager
     */
    function mcp_resource(string $name, $resource = null, array $metadata = [])
    {
        if ($resource === null) {
            return mcp('resource', $name);
        }

        mcp('resource', $name, $resource);

        if (! empty($metadata)) {
            $manager = app('laravel-mcp');
            // Store metadata if needed
            cache()->put("mcp:resource:{$name}:metadata", $metadata, now()->addHours(24));
        }

        return app('laravel-mcp');
    }
}

if (! function_exists('mcp_prompt')) {
    /**
     * Register or get an MCP prompt.
     *
     * @param  string  $name  Prompt name
     * @param  mixed  $prompt  Prompt instance or class name (optional)
     * @param  array  $metadata  Prompt metadata (optional)
     * @return mixed The prompt instance or manager
     */
    function mcp_prompt(string $name, $prompt = null, array $metadata = [])
    {
        if ($prompt === null) {
            return mcp('prompt', $name);
        }

        mcp('prompt', $name, $prompt);

        if (! empty($metadata)) {
            $manager = app('laravel-mcp');
            // Store metadata if needed
            cache()->put("mcp:prompt:{$name}:metadata", $metadata, now()->addHours(24));
        }

        return app('laravel-mcp');
    }
}

if (! function_exists('mcp_dispatch')) {
    /**
     * Dispatch an MCP request synchronously.
     *
     * @param  string  $method  MCP method to execute
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @return mixed The result of the request
     */
    function mcp_dispatch(string $method, array $parameters = [], array $context = [])
    {
        $manager = app('laravel-mcp');

        // Process the request based on method type
        if (str_starts_with($method, 'tools/')) {
            $toolName = substr($method, 6);
            $tool = $manager->getTool($toolName);

            if (! $tool) {
                throw new RuntimeException("Tool not found: {$toolName}");
            }

            return $tool->execute($parameters);
        }

        if (str_starts_with($method, 'resources/')) {
            $parts = explode('/', substr($method, 10));
            $resourceName = $parts[0];
            $action = $parts[1] ?? 'read';

            $resource = $manager->getResource($resourceName);

            if (! $resource) {
                throw new RuntimeException("Resource not found: {$resourceName}");
            }

            return match ($action) {
                'read' => $resource->read($parameters),
                'list' => $resource->list($parameters),
                'subscribe' => $resource->subscribe($parameters),
                default => throw new RuntimeException("Invalid resource action: {$action}")
            };
        }

        if (str_starts_with($method, 'prompts/')) {
            $promptName = substr($method, 8);
            $prompt = $manager->getPrompt($promptName);

            if (! $prompt) {
                throw new RuntimeException("Prompt not found: {$promptName}");
            }

            return $prompt->generate($parameters);
        }

        throw new RuntimeException("Unknown MCP method: {$method}");
    }
}

if (! function_exists('mcp_async')) {
    /**
     * Dispatch an MCP request asynchronously via job queue.
     *
     * @param  string  $method  MCP method to execute
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @param  string|null  $queue  Queue name (optional)
     * @return string Request ID for tracking
     */
    function mcp_async(string $method, array $parameters = [], array $context = [], ?string $queue = null): string
    {
        return Mcp::async($method, $parameters, $context, $queue);
    }
}

if (! function_exists('mcp_async_result')) {
    /**
     * Get the result of an async MCP request.
     *
     * @param  string  $requestId  The request ID to check
     * @return array|null The result data or null if not ready
     */
    function mcp_async_result(string $requestId): ?array
    {
        return Mcp::asyncResult($requestId);
    }
}

if (! function_exists('mcp_async_status')) {
    /**
     * Get the status of an async MCP request.
     *
     * @param  string  $requestId  The request ID to check
     * @return array|null The status data or null if not found
     */
    function mcp_async_status(string $requestId): ?array
    {
        return Mcp::asyncStatus($requestId);
    }
}

if (! function_exists('mcp_serialize')) {
    /**
     * Serialize MCP message data.
     *
     * @param  array  $message  The message to serialize
     * @param  int  $maxDepth  Maximum serialization depth
     * @return string The JSON-encoded message
     */
    function mcp_serialize(array $message, int $maxDepth = 10): string
    {
        $serializer = new MessageSerializer($maxDepth);

        return $serializer->serialize($message);
    }
}

if (! function_exists('mcp_deserialize')) {
    /**
     * Deserialize MCP message data.
     *
     * @param  string  $json  The JSON string to deserialize
     * @return array The decoded message
     */
    function mcp_deserialize(string $json): array
    {
        $serializer = new MessageSerializer;

        return $serializer->deserialize($json);
    }
}

if (! function_exists('mcp_debug')) {
    /**
     * Get the MCP debugger instance or log debug information.
     *
     * @param  string|null  $message  Debug message (optional)
     * @param  array  $context  Debug context (optional)
     * @return \JTD\LaravelMCP\Support\Debugger|void
     */
    function mcp_debug(?string $message = null, array $context = [])
    {
        $debugger = app(Debugger::class);

        if ($message === null) {
            return $debugger;
        }

        $debugger->log($message, $context);
    }
}

if (! function_exists('mcp_performance')) {
    /**
     * Get the MCP performance monitor instance or record a metric.
     *
     * @param  string|null  $metric  Metric name (optional)
     * @param  float|null  $value  Metric value (optional)
     * @param  array  $tags  Metric tags (optional)
     * @return \JTD\LaravelMCP\Support\PerformanceMonitor|void
     */
    function mcp_performance(?string $metric = null, ?float $value = null, array $tags = [])
    {
        $monitor = app(PerformanceMonitor::class);

        if ($metric === null) {
            return $monitor;
        }

        if ($value !== null) {
            $monitor->record($metric, $value, $tags);
        }
    }
}

if (! function_exists('mcp_measure')) {
    /**
     * Measure the execution time of a callback.
     *
     * @param  callable  $callback  The callback to measure
     * @param  string  $metric  Metric name for recording
     * @param  array  $tags  Metric tags (optional)
     * @return mixed The result of the callback
     */
    function mcp_measure(callable $callback, string $metric, array $tags = [])
    {
        $monitor = app(PerformanceMonitor::class);

        return $monitor->measure($callback, $metric, $tags);
    }
}

if (! function_exists('mcp_is_running')) {
    /**
     * Check if the MCP server is running.
     *
     * @return bool True if server is running
     */
    function mcp_is_running(): bool
    {
        return Mcp::isServerRunning();
    }
}

if (! function_exists('mcp_capabilities')) {
    /**
     * Get or set MCP server capabilities.
     *
     * @param  array|null  $capabilities  Capabilities to set (optional)
     * @return array Current capabilities
     */
    function mcp_capabilities(?array $capabilities = null): array
    {
        if ($capabilities !== null) {
            Mcp::setCapabilities($capabilities);
        }

        return Mcp::getCapabilities();
    }
}

if (! function_exists('mcp_stats')) {
    /**
     * Get MCP component statistics.
     *
     * @return array Component counts and statistics
     */
    function mcp_stats(): array
    {
        return Mcp::getComponentSummary();
    }
}

if (! function_exists('mcp_discover')) {
    /**
     * Discover MCP components in specified paths.
     *
     * @param  array  $paths  Paths to discover components
     * @return array Discovered components
     */
    function mcp_discover(array $paths = []): array
    {
        return Mcp::discover($paths);
    }
}

if (! function_exists('mcp_validate_message')) {
    /**
     * Validate a JSON-RPC message structure.
     *
     * @param  array  $message  The message to validate
     * @return bool True if valid
     */
    function mcp_validate_message(array $message): bool
    {
        $serializer = new MessageSerializer;

        return $serializer->validateMessage($message);
    }
}

if (! function_exists('mcp_error')) {
    /**
     * Create an MCP error response.
     *
     * @param  int  $code  Error code
     * @param  string  $message  Error message
     * @param  mixed  $data  Additional error data (optional)
     * @param  string|int|null  $id  Request ID (optional)
     * @return array The error response
     */
    function mcp_error(int $code, string $message, $data = null, $id = null): array
    {
        $error = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];

        if ($data !== null) {
            $error['error']['data'] = $data;
        }

        return $error;
    }
}

if (! function_exists('mcp_success')) {
    /**
     * Create an MCP success response.
     *
     * @param  mixed  $result  The result data
     * @param  string|int|null  $id  Request ID
     * @return array The success response
     */
    function mcp_success($result, $id = null): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];
    }
}

if (! function_exists('mcp_notification')) {
    /**
     * Create an MCP notification (request without ID).
     *
     * @param  string  $method  The method name
     * @param  array  $params  The parameters
     * @return array The notification
     */
    function mcp_notification(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];
    }
}
