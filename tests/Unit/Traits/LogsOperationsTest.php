<?php

namespace JTD\LaravelMCP\Tests\Unit\Traits;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Traits\LogsOperations;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LogLevel;

/**
 * Test file header as per standards.
 *
 * Epic: Base Classes
 * Sprint: Sprint 2
 * Ticket: BASECLASSES-015
 * URL: docs/Tickets/015-BaseClassesTraits.md
 * Dependencies: 014-BASECLASSESCORE
 *
 * @covers \JTD\LaravelMCP\Traits\LogsOperations
 */
#[Group('unit')]
#[Group('traits')]
#[Group('ticket-015')]
#[Group('epic-baseclasses')]
class LogsOperationsTest extends TestCase
{
    protected $logger;

    protected $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock class that uses the trait
        $this->logger = new class
        {
            use LogsOperations, ManagesCapabilities;

            public function getName(): string
            {
                return 'test_component';
            }

            public function getComponentType(): string
            {
                return 'test';
            }

            public function getCapabilities(): array
            {
                return ['execute'];
            }
        };

        $this->reflection = new \ReflectionClass($this->logger);
    }

    /**
     * Call a protected method on the logger.
     */
    protected function callMethod(string $methodName, array $arguments = [])
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->logger, $arguments);
    }

    #[Test]
    public function it_logs_operation_when_enabled(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.level', LogLevel::INFO);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'MCP Operation: test.operation', \Mockery::type('array'));

        $this->callMethod('logOperation', ['test.operation', ['data' => 'test']]);
    }

    #[Test]
    public function it_does_not_log_when_disabled(): void
    {
        Config::set('laravel-mcp.logging.enabled', false);

        Log::shouldReceive('channel')->never();

        $this->callMethod('logOperation', ['test.operation', ['data' => 'test']]);
    }

    #[Test]
    public function it_logs_operation_start(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.level', LogLevel::DEBUG);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'MCP Operation: test.start', \Mockery::type('array'));

        $this->callMethod('logOperationStart', ['test', ['param' => 'value']]);

        // Check that start time was set
        $property = $this->reflection->getProperty('operationStartTime');
        $property->setAccessible(true);
        $this->assertNotNull($property->getValue($this->logger));
    }

    #[Test]
    public function it_logs_operation_complete_with_duration(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.level', LogLevel::DEBUG);
        Config::set('laravel-mcp.logging.track_performance', true);

        // Set start time
        $property = $this->reflection->getProperty('operationStartTime');
        $property->setAccessible(true);
        $property->setValue($this->logger, microtime(true));

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::DEBUG, 'MCP Operation: test.complete', \Mockery::on(function ($context) {
                return isset($context['duration_ms']) && $context['duration_ms'] >= 0;
            }));

        $this->callMethod('logOperationComplete', ['test', ['result' => 'success']]);
    }

    #[Test]
    public function it_logs_operation_error(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.log_stack_trace', true);

        $exception = new \RuntimeException('Test error', 500);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::ERROR, 'MCP Operation: test.error', \Mockery::on(function ($context) {
                return $context['error_message'] === 'Test error' &&
                       $context['error_code'] === 500 &&
                       $context['error_type'] === 'RuntimeException' &&
                       isset($context['trace']);
            }));

        $this->callMethod('logOperationError', ['test', $exception]);
    }

    #[Test]
    public function it_logs_request_when_enabled(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.log_requests', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'MCP Operation: request', \Mockery::type('array'));

        $this->callMethod('logRequest', ['execute', ['param' => 'value']]);
    }

    #[Test]
    public function it_does_not_log_request_when_disabled(): void
    {
        Config::set('laravel-mcp.logging.log_requests', false);

        Log::shouldReceive('channel')->never();

        $this->callMethod('logRequest', ['execute', ['param' => 'value']]);
    }

    #[Test]
    public function it_logs_response_when_enabled(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.log_responses', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'MCP Operation: response', \Mockery::type('array'));

        $this->callMethod('logResponse', ['execute', ['result' => 'success']]);
    }

    #[Test]
    public function it_logs_validation_failure(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);

        $errors = ['field' => ['Field is required']];

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::WARNING, 'MCP Operation: validation.failed', \Mockery::on(function ($context) use ($errors) {
                return $context['errors'] === $errors;
            }));

        $this->callMethod('logValidationFailure', [$errors, ['field' => null]]);
    }

    #[Test]
    public function it_logs_authorization_failure(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::WARNING, 'MCP Operation: authorization.failed', \Mockery::on(function ($context) {
                return $context['action'] === 'execute';
            }));

        $this->callMethod('logAuthorizationFailure', ['execute', ['reason' => 'Insufficient permissions']]);
    }

    #[Test]
    public function it_tracks_performance_when_enabled(): void
    {
        Config::set('laravel-mcp.logging.track_performance', true);

        $this->callMethod('trackPerformance', ['test_operation', 100.5]);
        $this->callMethod('trackPerformance', ['test_operation', 150.3]);
        $this->callMethod('trackPerformance', ['test_operation', 125.7]);

        $property = $this->reflection->getProperty('performanceData');
        $property->setAccessible(true);
        $data = $property->getValue($this->logger);

        $this->assertArrayHasKey('test_operation', $data);
        $this->assertCount(3, $data['test_operation']);
    }

    #[Test]
    public function it_calculates_performance_metrics(): void
    {
        // Set performance data
        $property = $this->reflection->getProperty('performanceData');
        $property->setAccessible(true);
        $property->setValue($this->logger, [
            'operation1' => [100, 200, 150, 180, 120],
        ]);

        $method = $this->reflection->getMethod('calculatePerformanceMetrics');
        $method->setAccessible(true);
        $metrics = $method->invoke($this->logger);

        $this->assertArrayHasKey('operation1', $metrics);
        $this->assertEquals(5, $metrics['operation1']['count']);
        $this->assertEquals(100, $metrics['operation1']['min_ms']);
        $this->assertEquals(200, $metrics['operation1']['max_ms']);
        $this->assertEquals(150, $metrics['operation1']['avg_ms']);
    }

    #[Test]
    public function it_sanitizes_sensitive_data_for_logging(): void
    {
        $data = [
            'username' => 'john',
            'password' => 'secret123',
            'api_key' => 'key123',
            'credit_card' => '4111111111111111',
            'normal_field' => 'public',
        ];

        $method = $this->reflection->getMethod('sanitizeForLogging');
        $method->setAccessible(true);
        $sanitized = $method->invoke($this->logger, $data);

        $this->assertEquals('john', $sanitized['username']);
        $this->assertEquals('[REDACTED]', $sanitized['password']);
        $this->assertEquals('[REDACTED]', $sanitized['api_key']);
        $this->assertEquals('[REDACTED]', $sanitized['credit_card']);
        $this->assertEquals('public', $sanitized['normal_field']);
    }

    #[Test]
    public function it_logs_debug_when_enabled(): void
    {
        Config::set('laravel-mcp.debug', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('debug')
            ->once()
            ->with('Debug message', \Mockery::on(function ($context) {
                return isset($context['memory_usage']) && isset($context['peak_memory']);
            }));

        $this->callMethod('logDebug', ['Debug message', ['extra' => 'data']]);
    }

    #[Test]
    public function it_logs_registration(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);

        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once()
            ->with(LogLevel::INFO, 'MCP Operation: registration', \Mockery::on(function ($context) {
                return $context['component'] === 'test:test_component' &&
                       $context['type'] === 'test' &&
                       $context['capabilities'] === ['execute'];
            }));

        $this->callMethod('logRegistration', []);
    }

    #[Test]
    public function it_generates_unique_request_id(): void
    {
        $method = $this->reflection->getMethod('generateRequestId');
        $method->setAccessible(true);

        $id1 = $method->invoke($this->logger);
        $id2 = $method->invoke($this->logger);

        $this->assertStringStartsWith('mcp_', $id1);
        $this->assertStringStartsWith('mcp_', $id2);
        $this->assertNotEquals($id1, $id2);
    }

    #[Test]
    public function it_calculates_data_size(): void
    {
        $method = $this->reflection->getMethod('getDataSize');
        $method->setAccessible(true);

        $stringSize = $method->invoke($this->logger, 'test string');
        $this->assertEquals(11, $stringSize);

        $arraySize = $method->invoke($this->logger, ['key' => 'value']);
        $this->assertGreaterThan(0, $arraySize);

        $objectSize = $method->invoke($this->logger, (object) ['key' => 'value']);
        $this->assertGreaterThan(0, $objectSize);
    }

    #[Test]
    public function it_calculates_median_correctly(): void
    {
        $method = $this->reflection->getMethod('calculateMedian');
        $method->setAccessible(true);

        // Odd number of values
        $median1 = $method->invoke($this->logger, [1, 3, 5, 7, 9]);
        $this->assertEquals(5, $median1);

        // Even number of values
        $median2 = $method->invoke($this->logger, [1, 2, 3, 4]);
        $this->assertEquals(2.5, $median2);

        // Empty array
        $median3 = $method->invoke($this->logger, []);
        $this->assertEquals(0, $median3);
    }

    #[Test]
    public function it_calculates_percentile_correctly(): void
    {
        $method = $this->reflection->getMethod('calculatePercentile');
        $method->setAccessible(true);

        $values = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $p50 = $method->invoke($this->logger, $values, 50);
        $this->assertEquals(5.5, $p50);

        $p95 = $method->invoke($this->logger, $values, 95);
        $this->assertEqualsWithDelta(9.55, $p95, 0.01);

        $p99 = $method->invoke($this->logger, $values, 99);
        $this->assertEquals(9.91, $p99);
    }

    #[Test]
    public function it_sets_custom_log_channel(): void
    {
        $this->logger->setLogChannel('custom');

        Log::shouldReceive('channel')
            ->once()
            ->with('custom')
            ->andReturnSelf();

        Log::shouldReceive('log')
            ->once();

        Config::set('laravel-mcp.logging.enabled', true);
        $this->callMethod('logOperation', ['test', []]);
    }

    #[Test]
    public function it_clears_performance_data(): void
    {
        // Set some performance data
        $property = $this->reflection->getProperty('performanceData');
        $property->setAccessible(true);
        $property->setValue($this->logger, ['op1' => [100, 200]]);

        // Clear it
        $this->logger->clearPerformanceData();

        // Check it's empty
        $this->assertEquals([], $property->getValue($this->logger));
    }

    #[Test]
    public function it_respects_log_level_configuration(): void
    {
        Config::set('laravel-mcp.logging.enabled', true);
        Config::set('laravel-mcp.logging.level', LogLevel::ERROR);

        // Info level should not log
        Log::shouldReceive('channel')->never();
        $this->callMethod('logOperation', ['test', [], LogLevel::INFO]);

        // Error level should log
        Config::set('laravel-mcp.logging.level', LogLevel::INFO);
        Log::shouldReceive('channel')
            ->once()
            ->with('mcp')
            ->andReturnSelf();
        Log::shouldReceive('log')->once();
        $this->callMethod('logOperation', ['test', [], LogLevel::ERROR]);
    }

    #[Test]
    public function it_limits_performance_samples(): void
    {
        Config::set('laravel-mcp.logging.track_performance', true);
        Config::set('laravel-mcp.logging.performance.max_samples', 3);

        $method = $this->reflection->getMethod('trackPerformance');
        $method->setAccessible(true);

        // Add more than max samples
        for ($i = 1; $i <= 5; $i++) {
            $method->invoke($this->logger, 'test_op', $i * 10);
        }

        $property = $this->reflection->getProperty('performanceData');
        $property->setAccessible(true);
        $data = $property->getValue($this->logger);

        // Should only have 3 samples (the last 3)
        $this->assertCount(3, $data['test_op']);
        $this->assertEquals([30, 40, 50], $data['test_op']);
    }
}
