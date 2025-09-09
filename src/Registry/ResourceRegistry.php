<?php

namespace JTD\LaravelMCP\Registry;

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
     * Registered resources storage.
     */
    protected array $resources = [];

    /**
     * Resource metadata storage.
     */
    protected array $metadata = [];

    /**
     * Registry type identifier.
     */
    protected string $type = 'resources';

    /**
     * Register a resource with the registry.
     */
    public function register(string $name, $resource, array $metadata = []): void
    {
        if ($this->has($name)) {
            throw new RegistrationException("Resource '{$name}' is already registered");
        }

        $this->resources[$name] = $resource;
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

        unset($this->resources[$name], $this->metadata[$name]);

        return true;
    }

    /**
     * Check if a resource is registered.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->resources);
    }

    /**
     * Get a registered resource.
     */
    public function get(string $name)
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Resource '{$name}' is not registered");
        }

        return $this->resources[$name];
    }

    /**
     * Get all registered resources.
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * Get all registered resource names.
     */
    public function names(): array
    {
        return array_keys($this->resources);
    }

    /**
     * Count registered resources.
     */
    public function count(): int
    {
        return count($this->resources);
    }

    /**
     * Clear all registered resources.
     */
    public function clear(): void
    {
        $this->resources = [];
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
        return array_filter($this->resources, function ($resource, $name) use ($criteria) {
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
        return array_filter($this->resources, function ($resource, $name) use ($pattern) {
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

        foreach ($this->resources as $name => $resource) {
            $metadata = $this->metadata[$name];

            $templates[] = [
                'uriTemplate' => $metadata['uri'] ?? '',
                'name' => $name,
                'description' => $metadata['description'] ?? '',
                'mimeType' => $metadata['mime_type'] ?? 'application/json',
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
            $resource = new $resource;
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

        foreach ($this->resources as $name => $resource) {
            $metadata = $this->metadata[$name];

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
        return array_filter($this->resources, function ($resource, $name) use ($uriPattern) {
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
