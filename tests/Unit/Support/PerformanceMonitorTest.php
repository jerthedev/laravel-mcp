<?php

namespace JTD\LaravelMCP\Tests\Unit\Support;

use Illuminate\Support\Facades\Cache;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * PerformanceMonitor Tests
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
class PerformanceMonitorTest extends TestCase
{
    protected PerformanceMonitor $monitor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new PerformanceMonitor(true, 'cache', 3600);
    }

    #[Test]
    public function it_can_be_enabled_and_disabled(): void
    {
        $monitor = new PerformanceMonitor(false);
        $this->assertFalse($monitor->isEnabled());

        $monitor->enable();
        $this->assertTrue($monitor->isEnabled());

        $monitor->disable();
        $this->assertFalse($monitor->isEnabled());
    }

    #[Test]
    public function it_records_metrics(): void
    {
        $this->monitor->record('test.metric', 42.5, ['tag' => 'value'], 'gauge');

        $metrics = $this->monitor->getMetrics('test.metric');

        $this->assertCount(1, $metrics);
        $this->assertEquals('test.metric', $metrics[0]['name']);
        $this->assertEquals(42.5, $metrics[0]['value']);
        $this->assertEquals(['tag' => 'value'], $metrics[0]['tags']);
        $this->assertEquals('gauge', $metrics[0]['type']);
    }

    #[Test]
    public function it_increments_counter_metrics(): void
    {
        $this->monitor->increment('test.counter', 1);
        $this->monitor->increment('test.counter', 2);

        $metrics = $this->monitor->getMetrics('test.counter');

        $this->assertCount(2, $metrics);
        $this->assertEquals(1, $metrics[0]['value']);
        $this->assertEquals(2, $metrics[1]['value']);
    }

    #[Test]
    public function it_decrements_counter_metrics(): void
    {
        $this->monitor->decrement('test.counter', 3);

        $metrics = $this->monitor->getMetrics('test.counter');

        $this->assertCount(1, $metrics);
        $this->assertEquals(-3, $metrics[0]['value']);
    }

    #[Test]
    public function it_sets_gauge_metrics(): void
    {
        $this->monitor->gauge('memory.usage', 1024.5, ['server' => 'web1']);

        $metrics = $this->monitor->getMetrics('memory.usage');

        $this->assertCount(1, $metrics);
        $this->assertEquals(1024.5, $metrics[0]['value']);
        $this->assertEquals('gauge', $metrics[0]['type']);
    }

    #[Test]
    public function it_records_histogram_values(): void
    {
        $this->monitor->histogram('response.time', 150.5);
        $this->monitor->histogram('response.time', 200.3);
        $this->monitor->histogram('response.time', 175.8);

        $metrics = $this->monitor->getMetrics('response.time');

        $this->assertCount(3, $metrics);
        $this->assertEquals('histogram', $metrics[0]['type']);
    }

    #[Test]
    public function it_measures_timer_duration(): void
    {
        $this->monitor->startTimer('test.timer');

        usleep(10000); // Sleep for 10ms

        $elapsed = $this->monitor->stopTimer('test.timer');

        $this->assertNotNull($elapsed);
        $this->assertGreaterThan(9, $elapsed);
        $this->assertLessThan(50, $elapsed);

        // Check that metric was recorded
        $metrics = $this->monitor->getMetrics('test.timer.duration');
        $this->assertCount(1, $metrics);
    }

    #[Test]
    public function it_measures_callback_execution(): void
    {
        $result = $this->monitor->measure(function () {
            usleep(5000); // Sleep for 5ms

            return 'test-result';
        }, 'callback.test');

        $this->assertEquals('test-result', $result);

        $metrics = $this->monitor->getMetrics('callback.test.duration');
        $this->assertCount(1, $metrics);
        $this->assertGreaterThan(4, $metrics[0]['value']);
    }

    #[Test]
    public function it_handles_callback_exceptions(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        try {
            $this->monitor->measure(function () {
                throw new \RuntimeException('Test exception');
            }, 'callback.error');
        } catch (\RuntimeException $e) {
            // Check that error metric was recorded
            $metrics = $this->monitor->getMetrics('callback.error.errors');
            $this->assertCount(1, $metrics);
            throw $e;
        }
    }

    #[Test]
    public function it_records_memory_usage(): void
    {
        $this->monitor->recordMemory('test');

        $usageMetrics = $this->monitor->getMetrics('test.usage');
        $peakMetrics = $this->monitor->getMetrics('test.peak');

        $this->assertCount(1, $usageMetrics);
        $this->assertCount(1, $peakMetrics);
        $this->assertGreaterThan(0, $usageMetrics[0]['value']);
        $this->assertGreaterThan(0, $peakMetrics[0]['value']);
    }

    #[Test]
    public function it_calculates_aggregates(): void
    {
        $this->monitor->record('test.aggregate', 10);
        $this->monitor->record('test.aggregate', 20);
        $this->monitor->record('test.aggregate', 30);
        $this->monitor->record('test.aggregate', 40);
        $this->monitor->record('test.aggregate', 50);

        $aggregate = $this->monitor->getAggregate('test.aggregate');

        $this->assertNotNull($aggregate);
        $this->assertEquals(5, $aggregate['count']);
        $this->assertEquals(150, $aggregate['sum']);
        $this->assertEquals(10, $aggregate['min']);
        $this->assertEquals(50, $aggregate['max']);
        $this->assertEquals(30, $aggregate['avg']);
    }

    #[Test]
    public function it_calculates_percentiles(): void
    {
        // Add enough values for percentile calculation
        for ($i = 1; $i <= 100; $i++) {
            $this->monitor->record('test.percentile', $i);
        }

        $p50 = $this->monitor->getPercentile('test.percentile', 50);
        $p95 = $this->monitor->getPercentile('test.percentile', 95);
        $p99 = $this->monitor->getPercentile('test.percentile', 99);

        $this->assertEquals(50, $p50);
        $this->assertEquals(95, $p95);
        $this->assertEquals(99, $p99);
    }

    #[Test]
    public function it_calculates_rate_of_change(): void
    {
        $now = microtime(true);

        // Simulate metrics over time
        for ($i = 0; $i < 10; $i++) {
            $this->monitor->record('test.rate', 1, [], 'counter');
        }

        $rate = $this->monitor->getRate('test.rate', 60);

        $this->assertNotNull($rate);
        $this->assertGreaterThan(0, $rate);
    }

    #[Test]
    public function it_limits_metrics_in_memory(): void
    {
        $monitor = new PerformanceMonitor(true, 'memory', 3600);

        // Record more metrics than the limit
        for ($i = 0; $i < 1100; $i++) {
            $monitor->record('test.overflow', $i);
        }

        $metrics = $monitor->getMetrics();
        $this->assertLessThanOrEqual(1000, count($metrics));
    }

    #[Test]
    public function it_exports_metrics_as_json(): void
    {
        $this->monitor->record('test.export', 42);

        $json = $this->monitor->export('json');
        $data = json_decode($json, true);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('aggregates', $data);
    }

    #[Test]
    public function it_exports_metrics_in_prometheus_format(): void
    {
        $this->monitor->record('test_metric', 42, ['label' => 'value'], 'gauge');

        $prometheus = $this->monitor->export('prometheus');

        $this->assertStringContainsString('# TYPE test_metric gauge', $prometheus);
        $this->assertStringContainsString('# HELP test_metric', $prometheus);
        $this->assertStringContainsString('test_metric{label="value"} 42', $prometheus);
    }

    #[Test]
    public function it_exports_metrics_in_graphite_format(): void
    {
        $this->monitor->record('test.metric', 42, ['env' => 'prod']);

        $graphite = $this->monitor->export('graphite');

        $this->assertStringContainsString('mcp.test_metric.env_prod 42', $graphite);
    }

    #[Test]
    public function it_persists_metrics_to_cache(): void
    {
        Cache::shouldReceive('get')
            ->with('mcp:performance:metrics', [])
            ->once()
            ->andReturn([]);

        Cache::shouldReceive('get')
            ->with('mcp:performance:aggregates', [])
            ->once()
            ->andReturn([]);

        Cache::shouldReceive('put')
            ->with('mcp:performance:metrics', \Mockery::type('array'), 3600)
            ->once();

        Cache::shouldReceive('put')
            ->with('mcp:performance:aggregates', \Mockery::type('array'), 3600)
            ->once();

        $monitor = new PerformanceMonitor(true, 'cache', 3600);
        $monitor->record('test.cache', 42);
    }

    #[Test]
    public function it_clears_all_metrics(): void
    {
        Cache::shouldReceive('get')->andReturn([]);
        Cache::shouldReceive('put')
            ->withAnyArgs()
            ->andReturn(true);
        Cache::shouldReceive('forget')
            ->with('mcp:performance:metrics')
            ->once();
        Cache::shouldReceive('forget')
            ->with('mcp:performance:aggregates')
            ->once();

        $this->monitor->record('test.clear', 42);
        $this->monitor->startTimer('test.timer');

        $this->monitor->clear();

        $this->assertEmpty($this->monitor->getMetrics());
        $this->assertEmpty($this->monitor->getAggregates());
        $this->assertNull($this->monitor->stopTimer('test.timer'));
    }

    #[Test]
    public function it_registers_export_handlers(): void
    {
        $handlerCalled = false;
        $capturedMetric = null;

        $this->monitor->registerExportHandler(function ($metric) use (&$handlerCalled, &$capturedMetric) {
            $handlerCalled = true;
            $capturedMetric = $metric;
        });

        $this->monitor->record('test.handler', 42, ['tag' => 'value']);

        $this->assertTrue($handlerCalled);
        $this->assertNotNull($capturedMetric);
        $this->assertEquals('test.handler', $capturedMetric['name']);
        $this->assertEquals(42, $capturedMetric['value']);
    }

    #[Test]
    public function it_gets_summary_report(): void
    {
        $this->monitor->record('test.summary1', 10);
        $this->monitor->record('test.summary2', 20);
        $this->monitor->startTimer('active.timer');

        $summary = $this->monitor->getSummary();

        $this->assertIsArray($summary);
        $this->assertTrue($summary['enabled']);
        $this->assertEquals(2, $summary['total_metrics']);
        $this->assertEquals(2, $summary['unique_metrics']);
        $this->assertEquals(1, $summary['active_timers']);
        $this->assertArrayHasKey('memory_usage', $summary);
        $this->assertArrayHasKey('peak_memory', $summary);
        $this->assertArrayHasKey('aggregates', $summary);
    }

    #[Test]
    public function it_does_not_record_when_disabled(): void
    {
        $monitor = new PerformanceMonitor(false);

        $monitor->record('test.disabled', 42);
        $monitor->startTimer('test.timer');

        $this->assertEmpty($monitor->getMetrics());
        $this->assertNull($monitor->stopTimer('test.timer'));
    }

    #[Test]
    public function it_handles_invalid_export_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported export format: invalid');

        $this->monitor->export('invalid');
    }
}
