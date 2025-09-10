<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Facades\Mcp;

/**
 * Route-style registrar for MCP components.
 *
 * This class provides a Laravel-like routing interface for registering
 * MCP components. It supports individual registration and group registration
 * with shared attributes.
 */
class RouteRegistrar
{
    /**
     * The central MCP registry.
     */
    private McpRegistry $registry;

    /**
     * Stack of group attributes for nested groups.
     */
    private array $groupStack = [];

    /**
     * Create a new route registrar instance.
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Register a tool.
     *
     * @param  string  $name  Tool name
     * @param  mixed  $handler  Tool handler (class name, closure, or instance)
     * @param  array  $options  Tool options and middleware
     * @return $this
     */
    public function tool(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);

        // Apply group attributes (concatenate prefixes and namespaces from outer to inner)
        $fullPrefix = '';
        $namespace = '';

        foreach ($this->groupStack as $group) {
            // Concatenate prefixes with dot separator
            if (isset($group['prefix'])) {
                $fullPrefix .= ($fullPrefix !== '' ? '.' : '') . $group['prefix'];
            }

            // Concatenate namespaces
            if (isset($group['namespace'])) {
                $namespace .= ($namespace !== '' ? '\\' : '') . $group['namespace'];
            }
        }

        // Apply the accumulated prefix to the name
        if ($fullPrefix !== '') {
            $name = $fullPrefix.$name;
            $options['name'] = $name; // Store full name in options
            $options['prefix'] = $fullPrefix; // Store prefix in options for metadata
        }

        // Apply namespace to handler if it's a string without namespace
        if ($namespace !== '' && is_string($handler) && ! str_contains($handler, '\\')) {
            $handler = rtrim($namespace, '\\').'\\'.$handler;
        }

        // Store namespace in options for metadata (only when there are prefixes or complex grouping)
        if ($namespace !== '' && $fullPrefix !== '') {
            $options['namespace'] = $namespace;
        }

        $this->registry->registerWithType('tool', $name, $handler, $options);

        return $this;
    }

    /**
     * Register a resource.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $handler  Resource handler (class name, closure, or instance)
     * @param  array  $options  Resource options and middleware
     * @return $this
     */
    public function resource(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);

        // Apply group attributes (concatenate prefixes and namespaces from outer to inner)
        $fullPrefix = '';
        $namespace = '';

        foreach ($this->groupStack as $group) {
            // Concatenate prefixes with dot separator
            if (isset($group['prefix'])) {
                $fullPrefix .= ($fullPrefix !== '' ? '.' : '') . $group['prefix'];
            }

            // Concatenate namespaces
            if (isset($group['namespace'])) {
                $namespace .= ($namespace !== '' ? '\\' : '') . $group['namespace'];
            }
        }

        // Apply the accumulated prefix to the name
        if ($fullPrefix !== '') {
            $name = $fullPrefix.$name;
            $options['name'] = $name; // Store full name in options
            $options['prefix'] = $fullPrefix; // Store prefix in options for metadata
        }

        // Apply namespace to handler if it's a string without namespace
        if ($namespace !== '' && is_string($handler) && ! str_contains($handler, '\\')) {
            $handler = rtrim($namespace, '\\').'\\'.$handler;
        }

        // Store namespace in options for metadata (only when there are prefixes or complex grouping)
        if ($namespace !== '' && $fullPrefix !== '') {
            $options['namespace'] = $namespace;
        }

        $this->registry->registerWithType('resource', $name, $handler, $options);

        return $this;
    }

    /**
     * Register a prompt.
     *
     * @param  string  $name  Prompt name
     * @param  mixed  $handler  Prompt handler (class name, closure, or instance)
     * @param  array  $options  Prompt options and middleware
     * @return $this
     */
    public function prompt(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);

        // Apply group attributes (concatenate prefixes and namespaces from outer to inner)
        $fullPrefix = '';
        $namespace = '';

        foreach ($this->groupStack as $group) {
            // Concatenate prefixes with dot separator
            if (isset($group['prefix'])) {
                $fullPrefix .= ($fullPrefix !== '' ? '.' : '') . $group['prefix'];
            }

            // Concatenate namespaces
            if (isset($group['namespace'])) {
                $namespace .= ($namespace !== '' ? '\\' : '') . $group['namespace'];
            }
        }

        // Apply the accumulated prefix to the name
        if ($fullPrefix !== '') {
            $name = $fullPrefix.$name;
            $options['name'] = $name; // Store full name in options
            $options['prefix'] = $fullPrefix; // Store prefix in options for metadata
        }

        // Apply namespace to handler if it's a string without namespace
        if ($namespace !== '' && is_string($handler) && ! str_contains($handler, '\\')) {
            $handler = rtrim($namespace, '\\').'\\'.$handler;
        }

        // Store namespace in options for metadata (only when there are prefixes or complex grouping)
        if ($namespace !== '' && $fullPrefix !== '') {
            $options['namespace'] = $namespace;
        }

        $this->registry->registerWithType('prompt', $name, $handler, $options);

        return $this;
    }

    /**
     * Create a group of component registrations with shared attributes.
     *
     * @param  array  $attributes  Shared attributes for all components in the group
     * @param  \Closure  $callback  Closure containing component registrations
     */
    public function group(array $attributes, \Closure $callback): void
    {
        $this->groupStack[] = $attributes;

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
    }

    /**
     * Create a namespace group for component registrations.
     *
     * @param  string  $namespace  Namespace prefix for component classes
     * @param  \Closure  $callback  Closure containing component registrations
     */
    public function namespace(string $namespace, \Closure $callback): void
    {
        $this->group(['namespace' => $namespace], $callback);
    }

    /**
     * Create a middleware group for component registrations.
     *
     * @param  array|string  $middleware  Middleware to apply to all components in the group
     * @param  \Closure  $callback  Closure containing component registrations
     */
    public function middleware($middleware, \Closure $callback): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->group(['middleware' => $middleware], $callback);
    }

    /**
     * Create a prefix group for component registrations.
     *
     * @param  string  $prefix  Name prefix for all components in the group
     * @param  \Closure  $callback  Closure containing component registrations
     */
    public function prefix(string $prefix, \Closure $callback): void
    {
        $this->group(['prefix' => $prefix], $callback);
    }

    /**
     * Merge group attributes with component options.
     *
     * @param  array  $options  Component options
     * @return array Merged options with group attributes
     */
    private function mergeGroupAttributes(array $options): array
    {
        // Collect all middleware from groups (outer to inner)
        $groupMiddleware = [];

        foreach ($this->groupStack as $group) {
            // Note: namespace and prefix are now handled in the individual methods (tool, resource, prompt)
            // to properly apply them to the name and handler parameters

            // Collect middleware from groups (outer to inner order)
            if (isset($group['middleware'])) {
                $middleware = is_array($group['middleware']) ? $group['middleware'] : [$group['middleware']];
                $groupMiddleware = array_merge($groupMiddleware, $middleware);
            }

            // Merge other attributes (first group wins for non-array attributes)
            foreach ($group as $key => $value) {
                if (! in_array($key, ['namespace', 'prefix', 'middleware']) && ! isset($options[$key])) {
                    $options[$key] = $value;
                }
            }
        }

        // Apply middleware with correct order: group middleware (outer to inner) then component middleware
        if (! empty($groupMiddleware)) {
            $componentMiddleware = $options['middleware'] ?? [];
            if (! is_array($componentMiddleware)) {
                $componentMiddleware = [$componentMiddleware];
            }
            // Group middleware comes first (outer to inner), then component middleware
            $options['middleware'] = array_unique(array_merge($groupMiddleware, $componentMiddleware));
        }
        // If there's no group middleware and the component has middleware, leave it as-is (don't convert to array)

        return $options;
    }

    /**
     * Load component routes from a file.
     *
     * @param  string  $path  Path to the routes file
     */
    public function loadRoutesFrom(string $path): void
    {
        if (file_exists($path)) {
            $registrar = $this;
            require $path;
        }
    }

    /**
     * Get the underlying registry instance.
     */
    public function getRegistry(): McpRegistry
    {
        return $this->registry;
    }

    /**
     * Check if a component is registered.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     */
    public function has(string $type, string $name): bool
    {
        return match ($type) {
            'tool' => $this->registry->hasTool($name),
            'resource' => $this->registry->hasResource($name),
            'prompt' => $this->registry->hasPrompt($name),
            default => false
        };
    }

    /**
     * Get a registered component.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     * @return mixed|null
     */
    public function get(string $type, string $name)
    {
        return match ($type) {
            'tool' => $this->registry->getTool($name),
            'resource' => $this->registry->getResource($name),
            'prompt' => $this->registry->getPrompt($name),
            default => null
        };
    }

    /**
     * List all registered components of a type.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     */
    public function list(string $type): array
    {
        return match ($type) {
            'tool' => $this->registry->listTools(),
            'resource' => $this->registry->listResources(),
            'prompt' => $this->registry->listPrompts(),
            default => []
        };
    }

    /**
     * Batch register components of a specific type.
     *
     * @param  string  $type  Component type ('tool', 'resource', or 'prompt')
     * @param  array  $components  Array of name => handler pairs
     * @param  array  $options  Shared options for all components
     * @return $this
     */
    public function batch(string $type, array $components, array $options = []): self
    {
        foreach ($components as $name => $handler) {
            match ($type) {
                'tool' => $this->tool($name, $handler, $options),
                'resource' => $this->resource($name, $handler, $options),
                'prompt' => $this->prompt($name, $handler, $options),
                default => throw new \InvalidArgumentException("Invalid component type: {$type}")
            };
        }

        return $this;
    }
}
