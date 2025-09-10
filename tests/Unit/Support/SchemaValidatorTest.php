<?php

/**
 * @file tests/Unit/Support/SchemaValidatorTest.php
 *
 * @description Unit tests for SchemaValidator class
 *
 * @ticket BASECLASSES-014
 *
 * @epic BaseClasses
 *
 * @sprint Sprint-2
 */

namespace JTD\LaravelMCP\Tests\Unit\Support;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Support\SchemaValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelMCP\Tests\TestCase;

#[Group('base-classes')]
#[Group('schema-validator')]
#[Group('ticket-014')]
class SchemaValidatorTest extends TestCase
{
    private SchemaValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new SchemaValidator;
    }

    #[Test]
    public function it_validates_simple_string_schema()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
        ];

        $data = ['name' => 'John'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['name' => 'John'], $result);
    }

    #[Test]
    public function it_throws_exception_for_missing_required_field()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'required' => ['name'],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('missing required property: name');

        $this->validator->validate([], $schema);
    }

    #[Test]
    public function it_validates_integer_type()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer'],
            ],
        ];

        $data = ['age' => 25];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['age' => 25], $result);
    }

    #[Test]
    public function it_throws_exception_for_wrong_type()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'age' => ['type' => 'integer'],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('age must be of type integer');

        $this->validator->validate(['age' => 'twenty-five'], $schema);
    }

    #[Test]
    public function it_validates_number_with_minimum_and_maximum()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'score' => [
                    'type' => 'number',
                    'minimum' => 0,
                    'maximum' => 100,
                ],
            ],
        ];

        $data = ['score' => 75.5];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['score' => 75.5], $result);
    }

    #[Test]
    public function it_throws_exception_for_number_below_minimum()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'score' => [
                    'type' => 'number',
                    'minimum' => 0,
                ],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('must be at least 0');

        $this->validator->validate(['score' => -5], $schema);
    }

    #[Test]
    public function it_validates_array_schema()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 1,
                    'maxItems' => 5,
                ],
            ],
        ];

        $data = ['tags' => ['php', 'laravel']];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['tags' => ['php', 'laravel']], $result);
    }

    #[Test]
    public function it_validates_unique_array_items()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'uniqueItems' => true,
                ],
            ],
        ];

        $data = ['ids' => [1, 2, 3]];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['ids' => [1, 2, 3]], $result);
    }

    #[Test]
    public function it_throws_exception_for_non_unique_array_items()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'uniqueItems' => true,
                ],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('must have unique items');

        $this->validator->validate(['ids' => [1, 2, 1]], $schema);
    }

    #[Test]
    public function it_validates_string_with_pattern()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'pattern' => '^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
                ],
            ],
        ];

        $data = ['email' => 'test@example.com'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['email' => 'test@example.com'], $result);
    }

    #[Test]
    public function it_validates_string_with_enum()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive', 'pending'],
                ],
            ],
        ];

        $data = ['status' => 'active'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['status' => 'active'], $result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_enum_value()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'inactive'],
                ],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('must be one of: active, inactive');

        $this->validator->validate(['status' => 'unknown'], $schema);
    }

    #[Test]
    public function it_validates_string_with_format_email()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                ],
            ],
        ];

        $data = ['email' => 'user@example.com'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['email' => 'user@example.com'], $result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_email_format()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'email' => [
                    'type' => 'string',
                    'format' => 'email',
                ],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('must be a valid email address');

        $this->validator->validate(['email' => 'not-an-email'], $schema);
    }

    #[Test]
    public function it_validates_nested_objects()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'user' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'age' => ['type' => 'integer'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];

        $data = [
            'user' => [
                'name' => 'John',
                'age' => 30,
            ],
        ];

        $result = $this->validator->validate($data, $schema);

        $this->assertEquals($data, $result);
    }

    #[Test]
    public function it_applies_default_values()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'default' => 'active',
                ],
                'count' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ];

        $data = [];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['status' => 'active', 'count' => 0], $result);
    }

    #[Test]
    public function it_validates_additional_properties_when_false()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
            ],
            'additionalProperties' => false,
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('unexpected properties: extra');

        $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);
    }

    #[Test]
    public function it_creates_schema_from_laravel_rules()
    {
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'nullable|integer|min:18',
        ];

        $schema = SchemaValidator::fromLaravelRules($rules);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertArrayHasKey('required', $schema);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('email', $schema['required']);
        $this->assertNotContains('age', $schema['required']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('email', $schema['properties']['email']['format']);
        $this->assertEquals('integer', $schema['properties']['age']['type']);
    }

    #[Test]
    public function it_validates_boolean_type()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'active' => ['type' => 'boolean'],
            ],
        ];

        $data = ['active' => true];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['active' => true], $result);
    }

    #[Test]
    public function it_validates_null_when_nullable()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'optional' => [
                    'type' => 'string',
                    'nullable' => true,
                ],
            ],
        ];

        $data = ['optional' => null];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['optional' => null], $result);
    }

    #[Test]
    public function it_validates_string_length_constraints()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 3,
                    'maxLength' => 20,
                ],
            ],
        ];

        $data = ['username' => 'john_doe'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals(['username' => 'john_doe'], $result);
    }

    #[Test]
    public function it_throws_exception_for_string_too_short()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'username' => [
                    'type' => 'string',
                    'minLength' => 3,
                ],
            ],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('must be at least 3 characters');

        $this->validator->validate(['username' => 'ab'], $schema);
    }

    #[Test]
    public function it_validates_uuid_format()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                    'format' => 'uuid',
                ],
            ],
        ];

        $data = ['id' => '550e8400-e29b-41d4-a716-446655440000'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals($data, $result);
    }

    #[Test]
    public function it_validates_url_format()
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'website' => [
                    'type' => 'string',
                    'format' => 'url',
                ],
            ],
        ];

        $data = ['website' => 'https://example.com'];
        $result = $this->validator->validate($data, $schema);

        $this->assertEquals($data, $result);
    }
}
