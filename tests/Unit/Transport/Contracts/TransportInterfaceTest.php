<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport\Contracts;

use JTD\LaravelMCP\Tests\TestCase;
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
     * Test listen method starts listening for messages.
     */
    public function test_listen_starts_listening(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('listen');

        $this->transport->listen();
    }

    /**
     * Test send method sends messages.
     */
    public function test_send_message(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'params' => [],
            'id' => 1,
        ];

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
        $message = [
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => [
                    ['name' => 'calculator', 'description' => 'Math operations'],
                    ['name' => 'weather', 'description' => 'Weather info'],
                ],
            ],
            'id' => 'abc-123',
        ];

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
        $expectedMessage = [
            'jsonrpc' => '2.0',
            'method' => 'ping',
            'id' => 1,
        ];

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
     * Test close method closes connection.
     */
    public function test_close_connection(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('close');

        $this->transport->close();
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
     * Test getConfig returns configuration.
     */
    public function test_get_config(): void
    {
        $expectedConfig = [
            'host' => 'localhost',
            'port' => 3000,
            'ssl' => false,
        ];

        $this->transport
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn($expectedConfig);

        $config = $this->transport->getConfig();

        $this->assertSame($expectedConfig, $config);
    }

    /**
     * Test getConfig returns empty array when no config.
     */
    public function test_get_config_returns_empty_array(): void
    {
        $this->transport
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn([]);

        $config = $this->transport->getConfig();

        $this->assertSame([], $config);
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
     * Test lifecycle: initialize, connect, send/receive, close.
     */
    public function test_transport_lifecycle(): void
    {
        $config = ['host' => 'localhost'];
        $sendMessage = ['method' => 'ping'];
        $receiveMessage = ['result' => 'pong'];

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
            ->method('listen');

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
            ->method('close');

        // Execute lifecycle
        $this->assertFalse($this->transport->isConnected());

        $this->transport->initialize($config);
        $this->transport->listen();

        $this->assertTrue($this->transport->isConnected());

        $this->transport->send($sendMessage);
        $received = $this->transport->receive();
        $this->assertSame($receiveMessage, $received);

        $this->assertTrue($this->transport->isConnected());

        $this->transport->close();

        $this->assertFalse($this->transport->isConnected());
    }

    /**
     * Test multiple message send/receive operations.
     */
    public function test_multiple_message_operations(): void
    {
        $messages = [
            ['id' => 1, 'method' => 'tools/list'],
            ['id' => 2, 'method' => 'resources/list'],
            ['id' => 3, 'method' => 'prompts/list'],
        ];

        $responses = [
            ['id' => 1, 'result' => ['tools' => []]],
            ['id' => 2, 'result' => ['resources' => []]],
            ['id' => 3, 'result' => ['prompts' => []]],
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
     * Test configuration persistence after initialization.
     */
    public function test_configuration_persistence(): void
    {
        $config = [
            'host' => '192.168.1.100',
            'port' => 9000,
            'ssl' => true,
            'cert' => '/path/to/cert.pem',
        ];

        $this->transport
            ->expects($this->once())
            ->method('initialize')
            ->with($config);

        $this->transport
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn($config);

        $this->transport->initialize($config);
        $retrievedConfig = $this->transport->getConfig();

        $this->assertSame($config, $retrievedConfig);
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
