<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Connection pool for managing transport connections.
 *
 * This class implements a connection pool that manages multiple transport
 * connections with lifecycle management, eviction policies, and health monitoring.
 */
class ConnectionPool
{
    /**
     * Pool of active connections.
     */
    protected array $connections = [];

    /**
     * Maximum number of connections in the pool.
     */
    protected int $maxConnections;

    /**
     * Connection timeout in seconds.
     */
    protected int $timeout;

    /**
     * Connection idle timeout in seconds.
     */
    protected int $idleTimeout;

    /**
     * Health check interval in seconds.
     */
    protected int $healthCheckInterval;

    /**
     * Pool statistics.
     */
    protected array $stats = [
        'connections_created' => 0,
        'connections_acquired' => 0,
        'connections_released' => 0,
        'connections_evicted' => 0,
        'health_checks_performed' => 0,
        'unhealthy_connections_removed' => 0,
    ];

    /**
     * Last health check timestamp.
     */
    protected ?int $lastHealthCheck = null;

    /**
     * Pool configuration.
     */
    protected array $config;

    /**
     * Create a new connection pool instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_connections' => 10,
            'timeout' => 30,
            'idle_timeout' => 300, // 5 minutes
            'health_check_interval' => 60, // 1 minute
            'eviction_policy' => 'lru', // lru, fifo, lifo
            'debug' => false,
        ], $config);

        $this->maxConnections = $this->config['max_connections'];
        $this->timeout = $this->config['timeout'];
        $this->idleTimeout = $this->config['idle_timeout'];
        $this->healthCheckInterval = $this->config['health_check_interval'];

        if ($this->config['debug']) {
            Log::debug('Connection pool initialized', [
                'max_connections' => $this->maxConnections,
                'timeout' => $this->timeout,
                'idle_timeout' => $this->idleTimeout,
            ]);
        }
    }

    /**
     * Get a connection from the pool.
     */
    public function getConnection(string $key): ?TransportInterface
    {
        $this->performHealthCheckIfDue();

        if (isset($this->connections[$key])) {
            $connection = $this->connections[$key];

            // Check if connection is still valid
            if ($this->isConnectionValid($connection)) {
                $connection['last_accessed'] = time();
                $connection['access_count']++;

                $this->stats['connections_acquired']++;

                if ($this->config['debug']) {
                    Log::debug('Connection acquired from pool', [
                        'key' => $key,
                        'connection_id' => $connection['transport']->getConnectionId() ?? 'unknown',
                        'access_count' => $connection['access_count'],
                    ]);
                }

                return $connection['transport'];
            } else {
                // Remove invalid connection
                $this->removeConnection($key, 'invalid');
            }
        }

        return null;
    }

    /**
     * Add a connection to the pool.
     */
    public function addConnection(string $key, TransportInterface $transport): void
    {
        // Check if we need to evict connections to make room
        if (count($this->connections) >= $this->maxConnections) {
            $this->evictOldestConnection();
        }

        $connectionData = [
            'transport' => $transport,
            'created' => time(),
            'last_accessed' => time(),
            'expires' => time() + $this->timeout,
            'access_count' => 0,
            'health_status' => 'unknown',
            'last_health_check' => null,
        ];

        $this->connections[$key] = $connectionData;
        $this->stats['connections_created']++;

        if ($this->config['debug']) {
            Log::debug('Connection added to pool', [
                'key' => $key,
                'connection_id' => $transport->getConnectionId() ?? 'unknown',
                'pool_size' => count($this->connections),
                'max_connections' => $this->maxConnections,
            ]);
        }
    }

    /**
     * Release a connection back to the pool.
     */
    public function releaseConnection(string $key, TransportInterface $transport): void
    {
        if (isset($this->connections[$key])) {
            $this->connections[$key]['last_accessed'] = time();
            $this->stats['connections_released']++;

            if ($this->config['debug']) {
                Log::debug('Connection released to pool', [
                    'key' => $key,
                    'connection_id' => $transport->getConnectionId() ?? 'unknown',
                ]);
            }
        }
    }

    /**
     * Remove a connection from the pool.
     */
    public function removeConnection(string $key, string $reason = 'manual'): bool
    {
        if (! isset($this->connections[$key])) {
            return false;
        }

        $connection = $this->connections[$key];

        try {
            if ($connection['transport']->isConnected()) {
                $connection['transport']->stop();
            }
        } catch (\Throwable $e) {
            Log::warning('Error stopping connection during removal', [
                'key' => $key,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }

        unset($this->connections[$key]);

        if ($reason === 'eviction') {
            $this->stats['connections_evicted']++;
        } elseif ($reason === 'unhealthy') {
            $this->stats['unhealthy_connections_removed']++;
        }

        if ($this->config['debug']) {
            Log::debug('Connection removed from pool', [
                'key' => $key,
                'reason' => $reason,
                'pool_size' => count($this->connections),
            ]);
        }

        return true;
    }

    /**
     * Evict the oldest connection based on eviction policy.
     */
    protected function evictOldestConnection(): void
    {
        if (empty($this->connections)) {
            return;
        }

        $keyToEvict = $this->selectConnectionForEviction();

        if ($keyToEvict) {
            $this->removeConnection($keyToEvict, 'eviction');

            if ($this->config['debug']) {
                Log::info('Connection evicted from pool', [
                    'key' => $keyToEvict,
                    'policy' => $this->config['eviction_policy'],
                    'pool_size' => count($this->connections),
                ]);
            }
        }
    }

    /**
     * Select a connection for eviction based on the configured policy.
     */
    protected function selectConnectionForEviction(): ?string
    {
        switch ($this->config['eviction_policy']) {
            case 'lru': // Least Recently Used
                return $this->selectLeastRecentlyUsed();
            case 'fifo': // First In, First Out
                return $this->selectOldestCreated();
            case 'lifo': // Last In, First Out
                return $this->selectNewestCreated();
            default:
                return $this->selectLeastRecentlyUsed();
        }
    }

    /**
     * Select least recently used connection.
     */
    protected function selectLeastRecentlyUsed(): ?string
    {
        $oldest = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($this->connections as $key => $connection) {
            if ($connection['last_accessed'] < $oldestTime) {
                $oldestTime = $connection['last_accessed'];
                $oldest = $key;
            }
        }

        return $oldest;
    }

    /**
     * Select oldest created connection.
     */
    protected function selectOldestCreated(): ?string
    {
        $oldest = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($this->connections as $key => $connection) {
            if ($connection['created'] < $oldestTime) {
                $oldestTime = $connection['created'];
                $oldest = $key;
            }
        }

        return $oldest;
    }

    /**
     * Select newest created connection.
     */
    protected function selectNewestCreated(): ?string
    {
        $newest = null;
        $newestTime = 0;

        foreach ($this->connections as $key => $connection) {
            if ($connection['created'] > $newestTime) {
                $newestTime = $connection['created'];
                $newest = $key;
            }
        }

        return $newest;
    }

    /**
     * Check if a connection is valid.
     */
    protected function isConnectionValid(array $connection): bool
    {
        // Check if connection is expired
        if (time() > $connection['expires']) {
            return false;
        }

        // Check if connection is idle for too long
        if (time() - $connection['last_accessed'] > $this->idleTimeout) {
            return false;
        }

        // Check if transport is still connected
        try {
            return $connection['transport']->isConnected();
        } catch (\Throwable $e) {
            Log::warning('Error checking connection validity', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Perform health check if due.
     */
    protected function performHealthCheckIfDue(): void
    {
        if ($this->lastHealthCheck === null ||
            (time() - $this->lastHealthCheck) >= $this->healthCheckInterval) {
            $this->performHealthCheck();
        }
    }

    /**
     * Perform health check on all connections.
     */
    public function performHealthCheck(): array
    {
        $this->lastHealthCheck = time();
        $this->stats['health_checks_performed']++;

        $results = [
            'timestamp' => $this->lastHealthCheck,
            'total_connections' => count($this->connections),
            'healthy_connections' => 0,
            'unhealthy_connections' => 0,
            'connections' => [],
        ];

        $unhealthyKeys = [];

        foreach ($this->connections as $key => $connection) {
            try {
                $health = $this->checkConnectionHealth($connection);
                $this->connections[$key]['health_status'] = $health['healthy'] ? 'healthy' : 'unhealthy';
                $this->connections[$key]['last_health_check'] = $this->lastHealthCheck;

                $results['connections'][$key] = $health;

                if ($health['healthy']) {
                    $results['healthy_connections']++;
                } else {
                    $results['unhealthy_connections']++;
                    $unhealthyKeys[] = $key;
                }
            } catch (\Throwable $e) {
                $results['connections'][$key] = [
                    'healthy' => false,
                    'error' => $e->getMessage(),
                ];
                $results['unhealthy_connections']++;
                $unhealthyKeys[] = $key;
            }
        }

        // Remove unhealthy connections
        foreach ($unhealthyKeys as $key) {
            $this->removeConnection($key, 'unhealthy');
        }

        if ($this->config['debug']) {
            Log::debug('Pool health check completed', [
                'total_connections' => $results['total_connections'],
                'healthy_connections' => $results['healthy_connections'],
                'unhealthy_connections' => $results['unhealthy_connections'],
                'removed_unhealthy' => count($unhealthyKeys),
            ]);
        }

        return $results;
    }

    /**
     * Check health of a specific connection.
     */
    protected function checkConnectionHealth(array $connection): array
    {
        $transport = $connection['transport'];

        $health = [
            'healthy' => false,
            'checks' => [],
        ];

        // Basic connectivity check
        $health['checks']['connected'] = $transport->isConnected();

        // Age check
        $age = time() - $connection['created'];
        $health['checks']['age_within_limits'] = $age < $this->timeout;

        // Idle check
        $idleTime = time() - $connection['last_accessed'];
        $health['checks']['not_idle_too_long'] = $idleTime < $this->idleTimeout;

        // Transport-specific health check
        if (method_exists($transport, 'performHealthCheck')) {
            try {
                $transportHealth = $transport->performHealthCheck();
                $health['checks']['transport_specific'] = $transportHealth['healthy'] ?? false;
                if (isset($transportHealth['errors'])) {
                    $health['errors'] = $transportHealth['errors'];
                }
            } catch (\Throwable $e) {
                $health['checks']['transport_specific'] = false;
                $health['errors'][] = 'Transport health check failed: '.$e->getMessage();
            }
        }

        // Overall health determination
        $health['healthy'] = empty($health['errors']) &&
                           $health['checks']['connected'] &&
                           $health['checks']['age_within_limits'] &&
                           $health['checks']['not_idle_too_long'] &&
                           ($health['checks']['transport_specific'] ?? true);

        return $health;
    }

    /**
     * Get pool statistics.
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'current_connections' => count($this->connections),
            'max_connections' => $this->maxConnections,
            'pool_utilization' => count($this->connections) / $this->maxConnections,
            'last_health_check' => $this->lastHealthCheck,
        ]);
    }

    /**
     * Get detailed pool information.
     */
    public function getPoolInfo(): array
    {
        $info = [
            'config' => $this->config,
            'stats' => $this->getStats(),
            'connections' => [],
        ];

        foreach ($this->connections as $key => $connection) {
            $info['connections'][$key] = [
                'created' => $connection['created'],
                'last_accessed' => $connection['last_accessed'],
                'expires' => $connection['expires'],
                'access_count' => $connection['access_count'],
                'health_status' => $connection['health_status'],
                'last_health_check' => $connection['last_health_check'],
                'connected' => $connection['transport']->isConnected(),
                'age' => time() - $connection['created'],
                'idle_time' => time() - $connection['last_accessed'],
            ];
        }

        return $info;
    }

    /**
     * Clear all connections from the pool.
     */
    public function clear(): void
    {
        foreach ($this->connections as $key => $connection) {
            try {
                if ($connection['transport']->isConnected()) {
                    $connection['transport']->stop();
                }
            } catch (\Throwable $e) {
                Log::warning('Error stopping connection during pool clear', [
                    'key' => $key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $clearedCount = count($this->connections);
        $this->connections = [];

        if ($this->config['debug']) {
            Log::info('Connection pool cleared', [
                'connections_cleared' => $clearedCount,
            ]);
        }
    }

    /**
     * Get the number of active connections.
     */
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }

    /**
     * Check if the pool is full.
     */
    public function isFull(): bool
    {
        return count($this->connections) >= $this->maxConnections;
    }

    /**
     * Check if the pool is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->connections);
    }
}
