<?php

namespace JTD\LaravelMCP\Tests\Unit\Traits;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use Tests\TestCase;

/**
 * Unit tests for HandlesMcpRequests trait.
 *
 * This test ensures that the HandlesMcpRequests trait properly
 * handles MCP request processing, error handling, and validation.
 */
class HandlesMcpRequestsTest extends TestCase
{
    /** @var object */
    protected $handler;

    /** @var \ReflectionClass */
    protected $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an anonymous class that uses the trait
        $this->handler = new class
        {
            use HandlesMcpRequests;
        };

        // Create reflection for accessing protected methods
        $this->reflection = new \ReflectionClass($this->handler);
    }

    /**
     * Call a protected method on the handler.
     *
     * @return mixed
     */
    protected function callProtectedMethod(string $methodName, array $arguments = [])
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->handler, $arguments);
    }

    /**
     * Test processRequest with successful handler.
     */
    public function test_process_request_success(): void
    {
        $params = ['test' => 'value'];
        $expectedResult = ['data' => 'test data'];

        $handler = function ($params) use ($expectedResult) {
            return $expectedResult;
        };

        $response = $this->callProtectedMethod('processRequest', [$handler, $params]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals($expectedResult, $response['result']);
    }

    /**
     * Test processRequest with McpException.
     */
    public function test_process_request_with_mcp_exception(): void
    {
        $handler = function ($params) {
            throw new McpException('Test error', -32602, ['field' => 'test']);
        };

        $response = $this->callProtectedMethod('processRequest', [$handler, []]);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals(-32602, $response['error']['code']);
        $this->assertEquals('Test error', $response['error']['message']);
        $this->assertEquals(['field' => 'test'], $response['error']['data']);
    }

    /**
     * Test processRequest with generic exception.
     */
    public function test_process_request_with_generic_exception(): void
    {
        $handler = function ($params) {
            throw new \RuntimeException('Runtime error');
        };

        $response = $this->callProtectedMethod('processRequest', [$handler, []]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32603, $response['error']['code']);
        $this->assertEquals('Internal error', $response['error']['message']);
        $this->assertArrayHasKey('type', $response['error']['data']);
        $this->assertEquals('RuntimeException', $response['error']['data']['type']);
    }

    /**
     * Test createSuccessResponse.
     */
    public function test_create_success_response(): void
    {
        $result = ['test' => 'data'];

        $response = $this->callProtectedMethod('createSuccessResponse', [$result]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals($result, $response['result']);
    }

    /**
     * Test createErrorResponse without data.
     */
    public function test_create_error_response_without_data(): void
    {
        $response = $this->callProtectedMethod('createErrorResponse', [-32600, 'Invalid request']);

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('timestamp', $response);
        $this->assertEquals(-32600, $response['error']['code']);
        $this->assertEquals('Invalid request', $response['error']['message']);
        $this->assertArrayNotHasKey('data', $response['error']);
    }

    /**
     * Test createErrorResponse with data.
     */
    public function test_create_error_response_with_data(): void
    {
        $data = ['field' => 'name', 'reason' => 'required'];

        $response = $this->callProtectedMethod('createErrorResponse', [-32602, 'Validation failed', $data]);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals($data, $response['error']['data']);
    }

    /**
     * Test validateRequiredParams with all params present.
     */
    public function test_validate_required_params_all_present(): void
    {
        $params = ['name' => 'test', 'value' => 123];
        $required = ['name', 'value'];

        // Should not throw exception
        $this->callProtectedMethod('validateRequiredParams', [$params, $required]);
        $this->assertTrue(true); // If we get here, validation passed
    }

    /**
     * Test validateRequiredParams with missing params.
     */
    public function test_validate_required_params_missing(): void
    {
        $params = ['name' => 'test'];
        $required = ['name', 'value', 'type'];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Missing required parameters: value, type');
        $this->expectExceptionCode(-32602);

        $this->callProtectedMethod('validateRequiredParams', [$params, $required]);
    }

    /**
     * Test extractParams with required params.
     */
    public function test_extract_params_with_required(): void
    {
        $params = ['name' => 'test', 'age' => 25];
        $schema = [
            'name' => ['required' => true],
            'age' => ['required' => true],
        ];

        $extracted = $this->callProtectedMethod('extractParams', [$params, $schema]);

        $this->assertEquals(['name' => 'test', 'age' => 25], $extracted);
    }

    /**
     * Test extractParams with missing required param.
     */
    public function test_extract_params_missing_required(): void
    {
        $params = ['name' => 'test'];
        $schema = [
            'name' => ['required' => true],
            'age' => ['required' => true],
        ];

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Missing required parameter: age');

        $this->callProtectedMethod('extractParams', [$params, $schema]);
    }

    /**
     * Test extractParams with default values.
     */
    public function test_extract_params_with_defaults(): void
    {
        $params = ['name' => 'test'];
        $schema = [
            'name' => ['required' => true],
            'age' => ['default' => 18],
            'active' => ['default' => true],
        ];

        $extracted = $this->callProtectedMethod('extractParams', [$params, $schema]);

        $this->assertEquals([
            'name' => 'test',
            'age' => 18,
            'active' => true,
        ], $extracted);
    }

    /**
     * Test validateParam with type validation.
     */
    public function test_validate_param_with_type(): void
    {
        $rules = ['type' => 'string'];

        $result = $this->callProtectedMethod('validateParam', ['test', $rules]);
        $this->assertEquals('test', $result);
    }

    /**
     * Test validateParam with custom validator.
     */
    public function test_validate_param_with_custom_validator(): void
    {
        $rules = [
            'validator' => function ($value) {
                return strtoupper($value);
            },
        ];

        $result = $this->callProtectedMethod('validateParam', ['test', $rules]);
        $this->assertEquals('TEST', $result);
    }

    /**
     * Test validateParamType with string.
     */
    public function test_validate_param_type_string(): void
    {
        // Valid string
        $this->callProtectedMethod('validateParamType', ['test', 'string']);
        $this->assertTrue(true);

        // Invalid string
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Expected string, got integer');
        $this->callProtectedMethod('validateParamType', [123, 'string']);
    }

    /**
     * Test validateParamType with integer.
     */
    public function test_validate_param_type_integer(): void
    {
        // Valid integer
        $this->callProtectedMethod('validateParamType', [123, 'int']);
        $this->assertTrue(true);

        // Invalid integer
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Expected integer, got string');
        $this->callProtectedMethod('validateParamType', ['123', 'int']);
    }

    /**
     * Test validateParamType with array.
     */
    public function test_validate_param_type_array(): void
    {
        // Valid array
        $this->callProtectedMethod('validateParamType', [['test'], 'array']);
        $this->assertTrue(true);

        // Invalid array
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Expected array, got string');
        $this->callProtectedMethod('validateParamType', ['test', 'array']);
    }

    /**
     * Test validateParamType with object.
     */
    public function test_validate_param_type_object(): void
    {
        // Valid object (array)
        $this->callProtectedMethod('validateParamType', [['key' => 'value'], 'object']);
        $this->assertTrue(true);

        // Valid object (stdClass)
        $this->callProtectedMethod('validateParamType', [new \stdClass, 'object']);
        $this->assertTrue(true);

        // Invalid object
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Expected object, got string');
        $this->callProtectedMethod('validateParamType', ['test', 'object']);
    }

    /**
     * Test getComponentName.
     */
    public function test_get_component_name(): void
    {
        $name = $this->callProtectedMethod('getComponentName');

        // Anonymous class will have a generated name
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    /**
     * Test logRequest with debug enabled.
     */
    public function test_log_request_with_debug_enabled(): void
    {
        config(['laravel-mcp.debug' => true]);

        // Should not throw any exceptions
        $this->callProtectedMethod('logRequest', ['test/method', ['param' => 'value']]);
        $this->assertTrue(true);
    }

    /**
     * Test logRequest with debug disabled.
     */
    public function test_log_request_with_debug_disabled(): void
    {
        config(['laravel-mcp.debug' => false]);

        // Should not throw any exceptions
        $this->callProtectedMethod('logRequest', ['test/method', ['param' => 'value']]);
        $this->assertTrue(true);
    }

    /**
     * Test logResponse with debug enabled.
     */
    public function test_log_response_with_debug_enabled(): void
    {
        config(['laravel-mcp.debug' => true]);

        // Should not throw any exceptions
        $this->callProtectedMethod('logResponse', ['test/method', ['result' => 'success']]);
        $this->assertTrue(true);
    }

    /**
     * Test complex parameter extraction with validation.
     */
    public function test_complex_parameter_extraction(): void
    {
        $params = [
            'name' => 'test',
            'age' => 25,
            'email' => 'test@example.com',
            'extra' => 'ignored',
        ];

        $schema = [
            'name' => [
                'required' => true,
                'type' => 'string',
            ],
            'age' => [
                'required' => true,
                'type' => 'int',
                'validator' => function ($value) {
                    return min(100, max(0, $value));
                },
            ],
            'email' => [
                'required' => false,
                'type' => 'string',
            ],
            'country' => [
                'required' => false,
                'default' => 'US',
            ],
        ];

        $extracted = $this->callProtectedMethod('extractParams', [$params, $schema]);

        $this->assertEquals([
            'name' => 'test',
            'age' => 25,
            'email' => 'test@example.com',
            'country' => 'US',
        ], $extracted);
        $this->assertArrayNotHasKey('extra', $extracted);
    }

    /**
     * Test error response includes timestamp.
     */
    public function test_error_response_includes_timestamp(): void
    {
        $response = $this->callProtectedMethod('createErrorResponse', [-32600, 'Test error']);

        $this->assertArrayHasKey('timestamp', $response);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response['timestamp']);
    }

    /**
     * Test success response includes timestamp.
     */
    public function test_success_response_includes_timestamp(): void
    {
        $response = $this->callProtectedMethod('createSuccessResponse', [['test' => 'data']]);

        $this->assertArrayHasKey('timestamp', $response);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $response['timestamp']);
    }
}
