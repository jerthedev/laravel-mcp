<?php

namespace JTD\LaravelMCP\Transport\Concerns;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;

/**
 * Provides resilience capabilities for transport implementations.
 *
 * This trait implements retry logic, circuit breaker patterns, and
 * reconnection strategies to make transports more resilient to failures.
 */
trait SupportsResilience
{
    /**
     * Maximum number of retry attempts.
     */
    protected int $maxRetryAttempts = 3;

    /**
     * Base retry delay in milliseconds.
     */
    protected int $baseRetryDelay = 1000;

    /**
     * Maximum retry delay in milliseconds.
     */
    protected int $maxRetryDelay = 30000;

    /**
     * Circuit breaker failure threshold.
     */
    protected int $circuitBreakerThreshold = 5;

    /**
     * Circuit breaker timeout in seconds.
     */
    protected int $circuitBreakerTimeout = 60;

    /**
     * Current circuit breaker state.
     */
    protected string $circuitBreakerState = 'closed'; // closed, open, half-open

    /**
     * Circuit breaker failure count.
     */
    protected int $circuitBreakerFailures = 0;

    /**
     * Circuit breaker last failure time.
     */
    protected ?int $circuitBreakerLastFailure = null;

    /**
     * Reconnection attempt count.
     */
    protected int $reconnectionAttempts = 0;

    /**
     * Maximum reconnection attempts.
     */
    protected int $maxReconnectionAttempts = 5;

    /**
     * Initialize resilience configuration.
     */
    protected function initializeResilience(array $config = []): void
    {
        $this->maxRetryAttempts = $config['max_retry_attempts'] ?? $this->maxRetryAttempts;
        $this->baseRetryDelay = $config['base_retry_delay'] ?? $this->baseRetryDelay;
        $this->maxRetryDelay = $config['max_retry_delay'] ?? $this->maxRetryDelay;
        $this->circuitBreakerThreshold = $config['circuit_breaker_threshold'] ?? $this->circuitBreakerThreshold;
        $this->circuitBreakerTimeout = $config['circuit_breaker_timeout'] ?? $this->circuitBreakerTimeout;
        $this->maxReconnectionAttempts = $config['max_reconnection_attempts'] ?? $this->maxReconnectionAttempts;

        if ($this->config['debug'] ?? false) {
            Log::debug('Resilience features initialized', [
                'transport' => $this->getTransportType(),
                'max_retry_attempts' => $this->maxRetryAttempts,
                'circuit_breaker_threshold' => $this->circuitBreakerThreshold,
                'max_reconnection_attempts' => $this->maxReconnectionAttempts,
            ]);
        }
    }

    /**
     * Send a message with retry logic.
     */
    public function sendWithRetry(string $message): bool
    {
        if ($this->isCircuitOpen()) {
            throw TransportException::circuitBreakerOpen($this->getTransportType());
        }

        $attempts = 0;
        $lastException = null;

        while ($attempts < $this->maxRetryAttempts) {
            try {
                $this->doSend($message);
                $this->onOperationSuccess();

                return true;
            } catch (\Throwable $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts >= $this->maxRetryAttempts) {
                    $this->onOperationFailure($e);
                    break;
                }

                $delay = $this->calculateRetryDelay($attempts);

                if ($this->config['debug'] ?? false) {
                    Log::debug('Retrying message send', [
                        'transport' => $this->getTransportType(),
                        'attempt' => $attempts,
                        'max_attempts' => $this->maxRetryAttempts,
                        'delay_ms' => $delay,
                        'error' => $e->getMessage(),
                    ]);
                }

                usleep($delay * 1000);
            }
        }

        throw $lastException ?? new TransportException('Send failed after all retry attempts');
    }

    /**
     * Attempt to reconnect with exponential backoff.
     */
    public function reconnectWithBackoff(): bool
    {
        if ($this->reconnectionAttempts >= $this->maxReconnectionAttempts) {
            Log::error('Maximum reconnection attempts exceeded', [
                'transport' => $this->getTransportType(),
                'attempts' => $this->reconnectionAttempts,
                'max_attempts' => $this->maxReconnectionAttempts,
            ]);

            return false;
        }

        $this->reconnectionAttempts++;
        $delay = $this->calculateReconnectionDelay($this->reconnectionAttempts);

        if ($this->config['debug'] ?? false) {
            Log::info('Attempting reconnection', [
                'transport' => $this->getTransportType(),
                'attempt' => $this->reconnectionAttempts,
                'delay_ms' => $delay,
            ]);
        }

        usleep($delay * 1000);

        try {
            $this->doReconnect();
            $this->reconnectionAttempts = 0;
            $this->onReconnectionSuccess();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Reconnection attempt failed', [
                'transport' => $this->getTransportType(),
                'attempt' => $this->reconnectionAttempts,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check and update circuit breaker state.
     */
    protected function updateCircuitBreakerState(): void
    {
        if ($this->circuitBreakerState === 'open') {
            $timeSinceLastFailure = time() - ($this->circuitBreakerLastFailure ?? 0);

            if ($timeSinceLastFailure >= $this->circuitBreakerTimeout) {
                $this->circuitBreakerState = 'half-open';

                if ($this->config['debug'] ?? false) {
                    Log::info('Circuit breaker state changed to half-open', [
                        'transport' => $this->getTransportType(),
                        'timeout_elapsed' => $timeSinceLastFailure,
                    ]);
                }
            }
        }
    }

    /**
     * Check if circuit breaker is open.
     */
    protected function isCircuitOpen(): bool
    {
        $this->updateCircuitBreakerState();

        return $this->circuitBreakerState === 'open';
    }

    /**
     * Handle successful operation.
     */
    protected function onOperationSuccess(): void
    {
        if ($this->circuitBreakerState === 'half-open') {
            $this->circuitBreakerState = 'closed';
            $this->circuitBreakerFailures = 0;

            if ($this->config['debug'] ?? false) {
                Log::info('Circuit breaker closed after successful operation', [
                    'transport' => $this->getTransportType(),
                ]);
            }
        }

        // Update statistics
        if (isset($this->stats)) {
            $this->stats['successful_operations'] = ($this->stats['successful_operations'] ?? 0) + 1;
        }
    }

    /**
     * Handle operation failure.
     */
    protected function onOperationFailure(\Throwable $error): void
    {
        $this->circuitBreakerFailures++;
        $this->circuitBreakerLastFailure = time();

        if ($this->circuitBreakerFailures >= $this->circuitBreakerThreshold) {
            $this->circuitBreakerState = 'open';

            Log::warning('Circuit breaker opened due to failures', [
                'transport' => $this->getTransportType(),
                'failure_count' => $this->circuitBreakerFailures,
                'threshold' => $this->circuitBreakerThreshold,
                'error' => $error->getMessage(),
            ]);
        }

        // Update statistics
        if (isset($this->stats)) {
            $this->stats['failed_operations'] = ($this->stats['failed_operations'] ?? 0) + 1;
            $this->stats['circuit_breaker_failures'] = $this->circuitBreakerFailures;
        }
    }

    /**
     * Handle successful reconnection.
     */
    protected function onReconnectionSuccess(): void
    {
        Log::info('Reconnection successful', [
            'transport' => $this->getTransportType(),
            'attempts_made' => $this->reconnectionAttempts,
        ]);

        // Reset circuit breaker on successful reconnection
        $this->circuitBreakerState = 'closed';
        $this->circuitBreakerFailures = 0;

        // Update statistics
        if (isset($this->stats)) {
            $this->stats['successful_reconnections'] = ($this->stats['successful_reconnections'] ?? 0) + 1;
            $this->stats['total_reconnection_attempts'] = ($this->stats['total_reconnection_attempts'] ?? 0) + $this->reconnectionAttempts;
        }
    }

    /**
     * Calculate retry delay with exponential backoff.
     */
    protected function calculateRetryDelay(int $attempt): int
    {
        $delay = $this->baseRetryDelay * pow(2, $attempt - 1);

        return min($delay, $this->maxRetryDelay);
    }

    /**
     * Calculate reconnection delay with exponential backoff.
     */
    protected function calculateReconnectionDelay(int $attempt): int
    {
        $delay = $this->baseRetryDelay * pow(2, $attempt - 1);

        return min($delay, $this->maxRetryDelay);
    }

    /**
     * Get resilience statistics.
     */
    public function getResilienceStats(): array
    {
        return [
            'circuit_breaker_state' => $this->circuitBreakerState,
            'circuit_breaker_failures' => $this->circuitBreakerFailures,
            'reconnection_attempts' => $this->reconnectionAttempts,
            'max_retry_attempts' => $this->maxRetryAttempts,
            'max_reconnection_attempts' => $this->maxReconnectionAttempts,
            'successful_operations' => $this->stats['successful_operations'] ?? 0,
            'failed_operations' => $this->stats['failed_operations'] ?? 0,
            'successful_reconnections' => $this->stats['successful_reconnections'] ?? 0,
            'total_reconnection_attempts' => $this->stats['total_reconnection_attempts'] ?? 0,
        ];
    }

    /**
     * Reset circuit breaker state.
     */
    public function resetCircuitBreaker(): void
    {
        $this->circuitBreakerState = 'closed';
        $this->circuitBreakerFailures = 0;
        $this->circuitBreakerLastFailure = null;

        Log::info('Circuit breaker manually reset', [
            'transport' => $this->getTransportType(),
        ]);
    }

    /**
     * Reset reconnection attempts counter.
     */
    public function resetReconnectionAttempts(): void
    {
        $this->reconnectionAttempts = 0;

        if ($this->config['debug'] ?? false) {
            Log::debug('Reconnection attempts counter reset', [
                'transport' => $this->getTransportType(),
            ]);
        }
    }

    /**
     * Perform transport-specific reconnection logic.
     * This method should be implemented by the transport class.
     */
    protected function doReconnect(): void
    {
        $this->stop();
        sleep(1);
        $this->start();
    }

    /**
     * Get the transport type identifier.
     * This method should be implemented by the transport class.
     */
    abstract protected function getTransportType(): string;

    /**
     * Perform transport-specific send operations.
     * This method should be implemented by the transport class.
     */
    abstract protected function doSend(string $message): void;

    /**
     * Start the transport.
     * This method should be implemented by the transport class.
     */
    abstract public function start(): void;

    /**
     * Stop the transport.
     * This method should be implemented by the transport class.
     */
    abstract public function stop(): void;
}
