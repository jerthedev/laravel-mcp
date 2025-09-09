<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Exceptions\McpException;

/**
 * Calculator Tool for testing mathematical operations.
 *
 * This tool provides basic mathematical operations for testing
 * complex parameter validation and error handling.
 */
class CalculatorTool extends McpTool
{
    protected string $name = 'calculator';

    protected string $description = 'Performs basic mathematical operations';

    protected array $parameterSchema = [
        'operation' => [
            'type' => 'string',
            'description' => 'Mathematical operation to perform',
            'enum' => ['add', 'subtract', 'multiply', 'divide', 'power'],
            'required' => true,
        ],
        'operands' => [
            'type' => 'array',
            'description' => 'Numbers to operate on',
            'items' => [
                'type' => 'number',
            ],
            'minItems' => 2,
            'maxItems' => 10,
            'required' => true,
        ],
    ];

    /**
     * Handle the calculator tool execution.
     *
     * @param  array  $parameters  Tool parameters
     * @return array Tool execution result
     */
    protected function handle(array $parameters): array
    {
        $operation = $parameters['operation'];
        $operands = $parameters['operands'];

        try {
            $result = $this->performOperation($operation, $operands);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => "Result: {$result}",
                    ],
                ],
                'isError' => false,
            ];
        } catch (\Exception $e) {
            throw McpException::applicationError(
                "Calculation failed: {$e->getMessage()}",
                -32000,
                [
                    'operation' => $operation,
                    'operands' => $operands,
                ]
            );
        }
    }

    /**
     * Perform the mathematical operation.
     *
     * @param  string  $operation  Operation to perform
     * @param  array  $operands  Numbers to operate on
     * @return float Result of the operation
     *
     * @throws \InvalidArgumentException
     */
    protected function performOperation(string $operation, array $operands): float
    {
        switch ($operation) {
            case 'add':
                return array_sum($operands);

            case 'subtract':
                $result = array_shift($operands);
                foreach ($operands as $operand) {
                    $result -= $operand;
                }

                return $result;

            case 'multiply':
                return array_product($operands);

            case 'divide':
                $result = array_shift($operands);
                foreach ($operands as $operand) {
                    if ($operand == 0) {
                        throw new \InvalidArgumentException('Division by zero');
                    }
                    $result /= $operand;
                }

                return $result;

            case 'power':
                if (count($operands) !== 2) {
                    throw new \InvalidArgumentException('Power operation requires exactly 2 operands');
                }

                return pow($operands[0], $operands[1]);

            default:
                throw new \InvalidArgumentException("Unsupported operation: {$operation}");
        }
    }
}
