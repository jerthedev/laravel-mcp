<?php

/**
 * StreamHandler Unit Tests
 *
 * @epic Transport Layer
 *
 * @ticket 011-TransportStdio
 *
 * @module Transport/Utilities
 *
 * @coverage src/Transport/StreamHandler.php
 *
 * @test-type Unit
 *
 * Test requirements:
 * - Stream creation and destruction
 * - Timeout behavior
 * - Buffer overflow handling
 * - Error recovery
 * - Non-blocking I/O
 * - Health checks
 */

namespace Tests\Unit\Transport;

use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\StreamHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamHandler::class)]
#[Group('transport')]
#[Group('stdio')]
#[Group('ticket-011')]
class StreamHandlerTest extends TestCase
{
    private string $tempFile;

    private ?StreamHandler $handler = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'stream_test_');
    }

    protected function tearDown(): void
    {
        if ($this->handler && $this->handler->isOpen()) {
            $this->handler->close();
        }

        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_opens_and_closes_streams(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');

        $this->assertFalse($this->handler->isOpen());

        $this->handler->open();
        $this->assertTrue($this->handler->isOpen());

        $this->handler->close();
        $this->assertFalse($this->handler->isOpen());
    }

    #[Test]
    public function it_throws_exception_for_invalid_stream_path(): void
    {
        $this->handler = new StreamHandler('/invalid/path/that/does/not/exist', 'r');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/Failed to open stream/');

        $this->handler->open();
    }

    #[Test]
    public function it_writes_data_to_stream(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $data = "Test data line\n";
        $written = $this->handler->write($data);

        $this->assertEquals(strlen($data), $written);

        $this->handler->close();

        $this->assertEquals($data, file_get_contents($this->tempFile));
    }

    #[Test]
    public function it_reads_data_from_stream(): void
    {
        $testData = "Line 1\nLine 2\nLine 3\n";
        file_put_contents($this->tempFile, $testData);

        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $read = $this->handler->read();
        $this->assertNotNull($read);
        $this->assertStringStartsWith('Line 1', $read);
    }

    #[Test]
    public function it_reads_complete_lines(): void
    {
        $testData = "Line 1\nLine 2\nLine 3";
        file_put_contents($this->tempFile, $testData);

        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $line = $this->handler->readLine();
        $this->assertEquals('Line 1', $line);

        $line = $this->handler->readLine();
        $this->assertEquals('Line 2', $line);
    }

    #[Test]
    public function it_writes_lines_with_delimiter(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $this->handler->writeLine('Line 1');
        $this->handler->writeLine('Line 2');

        $this->handler->close();

        $this->assertEquals("Line 1\nLine 2\n", file_get_contents($this->tempFile));
    }

    #[Test]
    public function it_detects_readable_and_writable_modes(): void
    {
        // Test write mode
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $this->assertFalse($this->handler->isReadable());
        $this->assertTrue($this->handler->isWritable());

        $this->handler->close();

        // Test read mode
        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $this->assertTrue($this->handler->isReadable());
        $this->assertFalse($this->handler->isWritable());
    }

    #[Test]
    public function it_detects_end_of_file(): void
    {
        file_put_contents($this->tempFile, 'test');

        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $this->assertFalse($this->handler->isEof());

        // Read all data
        while ($this->handler->read() !== null) {
            // Keep reading
        }

        $this->assertTrue($this->handler->isEof());
    }

    #[Test]
    public function it_sets_blocking_mode(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $this->assertTrue($this->handler->setBlocking(false));

        $meta = $this->handler->getMetadata();
        $this->assertFalse($meta['blocked']);

        $this->assertTrue($this->handler->setBlocking(true));

        $meta = $this->handler->getMetadata();
        $this->assertTrue($meta['blocked']);
    }

    #[Test]
    public function it_sets_stream_timeout(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $this->assertTrue($this->handler->setTimeout(5.5));

        $meta = $this->handler->getMetadata();
        $this->assertTrue($meta['timed_out'] === false);
    }

    #[Test]
    public function it_tracks_statistics(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $stats = $this->handler->getStats();
        $this->assertEquals(0, $stats['bytes_written']);
        $this->assertEquals(0, $stats['write_operations']);

        $this->handler->write('test data');

        $stats = $this->handler->getStats();
        $this->assertEquals(9, $stats['bytes_written']);
        $this->assertEquals(1, $stats['write_operations']);
    }

    #[Test]
    public function it_resets_statistics(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $this->handler->write('test');
        $stats = $this->handler->getStats();
        $this->assertGreaterThan(0, $stats['bytes_written']);

        $this->handler->resetStats();

        $stats = $this->handler->getStats();
        $this->assertEquals(0, $stats['bytes_written']);
        $this->assertEquals(0, $stats['write_operations']);
    }

    #[Test]
    public function it_performs_health_checks(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');

        // Check before opening
        $health = $this->handler->healthCheck();
        $this->assertFalse($health['healthy']);
        $this->assertFalse($health['open']);

        // Check after opening
        $this->handler->open();
        $health = $this->handler->healthCheck();
        $this->assertTrue($health['healthy']);
        $this->assertTrue($health['open']);
        $this->assertTrue($health['writable']);
        $this->assertFalse($health['readable']);
    }

    #[Test]
    public function it_throws_exception_when_reading_from_closed_stream(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'r');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Stream is not open for reading');

        $this->handler->read();
    }

    #[Test]
    public function it_throws_exception_when_writing_to_closed_stream(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Stream is not open for writing');

        $this->handler->write('test');
    }

    #[Test]
    public function it_handles_partial_writes_with_retry(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w', [
            'retry_attempts' => 3,
            'retry_delay' => 10,
        ]);
        $this->handler->open();

        $largeData = str_repeat('x', 1000);
        $written = $this->handler->write($largeData);

        $this->assertEquals(strlen($largeData), $written);
    }

    #[Test]
    public function it_waits_for_stream_to_become_readable(): void
    {
        file_put_contents($this->tempFile, 'test');

        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $readable = $this->handler->waitForReadable(0.1);
        $this->assertTrue($readable);
    }

    #[Test]
    public function it_waits_for_stream_to_become_writable(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');
        $this->handler->open();

        $writable = $this->handler->waitForWritable(0.1);
        $this->assertTrue($writable);
    }

    #[Test]
    public function it_returns_stream_resource(): void
    {
        $this->handler = new StreamHandler($this->tempFile, 'w');

        $this->assertNull($this->handler->getStream());

        $this->handler->open();

        $stream = $this->handler->getStream();
        $this->assertIsResource($stream);
    }

    #[Test]
    public function it_handles_buffer_overflow_for_line_reading(): void
    {
        // Create a line that exceeds max buffer size
        $longLine = str_repeat('x', 2000);
        file_put_contents($this->tempFile, $longLine);

        $this->handler = new StreamHandler($this->tempFile, 'r', [
            'max_buffer_size' => 1000,
        ]);
        $this->handler->open();

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/buffer overflow/i');

        $this->handler->readLine();
    }

    #[Test]
    public function it_handles_custom_line_delimiter(): void
    {
        file_put_contents($this->tempFile, 'Line1|Line2|Line3');

        $this->handler = new StreamHandler($this->tempFile, 'r');
        $this->handler->open();

        $line = $this->handler->readLine('|');
        $this->assertEquals('Line1', $line);

        $line = $this->handler->readLine('|');
        $this->assertEquals('Line2', $line);
    }
}
