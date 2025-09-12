# Basic Calculator Tool Example

This example demonstrates how to create a simple MCP Tool that performs mathematical calculations.

## Features

- Basic arithmetic operations (add, subtract, multiply, divide)
- Input validation
- Error handling for edge cases (division by zero)
- Comprehensive testing

## Files

- `CalculatorTool.php` - The main tool implementation
- `CalculatorToolTest.php` - Unit tests for the calculator
- `README.md` - This documentation

## Usage

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "tools/call",
  "params": {
    "name": "calculator",
    "arguments": {
      "operation": "add",
      "a": 5,
      "b": 3
    }
  }
}
```

## Installation

1. Copy `CalculatorTool.php` to your `app/Mcp/Tools/` directory
2. The tool will be auto-discovered by the Laravel MCP package
3. Test using the MCP client or HTTP endpoint

## Testing

Run the tests with:

```bash
./vendor/bin/phpunit tests/Unit/Tools/CalculatorToolTest.php
```