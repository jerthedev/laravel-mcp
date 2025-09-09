<?php

/**
 * StdioTransport Integration Tests
 *
 * @epic Transport Layer
 *
 * @ticket 011-TransportStdio
 *
 * @module Transport/Integration
 *
 * @coverage src/Transport/StdioTransport.php
 *
 * @test-type Feature
 *
 * Test requirements:
 * - End-to-end stdio communication
 * - Error scenario handling
 * - Process lifecycle management
 * - Message exchange patterns
 */

namespace Tests\Feature\Transport;

use Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Transport\MessageFramer;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;

#[Group('transport')]
#[Group('stdio')]
#[Group('feature')]
#[Group('ticket-011')]
class StdioTransportIntegrationTest extends TestCase
{
    private TransportManager $manager;

    private MessageFramer $framer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(TransportManager::class);
        $this->framer = new MessageFramer;
    }

    protected function tearDown(): void
    {
        $this->manager->purgeAll();
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_stdio_transport_through_manager(): void
    {
        $transport = $this->manager->createTransport('stdio');

        $this->assertInstanceOf(StdioTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_initializes_stdio_transport_with_config(): void
    {
        $config = [
            'buffer_size' => 4096,
            'timeout' => 60,
            'use_content_length' => true,
        ];

        $transport = $this->manager->createTransport('stdio', $config);

        $transportConfig = $transport->getConfig();
        $this->assertEquals(4096, $transportConfig['buffer_size']);
        $this->assertEquals(60, $transportConfig['timeout']);
        $this->assertTrue($transportConfig['use_content_length']);
    }

    #[Test]
    public function it_manages_stdio_transport_lifecycle(): void
    {
        $transport = $this->manager->driver('stdio');

        $this->assertFalse($transport->isConnected());

        // Note: We can't actually start stdio transport in tests
        // as it would block waiting for input

        $info = $transport->getConnectionInfo();
        $this->assertEquals('stdio', $info['transport_type']);
        $this->assertFalse($info['connected']);
    }

    #[Test]
    public function it_simulates_message_exchange_with_mock_streams(): void
    {
        // Test message framing
        $request = $this->framer->createRequest('test', null, 1);
        $framedRequest = $this->framer->frame($request);

        $this->assertStringContainsString('"method":"test"', $framedRequest);
        $this->assertStringContainsString('"id":1', $framedRequest);

        // Test response framing
        $response = $this->framer->createResponse('success', 1);
        $framedResponse = $this->framer->frame($response);

        $this->assertStringContainsString('"result":"success"', $framedResponse);
        $this->assertStringContainsString('"id":1', $framedResponse);
    }

    #[Test]
    public function it_handles_json_rpc_error_responses(): void
    {
        $errorResponse = $this->framer->createErrorResponse(
            -32600,
            'Invalid Request',
            ['details' => 'Missing required field'],
            1
        );

        $framedError = $this->framer->frame($errorResponse);

        $this->assertStringContainsString('"error":', $framedError);
        $this->assertStringContainsString('"code":-32600', $framedError);
        $this->assertStringContainsString('"message":"Invalid Request"', $framedError);
    }

    #[Test]
    public function it_processes_notifications_without_id(): void
    {
        $notification = $this->framer->createRequest('notify', ['data' => 'test']);

        $this->assertArrayNotHasKey('id', $notification);

        $framedNotification = $this->framer->frame($notification);
        $this->assertStringContainsString('"method":"notify"', $framedNotification);
        $this->assertStringNotContainsString('"id":', $framedNotification);
    }

    #[Test]
    public function it_handles_large_messages(): void
    {
        $largeData = str_repeat('x', 10000);
        $request = $this->framer->createRequest('process', ['data' => $largeData], 1);

        $framedRequest = $this->framer->frame($request);

        $this->assertGreaterThan(10000, strlen($framedRequest));

        // Parse it back
        $messages = $this->framer->parse($framedRequest);

        $this->assertCount(1, $messages);
        $this->assertEquals($largeData, $messages[0]['params']['data']);
    }

    #[Test]
    public function it_handles_content_length_framing(): void
    {
        $framer = new MessageFramer(['use_content_length' => true]);

        $request = $framer->createRequest('test', ['param' => 'value'], 1);
        $framedRequest = $framer->frame($request);

        $this->assertStringContainsString('Content-Length:', $framedRequest);
        $this->assertStringContainsString('Content-Type: application/json', $framedRequest);
        $this->assertStringContainsString("\r\n\r\n", $framedRequest);

        // Parse it back
        $messages = $framer->parse($framedRequest);

        $this->assertCount(1, $messages);
        $this->assertEquals('test', $messages[0]['method']);
        $this->assertEquals(['param' => 'value'], $messages[0]['params']);
    }

    #[Test]
    public function it_handles_multiple_messages_in_buffer(): void
    {
        $message1 = $this->framer->createRequest('method1', null, 1);
        $message2 = $this->framer->createRequest('method2', null, 2);
        $message3 = $this->framer->createRequest('method3', null, 3);

        $framedMessages = $this->framer->frame($message1).
                         $this->framer->frame($message2).
                         $this->framer->frame($message3);

        $messages = $this->framer->parse($framedMessages);

        $this->assertCount(3, $messages);
        $this->assertEquals('method1', $messages[0]['method']);
        $this->assertEquals('method2', $messages[1]['method']);
        $this->assertEquals('method3', $messages[2]['method']);
    }

    #[Test]
    public function it_handles_partial_message_buffering(): void
    {
        $request = $this->framer->createRequest('test', ['data' => 'value'], 1);
        $framedRequest = $this->framer->frame($request);

        // Split message into parts
        $part1 = substr($framedRequest, 0, 20);
        $part2 = substr($framedRequest, 20, 20);
        $part3 = substr($framedRequest, 40);

        // Parse in parts
        $messages = $this->framer->parse($part1);
        $this->assertCount(0, $messages);

        $messages = $this->framer->parse($part2);
        $this->assertCount(0, $messages);

        $messages = $this->framer->parse($part3);
        $this->assertCount(1, $messages);
        $this->assertEquals('test', $messages[0]['method']);
    }

    #[Test]
    public function it_tracks_transport_health(): void
    {
        $transport = $this->manager->driver('stdio');

        $health = $transport->healthCheck();

        $this->assertArrayHasKey('healthy', $health);
        $this->assertArrayHasKey('transport_type', $health);
        $this->assertArrayHasKey('checks', $health);

        $this->assertEquals('stdio', $health['transport_type']);

        // Check specific stdio health indicators
        $this->assertArrayHasKey('input_handler_available', $health['checks']);
        $this->assertArrayHasKey('output_handler_available', $health['checks']);
    }

    #[Test]
    public function it_integrates_with_symfony_process(): void
    {
        $transport = new StdioTransport;

        // Test setting and getting process
        $process = Mockery::mock(Process::class);
        $transport->setProcess($process);

        $this->assertSame($process, $transport->getProcess());

        // Test that stop would attempt to stop the process
        $process->shouldReceive('isRunning')
            ->andReturn(true);

        $process->shouldReceive('stop')
            ->with(5)
            ->andReturn(null);

        // Initialize and stop the transport
        $transport->initialize();
        $transport->stop();
    }

    #[Test]
    public function it_provides_comprehensive_connection_info(): void
    {
        $transport = $this->manager->driver('stdio');

        $info = $transport->getConnectionInfo();

        $this->assertArrayHasKey('transport_type', $info);
        $this->assertArrayHasKey('connected', $info);
        $this->assertArrayHasKey('uptime', $info);
        $this->assertArrayHasKey('stats', $info);
        $this->assertArrayHasKey('stdio_specific', $info);

        $stdioInfo = $info['stdio_specific'];
        $this->assertArrayHasKey('has_input_handler', $stdioInfo);
        $this->assertArrayHasKey('has_output_handler', $stdioInfo);
        $this->assertArrayHasKey('input_stats', $stdioInfo);
        $this->assertArrayHasKey('output_stats', $stdioInfo);
        $this->assertArrayHasKey('framer_stats', $stdioInfo);
        $this->assertArrayHasKey('signal_handlers_registered', $stdioInfo);
        $this->assertArrayHasKey('process_running', $stdioInfo);
    }

    #[Test]
    public function it_handles_keepalive_messages(): void
    {
        $keepalive = $this->framer->createRequest('keepalive', ['timestamp' => time()]);

        $this->assertEquals('keepalive', $keepalive['method']);
        $this->assertArrayHasKey('timestamp', $keepalive['params']);

        $framedKeepalive = $this->framer->frame($keepalive);
        $this->assertStringContainsString('"method":"keepalive"', $framedKeepalive);
    }

    #[Test]
    public function it_manages_transport_through_manager(): void
    {
        // Create and register transport
        $transport = $this->manager->driver('stdio');

        // Check active transports
        $active = $this->manager->getActiveTransports();
        $this->assertArrayHasKey('stdio', $active);

        // Get transport health
        $health = $this->manager->getTransportHealth();
        $this->assertArrayHasKey('stdio', $health);

        // Purge transport
        $this->manager->purge('stdio');

        $active = $this->manager->getActiveTransports();
        $this->assertArrayNotHasKey('stdio', $active);
    }

    #[Test]
    public function it_supports_custom_configuration(): void
    {
        $customConfig = [
            'buffer_size' => 16384,
            'max_message_size' => 5242880, // 5MB
            'use_content_length' => true,
            'enable_keepalive' => false,
            'timeout' => 120,
        ];

        $transport = $this->manager->createCustomTransport('stdio', $customConfig);

        $config = $transport->getConfig();
        $this->assertEquals(16384, $config['buffer_size']);
        $this->assertEquals(5242880, $config['max_message_size']);
        $this->assertTrue($config['use_content_length']);
        $this->assertFalse($config['enable_keepalive']);
        $this->assertEquals(120, $config['timeout']);
    }
}
