<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;

/**
 * Main MCP component registry.
 *
 * This class serves as the central registry for all MCP components,
 * managing tools, resources, and prompts through type-specific registries.
 */
class McpRegistry implements RegistryInterface
{
    /**
     * Registered components storage.
     */
    protected array $components = [];

    /**
     * Component metadata storage.
     */
    protected array $metadata = [];

    /**
     * Registry type identifier.
     */
    protected string $type = 'main';

    /**
     * Type-specific registries.
     */
    protected array $typeRegistries = [];

    /**
     * Whether the registry has been initialized.
     */
    private bool $initialized = false;

    /**
     * Lock for thread-safe operations.
     */
    private mixed $lock = null;

    /**
     * Create a new MCP registry instance.
     */
    public function __construct(
        protected ToolRegistry $toolRegistry,
        protected ResourceRegistry $resourceRegistry,
        protected PromptRegistry $promptRegistry
    ) {
        $this->typeRegistries = [
            'tool' => $this->toolRegistry,
            'tools' => $this->toolRegistry,
            'resource' => $this->resourceRegistry,
            'resources' => $this->resourceRegistry,
            'prompt' => $this->promptRegistry,
            'prompts' => $this->promptRegistry,
        ];
    }

    /**
     * Register a component with the registry.
     * This method supports both the interface signature and spec signature.
     */
    public function register(string $name, $component, array $metadata = []): void
    {
        // Support spec signature: register(type, name, handler, options)
        if (func_num_args() >= 3 && in_array($name, ['tool', 'resource', 'prompt'])) {
            $type = $name;
            $name = $component;
            $handler = $metadata;
            $options = func_get_arg(3) ?? [];

            $this->registerWithType($type, $name, $handler, $options);

            return;
        }

        // Standard interface signature
        $this->withLock(function () use ($name, $component, $metadata) {
            if ($this->has($name)) {
                throw new RegistrationException("Component '{$name}' is already registered");
            }

            $this->components[$name] = $component;
            $this->metadata[$name] = array_merge([
                'name' => $name,
                'type' => $this->detectComponentType($component),
                'registered_at' => now()->toISOString(),
            ], $metadata);

            // Register with type-specific registry if applicable
            $type = $this->metadata[$name]['type'];
            if (isset($this->typeRegistries[$type])) {
                $this->typeRegistries[$type]->register($name, $component, $metadata);
            }
        });
    }

    /**
     * Register a component with specific type (spec-compliant method).
     */
    public function registerWithType(string $type, string $name, $handler, array $options = []): void
    {
        $this->validateRegistration($type, $name, $handler);

        $this->withLock(function () use ($type, $name, $handler, $options) {
            $registry = match ($type) {
                'tool' => $this->toolRegistry,
                'resource' => $this->resourceRegistry,
                'prompt' => $this->promptRegistry,
                default => throw new RegistrationException("Unknown component type: $type")
            };

            $registry->register($name, $handler, $options);

            $this->components[$name] = $handler;
            $this->metadata[$name] = array_merge([
                'name' => $name,
                'type' => $type.'s',
                'handler' => $handler,
                'options' => $options,
                'registered_at' => time(),
            ], $options);
        });
    }

    /**
     * Unregister a component from the registry.
     */
    public function unregister(string $name): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        $type = $this->metadata[$name]['type'];

        // Unregister from type-specific registry
        if (isset($this->typeRegistries[$type])) {
            $this->typeRegistries[$type]->unregister($name);
        }

        unset($this->components[$name], $this->metadata[$name]);

        return true;
    }

    /**
     * Check if a component is registered.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->components);
    }

    /**
     * Get a registered component.
     */
    public function get(string $name)
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Component '{$name}' is not registered");
        }

        return $this->components[$name];
    }

    /**
     * Get all registered components.
     */
    public function all(): array
    {
        return $this->components;
    }

    /**
     * Get all registered component names.
     */
    public function names(): array
    {
        return array_keys($this->components);
    }

    /**
     * Count registered components.
     */
    public function count(): int
    {
        return count($this->components);
    }

    /**
     * Clear all registered components.
     */
    public function clear(): void
    {
        $this->components = [];
        $this->metadata = [];

        // Clear type-specific registries
        foreach ($this->typeRegistries as $registry) {
            $registry->clear();
        }
    }

    /**
     * Get metadata for a registered component.
     */
    public function getMetadata(string $name): array
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Component '{$name}' is not registered");
        }

        return $this->metadata[$name];
    }

    /**
     * Filter components by metadata criteria.
     */
    public function filter(array $criteria): array
    {
        return array_filter($this->components, function ($component, $name) use ($criteria) {
            $metadata = $this->metadata[$name];

            foreach ($criteria as $key => $value) {
                if (! isset($metadata[$key]) || $metadata[$key] !== $value) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get components matching a pattern.
     */
    public function search(string $pattern): array
    {
        return array_filter($this->components, function ($component, $name) use ($pattern) {
            return fnmatch($pattern, $name);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get the registry type identifier.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get a type-specific registry.
     */
    public function getTypeRegistry(string $type): ?RegistryInterface
    {
        return $this->typeRegistries[$type] ?? null;
    }

    /**
     * Get all type-specific registries.
     */
    public function getTypeRegistries(): array
    {
        return $this->typeRegistries;
    }

    /**
     * Get components by type.
     */
    public function getByType(string $type): array
    {
        return $this->filter(['type' => $type]);
    }

    /**
     * Get component count by type.
     */
    public function getCountByType(): array
    {
        $counts = [];

        foreach ($this->typeRegistries as $type => $registry) {
            $counts[$type] = $registry->count();
        }

        return $counts;
    }

    /**
     * Detect the component type based on the component instance.
     */
    protected function detectComponentType($component): string
    {
        if (is_string($component) && class_exists($component)) {
            $component = new $component;
        }

        if (is_object($component)) {
            $class = get_class($component);

            if (str_contains($class, 'Tool')) {
                return 'tools';
            }

            if (str_contains($class, 'Resource')) {
                return 'resources';
            }

            if (str_contains($class, 'Prompt')) {
                return 'prompts';
            }
        }

        return 'unknown';
    }

    // Facade support methods

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array
    {
        return [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
            'logging' => [],
        ];
    }

    /**
     * Set server capabilities.
     */
    public function setCapabilities(array $capabilities): void
    {
        // Implementation will be added in future tickets
    }

    /**
     * Register a tool.
     */
    public function registerTool(string $name, $tool, array $metadata = []): void
    {
        $this->toolRegistry->register($name, $tool, $metadata);
    }

    /**
     * Register a resource.
     */
    public function registerResource(string $name, $resource, array $metadata = []): void
    {
        $this->resourceRegistry->register($name, $resource, $metadata);
    }

    /**
     * Register a prompt.
     */
    public function registerPrompt(string $name, $prompt, array $metadata = []): void
    {
        $this->promptRegistry->register($name, $prompt, $metadata);
    }

    /**
     * Unregister a tool.
     */
    public function unregisterTool(string $name): bool
    {
        return $this->toolRegistry->unregister($name);
    }

    /**
     * Unregister a resource.
     */
    public function unregisterResource(string $name): bool
    {
        return $this->resourceRegistry->unregister($name);
    }

    /**
     * Unregister a prompt.
     */
    public function unregisterPrompt(string $name): bool
    {
        return $this->promptRegistry->unregister($name);
    }

    /**
     * List all tools.
     */
    public function listTools(): array
    {
        return $this->toolRegistry->all();
    }

    /**
     * List all resources.
     */
    public function listResources(): array
    {
        return $this->resourceRegistry->all();
    }

    /**
     * List all prompts.
     */
    public function listPrompts(): array
    {
        return $this->promptRegistry->all();
    }

    /**
     * Get a tool.
     */
    public function getTool(string $name)
    {
        return $this->toolRegistry->get($name);
    }

    /**
     * Get a resource.
     */
    public function getResource(string $name)
    {
        return $this->resourceRegistry->get($name);
    }

    /**
     * Get a prompt.
     */
    public function getPrompt(string $name)
    {
        return $this->promptRegistry->get($name);
    }

    /**
     * Check if a tool exists.
     */
    public function hasTool(string $name): bool
    {
        return $this->toolRegistry->has($name);
    }

    /**
     * Check if a resource exists.
     */
    public function hasResource(string $name): bool
    {
        return $this->resourceRegistry->has($name);
    }

    /**
     * Check if a prompt exists.
     */
    public function hasPrompt(string $name): bool
    {
        return $this->promptRegistry->has($name);
    }

    /**
     * Discover components in specified paths.
     */
    public function discover(array $paths = []): array
    {
        // Implementation will be added in future tickets
        return [];
    }

    /**
     * Start the MCP server.
     */
    public function startServer(array $config = []): void
    {
        // Implementation will be added in future tickets
    }

    /**
     * Stop the MCP server.
     */
    public function stopServer(): void
    {
        // Implementation will be added in future tickets
    }

    /**
     * Check if server is running.
     */
    public function isServerRunning(): bool
    {
        // Implementation will be added in future tickets
        return false;
    }

    /**
     * Get server information.
     */
    public function getServerInfo(): array
    {
        // Implementation will be added in future tickets
        return [];
    }

    /**
     * Get server statistics.
     */
    public function getServerStats(): array
    {
        // Implementation will be added in future tickets
        return [];
    }

    /**
     * Enable debug mode.
     */
    public function enableDebugMode(): void
    {
        // Implementation will be added in future tickets
    }

    /**
     * Disable debug mode.
     */
    public function disableDebugMode(): void
    {
        // Implementation will be added in future tickets
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebugMode(): bool
    {
        // Implementation will be added in future tickets
        return false;
    }

    /**
     * Initialize the registry.
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->withLock(function () {
            // Initialize type-specific registries
            foreach ($this->typeRegistries as $registry) {
                if (method_exists($registry, 'initialize')) {
                    $registry->initialize();
                }
            }

            $this->initialized = true;
        });
    }

    /**
     * Validate registration parameters.
     */
    private function validateRegistration(string $type, string $name, $handler): void
    {
        if (empty($name)) {
            throw new RegistrationException('Component name cannot be empty');
        }

        if ($this->has($name)) {
            throw new RegistrationException("Component '{$name}' of type '{$type}' is already registered");
        }

        $this->validateHandler($type, $handler);
    }

    /**
     * Validate handler for a component type.
     */
    private function validateHandler(string $type, $handler): void
    {
        // Skip validation if disabled in configuration
        if (! config('laravel-mcp.validation.validate_handlers', true)) {
            return;
        }

        if (is_string($handler) && ! class_exists($handler)) {
            throw new RegistrationException("Handler class '{$handler}' does not exist");
        }

        if (is_string($handler)) {
            $requiredInterface = match ($type) {
                'tool' => \JTD\LaravelMCP\Abstracts\McpTool::class,
                'resource' => \JTD\LaravelMCP\Abstracts\McpResource::class,
                'prompt' => \JTD\LaravelMCP\Abstracts\McpPrompt::class,
                default => null
            };

            if ($requiredInterface && ! is_subclass_of($handler, $requiredInterface)) {
                throw new RegistrationException("Handler must extend {$requiredInterface}");
            }
        }
    }

    /**
     * Execute a closure with thread-safe locking.
     */
    private function withLock(callable $callback): mixed
    {
        if ($this->lock === null) {
            $this->lock = new \stdClass;
        }

        // Use synchronized block for thread-safety
        // In production, this could use a proper mutex or semaphore
        $lockId = spl_object_id($this->lock);

        try {
            // In a real implementation, acquire lock here
            return $callback();
        } finally {
            // Release lock here
        }
    }

    /**
     * Get all tools.
     */
    public function getTools(): array
    {
        return $this->toolRegistry->all();
    }

    /**
     * Get all resources.
     */
    public function getResources(): array
    {
        return $this->resourceRegistry->all();
    }

    /**
     * Get all prompts.
     */
    public function getPrompts(): array
    {
        return $this->promptRegistry->all();
    }

    /**
     * Create a group of component registrations with shared attributes.
     * This method delegates to RouteRegistrar for facade compatibility.
     *
     * @param  array  $attributes  Shared attributes for all components in the group
     * @param  \Closure  $callback  Closure containing component registrations
     */
    public function group(array $attributes, \Closure $callback): void
    {
        // Delegate to RouteRegistrar for route-style registration
        $registrar = app(RouteRegistrar::class);
        $registrar->group($attributes, $callback);
    }
}
