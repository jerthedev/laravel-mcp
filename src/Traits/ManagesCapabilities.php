<?php

namespace JTD\LaravelMCP\Traits;

/**
 * Trait for managing MCP server capabilities.
 *
 * This trait provides functionality for managing and negotiating MCP server
 * capabilities with clients. It handles capability discovery, validation,
 * and configuration for tools, resources, prompts, and other MCP features.
 */
trait ManagesCapabilities
{
    /**
     * Default server capabilities.
     */
    protected array $defaultCapabilities = [
        'tools' => [],
        'resources' => [],
        'prompts' => [],
        'logging' => [],
        'experimental' => [],
    ];

    /**
     * Current server capabilities.
     */
    protected array $capabilities = [];

    /**
     * Initialize capabilities with defaults.
     */
    protected function initializeCapabilities(): void
    {
        $this->capabilities = array_merge($this->defaultCapabilities, $this->capabilities);
    }

    /**
     * Get all server capabilities.
     */
    public function getCapabilities(): array
    {
        if (empty($this->capabilities)) {
            $this->initializeCapabilities();
        }

        return $this->capabilities;
    }

    /**
     * Set server capabilities.
     *
     * @param  array  $capabilities  Capabilities configuration
     */
    public function setCapabilities(array $capabilities): void
    {
        $this->capabilities = array_merge($this->defaultCapabilities, $capabilities);
    }

    /**
     * Add a capability.
     *
     * @param  string  $category  Capability category (tools, resources, prompts, etc.)
     * @param  string  $name  Capability name
     * @param  array  $config  Capability configuration
     */
    public function addCapability(string $category, string $name, array $config = []): void
    {
        if (! isset($this->capabilities[$category])) {
            $this->capabilities[$category] = [];
        }

        $this->capabilities[$category][$name] = $config;
    }

    /**
     * Remove a capability.
     *
     * @param  string  $category  Capability category
     * @param  string  $name  Capability name
     * @return bool True if capability was removed
     */
    public function removeCapability(string $category, string $name): bool
    {
        if (isset($this->capabilities[$category][$name])) {
            unset($this->capabilities[$category][$name]);

            return true;
        }

        return false;
    }

    /**
     * Check if a capability is supported.
     *
     * @param  string  $category  Capability category
     * @param  string  $name  Capability name
     */
    public function hasCapability(string $category, string $name): bool
    {
        return isset($this->capabilities[$category][$name]);
    }

    /**
     * Get capabilities for a specific category.
     *
     * @param  string  $category  Capability category
     */
    public function getCapabilitiesForCategory(string $category): array
    {
        return $this->capabilities[$category] ?? [];
    }

    /**
     * Configure tools capabilities.
     *
     * @param  array  $toolsConfig  Tools configuration
     */
    public function configureToolsCapabilities(array $toolsConfig = []): void
    {
        $defaultToolsConfig = [
            'listChanged' => true,
        ];

        $this->capabilities['tools'] = array_merge($defaultToolsConfig, $toolsConfig);
    }

    /**
     * Configure resources capabilities.
     *
     * @param  array  $resourcesConfig  Resources configuration
     */
    public function configureResourcesCapabilities(array $resourcesConfig = []): void
    {
        $defaultResourcesConfig = [
            'subscribe' => true,
            'listChanged' => true,
        ];

        $this->capabilities['resources'] = array_merge($defaultResourcesConfig, $resourcesConfig);
    }

    /**
     * Configure prompts capabilities.
     *
     * @param  array  $promptsConfig  Prompts configuration
     */
    public function configurePromptsCapabilities(array $promptsConfig = []): void
    {
        $defaultPromptsConfig = [
            'listChanged' => true,
        ];

        $this->capabilities['prompts'] = array_merge($defaultPromptsConfig, $promptsConfig);
    }

    /**
     * Configure logging capabilities.
     *
     * @param  array  $loggingConfig  Logging configuration
     */
    public function configureLoggingCapabilities(array $loggingConfig = []): void
    {
        $defaultLoggingConfig = [];

        $this->capabilities['logging'] = array_merge($defaultLoggingConfig, $loggingConfig);
    }

    /**
     * Configure experimental capabilities.
     *
     * @param  array  $experimentalConfig  Experimental configuration
     */
    public function configureExperimentalCapabilities(array $experimentalConfig = []): void
    {
        $this->capabilities['experimental'] = $experimentalConfig;
    }

    /**
     * Negotiate capabilities with client.
     *
     * @param  array  $clientCapabilities  Client capabilities
     * @return array Negotiated capabilities
     */
    public function negotiateCapabilities(array $clientCapabilities): array
    {
        $serverCapabilities = $this->getCapabilities();
        $negotiated = [];

        // Find common capabilities
        foreach ($serverCapabilities as $category => $capabilities) {
            if (! isset($clientCapabilities[$category])) {
                continue;
            }

            $negotiated[$category] = $this->negotiateCategoryCapabilities(
                $capabilities,
                $clientCapabilities[$category]
            );
        }

        return $negotiated;
    }

    /**
     * Negotiate capabilities for a specific category.
     *
     * @param  array  $serverCapabilities  Server capabilities for category
     * @param  array  $clientCapabilities  Client capabilities for category
     * @return array Negotiated capabilities
     */
    protected function negotiateCategoryCapabilities(array $serverCapabilities, array $clientCapabilities): array
    {
        $negotiated = [];

        foreach ($serverCapabilities as $capability => $config) {
            if (isset($clientCapabilities[$capability])) {
                $negotiated[$capability] = $this->mergeCapabilityConfig($config, $clientCapabilities[$capability]);
            }
        }

        return $negotiated;
    }

    /**
     * Merge capability configuration between server and client.
     *
     * @param  mixed  $serverConfig  Server capability configuration
     * @param  mixed  $clientConfig  Client capability configuration
     * @return mixed Merged configuration
     */
    protected function mergeCapabilityConfig($serverConfig, $clientConfig)
    {
        // If both are booleans, use logical AND (both must support)
        if (is_bool($serverConfig) && is_bool($clientConfig)) {
            return $serverConfig && $clientConfig;
        }

        // If both are arrays, merge them
        if (is_array($serverConfig) && is_array($clientConfig)) {
            return array_merge($serverConfig, $clientConfig);
        }

        // Default to server config
        return $serverConfig;
    }

    /**
     * Get capability configuration.
     *
     * @param  string  $category  Capability category
     * @param  string  $name  Capability name
     * @return mixed Capability configuration or null if not found
     */
    public function getCapabilityConfig(string $category, string $name)
    {
        return $this->capabilities[$category][$name] ?? null;
    }

    /**
     * Update capability configuration.
     *
     * @param  string  $category  Capability category
     * @param  string  $name  Capability name
     * @param  mixed  $config  New configuration
     */
    public function updateCapabilityConfig(string $category, string $name, $config): void
    {
        if (! isset($this->capabilities[$category])) {
            $this->capabilities[$category] = [];
        }

        $this->capabilities[$category][$name] = $config;
    }

    /**
     * Get supported capability categories.
     */
    public function getSupportedCategories(): array
    {
        return array_keys($this->capabilities);
    }

    /**
     * Validate capability configuration.
     *
     * @param  array  $capabilities  Capabilities to validate
     * @return array Validation errors (empty if valid)
     */
    public function validateCapabilities(array $capabilities): array
    {
        $errors = [];
        $supportedCategories = ['tools', 'resources', 'prompts', 'logging', 'experimental'];

        foreach ($capabilities as $category => $config) {
            if (! in_array($category, $supportedCategories)) {
                $errors[] = "Unsupported capability category: {$category}";

                continue;
            }

            if (! is_array($config)) {
                $errors[] = "Capability category '{$category}' must be an array";
            }
        }

        return $errors;
    }
}
