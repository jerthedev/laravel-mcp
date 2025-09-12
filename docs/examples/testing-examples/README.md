# Testing Examples

This directory provides comprehensive examples of how to test MCP components in Laravel applications, covering unit tests, integration tests, and end-to-end scenarios.

## Examples Included

### 1. Unit Testing Patterns
- `McpToolTest.php` - Testing MCP tools in isolation
- `McpResourceTest.php` - Resource testing with mocked dependencies
- `McpPromptTest.php` - Prompt testing with various inputs
- `MiddlewareTest.php` - Middleware testing patterns

### 2. Integration Testing
- `McpServerIntegrationTest.php` - Full server integration
- `DatabaseResourceIntegrationTest.php` - Database integration testing
- `ExternalApiIntegrationTest.php` - Third-party API testing

### 3. Feature Testing
- `McpEndpointTest.php` - HTTP endpoint testing
- `StdioTransportTest.php` - Transport layer testing
- `WorkflowTest.php` - Complex workflow testing

### 4. Performance Testing
- `LoadTest.php` - Load testing MCP endpoints
- `BenchmarkTest.php` - Performance benchmarking
- `CacheTest.php` - Caching performance validation

## Testing Strategies

### Mock Testing
```php
// Mock external dependencies
$mockService = Mockery::mock(ExternalService::class);
$mockService->shouldReceive('getData')->andReturn($expectedData);
```

### Database Testing
```php
// Use database transactions for isolation
use RefreshDatabase;
use DatabaseTransactions;
```

### HTTP Testing
```php
// Test MCP HTTP endpoints
$response = $this->postJson('/mcp', $mcpRequest);
$response->assertStatus(200);
```

### Transport Testing
```php
// Test Stdio transport
$transport = new StdioTransport();
$response = $transport->handle($request);
```

## Test Categories

### Unit Tests
- **Tools**: Input validation, execution logic, error handling
- **Resources**: Data access, URI parsing, content formatting
- **Prompts**: Template generation, argument processing
- **Middleware**: Request processing, authentication, validation

### Integration Tests
- **Registry**: Component discovery and registration
- **Transport**: Message handling and protocol compliance
- **Database**: Eloquent integration and query optimization
- **Cache**: Redis integration and performance

### Feature Tests
- **Workflows**: End-to-end business processes
- **Authentication**: Security and authorization flows
- **Error Handling**: Exception management and user feedback
- **Performance**: Response times and resource usage

## Testing Tools

### PHPUnit Extensions
- Custom assertions for MCP responses
- Test doubles for MCP components
- Data providers for comprehensive coverage

### Laravel Testing
- HTTP client for endpoint testing
- Database testing utilities
- Queue testing for background jobs
- Event testing for decoupled components

### Performance Testing
- Blackfire integration
- Memory usage monitoring
- Database query analysis
- Cache hit ratio validation

## Best Practices

### Test Structure
```php
class McpToolTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Setup test dependencies
    }

    public function test_it_performs_expected_operation()
    {
        // Arrange
        $input = ['key' => 'value'];
        
        // Act
        $result = $this->tool->execute($input);
        
        // Assert
        $this->assertArrayHasKey('content', $result);
    }
}
```

### Mock Strategies
- Mock external APIs to avoid network dependencies
- Use database factories for consistent test data
- Mock file systems for upload/download testing
- Stub time-dependent operations for reliability

### Assertion Patterns
- Validate JSON-RPC response structure
- Check MCP protocol compliance
- Verify security constraints
- Test error response formats

## Running Tests

```bash
# Run all MCP tests
./vendor/bin/phpunit --group=mcp

# Run specific test categories
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Feature
./vendor/bin/phpunit --testsuite=Integration

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Run performance tests
./vendor/bin/phpunit --group=performance
```

## Coverage Goals

- **Unit Tests**: 95%+ code coverage
- **Integration Tests**: All critical paths covered
- **Feature Tests**: End-to-end scenarios validated
- **Edge Cases**: Error conditions and boundary values

Each test should be:
- **Fast**: Under 100ms for unit tests
- **Isolated**: No dependencies between tests
- **Repeatable**: Same results every run
- **Self-validating**: Clear pass/fail criteria