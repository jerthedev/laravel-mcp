<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport\Contracts;

use Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for TransportInterface contract.
 *
 * This test ensures that all implementations of TransportInterface
 * properly implement the required methods and behaviors.
 */
class TransportInterfaceTest extends TestCase
{
    /** @var TransportInterface&MockObject */
    protected $transport;

    /** @var MessageHandlerInterface&MockObject */
    protected $messageHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = $this->createMock(TransportInterface::class);
        $this->messageHandler = $this->createMock(MessageHandlerInterface::class);
    }

    /**
     * Test initialize method with configuration.
     */
    public function test_initialize_with_config(): void
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 8080,
            'timeout' => 30,
        ];

        $this->transport
            ->expects($this->once())
            ->method('initialize')
            ->with($config);

        $this->transport->initialize($config);
    }

    /**
     * Test initialize method without configuration.
     */
    public function test_initialize_without_config(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('initialize')
            ->with([]);

        $this->transport->initialize([]);
    }

    /**
     * Test start method starts the transport.
     */
    public function test_start_transport(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('start');

        $this->transport->start();
    }

    /**
     * Test stop method stops the transport.
     */
    public function test_stop_transport(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('stop');

        $this->transport->stop();
    }

    /**
     * Test send method sends messages.
     */
    public function test_send_message(): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ]);

        $this->transport
            ->expects($this->once())
            ->method('send')
            ->with($message);

        $this->transport->send($message);
    }

    /**
     * Test send method with complex message structure.
     */
    public function test_send_complex_message(): void
    {
        $message = json_encode([
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => [
                    ['name' => 'calculator', 'description' => 'Math operations'],
                    ['name' => 'weather', 'description' => 'Weather info'],
                ],
            ],
            'id' => 'abc-123',
        ]);

        $this->transport
            ->expects($this->once())
            ->method('send')
            ->with($message);

        $this->transport->send($message);
    }

    /**
     * Test receive method returns message.
     */
    public function test_receive_returns_message(): void
    {
        $expectedMessage = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ]);

        $this->transport
            ->expects($this->once())
            ->method('receive')
            ->willReturn($expectedMessage);

        $message = $this->transport->receive();

        $this->assertSame($expectedMessage, $message);
    }

    /**
     * Test receive method returns null when no message.
     */
    public function test_receive_returns_null_when_no_message(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('receive')
            ->willReturn(null);

        $message = $this->transport->receive();

        $this->assertNull($message);
    }

    /**
     * Test getConnectionInfo returns connection information.
     */
    public function test_get_connection_info(): void
    {
        $expectedInfo = [
            'type' => 'http',
            'host' => 'localhost',
            'port' => 8080,
            'connected' => true,
        ];

        $this->transport
            ->expects($this->once())
            ->method('getConnectionInfo')
            ->willReturn($expectedInfo);

        $info = $this->transport->getConnectionInfo();

        $this->assertSame($expectedInfo, $info);
    }

    /**
     * Test isConnected returns true when connected.
     */
    public function test_is_connected_returns_true(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);

        $this->assertTrue($this->transport->isConnected());
    }

    /**
     * Test isConnected returns false when disconnected.
     */
    public function test_is_connected_returns_false(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('isConnected')
            ->willReturn(false);

        $this->assertFalse($this->transport->isConnected());
    }

    /**
     * Test getConnectionInfo returns empty array when no connection.
     */
    public function test_get_connection_info_returns_empty_array(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('getConnectionInfo')
            ->willReturn([]);

        $info = $this->transport->getConnectionInfo();

        $this->assertSame([], $info);
    }

    /**
     * Test setMessageHandler sets handler.
     */
    public function test_set_message_handler(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('setMessageHandler')
            ->with($this->messageHandler);

        $this->transport->setMessageHandler($this->messageHandler);
    }

    /**
     * Test lifecycle: initialize, start, send/receive, stop.
     */
    public function test_transport_lifecycle(): void
    {
        $config = ['host' => 'localhost'];
        $sendMessage = json_encode(['method' => 'ping']);
        $receiveMessage = json_encode(['result' => 'pong']);

        // Set up expectations in order
        $this->transport
            ->expects($this->exactly(4))
            ->method('isConnected')
            ->willReturnOnConsecutiveCalls(false, true, true, false);

        $this->transport
            ->expects($this->once())
            ->method('initialize')
            ->with($config);

        $this->transport
            ->expects($this->once())
            ->method('start');

        $this->transport
            ->expects($this->once())
            ->method('send')
            ->with($sendMessage);

        $this->transport
            ->expects($this->once())
            ->method('receive')
            ->willReturn($receiveMessage);

        $this->transport
            ->expects($this->once())
            ->method('stop');

        // Execute lifecycle
        $this->assertFalse($this->transport->isConnected());

        $this->transport->initialize($config);
        $this->transport->start();

        $this->assertTrue($this->transport->isConnected());

        $this->transport->send($sendMessage);
        $received = $this->transport->receive();
        $this->assertSame($receiveMessage, $received);

        $this->assertTrue($this->transport->isConnected());

        $this->transport->stop();

        $this->assertFalse($this->transport->isConnected());
    }

    /**
     * Test multiple message send/receive operations.
     */
    public function test_multiple_message_operations(): void
    {
        $messages = [
            json_encode(['id' => 1, 'method' => 'tools/list']),
            json_encode(['id' => 2, 'method' => 'resources/list']),
            json_encode(['id' => 3, 'method' => 'prompts/list']),
        ];

        $responses = [
            json_encode(['id' => 1, 'result' => ['tools' => []]]),
            json_encode(['id' => 2, 'result' => ['resources' => []]]),
            json_encode(['id' => 3, 'result' => ['prompts' => []]]),
        ];

        // Set up send expectations
        $sendCallIndex = 0;
        $this->transport
            ->expects($this->exactly(3))
            ->method('send')
            ->willReturnCallback(function ($message) use ($messages, &$sendCallIndex) {
                $this->assertEquals($messages[$sendCallIndex], $message);
                $sendCallIndex++;
            });

        // Set up receive expectations
        $receiveCallIndex = 0;
        $this->transport
            ->expects($this->exactly(3))
            ->method('receive')
            ->willReturnCallback(function () use ($responses, &$receiveCallIndex) {
                $result = $responses[$receiveCallIndex];
                $receiveCallIndex++;

                return $result;
            });

        // Execute operations
        foreach ($messages as $index => $message) {
            $this->transport->send($message);
            $received = $this->transport->receive();
            $this->assertSame($responses[$index], $received);
        }
    }

    /**
     * Test connection information after initialization.
     */
    public function test_connection_info_after_initialization(): void
    {
        $config = [
            'host' => '192.168.1.100',
            'port' => 9000,
            'ssl' => true,
            'cert' => '/path/to/cert.pem',
        ];

        $expectedInfo = [
            'type' => 'http',
            'host' => '192.168.1.100',
            'port' => 9000,
            'ssl' => true,
            'connected' => false,
        ];

        $this->transport
            ->expects($this->once())
            ->method('initialize')
            ->with($config);

        $this->transport
            ->expects($this->once())
            ->method('getConnectionInfo')
            ->willReturn($expectedInfo);

        $this->transport->initialize($config);
        $connectionInfo = $this->transport->getConnectionInfo();

        $this->assertSame($expectedInfo, $connectionInfo);
    }

    /**
     * Test setting multiple message handlers.
     */
    public function test_setting_multiple_handlers(): void
    {
        $handler1 = $this->createMock(MessageHandlerInterface::class);
        $handler2 = $this->createMock(MessageHandlerInterface::class);

        $handlers = [$handler1, $handler2];
        $callIndex = 0;

        $this->transport
            ->expects($this->exactly(2))
            ->method('setMessageHandler')
            ->willReturnCallback(function ($handler) use ($handlers, &$callIndex) {
                $this->assertSame($handlers[$callIndex], $handler);
                $callIndex++;
            });

        $this->transport->setMessageHandler($handler1);
        $this->transport->setMessageHandler($handler2);
    }
}
