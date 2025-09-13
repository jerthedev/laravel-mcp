<?php

namespace JTD\LaravelMCP\Transport\Concerns;

use Illuminate\Support\Facades\Log;

/**
 * Provides connection lifecycle management for transport implementations.
 *
 * This trait handles connection pooling integration, connection health monitoring,
 * and automatic connection lifecycle management.
 */
trait ManagesConnections
{
    /**
     * Connection pool instance.
     */
    protected ?\JTD\LaravelMCP\Transport\ConnectionPool $connectionPool = null;

    /**
     * Connection identifier for pool management.
     */
    protected ?string $connectionId = null;

    /**
     * Connection metadata for tracking.
     */
    protected array $connectionMetadata = [];

    /**
     * Connection health check interval in seconds.
     */
    protected int $healthCheckInterval = 30;

    /**
     * Last health check timestamp.
     */
    protected ?int $lastHealthCheck = null;

    /**
     * Connection timeout in seconds.
     */
    protected int $connectionTimeout = 60;

    /**
     * Whether connection management is enabled.
     */
    protected bool $connectionManagementEnabled = false;

    /**
     * Initialize connection management.
     */
    protected function initializeConnectionManagement(array $config = []): void
    {
        $this->connectionManagementEnabled = $config['connection_management_enabled'] ?? false;
        $this->healthCheckInterval = $config['health_check_interval'] ?? $this->healthCheckInterval;
        $this->connectionTimeout = $config['connection_timeout'] ?? $this->connectionTimeout;

        if ($this->connectionManagementEnabled) {
            $this->connectionId = $this->generateConnectionId();
            $this->connectionMetadata = [
                'created_at' => time(),
                'transport_type' => $this->getTransportType(),
                'config_hash' => $this->getConfigHash(),
            ];

            if ($this->config['debug'] ?? false) {
                Log::debug('Connection management initialized', [
                    'transport' => $this->getTransportType(),
                    'connection_id' => $this->connectionId,
                    'health_check_interval' => $this->healthCheckInterval,
                ]);
            }
        }
    }

    /**
     * Set the connection pool for this transport.
     */
    public function setConnectionPool(\JTD\LaravelMCP\Transport\ConnectionPool $pool): void
    {
        $this->connectionPool = $pool;

        if ($this->config['debug'] ?? false) {
            Log::debug('Connection pool assigned', [
                'transport' => $this->getTransportType(),
                'connection_id' => $this->connectionId,
            ]);
        }
    }

    /**
     * Get connection from pool or create new one.
     */
    protected function getOrCreateConnection(): void
    {
        if (! $this->connectionManagementEnabled || ! $this->connectionPool) {
            return;
        }

        $pooledConnection = $this->connectionPool->getConnection($this->getConnectionKey());

        if ($pooledConnection && $pooledConnection->isConnected()) {
            $this->adoptPooledConnection($pooledConnection);

            return;
        }

        // Create new connection and add to pool
        $this->establishNewConnection();

        if ($this->isConnected()) {
            $this->connectionPool->addConnection($this->getConnectionKey(), $this);
        }
    }

    /**
     * Release connection back to pool.
     */
    protected function releaseConnection(): void
    {
        if (! $this->connectionManagementEnabled || ! $this->connectionPool || ! $this->connectionId) {
            return;
        }

        if ($this->isConnected()) {
            $this->connectionPool->releaseConnection($this->getConnectionKey(), $this);
        }

        if ($this->config['debug'] ?? false) {
            Log::debug('Connection released to pool', [
                'transport' => $this->getTransportType(),
                'connection_id' => $this->connectionId,
            ]);
        }
    }

    /**
     * Perform connection health check.
     */
    public function performHealthCheck(): array
    {
        $this->lastHealthCheck = time();

        $health = [
            'connection_id' => $this->connectionId,
            'healthy' => false,
            'checks' => [],
            'metadata' => $this->connectionMetadata,
        ];

        try {
            // Basic connectivity check
            $health['checks']['connectivity'] = $this->isConnected();

            // Connection age check
            $connectionAge = time() - ($this->connectionMetadata['created_at'] ?? time());
            $health['checks']['age_within_limits'] = $connectionAge < $this->connectionTimeout;
            $health['connection_age'] = $connectionAge;

            // Transport-specific health checks
            $transportHealth = $this->performTransportSpecificHealthChecks();
            $health['checks'] = array_merge($health['checks'], $transportHealth['checks']);

            if (isset($transportHealth['errors'])) {
                $health['errors'] = $transportHealth['errors'];
            }

            // Overall health determination
            $health['healthy'] = empty($health['errors']) &&
                               $health['checks']['connectivity'] &&
                               $health['checks']['age_within_limits'];

            // Update connection metadata
            $this->connectionMetadata['last_health_check'] = $this->lastHealthCheck;
            $this->connectionMetadata['health_status'] = $health['healthy'] ? 'healthy' : 'unhealthy';

        } catch (\Throwable $e) {
            $health['healthy'] = false;
            $health['errors'][] = 'Health check failed: '.$e->getMessage();

            Log::error('Connection health check failed', [
                'transport' => $this->getTransportType(),
                'connection_id' => $this->connectionId,
                'error' => $e->getMessage(),
            ]);
        }

        return $health;
    }

    /**
     * Check if health check is due.
     */
    public function isHealthCheckDue(): bool
    {
        if (! $this->connectionManagementEnabled || $this->lastHealthCheck === null) {
            return false;
        }

        return (time() - $this->lastHealthCheck) >= $this->healthCheckInterval;
    }

    /**
     * Get connection statistics.
     */
    public function getConnectionStats(): array
    {
        $stats = [
            'connection_id' => $this->connectionId,
            'connection_management_enabled' => $this->connectionManagementEnabled,
            'connected' => $this->isConnected(),
            'metadata' => $this->connectionMetadata,
        ];

        if ($this->connectionManagementEnabled) {
            $stats['age'] = time() - ($this->connectionMetadata['created_at'] ?? time());
            $stats['time_since_health_check'] = $this->lastHealthCheck ?
                time() - $this->lastHealthCheck : null;
            $stats['health_check_due'] = $this->isHealthCheckDue();
        }

        return $stats;
    }

    /**
     * Generate unique connection identifier.
     */
    protected function generateConnectionId(): string
    {
        return sprintf(
            '%s_%s_%s',
            $this->getTransportType(),
            substr(md5(serialize($this->config)), 0, 8),
            uniqid()
        );
    }

    /**
     * Get connection key for pool management.
     */
    protected function getConnectionKey(): string
    {
        return sprintf(
            '%s_%s',
            $this->getTransportType(),
            $this->getConfigHash()
        );
    }

    /**
     * Get configuration hash for connection identification.
     */
    protected function getConfigHash(): string
    {
        // Create hash from connection-relevant config parameters
        $relevantConfig = array_filter($this->config, function ($key) {
            return in_array($key, ['host', 'port', 'timeout', 'auth']);
        }, ARRAY_FILTER_USE_KEY);

        return substr(md5(serialize($relevantConfig)), 0, 16);
    }

    /**
     * Adopt an existing pooled connection.
     */
    protected function adoptPooledConnection($pooledConnection): void
    {
        // Copy relevant state from pooled connection
        if (method_exists($pooledConnection, 'getConnectionMetadata')) {
            $this->connectionMetadata = array_merge(
                $this->connectionMetadata,
                $pooledConnection->getConnectionMetadata()
            );
        }

        if ($this->config['debug'] ?? false) {
            Log::debug('Adopted pooled connection', [
                'transport' => $this->getTransportType(),
                'connection_id' => $this->connectionId,
                'pooled_connection_id' => $pooledConnection->getConnectionId(),
            ]);
        }
    }

    /**
     * Establish a new connection.
     */
    protected function establishNewConnection(): void
    {
        // Update metadata
        $this->connectionMetadata['connection_established_at'] = time();

        if ($this->config['debug'] ?? false) {
            Log::debug('Establishing new connection', [
                'transport' => $this->getTransportType(),
                'connection_id' => $this->connectionId,
            ]);
        }
    }

    /**
     * Get connection metadata.
     */
    public function getConnectionMetadata(): array
    {
        return $this->connectionMetadata;
    }

    /**
     * Get connection identifier.
     */
    public function getConnectionId(): ?string
    {
        return $this->connectionId;
    }

    /**
     * Update connection metadata.
     */
    protected function updateConnectionMetadata(array $metadata): void
    {
        $this->connectionMetadata = array_merge($this->connectionMetadata, $metadata);
    }

    /**
     * Check if connection is expired.
     */
    public function isConnectionExpired(): bool
    {
        if (! $this->connectionManagementEnabled) {
            return false;
        }

        $connectionAge = time() - ($this->connectionMetadata['created_at'] ?? time());

        return $connectionAge >= $this->connectionTimeout;
    }

    /**
     * Cleanup connection resources.
     */
    protected function cleanupConnection(): void
    {
        if ($this->connectionManagementEnabled) {
            $this->releaseConnection();

            if ($this->config['debug'] ?? false) {
                Log::debug('Connection resources cleaned up', [
                    'transport' => $this->getTransportType(),
                    'connection_id' => $this->connectionId,
                ]);
            }
        }

        $this->connectionId = null;
        $this->connectionMetadata = [];
        $this->lastHealthCheck = null;
    }

    /**
     * Get the transport type identifier.
     * This method should be implemented by the transport class.
     */
    abstract protected function getTransportType(): string;

    /**
     * Check if the transport is connected.
     * This method should be implemented by the transport class.
     */
    abstract public function isConnected(): bool;

    /**
     * Perform transport-specific health checks.
     * This method should be implemented by the transport class.
     */
    abstract protected function performTransportSpecificHealthChecks(): array;
}
