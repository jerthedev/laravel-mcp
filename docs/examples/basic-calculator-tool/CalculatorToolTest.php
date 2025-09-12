<?php

namespace Tests\Unit\Examples;

use App\Mcp\Tools\CalculatorTool;
use Tests\TestCase;

class CalculatorToolTest extends TestCase
{
    private CalculatorTool $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CalculatorTool($this->app, app('validator'));
    }

    public function test_it_has_correct_name_and_description()
    {
        $this->assertEquals('calculator', $this->calculator->getName());
        $this->assertStringContains('mathematical calculations', $this->calculator->getDescription());
    }

    public function test_it_has_proper_input_schema()
    {
        $schema = $this->calculator->getInputSchema();

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('operation', $schema['properties']);
        $this->assertArrayHasKey('a', $schema['properties']);
        $this->assertArrayHasKey('b', $schema['properties']);
        $this->assertEquals(['operation', 'a', 'b'], $schema['required']);
    }

    /**
     * @dataProvider basicOperationsProvider
     */
    public function test_it_performs_basic_operations($operation, $a, $b, $expected)
    {
        $result = $this->calculator->execute([
            'operation' => $operation,
            'a' => $a,
            'b' => $b,
        ]);

        $this->assertEquals($expected, $result['result']);
        $this->assertEquals($operation, $result['operation']);
        $this->assertEquals(['a' => $a, 'b' => $b], $result['operands']);
    }

    public function basicOperationsProvider(): array
    {
        return [
            'addition' => ['add', 5, 3, 8],
            'subtraction' => ['subtract', 10, 4, 6],
            'multiplication' => ['multiply', 6, 7, 42],
            'division' => ['divide', 15, 3, 5],
            'decimal division' => ['divide', 10, 3, 10 / 3],
        ];
    }

    public function test_it_handles_division_by_zero()
    {
        $result = $this->calculator->execute([
            'operation' => 'divide',
            'a' => 10,
            'b' => 0,
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('Cannot divide by zero', $result['error']);
    }

    public function test_it_handles_invalid_operation()
    {
        $result = $this->calculator->execute([
            'operation' => 'power',
            'a' => 2,
            'b' => 3,
        ]);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContains('Unsupported operation', $result['error']);
    }

    public function test_it_works_with_negative_numbers()
    {
        $result = $this->calculator->execute([
            'operation' => 'add',
            'a' => -5,
            'b' => 3,
        ]);

        $this->assertEquals(-2, $result['result']);
    }

    public function test_it_works_with_decimal_numbers()
    {
        $result = $this->calculator->execute([
            'operation' => 'multiply',
            'a' => 2.5,
            'b' => 4.2,
        ]);

        $this->assertEquals(10.5, $result['result']);
    }
}
