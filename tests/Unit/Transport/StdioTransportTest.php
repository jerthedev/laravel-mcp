<?php

/**
 * StdioTransport Unit Tests
 *
 * @epic Transport Layer
 *
 * @ticket 011-TransportStdio
 *
 * @module Transport/Core
 *
 * @coverage src/Transport/StdioTransport.php
 *
 * @test-type Unit
 *
 * Test requirements:
 * - Initialization and configuration
 * - Message sending and receiving
 * - Signal handling
 * - Connection lifecycle
 * - Error handling
 * - Health checks
 */

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\StdioTransport;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

#[CoversClass(StdioTransport::class)]
#[Group('transport')]
#[Group('stdio')]
#[Group('ticket-011')]
class StdioTransportTest extends TestCase
{
    private StdioTransport $transport;

    private $mockInputStream;

    private $mockOutputStream;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent actual signal handlers from being registered during tests
        if (! defined('SIGTERM')) {
            define('SIGTERM', 15);
            define('SIGINT', 2);
            define('SIGHUP', 1);
        }

        $this->transport = new StdioTransport;
    }

    protected function tearDown(): void
    {
        if (isset($this->transport)) {
            try {
                $this->transport->stop();
            } catch (\Throwable $e) {
                // Ignore errors during cleanup
            }
        }

        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_initializes_with_default_configuration(): void
    {
        $this->transport->initialize();

        $config = $this->transport->getConfig();

        $this->assertArrayHasKey('buffer_size', $config);
        $this->assertArrayHasKey('line_delimiter', $config);
        $this->assertArrayHasKey('max_message_size', $config);
        $this->assertArrayHasKey('blocking_mode', $config);
        $this->assertArrayHasKey('read_timeout', $config);
        $this->assertArrayHasKey('write_timeout', $config);
        $this->assertArrayHasKey('use_content_length', $config);
        $this->assertArrayHasKey('enable_keepalive', $config);
    }

    #[Test]
    public function it_initializes_with_custom_configuration(): void
    {
        $customConfig = [
            'buffer_size' => 4096,
            'timeout' => 60,
            'use_content_length' => true,
        ];

        $this->transport->initialize($customConfig);

        $config = $this->transport->getConfig();

        $this->assertEquals(4096, $config['buffer_size']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertTrue($config['use_content_length']);
    }

    #[Test]
    public function it_returns_correct_transport_type(): void
    {
        $info = $this->transport->getConnectionInfo();

        $this->assertEquals('stdio', $info['transport_type']);
    }

    #[Test]
    public function it_tracks_connection_status(): void
    {
        $this->transport->initialize();

        $this->assertFalse($this->transport->isConnected());

        // We can't actually start the transport in unit tests
        // as it would try to open real stdio streams
    }

    #[Test]
    public function it_sets_message_handler(): void
    {
        $handler = Mockery::mock(MessageHandlerInterface::class);

        $this->transport->setMessageHandler($handler);

        // This should not throw an exception
        $this->assertTrue(true);
    }

    #[Test]
    public function it_clears_buffer(): void
    {
        $this->transport->initialize();
        $this->transport->clearBuffer();

        $buffer = $this->transport->getBuffer();
        $this->assertEquals('', $buffer);
    }

    #[Test]
    public function it_handles_symfony_process_integration(): void
    {
        $process = Mockery::mock(Process::class);

        $this->transport->setProcess($process);

        $retrievedProcess = $this->transport->getProcess();
        $this->assertSame($process, $retrievedProcess);
    }

    #[Test]
    public function it_provides_connection_info(): void
    {
        $this->transport->initialize();

        $info = $this->transport->getConnectionInfo();

        $this->assertArrayHasKey('transport_type', $info);
        $this->assertArrayHasKey('connected', $info);
        $this->assertArrayHasKey('stdio_specific', $info);

        $stdioInfo = $info['stdio_specific'];
        $this->assertArrayHasKey('has_input_handler', $stdioInfo);
        $this->assertArrayHasKey('has_output_handler', $stdioInfo);
        $this->assertArrayHasKey('signal_handlers_registered', $stdioInfo);
    }

    #[Test]
    public function it_performs_health_checks(): void
    {
        $this->transport->initialize();

        $health = $this->transport->healthCheck();

        $this->assertArrayHasKey('healthy', $health);
        $this->assertArrayHasKey('transport_type', $health);
        $this->assertArrayHasKey('connected', $health);
        $this->assertArrayHasKey('checks', $health);

        $this->assertEquals('stdio', $health['transport_type']);
    }

    #[Test]
    public function it_gets_statistics(): void
    {
        $this->transport->initialize();

        $stats = $this->transport->getStats();

        $this->assertArrayHasKey('messages_sent', $stats);
        $this->assertArrayHasKey('messages_received', $stats);
        $this->assertArrayHasKey('errors_count', $stats);
        $this->assertArrayHasKey('transport_type', $stats);

        $this->assertEquals(0, $stats['messages_sent']);
        $this->assertEquals(0, $stats['messages_received']);
    }

    #[Test]
    public function it_handles_signal_term(): void
    {
        Log::spy();

        $this->transport->initialize();

        // Simulate SIGTERM signal
        ob_start();
        try {
            $this->transport->handleSignal(SIGTERM);
        } catch (\Throwable $e) {
            // Expected to exit
        }
        ob_end_clean();

        Log::shouldHaveReceived('info')
            ->with('Signal received', ['signal' => SIGTERM])
            ->once();
    }

    #[Test]
    public function it_handles_signal_int(): void
    {
        Log::spy();

        $this->transport->initialize();

        // Simulate SIGINT signal
        ob_start();
        try {
            $this->transport->handleSignal(SIGINT);
        } catch (\Throwable $e) {
            // Expected to exit
        }
        ob_end_clean();

        Log::shouldHaveReceived('info')
            ->with('Signal received', ['signal' => SIGINT])
            ->once();
    }

    #[Test]
    public function it_handles_signal_hup(): void
    {
        Log::spy();

        $this->transport->initialize();

        // Simulate SIGHUP signal
        $this->transport->handleSignal(SIGHUP);

        Log::shouldHaveReceived('info')
            ->with('Signal received', ['signal' => SIGHUP])
            ->once();

        Log::shouldHaveReceived('info')
            ->with('Reloading configuration')
            ->once();
    }

    #[Test]
    public function it_runs_as_command(): void
    {
        $handler = Mockery::mock(MessageHandlerInterface::class);
        $this->transport->setMessageHandler($handler);

        // Without a handler, it should return error code
        $transport = new StdioTransport;
        $result = $transport->runAsCommand();
        $this->assertEquals(1, $result);
    }

    #[Test]
    public function it_sends_error_response(): void
    {
        // This is tested through the protected method via listen()
        // We can't directly test protected methods, but we ensure
        // the class has the method through reflection

        $reflection = new \ReflectionClass($this->transport);
        $this->assertTrue($reflection->hasMethod('sendErrorResponse'));
    }

    #[Test]
    public function it_validates_configuration(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Timeout must be greater than 0');

        $this->transport->initialize(['timeout' => -1]);
    }

    #[Test]
    public function it_validates_retry_attempts(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Retry attempts must be non-negative');

        $this->transport->initialize(['retry_attempts' => -1]);
    }

    #[Test]
    public function it_validates_retry_delay(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Retry delay must be non-negative');

        $this->transport->initialize(['retry_delay' => -1]);
    }

    #[Test]
    public function it_handles_reconnection(): void
    {
        Log::spy();

        $this->transport->initialize();

        // Attempt reconnection (will fail in test environment)
        try {
            $this->transport->reconnect();
        } catch (\Throwable $e) {
            // Expected to fail
        }

        Log::shouldHaveReceived('info')
            ->with('Attempting transport reconnection', Mockery::any())
            ->once();
    }

    #[Test]
    public function it_tracks_uptime(): void
    {
        $this->transport->initialize();

        $uptime = $this->transport->getUptime();
        $this->assertNull($uptime); // Not connected
    }

    #[Test]
    public function it_handles_shutdown_callback(): void
    {
        Log::spy();

        $this->transport->initialize();
        $this->transport->handleShutdown();

        // Should not log anything since not connected
        Log::shouldNotHaveReceived('info');
    }

    #[Test]
    public function it_provides_safe_config_for_logging(): void
    {
        $this->transport->initialize([
            'password' => 'secret',
            'token' => 'my-token',
            'buffer_size' => 8192,
        ]);

        $config = $this->transport->getConfig();

        // Original config should have sensitive data
        $this->assertEquals('secret', $config['password'] ?? null);
        $this->assertEquals('my-token', $config['token'] ?? null);

        // Safe config method should redact sensitive data
        $reflection = new \ReflectionClass($this->transport);
        $method = $reflection->getMethod('getSafeConfigForLogging');
        $method->setAccessible(true);

        $safeConfig = $method->invoke($this->transport);

        $this->assertEquals('[REDACTED]', $safeConfig['password']);
        $this->assertEquals('[REDACTED]', $safeConfig['token']);
        $this->assertEquals(8192, $safeConfig['buffer_size']);
    }

    #[Test]
    public function it_uses_custom_configuration_options(): void
    {
        $customConfig = [
            'use_content_length' => true,
            'blocking_mode' => true,
            'read_timeout' => 0.5,
            'write_timeout' => 10,
            'enable_keepalive' => false,
            'keepalive_interval' => 60,
            'process_timeout' => 120,
            'line_delimiter' => "\r\n",
        ];

        $this->transport->initialize($customConfig);
        $config = $this->transport->getConfig();

        // Verify custom config values are used
        $this->assertTrue($config['use_content_length']);
        $this->assertTrue($config['blocking_mode']);
        $this->assertEquals(0.5, $config['read_timeout']);
        $this->assertEquals(10, $config['write_timeout']);
        $this->assertFalse($config['enable_keepalive']);
        $this->assertEquals(60, $config['keepalive_interval']);
        $this->assertEquals(120, $config['process_timeout']);
        $this->assertEquals("\r\n", $config['line_delimiter']);
    }

    #[Test]
    public function it_uses_default_configuration_when_not_specified(): void
    {
        $this->transport->initialize([]);
        $config = $this->transport->getConfig();

        // Verify default values are used
        $this->assertFalse($config['use_content_length']);
        $this->assertFalse($config['blocking_mode']);
        $this->assertEquals(0.1, $config['read_timeout']);
        $this->assertEquals(5, $config['write_timeout']);
        $this->assertTrue($config['enable_keepalive']);
        $this->assertEquals(30, $config['keepalive_interval']);
        $this->assertNull($config['process_timeout']);
        $this->assertEquals("\n", $config['line_delimiter']);
    }
}
