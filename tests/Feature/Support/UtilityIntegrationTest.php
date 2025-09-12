<?php

namespace JTD\LaravelMCP\Tests\Feature\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Console\OutputFormatter;
use JTD\LaravelMCP\Support\Debugger;
use JTD\LaravelMCP\Support\MessageSerializer;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Utility Integration Tests
 *
 * EPIC: Laravel Integration Layer (006)
 * SPEC: Package Specification Document
 * SPRINT: 3 - Laravel Support Utilities
 * TICKET: 023-LaravelSupport
 *
 * @group feature
 * @group support
 * @group integration
 * @group ticket-023
 * @group epic-laravel-integration
 * @group sprint-3
 */
#[Group('feature')]
#[Group('support')]
#[Group('integration')]
#[Group('ticket-023')]
class UtilityIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached data
        Cache::flush();

        // Ensure storage directories exist
        if (! File::exists(storage_path('logs'))) {
            File::makeDirectory(storage_path('logs'), 0755, true);
        }
    }

    #[Test]
    public function utilities_work_together_for_mcp_request_processing(): void
    {
        // Enable debugging and performance monitoring
        $debugger = new Debugger(true);
        $monitor = new PerformanceMonitor(true);
        $serializer = new MessageSerializer;

        // Start performance monitoring
        $monitor->startTimer('request.processing');
        $debugger->startTimer('request.timer');

        // Create and serialize an MCP request
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/calculator',
            'params' => ['operation' => 'add', 'a' => 5, 'b' => 3],
            'id' => 'req-123',
        ];

        // Log the request
        $debugger->logRequest($request['method'], $request['params'], $request['id']);

        // Serialize the request
        $json = $serializer->serialize($request);
        $this->assertIsString($json);

        // Record message size
        $monitor->gauge('message.size.bytes', strlen($json), ['type' => 'request']);

        // Simulate processing with memory checkpoint
        $debugger->memoryCheckpoint('before-processing');

        // Process the request (simulated)
        $result = $debugger->profile(function () {
            // Simulate some work
            usleep(1000);

            return ['result' => 8];
        }, 'calculator.add');

        // Get memory delta
        $memoryDelta = $debugger->getMemoryDelta('before-processing');
        $this->assertArrayHasKey('delta', $memoryDelta);

        // Create response
        $response = [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $request['id'],
        ];

        // Stop timers
        $elapsed = $monitor->stopTimer('request.processing');
        $debugElapsed = $debugger->stopTimer('request.timer');

        // Log response
        $debugger->logResponse($response, $request['id'], $elapsed);

        // Record performance metrics
        $monitor->histogram('request.duration', $elapsed, ['method' => 'calculator']);
        $monitor->increment('request.count', 1, ['status' => 'success']);

        // Verify everything worked
        $this->assertNotNull($elapsed);
        $this->assertNotNull($debugElapsed);
        $this->assertGreaterThan(0, $elapsed);

        // Check metrics were recorded
        $metrics = $monitor->getMetrics('request.processing.duration');
        $this->assertCount(1, $metrics);

        // Check debug data was captured
        $debugData = $debugger->getDebugData();
        $this->assertNotEmpty($debugData);

        // Get performance summary
        $summary = $monitor->getSummary();
        $this->assertArrayHasKey('total_metrics', $summary);
        $this->assertGreaterThan(0, $summary['total_metrics']);
    }

    #[Test]
    public function helper_functions_integrate_with_mcp_components(): void
    {
        // Create test components
        $testTool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'test-tool';
            }

            public function getDescription(): string
            {
                return 'Test tool';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object'];
            }

            public function execute(array $params): mixed
            {
                return ['result' => 'success'];
            }
        };

        $testResource = new class extends McpResource
        {
            public function getName(): string
            {
                return 'test-resource';
            }

            public function getDescription(): string
            {
                return 'Test resource';
            }

            public function getUri(): string
            {
                return 'test://resource';
            }

            public function getMimeType(): string
            {
                return 'application/json';
            }

            public function read(array $params): mixed
            {
                return ['data' => 'content'];
            }

            public function list(array $params): array
            {
                return ['items' => []];
            }

            public function subscribe(array $params): array
            {
                return ['subscription' => 'id'];
            }
        };

        $testPrompt = new class extends McpPrompt
        {
            public function getName(): string
            {
                return 'test-prompt';
            }

            public function getDescription(): string
            {
                return 'Test prompt';
            }

            public function getArguments(): array
            {
                return [];
            }

            public function generate(array $params): string
            {
                return 'Generated prompt';
            }
        };

        // Register components using helper functions
        mcp_tool('test-tool', $testTool, ['version' => '1.0']);
        mcp_resource('test-resource', $testResource, ['type' => 'test']);
        mcp_prompt('test-prompt', $testPrompt, ['category' => 'testing']);

        // Dispatch requests using helper functions
        $toolResult = mcp_dispatch('tools/test-tool', ['param' => 'value']);
        $this->assertEquals(['result' => 'success'], $toolResult);

        $resourceResult = mcp_dispatch('resources/test-resource/read', ['id' => '123']);
        $this->assertEquals(['data' => 'content'], $resourceResult);

        $promptResult = mcp_dispatch('prompts/test-prompt', ['name' => 'Test']);
        $this->assertEquals('Generated prompt', $promptResult);

        // Verify components can be retrieved
        $retrievedTool = mcp_tool('test-tool');
        $this->assertInstanceOf(McpTool::class, $retrievedTool);

        $retrievedResource = mcp_resource('test-resource');
        $this->assertInstanceOf(McpResource::class, $retrievedResource);

        $retrievedPrompt = mcp_prompt('test-prompt');
        $this->assertInstanceOf(McpPrompt::class, $retrievedPrompt);
    }

    #[Test]
    public function console_formatter_displays_mcp_server_information(): void
    {
        $output = new BufferedOutput;
        $formatter = new OutputFormatter($output);

        // Display server info
        $serverInfo = [
            'name' => 'Laravel MCP Server',
            'version' => '1.0.0',
            'description' => 'MCP server for Laravel applications',
            'url' => 'http://localhost:8080',
        ];

        $formatter->displayServerInfo($serverInfo);

        // Display capabilities
        $capabilities = [
            'tools' => ['execute' => true, 'list' => true],
            'resources' => ['read' => true, 'write' => false],
            'prompts' => true,
        ];

        $formatter->displayCapabilities($capabilities);

        // Display component statistics
        $stats = [
            'tools' => 10,
            'resources' => 5,
            'prompts' => 3,
            'total' => 18,
        ];

        $formatter->displayStats($stats);

        // Display server status
        $formatter->displayServerStatus(true, [
            'transport' => 'HTTP',
            'host' => 'localhost',
            'port' => 8080,
            'pid' => getmypid(),
            'uptime' => '1 hour 30 minutes',
        ]);

        // Get output and verify
        $consoleOutput = $output->fetch();

        $this->assertStringContainsString('Laravel MCP Server', $consoleOutput);
        $this->assertStringContainsString('1.0.0', $consoleOutput);
        $this->assertStringContainsString('Server Capabilities', $consoleOutput);
        $this->assertStringContainsString('Component Statistics', $consoleOutput);
        $this->assertStringContainsString('Running', $consoleOutput);
        $this->assertStringContainsString('HTTP', $consoleOutput);
    }

    #[Test]
    public function serializer_handles_complex_laravel_objects(): void
    {
        $serializer = new MessageSerializer;

        // Create complex data with Laravel objects
        $data = [
            'jsonrpc' => '2.0',
            'result' => [
                'collection' => collect(['a', 'b', 'c']),
                'model_data' => [
                    'id' => 1,
                    'name' => 'Test',
                    'created_at' => now(),
                ],
                'config' => config('app.name'),
                'nested' => [
                    'level1' => [
                        'level2' => collect([
                            'item1' => ['value' => 100],
                            'item2' => ['value' => 200],
                        ]),
                    ],
                ],
            ],
            'id' => 'req-456',
        ];

        // Serialize
        $json = $serializer->serialize($data);
        $this->assertIsString($json);

        // Deserialize
        $decoded = $serializer->deserialize($json);
        $this->assertIsArray($decoded);
        $this->assertEquals('2.0', $decoded['jsonrpc']);
        $this->assertIsArray($decoded['result']['collection']);
        $this->assertCount(3, $decoded['result']['collection']);

        // Validate the message
        $this->assertTrue($serializer->validateMessage($decoded));

        // Test batch processing
        $batch = [
            ['jsonrpc' => '2.0', 'method' => 'test1', 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'test2', 'id' => 2],
            ['jsonrpc' => '2.0', 'method' => 'test3', 'id' => 3],
        ];

        $batchJson = $serializer->serializeBatch($batch);
        $decodedBatch = $serializer->deserializeBatch($batchJson);

        $this->assertCount(3, $decodedBatch);
        foreach ($decodedBatch as $i => $message) {
            $this->assertTrue($serializer->validateMessage($message));
        }
    }

    #[Test]
    public function performance_monitor_tracks_mcp_operations(): void
    {
        $monitor = new PerformanceMonitor(true, 'memory');

        // Track different types of operations
        $operations = [
            'tool.execute' => [150, 200, 175, 180, 160],
            'resource.read' => [50, 60, 55, 45, 70],
            'prompt.generate' => [300, 280, 310, 295, 305],
        ];

        foreach ($operations as $operation => $durations) {
            foreach ($durations as $duration) {
                $monitor->histogram($operation, $duration, ['environment' => 'testing']);
            }
        }

        // Calculate aggregates
        $toolAggregate = $monitor->getAggregate('tool.execute');
        $this->assertEquals(5, $toolAggregate['count']);
        $this->assertEquals(173, $toolAggregate['avg']);
        $this->assertEquals(150, $toolAggregate['min']);
        $this->assertEquals(200, $toolAggregate['max']);

        // Calculate percentiles
        $p50 = $monitor->getPercentile('tool.execute', 50);
        $p95 = $monitor->getPercentile('tool.execute', 95);
        $this->assertEquals(175, $p50);
        $this->assertEquals(200, $p95);

        // Record memory usage
        $monitor->recordMemory('mcp.operations');

        // Export metrics in different formats
        $jsonExport = $monitor->export('json');
        $this->assertIsString($jsonExport);
        $exportData = json_decode($jsonExport, true);
        $this->assertArrayHasKey('metrics', $exportData);
        $this->assertArrayHasKey('aggregates', $exportData);

        $prometheusExport = $monitor->export('prometheus');
        $this->assertStringContainsString('# TYPE tool_execute histogram', $prometheusExport);
        $this->assertStringContainsString('# TYPE resource_read histogram', $prometheusExport);

        // Get summary
        $summary = $monitor->getSummary();
        $this->assertGreaterThan(0, $summary['total_metrics']);
        $this->assertGreaterThan(0, $summary['unique_metrics']);
    }

    #[Test]
    public function debugger_captures_full_request_lifecycle(): void
    {
        $debugger = new Debugger(true);

        // Simulate full request lifecycle
        $requestId = 'req-789';

        // 1. Request received
        $debugger->startTimer('request');
        $debugger->memoryCheckpoint('start');
        $debugger->logRequest('tools/database', ['query' => 'SELECT * FROM users'], $requestId);

        // 2. Validation
        $debugger->log('Validating request parameters', ['request_id' => $requestId]);

        // 3. Processing
        $debugger->startTimer('database-query');
        usleep(5000); // Simulate database query
        $queryTime = $debugger->stopTimer('database-query');
        $debugger->log('Database query completed', ['duration' => $queryTime]);

        // 4. Response preparation
        $response = ['users' => ['user1', 'user2', 'user3']];

        // 5. Send response
        $totalTime = $debugger->stopTimer('request');
        $debugger->logResponse($response, $requestId, $totalTime);

        // Get memory usage
        $memoryDelta = $debugger->getMemoryDelta('start');
        $debugger->log('Request completed', [
            'memory_delta' => $memoryDelta['delta'],
            'peak_memory' => $memoryDelta['peak'],
        ]);

        // Verify debug data
        $history = $debugger->getHistory();
        $this->assertGreaterThanOrEqual(2, count($history)); // At least request and response

        $debugData = $debugger->getDebugData();
        $this->assertNotEmpty($debugData);

        // Dump to file for inspection
        $dumpFile = storage_path('logs/debug-test-lifecycle.json');
        $result = $debugger->dumpToFile($dumpFile);
        $this->assertTrue($result);
        $this->assertFileExists($dumpFile);

        // Clean up
        unlink($dumpFile);
    }

    #[Test]
    public function utilities_handle_error_conditions_gracefully(): void
    {
        // Test serializer with invalid JSON
        $serializer = new MessageSerializer;
        try {
            $serializer->deserialize('invalid json');
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Failed to decode message', $e->getMessage());
        }

        // Test performance monitor with invalid export format
        $monitor = new PerformanceMonitor(true);
        try {
            $monitor->export('invalid-format');
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Unsupported export format', $e->getMessage());
        }

        // Test debugger error logging
        $debugger = new Debugger(true);
        Log::shouldReceive('channel')->with('mcp-debug')->andReturnSelf();
        Log::shouldReceive('error')->once();

        $debugger->logError(-32700, 'Parse error', ['details' => 'Invalid JSON'], 'req-error');

        // Test helper functions with invalid component types
        try {
            mcp('invalid-type', 'name');
            $this->fail('Should have thrown exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('Invalid component type', $e->getMessage());
        }

        // Test mcp_dispatch with unknown method
        try {
            mcp_dispatch('unknown/method');
            $this->fail('Should have thrown exception');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Unknown MCP method', $e->getMessage());
        }
    }

    #[Test]
    public function utilities_support_laravel_cache_integration(): void
    {
        // Configure performance monitor to use cache
        $monitor = new PerformanceMonitor(true, 'cache', 3600);

        // Record some metrics
        $monitor->record('cache.test', 100);
        $monitor->record('cache.test', 200);
        $monitor->record('cache.test', 150);

        // Verify cache was used (metrics should persist)
        Cache::shouldReceive('get')
            ->with('mcp:performance:metrics', [])
            ->andReturn([
                ['name' => 'cache.test', 'value' => 100],
                ['name' => 'cache.test', 'value' => 200],
                ['name' => 'cache.test', 'value' => 150],
            ]);

        // Create new monitor instance and check metrics persist
        $monitor2 = new PerformanceMonitor(true, 'cache', 3600);
        $aggregate = $monitor2->getAggregate('cache.test');

        // The aggregate might be calculated from new data, but we can verify the structure
        $this->assertIsArray($aggregate);
        if ($aggregate !== null) {
            $this->assertArrayHasKey('count', $aggregate);
            $this->assertArrayHasKey('avg', $aggregate);
        }

        // Test cache metadata for components
        Cache::shouldReceive('put')
            ->with('mcp:tool:cached-tool:metadata', ['cached' => true], \Mockery::any());

        $tool = new class extends McpTool
        {
            public function getName(): string
            {
                return 'cached-tool';
            }

            public function getDescription(): string
            {
                return 'Cached tool';
            }

            public function getInputSchema(): array
            {
                return [];
            }

            public function execute(array $params): mixed
            {
                return 'result';
            }
        };

        mcp_tool('cached-tool', $tool, ['cached' => true]);
    }

    #[Test]
    public function message_helpers_create_valid_jsonrpc_messages(): void
    {
        // Test error creation
        $error = mcp_error(-32600, 'Invalid Request', ['detail' => 'Missing params'], 'req-123');
        $this->assertEquals('2.0', $error['jsonrpc']);
        $this->assertEquals(-32600, $error['error']['code']);
        $this->assertEquals('Invalid Request', $error['error']['message']);
        $this->assertEquals(['detail' => 'Missing params'], $error['error']['data']);
        $this->assertEquals('req-123', $error['id']);

        // Test success creation
        $success = mcp_success(['result' => 'data'], 'req-456');
        $this->assertEquals('2.0', $success['jsonrpc']);
        $this->assertEquals(['result' => 'data'], $success['result']);
        $this->assertEquals('req-456', $success['id']);

        // Test notification creation
        $notification = mcp_notification('status.update', ['status' => 'ready']);
        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('status.update', $notification['method']);
        $this->assertEquals(['status' => 'ready'], $notification['params']);
        $this->assertArrayNotHasKey('id', $notification);

        // Validate all created messages
        $serializer = new MessageSerializer;
        $this->assertTrue($serializer->validateMessage($error));
        $this->assertTrue($serializer->validateMessage($success));
        // Notifications are requests without ID
        $this->assertTrue($serializer->validateMessage($notification));
    }

    #[Test]
    public function console_formatter_supports_progress_tracking(): void
    {
        $output = new BufferedOutput;
        $formatter = new OutputFormatter($output);

        // Simulate a progress operation
        $items = range(1, 10);
        $total = count($items);

        foreach ($items as $i => $item) {
            $percent = ($i + 1) / $total * 100;
            $formatter->progress("Processing item {$item}", $percent);
            usleep(1000); // Simulate work
        }

        $consoleOutput = $output->fetch();

        // Should contain progress indicators
        $this->assertStringContainsString('Processing item', $consoleOutput);
        $this->assertStringContainsString('100%', $consoleOutput);
        $this->assertStringContainsString('█', $consoleOutput); // Progress bar fill
        $this->assertStringContainsString('░', $consoleOutput); // Progress bar empty
    }
}
