<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Test Calculator Tool
 *
 * A simple calculator tool for testing MCP tool functionality.
 */
class TestCalculatorTool extends McpTool
{
    /**
     * Tool name.
     */
    protected string $name = 'test_calculator';

    /**
     * Tool description.
     */
    protected string $description = 'Performs basic arithmetic operations for testing';

    /**
     * Tool parameter schema.
     */
    protected array $parameterSchema = [
        'operation' => [
            'type' => 'string',
            'description' => 'The arithmetic operation to perform',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
            'required' => true,
        ],
        'a' => [
            'type' => 'number',
            'description' => 'First operand',
            'required' => true,
        ],
        'b' => [
            'type' => 'number',
            'description' => 'Second operand',
            'required' => true,
        ],
    ];

    /**
     * Handle the tool execution.
     */
    protected function handle(array $parameters): mixed
    {
        $operation = $parameters['operation'];
        $a = $parameters['a'];
        $b = $parameters['b'];

        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
            default => throw new \InvalidArgumentException("Unknown operation: {$operation}"),
        };

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => (string) $result,
                ],
            ],
        ];
    }

    /**
     * Get the Laravel container instance.
     */
    public function getContainer(): \Illuminate\Container\Container
    {
        return app();
    }
}
