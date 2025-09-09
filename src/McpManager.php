<?php

namespace JTD\LaravelMCP;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;

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
}
