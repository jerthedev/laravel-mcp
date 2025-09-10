<?php

namespace Tests\Unit\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Comprehensive unit tests for JsonRpcHandler.
 *
 * Tests JSON-RPC 2.0 handler functionality including request handling,
 * notification processing, response handling, message creation, validation,
 * handler registration, error handling, and debug mode functionality.
 */
class JsonRpcHandlerTest extends TestCase
{
    protected JsonRpcHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new JsonRpcHandler(debug: false);
        
        // Clear log expectations
        Log::shouldReceive('debug')->andReturnNull()->byDefault();
        Log::shouldReceive('info')->andReturnNull()->byDefault();
        Log::shouldReceive('warning')->andReturnNull()->byDefault();
        Log::shouldReceive('error')->andReturnNull()->byDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Test constructor with different debug modes.
     */
    public function test_constructor_with_debug_modes(): void
    {
        $handlerDebugOff = new JsonRpcHandler(false);
        $this->assertFalse($handlerDebugOff->isDebug());

        $handlerDebugOn = new JsonRpcHandler(true);
        $this->assertTrue($handlerDebugOn->isDebug());

        // Default should be false
        $handlerDefault = new JsonRpcHandler();
        $this->assertFalse($handlerDefault->isDebug());
    }

    /**
     * Test debug mode setting and getting.
     */
    public function test_debug_mode_setting(): void
    {
        $this->assertFalse($this->handler->isDebug());

        $this->handler->setDebug(true);
        $this->assertTrue($this->handler->isDebug());

        $this->handler->setDebug(false);
        $this->assertFalse($this->handler->isDebug());
    }

    /**
     * Test handling valid JSON-RPC request.
     */
    public function test_handle_request_valid(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'test.method',
            'params' => ['arg1' => 'value1'],
            'id' => 1,
        ];

        // Register handler
        $this->handler->onRequest('test.method', function ($params) {
            return ['result' => 'success', 'params' => $params];
        });

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('success', $response['result']['result']);
        $this->assertEquals(['arg1' => 'value1'], $response['result']['params']);
    }

    /**
     * Test handling request with no parameters.
     */
    public function test_handle_request_no_params(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 'test-id',
        ];

        $this->handler->onRequest('ping', function ($params) {
            return 'pong';
        });

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertEquals('pong', $response['result']);
    }

    /**
     * Test handling request with invalid format.
     */
    #[DataProvider('invalidRequestProvider')]
    public function test_handle_request_invalid_format(array $request, $expectedId = null): void
    {
        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals($expectedId, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32600, $response['error']['code']);
        $this->assertEquals('Invalid request format', $response['error']['message']);
    }

    public static function invalidRequestProvider(): array
    {
        return [
            'missing jsonrpc' => [['method' => 'test', 'id' => 1], 1],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'method' => 'test', 'id' => 2], 2],
            'missing method' => [['jsonrpc' => '2.0', 'id' => 3], 3],
            'empty method' => [['jsonrpc' => '2.0', 'method' => '', 'id' => 4], 4],
            'missing id' => [['jsonrpc' => '2.0', 'method' => 'test'], null],
        ];
    }

    /**
     * Test handling request with method not found.
     */
    public function test_handle_request_method_not_found(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'nonexistent.method',
            'id' => 'test-id',
        ];

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertStringContainsString('Method \'nonexistent.method\' not found', $response['error']['message']);
    }

    /**
     * Test handling request with invalid parameters exception.
     */
    public function test_handle_request_invalid_params_exception(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'test.method',
            'params' => ['invalid' => 'params'],
            'id' => 'test-id',
        ];

        $this->handler->onRequest('test.method', function ($params) {
            throw new \InvalidArgumentException('Invalid parameter: missing required field');
        });

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
        $this->assertEquals('Invalid parameter: missing required field', $response['error']['message']);
    }

    /**
     * Test handling request with internal error.
     */
    public function test_handle_request_internal_error(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'error.method',
            'id' => 'test-id',
        ];

        $this->handler->onRequest('error.method', function ($params) {
            throw new \RuntimeException('Something went wrong');
        });

        Log::shouldReceive('error')->once()->with('Request handler error', \Mockery::type('array'));

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32603, $response['error']['code']);
        $this->assertEquals('Internal error', $response['error']['message']);
        $this->assertNull($response['error']['data'] ?? null);
    }

    /**
     * Test handling request with internal error in debug mode.
     */
    public function test_handle_request_internal_error_debug_mode(): void
    {
        $this->handler->setDebug(true);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'error.method',
            'id' => 'test-id',
        ];

        $this->handler->onRequest('error.method', function ($params) {
            throw new \RuntimeException('Debug error message');
        });

        Log::shouldReceive('error')->once()->with('Request handler error', \Mockery::type('array'));

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32603, $response['error']['code']);
        $this->assertEquals('Internal error', $response['error']['message']);
        $this->assertIsArray($response['error']['data']);
        $this->assertEquals('Debug error message', $response['error']['data']['message']);
    }

    /**
     * Test handling request with global exception.
     */
    public function test_handle_request_global_exception(): void
    {
        // Create a request that will cause validation to throw
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        // Mock the isRequest method to throw an exception
        $handler = $this->getMockBuilder(JsonRpcHandler::class)
            ->onlyMethods(['isRequest'])
            ->getMock();

        $handler->method('isRequest')
            ->willThrowException(new \RuntimeException('Global error'));

        Log::shouldReceive('error')->once()->with('JSON-RPC request processing error', \Mockery::type('array'));

        $response = $handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32603, $response['error']['code']);
        $this->assertEquals('Internal error', $response['error']['message']);
    }

    /**
     * Test debug logging during request processing.
     */
    public function test_handle_request_debug_logging(): void
    {
        $this->handler->setDebug(true);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'debug.method',
            'params' => ['debug' => true],
            'id' => 'debug-id',
        ];

        $this->handler->onRequest('debug.method', function ($params) {
            return ['debug_response' => true];
        });

        Log::shouldReceive('debug')->once()->with('Processing JSON-RPC request', [
            'method' => 'debug.method',
            'id' => 'debug-id',
            'params' => ['debug' => true],
        ]);

        $response = $this->handler->handleRequest($request);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('debug-id', $response['id']);
        $this->assertEquals(['debug_response' => true], $response['result']);
    }

    /**
     * Test handling valid notification.
     */
    public function test_handle_notification_valid(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'test.notification',
            'params' => ['key' => 'value'],
        ];

        $called = false;
        $receivedParams = null;

        $this->handler->onNotification('test.notification', function ($params) use (&$called, &$receivedParams) {
            $called = true;
            $receivedParams = $params;
        });

        $this->handler->handleNotification($notification);

        $this->assertTrue($called);
        $this->assertEquals(['key' => 'value'], $receivedParams);
    }

    /**
     * Test handling notification without parameters.
     */
    public function test_handle_notification_no_params(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'simple.notification',
        ];

        $called = false;
        $receivedParams = null;

        $this->handler->onNotification('simple.notification', function ($params) use (&$called, &$receivedParams) {
            $called = true;
            $receivedParams = $params;
        });

        $this->handler->handleNotification($notification);

        $this->assertTrue($called);
        $this->assertEquals([], $receivedParams);
    }

    /**
     * Test handling invalid notification.
     */
    #[DataProvider('invalidNotificationProvider')]
    public function test_handle_notification_invalid_format(array $notification): void
    {
        Log::shouldReceive('warning')->once()->with('Invalid notification format', ['notification' => $notification]);

        $this->handler->handleNotification($notification);

        // Should not throw exception, just log warning
        $this->assertTrue(true);
    }

    public static function invalidNotificationProvider(): array
    {
        return [
            'missing jsonrpc' => [['method' => 'test']],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'method' => 'test']],
            'missing method' => [['jsonrpc' => '2.0']],
            'empty method' => [['jsonrpc' => '2.0', 'method' => '']],
            'has id (makes it request)' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]],
        ];
    }

    /**
     * Test handling notification with unregistered method.
     */
    public function test_handle_notification_unregistered_method(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'unregistered.notification',
        ];

        Log::shouldReceive('info')->once()->with('No handler for notification method: unregistered.notification');

        $this->handler->handleNotification($notification);

        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * Test handling notification with handler exception.
     */
    public function test_handle_notification_handler_exception(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'error.notification',
        ];

        $this->handler->onNotification('error.notification', function ($params) {
            throw new \RuntimeException('Handler error');
        });

        Log::shouldReceive('error')
            ->once()
            ->with('Notification handler error', \Mockery::subset([
                'method' => 'error.notification',
                'error' => 'Handler error',
            ]));

        $this->handler->handleNotification($notification);

        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * Test debug logging during notification processing.
     */
    public function test_handle_notification_debug_logging(): void
    {
        $this->handler->setDebug(true);

        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'debug.notification',
            'params' => ['debug' => true],
        ];

        $this->handler->onNotification('debug.notification', function ($params) {
            // Handler implementation
        });

        Log::shouldReceive('debug')->once()->with('Processing JSON-RPC notification', [
            'method' => 'debug.notification',
            'params' => ['debug' => true],
        ]);

        $this->handler->handleNotification($notification);
    }

    /**
     * Test handling valid response.
     */
    public function test_handle_response_valid_success(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => ['data' => 'success'],
            'id' => 'test-id',
        ];

        $called = false;
        $receivedResponse = null;

        $this->handler->onResponse('test-id', function ($response) use (&$called, &$receivedResponse) {
            $called = true;
            $receivedResponse = $response;
        });

        $this->handler->handleResponse($response);

        $this->assertTrue($called);
        $this->assertEquals($response, $receivedResponse);

        // Handler should be removed after processing
        $this->handler->handleResponse($response);
        // Second call should not trigger the handler again
    }

    /**
     * Test handling valid error response.
     */
    public function test_handle_response_valid_error(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ],
            'id' => 'error-id',
        ];

        $called = false;
        $receivedResponse = null;

        $this->handler->onResponse('error-id', function ($response) use (&$called, &$receivedResponse) {
            $called = true;
            $receivedResponse = $response;
        });

        $this->handler->handleResponse($response);

        $this->assertTrue($called);
        $this->assertEquals($response, $receivedResponse);
    }

    /**
     * Test handling invalid response.
     */
    #[DataProvider('invalidResponseProvider')]
    public function test_handle_response_invalid_format(array $response): void
    {
        Log::shouldReceive('warning')->once()->with('Invalid response format', ['response' => $response]);

        $this->handler->handleResponse($response);

        // Should not throw exception
        $this->assertTrue(true);
    }

    public static function invalidResponseProvider(): array
    {
        return [
            'missing jsonrpc' => [['result' => 'test', 'id' => 1]],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'result' => 'test', 'id' => 1]],
            'missing id' => [['jsonrpc' => '2.0', 'result' => 'test']],
            'missing result and error' => [['jsonrpc' => '2.0', 'id' => 1]],
            'both result and error' => [['jsonrpc' => '2.0', 'result' => 'test', 'error' => ['code' => -1, 'message' => 'test'], 'id' => 1]],
        ];
    }

    /**
     * Test handling response without registered handler.
     */
    public function test_handle_response_no_handler(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => 'test',
            'id' => 'unhandled-id',
        ];

        // Should not throw exception or log anything special
        $this->handler->handleResponse($response);

        $this->assertTrue(true);
    }

    /**
     * Test handling response with handler exception.
     */
    public function test_handle_response_handler_exception(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => 'test',
            'id' => 'error-handler',
        ];

        $this->handler->onResponse('error-handler', function ($response) {
            throw new \RuntimeException('Response handler error');
        });

        Log::shouldReceive('error')->once()->with('Response handler error', [
            'id' => 'error-handler',
            'error' => 'Response handler error',
        ]);

        $this->handler->handleResponse($response);

        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * Test debug logging during response processing.
     */
    public function test_handle_response_debug_logging(): void
    {
        $this->handler->setDebug(true);

        $response = [
            'jsonrpc' => '2.0',
            'result' => 'debug response',
            'id' => 'debug-response-id',
        ];

        $this->handler->onResponse('debug-response-id', function ($response) {
            // Handler implementation
        });

        Log::shouldReceive('debug')->once()->with('Processing JSON-RPC response', [
            'id' => 'debug-response-id',
            'has_result' => true,
            'has_error' => false,
        ]);

        $this->handler->handleResponse($response);
    }

    /**
     * Test creating request with parameters.
     */
    public function test_create_request_with_params(): void
    {
        $request = $this->handler->createRequest('test.method', ['param1' => 'value1', 'param2' => 2], 'unique-id');

        $this->assertEquals('2.0', $request['jsonrpc']);
        $this->assertEquals('test.method', $request['method']);
        $this->assertEquals(['param1' => 'value1', 'param2' => 2], $request['params']);
        $this->assertEquals('unique-id', $request['id']);
    }

    /**
     * Test creating request without parameters.
     */
    public function test_create_request_no_params(): void
    {
        $request = $this->handler->createRequest('simple.method', [], 123);

        $this->assertEquals('2.0', $request['jsonrpc']);
        $this->assertEquals('simple.method', $request['method']);
        $this->assertArrayNotHasKey('params', $request);
        $this->assertEquals(123, $request['id']);
    }

    /**
     * Test creating notification (request without ID).
     */
    public function test_create_request_notification(): void
    {
        $notification = $this->handler->createRequest('notification.method', ['data' => 'test'], null);

        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('notification.method', $notification['method']);
        $this->assertEquals(['data' => 'test'], $notification['params']);
        $this->assertArrayNotHasKey('id', $notification);
    }

    /**
     * Test creating success response.
     */
    public function test_create_success_response(): void
    {
        $result = ['status' => 'success', 'data' => ['key' => 'value']];
        $response = $this->handler->createSuccessResponse($result, 'test-id');

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals($result, $response['result']);
        $this->assertEquals('test-id', $response['id']);
        $this->assertArrayNotHasKey('error', $response);
    }

    /**
     * Test creating error response without data.
     */
    public function test_create_error_response_no_data(): void
    {
        $response = $this->handler->createErrorResponse(-32601, 'Method not found', null, 'error-id');

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('error-id', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32601, $response['error']['code']);
        $this->assertEquals('Method not found', $response['error']['message']);
        $this->assertArrayNotHasKey('data', $response['error']);
        $this->assertArrayNotHasKey('result', $response);
    }

    /**
     * Test creating error response with data.
     */
    public function test_create_error_response_with_data(): void
    {
        $errorData = ['field' => 'param1', 'reason' => 'required'];
        $response = $this->handler->createErrorResponse(-32602, 'Invalid params', $errorData, 'validation-error');

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals('validation-error', $response['id']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(-32602, $response['error']['code']);
        $this->assertEquals('Invalid params', $response['error']['message']);
        $this->assertEquals($errorData, $response['error']['data']);
    }

    /**
     * Test all JSON-RPC error codes.
     */
    #[DataProvider('errorCodeProvider')]
    public function test_create_error_response_standard_codes(int $code, string $message): void
    {
        $response = $this->handler->createErrorResponse($code, $message, null, 1);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertEquals($code, $response['error']['code']);
        $this->assertEquals($message, $response['error']['message']);
    }

    public static function errorCodeProvider(): array
    {
        return [
            'Parse error' => [-32700, 'Parse error'],
            'Invalid request' => [-32600, 'Invalid Request'],
            'Method not found' => [-32601, 'Method not found'],
            'Invalid params' => [-32602, 'Invalid params'],
            'Internal error' => [-32603, 'Internal error'],
            'Server error' => [-32000, 'Server error'],
            'Custom error' => [-1, 'Custom error'],
        ];
    }

    /**
     * Test message validation.
     */
    #[DataProvider('messageValidationProvider')]
    public function test_validate_message(array $message, bool $expected): void
    {
        $result = $this->handler->validateMessage($message);
        $this->assertEquals($expected, $result);
    }

    public static function messageValidationProvider(): array
    {
        return [
            // Valid messages
            'valid request' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1], true],
            'valid notification' => [['jsonrpc' => '2.0', 'method' => 'test'], true],
            'valid success response' => [['jsonrpc' => '2.0', 'result' => 'test', 'id' => 1], true],
            'valid error response' => [['jsonrpc' => '2.0', 'error' => ['code' => -1, 'message' => 'test'], 'id' => 1], true],

            // Invalid messages
            'missing jsonrpc' => [['method' => 'test', 'id' => 1], false],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'method' => 'test', 'id' => 1], false],
            'empty message' => [[], false],
            'malformed response' => [['jsonrpc' => '2.0', 'id' => 1], false],
        ];
    }

    /**
     * Test request validation.
     */
    #[DataProvider('requestValidationProvider')]
    public function test_is_request(array $message, bool $expected): void
    {
        $result = $this->handler->isRequest($message);
        $this->assertEquals($expected, $result);
    }

    public static function requestValidationProvider(): array
    {
        return [
            // Valid requests
            'valid request' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1], true],
            'request with params' => [['jsonrpc' => '2.0', 'method' => 'test', 'params' => [], 'id' => 1], true],
            'request with string id' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 'string-id'], true],

            // Invalid requests
            'notification (no id)' => [['jsonrpc' => '2.0', 'method' => 'test'], false],
            'missing method' => [['jsonrpc' => '2.0', 'id' => 1], false],
            'empty method' => [['jsonrpc' => '2.0', 'method' => '', 'id' => 1], false],
            'non-string method' => [['jsonrpc' => '2.0', 'method' => 123, 'id' => 1], false],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'method' => 'test', 'id' => 1], false],
            'missing jsonrpc' => [['method' => 'test', 'id' => 1], false],
        ];
    }

    /**
     * Test notification validation.
     */
    #[DataProvider('notificationValidationProvider')]
    public function test_is_notification(array $message, bool $expected): void
    {
        $result = $this->handler->isNotification($message);
        $this->assertEquals($expected, $result);
    }

    public static function notificationValidationProvider(): array
    {
        return [
            // Valid notifications
            'valid notification' => [['jsonrpc' => '2.0', 'method' => 'test'], true],
            'notification with params' => [['jsonrpc' => '2.0', 'method' => 'test', 'params' => []], true],

            // Invalid notifications
            'request (has id)' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1], false],
            'missing method' => [['jsonrpc' => '2.0'], false],
            'empty method' => [['jsonrpc' => '2.0', 'method' => ''], false],
            'non-string method' => [['jsonrpc' => '2.0', 'method' => 123], false],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'method' => 'test'], false],
            'missing jsonrpc' => [['method' => 'test'], false],
        ];
    }

    /**
     * Test response validation.
     */
    #[DataProvider('responseValidationProvider')]
    public function test_is_response(array $message, bool $expected): void
    {
        $result = $this->handler->isResponse($message);
        $this->assertEquals($expected, $result);
    }

    public static function responseValidationProvider(): array
    {
        return [
            // Valid responses
            'success response' => [['jsonrpc' => '2.0', 'result' => 'test', 'id' => 1], true],
            'error response' => [['jsonrpc' => '2.0', 'error' => ['code' => -1, 'message' => 'test'], 'id' => 1], true],

            // Invalid responses
            'both result and error' => [['jsonrpc' => '2.0', 'result' => 'test', 'error' => ['code' => -1, 'message' => 'test'], 'id' => 1], false],
            'missing result and error' => [['jsonrpc' => '2.0', 'id' => 1], false],
            'missing id' => [['jsonrpc' => '2.0', 'result' => 'test'], false],
            'wrong jsonrpc version' => [['jsonrpc' => '1.0', 'result' => 'test', 'id' => 1], false],
            'missing jsonrpc' => [['result' => 'test', 'id' => 1], false],
            'request with method' => [['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1], false],
        ];
    }

    /**
     * Test handler registration and removal.
     */
    public function test_handler_registration(): void
    {
        $method = 'test.handler';

        // Initially should not have handler
        $this->assertFalse($this->handler->hasRequestHandler($method));
        $this->assertFalse($this->handler->hasNotificationHandler($method));

        // Register handlers
        $this->handler->onRequest($method, function () {});
        $this->handler->onNotification($method, function () {});

        // Should have handlers now
        $this->assertTrue($this->handler->hasRequestHandler($method));
        $this->assertTrue($this->handler->hasNotificationHandler($method));

        // Should be in method lists
        $this->assertContains($method, $this->handler->getRequestMethods());
        $this->assertContains($method, $this->handler->getNotificationMethods());

        // Remove handlers
        $this->handler->removeRequestHandler($method);
        $this->handler->removeNotificationHandler($method);

        // Should not have handlers anymore
        $this->assertFalse($this->handler->hasRequestHandler($method));
        $this->assertFalse($this->handler->hasNotificationHandler($method));
        $this->assertNotContains($method, $this->handler->getRequestMethods());
        $this->assertNotContains($method, $this->handler->getNotificationMethods());
    }

    /**
     * Test response handler registration and automatic removal.
     */
    public function test_response_handler_registration(): void
    {
        $responseId = 'test-response-id';
        $called = false;

        // Register response handler
        $this->handler->onResponse($responseId, function () use (&$called) {
            $called = true;
        });

        // Handle a response
        $response = [
            'jsonrpc' => '2.0',
            'result' => 'test',
            'id' => $responseId,
        ];

        $this->handler->handleResponse($response);

        // Handler should have been called
        $this->assertTrue($called);

        // Handler should be automatically removed after use
        $called = false;
        $this->handler->handleResponse($response);
        $this->assertFalse($called);
    }

    /**
     * Test getting methods from empty handler.
     */
    public function test_get_methods_empty(): void
    {
        $this->assertEquals([], $this->handler->getRequestMethods());
        $this->assertEquals([], $this->handler->getNotificationMethods());
    }

    /**
     * Test multiple handler registration.
     */
    public function test_multiple_handlers(): void
    {
        $methods = ['method1', 'method2', 'method3'];

        foreach ($methods as $method) {
            $this->handler->onRequest($method, function () {});
            $this->handler->onNotification($method, function () {});
        }

        $requestMethods = $this->handler->getRequestMethods();
        $notificationMethods = $this->handler->getNotificationMethods();

        foreach ($methods as $method) {
            $this->assertContains($method, $requestMethods);
            $this->assertContains($method, $notificationMethods);
        }

        $this->assertCount(3, $requestMethods);
        $this->assertCount(3, $notificationMethods);
    }

    /**
     * Test handler overriding.
     */
    public function test_handler_overriding(): void
    {
        $method = 'test.override';
        $firstResult = 'first';
        $secondResult = 'second';

        // Register first handler
        $this->handler->onRequest($method, function () use ($firstResult) {
            return $firstResult;
        });

        // Test first handler
        $request = ['jsonrpc' => '2.0', 'method' => $method, 'id' => 1];
        $response = $this->handler->handleRequest($request);
        $this->assertEquals($firstResult, $response['result']);

        // Override with second handler
        $this->handler->onRequest($method, function () use ($secondResult) {
            return $secondResult;
        });

        // Test second handler
        $response = $this->handler->handleRequest($request);
        $this->assertEquals($secondResult, $response['result']);

        // Should still be just one handler registered
        $this->assertCount(1, $this->handler->getRequestMethods());
    }
}