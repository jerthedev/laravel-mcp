<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Validation\ValidationException;
use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Support\SchemaValidator;

/**
 * Trait for parameter validation in MCP components.
 *
 * This trait provides comprehensive parameter validation functionality
 * using Laravel's validation system, including type checking, format
 * validation, and schema-based validation for MCP components.
 */
trait ValidatesParameters
{
    /**
     * Validate parameters against a schema or context.
     */
    protected function validateParameters(array $parameters, ?string $context = null): array
    {
        $rules = $this->getValidationRules($context);

        if (empty($rules)) {
            return $parameters;
        }

        try {
            return $this->validator->make($parameters, $rules)->validated();
        } catch (ValidationException $e) {
            throw new McpException('Parameter validation failed: '.$e->getMessage(), -32602);
        }
    }

    /**
     * Get validation rules for the given context.
     */
    protected function getValidationRules(?string $context = null): array
    {
        // Try context-specific rules first
        if ($context && method_exists($this, "get{$context}ValidationRules")) {
            return $this->{"get{$context}ValidationRules"}();
        }

        // Build rules from parameter schema if available
        return $this->buildRulesFromSchema();
    }

    /**
     * Build Laravel validation rules from parameter schema.
     */
    private function buildRulesFromSchema(): array
    {
        if (! isset($this->parameterSchema)) {
            return [];
        }

        $rules = [];

        foreach ($this->parameterSchema as $field => $schema) {
            $rules[$field] = $this->buildFieldRule($schema);
        }

        return $rules;
    }

    /**
     * Build validation rule for a single field from schema.
     */
    private function buildFieldRule(array $schema): string
    {
        $rules = [];

        // Required/nullable
        if ($schema['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        // Type validation
        switch ($schema['type'] ?? 'string') {
            case 'string':
                $rules[] = 'string';
                if (isset($schema['maxLength'])) {
                    $rules[] = "max:{$schema['maxLength']}";
                }
                if (isset($schema['minLength'])) {
                    $rules[] = "min:{$schema['minLength']}";
                }
                break;

            case 'integer':
                $rules[] = 'integer';
                if (isset($schema['minimum'])) {
                    $rules[] = "min:{$schema['minimum']}";
                }
                if (isset($schema['maximum'])) {
                    $rules[] = "max:{$schema['maximum']}";
                }
                break;

            case 'number':
                $rules[] = 'numeric';
                if (isset($schema['minimum'])) {
                    $rules[] = "min:{$schema['minimum']}";
                }
                if (isset($schema['maximum'])) {
                    $rules[] = "max:{$schema['maximum']}";
                }
                break;

            case 'boolean':
                $rules[] = 'boolean';
                break;

            case 'array':
                $rules[] = 'array';
                if (isset($schema['minItems'])) {
                    $rules[] = "min:{$schema['minItems']}";
                }
                if (isset($schema['maxItems'])) {
                    $rules[] = "max:{$schema['maxItems']}";
                }
                break;

            case 'object':
                $rules[] = 'array';
                break;
        }

        // Enum validation
        if (isset($schema['enum'])) {
            $values = implode(',', $schema['enum']);
            $rules[] = "in:$values";
        }

        // Pattern validation (regex)
        if (isset($schema['pattern'])) {
            $rules[] = "regex:{$schema['pattern']}";
        }

        // Format validation
        if (isset($schema['format'])) {
            switch ($schema['format']) {
                case 'email':
                    $rules[] = 'email';
                    break;
                case 'url':
                    $rules[] = 'url';
                    break;
                case 'uri':
                    $rules[] = 'url';
                    break;
                case 'uuid':
                    $rules[] = 'uuid';
                    break;
                case 'date':
                    $rules[] = 'date';
                    break;
                case 'date-time':
                    $rules[] = 'date_format:c';
                    break;
            }
        }

        return implode('|', $rules);
    }

    /**
     * Validate parameters against a schema using comprehensive validation.
     */
    protected function validateSchema(array $params, array $schema): array
    {
        $validated = [];
        $errors = [];

        foreach ($schema as $name => $rules) {
            try {
                if (isset($params[$name])) {
                    $validated[$name] = $this->validateField($params[$name], $rules, $name);
                } elseif ($this->isRequired($rules)) {
                    $errors[] = "Missing required parameter: {$name}";
                } elseif (isset($rules['default'])) {
                    $validated[$name] = $rules['default'];
                }
            } catch (McpException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (! empty($errors)) {
            throw new McpException(
                'Parameter validation failed: '.implode(', ', $errors),
                -32602
            );
        }

        return $validated;
    }

    /**
     * Validate a single field value with comprehensive validation.
     */
    protected function validateField($value, array $rules, string $fieldName)
    {
        // Type validation
        if (isset($rules['type'])) {
            $this->validateFieldType($value, $rules['type'], $fieldName);
        }

        // Format validation
        if (isset($rules['format'])) {
            $value = $this->validateFieldFormat($value, $rules['format'], $fieldName);
        }

        // Length validation
        if (isset($rules['min_length']) || isset($rules['max_length'])) {
            $this->validateFieldLength($value, $rules, $fieldName);
        }

        // Range validation
        if (isset($rules['min']) || isset($rules['max'])) {
            $this->validateFieldRange($value, $rules, $fieldName);
        }

        // Enum validation
        if (isset($rules['enum'])) {
            $this->validateFieldEnum($value, $rules['enum'], $fieldName);
        }

        // Pattern validation
        if (isset($rules['pattern'])) {
            $this->validateFieldPattern($value, $rules['pattern'], $fieldName);
        }

        // Custom validation
        if (isset($rules['validator']) && is_callable($rules['validator'])) {
            $value = call_user_func($rules['validator'], $value, $fieldName);
        }

        return $value;
    }

    /**
     * Validate field type.
     */
    protected function validateFieldType($value, string $expectedType, string $fieldName): void
    {
        switch ($expectedType) {
            case 'string':
                if (! is_string($value)) {
                    throw new McpException("Field '{$fieldName}' must be a string", -32602);
                }
                break;

            case 'integer':
            case 'int':
                if (! is_int($value)) {
                    throw new McpException("Field '{$fieldName}' must be an integer", -32602);
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    throw new McpException("Field '{$fieldName}' must be a number", -32602);
                }
                break;

            case 'boolean':
            case 'bool':
                if (! is_bool($value)) {
                    throw new McpException("Field '{$fieldName}' must be a boolean", -32602);
                }
                break;

            case 'array':
                if (! is_array($value)) {
                    throw new McpException("Field '{$fieldName}' must be an array", -32602);
                }
                break;

            case 'object':
                if (! is_array($value) && ! is_object($value)) {
                    throw new McpException("Field '{$fieldName}' must be an object", -32602);
                }
                break;

            case 'null':
                if ($value !== null) {
                    throw new McpException("Field '{$fieldName}' must be null", -32602);
                }
                break;
        }
    }

    /**
     * Validate field format.
     */
    protected function validateFieldFormat($value, string $format, string $fieldName)
    {
        switch ($format) {
            case 'email':
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new McpException("Field '{$fieldName}' must be a valid email address", -32602);
                }
                break;

            case 'url':
                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new McpException("Field '{$fieldName}' must be a valid URL", -32602);
                }
                break;

            case 'uri':
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $value)) {
                    throw new McpException("Field '{$fieldName}' must be a valid URI", -32602);
                }
                break;

            case 'date-time':
                try {
                    $date = new \DateTime($value);
                    $value = $date->format('c');
                } catch (\Exception $e) {
                    throw new McpException("Field '{$fieldName}' must be a valid ISO 8601 date-time", -32602);
                }
                break;

            case 'uuid':
                if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
                    throw new McpException("Field '{$fieldName}' must be a valid UUID", -32602);
                }
                break;
        }

        return $value;
    }

    /**
     * Validate field length.
     */
    protected function validateFieldLength($value, array $rules, string $fieldName): void
    {
        $length = is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0);

        if (isset($rules['min_length']) && $length < $rules['min_length']) {
            throw new McpException(
                "Field '{$fieldName}' must be at least {$rules['min_length']} characters/items long",
                -32602
            );
        }

        if (isset($rules['max_length']) && $length > $rules['max_length']) {
            throw new McpException(
                "Field '{$fieldName}' must be at most {$rules['max_length']} characters/items long",
                -32602
            );
        }
    }

    /**
     * Validate field numeric range.
     */
    protected function validateFieldRange($value, array $rules, string $fieldName): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $numericValue = is_string($value) ? (float) $value : $value;

        if (isset($rules['min']) && $numericValue < $rules['min']) {
            throw new McpException(
                "Field '{$fieldName}' must be at least {$rules['min']}",
                -32602
            );
        }

        if (isset($rules['max']) && $numericValue > $rules['max']) {
            throw new McpException(
                "Field '{$fieldName}' must be at most {$rules['max']}",
                -32602
            );
        }
    }

    /**
     * Validate field against enumerated values.
     */
    protected function validateFieldEnum($value, array $enum, string $fieldName): void
    {
        if (! in_array($value, $enum, true)) {
            $allowedValues = implode(', ', $enum);
            throw new McpException(
                "Field '{$fieldName}' must be one of: {$allowedValues}",
                -32602
            );
        }
    }

    /**
     * Validate field against regex pattern.
     */
    protected function validateFieldPattern($value, string $pattern, string $fieldName): void
    {
        if (! is_string($value) || ! preg_match($pattern, $value)) {
            throw new McpException(
                "Field '{$fieldName}' does not match the required pattern",
                -32602
            );
        }
    }

    /**
     * Check if a field is required.
     */
    protected function isRequired(array $rules): bool
    {
        return isset($rules['required']) && $rules['required'] === true;
    }

    /**
     * Get validation error summary.
     */
    protected function getValidationErrorSummary(array $errors): string
    {
        if (empty($errors)) {
            return 'No validation errors';
        }

        if (count($errors) === 1) {
            return $errors[0];
        }

        return 'Multiple validation errors: '.implode('; ', $errors);
    }

    /**
     * Validate parameters using JSON Schema validation.
     */
    protected function validateWithJsonSchema(array $parameters, array $schema): array
    {
        $validator = new SchemaValidator;

        return $validator->validate($parameters, $schema);
    }

    /**
     * Convert parameter schema to JSON Schema format.
     */
    protected function convertToJsonSchema(): array
    {
        if (! isset($this->parameterSchema)) {
            return [];
        }

        $jsonSchema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($this->parameterSchema as $name => $config) {
            $jsonSchema['properties'][$name] = $this->convertFieldToJsonSchema($config);

            if ($config['required'] ?? false) {
                $jsonSchema['required'][] = $name;
            }
        }

        return $jsonSchema;
    }

    /**
     * Convert a field configuration to JSON Schema.
     */
    protected function convertFieldToJsonSchema(array $config): array
    {
        $schema = [];

        // Set type
        if (isset($config['type'])) {
            $schema['type'] = $config['type'];
        }

        // Set description
        if (isset($config['description'])) {
            $schema['description'] = $config['description'];
        }

        // String constraints
        if (isset($config['minLength'])) {
            $schema['minLength'] = $config['minLength'];
        }
        if (isset($config['maxLength'])) {
            $schema['maxLength'] = $config['maxLength'];
        }
        if (isset($config['pattern'])) {
            $schema['pattern'] = $config['pattern'];
        }

        // Number constraints
        if (isset($config['minimum'])) {
            $schema['minimum'] = $config['minimum'];
        }
        if (isset($config['maximum'])) {
            $schema['maximum'] = $config['maximum'];
        }
        if (isset($config['exclusiveMinimum'])) {
            $schema['exclusiveMinimum'] = $config['exclusiveMinimum'];
        }
        if (isset($config['exclusiveMaximum'])) {
            $schema['exclusiveMaximum'] = $config['exclusiveMaximum'];
        }
        if (isset($config['multipleOf'])) {
            $schema['multipleOf'] = $config['multipleOf'];
        }

        // Array constraints
        if (isset($config['minItems'])) {
            $schema['minItems'] = $config['minItems'];
        }
        if (isset($config['maxItems'])) {
            $schema['maxItems'] = $config['maxItems'];
        }
        if (isset($config['uniqueItems'])) {
            $schema['uniqueItems'] = $config['uniqueItems'];
        }
        if (isset($config['items'])) {
            $schema['items'] = $config['items'];
        }

        // Other constraints
        if (isset($config['enum'])) {
            $schema['enum'] = $config['enum'];
        }
        if (isset($config['format'])) {
            $schema['format'] = $config['format'];
        }
        if (isset($config['default'])) {
            $schema['default'] = $config['default'];
        }

        return $schema;
    }
}
