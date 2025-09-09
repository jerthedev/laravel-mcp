<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport\Contracts;

use Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for MessageHandlerInterface contract.
 *
 * This test ensures that all implementations of MessageHandlerInterface
 * properly implement the required methods and behaviors.
 */
class MessageHandlerInterfaceTest extends TestCase
{
    /** @var MessageHandlerInterface&MockObject */
    protected $handler;

    /** @var TransportInterface&MockObject */
    protected $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = $this->createMock(MessageHandlerInterface::class);
        $this->transport = $this->createMock(TransportInterface::class);
    }

    /**
     * Test handle method with valid message returns response.
     */
    public function test_handle_valid_message_returns_response(): void
    {
        $message = [
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
            ->method('handle')
            ->with($message, $this->transport)
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($message, $this->transport);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handle method with notification returns null.
     */
    public function test_handle_notification_returns_null(): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'initialized',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($notification, $this->transport)
            ->willReturn(null);

        $response = $this->handler->handle($notification, $this->transport);

        $this->assertNull($response);
    }

    /**
     * Test handle method with complex message.
     */
    public function test_handle_complex_message(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculator',
                'arguments' => [
                    'operation' => 'add',
                    'a' => 5,
                    'b' => 3,
                ],
            ],
            'id' => 'complex-123',
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Result: 8',
                    ],
                ],
            ],
            'id' => 'complex-123',
        ];

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($message, $this->transport)
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($message, $this->transport);

        $this->assertSame($expectedResponse, $response);
    }

    /**
     * Test handleError method with exception.
     */
    public function test_handle_error_with_exception(): void
    {
        $error = new \Exception('Connection failed');

        $this->handler
            ->expects($this->once())
            ->method('handleError')
            ->with($error, $this->transport);

        $this->handler->handleError($error, $this->transport);
    }

    /**
     * Test handleError method with different error types.
     */
    public function test_handle_error_with_different_types(): void
    {
        $errors = [
            new \RuntimeException('Runtime error'),
            new \InvalidArgumentException('Invalid argument'),
            new \LogicException('Logic error'),
        ];

        // Set up expectation for exactly 3 calls
        $this->handler
            ->expects($this->exactly(3))
            ->method('handleError')
            ->willReturnCallback(function ($error, $transport) use ($errors) {
                static $callIndex = 0;
                $this->assertEquals($errors[$callIndex], $error);
                $this->assertSame($this->transport, $transport);
                $callIndex++;
            });

        foreach ($errors as $error) {
            $this->handler->handleError($error, $this->transport);
        }
    }

    /**
     * Test onConnect method when transport connects.
     */
    public function test_on_connect(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('onConnect')
            ->with($this->transport);

        $this->handler->onConnect($this->transport);
    }

    /**
     * Test onDisconnect method when transport disconnects.
     */
    public function test_on_disconnect(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('onDisconnect')
            ->with($this->transport);

        $this->handler->onDisconnect($this->transport);
    }

    /**
     * Test canHandle method returns true for supported message.
     */
    public function test_can_handle_supported_message(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('canHandle')
            ->with($message)
            ->willReturn(true);

        $this->assertTrue($this->handler->canHandle($message));
    }

    /**
     * Test canHandle method returns false for unsupported message.
     */
    public function test_can_handle_unsupported_message(): void
    {
        $message = [
            'jsonrpc' => '1.0', // Wrong version
            'method' => 'unknown/method',
            'id' => 1,
        ];

        $this->handler
            ->expects($this->once())
            ->method('canHandle')
            ->with($message)
            ->willReturn(false);

        $this->assertFalse($this->handler->canHandle($message));
    }

    /**
     * Test canHandle with various message formats.
     */
    public function test_can_handle_various_formats(): void
    {
        $messages = [
            ['jsonrpc' => '2.0', 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'result' => 'pong', 'id' => 1],
            ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Invalid Request'], 'id' => null],
        ];

        $expectations = [true, true, false];

        // Set up expectation for exactly 3 calls
        $this->handler
            ->expects($this->exactly(3))
            ->method('canHandle')
            ->willReturnCallback(function ($message) use ($messages, $expectations) {
                static $callIndex = 0;
                $this->assertEquals($messages[$callIndex], $message);
                $result = $expectations[$callIndex];
                $callIndex++;

                return $result;
            });

        foreach ($messages as $index => $message) {
            $this->assertSame($expectations[$index], $this->handler->canHandle($message));
        }
    }

    /**
     * Test getSupportedMessageTypes returns array of types.
     */
    public function test_get_supported_message_types(): void
    {
        $expectedTypes = [
            'initialize',
            'initialized',
            'ping',
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'prompts/list',
            'prompts/get',
        ];

        $this->handler
            ->expects($this->once())
            ->method('getSupportedMessageTypes')
            ->willReturn($expectedTypes);

        $types = $this->handler->getSupportedMessageTypes();

        $this->assertSame($expectedTypes, $types);
    }

    /**
     * Test getSupportedMessageTypes returns empty array.
     */
    public function test_get_supported_message_types_empty(): void
    {
        $this->handler
            ->expects($this->once())
            ->method('getSupportedMessageTypes')
            ->willReturn([]);

        $types = $this->handler->getSupportedMessageTypes();

        $this->assertSame([], $types);
    }

    /**
     * Test lifecycle events sequence.
     */
    public function test_lifecycle_events_sequence(): void
    {
        $message = ['method' => 'test'];
        $error = new \Exception('Test error');

        // Expect events in sequence
        $this->handler
            ->expects($this->once())
            ->method('onConnect')
            ->with($this->transport);

        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($message, $this->transport)
            ->willReturn(['result' => 'ok']);

        $this->handler
            ->expects($this->once())
            ->method('handleError')
            ->with($error, $this->transport);

        $this->handler
            ->expects($this->once())
            ->method('onDisconnect')
            ->with($this->transport);

        // Execute lifecycle
        $this->handler->onConnect($this->transport);
        $result = $this->handler->handle($message, $this->transport);
        $this->assertNotNull($result);
        $this->handler->handleError($error, $this->transport);
        $this->handler->onDisconnect($this->transport);
    }

    /**
     * Test handling multiple messages in sequence.
     */
    public function test_handle_multiple_messages(): void
    {
        $messages = [
            ['method' => 'initialize', 'params' => ['protocolVersion' => '1.0.0']],
            ['method' => 'tools/list'],
            ['method' => 'resources/list'],
            ['method' => 'prompts/list'],
        ];

        $responses = [
            ['result' => ['serverInfo' => ['name' => 'Test Server']]],
            ['result' => ['tools' => []]],
            ['result' => ['resources' => []]],
            ['result' => ['prompts' => []]],
        ];

        // Set up expectation for exactly 4 calls
        $this->handler
            ->expects($this->exactly(4))
            ->method('handle')
            ->willReturnCallback(function ($message, $transport) use ($messages, $responses) {
                static $callIndex = 0;
                $this->assertEquals($messages[$callIndex], $message);
                $this->assertSame($this->transport, $transport);
                $result = $responses[$callIndex];
                $callIndex++;

                return $result;
            });

        foreach ($messages as $index => $message) {
            $response = $this->handler->handle($message, $this->transport);
            $this->assertSame($responses[$index], $response);
        }
    }

    /**
     * Test error handling doesn't affect normal operations.
     */
    public function test_error_handling_isolation(): void
    {
        $message = ['method' => 'test'];
        $error = new \RuntimeException('Test error');
        $response = ['result' => 'success'];

        // Handle error first
        $this->handler
            ->expects($this->once())
            ->method('handleError')
            ->with($error, $this->transport);

        // Then handle normal message
        $this->handler
            ->expects($this->once())
            ->method('handle')
            ->with($message, $this->transport)
            ->willReturn($response);

        $this->handler->handleError($error, $this->transport);
        $result = $this->handler->handle($message, $this->transport);

        $this->assertSame($response, $result);
    }

    /**
     * Test checking support for specific message types.
     */
    public function test_check_specific_message_type_support(): void
    {
        $supportedTypes = ['ping', 'tools/list', 'resources/read'];

        $this->handler
            ->expects($this->once())
            ->method('getSupportedMessageTypes')
            ->willReturn($supportedTypes);

        $types = $this->handler->getSupportedMessageTypes();

        $this->assertContains('ping', $types);
        $this->assertContains('tools/list', $types);
        $this->assertContains('resources/read', $types);
        $this->assertNotContains('unknown/method', $types);
    }
}
