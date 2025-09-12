# Laravel MCP Testing Foundation

## Overview

The testing foundation for the Laravel MCP package has been successfully implemented with comprehensive testing utilities, fixtures, and infrastructure. This document describes the testing setup and provides guidance for running tests in different environments.

## Test Infrastructure Components

### 1. Enhanced Base TestCase (`tests/TestCase.php`)
- **MCP Test Helpers**: Integrated via `McpTestHelpers` trait
- **Component Creation Helpers**: Methods to create test Tools, Resources, and Prompts
- **Assertion Helpers**: MCP-specific assertions for component registration and responses
- **JSON-RPC Testing**: Utilities for testing JSON-RPC request/response handling
- **Mock Services**: Helper methods for mocking transports and external dependencies

### 2. Test Utilities (`tests/Utilities/`)

#### McpTestHelpers Trait
- `assertMcpToolExists()`: Verify tool registration
- `assertMcpResourceExists()`: Verify resource registration  
- `assertMcpPromptExists()`: Verify prompt registration
- `createMockJsonRpcRequest()`: Generate test JSON-RPC requests
- `assertValidMcpResponse()`: Validate MCP response structure

#### JsonRpcTestHelpers Trait
- `createJsonRpcRequest()`: Build JSON-RPC 2.0 requests
- `createJsonRpcNotification()`: Build JSON-RPC notifications
- `assertValidJsonRpcResponse()`: Validate JSON-RPC responses
- `assertJsonRpcError()`: Assert error responses

### 3. Test Fixtures (`tests/Fixtures/`)

#### MCP Components
- **TestCalculatorTool**: Example tool with parameter validation
- **TestDatabaseResource**: Example resource with read operations
- **TestEmailPrompt**: Example prompt with argument handling

#### Mock Services (`tests/Mocks/`)
- **MockHttpClient**: Simulated HTTP client for API testing
- **MockCache**: In-memory cache for testing cache operations
- **MockLogger**: Test logger that captures log entries
- **MockEventDispatcher**: Event dispatcher for testing events

### 4. PHPUnit Configuration

#### Tiered Test Suites
```xml
<testsuites>
    <testsuite name="Fast">
        <!-- Core unit tests, ~6 seconds -->
        <directory>tests/Unit</directory>
        <exclude>tests/Unit/Transport</exclude>
        <exclude>tests/Unit/Server</exclude>
        <exclude>tests/Unit/Protocol</exclude>
    </testsuite>
    
    <testsuite name="Unit">
        <!-- All unit tests -->
        <directory>tests/Unit</directory>
    </testsuite>
    
    <testsuite name="Feature">
        <!-- Integration tests -->
        <directory>tests/Feature</directory>
    </testsuite>
    
    <testsuite name="Comprehensive">
        <!-- Unit + Feature tests -->
        <directory>tests/Unit</directory>
        <directory>tests/Feature</directory>
    </testsuite>
</testsuites>
```

## Known Issues and Workarounds

### Cache Directory Permissions in Containerized Environments

When running tests in Docker or other containerized environments where the vendor directory is owned by root, you may encounter cache directory permission errors. This is a known issue with Orchestra Testbench.

#### Workaround Solutions:

1. **Use the provided test runner script**:
   ```bash
   ./run-tests.sh fast    # Run fast test suite
   ./run-tests.sh unit    # Run unit tests
   ./run-tests.sh feature # Run feature tests
   ```

2. **Run tests with proper user permissions**:
   ```bash
   # If running as root in Docker
   chown -R www-data:www-data vendor/
   su www-data -c "composer test"
   ```

3. **Use a bind mount for the cache directory**:
   ```yaml
   # docker-compose.yml
   volumes:
     - ./storage/test-cache:/var/www/html/vendor/orchestra/testbench-core/laravel/bootstrap/cache
   ```

4. **Run tests in CI/CD environments**:
   The GitHub Actions workflow is configured to handle this automatically.

### Test Execution Commands

```bash
# Development Testing (use these commands)
composer test:fast         # Quick validation (~6s)
composer test:unit         # Full unit tests
composer test:feature      # Feature tests
composer test:comprehensive # Full test suite

# CI Testing (automated)
composer test:ci           # Fast tests with fail-fast

# Coverage Analysis
composer test:coverage     # Generate coverage report
```

## Test Organization

Tests are organized following Laravel conventions:

```
tests/
├── Unit/                  # Unit tests for individual classes
│   ├── Abstracts/        # Base class tests
│   ├── Commands/         # Artisan command tests
│   ├── Registry/         # Component registry tests
│   ├── Protocol/         # JSON-RPC protocol tests
│   ├── Transport/        # Transport layer tests
│   └── Support/          # Helper and utility tests
├── Feature/              # Integration tests
│   ├── McpServer/       # Server integration tests
│   ├── Registration/    # Component registration tests
│   └── EndToEnd/        # Full workflow tests
├── Fixtures/            # Test fixtures and data
├── Mocks/              # Mock implementations
├── Utilities/          # Test helper traits
└── Support/            # Test support classes
```

## Writing Tests

### Example: Testing an MCP Tool

```php
use JTD\LaravelMCP\Tests\TestCase;

class CalculatorToolTest extends TestCase
{
    public function test_calculator_tool_registration()
    {
        // Arrange
        $tool = $this->createTestTool('calculator', [
            'description' => 'Performs calculations',
            'parameterSchema' => [
                'operation' => ['type' => 'string', 'required' => true],
                'values' => ['type' => 'array', 'required' => true],
            ],
        ]);
        
        // Act
        $this->app->make('mcp.registry')->registerTool($tool);
        
        // Assert
        $this->assertMcpToolExists('calculator');
        $this->assertComponentRegistered('tools', 'calculator');
    }
    
    public function test_calculator_tool_execution()
    {
        // Arrange
        $request = $this->createJsonRpcRequest('tools/call', [
            'name' => 'calculator',
            'arguments' => [
                'operation' => 'sum',
                'values' => [1, 2, 3],
            ],
        ]);
        
        // Act
        $response = $this->processJsonRpcRequest($request);
        
        // Assert
        $this->assertValidJsonRpcResponse($response);
        $this->assertEquals(6, $response['result']['content'][0]['text']);
    }
}
```

### Example: Testing an MCP Resource

```php
public function test_database_resource_read()
{
    // Arrange
    $resource = $this->createTestResource('users', [
        'uri' => 'db://users',
        'mimeType' => 'application/json',
    ]);
    
    // Act
    $this->app->make('mcp.registry')->registerResource($resource);
    $result = $resource->read();
    
    // Assert
    $this->assertMcpResourceExists('users');
    $this->assertArrayHasKey('contents', $result);
    $this->assertEquals('application/json', $result['contents'][0]['mimeType']);
}
```

## Continuous Integration

The GitHub Actions workflow (`.github/workflows/tests.yml`) is configured to:

1. Run tests on multiple PHP versions (8.2, 8.3)
2. Test with Laravel 11.x
3. Execute the fast test suite for quick feedback
4. Run code style checks (Pint)
5. Perform static analysis (PHPStan)
6. Test with both `prefer-lowest` and `prefer-stable` dependencies

## Troubleshooting

### Common Issues

1. **"Cache directory not writable" error**
   - Use the `run-tests.sh` script
   - Check file permissions
   - Ensure /tmp is writable

2. **"Class not found" errors**
   - Run `composer dump-autoload`
   - Check namespace declarations
   - Verify PSR-4 autoloading configuration

3. **"Mocked method does not exist" errors**
   - Ensure interfaces are properly defined
   - Check mock expectations match actual methods
   - Verify mock setup in setUp() method

4. **Database connection errors**
   - Tests use in-memory SQLite by default
   - Check `defineEnvironment()` in TestCase
   - Ensure SQLite PHP extension is installed

## Best Practices

1. **Use the tiered testing strategy**
   - Run `test:fast` during development for quick feedback
   - Run `test:comprehensive` before committing
   - Let CI handle full test suite validation

2. **Write focused unit tests**
   - Test one thing per test method
   - Use descriptive test names
   - Follow AAA pattern (Arrange, Act, Assert)

3. **Use test helpers and traits**
   - Leverage McpTestHelpers for MCP-specific assertions
   - Use JsonRpcTestHelpers for protocol testing
   - Create custom helpers for repeated test logic

4. **Mock external dependencies**
   - Use provided mock services
   - Mock at the boundary (interfaces)
   - Avoid over-mocking

5. **Maintain test coverage**
   - Aim for 90%+ coverage
   - Focus on critical paths
   - Don't sacrifice quality for coverage metrics

## Summary

The testing foundation provides:
- ✅ Comprehensive test utilities and helpers
- ✅ Reusable fixtures and mock services
- ✅ Tiered testing strategy for efficiency
- ✅ CI/CD integration with GitHub Actions
- ✅ Workarounds for environment-specific issues
- ✅ Clear documentation and examples

The foundation is ready for comprehensive testing of the Laravel MCP package implementation.