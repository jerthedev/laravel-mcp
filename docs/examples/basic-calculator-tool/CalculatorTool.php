<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Basic calculator tool for mathematical operations
 *
 * This tool demonstrates the fundamentals of creating MCP tools
 * with proper validation, error handling, and documentation.
 */
class CalculatorTool extends McpTool
{
    /**
     * Get the tool name
     */
    public function getName(): string
    {
        return 'calculator';
    }

    /**
     * Get the tool description
     */
    public function getDescription(): string
    {
        return 'Performs basic mathematical calculations including addition, subtraction, multiplication, and division.';
    }

    /**
     * Get the input schema for validation
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'operation' => [
                    'type' => 'string',
                    'enum' => ['add', 'subtract', 'multiply', 'divide'],
                    'description' => 'The mathematical operation to perform',
                ],
                'a' => [
                    'type' => 'number',
                    'description' => 'The first operand',
                ],
                'b' => [
                    'type' => 'number',
                    'description' => 'The second operand',
                ],
            ],
            'required' => ['operation', 'a', 'b'],
        ];
    }

    /**
     * Execute the calculator tool
     */
    public function execute(array $arguments): array
    {
        $operation = $arguments['operation'];
        $a = $arguments['a'];
        $b = $arguments['b'];

        try {
            $result = match ($operation) {
                'add' => $a + $b,
                'subtract' => $a - $b,
                'multiply' => $a * $b,
                'divide' => $this->divide($a, $b),
                default => throw new \InvalidArgumentException("Unsupported operation: {$operation}")
            };

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Result: {$result}",
                    ],
                ],
                'result' => $result,
                'operation' => $operation,
                'operands' => ['a' => $a, 'b' => $b],
            ];
        } catch (\Exception $e) {
            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Error: {$e->getMessage()}",
                    ],
                ],
                'error' => $e->getMessage(),
                'operation' => $operation,
                'operands' => ['a' => $a, 'b' => $b],
            ];
        }
    }

    /**
     * Handle division with zero check
     */
    private function divide(float $a, float $b): float
    {
        if ($b === 0.0) {
            throw new \DivisionByZeroError('Cannot divide by zero');
        }

        return $a / $b;
    }
}
