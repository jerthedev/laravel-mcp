<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;

/**
 * Registry for MCP tool components.
 *
 * This class manages the registration and retrieval of MCP tools,
 * providing specialized functionality for tool-specific operations.
 */
class ToolRegistry implements RegistryInterface
{
    /**
     * Component factory for lazy loading.
     */
    protected ComponentFactory $factory;

    /**
     * Container instance.
     */
    protected Container $container;

    /**
     * Tool metadata storage.
     */
    protected array $metadata = [];

    /**
     * Registry type identifier.
     */
    protected string $type = 'tools';

    /**
     * Create a new tool registry.
     */
    public function __construct(Container $container, ?ComponentFactory $factory = null)
    {
        $this->container = $container;
        $this->factory = $factory ?? new ComponentFactory($container);
    }

    /**
     * Initialize the tool registry.
     */
    public function initialize(): void
    {
        // Tool registry initialization
        // Any initialization logic can be added here in future
    }

    /**
     * Register a tool with the registry.
     */
    public function register(string $name, $tool, $metadata = []): void
    {
        if ($this->has($name)) {
            throw new RegistrationException("Tool '{$name}' is already registered");
        }

        // Ensure metadata is an array
        $metadata = is_array($metadata) ? $metadata : [];

        // Use factory for lazy loading
        $this->factory->register('tool', $name, $tool, $metadata);
        $this->metadata[$name] = array_merge([
            'name' => $name,
            'type' => 'tool',
            'registered_at' => now()->toISOString(),
            'description' => $metadata['description'] ?? '',
            'parameters' => $metadata['parameters'] ?? [],
            'input_schema' => $metadata['input_schema'] ?? null,
        ], $metadata);
    }

    /**
     * Unregister a tool from the registry.
     */
    public function unregister(string $name): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        $this->factory->unregister('tool', $name);
        unset($this->metadata[$name]);

        return true;
    }

    /**
     * Check if a tool is registered.
     */
    public function has(string $name): bool
    {
        return $this->factory->has('tool', $name);
    }

    /**
     * Get a registered tool.
     */
    public function get(string $name): mixed
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Tool '{$name}' is not registered");
        }

        return $this->factory->get('tool', $name);
    }

    /**
     * Get all registered tools.
     */
    public function all(): array
    {
        return $this->factory->getAllOfType('tool');
    }

    /**
     * Get all registered tools (alias for all()).
     */
    public function getAll(): array
    {
        return $this->all();
    }

    /**
     * Get all registered tool names.
     */
    public function names(): array
    {
        return array_keys($this->metadata);
    }

    /**
     * Count registered tools.
     */
    public function count(): int
    {
        return count($this->metadata);
    }

    /**
     * Clear all registered tools.
     */
    public function clear(): void
    {
        $this->factory->clearCache('tool');
        $this->metadata = [];
    }

    /**
     * Get metadata for a registered tool.
     */
    public function getMetadata(string $name): array
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Tool '{$name}' is not registered");
        }

        return $this->metadata[$name];
    }

    /**
     * Set metadata for a registered tool.
     */
    public function setMetadata(string $name, array $metadata): void
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Tool '{$name}' is not registered");
        }

        $this->metadata[$name] = array_merge($this->metadata[$name], $metadata);
    }

    /**
     * Filter tools by metadata criteria.
     */
    public function filter(array $criteria): array
    {
        $tools = $this->factory->getAllOfType('tool');
        return array_filter($tools, function ($tool, $name) use ($criteria) {
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
     * Get tools matching a pattern.
     */
    public function search(string $pattern): array
    {
        $tools = $this->factory->getAllOfType('tool');
        return array_filter($tools, function ($tool, $name) use ($pattern) {
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
     * Get tool definitions for MCP protocol.
     */
    public function getToolDefinitions(): array
    {
        $definitions = [];
        $tools = $this->factory->getAllOfType('tool');

        foreach ($tools as $name => $tool) {
            $metadata = $this->metadata[$name];

            $definitions[] = [
                'name' => $name,
                'description' => $metadata['description'] ?? '',
                'inputSchema' => $metadata['input_schema'] ?? [
                    'type' => 'object',
                    'properties' => [],
                ],
            ];
        }

        return $definitions;
    }

    /**
     * Execute a tool with given parameters.
     */
    public function executeTool(string $name, array $parameters = []): array
    {
        $tool = $this->get($name);

        if (is_string($tool) && class_exists($tool)) {
            // Try to instantiate with name parameter first, then without
            try {
                $reflection = new \ReflectionClass($tool);
                $constructor = $reflection->getConstructor();

                if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                    // If constructor requires parameters, try passing the name
                    $tool = new $tool($name);
                } else {
                    // No required parameters, instantiate without arguments
                    $tool = new $tool;
                }
            } catch (\Exception $e) {
                // Fall back to no arguments
                $tool = new $tool;
            }
        }

        if (! is_object($tool) || ! method_exists($tool, 'execute')) {
            throw new RegistrationException("Tool '{$name}' does not have an execute method");
        }

        return $tool->execute($parameters);
    }

    /**
     * Validate tool parameters against schema.
     */
    public function validateParameters(string $name, array $parameters): bool
    {
        $metadata = $this->getMetadata($name);
        $schema = $metadata['input_schema'] ?? null;

        if (! $schema) {
            return true; // No schema defined, allow any parameters
        }

        // Basic validation - in a full implementation, you'd use a JSON schema validator
        $requiredProperties = $schema['required'] ?? [];

        foreach ($requiredProperties as $property) {
            if (! array_key_exists($property, $parameters)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get tools that match specific capability requirements.
     */
    public function getToolsByCapability(array $capabilities): array
    {
        $tools = $this->factory->getAllOfType('tool');
        return array_filter($tools, function ($tool, $name) use ($capabilities) {
            $metadata = $this->metadata[$name];
            $toolCapabilities = $metadata['capabilities'] ?? [];

            return ! empty(array_intersect($capabilities, $toolCapabilities));
        }, ARRAY_FILTER_USE_BOTH);
    }
}
