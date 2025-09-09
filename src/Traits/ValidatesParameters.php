<?php

namespace JTD\LaravelMCP\Traits;

use JTD\LaravelMCP\Exceptions\ProtocolException;

/**
 * Trait for parameter validation in MCP components.
 *
 * This trait provides comprehensive parameter validation functionality
 * for MCP tools, resources, and prompts. It includes type checking,
 * format validation, and schema-based validation.
 */
trait ValidatesParameters
{
    /**
     * Validate parameters against a schema.
     *
     * @param  array  $params  Parameters to validate
     * @param  array  $schema  Validation schema
     * @return array Validated and processed parameters
     *
     * @throws ProtocolException If validation fails
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
            } catch (ProtocolException $e) {
                $errors[] = $e->getMessage();
            }
        }

        if (! empty($errors)) {
            throw new ProtocolException(
                'Parameter validation failed: '.implode(', ', $errors),
                -32602
            );
        }

        return $validated;
    }

    /**
     * Validate a single field value.
     *
     * @param  mixed  $value  Field value
     * @param  array  $rules  Validation rules
     * @param  string  $fieldName  Field name for error messages
     * @return mixed Validated value
     *
     * @throws ProtocolException If validation fails
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
     *
     * @param  mixed  $value  Field value
     * @param  string  $expectedType  Expected type
     * @param  string  $fieldName  Field name
     *
     * @throws ProtocolException If type validation fails
     */
    protected function validateFieldType($value, string $expectedType, string $fieldName): void
    {
        switch ($expectedType) {
            case 'string':
                if (! is_string($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a string", -32602);
                }
                break;

            case 'integer':
            case 'int':
                if (! is_int($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be an integer", -32602);
                }
                break;

            case 'number':
                if (! is_numeric($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a number", -32602);
                }
                break;

            case 'boolean':
            case 'bool':
                if (! is_bool($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a boolean", -32602);
                }
                break;

            case 'array':
                if (! is_array($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be an array", -32602);
                }
                break;

            case 'object':
                if (! is_array($value) && ! is_object($value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be an object", -32602);
                }
                break;

            case 'null':
                if ($value !== null) {
                    throw new ProtocolException("Field '{$fieldName}' must be null", -32602);
                }
                break;
        }
    }

    /**
     * Validate field format.
     *
     * @param  mixed  $value  Field value
     * @param  string  $format  Expected format
     * @param  string  $fieldName  Field name
     * @return mixed Processed value
     *
     * @throws ProtocolException If format validation fails
     */
    protected function validateFieldFormat($value, string $format, string $fieldName)
    {
        switch ($format) {
            case 'email':
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a valid email address", -32602);
                }
                break;

            case 'url':
                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a valid URL", -32602);
                }
                break;

            case 'uri':
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9+.-]*:/', $value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a valid URI", -32602);
                }
                break;

            case 'date-time':
                try {
                    $date = new \DateTime($value);
                    $value = $date->format('c');
                } catch (\Exception $e) {
                    throw new ProtocolException("Field '{$fieldName}' must be a valid ISO 8601 date-time", -32602);
                }
                break;

            case 'uuid':
                if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value)) {
                    throw new ProtocolException("Field '{$fieldName}' must be a valid UUID", -32602);
                }
                break;
        }

        return $value;
    }

    /**
     * Validate field length.
     *
     * @param  mixed  $value  Field value
     * @param  array  $rules  Validation rules
     * @param  string  $fieldName  Field name
     *
     * @throws ProtocolException If length validation fails
     */
    protected function validateFieldLength($value, array $rules, string $fieldName): void
    {
        $length = is_string($value) ? strlen($value) : (is_array($value) ? count($value) : 0);

        if (isset($rules['min_length']) && $length < $rules['min_length']) {
            throw new ProtocolException(
                "Field '{$fieldName}' must be at least {$rules['min_length']} characters/items long",
                -32602
            );
        }

        if (isset($rules['max_length']) && $length > $rules['max_length']) {
            throw new ProtocolException(
                "Field '{$fieldName}' must be at most {$rules['max_length']} characters/items long",
                -32602
            );
        }
    }

    /**
     * Validate field numeric range.
     *
     * @param  mixed  $value  Field value
     * @param  array  $rules  Validation rules
     * @param  string  $fieldName  Field name
     *
     * @throws ProtocolException If range validation fails
     */
    protected function validateFieldRange($value, array $rules, string $fieldName): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $numericValue = is_string($value) ? (float) $value : $value;

        if (isset($rules['min']) && $numericValue < $rules['min']) {
            throw new ProtocolException(
                "Field '{$fieldName}' must be at least {$rules['min']}",
                -32602
            );
        }

        if (isset($rules['max']) && $numericValue > $rules['max']) {
            throw new ProtocolException(
                "Field '{$fieldName}' must be at most {$rules['max']}",
                -32602
            );
        }
    }

    /**
     * Validate field against enumerated values.
     *
     * @param  mixed  $value  Field value
     * @param  array  $enum  Allowed values
     * @param  string  $fieldName  Field name
     *
     * @throws ProtocolException If enum validation fails
     */
    protected function validateFieldEnum($value, array $enum, string $fieldName): void
    {
        if (! in_array($value, $enum, true)) {
            $allowedValues = implode(', ', $enum);
            throw new ProtocolException(
                "Field '{$fieldName}' must be one of: {$allowedValues}",
                -32602
            );
        }
    }

    /**
     * Validate field against regex pattern.
     *
     * @param  mixed  $value  Field value
     * @param  string  $pattern  Regex pattern
     * @param  string  $fieldName  Field name
     *
     * @throws ProtocolException If pattern validation fails
     */
    protected function validateFieldPattern($value, string $pattern, string $fieldName): void
    {
        if (! is_string($value) || ! preg_match($pattern, $value)) {
            throw new ProtocolException(
                "Field '{$fieldName}' does not match the required pattern",
                -32602
            );
        }
    }

    /**
     * Check if a field is required.
     *
     * @param  array  $rules  Field validation rules
     */
    protected function isRequired(array $rules): bool
    {
        return isset($rules['required']) && $rules['required'] === true;
    }

    /**
     * Get validation error summary.
     *
     * @param  array  $errors  Array of validation errors
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
}
