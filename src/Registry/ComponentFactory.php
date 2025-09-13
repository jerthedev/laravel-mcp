<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\RegistrationErrorCodes;
use JTD\LaravelMCP\Exceptions\RegistrationException;

/**
 * Factory for lazy instantiation of MCP components.
 *
 * This factory provides lazy loading capabilities for MCP components,
 * ensuring they are only instantiated when actually needed, reducing
 * memory overhead and improving performance.
 */
class ComponentFactory
{
    /**
     * Container instance for dependency injection.
     */
    private Container $container;

    /**
     * Cache of created instances.
     */
    private array $instances = [];

    /**
     * Component definitions for lazy loading.
     */
    private array $definitions = [];

    /**
     * Whether to cache instances after creation.
     */
    private bool $cacheInstances;

    /**
     * Create a new component factory.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->cacheInstances = config('laravel-mcp.cache.instances', true);
    }

    /**
     * Register a component definition for lazy loading.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     * @param  mixed  $handler  Component handler (class name, callable, or instance)
     * @param  array  $options  Component options
     */
    public function register(string $type, string $name, $handler, array $options = []): void
    {
        $key = "{$type}:{$name}";

        // If handler is already an instance and we're caching, store it
        if (is_object($handler) && $this->isValidInstance($type, $handler)) {
            if ($this->cacheInstances) {
                $this->instances[$key] = $handler;
            }
            $this->definitions[$key] = [
                'type' => $type,
                'name' => $name,
                'handler' => $handler,
                'options' => $options,
                'is_instance' => true,
            ];
        } else {
            // Store definition for lazy loading
            $this->definitions[$key] = [
                'type' => $type,
                'name' => $name,
                'handler' => $handler,
                'options' => $options,
                'is_instance' => false,
            ];
        }
    }

    /**
     * Get a component instance, creating it lazily if needed.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @return mixed|null Component instance or null if not found
     */
    public function get(string $type, string $name): mixed
    {
        $key = "{$type}:{$name}";

        // Return cached instance if available
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // Check if definition exists
        if (! isset($this->definitions[$key])) {
            return null;
        }

        $definition = $this->definitions[$key];

        // If it's already an instance, return it
        if ($definition['is_instance']) {
            return $definition['handler'];
        }

        // Create instance lazily
        $instance = $this->createInstance($definition);

        // Cache if enabled
        if ($this->cacheInstances && $instance !== null) {
            $this->instances[$key] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a component is registered.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @return bool True if component is registered
     */
    public function has(string $type, string $name): bool
    {
        $key = "{$type}:{$name}";

        return isset($this->definitions[$key]);
    }

    /**
     * Get all components of a specific type.
     *
     * @param  string  $type  Component type
     * @return array Array of component instances
     */
    public function getAllOfType(string $type): array
    {
        $components = [];

        foreach ($this->definitions as $key => $definition) {
            if ($definition['type'] === $type) {
                $name = $definition['name'];
                $instance = $this->get($type, $name);
                if ($instance !== null) {
                    $components[$name] = $instance;
                }
            }
        }

        return $components;
    }

    /**
     * Clear cached instances.
     *
     * @param  string|null  $type  Optional type to clear, null clears all
     */
    public function clearCache(?string $type = null): void
    {
        if ($type === null) {
            $this->instances = [];
        } else {
            foreach ($this->instances as $key => $instance) {
                if (str_starts_with($key, "{$type}:")) {
                    unset($this->instances[$key]);
                }
            }
        }
    }

    /**
     * Remove a component definition.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @return bool True if removed, false if not found
     */
    public function unregister(string $type, string $name): bool
    {
        $key = "{$type}:{$name}";

        if (isset($this->definitions[$key])) {
            unset($this->definitions[$key]);
            unset($this->instances[$key]);

            return true;
        }

        return false;
    }

    /**
     * Get memory usage statistics.
     *
     * @return array Memory usage information
     */
    public function getMemoryStats(): array
    {
        $stats = [
            'total_definitions' => count($this->definitions),
            'cached_instances' => count($this->instances),
            'lazy_definitions' => 0,
            'immediate_instances' => 0,
        ];

        foreach ($this->definitions as $definition) {
            if ($definition['is_instance']) {
                $stats['immediate_instances']++;
            } else {
                $stats['lazy_definitions']++;
            }
        }

        return $stats;
    }

    /**
     * Create an instance from a definition.
     *
     * @param  array  $definition  Component definition
     * @return mixed Component instance
     *
     * @throws RegistrationException If instantiation fails
     */
    private function createInstance(array $definition): mixed
    {
        $handler = $definition['handler'];
        $type = $definition['type'];
        $name = $definition['name'];

        try {
            // String class name
            if (is_string($handler)) {
                if (! class_exists($handler)) {
                    throw RegistrationException::classNotFound($handler, $type);
                }

                // Use container for dependency injection
                $instance = $this->container->make($handler, [
                    'name' => $name,
                    'options' => $definition['options'],
                ]);

                if (! $this->isValidInstance($type, $instance)) {
                    throw RegistrationException::invalidComponentClass(
                        $handler,
                        $type,
                        $this->getRequiredClass($type)
                    );
                }

                return $instance;
            }

            // Callable factory
            if (is_callable($handler)) {
                $instance = $handler($this->container, $name, $definition['options']);

                if (! $this->isValidInstance($type, $instance)) {
                    throw new RegistrationException(
                        "Callable factory did not return valid {$type} instance",
                        RegistrationErrorCodes::INVALID_HANDLER,
                        $type,
                        $name
                    );
                }

                return $instance;
            }

            // Already an instance
            if (is_object($handler)) {
                if (! $this->isValidInstance($type, $handler)) {
                    throw RegistrationException::invalidComponentClass(
                        get_class($handler),
                        $type,
                        $this->getRequiredClass($type)
                    );
                }

                return $handler;
            }

            throw new RegistrationException(
                'Invalid handler type for component',
                RegistrationErrorCodes::INVALID_HANDLER,
                $type,
                $name
            );

        } catch (RegistrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw RegistrationException::instantiationFailure(
                is_string($handler) ? $handler : get_class($handler),
                $type,
                $e->getMessage()
            );
        }
    }

    /**
     * Check if an instance is valid for the given type.
     *
     * @param  string  $type  Component type
     * @param  mixed  $instance  Instance to check
     * @return bool True if valid
     */
    private function isValidInstance(string $type, $instance): bool
    {
        if (! is_object($instance)) {
            return false;
        }

        $requiredClass = $this->getRequiredClass($type);

        return $requiredClass && $instance instanceof $requiredClass;
    }

    /**
     * Get the required base class for a component type.
     *
     * @param  string  $type  Component type
     * @return string|null Required class name or null
     */
    private function getRequiredClass(string $type): ?string
    {
        return match ($type) {
            'tool' => McpTool::class,
            'resource' => McpResource::class,
            'prompt' => McpPrompt::class,
            default => null
        };
    }

    /**
     * Warm the cache by instantiating specified components.
     *
     * @param  array  $components  Array of [type => [names]] to warm
     */
    public function warmCache(array $components): void
    {
        foreach ($components as $type => $names) {
            foreach ($names as $name) {
                $this->get($type, $name);
            }
        }
    }

    /**
     * Enable or disable instance caching.
     *
     * @param  bool  $enable  Whether to enable caching
     */
    public function setCacheEnabled(bool $enable): void
    {
        $this->cacheInstances = $enable;

        if (! $enable) {
            $this->clearCache();
        }
    }
}
