<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport\Concerns;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Concerns\SupportsBatching;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use PHPUnit\Framework\Attributes\Test;

class SupportsBatchingTest extends TestCase
{
    private TestTransportWithBatching $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new TestTransportWithBatching;
        $this->transport->initialize(['debug' => false]);
    }

    #[Test]
    public function it_can_initialize_batching_configuration(): void
    {
        $config = [
            'batching_enabled' => true,
            'batch_size' => 20,
            'batch_timeout' => 200,
        ];

        $this->transport->enableBatching($config);

        $stats = $this->transport->getBatchingStats();
        $this->assertTrue($stats['batching_enabled']);
        $this->assertEquals(20, $stats['batch_size_limit']);
        $this->assertEquals(200, $stats['batch_timeout_ms']);
    }

    #[Test]
    public function it_sends_messages_immediately_when_batching_disabled(): void
    {
        $this->transport->start();

        $this->transport->addToBatch('test message 1');
        $this->transport->addToBatch('test message 2');

        $this->assertCount(2, $this->transport->getSentMessages());
        $this->assertEquals(['test message 1', 'test message 2'], $this->transport->getSentMessages());
    }

    #[Test]
    public function it_batches_messages_when_enabled(): void
    {
        $this->transport->enableBatching([
            'batch_size' => 3,
            'batch_timeout' => 1000,
        ]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2');

        // Messages should be batched, not sent immediately
        $this->assertCount(0, $this->transport->getSentMessages());

        $stats = $this->transport->getBatchingStats();
        $this->assertEquals(2, $stats['pending_messages']);
    }

    #[Test]
    public function it_processes_batch_when_size_limit_reached(): void
    {
        $this->transport->enableBatching([
            'batch_size' => 3,
            'batch_timeout' => 1000,
        ]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2');
        $this->transport->addToBatch('message 3'); // Should trigger batch processing

        // Batch should be processed automatically
        $this->assertCount(3, $this->transport->getSentMessages());
        $this->assertEquals(['message 1', 'message 2', 'message 3'], $this->transport->getSentMessages());

        $stats = $this->transport->getBatchingStats();
        $this->assertEquals(0, $stats['pending_messages']);
        $this->assertEquals(1, $stats['batches_processed']);
    }

    #[Test]
    public function it_can_manually_flush_pending_batch(): void
    {
        $this->transport->enableBatching([
            'batch_size' => 10,
            'batch_timeout' => 1000,
        ]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2');

        // Manually flush the batch
        $this->transport->flushBatch();

        $this->assertCount(2, $this->transport->getSentMessages());
        $stats = $this->transport->getBatchingStats();
        $this->assertEquals(0, $stats['pending_messages']);
    }

    #[Test]
    public function it_handles_batch_timeout(): void
    {
        $this->transport->enableBatching([
            'batch_size' => 10,
            'batch_timeout' => 50, // 50ms timeout
        ]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');

        // Wait for timeout
        usleep(60000); // 60ms

        $this->transport->checkBatchTimeout();

        $this->assertCount(1, $this->transport->getSentMessages());
        $stats = $this->transport->getBatchingStats();
        $this->assertEquals(0, $stats['pending_messages']);
    }

    #[Test]
    public function it_can_disable_batching_and_flush_pending(): void
    {
        $this->transport->enableBatching(['batch_size' => 10]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2');

        $this->transport->disableBatching();

        // Should flush pending messages and disable batching
        $this->assertCount(2, $this->transport->getSentMessages());
        $this->assertFalse($this->transport->getBatchingStats()['batching_enabled']);
    }

    #[Test]
    public function it_tracks_batching_statistics(): void
    {
        $this->transport->enableBatching(['batch_size' => 2]);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2'); // Triggers batch 1

        $this->transport->addToBatch('message 3');
        $this->transport->addToBatch('message 4'); // Triggers batch 2

        $stats = $this->transport->getBatchingStats();
        $this->assertEquals(2, $stats['batches_processed']);
        $this->assertEquals(4, $stats['total_batched_messages']);
        $this->assertEquals(2.0, $stats['avg_batch_size']);
    }

    #[Test]
    public function it_handles_batch_processing_errors_gracefully(): void
    {
        $this->transport->enableBatching(['batch_size' => 2]);
        $this->transport->setShouldFailBatch(true);
        $this->transport->start();

        $this->transport->addToBatch('message 1');
        $this->transport->addToBatch('message 2'); // Should trigger batch that fails

        // Should fall back to individual sends
        $this->assertCount(2, $this->transport->getSentMessages());
        $this->assertContains('message 1', $this->transport->getSentMessages());
        $this->assertContains('message 2', $this->transport->getSentMessages());
    }
}

/**
 * Test transport implementation with batching support.
 */
class TestTransportWithBatching implements TransportInterface
{
    use SupportsBatching;

    protected array $config = [];

    protected bool $connected = false;

    protected array $sentMessages = [];

    protected array $stats = [];

    protected bool $shouldFailBatch = false;

    public function initialize(array $config = []): void
    {
        $this->config = array_merge(['debug' => false], $config);
        $this->stats = [];
        $this->initializeBatching($this->config);
    }

    public function start(): void
    {
        $this->connected = true;
    }

    public function stop(): void
    {
        $this->connected = false;
    }

    public function send(string $message): void
    {
        $this->sendImmediately($message);
    }

    public function receive(): ?string
    {
        return null;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function getConnectionInfo(): array
    {
        return ['connected' => $this->connected];
    }

    public function setMessageHandler($handler): void
    {
        // Not needed for this test
    }

    protected function getTransportType(): string
    {
        return 'test';
    }

    protected function doSend(string $message): void
    {
        $this->sentMessages[] = $message;
    }

    protected function sendBatch(array $messages): void
    {
        if ($this->shouldFailBatch) {
            throw new \Exception('Batch send failed for testing');
        }

        foreach ($messages as $message) {
            $this->sentMessages[] = $message;
        }
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function setShouldFailBatch(bool $shouldFail): void
    {
        $this->shouldFailBatch = $shouldFail;
    }
}
