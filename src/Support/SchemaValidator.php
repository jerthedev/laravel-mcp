<?php

namespace JTD\LaravelMCP\Support;

use JTD\LaravelMCP\Exceptions\McpException;

/**
 * JSON Schema validator for MCP components.
 *
 * This class provides JSON Schema Draft 7 validation functionality
 * for validating MCP component parameters against defined schemas.
 */
class SchemaValidator
{
    /**
     * Validate data against a JSON Schema.
     *
     * @param  mixed  $data  The data to validate
     * @param  array  $schema  The JSON Schema
     * @return array The validated data
     *
     * @throws McpException If validation fails
     */
    public function validate($data, array $schema): array
    {
        $errors = [];
        $validated = $this->validateValue($data, $schema, '', $errors);

        if (! empty($errors)) {
            throw new McpException(
                'Schema validation failed: '.implode(', ', $errors),
                -32602
            );
        }

        return is_array($validated) ? $validated : [$validated];
    }

    /**
     * Validate a single value against a schema.
     *
     * @param  mixed  $value  The value to validate
     * @param  array  $schema  The schema for this value
     * @param  string  $path  The current path for error reporting
     * @param  array  $errors  Array to collect errors
     * @return mixed The validated value
     */
    protected function validateValue($value, array $schema, string $path, array &$errors)
    {
        // Handle null values
        if ($value === null) {
            if (! ($schema['nullable'] ?? false)) {
                $errors[] = "{$path} cannot be null";
            }

            return $value;
        }

        // Validate type
        if (isset($schema['type'])) {
            if (! $this->validateType($value, $schema['type'], $path, $errors)) {
                return $value;
            }
        }

        // Validate based on type
        $type = $schema['type'] ?? $this->detectType($value);

        switch ($type) {
            case 'object':
                return $this->validateObject($value, $schema, $path, $errors);

            case 'array':
                return $this->validateArray($value, $schema, $path, $errors);

            case 'string':
                return $this->validateString($value, $schema, $path, $errors);

            case 'number':
            case 'integer':
                return $this->validateNumber($value, $schema, $path, $errors);

            case 'boolean':
                return $this->validateBoolean($value, $schema, $path, $errors);

            default:
                return $value;
        }
    }

    /**
     * Validate value type.
     */
    protected function validateType($value, $type, string $path, array &$errors): bool
    {
        $actualType = $this->detectType($value);

        if (is_array($type)) {
            if (! in_array($actualType, $type)) {
                $errors[] = "{$path} must be one of types: ".implode(', ', $type);

                return false;
            }
        } elseif ($actualType !== $type) {
            // Allow integers for number type
            if (! ($type === 'number' && $actualType === 'integer')) {
                $pathPrefix = $path ? "{$path} " : '';
                $errors[] = "{$pathPrefix}must be of type {$type}, got {$actualType}";

                return false;
            }
        }

        return true;
    }

    /**
     * Detect the JSON Schema type of a value.
     */
    protected function detectType($value): string
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'number';
        }
        if (is_string($value)) {
            return 'string';
        }
        if (is_array($value)) {
            // Empty arrays are treated as objects for JSON compatibility
            if (empty($value)) {
                return 'object';
            }

            // Check if associative array (object) or indexed array
            return array_values($value) === $value ? 'array' : 'object';
        }
        if (is_object($value)) {
            return 'object';
        }

        return 'unknown';
    }

    /**
     * Validate an object.
     */
    protected function validateObject($value, array $schema, string $path, array &$errors): array
    {
        if (! is_array($value) && ! is_object($value)) {
            $errors[] = "{$path} must be an object";

            return [];
        }

        $value = (array) $value;
        $validated = [];

        // Validate properties
        if (isset($schema['properties'])) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                $propertyPath = $path ? "{$path}.{$property}" : $property;

                if (isset($value[$property])) {
                    $validated[$property] = $this->validateValue(
                        $value[$property],
                        $propertySchema,
                        $propertyPath,
                        $errors
                    );
                } elseif (isset($propertySchema['default'])) {
                    $validated[$property] = $propertySchema['default'];
                }
            }
        }

        // Check required properties
        if (isset($schema['required'])) {
            foreach ($schema['required'] as $required) {
                if (! isset($validated[$required]) && ! isset($value[$required])) {
                    $prefix = $path ? "{$path} is " : '';
                    $errors[] = "{$prefix}missing required property: {$required}";
                }
            }
        }

        // Validate additional properties
        if (isset($schema['additionalProperties'])) {
            if ($schema['additionalProperties'] === false) {
                $defined = array_keys($schema['properties'] ?? []);
                $extra = array_diff(array_keys($value), $defined);
                if (! empty($extra)) {
                    $errors[] = "{$path} has unexpected properties: ".implode(', ', $extra);
                }
            } elseif (is_array($schema['additionalProperties'])) {
                // Validate additional properties against schema
                foreach ($value as $key => $val) {
                    if (! isset($schema['properties'][$key])) {
                        $validated[$key] = $this->validateValue(
                            $val,
                            $schema['additionalProperties'],
                            "{$path}.{$key}",
                            $errors
                        );
                    }
                }
            }
        } else {
            // Include all properties if additionalProperties not specified
            foreach ($value as $key => $val) {
                if (! isset($validated[$key])) {
                    $validated[$key] = $val;
                }
            }
        }

        return $validated;
    }

    /**
     * Validate an array.
     */
    protected function validateArray($value, array $schema, string $path, array &$errors): array
    {
        if (! is_array($value)) {
            $errors[] = "{$path} must be an array";

            return [];
        }

        $validated = [];

        // Validate array items
        if (isset($schema['items'])) {
            foreach ($value as $index => $item) {
                $validated[] = $this->validateValue(
                    $item,
                    $schema['items'],
                    "{$path}[{$index}]",
                    $errors
                );
            }
        } else {
            $validated = $value;
        }

        // Validate array length
        if (isset($schema['minItems']) && count($validated) < $schema['minItems']) {
            $errors[] = "{$path} must have at least {$schema['minItems']} items";
        }

        if (isset($schema['maxItems']) && count($validated) > $schema['maxItems']) {
            $errors[] = "{$path} must have at most {$schema['maxItems']} items";
        }

        // Validate unique items
        if (isset($schema['uniqueItems']) && $schema['uniqueItems']) {
            $serialized = array_map('serialize', $validated);
            if (count($serialized) !== count(array_unique($serialized))) {
                $errors[] = "{$path} must have unique items";
            }
        }

        return $validated;
    }

    /**
     * Validate a string.
     */
    protected function validateString($value, array $schema, string $path, array &$errors): string
    {
        if (! is_string($value)) {
            $errors[] = "{$path} must be a string";

            return '';
        }

        // Validate string length
        if (isset($schema['minLength']) && strlen($value) < $schema['minLength']) {
            $errors[] = "{$path} must be at least {$schema['minLength']} characters";
        }

        if (isset($schema['maxLength']) && strlen($value) > $schema['maxLength']) {
            $errors[] = "{$path} must be at most {$schema['maxLength']} characters";
        }

        // Validate pattern
        if (isset($schema['pattern'])) {
            $pattern = '/'.$schema['pattern'].'/';
            if (! preg_match($pattern, $value)) {
                $errors[] = "{$path} does not match required pattern";
            }
        }

        // Validate format
        if (isset($schema['format'])) {
            $this->validateFormat($value, $schema['format'], $path, $errors);
        }

        // Validate enum
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} must be one of: ".implode(', ', $schema['enum']);
        }

        return $value;
    }

    /**
     * Validate a number.
     */
    protected function validateNumber($value, array $schema, string $path, array &$errors)
    {
        if (! is_numeric($value)) {
            $errors[] = "{$path} must be a number";

            return 0;
        }

        $type = $schema['type'] ?? 'number';
        if ($type === 'integer' && ! is_int($value)) {
            $errors[] = "{$path} must be an integer";
        }

        // Validate range
        if (isset($schema['minimum'])) {
            if (isset($schema['exclusiveMinimum']) && $schema['exclusiveMinimum']) {
                if ($value <= $schema['minimum']) {
                    $errors[] = "{$path} must be greater than {$schema['minimum']}";
                }
            } elseif ($value < $schema['minimum']) {
                $errors[] = "{$path} must be at least {$schema['minimum']}";
            }
        }

        if (isset($schema['maximum'])) {
            if (isset($schema['exclusiveMaximum']) && $schema['exclusiveMaximum']) {
                if ($value >= $schema['maximum']) {
                    $errors[] = "{$path} must be less than {$schema['maximum']}";
                }
            } elseif ($value > $schema['maximum']) {
                $errors[] = "{$path} must be at most {$schema['maximum']}";
            }
        }

        // Validate multiple of
        if (isset($schema['multipleOf'])) {
            $remainder = fmod($value, $schema['multipleOf']);
            if ($remainder != 0) {
                $errors[] = "{$path} must be a multiple of {$schema['multipleOf']}";
            }
        }

        // Validate enum
        if (isset($schema['enum']) && ! in_array($value, $schema['enum'], true)) {
            $errors[] = "{$path} must be one of: ".implode(', ', $schema['enum']);
        }

        return $value;
    }

    /**
     * Validate a boolean.
     */
    protected function validateBoolean($value, array $schema, string $path, array &$errors): bool
    {
        if (! is_bool($value)) {
            $errors[] = "{$path} must be a boolean";

            return false;
        }

        return $value;
    }

    /**
     * Validate string format.
     */
    protected function validateFormat(string $value, string $format, string $path, array &$errors): void
    {
        switch ($format) {
            case 'date-time':
                if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                    $errors[] = "{$path} must be a valid ISO 8601 date-time";
                }
                break;

            case 'date':
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $errors[] = "{$path} must be a valid date (YYYY-MM-DD)";
                }
                break;

            case 'time':
                if (! preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
                    $errors[] = "{$path} must be a valid time (HH:MM:SS)";
                }
                break;

            case 'email':
                if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "{$path} must be a valid email address";
                }
                break;

            case 'hostname':
                if (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                    $errors[] = "{$path} must be a valid hostname";
                }
                break;

            case 'ipv4':
                if (! filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $errors[] = "{$path} must be a valid IPv4 address";
                }
                break;

            case 'ipv6':
                if (! filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $errors[] = "{$path} must be a valid IPv6 address";
                }
                break;

            case 'uri':
            case 'url':
                if (! filter_var($value, FILTER_VALIDATE_URL)) {
                    $errors[] = "{$path} must be a valid URL";
                }
                break;

            case 'uuid':
                $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
                if (! preg_match($pattern, $value)) {
                    $errors[] = "{$path} must be a valid UUID";
                }
                break;

            case 'json-pointer':
                if (! preg_match('/^(\/[^\/~]*(~[01][^\/~]*)*)*$/', $value)) {
                    $errors[] = "{$path} must be a valid JSON pointer";
                }
                break;
        }
    }

    /**
     * Create a schema from Laravel validation rules.
     */
    public static function fromLaravelRules(array $rules): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [],
            'required' => [],
        ];

        foreach ($rules as $field => $rule) {
            $fieldSchema = self::parseRule($rule);
            $schema['properties'][$field] = $fieldSchema;

            if (self::isRequired($rule)) {
                $schema['required'][] = $field;
            }
        }

        return $schema;
    }

    /**
     * Parse a Laravel validation rule into a JSON Schema.
     */
    protected static function parseRule($rule): array
    {
        $rules = is_string($rule) ? explode('|', $rule) : $rule;
        $schema = [];

        foreach ($rules as $r) {
            if (is_string($r)) {
                self::parseRuleString($r, $schema);
            }
        }

        return $schema;
    }

    /**
     * Parse a single rule string.
     */
    protected static function parseRuleString(string $rule, array &$schema): void
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameters = isset($parts[1]) ? explode(',', $parts[1]) : [];

        switch ($ruleName) {
            case 'string':
                $schema['type'] = 'string';
                break;

            case 'integer':
            case 'int':
                $schema['type'] = 'integer';
                break;

            case 'numeric':
                $schema['type'] = 'number';
                break;

            case 'boolean':
            case 'bool':
                $schema['type'] = 'boolean';
                break;

            case 'array':
                $schema['type'] = 'array';
                break;

            case 'email':
                $schema['format'] = 'email';
                break;

            case 'url':
                $schema['format'] = 'url';
                break;

            case 'uuid':
                $schema['format'] = 'uuid';
                break;

            case 'min':
                if (isset($schema['type']) && in_array($schema['type'], ['integer', 'number'])) {
                    $schema['minimum'] = (float) $parameters[0];
                } else {
                    $schema['minLength'] = (int) $parameters[0];
                }
                break;

            case 'max':
                if (isset($schema['type']) && in_array($schema['type'], ['integer', 'number'])) {
                    $schema['maximum'] = (float) $parameters[0];
                } else {
                    $schema['maxLength'] = (int) $parameters[0];
                }
                break;

            case 'in':
                $schema['enum'] = $parameters;
                break;

            case 'regex':
                $schema['pattern'] = $parameters[0];
                break;
        }
    }

    /**
     * Check if a rule indicates a required field.
     */
    protected static function isRequired($rule): bool
    {
        $rules = is_string($rule) ? explode('|', $rule) : $rule;

        foreach ($rules as $r) {
            if ($r === 'required' || str_starts_with($r, 'required')) {
                return true;
            }
        }

        return false;
    }
}
