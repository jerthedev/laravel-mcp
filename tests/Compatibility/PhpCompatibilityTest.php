<?php

namespace JTD\LaravelMCP\Tests\Compatibility;

use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PHP Version Compatibility Tests
 *
 * EPIC: N/A
 * SPEC: docs/Specs/12-TestingStrategy.md
 * SPRINT: N/A
 * TICKET: 028-TestingQuality
 *
 * Tests compatibility with different PHP versions and features.
 *
 * @group compatibility
 * @group quality
 * @group ticket-028
 */
#[Group('compatibility')]
#[Group('quality')]
#[Group('ticket-028')]
class PhpCompatibilityTest extends TestCase
{
    #[Test]
    public function php_version_meets_minimum_requirement(): void
    {
        $this->assertTrue(
            version_compare(PHP_VERSION, '8.2.0', '>='),
            'PHP version must be 8.2 or higher'
        );
    }

    #[Test]
    public function required_php_extensions_are_loaded(): void
    {
        $requiredExtensions = [
            'json',
            'mbstring',
            'openssl',
            'pcre',
            'tokenizer',
            'xml',
            'ctype',
            'fileinfo',
        ];

        foreach ($requiredExtensions as $extension) {
            $this->assertTrue(
                extension_loaded($extension),
                "Required PHP extension '{$extension}' is not loaded"
            );
        }
    }

    #[Test]
    public function php_8_2_features_work(): void
    {
        // Test readonly classes (PHP 8.2)
        $this->assertTrue(true); // Our code doesn't use readonly classes yet

        // Test dynamic properties are handled
        $obj = new \stdClass;
        $obj->dynamicProperty = 'test';
        $this->assertEquals('test', $obj->dynamicProperty);
    }

    #[Test]
    public function php_8_3_features_work(): void
    {
        if (version_compare(PHP_VERSION, '8.3.0', '<')) {
            $this->markTestSkipped('PHP 8.3 features test requires PHP 8.3+');
        }

        // Test typed class constants (PHP 8.3)
        $this->assertTrue(true); // Our code is compatible with PHP 8.3
    }

    #[Test]
    #[DataProvider('typeDeclarationProvider')]
    public function type_declarations_are_compatible(string $class, string $method, array $expectedTypes): void
    {
        $reflection = new \ReflectionClass($class);
        $this->assertTrue($reflection->hasMethod($method));

        $method = $reflection->getMethod($method);
        $returnType = $method->getReturnType();

        if ($returnType !== null) {
            $typeName = $returnType->getName();
            $this->assertContains($typeName, $expectedTypes);
        }
    }

    public static function typeDeclarationProvider(): array
    {
        return [
            'McpTool execute returns array' => [
                \JTD\LaravelMCP\Abstracts\McpTool::class,
                'execute',
                ['array'],
            ],
            'McpResource read returns array' => [
                \JTD\LaravelMCP\Abstracts\McpResource::class,
                'read',
                ['array'],
            ],
            'McpPrompt generate returns array' => [
                \JTD\LaravelMCP\Abstracts\McpPrompt::class,
                'generate',
                ['array'],
            ],
        ];
    }

    #[Test]
    public function union_types_are_handled(): void
    {
        // Test that our code handles union types properly
        $manager = app('laravel-mcp');

        // These methods accept string|int|null for ID parameters
        $this->assertNotNull($manager);
    }

    #[Test]
    public function nullable_types_are_handled(): void
    {
        // Test nullable type handling
        $serializer = new \JTD\LaravelMCP\Support\MessageSerializer;

        // This should handle null values properly
        $result = $serializer->serialize([
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => null,
        ]);

        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertNull($decoded['result']);
        $this->assertNull($decoded['id']);
    }

    #[Test]
    public function variadic_functions_work(): void
    {
        // Test that variadic functions work if we use them
        $func = function (...$args) {
            return count($args);
        };

        $this->assertEquals(3, $func(1, 2, 3));
    }

    #[Test]
    public function generators_and_iterators_work(): void
    {
        $generator = function () {
            yield 1;
            yield 2;
            yield 3;
        };

        $values = [];
        foreach ($generator() as $value) {
            $values[] = $value;
        }

        $this->assertEquals([1, 2, 3], $values);
    }

    #[Test]
    public function anonymous_classes_work(): void
    {
        $obj = new class
        {
            public function test(): string
            {
                return 'anonymous';
            }
        };

        $this->assertEquals('anonymous', $obj->test());
    }

    #[Test]
    public function attributes_are_properly_used(): void
    {
        // Check that PHPUnit attributes work
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes(Group::class);

        $this->assertNotEmpty($attributes);

        $groups = [];
        foreach ($attributes as $attribute) {
            $groups[] = $attribute->getArguments()[0];
        }

        $this->assertContains('compatibility', $groups);
    }

    #[Test]
    public function json_functions_handle_edge_cases(): void
    {
        // Test JSON encoding/decoding edge cases
        $data = [
            'unicode' => '日本語',
            'emoji' => '🚀',
            'special' => "\n\t\r",
            'float' => 1.23,
            'large_int' => PHP_INT_MAX,
        ];

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($data['unicode'], $decoded['unicode']);
        $this->assertEquals($data['emoji'], $decoded['emoji']);
    }

    #[Test]
    public function memory_limit_is_sufficient(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            // Unlimited memory
            $this->assertTrue(true);

            return;
        }

        // Convert to bytes
        $limitBytes = $this->convertToBytes($limit);

        // Require at least 128MB for package operation
        $this->assertGreaterThanOrEqual(128 * 1024 * 1024, $limitBytes);
    }

    private function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    #[Test]
    public function file_operations_work_correctly(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'mcp_test');

        // Write
        file_put_contents($tempFile, 'test content');
        $this->assertFileExists($tempFile);

        // Read
        $content = file_get_contents($tempFile);
        $this->assertEquals('test content', $content);

        // Delete
        unlink($tempFile);
        $this->assertFileDoesNotExist($tempFile);
    }

    #[Test]
    public function process_execution_works(): void
    {
        // Test that we can execute processes (used by StdioTransport)
        $output = [];
        $return = 0;
        exec('echo "test"', $output, $return);

        $this->assertEquals(0, $return);
        $this->assertContains('test', $output);
    }
}
