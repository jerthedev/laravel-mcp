<?php

namespace JTD\LaravelMCP\Server;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Registry\McpRegistry;

class CapabilityManager extends CapabilityNegotiator
{
    private McpRegistry $registry;

    private array $serverCapabilities = [];

    private array $negotiatedCapabilities = [];

    private bool $capabilitiesLocked = false;

    public function __construct(McpRegistry $registry)
    {
        parent::__construct();
        $this->registry = $registry;
        $this->initializeServerCapabilities();
    }

    /**
     * Initialize server capabilities based on configuration and available components.
     */
    private function initializeServerCapabilities(): void
    {
        $this->serverCapabilities = [
            'tools' => [
                'listChanged' => Config::get('laravel-mcp.capabilities.tools.list_changed_notifications', true),
            ],
            'resources' => [
                'subscribe' => Config::get('laravel-mcp.capabilities.resources.subscriptions', false),
                'listChanged' => Config::get('laravel-mcp.capabilities.resources.list_changed_notifications', true),
            ],
            'prompts' => [
                'listChanged' => Config::get('laravel-mcp.capabilities.prompts.list_changed_notifications', true),
            ],
            'logging' => [
                'level' => Config::get('laravel-mcp.capabilities.logging.level', 'info'),
            ],
        ];

        // Add experimental capabilities if enabled
        if (Config::get('laravel-mcp.capabilities.experimental.enabled', false)) {
            $this->serverCapabilities['completion'] = [
                'enabled' => Config::get('laravel-mcp.capabilities.completion.enabled', false),
            ];
        }
    }

    /**
     * Negotiate capabilities with enhanced logic.
     */
    public function negotiateWithClient(array $clientCapabilities): array
    {
        if ($this->capabilitiesLocked) {
            Log::warning('Attempt to negotiate capabilities after they were locked');

            return $this->negotiatedCapabilities;
        }

        // Dynamically adjust capabilities based on available components
        $this->adjustCapabilitiesForAvailableComponents();

        // Perform the negotiation
        $this->negotiatedCapabilities = $this->negotiate($clientCapabilities, $this->serverCapabilities);

        // Post-negotiation validation and adjustment
        $this->validateNegotiatedCapabilities();

        $this->capabilitiesLocked = true;

        Log::info('MCP capabilities negotiated', [
            'client_capabilities' => $clientCapabilities,
            'server_capabilities' => $this->serverCapabilities,
            'negotiated_capabilities' => $this->negotiatedCapabilities,
        ]);

        return $this->negotiatedCapabilities;
    }

    /**
     * Adjust capabilities based on available components.
     */
    private function adjustCapabilitiesForAvailableComponents(): void
    {
        // Disable tools capability if no tools are registered
        if (empty($this->registry->getTools())) {
            $this->serverCapabilities['tools'] = [];
        }

        // Disable resources capability if no resources are registered
        if (empty($this->registry->getResources())) {
            $this->serverCapabilities['resources'] = [];
        }

        // Disable prompts capability if no prompts are registered
        if (empty($this->registry->getPrompts())) {
            $this->serverCapabilities['prompts'] = [];
        }
    }

    /**
     * Validate negotiated capabilities.
     */
    private function validateNegotiatedCapabilities(): void
    {
        // Ensure we have at least one capability enabled
        if (empty($this->negotiatedCapabilities)) {
            Log::warning('No capabilities negotiated - enabling minimal toolset');
            $this->negotiatedCapabilities = $this->createMinimalCapabilities();
        }

        // Validate capability consistency
        foreach ($this->negotiatedCapabilities as $capability => $features) {
            if (! $this->isCapabilityValid($capability, $features)) {
                Log::warning("Invalid capability configuration: {$capability}", [
                    'features' => $features,
                ]);
                unset($this->negotiatedCapabilities[$capability]);
            }
        }
    }

    /**
     * Check if a capability configuration is valid.
     */
    private function isCapabilityValid(string $capability, $features): bool
    {
        switch ($capability) {
            case 'tools':
                return $this->validateToolsCapability($features);
            case 'resources':
                return $this->validateResourcesCapability($features);
            case 'prompts':
                return $this->validatePromptsCapability($features);
            case 'logging':
                return $this->validateLoggingCapability($features);
            default:
                return true; // Allow unknown capabilities
        }
    }

    /**
     * Validate tools capability.
     */
    private function validateToolsCapability($features): bool
    {
        if (! is_array($features)) {
            return true; // Simple boolean enable/disable
        }

        // listChanged must be boolean if present
        if (isset($features['listChanged']) && ! is_bool($features['listChanged'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate resources capability.
     */
    private function validateResourcesCapability($features): bool
    {
        if (! is_array($features)) {
            return true; // Simple boolean enable/disable
        }

        // subscribe must be boolean if present
        if (isset($features['subscribe']) && ! is_bool($features['subscribe'])) {
            return false;
        }

        // listChanged must be boolean if present
        if (isset($features['listChanged']) && ! is_bool($features['listChanged'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate prompts capability.
     */
    private function validatePromptsCapability($features): bool
    {
        if (! is_array($features)) {
            return true; // Simple boolean enable/disable
        }

        // listChanged must be boolean if present
        if (isset($features['listChanged']) && ! is_bool($features['listChanged'])) {
            return false;
        }

        return true;
    }

    /**
     * Validate logging capability.
     */
    private function validateLoggingCapability($features): bool
    {
        if (! is_array($features)) {
            return true; // Simple boolean enable/disable
        }

        // level must be valid log level if present
        if (isset($features['level'])) {
            $validLevels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];

            return in_array($features['level'], $validLevels);
        }

        return true;
    }

    /**
     * Get the negotiated capabilities.
     */
    public function getNegotiatedCapabilities(): array
    {
        return $this->negotiatedCapabilities;
    }

    /**
     * Get server capabilities.
     */
    public function getServerCapabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Check if a specific capability is negotiated and enabled.
     */
    public function isCapabilityEnabled(string $capability): bool
    {
        return isset($this->negotiatedCapabilities[$capability]) &&
               ! empty($this->negotiatedCapabilities[$capability]);
    }

    /**
     * Check if a specific feature is negotiated and enabled.
     */
    public function isFeatureEnabled(string $capability, string $feature): bool
    {
        return isset($this->negotiatedCapabilities[$capability][$feature]) &&
               $this->negotiatedCapabilities[$capability][$feature] === true;
    }

    /**
     * Get capability information for a specific capability.
     */
    public function getCapabilityInfo(string $capability): ?array
    {
        return $this->negotiatedCapabilities[$capability] ?? null;
    }

    /**
     * Update server capabilities (only before negotiation).
     */
    public function updateServerCapabilities(array $capabilities): void
    {
        if ($this->capabilitiesLocked) {
            throw new \RuntimeException('Cannot update server capabilities after negotiation');
        }

        $this->serverCapabilities = array_merge($this->serverCapabilities, $capabilities);
    }

    /**
     * Reset capabilities (for testing or re-negotiation).
     */
    public function resetCapabilities(): void
    {
        $this->negotiatedCapabilities = [];
        $this->capabilitiesLocked = false;
        $this->initializeServerCapabilities();
    }

    /**
     * Lock capabilities to prevent further changes.
     */
    public function lockCapabilities(): void
    {
        $this->capabilitiesLocked = true;
    }

    /**
     * Check if capabilities are locked.
     */
    public function areCapabilitiesLocked(): bool
    {
        return $this->capabilitiesLocked;
    }

    /**
     * Get capability requirements for MCP 1.0 compliance.
     */
    public function getMcp10Requirements(): array
    {
        return [
            'required_capabilities' => ['tools', 'resources', 'prompts'],
            'optional_capabilities' => ['logging', 'completion'],
            'required_methods' => [
                'initialize',
                'tools/list',
                'tools/call',
                'resources/list',
                'resources/read',
                'prompts/list',
                'prompts/get',
            ],
        ];
    }

    /**
     * Validate MCP 1.0 compliance.
     */
    public function validateMcp10Compliance(): array
    {
        $requirements = $this->getMcp10Requirements();
        $issues = [];

        // Check required capabilities
        foreach ($requirements['required_capabilities'] as $capability) {
            if (! isset($this->negotiatedCapabilities[$capability])) {
                $issues[] = "Missing required capability: {$capability}";
            }
        }

        return [
            'compliant' => empty($issues),
            'issues' => $issues,
            'negotiated_capabilities' => $this->negotiatedCapabilities,
        ];
    }

    /**
     * Get capability summary for debugging.
     */
    public function getCapabilitySummary(): array
    {
        $summary = parent::getCapabilitySummary($this->negotiatedCapabilities);

        $summary['server_capabilities'] = $this->serverCapabilities;
        $summary['capabilities_locked'] = $this->capabilitiesLocked;
        $summary['mcp10_compliance'] = $this->validateMcp10Compliance();

        return $summary;
    }

    /**
     * Create capabilities tailored to current component availability.
     */
    public function createDynamicCapabilities(): array
    {
        $capabilities = [];

        // Tools capability
        if (! empty($this->registry->getTools())) {
            $capabilities['tools'] = $this->getToolsCapabilities();
        }

        // Resources capability
        if (! empty($this->registry->getResources())) {
            $capabilities['resources'] = $this->getResourcesCapabilities();
        }

        // Prompts capability
        if (! empty($this->registry->getPrompts())) {
            $capabilities['prompts'] = $this->getPromptsCapabilities();
        }

        // Always include logging
        $capabilities['logging'] = $this->getLoggingCapabilities();

        return $capabilities;
    }

    /**
     * Get detailed capability information.
     */
    public function getDetailedCapabilityInfo(): array
    {
        return [
            'server_capabilities' => $this->serverCapabilities,
            'negotiated_capabilities' => $this->negotiatedCapabilities,
            'capabilities_locked' => $this->capabilitiesLocked,
            'component_counts' => [
                'tools' => count($this->registry->getTools()),
                'resources' => count($this->registry->getResources()),
                'prompts' => count($this->registry->getPrompts()),
            ],
            'mcp10_compliance' => $this->validateMcp10Compliance(),
            'capability_summary' => $this->getCapabilitySummary(),
        ];
    }
}
