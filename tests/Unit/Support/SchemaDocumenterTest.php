<?php

/**
 * Test file for SchemaDocumenter comprehensive unit tests.
 * Tests schema documentation functionality for MCP components.
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\SchemaDocumenter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('support')]
#[Group('schema-documenter')]
#[Group('ticket-019')]
class SchemaDocumenterTest extends TestCase
{
    protected SchemaDocumenter $documenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->documenter = new SchemaDocumenter;
    }

    #[Test]
    public function it_documents_string_schema(): void
    {
        $schema = [
            'type' => 'string',
            'description' => 'A simple string field',
        ];

        $documentation = $this->documenter->documentSchema($schema, 'String Field');

        $this->assertStringContainsString('### String Field', $documentation);
        $this->assertStringContainsString('A simple string field', $documentation);
        $this->assertStringContainsString('**Type:** `string`', $documentation);
        $this->assertStringContainsString('#### Example', $documentation);
        $this->assertStringContainsString('"example_string"', $documentation);
    }

    #[Test]
    public function it_documents_number_schema(): void
    {
        $schema = [
            'type' => 'number',
            'description' => 'A numeric value',
            'minimum' => 0,
            'maximum' => 100,
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `number`', $documentation);
        $this->assertStringContainsString('A numeric value', $documentation);
        $this->assertStringContainsString('**Validation:** Minimum: 0, Maximum: 100', $documentation);
        $this->assertStringContainsString('0', $documentation); // Example should use minimum value
    }

    #[Test]
    public function it_documents_integer_schema(): void
    {
        $schema = [
            'type' => 'integer',
            'minimum' => 1,
            'maximum' => 10,
            'multipleOf' => 2,
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `integer`', $documentation);
        $this->assertStringContainsString('**Validation:** Minimum: 1, Maximum: 10, Multiple of: 2', $documentation);
        $this->assertStringContainsString('1', $documentation); // Example should use minimum value
    }

    #[Test]
    public function it_documents_boolean_schema(): void
    {
        $schema = [
            'type' => 'boolean',
            'description' => 'A true/false value',
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `boolean`', $documentation);
        $this->assertStringContainsString('A true/false value', $documentation);
        $this->assertStringContainsString('true', $documentation);
    }

    #[Test]
    public function it_documents_object_schema_with_properties(): void
    {
        $schema = [
            'type' => 'object',
            'description' => 'A user object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'User name',
                ],
                'age' => [
                    'type' => 'integer',
                    'description' => 'User age',
                    'minimum' => 0,
                ],
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                    'description' => 'User email address',
                ],
            ],
            'required' => ['name', 'email'],
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('A user object', $documentation);
        $this->assertStringContainsString('#### Properties', $documentation);
        $this->assertStringContainsString('- **name** (`string`) _(required)_', $documentation);
        $this->assertStringContainsString('- **age** (`integer`) _(optional)_', $documentation);
        $this->assertStringContainsString('- **email** (`string`) _(required)_', $documentation);
        $this->assertStringContainsString('User name', $documentation);
        $this->assertStringContainsString('User age', $documentation);
        $this->assertStringContainsString('User email address', $documentation);
    }

    #[Test]
    public function it_documents_array_schema(): void
    {
        $schema = [
            'type' => 'array',
            'description' => 'A list of tags',
            'items' => [
                'type' => 'string',
            ],
            'minItems' => 1,
            'maxItems' => 10,
            'uniqueItems' => true,
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `array`', $documentation);
        $this->assertStringContainsString('A list of tags', $documentation);
        $this->assertStringContainsString('**Validation:** Minimum items: 1, Maximum items: 10, Items must be unique', $documentation);
        $this->assertStringContainsString('#### Array Items', $documentation);
        $this->assertStringContainsString('**Type:** `string`', $documentation);
    }

    #[Test]
    public function it_documents_array_schema_with_object_items(): void
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'title' => ['type' => 'string'],
                ],
                'required' => ['id'],
            ],
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `array`', $documentation);
        $this->assertStringContainsString('#### Array Items', $documentation);
        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('#### Properties', $documentation);
        $this->assertStringContainsString('- **id** (`integer`) _(required)_', $documentation);
        $this->assertStringContainsString('- **title** (`string`) _(optional)_', $documentation);
    }

    #[Test]
    public function it_documents_properties_with_empty_properties(): void
    {
        $documentation = $this->documenter->documentProperties([]);

        $this->assertEquals('_No properties defined._', $documentation);
    }

    #[Test]
    public function it_documents_properties_with_nested_objects(): void
    {
        $properties = [
            'address' => [
                'type' => 'object',
                'description' => 'User address',
                'properties' => [
                    'street' => [
                        'type' => 'string',
                        'description' => 'Street address',
                    ],
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name',
                    ],
                ],
                'required' => ['street'],
            ],
        ];

        $required = [];

        $documentation = $this->documenter->documentProperties($properties, $required);

        $this->assertStringContainsString('- **address** (`object`) _(optional)_', $documentation);
        $this->assertStringContainsString('User address', $documentation);
        $this->assertStringContainsString('- **street** (`string`) _(required)_', $documentation);
        $this->assertStringContainsString('- **city** (`string`) _(optional)_', $documentation);
        $this->assertStringContainsString('Street address', $documentation);
        $this->assertStringContainsString('City name', $documentation);
    }

    #[Test]
    public function it_documents_properties_with_nested_arrays(): void
    {
        $properties = [
            'tags' => [
                'type' => 'array',
                'description' => 'List of tags',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                    ],
                ],
            ],
        ];

        $documentation = $this->documenter->documentProperties($properties);

        $this->assertStringContainsString('- **tags** (`array`) _(optional)_', $documentation);
        $this->assertStringContainsString('List of tags', $documentation);
        $this->assertStringContainsString('Array of object items', $documentation);
        $this->assertStringContainsString('- **name** (`string`) _(optional)_', $documentation);
        $this->assertStringContainsString('- **color** (`string`) _(optional)_', $documentation);
    }

    #[Test]
    public function it_documents_validation_rules_for_string_type(): void
    {
        $schema = [
            'type' => 'string',
            'minLength' => 5,
            'maxLength' => 50,
            'pattern' => '^[A-Za-z]+$',
            'format' => 'email',
            'enum' => ['draft', 'published', 'archived'],
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertStringContainsString('Minimum length: 5', $validationRules);
        $this->assertStringContainsString('Maximum length: 50', $validationRules);
        $this->assertStringContainsString('Pattern: `^[A-Za-z]+$`', $validationRules);
        $this->assertStringContainsString('Format: email', $validationRules);
        $this->assertStringContainsString('Allowed values: `draft`, `published`, `archived`', $validationRules);
    }

    #[Test]
    public function it_documents_validation_rules_for_number_type(): void
    {
        $schema = [
            'type' => 'number',
            'minimum' => 10.5,
            'maximum' => 100.0,
            'exclusiveMinimum' => 0,
            'exclusiveMaximum' => 200,
            'multipleOf' => 0.5,
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertStringContainsString('Minimum: 10.5', $validationRules);
        $this->assertStringContainsString('Maximum: 100', $validationRules);
        $this->assertStringContainsString('Exclusive minimum: 0', $validationRules);
        $this->assertStringContainsString('Exclusive maximum: 200', $validationRules);
        $this->assertStringContainsString('Multiple of: 0.5', $validationRules);
    }

    #[Test]
    public function it_documents_validation_rules_for_array_type(): void
    {
        $schema = [
            'type' => 'array',
            'minItems' => 2,
            'maxItems' => 10,
            'uniqueItems' => true,
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertStringContainsString('Minimum items: 2', $validationRules);
        $this->assertStringContainsString('Maximum items: 10', $validationRules);
        $this->assertStringContainsString('Items must be unique', $validationRules);
    }

    #[Test]
    public function it_documents_validation_rules_for_object_type(): void
    {
        $schema = [
            'type' => 'object',
            'minProperties' => 1,
            'maxProperties' => 5,
            'additionalProperties' => false,
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertStringContainsString('Minimum properties: 1', $validationRules);
        $this->assertStringContainsString('Maximum properties: 5', $validationRules);
        $this->assertStringContainsString('No additional properties allowed', $validationRules);
    }

    #[Test]
    public function it_documents_general_validation_rules(): void
    {
        $schema = [
            'type' => 'string',
            'const' => 'fixed_value',
            'nullable' => true,
            'default' => 'default_value',
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertStringContainsString('Must equal: `fixed_value`', $validationRules);
        $this->assertStringContainsString('Nullable', $validationRules);
        $this->assertStringContainsString('Default: `default_value`', $validationRules);
    }

    #[Test]
    public function it_returns_empty_string_for_no_validation_rules(): void
    {
        $schema = [
            'type' => 'string',
        ];

        $validationRules = $this->documenter->documentValidationRules($schema);

        $this->assertEmpty($validationRules);
    }

    #[Test]
    public function it_formats_array_type(): void
    {
        $result = $this->documenter->formatType(['string', 'number']);

        $this->assertEquals('string|number', $result);
    }

    #[Test]
    public function it_formats_string_type(): void
    {
        $result = $this->documenter->formatType('boolean');

        $this->assertEquals('boolean', $result);
    }

    #[Test]
    public function it_formats_unknown_type(): void
    {
        $result = $this->documenter->formatType(123);

        $this->assertEquals('mixed', $result);
    }

    #[Test]
    public function it_generates_example_with_const_value(): void
    {
        $schema = [
            'type' => 'string',
            'const' => 'fixed_value',
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertStringContainsString('"fixed_value"', $example);
    }

    #[Test]
    public function it_generates_example_with_default_value(): void
    {
        $schema = [
            'type' => 'integer',
            'default' => 42,
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertStringContainsString('42', $example);
    }

    #[Test]
    public function it_generates_example_with_enum_value(): void
    {
        $schema = [
            'type' => 'string',
            'enum' => ['option1', 'option2', 'option3'],
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertStringContainsString('"option1"', $example);
    }

    #[Test]
    public function it_generates_example_for_string_with_format(): void
    {
        $emailSchema = ['type' => 'string', 'format' => 'email'];
        $uriSchema = ['type' => 'string', 'format' => 'uri'];
        $dateSchema = ['type' => 'string', 'format' => 'date'];
        $uuidSchema = ['type' => 'string', 'format' => 'uuid'];

        $emailExample = $this->documenter->generateExample($emailSchema);
        $uriExample = $this->documenter->generateExample($uriSchema);
        $dateExample = $this->documenter->generateExample($dateSchema);
        $uuidExample = $this->documenter->generateExample($uuidSchema);

        $this->assertStringContainsString('user@example.com', $emailExample);
        $this->assertStringContainsString('https://example.com', $uriExample);
        $this->assertStringContainsString('2024-01-01', $dateExample);
        $this->assertStringContainsString('123e4567-e89b-12d3-a456-426614174000', $uuidExample);
    }

    #[Test]
    public function it_generates_example_for_array(): void
    {
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'string',
            ],
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertStringContainsString('"example_string"', $example);
    }

    #[Test]
    public function it_generates_example_for_object(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
                'email' => ['type' => 'string', 'format' => 'email'],
                'optional' => ['type' => 'string'],
            ],
            'required' => ['name', 'email'],
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertStringContainsString('"name"', $example);
        $this->assertStringContainsString('"email"', $example);
        $this->assertStringContainsString('user@example.com', $example);
        // Should include required properties and some optional ones (up to 3 total)
    }

    #[Test]
    public function it_generates_empty_example_for_null_result(): void
    {
        $schema = [
            'type' => 'null',
        ];

        $example = $this->documenter->generateExample($schema);

        $this->assertEquals('', $example);
    }

    #[Test]
    public function it_returns_empty_string_for_no_example(): void
    {
        // Create a documenter that doesn't include examples
        $documenter = new SchemaDocumenter(['include_examples' => false]);

        $schema = ['type' => 'string'];
        $example = $documenter->generateExample($schema);

        $this->assertStringContainsString('"example_string"', $example);
    }

    #[Test]
    public function it_extracts_input_schema_from_metadata(): void
    {
        $metadata = [
            'input_schema' => [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
            ],
            'other_field' => 'value',
        ];

        $schema = $this->documenter->extractInputSchema($metadata);

        $this->assertEquals($metadata['input_schema'], $schema);
    }

    #[Test]
    public function it_returns_null_for_missing_input_schema(): void
    {
        $metadata = ['other_field' => 'value'];

        $schema = $this->documenter->extractInputSchema($metadata);

        $this->assertNull($schema);
    }

    #[Test]
    public function it_documents_tool_schema(): void
    {
        $metadata = [
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Search query'],
                ],
            ],
        ];

        $documentation = $this->documenter->documentToolSchema($metadata);

        $this->assertStringContainsString('### Tool Input Schema', $documentation);
        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('- **query** (`string`) _(optional)_', $documentation);
        $this->assertStringContainsString('Search query', $documentation);
    }

    #[Test]
    public function it_documents_tool_schema_with_no_schema(): void
    {
        $metadata = [];

        $documentation = $this->documenter->documentToolSchema($metadata);

        $this->assertEquals('_No input schema defined._', $documentation);
    }

    #[Test]
    public function it_documents_resource_schema(): void
    {
        $metadata = [
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Resource ID'],
                ],
            ],
        ];

        $documentation = $this->documenter->documentResourceSchema($metadata);

        $this->assertStringContainsString('### Resource Parameters Schema', $documentation);
        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('- **id** (`integer`) _(optional)_', $documentation);
        $this->assertStringContainsString('Resource ID', $documentation);
    }

    #[Test]
    public function it_documents_resource_schema_with_no_schema(): void
    {
        $metadata = [];

        $documentation = $this->documenter->documentResourceSchema($metadata);

        $this->assertEquals('_No input schema defined._', $documentation);
    }

    #[Test]
    public function it_documents_prompt_schema(): void
    {
        $metadata = [
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'topic' => ['type' => 'string', 'description' => 'Topic to write about'],
                ],
            ],
        ];

        $documentation = $this->documenter->documentPromptSchema($metadata);

        $this->assertStringContainsString('### Prompt Arguments Schema', $documentation);
        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('- **topic** (`string`) _(optional)_', $documentation);
        $this->assertStringContainsString('Topic to write about', $documentation);
    }

    #[Test]
    public function it_documents_prompt_schema_with_no_schema(): void
    {
        $metadata = [];

        $documentation = $this->documenter->documentPromptSchema($metadata);

        $this->assertEquals('_No input schema defined._', $documentation);
    }

    #[Test]
    public function it_respects_depth_limiting_for_nested_schemas(): void
    {
        $documenter = new SchemaDocumenter(['max_depth' => 1]);

        $properties = [
            'level1' => [
                'type' => 'object',
                'properties' => [
                    'level2' => [
                        'type' => 'object',
                        'properties' => [
                            'level3' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        $documentation = $documenter->documentProperties($properties);

        $this->assertStringContainsString('- **level1** (`object`) _(optional)_', $documentation);
        // Should not document level3 due to depth limit
        $this->assertStringNotContainsString('level3', $documentation);
    }

    #[Test]
    public function it_handles_empty_schema(): void
    {
        $documentation = $this->documenter->documentSchema([]);

        $this->assertStringContainsString('**Type:** `mixed`', $documentation);
    }

    #[Test]
    public function it_handles_invalid_schema_gracefully(): void
    {
        $schema = [
            'type' => 'unknown_type',
            'invalid_property' => 'value',
        ];

        $documentation = $this->documenter->documentSchema($schema);

        $this->assertStringContainsString('**Type:** `unknown_type`', $documentation);
        // Should not crash and produce some output
        $this->assertNotEmpty($documentation);
    }

    #[Test]
    public function it_gets_and_sets_options(): void
    {
        $options = $this->documenter->getOptions();

        $this->assertIsArray($options);
        $this->assertArrayHasKey('include_examples', $options);
        $this->assertTrue($options['include_examples']);

        $this->documenter->setOptions(['include_examples' => false, 'max_depth' => 5]);

        $updatedOptions = $this->documenter->getOptions();
        $this->assertFalse($updatedOptions['include_examples']);
        $this->assertEquals(5, $updatedOptions['max_depth']);
    }

    #[Test]
    public function it_gets_and_sets_templates(): void
    {
        $templates = $this->documenter->getTemplates();

        $this->assertIsArray($templates);
        $this->assertArrayHasKey('schema_header', $templates);
        $this->assertArrayHasKey('property', $templates);

        $this->documenter->setTemplate('custom_template', 'Custom: {value}');

        $updatedTemplates = $this->documenter->getTemplates();
        $this->assertArrayHasKey('custom_template', $updatedTemplates);
        $this->assertEquals('Custom: {value}', $updatedTemplates['custom_template']);
    }

    #[Test]
    public function it_disables_validation_rules_when_option_is_false(): void
    {
        $documenter = new SchemaDocumenter(['include_validation_rules' => false]);

        $schema = [
            'type' => 'string',
            'minLength' => 5,
            'maxLength' => 50,
        ];

        $documentation = $documenter->documentSchema($schema);

        $this->assertStringNotContainsString('**Validation:**', $documentation);
        $this->assertStringNotContainsString('Minimum length', $documentation);
    }

    #[Test]
    public function it_disables_nested_schemas_when_option_is_false(): void
    {
        $documenter = new SchemaDocumenter(['include_nested_schemas' => false]);

        $properties = [
            'nested' => [
                'type' => 'object',
                'properties' => [
                    'inner' => ['type' => 'string'],
                ],
            ],
        ];

        $documentation = $documenter->documentProperties($properties);

        $this->assertStringContainsString('- **nested** (`object`) _(optional)_', $documentation);
        $this->assertStringNotContainsString('inner', $documentation);
    }

    #[Test]
    public function it_generates_markdown_formatted_output(): void
    {
        $schema = [
            'type' => 'object',
            'description' => 'Test schema',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
        ];

        $documentation = $this->documenter->documentSchema($schema, 'Test Schema');

        // Check for proper markdown formatting
        $this->assertStringContainsString('### Test Schema', $documentation);
        $this->assertStringContainsString('**Type:** `object`', $documentation);
        $this->assertStringContainsString('#### Properties', $documentation);
        $this->assertStringContainsString('#### Example', $documentation);
        $this->assertStringContainsString('```json', $documentation);
        $this->assertStringContainsString('```', $documentation);
    }
}
