<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Base transport implementation providing common functionality.
 *
 * This abstract class provides common transport functionality that can be
 * shared between different transport implementations (HTTP, Stdio, etc.).
 * It handles configuration, lifecycle management, error handling, and logging.
 */
abstract class BaseTransport implements TransportInterface
{
    /**
     * Transport configuration.
     */
    protected array $config = [];

    /**
     * Message handler instance.
     */
    protected ?MessageHandlerInterface $messageHandler = null;

    /**
     * Connection status.
     */
    protected bool $connected = false;

    /**
     * Transport running status.
     */
    protected bool $running = false;

    /**
     * Connection establishment timestamp.
     */
    protected ?int $connectedAt = null;

    /**
     * Transport statistics.
     */
    protected array $stats = [
        'messages_sent' => 0,
        'messages_received' => 0,
        'bytes_sent' => 0,
        'bytes_received' => 0,
        'errors_count' => 0,
        'last_activity' => null,
    ];

    /**
     * Default configuration options.
     */
    protected array $defaultConfig = [
        'timeout' => 30,
        'debug' => false,
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
    ];

    /**
     * Initialize the transport layer.
     *
     * @param  array  $config  Transport-specific configuration options
     *
     * @throws TransportException If initialization fails
     */
    public function initialize(array $config = []): void
    {
        $this->config = array_merge($this->defaultConfig, $this->getTransportDefaults(), $config);
        $this->connected = false;
        $this->running = false;
        $this->connectedAt = null;
        $this->stats = [
            'messages_sent' => 0,
            'messages_received' => 0,
            'bytes_sent' => 0,
            'bytes_received' => 0,
            'errors_count' => 0,
            'last_activity' => null,
        ];

        $this->validateConfiguration();

        if ($this->config['debug'] ?? false) {
            Log::debug('Transport initialized', [
                'transport' => $this->getTransportType(),
                'config' => $this->getSafeConfigForLogging(),
            ]);
        }
    }

    /**
     * Start the transport.
     *
     * @throws TransportException If start fails
     */
    public function start(): void
    {
        if ($this->running) {
            return;
        }

        try {
            $this->doStart();
            $this->connected = true;
            $this->running = true;
            $this->connectedAt = time();

            if ($this->messageHandler) {
                $this->messageHandler->onConnect($this);
            }

            if ($this->config['debug'] ?? false) {
                Log::info('Transport started', [
                    'transport' => $this->getTransportType(),
                    'connection_info' => $this->getConnectionInfo(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw TransportException::fromTransportError($e, $this->getTransportType());
        }
    }

    /**
     * Stop the transport.
     */
    public function stop(): void
    {
        if (! $this->running) {
            return;
        }

        try {
            $this->doStop();

            if ($this->messageHandler) {
                $this->messageHandler->onDisconnect($this);
            }

            if ($this->config['debug'] ?? false) {
                Log::info('Transport stopped', [
                    'transport' => $this->getTransportType(),
                    'uptime' => $this->getUptime(),
                    'stats' => $this->getStats(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Error stopping transport', [
                'transport' => $this->getTransportType(),
                'error' => $e->getMessage(),
            ]);
        } finally {
            $this->connected = false;
            $this->running = false;
            $this->connectedAt = null;
        }
    }

    /**
     * Send a message.
     *
     * @param  string  $message  The message to send
     *
     * @throws TransportException If sending fails
     */
    public function send(string $message): void
    {
        if (! $this->isConnected()) {
            throw TransportException::transportClosed($this->getTransportType());
        }

        try {
            $this->doSend($message);
            $this->stats['messages_sent']++;
            $this->stats['bytes_sent'] += strlen($message);
            $this->stats['last_activity'] = time();

            if ($this->config['debug'] ?? false) {
                Log::debug('Message sent', [
                    'transport' => $this->getTransportType(),
                    'message_length' => strlen($message),
                ]);
            }
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw TransportException::transmissionError(
                $this->getTransportType(),
                'Failed to send message: '.$e->getMessage(),
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Receive a message.
     *
     * @return string|null The received message, or null if none available
     *
     * @throws TransportException If receiving fails
     */
    public function receive(): ?string
    {
        if (! $this->isConnected()) {
            return null;
        }

        try {
            $message = $this->doReceive();

            if ($message !== null) {
                $this->stats['messages_received']++;
                $this->stats['bytes_received'] += strlen($message);
                $this->stats['last_activity'] = time();

                if ($this->config['debug'] ?? false) {
                    Log::debug('Message received', [
                        'transport' => $this->getTransportType(),
                        'message_length' => strlen($message),
                    ]);
                }
            }

            return $message;
        } catch (\Throwable $e) {
            $this->handleError($e);
            throw TransportException::transmissionError(
                $this->getTransportType(),
                'Failed to receive message: '.$e->getMessage(),
                ['original_error' => $e->getMessage()]
            );
        }
    }

    /**
     * Check if the transport is connected.
     *
     * @return bool True if connected, false otherwise
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->running;
    }

    /**
     * Get connection information.
     *
     * @return array Connection information
     */
    public function getConnectionInfo(): array
    {
        return [
            'transport_type' => $this->getTransportType(),
            'connected' => $this->isConnected(),
            'connected_at' => $this->connectedAt,
            'uptime' => $this->getUptime(),
            'stats' => $this->getStats(),
        ];
    }

    /**
     * Set the message handler for processing received messages.
     *
     * @param  MessageHandlerInterface  $handler  The message handler
     */
    public function setMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->messageHandler = $handler;

        if ($this->config['debug'] ?? false) {
            Log::debug('Message handler set', [
                'transport' => $this->getTransportType(),
                'handler' => get_class($handler),
            ]);
        }
    }

    /**
     * Get transport configuration.
     *
     * @return array Transport configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get transport statistics.
     *
     * @return array Transport statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'transport_type' => $this->getTransportType(),
            'connected' => $this->isConnected(),
            'uptime' => $this->getUptime(),
        ]);
    }

    /**
     * Get transport uptime in seconds.
     *
     * @return int|null Uptime in seconds, or null if not connected
     */
    public function getUptime(): ?int
    {
        return $this->connectedAt ? time() - $this->connectedAt : null;
    }

    /**
     * Handle transport errors.
     *
     * @param  \Throwable  $error  The error to handle
     */
    protected function handleError(\Throwable $error): void
    {
        $this->stats['errors_count']++;

        Log::error('Transport error', [
            'transport' => $this->getTransportType(),
            'error' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
        ]);

        if ($this->messageHandler) {
            try {
                $this->messageHandler->handleError($error, $this);
            } catch (\Throwable $e) {
                Log::error('Error in message handler error handling', [
                    'transport' => $this->getTransportType(),
                    'original_error' => $error->getMessage(),
                    'handler_error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send a message with retry logic.
     *
     * @param  string  $message  The message to send
     *
     * @throws TransportException If all retry attempts fail
     */
    protected function sendWithRetry(string $message): void
    {
        $attempts = 0;
        $maxAttempts = $this->config['retry_attempts'];
        $retryDelay = $this->config['retry_delay'];

        while ($attempts < $maxAttempts) {
            try {
                $this->send($message);

                return;
            } catch (TransportException $e) {
                $attempts++;

                if ($attempts >= $maxAttempts) {
                    throw $e;
                }

                if ($this->config['debug'] ?? false) {
                    Log::debug('Retrying message send', [
                        'transport' => $this->getTransportType(),
                        'attempt' => $attempts,
                        'max_attempts' => $maxAttempts,
                        'delay' => $retryDelay,
                    ]);
                }

                usleep($retryDelay * 1000 * $attempts); // Exponential backoff
            }
        }
    }

    /**
     * Validate transport configuration.
     *
     * @throws TransportException If configuration is invalid
     */
    protected function validateConfiguration(): void
    {
        if (isset($this->config['timeout']) && $this->config['timeout'] <= 0) {
            throw TransportException::configurationError(
                $this->getTransportType(),
                'Timeout must be greater than 0'
            );
        }

        if (isset($this->config['retry_attempts']) && $this->config['retry_attempts'] < 0) {
            throw TransportException::configurationError(
                $this->getTransportType(),
                'Retry attempts must be non-negative'
            );
        }

        if (isset($this->config['retry_delay']) && $this->config['retry_delay'] < 0) {
            throw TransportException::configurationError(
                $this->getTransportType(),
                'Retry delay must be non-negative'
            );
        }
    }

    /**
     * Get safe configuration for logging (removes sensitive data).
     *
     * @return array Safe configuration
     */
    protected function getSafeConfigForLogging(): array
    {
        $config = $this->config;

        // Remove sensitive configuration keys
        $sensitiveKeys = ['password', 'secret', 'token', 'key', 'auth'];
        foreach ($sensitiveKeys as $key) {
            if (isset($config[$key])) {
                $config[$key] = '[REDACTED]';
            }
        }

        return $config;
    }

    /**
     * Attempt to reconnect the transport.
     *
     * @throws TransportException If reconnection fails
     */
    public function reconnect(): void
    {
        if ($this->config['debug'] ?? false) {
            Log::info('Attempting transport reconnection', [
                'transport' => $this->getTransportType(),
            ]);
        }

        $this->stop();
        sleep(1); // Brief pause before reconnection
        $this->start();
    }

    /**
     * Health check for the transport.
     *
     * @return array Health check results
     */
    public function healthCheck(): array
    {
        $health = [
            'healthy' => false,
            'transport_type' => $this->getTransportType(),
            'connected' => $this->isConnected(),
            'uptime' => $this->getUptime(),
            'stats' => $this->getStats(),
            'checks' => [],
        ];

        // Basic connectivity check
        $health['checks']['connectivity'] = $this->isConnected();

        // Configuration validation
        try {
            $this->validateConfiguration();
            $health['checks']['configuration'] = true;
        } catch (\Throwable $e) {
            $health['checks']['configuration'] = false;
            $health['errors'][] = 'Configuration invalid: '.$e->getMessage();
        }

        // Transport-specific health checks
        $specificChecks = $this->performTransportSpecificHealthChecks();
        $health['checks'] = array_merge($health['checks'], $specificChecks['checks']);

        if (isset($specificChecks['errors'])) {
            $health['errors'] = array_merge($health['errors'] ?? [], $specificChecks['errors']);
        }

        // Overall health determination
        $health['healthy'] = empty($health['errors']) &&
                           $health['checks']['connectivity'] &&
                           $health['checks']['configuration'];

        return $health;
    }

    /**
     * Perform transport-specific health checks.
     *
     * @return array Transport-specific health check results
     */
    protected function performTransportSpecificHealthChecks(): array
    {
        return [
            'checks' => [],
            'errors' => [],
        ];
    }

    /**
     * Get transport type identifier.
     *
     * @return string Transport type
     */
    abstract protected function getTransportType(): string;

    /**
     * Get default configuration for this transport type.
     *
     * @return array Default configuration
     */
    abstract protected function getTransportDefaults(): array;

    /**
     * Perform transport-specific start operations.
     *
     * @throws \Throwable If start fails
     */
    abstract protected function doStart(): void;

    /**
     * Perform transport-specific stop operations.
     *
     * @throws \Throwable If stop fails
     */
    abstract protected function doStop(): void;

    /**
     * Perform transport-specific send operations.
     *
     * @param  string  $message  The message to send
     *
     * @throws \Throwable If send fails
     */
    abstract protected function doSend(string $message): void;

    /**
     * Perform transport-specific receive operations.
     *
     * @return string|null The received message, or null if none available
     *
     * @throws \Throwable If receive fails
     */
    abstract protected function doReceive(): ?string;
}
