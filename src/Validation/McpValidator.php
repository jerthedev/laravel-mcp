<?php

namespace JTD\LaravelMCP\Validation;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\ValidationException;

class McpValidator
{
    private Factory $validator;

    public function __construct(Factory $validator)
    {
        $this->validator = $validator;
    }

    public function validateMcpParameters(array $parameters, array $rules, array $messages = []): array
    {
        try {
            $validator = $this->validator->make($parameters, $rules, $messages);

            return $validator->validated();
        } catch (ValidationException $e) {
            throw new McpValidationException(
                'MCP parameter validation failed',
                $e->errors(),
                $e
            );
        }
    }

    public function validateToolParameters(string $toolName, array $parameters, array $schema): array
    {
        $rules = $this->convertSchemaToRules($schema);

        return $this->validateMcpParameters(
            $parameters,
            $rules,
            $this->generateValidationMessages($toolName, $schema)
        );
    }

    public function validateResourceParameters(string $resourceName, array $parameters, string $action = 'read'): array
    {
        $rules = $this->getResourceValidationRules($resourceName, $action);

        return $this->validateMcpParameters(
            $parameters,
            $rules,
            $this->getResourceValidationMessages($resourceName, $action)
        );
    }

    private function convertSchemaToRules(array $schema): array
    {
        $rules = [];

        foreach ($schema as $field => $config) {
            $rules[$field] = $this->buildFieldRules($config);
        }

        return $rules;
    }

    private function buildFieldRules(array $config): array
    {
        $rules = [];

        // Required/Optional
        if ($config['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type validation
        match ($config['type'] ?? 'string') {
            'string' => $rules[] = 'string',
            'integer' => $rules[] = 'integer',
            'number' => $rules[] = 'numeric',
            'boolean' => $rules[] = 'boolean',
            'array' => $rules[] = 'array',
            'object' => $rules[] = 'array',
            default => null,
        };

        // Additional constraints
        if (isset($config['minLength'])) {
            $rules[] = 'min:'.$config['minLength'];
        }

        if (isset($config['maxLength'])) {
            $rules[] = 'max:'.$config['maxLength'];
        }

        if (isset($config['minimum'])) {
            $rules[] = 'min:'.$config['minimum'];
        }

        if (isset($config['maximum'])) {
            $rules[] = 'max:'.$config['maximum'];
        }

        if (isset($config['enum'])) {
            $rules[] = 'in:'.implode(',', $config['enum']);
        }

        return $rules;
    }

    private function generateValidationMessages(string $toolName, array $schema): array
    {
        $messages = [];

        foreach ($schema as $field => $config) {
            $fieldName = $config['description'] ?? $field;

            $messages["{$field}.required"] = "The {$fieldName} parameter is required for {$toolName}.";
            $messages["{$field}.string"] = "The {$fieldName} parameter must be a string.";
            $messages["{$field}.integer"] = "The {$fieldName} parameter must be an integer.";
            $messages["{$field}.numeric"] = "The {$fieldName} parameter must be numeric.";
            $messages["{$field}.boolean"] = "The {$fieldName} parameter must be true or false.";
            $messages["{$field}.array"] = "The {$fieldName} parameter must be an array.";
        }

        return $messages;
    }

    private function getResourceValidationRules(string $resourceName, string $action): array
    {
        // Define default rules for resource actions
        return match ($action) {
            'read' => [
                'id' => ['required', 'string'],
            ],
            'list' => [
                'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
                'offset' => ['nullable', 'integer', 'min:0'],
            ],
            'subscribe' => [
                'resource_id' => ['required', 'string'],
                'events' => ['nullable', 'array'],
            ],
            default => [],
        };
    }

    private function getResourceValidationMessages(string $resourceName, string $action): array
    {
        return match ($action) {
            'read' => [
                'id.required' => "Resource ID is required for reading {$resourceName}.",
                'id.string' => 'Resource ID must be a string.',
            ],
            'list' => [
                'limit.integer' => 'Limit must be an integer.',
                'limit.min' => 'Limit must be at least 1.',
                'limit.max' => 'Limit cannot exceed 100.',
                'offset.integer' => 'Offset must be an integer.',
                'offset.min' => 'Offset cannot be negative.',
            ],
            'subscribe' => [
                'resource_id.required' => 'Resource ID is required for subscription.',
                'resource_id.string' => 'Resource ID must be a string.',
                'events.array' => 'Events must be an array.',
            ],
            default => [],
        };
    }
}
