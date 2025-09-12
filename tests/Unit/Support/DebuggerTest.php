<?php

namespace JTD\LaravelMCP\Tests\Unit\Support;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Support\Debugger;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Debugger Tests
 *
 * @group unit
 * @group support
 * @group ticket-023
 * @group epic-laravel-integration
 * @group sprint-3
 */
#[Group('unit')]
#[Group('support')]
#[Group('ticket-023')]
class DebuggerTest extends TestCase
{
    protected Debugger $debugger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debugger = new Debugger(true);
    }

    #[Test]
    public function it_can_be_enabled_and_disabled(): void
    {
        $debugger = new Debugger(false);
        $this->assertFalse($debugger->isEnabled());

        $debugger->enable();
        $this->assertTrue($debugger->isEnabled());

        $debugger->disable();
        $this->assertFalse($debugger->isEnabled());
    }

    #[Test]
    public function it_logs_debug_messages_when_enabled(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->with('Test message', \Mockery::type('array'));

        $this->debugger->log('Test message', ['context' => 'test']);
    }

    #[Test]
    public function it_does_not_log_when_disabled(): void
    {
        $debugger = new Debugger(false);

        Log::shouldReceive('channel')->never();
        Log::shouldReceive('debug')->never();

        $debugger->log('Test message', ['context' => 'test']);
    }

    #[Test]
    public function it_logs_mcp_requests(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'MCP Request: test.method' &&
                       isset($context['type']) &&
                       $context['type'] === 'request';
            });

        $this->debugger->logRequest('test.method', ['param' => 'value'], 'req-123');
    }

    #[Test]
    public function it_logs_mcp_responses(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'MCP Response' &&
                       isset($context['type']) &&
                       $context['type'] === 'response';
            });

        $this->debugger->logResponse(['result' => 'success'], 'req-123', 150.5);
    }

    #[Test]
    public function it_logs_mcp_errors(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'MCP Error: Test error' &&
                       isset($context['type']) &&
                       $context['type'] === 'error' &&
                       $context['code'] === -32600;
            });

        $this->debugger->logError(-32600, 'Test error', null, 'req-123');
    }

    #[Test]
    public function it_manages_performance_timers(): void
    {
        $this->debugger->startTimer('test-timer');

        usleep(10000); // Sleep for 10ms

        $elapsed = $this->debugger->stopTimer('test-timer');

        $this->assertNotNull($elapsed);
        $this->assertGreaterThan(9, $elapsed); // Should be at least 9ms
        $this->assertLessThan(50, $elapsed); // But not more than 50ms
    }

    #[Test]
    public function it_gets_elapsed_time_for_running_timer(): void
    {
        $this->debugger->startTimer('test-timer');

        usleep(10000); // Sleep for 10ms

        $elapsed = $this->debugger->getElapsedTime('test-timer');

        $this->assertNotNull($elapsed);
        $this->assertGreaterThan(9, $elapsed);

        // Timer should still be running
        $finalElapsed = $this->debugger->stopTimer('test-timer');
        $this->assertGreaterThanOrEqual($elapsed, $finalElapsed);
    }

    #[Test]
    public function it_creates_memory_checkpoints(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return $message === 'Memory checkpoint: test-checkpoint' &&
                       isset($context['usage']) &&
                       isset($context['peak']);
            });

        $this->debugger->memoryCheckpoint('test-checkpoint');
    }

    #[Test]
    public function it_calculates_memory_delta(): void
    {
        $this->debugger->memoryCheckpoint('test-checkpoint');

        // Allocate some memory
        $data = str_repeat('x', 10000);

        $delta = $this->debugger->getMemoryDelta('test-checkpoint');

        $this->assertIsArray($delta);
        $this->assertArrayHasKey('delta', $delta);
        $this->assertArrayHasKey('current', $delta);
        $this->assertArrayHasKey('peak', $delta);
        $this->assertArrayHasKey('checkpoint', $delta);
        $this->assertArrayHasKey('elapsed_time', $delta);
    }

    #[Test]
    public function it_profiles_callback_execution(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->times(2)
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->twice();

        $result = $this->debugger->profile(function () {
            return 'test-result';
        }, 'test-profile');

        $this->assertEquals('test-result', $result);
    }

    #[Test]
    public function it_profiles_callback_with_exception(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->times(2)
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->twice();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $this->debugger->profile(function () {
            throw new \RuntimeException('Test exception');
        }, 'test-profile');
    }

    #[Test]
    public function it_stores_and_retrieves_debug_data(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->times(3)
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->times(3);

        $this->debugger->log('Message 1');
        $this->debugger->log('Message 2');
        $this->debugger->log('Message 3');

        $data = $this->debugger->getDebugData();
        $this->assertCount(3, $data);

        $limitedData = $this->debugger->getDebugData(2);
        $this->assertCount(2, $limitedData);
    }

    #[Test]
    public function it_maintains_request_history(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->times(2)
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->twice();

        $this->debugger->logRequest('method1', [], 'req-1');
        $this->debugger->logResponse('result1', 'req-1');

        $history = $this->debugger->getHistory();
        $this->assertCount(2, $history);
        $this->assertEquals('request', $history[0]['type']);
        $this->assertEquals('response', $history[1]['type']);
    }

    #[Test]
    public function it_clears_debug_data(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->atLeast()->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->atLeast()->once();

        $this->debugger->log('Test message');
        $this->debugger->startTimer('test-timer');
        $this->debugger->memoryCheckpoint('test-checkpoint');

        $this->debugger->clear();

        $this->assertEmpty($this->debugger->getDebugData());
        $this->assertEmpty($this->debugger->getHistory());
        $this->assertNull($this->debugger->stopTimer('test-timer'));
        $this->assertNull($this->debugger->getMemoryDelta('test-checkpoint'));
    }

    #[Test]
    public function it_gets_memory_usage_info(): void
    {
        $memoryUsage = $this->debugger->getMemoryUsage();

        $this->assertIsArray($memoryUsage);
        $this->assertArrayHasKey('current', $memoryUsage);
        $this->assertArrayHasKey('peak', $memoryUsage);
        $this->assertArrayHasKey('limit', $memoryUsage);
        $this->assertArrayHasKey('percentage', $memoryUsage);
    }

    #[Test]
    public function it_gets_system_info(): void
    {
        $systemInfo = $this->debugger->getSystemInfo();

        $this->assertIsArray($systemInfo);
        $this->assertArrayHasKey('php_version', $systemInfo);
        $this->assertArrayHasKey('laravel_version', $systemInfo);
        $this->assertArrayHasKey('environment', $systemInfo);
        $this->assertArrayHasKey('debug_mode', $systemInfo);
        $this->assertArrayHasKey('cache_driver', $systemInfo);
        $this->assertArrayHasKey('queue_driver', $systemInfo);
        $this->assertArrayHasKey('loaded_extensions', $systemInfo);
    }

    #[Test]
    public function it_dumps_debug_info_to_file(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once();

        $this->debugger->log('Test message');

        $filename = storage_path('logs/debug-test.json');
        $result = $this->debugger->dumpToFile($filename);

        $this->assertTrue($result);
        $this->assertFileExists($filename);

        $content = json_decode(file_get_contents($filename), true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('timestamp', $content);
        $this->assertArrayHasKey('system', $content);
        $this->assertArrayHasKey('memory', $content);
        $this->assertArrayHasKey('debug_data', $content);

        // Clean up
        unlink($filename);
    }

    #[Test]
    public function it_truncates_large_data(): void
    {
        Log::shouldReceive('channel')
            ->with('mcp-debug')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                // Check that large string was truncated
                return isset($context['large_string']) &&
                       strpos($context['large_string'], '[truncated]') !== false;
            });

        $largeString = str_repeat('x', 20000);
        $this->debugger->log('Test', ['large_string' => $largeString]);
    }
}
