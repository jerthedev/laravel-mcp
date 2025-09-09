<?php

namespace JTD\LaravelMCP\Support\Contracts;

/**
 * Interface for generating client-specific MCP configuration.
 *
 * This interface defines the contract for generating configuration
 * files for different AI clients (Claude Desktop, Claude Code, etc.).
 */
interface ClientGeneratorInterface
{
    /**
     * Generate configuration for the specific client.
     *
     * @param  array  $options  Configuration options
     * @return array Generated configuration array
     */
    public function generate(array $options = []): array;

    /**
     * Get the default server name for this client.
     *
     * @return string Default server name
     */
    public function getDefaultServerName(): string;

    /**
     * Get the default description for this client.
     *
     * @return string Default description
     */
    public function getDefaultDescription(): string;

    /**
     * Validate configuration for this client.
     *
     * @param  array  $config  Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array;

    /**
     * Merge new configuration with existing configuration.
     *
     * @param  array  $newConfig  New configuration
     * @param  array  $existingConfig  Existing configuration
     * @return array Merged configuration
     */
    public function mergeConfig(array $newConfig, array $existingConfig): array;
}
