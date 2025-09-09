<?php

namespace JTD\LaravelMCP\Registry\Contracts;

/**
 * Interface for MCP component discovery.
 *
 * This interface defines the contract for component discovery services that
 * automatically find and register MCP components (tools, resources, prompts)
 * in Laravel applications. Discovery services scan directories, analyze files,
 * and register valid components with appropriate registries.
 */
interface DiscoveryInterface
{
    /**
     * Discover components in the specified paths.
     *
     * @param  array  $paths  Array of paths to scan for components
     * @return array Discovered components with their metadata
     */
    public function discover(array $paths): array;

    /**
     * Discover components of a specific type.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  array  $paths  Array of paths to scan
     * @return array Discovered components of the specified type
     */
    public function discoverType(string $type, array $paths): array;

    /**
     * Check if a file contains a valid component.
     *
     * @param  string  $filePath  Path to the file to check
     * @param  string  $type  Expected component type
     */
    public function isValidComponent(string $filePath, string $type): bool;

    /**
     * Extract component metadata from a file.
     *
     * @param  string  $filePath  Path to the component file
     * @return array Component metadata (name, description, parameters, etc.)
     */
    public function extractMetadata(string $filePath): array;

    /**
     * Get the class name from a file path.
     *
     * @param  string  $filePath  Path to the file
     * @return string|null Full class name or null if not found
     */
    public function getClassFromFile(string $filePath): ?string;

    /**
     * Validate that a class is a valid MCP component.
     *
     * @param  string  $className  Full class name
     * @param  string  $type  Expected component type
     */
    public function isValidComponentClass(string $className, string $type): bool;

    /**
     * Get supported component types for discovery.
     *
     * @return array Array of supported component types
     */
    public function getSupportedTypes(): array;

    /**
     * Set discovery configuration.
     *
     * @param  array  $config  Discovery configuration
     */
    public function setConfig(array $config): void;

    /**
     * Get current discovery configuration.
     *
     * @return array Current configuration
     */
    public function getConfig(): array;

    /**
     * Add a discovery filter/rule.
     *
     * @param  callable  $filter  Filter function
     */
    public function addFilter(callable $filter): void;

    /**
     * Get all active discovery filters.
     *
     * @return array Array of filter functions
     */
    public function getFilters(): array;
}
