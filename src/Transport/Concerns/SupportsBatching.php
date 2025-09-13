<?php

namespace JTD\LaravelMCP\Transport\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Provides message batching capabilities for transport implementations.
 *
 * This trait enables transports to batch multiple messages together for
 * efficient bulk processing, reducing overhead and improving throughput.
 */
trait SupportsBatching
{
    /**
     * Batched messages awaiting processing.
     */
    protected array $batchedMessages = [];

    /**
     * Maximum number of messages per batch.
     */
    protected int $batchSize = 10;

    /**
     * Maximum time to wait before processing batch (milliseconds).
     */
    protected int $batchTimeout = 100;

    /**
     * Timestamp when the first message was added to current batch.
     */
    protected ?float $batchStartTime = null;

    /**
     * Whether batching is currently enabled.
     */
    protected bool $batchingEnabled = false;

    /**
     * Initialize batching configuration.
     */
    protected function initializeBatching(array $config = []): void
    {
        $this->batchSize = $config['batch_size'] ?? $this->batchSize;
        $this->batchTimeout = $config['batch_timeout'] ?? $this->batchTimeout;
        $this->batchingEnabled = $config['batching_enabled'] ?? false;

        if ($this->batchingEnabled && ($this->config['debug'] ?? false)) {
            Log::debug('Message batching initialized', [
                'transport' => $this->getTransportType(),
                'batch_size' => $this->batchSize,
                'batch_timeout' => $this->batchTimeout,
            ]);
        }
    }

    /**
     * Add a message to the batch.
     */
    public function addToBatch(string $message): void
    {
        if (! $this->batchingEnabled) {
            $this->sendImmediately($message);

            return;
        }

        $this->batchedMessages[] = [
            'message' => $message,
            'timestamp' => microtime(true),
        ];

        if ($this->batchStartTime === null) {
            $this->batchStartTime = microtime(true);
        }

        if ($this->config['debug'] ?? false) {
            Log::debug('Message added to batch', [
                'transport' => $this->getTransportType(),
                'batch_count' => count($this->batchedMessages),
                'batch_size_limit' => $this->batchSize,
            ]);
        }

        // Process batch if size limit reached
        if (count($this->batchedMessages) >= $this->batchSize) {
            $this->processBatch();
        }
    }

    /**
     * Process the current batch of messages.
     */
    public function processBatch(): void
    {
        if (empty($this->batchedMessages)) {
            return;
        }

        $batchCount = count($this->batchedMessages);
        $batch = array_map(fn ($item) => $item['message'], $this->batchedMessages);

        try {
            $startTime = microtime(true);
            $this->sendBatch($batch);
            $processingTime = (microtime(true) - $startTime) * 1000;

            if ($this->config['debug'] ?? false) {
                Log::debug('Batch processed successfully', [
                    'transport' => $this->getTransportType(),
                    'message_count' => $batchCount,
                    'processing_time_ms' => round($processingTime, 2),
                ]);
            }

            // Update statistics
            if (isset($this->stats)) {
                $this->stats['batches_processed'] = ($this->stats['batches_processed'] ?? 0) + 1;
                $this->stats['total_batched_messages'] = ($this->stats['total_batched_messages'] ?? 0) + $batchCount;
                $this->stats['avg_batch_size'] = $this->stats['total_batched_messages'] / $this->stats['batches_processed'];
            }

        } catch (\Throwable $e) {
            Log::error('Batch processing failed', [
                'transport' => $this->getTransportType(),
                'message_count' => $batchCount,
                'error' => $e->getMessage(),
            ]);

            // Fall back to individual message sending
            $this->handleBatchFailure($batch, $e);
        } finally {
            $this->clearBatch();
        }
    }

    /**
     * Check if batch should be processed based on timeout.
     */
    public function checkBatchTimeout(): void
    {
        if (! $this->batchingEnabled || empty($this->batchedMessages) || $this->batchStartTime === null) {
            return;
        }

        $elapsedMs = (microtime(true) - $this->batchStartTime) * 1000;

        if ($elapsedMs >= $this->batchTimeout) {
            if ($this->config['debug'] ?? false) {
                Log::debug('Processing batch due to timeout', [
                    'transport' => $this->getTransportType(),
                    'elapsed_ms' => round($elapsedMs, 2),
                    'timeout_ms' => $this->batchTimeout,
                    'message_count' => count($this->batchedMessages),
                ]);
            }

            $this->processBatch();
        }
    }

    /**
     * Force processing of any pending batch.
     */
    public function flushBatch(): void
    {
        if (! empty($this->batchedMessages)) {
            $this->processBatch();
        }
    }

    /**
     * Enable batching mode.
     */
    public function enableBatching(array $config = []): void
    {
        $this->batchingEnabled = true;
        $this->initializeBatching($config);

        if ($this->config['debug'] ?? false) {
            Log::info('Batching enabled', [
                'transport' => $this->getTransportType(),
                'batch_size' => $this->batchSize,
                'batch_timeout' => $this->batchTimeout,
            ]);
        }
    }

    /**
     * Disable batching mode and flush any pending messages.
     */
    public function disableBatching(): void
    {
        $this->flushBatch();
        $this->batchingEnabled = false;

        if ($this->config['debug'] ?? false) {
            Log::info('Batching disabled', [
                'transport' => $this->getTransportType(),
            ]);
        }
    }

    /**
     * Get batching statistics.
     */
    public function getBatchingStats(): array
    {
        return [
            'batching_enabled' => $this->batchingEnabled,
            'batch_size_limit' => $this->batchSize,
            'batch_timeout_ms' => $this->batchTimeout,
            'pending_messages' => count($this->batchedMessages),
            'batches_processed' => $this->stats['batches_processed'] ?? 0,
            'total_batched_messages' => $this->stats['total_batched_messages'] ?? 0,
            'avg_batch_size' => $this->stats['avg_batch_size'] ?? 0,
        ];
    }

    /**
     * Clear the current batch.
     */
    protected function clearBatch(): void
    {
        $this->batchedMessages = [];
        $this->batchStartTime = null;
    }

    /**
     * Handle batch processing failure by falling back to individual sends.
     */
    protected function handleBatchFailure(array $batch, \Throwable $error): void
    {
        Log::warning('Falling back to individual message sending', [
            'transport' => $this->getTransportType(),
            'batch_size' => count($batch),
            'error' => $error->getMessage(),
        ]);

        foreach ($batch as $message) {
            try {
                $this->sendImmediately($message);
            } catch (\Throwable $e) {
                Log::error('Individual message send failed during batch fallback', [
                    'transport' => $this->getTransportType(),
                    'error' => $e->getMessage(),
                ]);

                if (isset($this->stats)) {
                    $this->stats['fallback_failures'] = ($this->stats['fallback_failures'] ?? 0) + 1;
                }
            }
        }
    }

    /**
     * Send a message immediately without batching.
     */
    protected function sendImmediately(string $message): void
    {
        // This method should be implemented by the transport class
        // to provide direct message sending capability
        $this->doSend($message);
    }

    /**
     * Send a batch of messages.
     *
     * Transport implementations should override this method to provide
     * optimized batch sending. Default implementation sends individually.
     */
    protected function sendBatch(array $messages): void
    {
        foreach ($messages as $message) {
            $this->sendImmediately($message);
        }
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
}
