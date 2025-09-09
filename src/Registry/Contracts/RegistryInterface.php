<?php

namespace JTD\LaravelMCP\Registry\Contracts;

/**
 * Interface for MCP component registry.
 *
 * This interface defines the contract for registries that manage MCP components
 * (tools, resources, prompts). Registries are responsible for storing,
 * retrieving, and organizing components for use by the MCP server.
 */
interface RegistryInterface
{
    /**
     * Register a component with the registry.
     *
     * @param  string  $name  Component name/identifier
     * @param  mixed  $component  The component to register
     * @param  array  $metadata  Optional metadata about the component
     */
    public function register(string $name, $component, array $metadata = []): void;

    /**
     * Unregister a component from the registry.
     *
     * @param  string  $name  Component name/identifier
     * @return bool True if component was unregistered, false if not found
     */
    public function unregister(string $name): bool;

    /**
     * Check if a component is registered.
     *
     * @param  string  $name  Component name/identifier
     */
    public function has(string $name): bool;

    /**
     * Get a registered component.
     *
     * @param  string  $name  Component name/identifier
     * @return mixed The registered component
     *
     * @throws \JTD\LaravelMCP\Exceptions\RegistrationException If component not found
     */
    public function get(string $name);

    /**
     * Get all registered components.
     *
     * @return array Array of all registered components
     */
    public function all(): array;

    /**
     * Get all registered component names.
     *
     * @return array Array of component names
     */
    public function names(): array;

    /**
     * Count registered components.
     *
     * @return int Number of registered components
     */
    public function count(): int;

    /**
     * Clear all registered components.
     */
    public function clear(): void;

    /**
     * Get metadata for a registered component.
     *
     * @param  string  $name  Component name/identifier
     * @return array Component metadata
     */
    public function getMetadata(string $name): array;

    /**
     * Filter components by metadata criteria.
     *
     * @param  array  $criteria  Metadata criteria to filter by
     * @return array Filtered components
     */
    public function filter(array $criteria): array;

    /**
     * Get components matching a pattern.
     *
     * @param  string  $pattern  Pattern to match against component names
     * @return array Matching components
     */
    public function search(string $pattern): array;

    /**
     * Get the registry type identifier.
     *
     * @return string Registry type (tools, resources, prompts)
     */
    public function getType(): string;
}
