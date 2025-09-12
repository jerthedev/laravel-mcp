<?php

namespace JTD\LaravelMCP\Tests\Performance;

use JTD\LaravelMCP\Support\MessageSerializer;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Performance Benchmark Tests
 *
 * EPIC: TESTING-QUALITY
 * SPEC: docs/Specs/12-TestingStrategy.md
 * SPRINT: Sprint 3
 * TICKET: TESTING-028 - Testing Strategy Quality Assurance
 *
 * Purpose: Validate package performance meets acceptable standards
 * Dependencies: PerformanceMonitor, MessageSerializer
 */
#[Group('performance')]
#[Group('benchmark')]
#[Group('ticket-028')]
class PerformanceBenchmarkTest extends TestCase
{
    private PerformanceMonitor $monitor;

    private MessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->monitor = new PerformanceMonitor(true, 'memory');
        $this->serializer = new MessageSerializer;
    }

    #[Test]
    public function message_serialization_performance(): void
    {
        $this->monitor->startTimer('serialization_benchmark');

        // Test serialization of various message sizes
        $messageSizes = [100, 1000, 10000, 50000];
        $results = [];

        foreach ($messageSizes as $size) {
            $data = [
                'jsonrpc' => '2.0',
                'method' => 'test',
                'params' => ['data' => str_repeat('x', $size)],
                'id' => uniqid(),
            ];

            $startTime = microtime(true);
            $json = $this->serializer->serialize($data);
            $endTime = microtime(true);

            $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $results[$size] = $duration;

            $this->monitor->histogram('serialization.duration', $duration, [
                'size' => $size,
                'message_type' => 'request',
            ]);

            // Performance assertions
            if ($size <= 1000) {
                $this->assertLessThan(1.0, $duration, 'Small message serialization should be < 1ms');
            } elseif ($size <= 10000) {
                $this->assertLessThan(5.0, $duration, 'Medium message serialization should be < 5ms');
            } else {
                $this->assertLessThan(20.0, $duration, 'Large message serialization should be < 20ms');
            }

            // Memory usage should be reasonable
            $memoryUsage = memory_get_usage(true);
            $this->assertLessThan(50 * 1024 * 1024, $memoryUsage, 'Memory usage should be < 50MB');
        }

        $totalTime = $this->monitor->stopTimer('serialization_benchmark');
        $this->assertLessThan(100, $totalTime, 'Total serialization benchmark should complete in < 100ms');
    }

    #[Test]
    public function batch_processing_performance(): void
    {
        $batchSizes = [10, 100, 1000];

        foreach ($batchSizes as $batchSize) {
            $messages = [];
            for ($i = 0; $i < $batchSize; $i++) {
                $messages[] = [
                    'jsonrpc' => '2.0',
                    'method' => 'batch.test',
                    'params' => ['index' => $i, 'data' => str_repeat('y', 100)],
                    'id' => $i,
                ];
            }

            $this->monitor->startTimer("batch_{$batchSize}");

            $startTime = microtime(true);
            $json = $this->serializer->serializeBatch($messages);
            $deserialized = $this->serializer->deserializeBatch($json);
            $endTime = microtime(true);

            $duration = ($endTime - $startTime) * 1000;
            $this->monitor->stopTimer("batch_{$batchSize}");

            $this->monitor->histogram('batch.processing.duration', $duration, [
                'batch_size' => $batchSize,
            ]);

            // Verify deserialized data matches
            $this->assertCount($batchSize, $deserialized);
            $this->assertEquals($messages[0]['method'], $deserialized[0]['method']);

            // Performance assertions based on batch size
            if ($batchSize <= 10) {
                $this->assertLessThan(2.0, $duration, 'Small batch processing should be < 2ms');
            } elseif ($batchSize <= 100) {
                $this->assertLessThan(10.0, $duration, 'Medium batch processing should be < 10ms');
            } else {
                $this->assertLessThan(50.0, $duration, 'Large batch processing should be < 50ms');
            }
        }
    }

    #[Test]
    public function performance_monitor_overhead(): void
    {
        // Test performance monitor overhead
        $iterations = 10000;

        // Measure without monitoring
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $dummy = $i * 2;
        }
        $baselineTime = (microtime(true) - $startTime) * 1000;

        // Measure with monitoring
        $monitor = new PerformanceMonitor(true);
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            $monitor->record('test.metric', $i, [], 'counter');
        }
        $monitoredTime = (microtime(true) - $startTime) * 1000;

        $overhead = $monitoredTime - $baselineTime;
        $overheadPerOperation = $overhead / $iterations;

        $this->monitor->gauge('monitor.overhead.total', $overhead);
        $this->monitor->gauge('monitor.overhead.per_operation', $overheadPerOperation);

        // Overhead should be minimal
        $this->assertLessThan(0.01, $overheadPerOperation, 'Monitor overhead should be < 0.01ms per operation');
        $this->assertLessThan(50, $overhead, 'Total monitoring overhead should be < 50ms for 10k operations');
    }

    #[Test]
    public function memory_usage_benchmark(): void
    {
        $this->monitor->recordMemory('test_start');
        $initialMemory = memory_get_usage(true);

        // Create large data structures
        $data = [];
        for ($i = 0; $i < 1000; $i++) {
            $data[] = [
                'id' => $i,
                'content' => str_repeat('content', 100),
                'metadata' => [
                    'timestamp' => microtime(true),
                    'size' => strlen(str_repeat('content', 100)),
                ],
            ];
        }

        $this->monitor->recordMemory('test_peak');
        $peakMemory = memory_get_usage(true);
        $memoryIncrease = $peakMemory - $initialMemory;

        // Serialize the data
        $serialized = $this->serializer->serialize([
            'jsonrpc' => '2.0',
            'result' => $data,
            'id' => 'memory-test',
        ]);

        $this->monitor->recordMemory('test_serialized');

        // Clean up
        unset($data, $serialized);
        gc_collect_cycles();

        $this->monitor->recordMemory('test_cleanup');
        $finalMemory = memory_get_usage(true);

        // Memory assertions
        $this->assertLessThan(20 * 1024 * 1024, $memoryIncrease, 'Memory increase should be < 20MB');
        $this->assertLessThan($peakMemory, $finalMemory + (5 * 1024 * 1024), 'Memory should be mostly freed after cleanup');
    }

    #[Test]
    public function concurrent_operation_simulation(): void
    {
        // Simulate concurrent-like operations
        $operations = ['serialize', 'deserialize', 'monitor', 'validate'];
        $iterationsPerOperation = 100;

        $results = [];

        foreach ($operations as $operation) {
            $this->monitor->startTimer($operation);

            $startTime = microtime(true);

            for ($i = 0; $i < $iterationsPerOperation; $i++) {
                switch ($operation) {
                    case 'serialize':
                        $data = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => $i];
                        $this->serializer->serialize($data);
                        break;
                    case 'deserialize':
                        $json = '{"jsonrpc":"2.0","method":"test","id":'.$i.'}';
                        $this->serializer->deserialize($json);
                        break;
                    case 'monitor':
                        $this->monitor->record('concurrent.test', $i, ['op' => $operation]);
                        break;
                    case 'validate':
                        $message = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => $i];
                        $this->serializer->validateMessage($message);
                        break;
                }
            }

            $duration = $this->monitor->stopTimer($operation);
            $results[$operation] = $duration;

            // Each operation type should complete reasonably quickly
            $this->assertLessThan(50, $duration, "{$operation} operations should complete in < 50ms");
        }

        // Total time for all operations should be reasonable
        $totalTime = array_sum($results);
        $this->assertLessThan(150, $totalTime, 'All concurrent operations should complete in < 150ms');
    }

    #[Test]
    public function compression_performance(): void
    {
        $testData = [
            'jsonrpc' => '2.0',
            'result' => [
                'large_text' => str_repeat('This is a test string that will compress well. ', 1000),
                'numbers' => range(1, 1000),
                'nested' => [
                    'deep' => [
                        'structure' => str_repeat('nested', 500),
                    ],
                ],
            ],
            'id' => 'compression-test',
        ];

        // Measure serialization
        $this->monitor->startTimer('compression.serialize');
        $json = $this->serializer->serialize($testData);
        $serializeTime = $this->monitor->stopTimer('compression.serialize');

        // Measure compression
        $this->monitor->startTimer('compression.compress');
        $compressed = $this->serializer->compress($json);
        $compressTime = $this->monitor->stopTimer('compression.compress');

        // Measure decompression
        $this->monitor->startTimer('compression.decompress');
        $decompressed = $this->serializer->decompress($compressed);
        $decompressTime = $this->monitor->stopTimer('compression.decompress');

        $originalSize = strlen($json);
        $compressedSize = strlen($compressed);
        $compressionRatio = $compressedSize / $originalSize;

        $this->monitor->gauge('compression.ratio', $compressionRatio);
        $this->monitor->gauge('compression.original_size', $originalSize);
        $this->monitor->gauge('compression.compressed_size', $compressedSize);

        // Performance assertions
        $this->assertLessThan(10, $serializeTime, 'Serialization should be < 10ms');
        $this->assertLessThan(20, $compressTime, 'Compression should be < 20ms');
        $this->assertLessThan(10, $decompressTime, 'Decompression should be < 10ms');

        // Compression should be effective
        $this->assertLessThan(0.5, $compressionRatio, 'Compression ratio should be < 50%');

        // Data integrity
        $this->assertEquals($json, $decompressed, 'Decompressed data should match original');
    }

    #[Test]
    public function performance_regression_detection(): void
    {
        // This test helps detect performance regressions
        $benchmarks = [
            'small_message_serialize' => 1.0,   // < 1ms
            'medium_message_serialize' => 5.0,  // < 5ms
            'large_message_serialize' => 20.0,  // < 20ms
            'batch_10_process' => 2.0,          // < 2ms
            'batch_100_process' => 10.0,        // < 10ms
            'validation_check' => 0.1,          // < 0.1ms
        ];

        $testData = [
            'small' => ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1],
            'medium' => ['jsonrpc' => '2.0', 'method' => 'test', 'params' => str_repeat('x', 5000), 'id' => 1],
            'large' => ['jsonrpc' => '2.0', 'method' => 'test', 'params' => str_repeat('x', 50000), 'id' => 1],
        ];

        // Test small message
        $startTime = microtime(true);
        $this->serializer->serialize($testData['small']);
        $smallTime = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['small_message_serialize'], $smallTime);

        // Test medium message
        $startTime = microtime(true);
        $this->serializer->serialize($testData['medium']);
        $mediumTime = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['medium_message_serialize'], $mediumTime);

        // Test large message
        $startTime = microtime(true);
        $this->serializer->serialize($testData['large']);
        $largeTime = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['large_message_serialize'], $largeTime);

        // Test batch processing
        $batch10 = array_fill(0, 10, $testData['small']);
        $startTime = microtime(true);
        $this->serializer->serializeBatch($batch10);
        $batch10Time = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['batch_10_process'], $batch10Time);

        $batch100 = array_fill(0, 100, $testData['small']);
        $startTime = microtime(true);
        $this->serializer->serializeBatch($batch100);
        $batch100Time = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['batch_100_process'], $batch100Time);

        // Test validation
        $startTime = microtime(true);
        $this->serializer->validateMessage($testData['small']);
        $validateTime = (microtime(true) - $startTime) * 1000;
        $this->assertLessThan($benchmarks['validation_check'], $validateTime);

        // Record all benchmarks for monitoring
        $this->monitor->histogram('regression.small_serialize', $smallTime);
        $this->monitor->histogram('regression.medium_serialize', $mediumTime);
        $this->monitor->histogram('regression.large_serialize', $largeTime);
        $this->monitor->histogram('regression.batch10_process', $batch10Time);
        $this->monitor->histogram('regression.batch100_process', $batch100Time);
        $this->monitor->histogram('regression.validate', $validateTime);
    }

    protected function tearDown(): void
    {
        // Output performance summary if test fails
        if ($this->hasFailed()) {
            $summary = $this->monitor->getSummary();
            $this->addWarning('Performance summary: '.json_encode($summary, JSON_PRETTY_PRINT));
        }

        parent::tearDown();
    }
}
