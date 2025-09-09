<?php

namespace JTD\LaravelMCP\Traits;

/**
 * Trait for managing MCP component capabilities.
 *
 * This trait provides functionality for managing capabilities specific to
 * MCP components (tools, resources, prompts), including capability discovery,
 * configuration, and integration with MCP protocol requirements.
 */
trait ManagesCapabilities
{
    /**
     * Component capabilities.
     */
    protected array $capabilities = [];

    /**
     * Get all component capabilities.
     */
    public function getCapabilities(): array
    {
        return array_merge($this->getDefaultCapabilities(), $this->capabilities);
    }

    /**
     * Check if component has a specific capability.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities());
    }

    /**
     * Add a capability to the component.
     */
    public function addCapability(string $capability): self
    {
        if (! in_array($capability, $this->capabilities)) {
            $this->capabilities[] = $capability;
        }

        return $this;
    }

    /**
     * Remove a capability from the component.
     */
    public function removeCapability(string $capability): self
    {
        $this->capabilities = array_filter($this->capabilities, fn ($c) => $c !== $capability);

        return $this;
    }

    /**
     * Get default capabilities based on component type.
     */
    protected function getDefaultCapabilities(): array
    {
        $className = class_basename(static::class);

        // For specific base classes
        if (str_contains($className, 'Tool')) {
            return ['execute'];
        }

        if (str_contains($className, 'Resource')) {
            return ['read', 'list'];
        }

        if (str_contains($className, 'Prompt')) {
            return ['get'];
        }

        // For classes extending our abstract base classes
        $parentClass = get_parent_class(static::class);
        if ($parentClass) {
            $parentClassName = class_basename($parentClass);

            if (str_contains($parentClassName, 'McpTool')) {
                return ['execute'];
            }

            if (str_contains($parentClassName, 'McpResource')) {
                return ['read', 'list'];
            }

            if (str_contains($parentClassName, 'McpPrompt')) {
                return ['get'];
            }
        }

        return [];
    }

    /**
     * Set all capabilities, replacing existing ones.
     */
    public function setCapabilities(array $capabilities): self
    {
        $this->capabilities = $capabilities;

        return $this;
    }

    /**
     * Get capabilities as a formatted array for MCP.
     */
    public function getCapabilitiesArray(): array
    {
        return [
            'capabilities' => $this->getCapabilities(),
            'supports' => $this->getSupportedOperations(),
        ];
    }

    /**
     * Get supported operations based on capabilities and component type.
     */
    protected function getSupportedOperations(): array
    {
        $operations = [];
        $capabilities = $this->getCapabilities();

        // Tool operations
        if (in_array('execute', $capabilities)) {
            $operations[] = 'execute';
        }

        // Resource operations
        if (in_array('read', $capabilities)) {
            $operations[] = 'read';
        }

        if (in_array('list', $capabilities)) {
            $operations[] = 'list';
        }

        if (in_array('subscribe', $capabilities)) {
            $operations[] = 'subscribe';
        }

        // Prompt operations
        if (in_array('get', $capabilities)) {
            $operations[] = 'get';
        }

        return $operations;
    }

    /**
     * Check if component supports a specific operation.
     */
    public function supportsOperation(string $operation): bool
    {
        return in_array($operation, $this->getSupportedOperations());
    }

    /**
     * Enable subscription capability for resources.
     */
    public function enableSubscription(): self
    {
        return $this->addCapability('subscribe');
    }

    /**
     * Disable subscription capability for resources.
     */
    public function disableSubscription(): self
    {
        return $this->removeCapability('subscribe');
    }

    /**
     * Check if component supports subscriptions.
     */
    public function supportsSubscription(): bool
    {
        return $this->hasCapability('subscribe');
    }

    /**
     * Get capability metadata for introspection.
     */
    public function getCapabilityMetadata(): array
    {
        return [
            'type' => $this->getComponentType(),
            'capabilities' => $this->getCapabilities(),
            'operations' => $this->getSupportedOperations(),
            'default_capabilities' => $this->getDefaultCapabilities(),
            'custom_capabilities' => array_diff($this->capabilities, $this->getDefaultCapabilities()),
        ];
    }

    /**
     * Get component type based on class hierarchy.
     */
    protected function getComponentType(): string
    {
        $className = class_basename(static::class);

        if (str_contains($className, 'Tool')) {
            return 'tool';
        }

        if (str_contains($className, 'Resource')) {
            return 'resource';
        }

        if (str_contains($className, 'Prompt')) {
            return 'prompt';
        }

        // Check parent class
        $parentClass = get_parent_class(static::class);
        if ($parentClass) {
            $parentClassName = class_basename($parentClass);

            if (str_contains($parentClassName, 'McpTool')) {
                return 'tool';
            }

            if (str_contains($parentClassName, 'McpResource')) {
                return 'resource';
            }

            if (str_contains($parentClassName, 'McpPrompt')) {
                return 'prompt';
            }
        }

        return 'unknown';
    }

    /**
     * Validate that capabilities are appropriate for component type.
     */
    public function validateCapabilities(): array
    {
        $errors = [];
        $type = $this->getComponentType();
        $capabilities = $this->getCapabilities();

        // Define valid capabilities per component type
        $validCapabilities = [
            'tool' => ['execute'],
            'resource' => ['read', 'list', 'subscribe'],
            'prompt' => ['get'],
        ];

        if (! isset($validCapabilities[$type])) {
            $errors[] = "Unknown component type: {$type}";

            return $errors;
        }

        foreach ($capabilities as $capability) {
            if (! in_array($capability, $validCapabilities[$type])) {
                $errors[] = "Invalid capability '{$capability}' for {$type} component";
            }
        }

        return $errors;
    }
}
