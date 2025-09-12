<?php

namespace JTD\LaravelMCP\Facades;

use Illuminate\Support\Facades\Facade;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\Notifications\McpErrorNotification;

/**
 * MCP Facade for Laravel MCP package.
 *
 * This facade provides a convenient way to interact with the MCP server
 * functionality from anywhere in the Laravel application. It exposes
 * methods for managing tools, resources, prompts, and server operations.
 *
 * @method static array getCapabilities()
 * @method static void setCapabilities(array $capabilities)
 * @method static void registerTool(string $name, $tool, array $metadata = [])
 * @method static void registerResource(string $name, $resource, array $metadata = [])
 * @method static void registerPrompt(string $name, $prompt, array $metadata = [])
 * @method static bool unregisterTool(string $name)
 * @method static bool unregisterResource(string $name)
 * @method static bool unregisterPrompt(string $name)
 * @method static array listTools()
 * @method static array listResources()
 * @method static array listPrompts()
 * @method static mixed getTool(string $name)
 * @method static mixed getResource(string $name)
 * @method static mixed getPrompt(string $name)
 * @method static bool hasTool(string $name)
 * @method static bool hasResource(string $name)
 * @method static bool hasPrompt(string $name)
 * @method static array discover(array $paths = [])
 * @method static void startServer(array $config = [])
 * @method static void stopServer()
 * @method static bool isServerRunning()
 * @method static array getServerInfo()
 * @method static array getServerStats()
 * @method static void enableDebugMode()
 * @method static void disableDebugMode()
 * @method static bool isDebugMode()
 * @method static void dispatchComponentRegistered(string $type, string $name, $component, array $metadata = [])
 * @method static void dispatchRequestProcessed(string|int $requestId, string $method, array $parameters, $result, float $executionTime)
 * @method static string dispatchAsync(string $method, array $parameters = [], array $context = [])
 * @method static array|null getAsyncResult(string $requestId)
 * @method static array|null getAsyncStatus(string $requestId)
 * @method static void notifyError(string $errorType, string $errorMessage, ?string $method = null, array $parameters = [], ?\Throwable $exception = null)
 *
 * @see \JTD\LaravelMCP\McpManager
 */
class Mcp extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'laravel-mcp';
    }

    /**
     * Register a tool with fluent interface.
     *
     * @param  string  $name  Tool name
     * @param  mixed  $tool  Tool instance or class name
     * @param  array  $metadata  Tool metadata
     * @return static
     */
    public static function tool(string $name, $tool, array $metadata = []): self
    {
        static::registerTool($name, $tool, $metadata);

        return new static;
    }

    /**
     * Register a resource with fluent interface.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $resource  Resource instance or class name
     * @param  array  $metadata  Resource metadata
     * @return static
     */
    public static function resource(string $name, $resource, array $metadata = []): self
    {
        static::registerResource($name, $resource, $metadata);

        return new static;
    }

    /**
     * Register a prompt with fluent interface.
     *
     * @param  string  $name  Prompt name
     * @param  mixed  $prompt  Prompt instance or class name
     * @param  array  $metadata  Prompt metadata
     * @return static
     */
    public static function prompt(string $name, $prompt, array $metadata = []): self
    {
        static::registerPrompt($name, $prompt, $metadata);

        return new static;
    }

    /**
     * Configure server capabilities with fluent interface.
     *
     * @param  array  $capabilities  Capabilities configuration
     * @return static
     */
    public static function capabilities(array $capabilities): self
    {
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Enable tools capabilities.
     *
     * @param  array  $config  Tools configuration
     * @return static
     */
    public static function withTools(array $config = []): self
    {
        $capabilities = static::getCapabilities();
        $capabilities['tools'] = array_merge($capabilities['tools'] ?? [], $config);
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Enable resources capabilities.
     *
     * @param  array  $config  Resources configuration
     * @return static
     */
    public static function withResources(array $config = []): self
    {
        $capabilities = static::getCapabilities();
        $capabilities['resources'] = array_merge($capabilities['resources'] ?? [], $config);
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Enable prompts capabilities.
     *
     * @param  array  $config  Prompts configuration
     * @return static
     */
    public static function withPrompts(array $config = []): self
    {
        $capabilities = static::getCapabilities();
        $capabilities['prompts'] = array_merge($capabilities['prompts'] ?? [], $config);
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Enable logging capabilities.
     *
     * @param  array  $config  Logging configuration
     * @return static
     */
    public static function withLogging(array $config = []): self
    {
        $capabilities = static::getCapabilities();
        $capabilities['logging'] = array_merge($capabilities['logging'] ?? [], $config);
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Configure experimental capabilities.
     *
     * @param  array  $config  Experimental configuration
     * @return static
     */
    public static function withExperimental(array $config = []): self
    {
        $capabilities = static::getCapabilities();
        $capabilities['experimental'] = array_merge($capabilities['experimental'] ?? [], $config);
        static::setCapabilities($capabilities);

        return new static;
    }

    /**
     * Discover components in specified paths.
     *
     * @param  array  $paths  Paths to discover components
     * @return static
     */
    public static function discoverIn(array $paths): self
    {
        static::discover($paths);

        return new static;
    }

    /**
     * Get component count by type.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     */
    public static function countComponents(string $type): int
    {
        switch ($type) {
            case 'tools':
                return count(static::listTools());
            case 'resources':
                return count(static::listResources());
            case 'prompts':
                return count(static::listPrompts());
            default:
                return 0;
        }
    }

    /**
     * Get total component count.
     */
    public static function totalComponents(): int
    {
        return static::countComponents('tools') +
               static::countComponents('resources') +
               static::countComponents('prompts');
    }

    /**
     * Check if any components are registered.
     */
    public static function hasComponents(): bool
    {
        return static::totalComponents() > 0;
    }

    /**
     * Get component summary.
     */
    public static function getComponentSummary(): array
    {
        return [
            'tools' => static::countComponents('tools'),
            'resources' => static::countComponents('resources'),
            'prompts' => static::countComponents('prompts'),
            'total' => static::totalComponents(),
        ];
    }

    /**
     * Reset all registered components.
     *
     * @return static
     */
    public static function reset(): self
    {
        $tools = static::listTools();
        foreach (array_keys($tools) as $name) {
            static::unregisterTool($name);
        }

        $resources = static::listResources();
        foreach (array_keys($resources) as $name) {
            static::unregisterResource($name);
        }

        $prompts = static::listPrompts();
        foreach (array_keys($prompts) as $name) {
            static::unregisterPrompt($name);
        }

        return new static;
    }

    /**
     * Fire an event when a component is registered.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     * @param  mixed  $component  Component instance
     * @param  array  $metadata  Component metadata
     * @return static
     */
    public static function fireComponentRegistered(string $type, string $name, $component, array $metadata = []): self
    {
        event(new McpComponentRegistered($type, $name, $component, $metadata));

        return new static;
    }

    /**
     * Fire an event when a request is processed.
     *
     * @param  string|int  $requestId  Request ID
     * @param  string  $method  MCP method
     * @param  array  $parameters  Request parameters
     * @param  mixed  $result  Request result
     * @param  float  $executionTime  Execution time in milliseconds
     * @param  string  $transport  Transport type
     * @param  array  $context  Additional context
     * @return static
     */
    public static function fireRequestProcessed(
        string|int $requestId,
        string $method,
        array $parameters,
        $result,
        float $executionTime,
        string $transport = 'http',
        array $context = []
    ): self {
        event(new McpRequestProcessed(
            $requestId,
            $method,
            $parameters,
            $result,
            $executionTime,
            $transport,
            $context
        ));

        return new static;
    }

    /**
     * Dispatch an MCP request asynchronously via job queue.
     *
     * @param  string  $method  MCP method to execute
     * @param  array  $parameters  Request parameters
     * @param  array  $context  Additional context
     * @param  string|null  $queue  Queue name
     * @return string Request ID for tracking
     */
    public static function async(string $method, array $parameters = [], array $context = [], ?string $queue = null): string
    {
        $job = new ProcessMcpRequest($method, $parameters, null, $context);

        if ($queue) {
            $job->onQueue($queue);
        }

        dispatch($job);

        return $job->requestId;
    }

    /**
     * Get the result of an async MCP request.
     *
     * @param  string  $requestId  The request ID to check
     * @return array|null The result data or null if not ready
     */
    public static function asyncResult(string $requestId): ?array
    {
        return cache()->get("mcp:async:result:{$requestId}");
    }

    /**
     * Get the status of an async MCP request.
     *
     * @param  string  $requestId  The request ID to check
     * @return array|null The status data or null if not found
     */
    public static function asyncStatus(string $requestId): ?array
    {
        return cache()->get("mcp:async:status:{$requestId}");
    }

    /**
     * Send an error notification.
     *
     * @param  string  $errorType  Error type
     * @param  string  $errorMessage  Error message
     * @param  string|null  $method  MCP method that caused the error
     * @param  array  $parameters  Request parameters
     * @param  \Throwable|null  $exception  The exception
     * @param  string  $severity  Severity level
     * @return static
     */
    public static function notifyError(
        string $errorType,
        string $errorMessage,
        ?string $method = null,
        array $parameters = [],
        ?\Throwable $exception = null,
        string $severity = 'error'
    ): self {
        $notification = new McpErrorNotification(
            $errorType,
            $errorMessage,
            $method,
            $parameters,
            [],
            $exception,
            $severity
        );

        // Send to configured notifiable
        $notifiable = static::getNotifiable();
        if ($notifiable) {
            $notifiable->notify($notification);
        }

        return new static;
    }

    /**
     * Configure event listeners with fluent interface.
     *
     * @param  string  $event  Event class name
     * @param  callable|string  $listener  Listener callback or class
     * @return static
     */
    public static function on(string $event, $listener): self
    {
        app('events')->listen($event, $listener);

        return new static;
    }

    /**
     * Configure event listener for component registration.
     *
     * @param  callable  $callback  Callback function
     * @return static
     */
    public static function onComponentRegistered(callable $callback): self
    {
        return static::on(McpComponentRegistered::class, $callback);
    }

    /**
     * Configure event listener for request processing.
     *
     * @param  callable  $callback  Callback function
     * @return static
     */
    public static function onRequestProcessed(callable $callback): self
    {
        return static::on(McpRequestProcessed::class, $callback);
    }

    /**
     * Get the notifiable instance for sending notifications.
     *
     * @return mixed
     */
    protected static function getNotifiable()
    {
        // Try to get from config
        $notifiableClass = config('laravel-mcp.notifications.notifiable');

        if ($notifiableClass && class_exists($notifiableClass)) {
            return app($notifiableClass);
        }

        // Fall back to logged-in user
        if (auth()->check()) {
            return auth()->user();
        }

        // Fall back to a default admin notifiable if configured
        $adminEmail = config('laravel-mcp.notifications.admin_email');
        if ($adminEmail) {
            return new \Illuminate\Notifications\AnonymousNotifiable([
                'mail' => $adminEmail,
            ]);
        }

        return null;
    }

    /**
     * Enable event dispatching.
     *
     * @return static
     */
    public static function withEvents(): self
    {
        config(['laravel-mcp.events.enabled' => true]);

        return new static;
    }

    /**
     * Disable event dispatching.
     *
     * @return static
     */
    public static function withoutEvents(): self
    {
        config(['laravel-mcp.events.enabled' => false]);

        return new static;
    }

    /**
     * Enable job queue processing.
     *
     * @param  string|null  $queue  Queue name
     * @return static
     */
    public static function withQueue(?string $queue = null): self
    {
        config(['laravel-mcp.queue.enabled' => true]);

        if ($queue) {
            config(['laravel-mcp.queue.default' => $queue]);
        }

        return new static;
    }

    /**
     * Disable job queue processing.
     *
     * @return static
     */
    public static function withoutQueue(): self
    {
        config(['laravel-mcp.queue.enabled' => false]);

        return new static;
    }

    /**
     * Enable notifications.
     *
     * @param  array  $channels  Notification channels to enable
     * @return static
     */
    public static function withNotifications(array $channels = ['database']): self
    {
        config(['laravel-mcp.notifications.enabled' => true]);
        config(['laravel-mcp.notifications.channels' => $channels]);

        return new static;
    }

    /**
     * Disable notifications.
     *
     * @return static
     */
    public static function withoutNotifications(): self
    {
        config(['laravel-mcp.notifications.enabled' => false]);

        return new static;
    }
}
