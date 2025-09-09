<?php

namespace JTD\LaravelMCP\Abstracts;

/**
 * Abstract base class for MCP Prompts.
 *
 * This class provides the foundation for creating MCP prompts that can be
 * used by AI clients. Prompts are templates that generate structured messages
 * for AI interactions within the Laravel application.
 */
abstract class McpPrompt
{
    /**
     * The name of the prompt.
     */
    protected string $name;

    /**
     * A description of what the prompt generates.
     */
    protected string $description;

    /**
     * The JSON Schema for the prompt's arguments.
     */
    protected array $argumentsSchema = [];

    /**
     * Get the messages for this prompt with the given arguments.
     *
     * @param  array  $arguments  The prompt arguments
     * @return array The generated messages
     */
    abstract public function getMessages(array $arguments): array;

    /**
     * Get the prompt name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the prompt description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the prompt's arguments schema.
     */
    public function getArgumentsSchema(): array
    {
        return $this->argumentsSchema;
    }

    /**
     * Get the prompt definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'arguments' => $this->getArgumentsSchema(),
        ];
    }

    /**
     * Validate the provided arguments against the arguments schema.
     *
     * @param  array  $arguments  The arguments to validate
     * @return bool True if valid, throws exception otherwise
     *
     * @throws \InvalidArgumentException
     */
    public function validateArguments(array $arguments): bool
    {
        // Basic validation - check required fields
        if (isset($this->argumentsSchema['required'])) {
            foreach ($this->argumentsSchema['required'] as $requiredField) {
                if (! isset($arguments[$requiredField])) {
                    throw new \InvalidArgumentException(
                        "Required field '{$requiredField}' is missing"
                    );
                }
            }
        }

        // Type validation for each property
        if (isset($this->argumentsSchema['properties'])) {
            foreach ($arguments as $key => $value) {
                if (isset($this->argumentsSchema['properties'][$key])) {
                    $this->validatePropertyType(
                        $key,
                        $value,
                        $this->argumentsSchema['properties'][$key]
                    );
                }
            }
        }

        return true;
    }

    /**
     * Validate a property type.
     *
     * @param  string  $key  The property key
     * @param  mixed  $value  The property value
     * @param  array  $schema  The property schema
     *
     * @throws \InvalidArgumentException
     */
    protected function validatePropertyType(string $key, mixed $value, array $schema): void
    {
        $type = $schema['type'] ?? null;

        if (! $type) {
            return;
        }

        $isValid = match ($type) {
            'string' => is_string($value),
            'number' => is_numeric($value),
            'integer' => is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_array($value) || is_object($value),
            default => true,
        };

        if (! $isValid) {
            throw new \InvalidArgumentException(
                "Property '{$key}' must be of type '{$type}'"
            );
        }

        // Additional validations
        if ($type === 'integer' || $type === 'number') {
            if (isset($schema['minimum']) && $value < $schema['minimum']) {
                throw new \InvalidArgumentException(
                    "Property '{$key}' must be at least {$schema['minimum']}"
                );
            }
            if (isset($schema['maximum']) && $value > $schema['maximum']) {
                throw new \InvalidArgumentException(
                    "Property '{$key}' must be at most {$schema['maximum']}"
                );
            }
        }

        if ($type === 'string') {
            if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
                throw new \InvalidArgumentException(
                    "Property '{$key}' must be at least {$schema['minLength']} characters long"
                );
            }
            if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
                throw new \InvalidArgumentException(
                    "Property '{$key}' must be at most {$schema['maxLength']} characters long"
                );
            }
        }
    }

    /**
     * Create a message structure for MCP.
     *
     * @param  string  $role  The message role (user, assistant, system)
     * @param  string  $content  The message content
     */
    protected function createMessage(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => [
                'type' => 'text',
                'text' => $content,
            ],
        ];
    }

    /**
     * Format messages for MCP response.
     *
     * @param  array  $messages  The messages to format
     */
    protected function formatMessages(array $messages): array
    {
        return [
            'messages' => $messages,
        ];
    }

    /**
     * Apply template variables to a string.
     *
     * @param  string  $template  The template string
     * @param  array  $variables  The variables to apply
     */
    protected function applyTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}
