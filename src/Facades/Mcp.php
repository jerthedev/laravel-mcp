<?php

namespace JTD\LaravelMCP\Facades;

use Illuminate\Support\Facades\Facade;

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
}
