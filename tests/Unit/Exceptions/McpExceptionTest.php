<?php

namespace JTD\LaravelMCP\Tests\Unit\Exceptions;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Unit tests for McpException class.
 *
 * This test ensures that the McpException class properly handles
 * error data, context, and provides utility methods for error handling.
 */
class McpExceptionTest extends TestCase
{
    /**
     * Test basic exception creation.
     */
    public function test_basic_exception_creation(): void
    {
        $exception = new McpException('Test error', -32600);

        $this->assertEquals('Test error', $exception->getMessage());
        $this->assertEquals(-32600, $exception->getCode());
    }

    /**
     * Test exception with data.
     */
    public function test_exception_with_data(): void
    {
        $data = ['field' => 'test', 'value' => 123];
        $exception = new McpException('Test error', -32602, $data);

        $this->assertEquals($data, $exception->getData());
    }

    /**
     * Test exception with context.
     */
    public function test_exception_with_context(): void
    {
        $context = ['user' => 'test', 'action' => 'create'];
        $exception = new McpException('Test error', -32600, null, $context);

        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * Test exception with previous exception.
     */
    public function test_exception_with_previous(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new McpException('Test error', -32603, null, [], $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    /**
     * Test setData method.
     */
    public function test_set_data(): void
    {
        $exception = new McpException('Test error');
        $data = ['new' => 'data'];

        $exception->setData($data);

        $this->assertEquals($data, $exception->getData());
    }

    /**
     * Test setContext method.
     */
    public function test_set_context(): void
    {
        $exception = new McpException('Test error');
        $context = ['new' => 'context'];

        $exception->setContext($context);

        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * Test addContext method.
     */
    public function test_add_context(): void
    {
        $exception = new McpException('Test error', 0, null, ['initial' => 'value']);

        $exception->addContext('added', 'new value');

        $context = $exception->getContext();
        $this->assertEquals('value', $context['initial']);
        $this->assertEquals('new value', $context['added']);
    }

    /**
     * Test toArray method.
     */
    public function test_to_array(): void
    {
        $data = ['field' => 'test'];
        $context = ['user' => 'admin'];
        $exception = new McpException('Test error', -32602, $data, $context);

        $array = $exception->toArray();

        $this->assertArrayHasKey('error', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertArrayHasKey('context', $array);

        $this->assertEquals(-32602, $array['error']['code']);
        $this->assertEquals('Test error', $array['error']['message']);
        $this->assertEquals($data, $array['error']['data']);
        $this->assertEquals($context, $array['context']);
    }

    /**
     * Test toArray with previous exception.
     */
    public function test_to_array_with_previous(): void
    {
        $previous = new \RuntimeException('Previous error', 500);
        $exception = new McpException('Test error', -32603, null, [], $previous);

        $array = $exception->toArray();

        $this->assertArrayHasKey('previous', $array);
        $this->assertEquals('RuntimeException', $array['previous']['type']);
        $this->assertEquals('Previous error', $array['previous']['message']);
        $this->assertEquals(500, $array['previous']['code']);
    }

    /**
     * Test toJson method.
     */
    public function test_to_json(): void
    {
        $exception = new McpException('Test error', -32600, ['test' => 'data']);

        $json = $exception->toJson();

        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals(-32600, $decoded['error']['code']);
        $this->assertEquals('Test error', $decoded['error']['message']);
    }

    /**
     * Test getErrorType for JSON-RPC errors.
     */
    public function test_get_error_type_json_rpc(): void
    {
        $tests = [
            -32700 => 'Parse Error',
            -32600 => 'Invalid Request',
            -32601 => 'Method Not Found',
            -32602 => 'Invalid Params',
            -32603 => 'Internal Error',
            -32050 => 'Server Error',
            -32000 => 'Server Error',
            0 => 'Application Error',
            100 => 'Application Error',
        ];

        foreach ($tests as $code => $expectedType) {
            $exception = new McpException('Test', $code);
            $this->assertEquals($expectedType, $exception->getErrorType());
        }
    }

    /**
     * Test isClientError method.
     */
    public function test_is_client_error(): void
    {
        $clientErrors = [-32600, -32601, -32602];
        $nonClientErrors = [-32603, -32700, 0, 100];

        foreach ($clientErrors as $code) {
            $exception = new McpException('Test', $code);
            $this->assertTrue($exception->isClientError(), "Code {$code} should be client error");
        }

        foreach ($nonClientErrors as $code) {
            $exception = new McpException('Test', $code);
            $this->assertFalse($exception->isClientError(), "Code {$code} should not be client error");
        }
    }

    /**
     * Test isServerError method.
     */
    public function test_is_server_error(): void
    {
        $serverErrors = [-32603, -32099, -32050, -32000];
        $nonServerErrors = [-32600, -32602, 0, 100];

        foreach ($serverErrors as $code) {
            $exception = new McpException('Test', $code);
            $this->assertTrue($exception->isServerError(), "Code {$code} should be server error");
        }

        foreach ($nonServerErrors as $code) {
            $exception = new McpException('Test', $code);
            $this->assertFalse($exception->isServerError(), "Code {$code} should not be server error");
        }
    }

    /**
     * Test static parseError method.
     */
    public function test_static_parse_error(): void
    {
        $exception = McpException::parseError('Custom parse error', ['line' => 10]);

        $this->assertEquals('Custom parse error', $exception->getMessage());
        $this->assertEquals(-32700, $exception->getCode());
        $this->assertEquals(['line' => 10], $exception->getData());
    }

    /**
     * Test static invalidRequest method.
     */
    public function test_static_invalid_request(): void
    {
        $exception = McpException::invalidRequest('Bad request', ['reason' => 'missing field']);

        $this->assertEquals('Bad request', $exception->getMessage());
        $this->assertEquals(-32600, $exception->getCode());
        $this->assertEquals(['reason' => 'missing field'], $exception->getData());
    }

    /**
     * Test static methodNotFound method.
     */
    public function test_static_method_not_found(): void
    {
        $exception = McpException::methodNotFound('unknown/method', ['available' => ['ping']]);

        $this->assertEquals('Method not found: unknown/method', $exception->getMessage());
        $this->assertEquals(-32601, $exception->getCode());
        $this->assertEquals(['available' => ['ping']], $exception->getData());
    }

    /**
     * Test static invalidParams method.
     */
    public function test_static_invalid_params(): void
    {
        $exception = McpException::invalidParams('Missing required param', ['param' => 'name']);

        $this->assertEquals('Missing required param', $exception->getMessage());
        $this->assertEquals(-32602, $exception->getCode());
        $this->assertEquals(['param' => 'name'], $exception->getData());
    }

    /**
     * Test static internalError method.
     */
    public function test_static_internal_error(): void
    {
        $exception = McpException::internalError('Server failure', ['trace' => 'stack']);

        $this->assertEquals('Server failure', $exception->getMessage());
        $this->assertEquals(-32603, $exception->getCode());
        $this->assertEquals(['trace' => 'stack'], $exception->getData());
    }

    /**
     * Test static applicationError method.
     */
    public function test_static_application_error(): void
    {
        $exception = McpException::applicationError('App error', -32000, ['custom' => 'data']);

        $this->assertEquals('App error', $exception->getMessage());
        $this->assertEquals(-32000, $exception->getCode());
        $this->assertEquals(['custom' => 'data'], $exception->getData());
    }

    /**
     * Test static fromThrowable method.
     */
    public function test_static_from_throwable(): void
    {
        $original = new \RuntimeException('Original error', 500);
        $exception = McpException::fromThrowable($original);

        $this->assertEquals('Original error', $exception->getMessage());
        $this->assertEquals(-32603, $exception->getCode());
        $this->assertSame($original, $exception->getPrevious());

        $data = $exception->getData();
        $this->assertEquals('RuntimeException', $data['type']);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
        $this->assertArrayHasKey('trace', $data);
    }

    /**
     * Test fromThrowable with custom code.
     */
    public function test_from_throwable_with_custom_code(): void
    {
        $original = new \Exception('Error');
        $exception = McpException::fromThrowable($original, -32000);

        $this->assertEquals(-32000, $exception->getCode());
    }

    /**
     * Test fluent interface for setters.
     */
    public function test_fluent_interface(): void
    {
        $exception = new McpException('Test');

        $result = $exception
            ->setData(['test' => 'data'])
            ->setContext(['test' => 'context'])
            ->addContext('additional', 'value');

        $this->assertSame($exception, $result);
        $this->assertEquals(['test' => 'data'], $exception->getData());
        $this->assertEquals(['test' => 'context', 'additional' => 'value'], $exception->getContext());
    }
}
