<?php

namespace JTD\LaravelMCP\Registry;

use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Registry\Contracts\RegistryInterface;

/**
 * Registry for MCP prompt components.
 *
 * This class manages the registration and retrieval of MCP prompts,
 * providing specialized functionality for prompt-specific operations.
 */
class PromptRegistry implements RegistryInterface
{
    /**
     * Registered prompts storage.
     */
    protected array $prompts = [];

    /**
     * Prompt metadata storage.
     */
    protected array $metadata = [];

    /**
     * Registry type identifier.
     */
    protected string $type = 'prompts';

    /**
     * Register a prompt with the registry.
     */
    public function register(string $name, $prompt, array $metadata = []): void
    {
        if ($this->has($name)) {
            throw new RegistrationException("Prompt '{$name}' is already registered");
        }

        $this->prompts[$name] = $prompt;
        $this->metadata[$name] = array_merge([
            'name' => $name,
            'type' => 'prompt',
            'registered_at' => now()->toISOString(),
            'description' => $metadata['description'] ?? '',
            'arguments' => $metadata['arguments'] ?? [],
        ], $metadata);
    }

    /**
     * Unregister a prompt from the registry.
     */
    public function unregister(string $name): bool
    {
        if (! $this->has($name)) {
            return false;
        }

        unset($this->prompts[$name], $this->metadata[$name]);

        return true;
    }

    /**
     * Check if a prompt is registered.
     */
    public function has(string $name): bool
    {
        return array_key_exists($name, $this->prompts);
    }

    /**
     * Get a registered prompt.
     */
    public function get(string $name)
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Prompt '{$name}' is not registered");
        }

        return $this->prompts[$name];
    }

    /**
     * Get all registered prompts.
     */
    public function all(): array
    {
        return $this->prompts;
    }

    /**
     * Get all registered prompt names.
     */
    public function names(): array
    {
        return array_keys($this->prompts);
    }

    /**
     * Count registered prompts.
     */
    public function count(): int
    {
        return count($this->prompts);
    }

    /**
     * Clear all registered prompts.
     */
    public function clear(): void
    {
        $this->prompts = [];
        $this->metadata = [];
    }

    /**
     * Get metadata for a registered prompt.
     */
    public function getMetadata(string $name): array
    {
        if (! $this->has($name)) {
            throw new RegistrationException("Prompt '{$name}' is not registered");
        }

        return $this->metadata[$name];
    }

    /**
     * Filter prompts by metadata criteria.
     */
    public function filter(array $criteria): array
    {
        return array_filter($this->prompts, function ($prompt, $name) use ($criteria) {
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
     * Get prompts matching a pattern.
     */
    public function search(string $pattern): array
    {
        return array_filter($this->prompts, function ($prompt, $name) use ($pattern) {
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
     * Get prompt definitions for MCP protocol.
     */
    public function getPromptDefinitions(): array
    {
        $definitions = [];

        foreach ($this->prompts as $name => $prompt) {
            $metadata = $this->metadata[$name];

            $definitions[] = [
                'name' => $name,
                'description' => $metadata['description'] ?? '',
                'arguments' => $this->formatArguments($metadata['arguments'] ?? []),
            ];
        }

        return $definitions;
    }

    /**
     * Get a prompt with rendered content.
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        $prompt = $this->get($name);

        if (is_string($prompt) && class_exists($prompt)) {
            $prompt = new $prompt;
        }

        if (! is_object($prompt) || ! method_exists($prompt, 'render')) {
            throw new RegistrationException("Prompt '{$name}' does not have a render method");
        }

        $content = $prompt->render($arguments);
        $metadata = $this->getMetadata($name);

        return [
            'description' => $metadata['description'] ?? '',
            'messages' => $this->formatMessages($content),
        ];
    }

    /**
     * List prompts for MCP protocol.
     */
    public function listPrompts(?string $cursor = null): array
    {
        return [
            'prompts' => $this->getPromptDefinitions(),
        ];
    }

    /**
     * Validate prompt arguments.
     */
    public function validateArguments(string $name, array $arguments): bool
    {
        $metadata = $this->getMetadata($name);
        $requiredArguments = $metadata['arguments'] ?? [];

        foreach ($requiredArguments as $argument) {
            if (isset($argument['required']) && $argument['required']) {
                $argName = $argument['name'] ?? '';
                if (empty($arguments[$argName])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Get prompts by argument requirements.
     */
    public function getPromptsByArguments(array $requiredArguments): array
    {
        return array_filter($this->prompts, function ($prompt, $name) use ($requiredArguments) {
            $metadata = $this->metadata[$name];
            $promptArguments = array_column($metadata['arguments'] ?? [], 'name');

            return empty(array_diff($requiredArguments, $promptArguments));
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Get argument schema for a prompt.
     */
    public function getArgumentSchema(string $name): array
    {
        $metadata = $this->getMetadata($name);
        $arguments = $metadata['arguments'] ?? [];

        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($arguments as $argument) {
            $argName = $argument['name'] ?? '';
            if (! $argName) {
                continue;
            }

            $schema['properties'][$argName] = [
                'type' => $argument['type'] ?? 'string',
                'description' => $argument['description'] ?? '',
            ];

            if (isset($argument['required']) && $argument['required']) {
                $schema['required'][] = $argName;
            }
        }

        return $schema;
    }

    /**
     * Format arguments for MCP protocol.
     */
    protected function formatArguments(array $arguments): array
    {
        return array_map(function ($argument) {
            return [
                'name' => $argument['name'] ?? '',
                'description' => $argument['description'] ?? '',
                'required' => $argument['required'] ?? false,
            ];
        }, $arguments);
    }

    /**
     * Format content as messages.
     */
    protected function formatMessages($content): array
    {
        if (is_string($content)) {
            return [[
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => $content,
                ],
            ]];
        }

        if (is_array($content) && isset($content['messages'])) {
            return $content['messages'];
        }

        return [[
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => json_encode($content),
            ],
        ]];
    }
}
