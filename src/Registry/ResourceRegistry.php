<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;

/**
 * Registry for MCP resource components.
 *
 * This class manages the registration and retrieval of MCP resources,
 * providing specialized functionality for resource-specific operations.
 */
class ResourceRegistry implements RegistryInterface
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
     * Resource metadata storage.
     */
    protected array $metadata = [];

    /**
     * Registry type identifier.
     */
    protected string $type = 'resources';

    /**
     * Create a new resource registry.
     */
    public function __construct(Container $container, ?ComponentFactory $factory = null)
    {
        $this->container = $container;
        $this->factory = $factory ?? new ComponentFactory($container);
    }

    /**
     * Initialize the resource registry.
     */
    public function initialize(): void
    {
        // Resource registry initialization
        // Any initialization logic can be added here in future
    }

    /**
     * Register a resource with the registry.
     */
    public function register(string $name, $resource, $metadata = []): void
    {
        if ($this->has($name)) {
            throw new RegistrationException("Resource '{$name}' is already registered");
        }

        // Ensure metadata is an array
        $metadata = is_array($metadata) ? $metadata : [];

        // Use factory for lazy loading
        $this->factory->register('resource', $name, $resource, $metadata);
        $this->metadata[$name] = array_merge([
            'name' => $name,
            'type' => 'resource',
            'registered_at' => now()->toISOString(),
            'description' => $metadata['description'] ?? '',
            'uri' => $metadata['uri'] ?? '',
            'mime_type' => $metadata['mime_type'] ?? 'application/json',
            'annotations' => $metadata['annotations'] ?? [],
        ], $metadata);
    }

    /**
     * Unregister a resource from the registry.
     */
    public function unregister(string $name): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        $this->factory->unregister('resource', $name);
        unset($this->metadata[$name]);

        return true;
    }

    /**
     * Check if a resource is registered.
     */
    public function has(string $name): bool
    {
        return $this->factory->has('resource', $name);
    }

    /**
     * Get a registered resource.
     */
    public function get(string $name): mixed
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Resource '{$name}' is not registered");
        }

        return $this->factory->get('resource', $name);
    }

    /**
     * Get all registered resources.
     */
    public function all(): array
    {
        return $this->factory->getAllOfType('resource');
    }

    /**
     * Get all registered resources (alias for all()).
     */
    public function getAll(): array
    {
        return $this->all();
    }

    /**
     * Get all registered resource names.
     */
    public function names(): array
    {
        return array_keys($this->metadata);
    }

    /**
     * Count registered resources.
     */
    public function count(): int
    {
        return count($this->metadata);
    }

    /**
     * Clear all registered resources.
     */
    public function clear(): void
    {
        $this->factory->clearCache('resource');
        $this->metadata = [];
    }

    /**
     * Get metadata for a registered resource.
     */
    public function getMetadata(string $name): array
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Resource '{$name}' is not registered");
        }

        return $this->metadata[$name];
    }

    /**
     * Filter resources by metadata criteria.
     */
    public function filter(array $criteria): array
    {
        $resources = $this->all();

        return array_filter($resources, function ($resource, $name) use ($criteria) {
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
     * Get resources matching a pattern.
     */
    public function search(string $pattern): array
    {
        $resources = $this->all();

        return array_filter($resources, function ($resource, $name) use ($pattern) {
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
     * Get resource templates for MCP protocol.
     */
    public function getResourceTemplates(): array
    {
        $templates = [];

        foreach ($this->metadata as $name => $metadataItem) {
            $templates[] = [
                'uriTemplate' => $metadataItem['uri'] ?? '',
                'name' => $name,
                'description' => $metadataItem['description'] ?? '',
                'mimeType' => $metadataItem['mime_type'] ?? 'application/json',
            ];
        }

        return $templates;
    }

    /**
     * Read resource content.
     */
    public function readResource(string $name, array $parameters = []): array
    {
        $resource = $this->get($name);

        if (is_string($resource) && class_exists($resource)) {
            // Try to instantiate with name parameter first, then without
            try {
                $reflection = new \ReflectionClass($resource);
                $constructor = $reflection->getConstructor();

                if ($constructor && $constructor->getNumberOfRequiredParameters() > 0) {
                    // If constructor requires parameters, try passing the name
                    $resource = new $resource($name);
                } else {
                    // No required parameters, instantiate without arguments
                    $resource = new $resource;
                }
            } catch (\Exception $e) {
                // Fall back to no arguments
                $resource = new $resource;
            }
        }

        if (! is_object($resource) || ! method_exists($resource, 'read')) {
            throw new RegistrationException("Resource '{$name}' does not have a read method");
        }

        return $resource->read($parameters);
    }

    /**
     * Get resource content with metadata.
     */
    public function getResourceContent(string $name, array $parameters = []): array
    {
        $content = $this->readResource($name, $parameters);
        $metadata = $this->getMetadata($name);

        return [
            'contents' => [[
                'uri' => $metadata['uri'] ?? '',
                'mimeType' => $metadata['mime_type'] ?? 'application/json',
                'text' => is_string($content) ? $content : json_encode($content),
            ]],
        ];
    }

    /**
     * List resources for MCP protocol.
     */
    public function listResources(?string $cursor = null): array
    {
        $resources = [];

        foreach ($this->metadata as $name => $metadata) {

            $resources[] = [
                'uri' => $metadata['uri'] ?? '',
                'name' => $name,
                'description' => $metadata['description'] ?? '',
                'mimeType' => $metadata['mime_type'] ?? 'application/json',
                'annotations' => $metadata['annotations'] ?? [],
            ];
        }

        return [
            'resources' => $resources,
        ];
    }

    /**
     * Get resources by URI pattern.
     */
    public function getResourcesByUri(string $uriPattern): array
    {
        $resources = $this->all();

        return array_filter($resources, function ($resource, $name) use ($uriPattern) {
            $metadata = $this->metadata[$name];
            $uri = $metadata['uri'] ?? '';

            return fnmatch($uriPattern, $uri);
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get resources by MIME type.
     */
    public function getResourcesByMimeType(string $mimeType): array
    {
        return $this->filter(['mime_type' => $mimeType]);
    }

    /**
     * Check if resource supports annotations.
     */
    public function hasAnnotations(string $name): bool
    {
        $metadata = $this->getMetadata($name);

        return ! empty($metadata['annotations']);
    }
}
