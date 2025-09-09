<?php

namespace JTD\LaravelMCP\Registry;

/**
 * Route-style registration for MCP components.
 *
 * This class provides a Laravel route-style API for registering MCP components,
 * allowing for fluent method chaining and group registration with shared attributes.
 */
class RouteRegistrar
{
    private McpRegistry $registry;

    private array $groupStack = [];

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Register a tool component.
     *
     * @param  string  $name  The tool name/identifier
     * @param  mixed  $handler  The tool handler (class name, instance, or callback)
     * @param  array  $options  Optional registration options
     * @return self For method chaining
     */
    public function tool(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('tool', $name, $handler, $options);

        return $this;
    }

    /**
     * Register a resource component.
     *
     * @param  string  $name  The resource name/identifier
     * @param  mixed  $handler  The resource handler (class name, instance, or callback)
     * @param  array  $options  Optional registration options
     * @return self For method chaining
     */
    public function resource(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('resource', $name, $handler, $options);

        return $this;
    }

    /**
     * Register a prompt component.
     *
     * @param  string  $name  The prompt name/identifier
     * @param  mixed  $handler  The prompt handler (class name, instance, or callback)
     * @param  array  $options  Optional registration options
     * @return self For method chaining
     */
    public function prompt(string $name, $handler, array $options = []): self
    {
        $options = $this->mergeGroupAttributes($options);
        $this->registry->register('prompt', $name, $handler, $options);

        return $this;
    }

    /**
     * Register a group of components with shared attributes.
     *
     * @param  array  $attributes  Shared attributes for all components in the group
     * @param  \Closure  $callback  Callback function that registers components
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
     * Get the underlying registry instance.
     */
    public function getRegistry(): McpRegistry
    {
        return $this->registry;
    }

    /**
     * Register multiple components of the same type.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  array  $components  Associative array of name => handler pairs
     * @param  array  $commonOptions  Common options to apply to all components
     * @return self For method chaining
     */
    public function batch(string $type, array $components, array $commonOptions = []): self
    {
        foreach ($components as $name => $handler) {
            $options = array_merge($commonOptions, $this->mergeGroupAttributes([]));
            $this->registry->register($type, $name, $handler, $options);
        }

        return $this;
    }

    /**
     * Register components using a prefix for naming.
     *
     * @param  string  $prefix  Prefix to add to component names
     * @param  \Closure  $callback  Callback function that registers components
     */
    public function prefix(string $prefix, \Closure $callback): void
    {
        $this->group(['prefix' => $prefix], $callback);
    }

    /**
     * Register components under a namespace.
     *
     * @param  string  $namespace  Namespace for component handlers
     * @param  \Closure  $callback  Callback function that registers components
     */
    public function namespace(string $namespace, \Closure $callback): void
    {
        $this->group(['namespace' => $namespace], $callback);
    }

    /**
     * Register components with middleware.
     *
     * @param  array|string  $middleware  Middleware to apply
     * @param  \Closure  $callback  Callback function that registers components
     */
    public function middleware($middleware, \Closure $callback): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->group(['middleware' => $middleware], $callback);
    }

    /**
     * Merge group attributes with component options.
     *
     * @param  array  $options  Component-specific options
     * @return array Merged options with group attributes
     */
    private function mergeGroupAttributes(array $options): array
    {
        // First, build up group attributes
        $groupAttributes = [];
        
        foreach ($this->groupStack as $group) {
            foreach ($group as $key => $value) {
                if ($key === 'middleware') {
                    // Build middleware array from groups
                    if (!isset($groupAttributes['middleware'])) {
                        $groupAttributes['middleware'] = [];
                    }
                    $middleware = is_array($value) ? $value : [$value];
                    $groupAttributes['middleware'] = array_merge($groupAttributes['middleware'], $middleware);
                } elseif ($key === 'prefix') {
                    // Concatenate prefixes
                    if (isset($groupAttributes['prefix'])) {
                        $groupAttributes['prefix'] = $groupAttributes['prefix'].'.'.$value;
                    } else {
                        $groupAttributes['prefix'] = $value;
                    }
                } elseif ($key === 'namespace') {
                    // Concatenate namespaces
                    if (isset($groupAttributes['namespace'])) {
                        $groupAttributes['namespace'] = $groupAttributes['namespace'].'\\'.$value;
                    } else {
                        $groupAttributes['namespace'] = $value;
                    }
                } else {
                    // For other attributes, last group wins
                    $groupAttributes[$key] = $value;
                }
            }
        }
        
        // Now merge with component options
        $merged = [];
        
        // Handle middleware specially - group middleware comes first
        if (isset($groupAttributes['middleware']) && isset($options['middleware'])) {
            $componentMiddleware = is_array($options['middleware']) ? $options['middleware'] : [$options['middleware']];
            $merged['middleware'] = array_merge($groupAttributes['middleware'], $componentMiddleware);
        } elseif (isset($groupAttributes['middleware'])) {
            $merged['middleware'] = $groupAttributes['middleware'];
        } elseif (isset($options['middleware'])) {
            $merged['middleware'] = $options['middleware'];
        }
        
        // Handle prefix specially - group prefix comes first
        if (isset($groupAttributes['prefix']) && isset($options['prefix'])) {
            $merged['prefix'] = $groupAttributes['prefix'].'.'.$options['prefix'];
        } elseif (isset($groupAttributes['prefix'])) {
            $merged['prefix'] = $groupAttributes['prefix'];
        } elseif (isset($options['prefix'])) {
            $merged['prefix'] = $options['prefix'];
        }
        
        // Handle namespace specially - group namespace comes first
        if (isset($groupAttributes['namespace']) && isset($options['namespace'])) {
            $merged['namespace'] = $groupAttributes['namespace'].'\\'.$options['namespace'];
        } elseif (isset($groupAttributes['namespace'])) {
            $merged['namespace'] = $groupAttributes['namespace'];
        } elseif (isset($options['namespace'])) {
            $merged['namespace'] = $options['namespace'];
        }
        
        // Merge other attributes - component options override group attributes
        foreach ($groupAttributes as $key => $value) {
            if (!in_array($key, ['middleware', 'prefix', 'namespace'])) {
                $merged[$key] = $value;
            }
        }
        
        foreach ($options as $key => $value) {
            if (!in_array($key, ['middleware', 'prefix', 'namespace'])) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }
}
