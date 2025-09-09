<?php

namespace Tests\Unit\Protocol\Contracts;

use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for JsonRpcHandlerInterface contract.
 *
 * This test ensures that all implementations of JsonRpcHandlerInterface
 * properly implement the required methods for JSON-RPC 2.0 handling.
 */
class JsonRpcHandlerInterfaceTest extends TestCase
{
    /** @var JsonRpcHandlerInterface&MockObject */
    protected $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->createMock(JsonRpcHandlerInterface::class);
    }

    /**
     * Test handleRequest with valid request.
     */
    public function test_handle_request_with_valid_request(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'result' => 'pong',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleRequest($request);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleRequest with params.
     */
    public function test_handle_request_with_params(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculator',
                'arguments' => ['a' => 5, 'b' => 3],
            ],
            'id' => 'abc-123',
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'result' => ['sum' => 8],
            'id' => 'abc-123',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleRequest($request);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleNotification with valid notification.
     */
    public function test_handle_notification(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleNotification')
            ->with($notification);

        $this->handler->handleNotification($notification);
    }

    /**
     * Test handleNotification with params.
     */
    public function test_handle_notification_with_params(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'logging/message',
            'params' => [
                'level' => 'info',
                'message' => 'Server started',
            ],
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleNotification')
            ->with($notification);

        $this->handler->handleNotification($notification);
    }

    /**
     * Test handleResponse with success response.
     */
    public function test_handle_response_success(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'result' => ['tools' => ['calculator', 'weather']],
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResponse')
            ->with($response);

        $this->handler->handleResponse($response);
    }

    /**
     * Test handleResponse with error response.
     */
    public function test_handle_response_error(): void
    {
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32601,
                'message' => 'Method not found',
            ],
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleResponse')
            ->with($response);

        $this->handler->handleResponse($response);
    }

    /**
     * Test createRequest without params.
     */
    public function test_create_request_without_params(): void
    {
        $expectedRequest = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('createRequest')
            ->with('ping', [], 1)
            ->willReturn($expectedRequest);

        $request = $this->handler->createRequest('ping', [], 1);

        $this->assertSame($expectedRequest, $request);
    }

    /**
     * Test createRequest with params.
     */
    public function test_create_request_with_params(): void
    {
        $params = ['name' => 'test', 'value' => 123];

        $expectedRequest = [
            'jsonrpc' => '2.0',
            'method' => 'test/method',
            'params' => $params,
            'id' => 'unique-id',
        ];

        $this->handler
            ->expects($this->once())
            ->method('createRequest')
            ->with('test/method', $params, 'unique-id')
            ->willReturn($expectedRequest);

        $request = $this->handler->createRequest('test/method', $params, 'unique-id');

        $this->assertSame($expectedRequest, $request);
    }

    /**
     * Test createRequest for notification (no ID).
     */
    public function test_create_request_notification(): void
    {
        $expectedNotification = [
            'jsonrpc' => '2.0',
            'method' => 'notify',
        ];

        $this->handler
            ->expects($this->once())
            ->method('createRequest')
            ->with('notify', [], null)
            ->willReturn($expectedNotification);

        $notification = $this->handler->createRequest('notify', [], null);

        $this->assertSame($expectedNotification, $notification);
    }

    /**
     * Test createSuccessResponse.
     */
    public function test_create_success_response(): void
    {
        $result = ['data' => 'test data'];
        $id = 42;

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id,
        ];

        $this->handler
            ->expects($this->once())
            ->method('createSuccessResponse')
            ->with($result, $id)
            ->willReturn($expectedResponse);

        $response = $this->handler->createSuccessResponse($result, $id);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test createErrorResponse without data.
     */
    public function test_create_error_response_without_data(): void
    {
        $expectedResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32602,
                'message' => 'Invalid params',
            ],
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with(-32602, 'Invalid params', null, 1)
            ->willReturn($expectedResponse);

        $response = $this->handler->createErrorResponse(-32602, 'Invalid params', null, 1);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test createErrorResponse with data.
     */
    public function test_create_error_response_with_data(): void
    {
        $errorData = ['field' => 'name', 'reason' => 'required'];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32602,
                'message' => 'Validation failed',
                'data' => $errorData,
            ],
            'id' => 'test-id',
        ];

        $this->handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with(-32602, 'Validation failed', $errorData, 'test-id')
            ->willReturn($expectedResponse);

        $response = $this->handler->createErrorResponse(-32602, 'Validation failed', $errorData, 'test-id');

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test createErrorResponse for notification (null ID).
     */
    public function test_create_error_response_for_notification(): void
    {
        $expectedResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error',
            ],
            'id' => null,
        ];

        $this->handler
            ->expects($this->once())
            ->method('createErrorResponse')
            ->with(-32700, 'Parse error', null, null)
            ->willReturn($expectedResponse);

        $response = $this->handler->createErrorResponse(-32700, 'Parse error', null, null);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test validateMessage with valid message.
     */
    public function test_validate_message_valid(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('validateMessage')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->validateMessage($message));
    }

    /**
     * Test validateMessage with invalid message.
     */
    public function test_validate_message_invalid(): void
    {
        $message = [
            'method' => 'test', // Missing jsonrpc field
        ];

        $this->handler
            ->expects($this->once())
            ->method('validateMessage')
            ->with($message)
            ->willReturn(false);

        $this->assertFalse($this->handler->validateMessage($message));
    }

    /**
     * Test isRequest with valid request.
     */
    public function test_is_request_valid(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('isRequest')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->isRequest($message));
    }

    /**
     * Test isRequest with notification (not a request).
     */
    public function test_is_request_with_notification(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            // No ID field
        ];

        $this->handler
            ->expects($this->once())
            ->method('isRequest')
            ->with($message)
            ->willReturn(false);

        $this->assertFalse($this->handler->isRequest($message));
    }

    /**
     * Test isNotification with valid notification.
     */
    public function test_is_notification_valid(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            // No ID field
        ];

        $this->handler
            ->expects($this->once())
            ->method('isNotification')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->isNotification($message));
    }

    /**
     * Test isNotification with request (not a notification).
     */
    public function test_is_notification_with_request(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1, // Has ID, so it's a request
        ];

        $this->handler
            ->expects($this->once())
            ->method('isNotification')
            ->with($message)
            ->willReturn(false);

        $this->assertFalse($this->handler->isNotification($message));
    }

    /**
     * Test isResponse with success response.
     */
    public function test_is_response_success(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'result' => 'test',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('isResponse')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->isResponse($message));
    }

    /**
     * Test isResponse with error response.
     */
    public function test_is_response_error(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('isResponse')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->isResponse($message));
    }

    /**
     * Test isResponse with request (not a response).
     */
    public function test_is_response_with_request(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('isResponse')
            ->with($message)
            ->willReturn(false);

        $this->assertFalse($this->handler->isResponse($message));
    }

    /**
     * Test handling batch requests.
     */
    public function test_handle_batch_request(): void
    {
        $batchRequest = [
            ['jsonrpc' => '2.0', 'method' => 'ping', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'tools/list', 'id' => 2],
            ['jsonrpc' => '2.0', 'method' => 'initialized'], // Notification
        ];

        $expectedResponse = [
            ['jsonrpc' => '2.0', 'result' => 'pong', 'id' => 1],
            ['jsonrpc' => '2.0', 'result' => ['tools' => []], 'id' => 2],
            // No response for notification
        ];

        $this->handler
            ->expects($this->once())
            ->method('handleRequest')
            ->with($batchRequest)
            ->willReturn($expectedResponse);

        $response = $this->handler->handleRequest($batchRequest);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test all JSON-RPC error codes.
     */
    public function test_json_rpc_error_codes(): void
    {
        $errorCodes = [
            -32700 => 'Parse error',
            -32600 => 'Invalid Request',
            -32601 => 'Method not found',
            -32602 => 'Invalid params',
            -32603 => 'Internal error',
            -32000 => 'Server error',
        ];

        // Set up expectation for exactly 6 calls
        $this->handler
            ->expects($this->exactly(6))
            ->method('createErrorResponse')
            ->willReturnCallback(function ($code, $message, $data, $id) use ($errorCodes) {
                $this->assertArrayHasKey($code, $errorCodes);
                $this->assertEquals($errorCodes[$code], $message);
                $this->assertNull($data);
                $this->assertNull($id);

                return [
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => $code,
                        'message' => $message,
                    ],
                    'id' => $id,
                ];
            });

        foreach ($errorCodes as $code => $message) {
            $result = $this->handler->createErrorResponse($code, $message, null, null);
            $this->assertArrayHasKey('error', $result);
            $this->assertEquals($code, $result['error']['code']);
            $this->assertEquals($message, $result['error']['message']);
        }
    }
}
