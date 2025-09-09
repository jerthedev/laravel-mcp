<?php

namespace JTD\LaravelMCP\Abstracts;

/**
 * Abstract base class for MCP Tools.
 *
 * This class provides the foundation for creating MCP tools that can be
 * executed by AI clients. Tools are functions that AI can call to perform
 * specific actions within the Laravel application.
 */
abstract class McpTool
{
    /**
     * The name of the tool.
     */
    protected string $name;

    /**
     * A description of what the tool does.
     */
    protected string $description;

    /**
     * The JSON Schema for the tool's input parameters.
     */
    protected array $inputSchema = [];

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array  $arguments  The tool arguments
     * @return array The tool execution result
     */
    abstract public function execute(array $arguments): array;

    /**
     * Get the tool name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the tool description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the tool's input schema.
     */
    public function getInputSchema(): array
    {
        return $this->inputSchema;
    }

    /**
     * Get the tool definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'inputSchema' => $this->getInputSchema(),
        ];
    }

    /**
     * Validate the provided arguments against the input schema.
     *
     * @param  array  $arguments  The arguments to validate
     * @return bool True if valid, throws exception otherwise
     *
     * @throws \InvalidArgumentException
     */
    public function validateArguments(array $arguments): bool
    {
        // Basic validation - check required fields
        if (isset($this->inputSchema['required'])) {
            foreach ($this->inputSchema['required'] as $requiredField) {
                if (! isset($arguments[$requiredField])) {
                    throw new \InvalidArgumentException(
                        "Required field '{$requiredField}' is missing"
                    );
                }
            }
        }

        // Type validation for each property
        if (isset($this->inputSchema['properties'])) {
            foreach ($arguments as $key => $value) {
                if (isset($this->inputSchema['properties'][$key])) {
                    $this->validatePropertyType(
                        $key,
                        $value,
                        $this->inputSchema['properties'][$key]
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
}
