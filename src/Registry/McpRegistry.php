<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;

/**
 * Main MCP component registry.
 *
 * This class serves as the central registry for all MCP components,
 * managing tools, resources, and prompts through type-specific registries.
 */
class McpRegistry
{
    private ToolRegistry $toolRegistry;

    private ResourceRegistry $resourceRegistry;

    private PromptRegistry $promptRegistry;

    private array $registered = [];

    private bool $initialized = false;

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
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry
    ) {
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;
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

    public function unregister(string $type, string $name): bool
    {
        $success = match ($type) {
            'tool' => $this->toolRegistry->unregister($name),
            'resource' => $this->resourceRegistry->unregister($name),
            'prompt' => $this->promptRegistry->unregister($name),
            default => false
        };

        if ($success) {
            unset($this->registered[$type][$name]);
        }

        return $success;
    }

    public function count(?string $type = null): int
    {
        if ($type === null) {
            // Count from both internal registry and type-specific registries
            $internalCount = count($this->registered['tool'] ?? []) +
                           count($this->registered['resource'] ?? []) +
                           count($this->registered['prompt'] ?? []);

            // If we have items in internal registry, use that count
            if ($internalCount > 0) {
                return $internalCount;
            }

            // Otherwise fall back to type-specific registries
            return $this->toolRegistry->count() +
                   $this->resourceRegistry->count() +
                   $this->promptRegistry->count();
        }

        // For specific type, check internal registry first
        if (isset($this->registered[$type])) {
            $count = count($this->registered[$type]);
            if ($count > 0) {
                return $count;
            }
        }

        return match ($type) {
            'tool' => $this->toolRegistry->count(),
            'resource' => $this->resourceRegistry->count(),
            'prompt' => $this->promptRegistry->count(),
            default => 0
        };
    }

    public function getTypes(): array
    {
        return ['tool', 'resource', 'prompt'];
    }

    public function getMetadata(string $type, string $name): array
    {
        if (! isset($this->registered[$type][$name])) {
            return [];
        }

        $metadata = $this->registered[$type][$name]['options'] ?? [];

        // Include registered_at timestamp if available
        if (isset($this->registered[$type][$name]['registered_at'])) {
            $metadata['registered_at'] = $this->registered[$type][$name]['registered_at'];
        }

        return $metadata;
    }

    public function has(string $type, string $name): bool
    {
        // First check if registered in the internal registry
        if (isset($this->registered[$type][$name])) {
            return true;
        }

        // Then check type-specific registries
        return match ($type) {
            'tool' => $this->toolRegistry->has($name),
            'resource' => $this->resourceRegistry->has($name),
            'prompt' => $this->promptRegistry->has($name),
            default => false
        };
    }

    public function get(string $type, string $name): mixed
    {
        return match ($type) {
            'tool' => $this->toolRegistry->get($name),
            'resource' => $this->resourceRegistry->get($name),
            'prompt' => $this->promptRegistry->get($name),
            default => null
        };
    }

    public function getAll(string $type): array
    {
        return match ($type) {
            'tool' => $this->toolRegistry->getAll(),
            'resource' => $this->resourceRegistry->getAll(),
            'prompt' => $this->promptRegistry->getAll(),
            default => []
        };
    }

    /**
     * Clear all registered components.
     */
    public function clear(): void
    {
        $this->registered = [];

        // Clear type-specific registries
        foreach ($this->typeRegistries as $registry) {
            $registry->clear();
        }
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

    private function validateRegistration(string $type, string $name, $handler): void
    {
        if (empty($name)) {
            throw new RegistrationException('Component name cannot be empty');
        }

        if ($this->has($type, $name)) {
            throw new RegistrationException("Component '{$name}' of type '{$type}' is already registered");
        }

        $this->validateHandler($type, $handler);
    }

    private function validateHandler(string $type, $handler): void
    {
        if (is_string($handler) && ! class_exists($handler)) {
            throw new RegistrationException("Handler class '{$handler}' does not exist");
        }

        if (is_string($handler)) {
            $requiredInterface = match ($type) {
                'tool' => McpTool::class,
                'resource' => McpResource::class,
                'prompt' => McpPrompt::class,
                default => null
            };

            if ($requiredInterface && ! is_subclass_of($handler, $requiredInterface)) {
                throw new RegistrationException("Handler must extend {$requiredInterface}");
            }
        }
    }

    // Backward compatibility and facade support methods

    /**
     * Backward compatibility: Get all components (legacy method).
     */
    public function all(): array
    {
        return array_merge(
            $this->getTools(),
            $this->getResources(),
            $this->getPrompts()
        );
    }

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
     * Register a tool (backward compatibility).
     */
    public function registerTool(string $name, $tool, array $metadata = []): void
    {
        $this->register('tool', $name, $tool, $metadata);
    }

    /**
     * Register a resource (backward compatibility).
     */
    public function registerResource(string $name, $resource, array $metadata = []): void
    {
        $this->register('resource', $name, $resource, $metadata);
    }

    /**
     * Register a prompt (backward compatibility).
     */
    public function registerPrompt(string $name, $prompt, array $metadata = []): void
    {
        $this->register('prompt', $name, $prompt, $metadata);
    }

    /**
     * Unregister a tool (backward compatibility).
     */
    public function unregisterTool(string $name): bool
    {
        return $this->unregister('tool', $name);
    }

    /**
     * Unregister a resource (backward compatibility).
     */
    public function unregisterResource(string $name): bool
    {
        return $this->unregister('resource', $name);
    }

    /**
     * Unregister a prompt (backward compatibility).
     */
    public function unregisterPrompt(string $name): bool
    {
        return $this->unregister('prompt', $name);
    }

    /**
     * List all tools (backward compatibility).
     */
    public function listTools(): array
    {
        return $this->getTools();
    }

    /**
     * List all resources (backward compatibility).
     */
    public function listResources(): array
    {
        return $this->getResources();
    }

    /**
     * List all prompts (backward compatibility).
     */
    public function listPrompts(): array
    {
        return $this->getPrompts();
    }

    public function getTool(string $name): ?McpTool
    {
        $tool = $this->toolRegistry->get($name);

        if (is_string($tool) && class_exists($tool)) {
            return $this->instantiate($tool, $name);
        }

        return $tool instanceof McpTool ? $tool : null;
    }

    public function getResource(string $name): ?McpResource
    {
        $resource = $this->resourceRegistry->get($name);

        if (is_string($resource) && class_exists($resource)) {
            return $this->instantiate($resource, $name);
        }

        return $resource instanceof McpResource ? $resource : null;
    }

    public function getPrompt(string $name): ?McpPrompt
    {
        $prompt = $this->promptRegistry->get($name);

        if (is_string($prompt) && class_exists($prompt)) {
            return $this->instantiate($prompt, $name);
        }

        return $prompt instanceof McpPrompt ? $prompt : null;
    }

    /**
     * Instantiate a class with optional name parameter.
     */
    private function instantiate(string $className, ?string $name = null): mixed
    {
        try {
            $reflection = new \ReflectionClass($className);
            $constructor = $reflection->getConstructor();

            if ($constructor && $constructor->getNumberOfRequiredParameters() > 0 && $name) {
                return new $className($name);
            } else {
                return new $className;
            }
        } catch (\Exception $e) {
            try {
                return new $className;
            } catch (\Exception $fallbackException) {
                throw new RegistrationException("Unable to instantiate {$className}: {$fallbackException->getMessage()}");
            }
        }
    }

    /**
     * Check if a tool exists (backward compatibility).
     */
    public function hasTool(string $name): bool
    {
        return $this->has('tool', $name);
    }

    /**
     * Check if a resource exists (backward compatibility).
     */
    public function hasResource(string $name): bool
    {
        return $this->has('resource', $name);
    }

    /**
     * Check if a prompt exists (backward compatibility).
     */
    public function hasPrompt(string $name): bool
    {
        return $this->has('prompt', $name);
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

    public function getTools(): array
    {
        return $this->toolRegistry->getAll();
    }

    public function getResources(): array
    {
        return $this->resourceRegistry->getAll();
    }

    public function getPrompts(): array
    {
        return $this->promptRegistry->getAll();
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
