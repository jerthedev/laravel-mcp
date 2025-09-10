<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\BaseTransport;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelMCP\Tests\TestCase;

#[Group('Epic-Transport')]
#[Group('Sprint-Core')]
#[Group('ticket-010')]
class BaseTransportTest extends TestCase
{
    private ConcreteTestTransport $transport;

    private MessageHandlerInterface $messageHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new ConcreteTestTransport;
        $this->messageHandler = $this->createMock(MessageHandlerInterface::class);
    }

    #[Test]
    public function it_initializes_with_default_configuration()
    {
        $this->transport->initialize();

        $config = $this->transport->getConfig();

        $this->assertArrayHasKey('timeout', $config);
        $this->assertEquals(30, $config['timeout']);
        $this->assertArrayHasKey('debug', $config);
        $this->assertFalse($config['debug']);
        $this->assertArrayHasKey('retry_attempts', $config);
        $this->assertEquals(3, $config['retry_attempts']);
    }

    #[Test]
    public function it_merges_custom_configuration_with_defaults()
    {
        $customConfig = [
            'timeout' => 60,
            'custom_option' => 'test_value',
        ];

        $this->transport->initialize($customConfig);

        $config = $this->transport->getConfig();

        $this->assertEquals(60, $config['timeout']);
        $this->assertEquals('test_value', $config['custom_option']);
        $this->assertFalse($config['debug']); // Default preserved
    }

    #[Test]
    public function it_validates_configuration_on_initialization()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        $this->transport->initialize(['timeout' => 0]);
    }

    #[Test]
    public function it_starts_and_stops_correctly()
    {
        $this->transport->initialize();

        $this->assertFalse($this->transport->isConnected());

        $this->transport->start();

        $this->assertTrue($this->transport->isConnected());
        $this->assertTrue($this->transport->wasStarted);

        $this->transport->stop();

        $this->assertFalse($this->transport->isConnected());
        $this->assertTrue($this->transport->wasStopped);
    }

    #[Test]
    public function it_prevents_double_start()
    {
        $this->transport->initialize();
        $this->transport->start();

        $initialStartCount = $this->transport->startCallCount;

        $this->transport->start(); // Second start should be ignored

        $this->assertEquals($initialStartCount, $this->transport->startCallCount);
    }

    #[Test]
    public function it_gracefully_handles_double_stop()
    {
        $this->transport->initialize();
        $this->transport->start();
        $this->transport->stop();

        $initialStopCount = $this->transport->stopCallCount;

        $this->transport->stop(); // Second stop should be graceful

        $this->assertEquals($initialStopCount, $this->transport->stopCallCount);
    }

    #[Test]
    public function it_sends_messages_when_connected()
    {
        $this->transport->initialize();
        $this->transport->start();

        $message = '{"jsonrpc": "2.0", "method": "test"}';
        $this->transport->send($message);

        $this->assertContains($message, $this->transport->sentMessages);

        $stats = $this->transport->getStats();
        $this->assertEquals(1, $stats['messages_sent']);
    }

    #[Test]
    public function it_throws_exception_when_sending_while_disconnected()
    {
        $this->transport->initialize();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Transport connection is closed');

        $this->transport->send('test message');
    }

    #[Test]
    public function it_receives_messages_when_connected()
    {
        $this->transport->initialize();
        $this->transport->start();

        $expectedMessage = '{"jsonrpc": "2.0", "result": "test"}';
        $this->transport->queueMessage($expectedMessage);

        $received = $this->transport->receive();

        $this->assertEquals($expectedMessage, $received);

        $stats = $this->transport->getStats();
        $this->assertEquals(1, $stats['messages_received']);
    }

    #[Test]
    public function it_returns_null_when_no_messages_to_receive()
    {
        $this->transport->initialize();
        $this->transport->start();

        $received = $this->transport->receive();

        $this->assertNull($received);
    }

    #[Test]
    public function it_tracks_statistics_correctly()
    {
        $this->transport->initialize();
        $this->transport->start();

        // Send some messages
        $this->transport->send('message 1');
        $this->transport->send('message 2');

        // Receive some messages
        $this->transport->queueMessage('response 1');
        $this->transport->queueMessage('response 2');
        $this->transport->receive();
        $this->transport->receive();

        $stats = $this->transport->getStats();

        $this->assertEquals(2, $stats['messages_sent']);
        $this->assertEquals(2, $stats['messages_received']);
        $this->assertEquals(0, $stats['errors_count']);
        $this->assertEquals('test', $stats['transport_type']);
        $this->assertTrue($stats['connected']);
        $this->assertIsInt($stats['uptime']);
    }

    #[Test]
    public function it_handles_errors_correctly()
    {
        $this->transport->initialize();
        $this->transport->start();
        $this->transport->setMessageHandler($this->messageHandler);

        $error = new \Exception('Test error');
        $this->transport->simulateError($error);

        $stats = $this->transport->getStats();
        $this->assertEquals(1, $stats['errors_count']);
    }

    #[Test]
    public function it_sets_and_uses_message_handler()
    {
        $this->transport->setMessageHandler($this->messageHandler);

        $this->messageHandler->expects($this->once())
            ->method('onConnect')
            ->with($this->transport);

        $this->messageHandler->expects($this->once())
            ->method('onDisconnect')
            ->with($this->transport);

        $this->transport->initialize();
        $this->transport->start();
        $this->transport->stop();
    }

    #[Test]
    public function it_calculates_uptime_correctly()
    {
        $this->transport->initialize();

        $this->assertNull($this->transport->getUptime());

        $this->transport->start();

        $uptime = $this->transport->getUptime();
        $this->assertIsInt($uptime);
        $this->assertGreaterThanOrEqual(0, $uptime);
    }

    #[Test]
    public function it_provides_connection_information()
    {
        $this->transport->initialize();
        $this->transport->start();

        $info = $this->transport->getConnectionInfo();

        $this->assertArrayHasKey('transport_type', $info);
        $this->assertEquals('test', $info['transport_type']);

        $this->assertArrayHasKey('connected', $info);
        $this->assertTrue($info['connected']);

        $this->assertArrayHasKey('uptime', $info);
        $this->assertIsInt($info['uptime']);

        $this->assertArrayHasKey('stats', $info);
        $this->assertIsArray($info['stats']);
    }

    #[Test]
    public function it_performs_health_checks()
    {
        $this->transport->initialize();
        $this->transport->start();

        $health = $this->transport->healthCheck();

        $this->assertArrayHasKey('healthy', $health);
        $this->assertTrue($health['healthy']);

        $this->assertArrayHasKey('transport_type', $health);
        $this->assertEquals('test', $health['transport_type']);

        $this->assertArrayHasKey('checks', $health);
        $this->assertTrue($health['checks']['connectivity']);
        $this->assertTrue($health['checks']['configuration']);
    }

    #[Test]
    public function it_fails_health_check_when_disconnected()
    {
        $this->transport->initialize();

        $health = $this->transport->healthCheck();

        $this->assertFalse($health['healthy']);
        $this->assertFalse($health['checks']['connectivity']);
    }

    #[Test]
    public function it_reconnects_successfully()
    {
        $this->transport->initialize();
        $this->transport->start();

        $this->assertTrue($this->transport->isConnected());

        $this->transport->reconnect();

        $this->assertTrue($this->transport->isConnected());
        $this->assertTrue($this->transport->wasStopped);
        $this->assertTrue($this->transport->wasStarted);
    }

    #[Test]
    public function it_handles_start_failure_gracefully()
    {
        $this->transport->initialize();
        $this->transport->shouldFailStart = true;

        $this->expectException(TransportException::class);

        $this->transport->start();

        $this->assertFalse($this->transport->isConnected());
    }

    #[Test]
    public function it_validates_negative_retry_attempts()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Retry attempts must be non-negative');

        $this->transport->initialize(['retry_attempts' => -1]);
    }

    #[Test]
    public function it_validates_negative_retry_delay()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Retry delay must be non-negative');

        $this->transport->initialize(['retry_delay' => -100]);
    }

    #[Test]
    public function it_sends_with_retry_on_failure()
    {
        $this->transport->initialize(['retry_attempts' => 2, 'retry_delay' => 1]);
        $this->transport->start();

        $this->transport->shouldFailSend = 1; // Fail first attempt, succeed second

        $this->transport->sendWithRetry('test message');

        $this->assertEquals(2, $this->transport->sendAttemptCount);
        $this->assertContains('test message', $this->transport->sentMessages);
    }

    #[Test]
    public function it_fails_after_max_retry_attempts()
    {
        $this->transport->initialize(['retry_attempts' => 2, 'retry_delay' => 1]);
        $this->transport->start();

        $this->transport->shouldFailSend = 3; // Fail more than max attempts

        $this->expectException(TransportException::class);

        $this->transport->sendWithRetry('test message');

        $this->assertEquals(2, $this->transport->sendAttemptCount);
    }

    #[Test]
    public function it_masks_sensitive_config_in_logs()
    {
        $sensitiveConfig = [
            'password' => 'secret123',
            'token' => 'abc123def',
            'public_setting' => 'visible',
        ];

        $this->transport->initialize($sensitiveConfig);

        $safeConfig = $this->transport->getSafeConfig();

        $this->assertEquals('[REDACTED]', $safeConfig['password']);
        $this->assertEquals('[REDACTED]', $safeConfig['token']);
        $this->assertEquals('visible', $safeConfig['public_setting']);
    }
}

/**
 * Concrete implementation of BaseTransport for testing purposes.
 */
class ConcreteTestTransport extends BaseTransport
{
    public bool $wasStarted = false;

    public bool $wasStopped = false;

    public bool $shouldFailStart = false;

    public int $shouldFailSend = 0; // Number of send attempts that should fail

    public int $startCallCount = 0;

    public int $stopCallCount = 0;

    public int $sendAttemptCount = 0;

    public array $sentMessages = [];

    public array $messageQueue = [];

    protected function getTransportType(): string
    {
        return 'test';
    }

    protected function getTransportDefaults(): array
    {
        return [
            'test_option' => 'default_value',
        ];
    }

    protected function doStart(): void
    {
        $this->startCallCount++;

        if ($this->shouldFailStart) {
            throw new \Exception('Simulated start failure');
        }

        $this->wasStarted = true;
    }

    protected function doStop(): void
    {
        $this->stopCallCount++;
        $this->wasStopped = true;
    }

    protected function doSend(string $message): void
    {
        $this->sendAttemptCount++;

        if ($this->shouldFailSend > 0) {
            $this->shouldFailSend--;
            throw new \Exception('Simulated send failure');
        }

        $this->sentMessages[] = $message;
    }

    protected function doReceive(): ?string
    {
        return array_shift($this->messageQueue);
    }

    // Helper methods for testing
    public function queueMessage(string $message): void
    {
        $this->messageQueue[] = $message;
    }

    public function simulateError(\Throwable $error): void
    {
        $this->handleError($error);
    }

    public function getSafeConfig(): array
    {
        return $this->getSafeConfigForLogging();
    }

    public function sendWithRetry(string $message): void
    {
        parent::sendWithRetry($message);
    }
}
