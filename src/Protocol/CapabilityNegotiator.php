<?php

namespace JTD\LaravelMCP\Protocol;

use Illuminate\Support\Facades\Log;

/**
 * MCP capability negotiator.
 *
 * This class handles the negotiation of capabilities between MCP clients
 * and servers, determining which features are supported by both parties.
 */
class CapabilityNegotiator
{
    /**
     * Default server capabilities.
     */
    protected array $defaultServerCapabilities = [
        'tools' => [
            'listChanged' => false,
        ],
        'resources' => [
            'subscribe' => false,
            'listChanged' => false,
        ],
        'prompts' => [
            'listChanged' => false,
        ],
        'logging' => [],
    ];

    /**
     * Capability requirements and constraints.
     */
    protected array $capabilityConstraints = [
        'tools' => [
            'required' => false,
            'features' => ['listChanged'],
        ],
        'resources' => [
            'required' => false,
            'features' => ['subscribe', 'listChanged'],
        ],
        'prompts' => [
            'required' => false,
            'features' => ['listChanged'],
        ],
        'logging' => [
            'required' => false,
            'features' => [],
        ],
    ];

    /**
     * Negotiate capabilities between client and server.
     */
    public function negotiate(array $clientCapabilities, array $serverCapabilities): array
    {
        $negotiated = [];

        // Start with server capabilities as base
        $baseCapabilities = array_merge($this->defaultServerCapabilities, $serverCapabilities);

        foreach ($baseCapabilities as $capability => $features) {
            $negotiated[$capability] = $this->negotiateCapability(
                $capability,
                $features,
                $clientCapabilities[$capability] ?? []
            );
        }

        // Add any client-specific capabilities we can support
        foreach ($clientCapabilities as $capability => $features) {
            if (! isset($negotiated[$capability]) && $this->canSupportCapability($capability)) {
                $negotiated[$capability] = $this->negotiateCapability(
                    $capability,
                    $this->getDefaultCapabilityFeatures($capability),
                    $features
                );
            }
        }

        Log::debug('Capability negotiation completed', [
            'client_capabilities' => $clientCapabilities,
            'server_capabilities' => $serverCapabilities,
            'negotiated_capabilities' => $negotiated,
        ]);

        return $negotiated;
    }

    /**
     * Negotiate a specific capability between client and server.
     */
    protected function negotiateCapability(string $capability, array $serverFeatures, array $clientFeatures): array
    {
        $negotiated = [];

        // If server features is not an array, treat it as enabled/disabled
        if (! is_array($serverFeatures)) {
            return $serverFeatures ? [] : [];
        }

        // If client features is not an array, treat it as enabled/disabled
        if (! is_array($clientFeatures)) {
            return $clientFeatures ? $serverFeatures : [];
        }

        // Negotiate each feature
        foreach ($serverFeatures as $feature => $serverValue) {
            $clientValue = $clientFeatures[$feature] ?? null;

            $negotiated[$feature] = $this->negotiateFeature($capability, $feature, $serverValue, $clientValue);
        }

        // Add any client features we can support but weren't in server features
        foreach ($clientFeatures as $feature => $clientValue) {
            if (! isset($negotiated[$feature]) && $this->canSupportFeature($capability, $feature)) {
                $negotiated[$feature] = $this->negotiateFeature($capability, $feature, null, $clientValue);
            }
        }

        return $negotiated;
    }

    /**
     * Negotiate a specific feature.
     */
    protected function negotiateFeature(string $capability, string $feature, $serverValue, $clientValue): bool|array|null
    {
        // If server doesn't support this feature, it's disabled
        if ($serverValue === null || $serverValue === false) {
            return false;
        }

        // If client doesn't specify this feature, use server default
        if ($clientValue === null) {
            return $serverValue;
        }

        // Both support it - enable if both want it enabled
        if (is_bool($serverValue) && is_bool($clientValue)) {
            return $serverValue && $clientValue;
        }

        // For complex features, merge arrays or use server value
        if (is_array($serverValue) && is_array($clientValue)) {
            return array_merge($serverValue, $clientValue);
        }

        if (is_array($serverValue)) {
            return $serverValue;
        }

        if (is_array($clientValue)) {
            return $clientValue;
        }

        // Default to enabling the feature
        return true;
    }

    /**
     * Check if we can support a capability.
     */
    protected function canSupportCapability(string $capability): bool
    {
        return isset($this->capabilityConstraints[$capability]) ||
               isset($this->defaultServerCapabilities[$capability]);
    }

    /**
     * Check if we can support a specific feature of a capability.
     */
    protected function canSupportFeature(string $capability, string $feature): bool
    {
        if (! isset($this->capabilityConstraints[$capability])) {
            return false;
        }

        $supportedFeatures = $this->capabilityConstraints[$capability]['features'] ?? [];

        return in_array($feature, $supportedFeatures);
    }

    /**
     * Get default features for a capability.
     */
    protected function getDefaultCapabilityFeatures(string $capability): array
    {
        return $this->defaultServerCapabilities[$capability] ?? [];
    }

    /**
     * Validate negotiated capabilities.
     */
    public function validateCapabilities(array $capabilities): bool
    {
        foreach ($this->capabilityConstraints as $capability => $constraints) {
            if ($constraints['required'] && ! isset($capabilities[$capability])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get capability summary for debugging.
     */
    public function getCapabilitySummary(array $capabilities): array
    {
        $summary = [
            'supported_capabilities' => array_keys($capabilities),
            'feature_count' => 0,
            'enabled_features' => [],
            'disabled_features' => [],
        ];

        foreach ($capabilities as $capability => $features) {
            if (is_array($features)) {
                $summary['feature_count'] += count($features);

                foreach ($features as $feature => $enabled) {
                    $featureName = "{$capability}.{$feature}";

                    if ($enabled) {
                        $summary['enabled_features'][] = $featureName;
                    } else {
                        $summary['disabled_features'][] = $featureName;
                    }
                }
            }
        }

        return $summary;
    }

    /**
     * Check if a specific capability is enabled.
     */
    public function hasCapability(array $capabilities, string $capability): bool
    {
        return isset($capabilities[$capability]);
    }

    /**
     * Check if a specific feature is enabled.
     */
    public function hasFeature(array $capabilities, string $capability, string $feature): bool
    {
        return isset($capabilities[$capability][$feature]) &&
               $capabilities[$capability][$feature];
    }

    /**
     * Get tools capabilities.
     */
    public function getToolsCapabilities(): array
    {
        return [
            'listChanged' => false,
        ];
    }

    /**
     * Get resources capabilities.
     */
    public function getResourcesCapabilities(): array
    {
        return [
            'subscribe' => false,
            'listChanged' => false,
        ];
    }

    /**
     * Get prompts capabilities.
     */
    public function getPromptsCapabilities(): array
    {
        return [
            'listChanged' => false,
        ];
    }

    /**
     * Get logging capabilities.
     */
    public function getLoggingCapabilities(): array
    {
        return [];
    }

    /**
     * Set default server capabilities.
     */
    public function setDefaultServerCapabilities(array $capabilities): void
    {
        $this->defaultServerCapabilities = array_merge($this->defaultServerCapabilities, $capabilities);
    }

    /**
     * Get default server capabilities.
     */
    public function getDefaultServerCapabilities(): array
    {
        return $this->defaultServerCapabilities;
    }

    /**
     * Add capability constraint.
     */
    public function addCapabilityConstraint(string $capability, array $constraint): void
    {
        $this->capabilityConstraints[$capability] = array_merge(
            $this->capabilityConstraints[$capability] ?? [],
            $constraint
        );
    }

    /**
     * Remove capability constraint.
     */
    public function removeCapabilityConstraint(string $capability): void
    {
        unset($this->capabilityConstraints[$capability]);
    }

    /**
     * Get all capability constraints.
     */
    public function getCapabilityConstraints(): array
    {
        return $this->capabilityConstraints;
    }

    /**
     * Create a minimal capability set for basic MCP functionality.
     */
    public function createMinimalCapabilities(): array
    {
        return [
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ];
    }

    /**
     * Create a full capability set with all features enabled.
     */
    public function createFullCapabilities(): array
    {
        return [
            'tools' => [
                'listChanged' => true,
            ],
            'resources' => [
                'subscribe' => true,
                'listChanged' => true,
            ],
            'prompts' => [
                'listChanged' => true,
            ],
            'logging' => [],
        ];
    }
}
