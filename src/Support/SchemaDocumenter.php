<?php

namespace JTD\LaravelMCP\Support;

/**
 * Schema documentation generator for MCP components.
 *
 * This class extracts and formats JSON Schema documentation,
 * generating readable markdown documentation from schemas
 * for Tools, Resources, and Prompts.
 */
class SchemaDocumenter
{
    /**
     * Documentation templates.
     */
    protected array $templates = [];

    /**
     * Documentation options.
     */
    protected array $options = [
        'include_examples' => true,
        'include_validation_rules' => true,
        'include_type_details' => true,
        'include_nested_schemas' => true,
        'max_depth' => 10,
    ];

    /**
     * Current depth for nested documentation.
     */
    protected int $currentDepth = 0;

    /**
     * Create a new schema documenter instance.
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->initializeTemplates();
    }

    /**
     * Document a JSON schema and return markdown formatted documentation.
     *
     * @param  array  $schema  The JSON schema to document
     * @param  string  $title  Optional title for the schema documentation
     * @return string The formatted markdown documentation
     */
    public function documentSchema(array $schema, string $title = ''): string
    {
        $this->currentDepth = 0;

        $markdown = [];

        if (! empty($title)) {
            $markdown[] = "### {$title}";
            $markdown[] = '';
        }

        // Add schema description if available
        if (! empty($schema['description'])) {
            $markdown[] = $schema['description'];
            $markdown[] = '';
        }

        // Document schema type and basic information
        $type = $this->formatType($schema['type'] ?? 'mixed');
        $markdown[] = "**Type:** {$type}";

        // Add validation rules if enabled
        if ($this->options['include_validation_rules']) {
            $validationRules = $this->documentValidationRules($schema);
            if (! empty($validationRules)) {
                $markdown[] = '';
                $markdown[] = $validationRules;
            }
        }

        // Document properties for object types
        if (($schema['type'] ?? '') === 'object' && ! empty($schema['properties'])) {
            $markdown[] = '';
            $markdown[] = '#### Properties';
            $markdown[] = '';
            $markdown[] = $this->documentProperties($schema['properties'], $schema['required'] ?? []);
        }

        // Document array items
        if (($schema['type'] ?? '') === 'array' && ! empty($schema['items'])) {
            $markdown[] = '';
            $markdown[] = '#### Array Items';
            $markdown[] = '';
            $this->currentDepth++;
            $markdown[] = $this->documentSchema($schema['items'], '');
            $this->currentDepth--;
        }

        // Generate example if enabled
        if ($this->options['include_examples']) {
            $example = $this->generateExample($schema);
            if (! empty($example)) {
                $markdown[] = '';
                $markdown[] = '#### Example';
                $markdown[] = '';
                $markdown[] = '```json';
                $markdown[] = $example;
                $markdown[] = '```';
            }
        }

        return implode("\n", $markdown);
    }

    /**
     * Document schema properties.
     *
     * @param  array  $properties  The properties to document
     * @param  array  $required  List of required property names
     * @return string The formatted properties documentation
     */
    public function documentProperties(array $properties, array $required = []): string
    {
        if (empty($properties)) {
            return '_No properties defined._';
        }

        $markdown = [];

        foreach ($properties as $name => $property) {
            $isRequired = in_array($name, $required);
            $requiredText = $isRequired ? ' _(required)_' : ' _(optional)_';

            $type = $this->formatType($property['type'] ?? 'mixed');
            $description = $property['description'] ?? '';

            $markdown[] = "- **{$name}** (`{$type}`){$requiredText}";

            if (! empty($description)) {
                $markdown[] = "  {$description}";
            }

            // Add validation details for this property
            if ($this->options['include_validation_rules']) {
                $validationRules = $this->documentValidationRules($property);
                if (! empty($validationRules)) {
                    $indentedRules = '  '.str_replace("\n", "\n  ", trim($validationRules));
                    $markdown[] = $indentedRules;
                }
            }

            // Handle nested objects and arrays
            if ($this->options['include_nested_schemas'] && $this->currentDepth < $this->options['max_depth']) {
                if (($property['type'] ?? '') === 'object' && ! empty($property['properties'])) {
                    $this->currentDepth++;
                    $nestedProps = $this->documentProperties($property['properties'], $property['required'] ?? []);
                    $indentedProps = '  '.str_replace("\n", "\n  ", trim($nestedProps));
                    $markdown[] = $indentedProps;
                    $this->currentDepth--;
                } elseif (($property['type'] ?? '') === 'array' && ! empty($property['items'])) {
                    $itemType = $this->formatType($property['items']['type'] ?? 'mixed');
                    $markdown[] = "  Array of {$itemType} items";

                    if (($property['items']['type'] ?? '') === 'object' && ! empty($property['items']['properties'])) {
                        $this->currentDepth++;
                        $nestedProps = $this->documentProperties($property['items']['properties'], $property['items']['required'] ?? []);
                        $indentedProps = '    '.str_replace("\n", "\n    ", trim($nestedProps));
                        $markdown[] = $indentedProps;
                        $this->currentDepth--;
                    }
                }
            }

            $markdown[] = '';
        }

        return implode("\n", array_slice($markdown, 0, -1)); // Remove last empty line
    }

    /**
     * Document validation rules for a schema.
     *
     * @param  array  $schema  The schema to extract validation rules from
     * @return string The formatted validation rules
     */
    public function documentValidationRules(array $schema): string
    {
        $rules = [];

        // Type-specific validation
        $type = $schema['type'] ?? null;

        if ($type === 'string') {
            if (isset($schema['minLength'])) {
                $rules[] = "Minimum length: {$schema['minLength']}";
            }
            if (isset($schema['maxLength'])) {
                $rules[] = "Maximum length: {$schema['maxLength']}";
            }
            if (isset($schema['pattern'])) {
                $rules[] = "Pattern: `{$schema['pattern']}`";
            }
            if (isset($schema['format'])) {
                $rules[] = "Format: {$schema['format']}";
            }
            if (isset($schema['enum'])) {
                $enumValues = array_map(fn ($v) => "`{$v}`", $schema['enum']);
                $rules[] = 'Allowed values: '.implode(', ', $enumValues);
            }
        } elseif ($type === 'number' || $type === 'integer') {
            if (isset($schema['minimum'])) {
                $rules[] = "Minimum: {$schema['minimum']}";
            }
            if (isset($schema['maximum'])) {
                $rules[] = "Maximum: {$schema['maximum']}";
            }
            if (isset($schema['exclusiveMinimum'])) {
                $rules[] = "Exclusive minimum: {$schema['exclusiveMinimum']}";
            }
            if (isset($schema['exclusiveMaximum'])) {
                $rules[] = "Exclusive maximum: {$schema['exclusiveMaximum']}";
            }
            if (isset($schema['multipleOf'])) {
                $rules[] = "Multiple of: {$schema['multipleOf']}";
            }
        } elseif ($type === 'array') {
            if (isset($schema['minItems'])) {
                $rules[] = "Minimum items: {$schema['minItems']}";
            }
            if (isset($schema['maxItems'])) {
                $rules[] = "Maximum items: {$schema['maxItems']}";
            }
            if (isset($schema['uniqueItems']) && $schema['uniqueItems']) {
                $rules[] = 'Items must be unique';
            }
        } elseif ($type === 'object') {
            if (isset($schema['minProperties'])) {
                $rules[] = "Minimum properties: {$schema['minProperties']}";
            }
            if (isset($schema['maxProperties'])) {
                $rules[] = "Maximum properties: {$schema['maxProperties']}";
            }
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $rules[] = 'No additional properties allowed';
            }
        }

        // General validation rules
        if (isset($schema['const'])) {
            $constValue = is_string($schema['const']) ? "`{$schema['const']}`" : json_encode($schema['const']);
            $rules[] = "Must equal: {$constValue}";
        }

        if (isset($schema['nullable']) && $schema['nullable']) {
            $rules[] = 'Nullable';
        }

        if (isset($schema['default'])) {
            $defaultValue = is_string($schema['default']) ? "`{$schema['default']}`" : json_encode($schema['default']);
            $rules[] = "Default: {$defaultValue}";
        }

        return empty($rules) ? '' : '**Validation:** '.implode(', ', $rules);
    }

    /**
     * Format type information for display.
     *
     * @param  mixed  $type  The type to format
     * @return string The formatted type string
     */
    public function formatType($type): string
    {
        if (is_array($type)) {
            return implode('|', $type);
        }

        if (is_string($type)) {
            return $type;
        }

        return 'mixed';
    }

    /**
     * Generate example JSON based on schema.
     *
     * @param  array  $schema  The schema to generate an example for
     * @return string The JSON example
     */
    public function generateExample(array $schema): string
    {
        $example = $this->generateExampleValue($schema);

        if ($example === null) {
            return '';
        }

        return json_encode($example, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate example value based on schema type.
     *
     * @param  array  $schema  The schema to generate example for
     * @return mixed The example value
     */
    protected function generateExampleValue(array $schema)
    {
        // Use const if available
        if (isset($schema['const'])) {
            return $schema['const'];
        }

        // Use default if available
        if (isset($schema['default'])) {
            return $schema['default'];
        }

        // Use enum first value if available
        if (isset($schema['enum']) && ! empty($schema['enum'])) {
            return $schema['enum'][0];
        }

        $type = $schema['type'] ?? 'string';

        switch ($type) {
            case 'string':
                if (isset($schema['format'])) {
                    return $this->generateExampleByFormat($schema['format']);
                }

                return 'example_string';

            case 'number':
                return isset($schema['minimum']) ? $schema['minimum'] : 42.5;

            case 'integer':
                return isset($schema['minimum']) ? (int) $schema['minimum'] : 42;

            case 'boolean':
                return true;

            case 'array':
                if (isset($schema['items'])) {
                    $itemExample = $this->generateExampleValue($schema['items']);

                    return [$itemExample];
                }

                return [];

            case 'object':
                $example = [];
                if (isset($schema['properties'])) {
                    $required = $schema['required'] ?? [];
                    foreach ($schema['properties'] as $propName => $propSchema) {
                        // Include required properties and some optional ones
                        if (in_array($propName, $required) || count($example) < 3) {
                            $example[$propName] = $this->generateExampleValue($propSchema);
                        }
                    }
                }

                return $example;

            case 'null':
                return null;

            default:
                return null;
        }
    }

    /**
     * Generate example value based on string format.
     *
     * @param  string  $format  The string format
     * @return string The example value
     */
    protected function generateExampleByFormat(string $format): string
    {
        return match ($format) {
            'email' => 'user@example.com',
            'uri', 'url' => 'https://example.com',
            'date' => '2024-01-01',
            'time' => '12:00:00',
            'date-time' => '2024-01-01T12:00:00Z',
            'uuid' => '123e4567-e89b-12d3-a456-426614174000',
            'ipv4' => '192.168.1.1',
            'ipv6' => '2001:db8::1',
            'hostname' => 'example.com',
            'regex' => '^[a-zA-Z]+$',
            'password' => '••••••••',
            default => 'example_'.$format,
        };
    }

    /**
     * Extract input schema from MCP component metadata.
     *
     * @param  array  $metadata  Component metadata
     * @return array|null The input schema if found
     */
    public function extractInputSchema(array $metadata): ?array
    {
        return $metadata['input_schema'] ?? null;
    }

    /**
     * Document MCP Tool schema.
     *
     * @param  array  $toolMetadata  Tool metadata containing input_schema
     * @return string The documented schema
     */
    public function documentToolSchema(array $toolMetadata): string
    {
        $inputSchema = $this->extractInputSchema($toolMetadata);

        if (empty($inputSchema)) {
            return '_No input schema defined._';
        }

        return $this->documentSchema($inputSchema, 'Tool Input Schema');
    }

    /**
     * Document MCP Resource schema.
     *
     * @param  array  $resourceMetadata  Resource metadata
     * @return string The documented schema
     */
    public function documentResourceSchema(array $resourceMetadata): string
    {
        $inputSchema = $this->extractInputSchema($resourceMetadata);

        if (empty($inputSchema)) {
            return '_No input schema defined._';
        }

        return $this->documentSchema($inputSchema, 'Resource Parameters Schema');
    }

    /**
     * Document MCP Prompt schema.
     *
     * @param  array  $promptMetadata  Prompt metadata
     * @return string The documented schema
     */
    public function documentPromptSchema(array $promptMetadata): string
    {
        $inputSchema = $this->extractInputSchema($promptMetadata);

        if (empty($inputSchema)) {
            return '_No input schema defined._';
        }

        return $this->documentSchema($inputSchema, 'Prompt Arguments Schema');
    }

    /**
     * Set documentation options.
     *
     * @param  array  $options  Options to set
     */
    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Get documentation options.
     *
     * @return array The current options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Initialize documentation templates.
     */
    protected function initializeTemplates(): void
    {
        $this->templates = [
            'schema_header' => '### {title}',
            'property' => '- **{name}** (`{type}`){required}: {description}',
            'validation_rule' => '**Validation:** {rules}',
            'example_header' => '#### Example',
            'type_info' => '**Type:** {type}',
        ];
    }

    /**
     * Get documentation templates.
     *
     * @return array The templates
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Set documentation template.
     *
     * @param  string  $key  Template key
     * @param  string  $template  Template content
     */
    public function setTemplate(string $key, string $template): void
    {
        $this->templates[$key] = $template;
    }
}
