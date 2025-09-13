<?php

namespace JTD\LaravelMCP;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\Notifications\McpErrorNotification;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Support\McpConstants;

/**
 * MCP Manager - Bridge between facade and services.
 *
 * This class acts as a bridge to provide both route-style registration
 * (via RouteRegistrar) and direct registry access (via McpRegistry).
 */
class McpManager
{
    /**
     * The MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * The route registrar instance.
     */
    protected RouteRegistrar $registrar;

    /**
     * Create a new MCP manager instance.
     */
    public function __construct(McpRegistry $registry, RouteRegistrar $registrar)
    {
        $this->registry = $registry;
        $this->registrar = $registrar;
    }

    /**
     * Register a tool using route-style registration.
     *
     * @param  string  $name  Tool name
     * @param  mixed  $handler  Tool handler
     * @param  array  $options  Tool options
     * @return $this
     */
    public function tool(string $name, $handler, array $options = []): self
    {
        $this->registrar->tool($name, $handler, $options);

        return $this;
    }

    /**
     * Register a resource using route-style registration.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $handler  Resource handler
     * @param  array  $options  Resource options
     * @return $this
     */
    public function resource(string $name, $handler, array $options = []): self
    {
        $this->registrar->resource($name, $handler, $options);

        return $this;
    }

    /**
     * Register a prompt using route-style registration.
     *
     * @param  string  $name  Prompt name
     * @param  mixed  $handler  Prompt handler
     * @param  array  $options  Prompt options
     * @return $this
     */
    public function prompt(string $name, $handler, array $options = []): self
    {
        $this->registrar->prompt($name, $handler, $options);

        return $this;
    }

    /**
     * Create a group of component registrations with shared attributes.
     *
     * @param  array  $attributes  Shared attributes
     * @param  \Closure  $callback  Group callback
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $this->registrar->group($attributes, $callback);
    }

    /**
     * Dynamically handle calls to the underlying services.
     *
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        // First check if the method exists on the registrar
        if (method_exists($this->registrar, $method)) {
            return $this->registrar->$method(...$parameters);
        }

        // Then check if the method exists on the registry
        if (method_exists($this->registry, $method)) {
            return $this->registry->$method(...$parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on McpManager");
    }

    /**
     * Get the underlying registry instance.
     */
    public function getRegistry(): McpRegistry
    {
        return $this->registry;
    }

    /**
     * Get the underlying registrar instance.
     */
    public function getRegistrar(): RouteRegistrar
    {
        return $this->registrar;
    }

    /**
     * Dispatch a component registered event.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  mixed  $component  Component instance
     * @param  array  $metadata  Component metadata
     */
    public function dispatchComponentRegistered(string $type, string $name, $component, array $metadata = []): void
    {
        if (config('laravel-mcp.events.enabled', true)) {
            event(new McpComponentRegistered($type, $name, $component, $metadata));
        }
    }

    /**
     * Dispatch a request processed event.
     *
     * @param  string|int  $requestId  Request ID
     * @param  string  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  mixed  $result  Request result
     * @param  float  $executionTime  Execution time
     * @param  string  $transport  Transport type
     * @param  array  $context  Additional context
     */
    public function dispatchRequestProcessed(
        string|int $requestId,
        string $method,
        array $parameters,
        $result,
        float $executionTime,
        string $transport = 'http',
        array $context = []
    ): void {
        if (config('laravel-mcp.events.enabled', true)) {
            event(new McpRequestProcessed(
                $requestId,
                $method,
                $parameters,
                $result,
                $executionTime,
                $transport,
                $context
            ));
        }
    }

    /**
     * Dispatch an MCP request asynchronously.
     *
     * @param  string  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @return string Request ID
     */
    public function dispatchAsync(string $method, array $parameters = [], array $context = []): string
    {
        if (! config('laravel-mcp.queue.enabled', true)) {
            throw new \RuntimeException('Queue processing is not enabled for MCP');
        }

        $job = new ProcessMcpRequest($method, $parameters, null, $context);

        $queue = config('laravel-mcp.queue.default', 'default');
        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);

        Log::info('MCP request dispatched to queue', [
            'request_id' => $job->requestId,
            'method' => $method,
            'queue' => $queue,
        ]);

        return $job->requestId;
    }

    /**
     * Get the result of an async request.
     *
     * @param  string  $requestId  Request ID
     */
    public function getAsyncResult(string $requestId): ?array
    {
        return Cache::get("mcp:async:result:{$requestId}");
    }

    /**
     * Get the status of an async request.
     *
     * @param  string  $requestId  Request ID
     */
    public function getAsyncStatus(string $requestId): ?array
    {
        return Cache::get("mcp:async:status:{$requestId}");
    }

    /**
     * Send an error notification.
     *
     * @param  string  $errorType  Error type
     * @param  string  $errorMessage  Error message
     * @param  string|null  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  \Throwable|null  $exception  Exception
     * @param  string  $severity  Severity level
     */
    public function notifyError(
        string $errorType,
        string $errorMessage,
        ?string $method = null,
        array $parameters = [],
        ?\Throwable $exception = null,
        string $severity = 'error'
    ): void {
        if (! config('laravel-mcp.notifications.enabled', true)) {
            return;
        }

        $notification = new McpErrorNotification(
            $errorType,
            $errorMessage,
            $method,
            $parameters,
            [],
            $exception,
            $severity
        );

        // Get notifiable instance
        $notifiable = $this->getNotifiable();

        if ($notifiable) {
            $notifiable->notify($notification);

            Log::info('MCP error notification sent', [
                'error_type' => $errorType,
                'severity' => $severity,
                'method' => $method,
            ]);
        }
    }

    /**
     * Get the notifiable instance for notifications.
     *
     * @return mixed
     */
    protected function getNotifiable()
    {
        // Try to get from config
        $notifiableClass = config('laravel-mcp.notifications.notifiable');

        if ($notifiableClass && class_exists($notifiableClass)) {
            return app($notifiableClass);
        }

        // Fall back to logged-in user
        if (function_exists('auth') && auth()->check()) {
            return auth()->user();
        }

        // Fall back to admin email
        $adminEmail = config('laravel-mcp.notifications.admin_email');
        if ($adminEmail) {
            return new \Illuminate\Notifications\AnonymousNotifiable([
                'mail' => $adminEmail,
            ]);
        }

        return null;
    }

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->registry->getCapabilities();
    }

    /**
     * Set server capabilities.
     */
    public function setCapabilities(array $capabilities): void
    {
        $this->registry->setCapabilities($capabilities);
    }

    /**
     * Register a tool.
     *
     * @param  string  $name  Tool name
     * @param  mixed  $tool  Tool instance
     * @param  array  $metadata  Tool metadata
     */
    public function registerTool(string $name, $tool, array $metadata = []): void
    {
        $this->registry->register('tool', $name, $tool, $metadata);
        $this->dispatchComponentRegistered('tool', $name, $tool, $metadata);
    }

    /**
     * Register a resource.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $resource  Resource instance
     * @param  array  $metadata  Resource metadata
     */
    public function registerResource(string $name, $resource, array $metadata = []): void
    {
        $this->registry->register('resource', $name, $resource, $metadata);
        $this->dispatchComponentRegistered('resource', $name, $resource, $metadata);
    }

    /**
     * Register a prompt.
     *
     * @param  string  $name  Prompt name
     * @param  mixed  $prompt  Prompt instance
     * @param  array  $metadata  Prompt metadata
     */
    public function registerPrompt(string $name, $prompt, array $metadata = []): void
    {
        $this->registry->register('prompt', $name, $prompt, $metadata);
        $this->dispatchComponentRegistered('prompt', $name, $prompt, $metadata);
    }

    /**
     * Unregister a tool.
     *
     * @param  string  $name  Tool name
     */
    public function unregisterTool(string $name): bool
    {
        return $this->registry->unregister('tool', $name);
    }

    /**
     * Unregister a resource.
     *
     * @param  string  $name  Resource name
     */
    public function unregisterResource(string $name): bool
    {
        return $this->registry->unregister('resource', $name);
    }

    /**
     * Unregister a prompt.
     *
     * @param  string  $name  Prompt name
     */
    public function unregisterPrompt(string $name): bool
    {
        return $this->registry->unregister('prompt', $name);
    }

    /**
     * List all tools.
     */
    public function listTools(): array
    {
        return $this->registry->listTools();
    }

    /**
     * List all resources.
     */
    public function listResources(): array
    {
        return $this->registry->listResources();
    }

    /**
     * List all prompts.
     */
    public function listPrompts(): array
    {
        return $this->registry->listPrompts();
    }

    /**
     * Get a tool by name.
     *
     * @param  string  $name  Tool name
     * @return mixed
     */
    public function getTool(string $name)
    {
        return $this->registry->get('tool', $name);
    }

    /**
     * Get a resource by name.
     *
     * @param  string  $name  Resource name
     * @return mixed
     */
    public function getResource(string $name)
    {
        return $this->registry->get('resource', $name);
    }

    /**
     * Get a prompt by name.
     *
     * @param  string  $name  Prompt name
     * @return mixed
     */
    public function getPrompt(string $name)
    {
        return $this->registry->get('prompt', $name);
    }

    /**
     * Check if a tool exists.
     *
     * @param  string  $name  Tool name
     */
    public function hasTool(string $name): bool
    {
        return $this->registry->has('tool', $name);
    }

    /**
     * Check if a resource exists.
     *
     * @param  string  $name  Resource name
     */
    public function hasResource(string $name): bool
    {
        return $this->registry->has('resource', $name);
    }

    /**
     * Check if a prompt exists.
     *
     * @param  string  $name  Prompt name
     */
    public function hasPrompt(string $name): bool
    {
        return $this->registry->has('prompt', $name);
    }

    /**
     * Discover components in paths.
     *
     * @param  array  $paths  Paths to discover
     */
    public function discover(array $paths = []): array
    {
        return $this->registry->discover($paths);
    }

    /**
     * Get server info.
     */
    public function getServerInfo(): array
    {
        return [
            'name' => config('laravel-mcp.server.name', 'Laravel MCP Server'),
            'version' => config('laravel-mcp.server.version', '1.0.0'),
            'protocol_version' => McpConstants::getMcpVersion(),
            'capabilities' => $this->getCapabilities(),
            'components' => [
                'tools' => count($this->listTools()),
                'resources' => count($this->listResources()),
                'prompts' => count($this->listPrompts()),
            ],
        ];
    }

    /**
     * Get server statistics.
     */
    public function getServerStats(): array
    {
        return [
            'uptime' => time() - app()->make('laravel-mcp.start_time', time()),
            'requests_processed' => Cache::get('mcp:stats:requests_processed', 0),
            'errors_count' => Cache::get('mcp:stats:errors_count', 0),
            'average_response_time' => Cache::get('mcp:stats:avg_response_time', 0),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Enable debug mode.
     */
    public function enableDebugMode(): void
    {
        config(['laravel-mcp.debug' => true]);
    }

    /**
     * Disable debug mode.
     */
    public function disableDebugMode(): void
    {
        config(['laravel-mcp.debug' => false]);
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebugMode(): bool
    {
        return config('laravel-mcp.debug', false);
    }

    /**
     * Start the MCP server.
     *
     * @param  array  $config  Server configuration
     */
    public function startServer(array $config = []): void
    {
        // This would be implemented based on transport type
        Log::info('MCP server start requested', $config);
    }

    /**
     * Stop the MCP server.
     */
    public function stopServer(): void
    {
        // This would be implemented based on transport type
        Log::info('MCP server stop requested');
    }

    /**
     * Check if server is running.
     */
    public function isServerRunning(): bool
    {
        // This would be implemented based on transport type
        return false;
    }

    /**
     * Fire a component registered event.
     *
     * Alias for dispatchComponentRegistered for facade compatibility.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @param  mixed  $component  Component instance
     * @param  array  $metadata  Component metadata
     */
    public function fireComponentRegistered(string $type, string $name, $component, array $metadata = []): void
    {
        $this->dispatchComponentRegistered($type, $name, $component, $metadata);
    }

    /**
     * Fire a request processed event.
     *
     * Alias for dispatchRequestProcessed for facade compatibility.
     *
     * @param  string|int  $requestId  Request ID
     * @param  string  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  mixed  $result  Request result
     * @param  float  $executionTime  Execution time
     * @param  string  $transport  Transport type
     * @param  array  $context  Additional context
     */
    public function fireRequestProcessed(
        string|int $requestId,
        string $method,
        array $parameters,
        $result,
        float $executionTime,
        string $transport = 'http',
        array $context = []
    ): void {
        $this->dispatchRequestProcessed(
            $requestId,
            $method,
            $parameters,
            $result,
            $executionTime,
            $transport,
            $context
        );
    }

    /**
     * Process an MCP request asynchronously.
     *
     * Alias for dispatchAsync for facade compatibility.
     *
     * @param  string  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @return string Request ID
     */
    public function async(string $method, array $parameters = [], array $context = []): string
    {
        return $this->dispatchAsync($method, $parameters, $context);
    }

    /**
     * Get the result of an async MCP request.
     *
     * Alias for getAsyncResult for facade compatibility.
     *
     * @param  string  $requestId  Request ID
     * @return mixed Result data or null if not found
     */
    public function asyncResult(string $requestId)
    {
        $result = $this->getAsyncResult($requestId);

        // If result is an array with a 'result' key, return just the result
        if (is_array($result) && isset($result['result'])) {
            return $result['result'];
        }

        return $result;
    }
}
