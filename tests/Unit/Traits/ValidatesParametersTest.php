<?php

namespace JTD\LaravelMCP\Tests\Unit\Traits;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Tests\TestCase;

/**
 * Unit tests for ValidatesParameters trait.
 *
 * This test ensures that the ValidatesParameters trait properly
 * validates parameters according to various rules and schemas.
 */
class ValidatesParametersTest extends TestCase
{
    /** @var object */
    protected $validator;

    /** @var \ReflectionClass */
    protected $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->validator = new class
        {
            use ValidatesParameters;
        };

        // Create reflection for accessing protected methods
        $this->reflection = new \ReflectionClass($this->validator);
    }

    /**
     * Call a protected method on the validator.
     *
     * @return mixed
     */
    protected function callProtectedMethod(string $methodName, array $arguments = [])
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->validator, $arguments);
    }

    /**
     * Test validateSchema with valid params.
     */
    public function test_validate_schema_with_valid_params(): void
    {
        $params = [
            'name' => 'John Doe',
            'age' => 25,
            'email' => 'john@example.com',
        ];

        $schema = [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'int', 'required' => true],
            'email' => ['type' => 'string', 'format' => 'email'],
        ];

        $validated = $this->callProtectedMethod('validateSchema', [$params, $schema]);

        $this->assertEquals($params, $validated);
    }

    /**
     * Test validateSchema with missing required field.
     */
    public function test_validate_schema_missing_required(): void
    {
        $params = ['name' => 'John'];

        $schema = [
            'name' => ['required' => true],
            'email' => ['required' => true],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Missing required parameter: email');

        $this->callProtectedMethod('validateSchema', [$params, $schema]);
    }

    /**
     * Test validateSchema with default values.
     */
    public function test_validate_schema_with_defaults(): void
    {
        $params = ['name' => 'John'];

        $schema = [
            'name' => ['required' => true],
            'country' => ['default' => 'US'],
            'active' => ['default' => true],
        ];

        $validated = $this->callProtectedMethod('validateSchema', [$params, $schema]);

        $this->assertEquals([
            'name' => 'John',
            'country' => 'US',
            'active' => true,
        ], $validated);
    }

    /**
     * Test validateFieldType with string.
     */
    public function test_validate_field_type_string(): void
    {
        // Valid string
        $this->callProtectedMethod('validateFieldType', ['test', 'string', 'testField']);
        $this->assertTrue(true);

        // Invalid string
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be a string");
        $this->callProtectedMethod('validateFieldType', [123, 'string', 'testField']);
    }

    /**
     * Test validateFieldType with integer.
     */
    public function test_validate_field_type_integer(): void
    {
        // Valid integer
        $this->callProtectedMethod('validateFieldType', [123, 'integer', 'testField']);
        $this->assertTrue(true);

        // Also test 'int' alias
        $this->callProtectedMethod('validateFieldType', [456, 'int', 'testField']);
        $this->assertTrue(true);

        // Invalid integer
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be an integer");
        $this->callProtectedMethod('validateFieldType', ['123', 'integer', 'testField']);
    }

    /**
     * Test validateFieldType with number.
     */
    public function test_validate_field_type_number(): void
    {
        // Valid numbers
        $this->callProtectedMethod('validateFieldType', [123, 'number', 'testField']);
        $this->callProtectedMethod('validateFieldType', [123.45, 'number', 'testField']);
        $this->callProtectedMethod('validateFieldType', ['123.45', 'number', 'testField']);
        $this->assertTrue(true);

        // Invalid number
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be a number");
        $this->callProtectedMethod('validateFieldType', ['abc', 'number', 'testField']);
    }

    /**
     * Test validateFieldType with boolean.
     */
    public function test_validate_field_type_boolean(): void
    {
        // Valid boolean
        $this->callProtectedMethod('validateFieldType', [true, 'boolean', 'testField']);
        $this->callProtectedMethod('validateFieldType', [false, 'bool', 'testField']);
        $this->assertTrue(true);

        // Invalid boolean
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be a boolean");
        $this->callProtectedMethod('validateFieldType', [1, 'boolean', 'testField']);
    }

    /**
     * Test validateFieldType with array.
     */
    public function test_validate_field_type_array(): void
    {
        // Valid array
        $this->callProtectedMethod('validateFieldType', [['test'], 'array', 'testField']);
        $this->assertTrue(true);

        // Invalid array
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be an array");
        $this->callProtectedMethod('validateFieldType', ['test', 'array', 'testField']);
    }

    /**
     * Test validateFieldType with object.
     */
    public function test_validate_field_type_object(): void
    {
        // Valid object (array)
        $this->callProtectedMethod('validateFieldType', [['key' => 'value'], 'object', 'testField']);

        // Valid object (stdClass)
        $this->callProtectedMethod('validateFieldType', [new \stdClass, 'object', 'testField']);
        $this->assertTrue(true);

        // Invalid object
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be an object");
        $this->callProtectedMethod('validateFieldType', ['test', 'object', 'testField']);
    }

    /**
     * Test validateFieldType with null.
     */
    public function test_validate_field_type_null(): void
    {
        // Valid null
        $this->callProtectedMethod('validateFieldType', [null, 'null', 'testField']);
        $this->assertTrue(true);

        // Invalid null
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'testField' must be null");
        $this->callProtectedMethod('validateFieldType', ['', 'null', 'testField']);
    }

    /**
     * Test validateFieldFormat with email.
     */
    public function test_validate_field_format_email(): void
    {
        // Valid email
        $result = $this->callProtectedMethod('validateFieldFormat', ['test@example.com', 'email', 'emailField']);
        $this->assertEquals('test@example.com', $result);

        // Invalid email
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'emailField' must be a valid email address");
        $this->callProtectedMethod('validateFieldFormat', ['invalid-email', 'email', 'emailField']);
    }

    /**
     * Test validateFieldFormat with URL.
     */
    public function test_validate_field_format_url(): void
    {
        // Valid URL
        $result = $this->callProtectedMethod('validateFieldFormat', ['https://example.com', 'url', 'urlField']);
        $this->assertEquals('https://example.com', $result);

        // Invalid URL
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'urlField' must be a valid URL");
        $this->callProtectedMethod('validateFieldFormat', ['not-a-url', 'url', 'urlField']);
    }

    /**
     * Test validateFieldFormat with URI.
     */
    public function test_validate_field_format_uri(): void
    {
        // Valid URIs
        $result = $this->callProtectedMethod('validateFieldFormat', ['file:///path/to/file', 'uri', 'uriField']);
        $this->assertEquals('file:///path/to/file', $result);

        $result = $this->callProtectedMethod('validateFieldFormat', ['http://example.com', 'uri', 'uriField']);
        $this->assertEquals('http://example.com', $result);

        // Invalid URI
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'uriField' must be a valid URI");
        $this->callProtectedMethod('validateFieldFormat', ['invalid uri', 'uri', 'uriField']);
    }

    /**
     * Test validateFieldFormat with date-time.
     */
    public function test_validate_field_format_datetime(): void
    {
        // Valid date-time
        $result = $this->callProtectedMethod('validateFieldFormat', ['2024-01-01 12:00:00', 'date-time', 'dateField']);
        $this->assertStringContainsString('2024-01-01', $result);

        // Invalid date-time
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'dateField' must be a valid ISO 8601 date-time");
        $this->callProtectedMethod('validateFieldFormat', ['invalid-date', 'date-time', 'dateField']);
    }

    /**
     * Test validateFieldFormat with UUID.
     */
    public function test_validate_field_format_uuid(): void
    {
        // Valid UUID
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $result = $this->callProtectedMethod('validateFieldFormat', [$uuid, 'uuid', 'uuidField']);
        $this->assertEquals($uuid, $result);

        // Invalid UUID
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'uuidField' must be a valid UUID");
        $this->callProtectedMethod('validateFieldFormat', ['not-a-uuid', 'uuid', 'uuidField']);
    }

    /**
     * Test validateFieldLength with string.
     */
    public function test_validate_field_length_string(): void
    {
        $rules = ['min_length' => 3, 'max_length' => 10];

        // Valid length
        $this->callProtectedMethod('validateFieldLength', ['test', $rules, 'field']);
        $this->assertTrue(true);

        // Too short
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'field' must be at least 3 characters/items long");
        $this->callProtectedMethod('validateFieldLength', ['ab', $rules, 'field']);
    }

    /**
     * Test validateFieldLength with array.
     */
    public function test_validate_field_length_array(): void
    {
        $rules = ['min_length' => 2, 'max_length' => 5];

        // Valid length
        $this->callProtectedMethod('validateFieldLength', [['a', 'b', 'c'], $rules, 'field']);
        $this->assertTrue(true);

        // Too long
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'field' must be at most 5 characters/items long");
        $this->callProtectedMethod('validateFieldLength', [['a', 'b', 'c', 'd', 'e', 'f'], $rules, 'field']);
    }

    /**
     * Test validateFieldRange.
     */
    public function test_validate_field_range(): void
    {
        $rules = ['min' => 10, 'max' => 100];

        // Valid range
        $this->callProtectedMethod('validateFieldRange', [50, $rules, 'field']);
        $this->assertTrue(true);

        // Below minimum
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'field' must be at least 10");
        $this->callProtectedMethod('validateFieldRange', [5, $rules, 'field']);
    }

    /**
     * Test validateFieldEnum.
     */
    public function test_validate_field_enum(): void
    {
        $enum = ['red', 'green', 'blue'];

        // Valid enum value
        $this->callProtectedMethod('validateFieldEnum', ['red', $enum, 'color']);
        $this->assertTrue(true);

        // Invalid enum value
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'color' must be one of: red, green, blue");
        $this->callProtectedMethod('validateFieldEnum', ['yellow', $enum, 'color']);
    }

    /**
     * Test validateFieldPattern.
     */
    public function test_validate_field_pattern(): void
    {
        $pattern = '/^[A-Z][a-z]+$/';

        // Valid pattern match
        $this->callProtectedMethod('validateFieldPattern', ['Test', $pattern, 'field']);
        $this->assertTrue(true);

        // Invalid pattern match
        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'field' does not match the required pattern");
        $this->callProtectedMethod('validateFieldPattern', ['test', $pattern, 'field']);
    }

    /**
     * Test validateField with custom validator.
     */
    public function test_validate_field_with_custom_validator(): void
    {
        // Test validator that transforms the value
        $rules = [
            'validator' => function ($value, $fieldName) {
                return abs($value);
            },
        ];

        $result = $this->callProtectedMethod('validateField', [5, $rules, 'number']);
        $this->assertEquals(5, $result);

        $result = $this->callProtectedMethod('validateField', [-5, $rules, 'number']);
        $this->assertEquals(5, $result); // Validator converts to absolute value

        // Test validator that throws exception for invalid values
        $strictRules = [
            'validator' => function ($value, $fieldName) {
                if ($value < 0) {
                    throw new McpException("Field '{$fieldName}' must be positive", -32602);
                }

                return $value;
            },
        ];

        $result = $this->callProtectedMethod('validateField', [5, $strictRules, 'number']);
        $this->assertEquals(5, $result);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage("Field 'number' must be positive");
        $this->callProtectedMethod('validateField', [-5, $strictRules, 'number']);
    }

    /**
     * Test complex schema validation.
     */
    public function test_complex_schema_validation(): void
    {
        $params = [
            'user' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'age' => 30,
            ],
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ];

        $schema = [
            'user' => [
                'type' => 'object',
                'required' => true,
            ],
            'settings' => [
                'type' => 'object',
                'required' => false,
                'default' => ['theme' => 'light'],
            ],
        ];

        $validated = $this->callProtectedMethod('validateSchema', [$params, $schema]);

        $this->assertArrayHasKey('user', $validated);
        $this->assertArrayHasKey('settings', $validated);
    }

    /**
     * Test isRequired method.
     */
    public function test_is_required(): void
    {
        $this->assertTrue($this->callProtectedMethod('isRequired', [['required' => true]]));
        $this->assertFalse($this->callProtectedMethod('isRequired', [['required' => false]]));
        $this->assertFalse($this->callProtectedMethod('isRequired', [[]]));
    }

    /**
     * Test getValidationErrorSummary.
     */
    public function test_get_validation_error_summary(): void
    {
        $errors = [];
        $summary = $this->callProtectedMethod('getValidationErrorSummary', [$errors]);
        $this->assertEquals('No validation errors', $summary);

        $errors = ['Field is required'];
        $summary = $this->callProtectedMethod('getValidationErrorSummary', [$errors]);
        $this->assertEquals('Field is required', $summary);

        $errors = ['Error 1', 'Error 2', 'Error 3'];
        $summary = $this->callProtectedMethod('getValidationErrorSummary', [$errors]);
        $this->assertEquals('Multiple validation errors: Error 1; Error 2; Error 3', $summary);
    }
}
