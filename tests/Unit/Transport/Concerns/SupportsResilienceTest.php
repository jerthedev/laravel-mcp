<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport\Concerns;

use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Concerns\SupportsResilience;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use PHPUnit\Framework\Attributes\Test;

class SupportsResilienceTest extends TestCase
{
    private TestTransportWithResilience $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transport = new TestTransportWithResilience;
        $this->transport->initialize(['debug' => false]);
    }

    #[Test]
    public function it_can_initialize_resilience_configuration(): void
    {
        $config = [
            'max_retry_attempts' => 5,
            'base_retry_delay' => 2000,
            'circuit_breaker_threshold' => 10,
        ];

        $this->transport->initializeResilience($config);

        $stats = $this->transport->getResilienceStats();
        $this->assertEquals(5, $stats['max_retry_attempts']);
        $this->assertEquals(10, $stats['circuit_breaker_threshold']);
    }

    #[Test]
    public function it_retries_failed_send_operations(): void
    {
        $this->transport->setFailureCount(2); // Fail first 2 attempts, succeed on 3rd
        $this->transport->start();

        $result = $this->transport->sendWithRetry('test message');

        $this->assertTrue($result);
        $this->assertEquals(3, $this->transport->getSendAttemptCount());
    }

    #[Test]
    public function it_throws_exception_after_max_retry_attempts(): void
    {
        $this->transport->setFailureCount(5); // Fail more times than max retries
        $this->transport->initializeResilience(['max_retry_attempts' => 3]);
        $this->transport->start();

        $this->expectException(\Exception::class);
        $this->transport->sendWithRetry('test message');
    }

    #[Test]
    public function it_uses_exponential_backoff_for_retries(): void
    {
        $this->transport->initializeResilience([
            'base_retry_delay' => 100,
            'max_retry_delay' => 5000,
        ]);

        // Test delay calculation
        $delay1 = $this->transport->calculateRetryDelay(1);
        $delay2 = $this->transport->calculateRetryDelay(2);
        $delay3 = $this->transport->calculateRetryDelay(3);

        $this->assertEquals(100, $delay1);
        $this->assertEquals(200, $delay2);
        $this->assertEquals(400, $delay3);
    }

    #[Test]
    public function it_caps_retry_delay_at_maximum(): void
    {
        $this->transport->initializeResilience([
            'base_retry_delay' => 1000,
            'max_retry_delay' => 5000,
        ]);

        $delay = $this->transport->calculateRetryDelay(10); // Very high attempt number
        $this->assertEquals(5000, $delay);
    }

    #[Test]
    public function it_implements_circuit_breaker_pattern(): void
    {
        $this->transport->initializeResilience(['circuit_breaker_threshold' => 3]);
        $this->transport->start();

        // Trigger multiple failures to open circuit breaker
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->transport->setFailureCount(1);
                $this->transport->sendWithRetry('test message');
            } catch (\Exception $e) {
                // Expected failures
            }
        }

        $stats = $this->transport->getResilienceStats();
        $this->assertEquals('open', $stats['circuit_breaker_state']);
        $this->assertEquals(3, $stats['circuit_breaker_failures']);
    }

    #[Test]
    public function it_throws_circuit_breaker_exception_when_open(): void
    {
        $this->transport->initializeResilience(['circuit_breaker_threshold' => 1]);
        $this->transport->start();

        // Trigger failure to open circuit breaker
        try {
            $this->transport->setFailureCount(1);
            $this->transport->sendWithRetry('test message');
        } catch (\Exception $e) {
            // Expected
        }

        // Circuit should now be open and throw TransportException
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Circuit breaker is open');
        $this->transport->sendWithRetry('another message');
    }

    #[Test]
    public function it_transitions_circuit_breaker_to_half_open_after_timeout(): void
    {
        $this->transport->initializeResilience([
            'circuit_breaker_threshold' => 1,
            'circuit_breaker_timeout' => 1, // 1 second timeout
        ]);
        $this->transport->start();

        // Open circuit breaker
        try {
            $this->transport->setFailureCount(1);
            $this->transport->sendWithRetry('test message');
        } catch (\Exception $e) {
            // Expected
        }

        // Wait for timeout and update state
        sleep(2);
        $this->transport->updateCircuitBreakerState();

        $stats = $this->transport->getResilienceStats();
        $this->assertEquals('half-open', $stats['circuit_breaker_state']);
    }

    #[Test]
    public function it_closes_circuit_breaker_after_successful_operation_in_half_open(): void
    {
        $this->transport->initializeResilience(['circuit_breaker_threshold' => 1]);
        $this->transport->start();

        // Open circuit breaker
        try {
            $this->transport->setFailureCount(1);
            $this->transport->sendWithRetry('test message');
        } catch (\Exception $e) {
            // Expected
        }

        // Manually set to half-open and succeed
        $this->transport->setCircuitBreakerState('half-open');
        $this->transport->setFailureCount(0);
        $result = $this->transport->sendWithRetry('success message');

        $this->assertTrue($result);
        $stats = $this->transport->getResilienceStats();
        $this->assertEquals('closed', $stats['circuit_breaker_state']);
    }

    #[Test]
    public function it_can_reset_circuit_breaker_manually(): void
    {
        $this->transport->initializeResilience(['circuit_breaker_threshold' => 1]);
        $this->transport->start();

        // Open circuit breaker
        try {
            $this->transport->setFailureCount(1);
            $this->transport->sendWithRetry('test message');
        } catch (\Exception $e) {
            // Expected
        }

        $this->transport->resetCircuitBreaker();

        $stats = $this->transport->getResilienceStats();
        $this->assertEquals('closed', $stats['circuit_breaker_state']);
        $this->assertEquals(0, $stats['circuit_breaker_failures']);
    }

    #[Test]
    public function it_tracks_reconnection_attempts_with_backoff(): void
    {
        $this->transport->initializeResilience([
            'max_reconnection_attempts' => 3,
            'base_retry_delay' => 100,
        ]);

        // Simulate reconnection failures
        $this->transport->setReconnectionFailureCount(2);

        $result1 = $this->transport->reconnectWithBackoff(); // Should fail
        $this->assertFalse($result1);

        $result2 = $this->transport->reconnectWithBackoff(); // Should fail
        $this->assertFalse($result2);

        $result3 = $this->transport->reconnectWithBackoff(); // Should succeed
        $this->assertTrue($result3);

        $stats = $this->transport->getResilienceStats();
        $this->assertGreaterThan(0, $stats['successful_reconnections']);
    }

    #[Test]
    public function it_stops_reconnection_after_max_attempts(): void
    {
        $this->transport->initializeResilience(['max_reconnection_attempts' => 2]);
        $this->transport->setReconnectionFailureCount(5); // Always fail

        $result1 = $this->transport->reconnectWithBackoff();
        $this->assertFalse($result1);

        $result2 = $this->transport->reconnectWithBackoff();
        $this->assertFalse($result2);

        $result3 = $this->transport->reconnectWithBackoff();
        $this->assertFalse($result3); // Should not attempt again

        $this->assertEquals(2, $this->transport->getReconnectionAttempts());
    }

    #[Test]
    public function it_can_reset_reconnection_attempts(): void
    {
        $this->transport->initializeResilience(['max_reconnection_attempts' => 2]);
        $this->transport->setReconnectionFailureCount(5);

        $this->transport->reconnectWithBackoff();
        $this->transport->reconnectWithBackoff();

        $this->assertEquals(2, $this->transport->getReconnectionAttempts());

        $this->transport->resetReconnectionAttempts();

        $this->assertEquals(0, $this->transport->getReconnectionAttempts());
    }

    #[Test]
    public function it_tracks_operation_statistics(): void
    {
        $this->transport->start();

        // Successful operations
        $this->transport->setFailureCount(0);
        $this->transport->sendWithRetry('message 1');
        $this->transport->sendWithRetry('message 2');

        // Failed operation
        try {
            $this->transport->setFailureCount(5);
            $this->transport->sendWithRetry('message 3');
        } catch (\Exception $e) {
            // Expected
        }

        $stats = $this->transport->getResilienceStats();
        $this->assertEquals(2, $stats['successful_operations']);
        $this->assertEquals(1, $stats['failed_operations']);
    }
}

/**
 * Test transport implementation with resilience support.
 */
class TestTransportWithResilience implements TransportInterface
{
    use SupportsResilience;

    protected array $config = [];

    protected bool $connected = false;

    protected array $stats = [];

    protected int $sendAttemptCount = 0;

    protected int $failureCount = 0;

    protected int $reconnectionFailureCount = 0;

    protected int $currentReconnectionAttempt = 0;

    public function initialize(array $config = []): void
    {
        $this->config = $config;
        $this->initializeResilience($config);
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
        $this->doSend($message);
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
        $this->sendAttemptCount++;

        if ($this->failureCount > 0) {
            $this->failureCount--;
            throw new \Exception('Send failed for testing');
        }
    }

    protected function doReconnect(): void
    {
        $this->currentReconnectionAttempt++;

        if ($this->reconnectionFailureCount > 0) {
            $this->reconnectionFailureCount--;
            throw new \Exception('Reconnection failed for testing');
        }
    }

    public function setFailureCount(int $count): void
    {
        $this->failureCount = $count;
        $this->sendAttemptCount = 0;
    }

    public function getSendAttemptCount(): int
    {
        return $this->sendAttemptCount;
    }

    public function setReconnectionFailureCount(int $count): void
    {
        $this->reconnectionFailureCount = $count;
        $this->currentReconnectionAttempt = 0;
    }

    public function getReconnectionAttempts(): int
    {
        return $this->reconnectionAttempts;
    }

    public function setCircuitBreakerState(string $state): void
    {
        $this->circuitBreakerState = $state;
    }

    // Expose protected methods for testing
    public function calculateRetryDelay(int $attempt): int
    {
        $delay = $this->baseRetryDelay * pow(2, $attempt - 1);

        return min($delay, $this->maxRetryDelay);
    }

    public function updateCircuitBreakerState(): void
    {
        if ($this->circuitBreakerState === 'open') {
            $timeSinceLastFailure = time() - ($this->circuitBreakerLastFailure ?? 0);

            if ($timeSinceLastFailure >= $this->circuitBreakerTimeout) {
                $this->circuitBreakerState = 'half-open';
            }
        }
    }
}
