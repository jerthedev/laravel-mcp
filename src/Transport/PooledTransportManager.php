<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Transport manager with connection pooling capabilities.
 *
 * This class extends the base TransportManager to provide connection pooling,
 * connection reuse, and enhanced lifecycle management for transport instances.
 */
class PooledTransportManager extends TransportManager
{
    /**
     * Connection pools indexed by transport type.
     */
    protected array $connectionPools = [];

    /**
     * Pool configuration.
     */
    protected array $poolConfig;

    /**
     * Whether pooling is enabled.
     */
    protected bool $poolingEnabled;

    /**
     * Create a new pooled transport manager instance.
     */
    public function __construct(Container $container, array $config = [])
    {
        parent::__construct($container);

        $this->poolConfig = array_merge([
            'enabled' => true,
            'max_connections_per_type' => 10,
            'connection_timeout' => 300, // 5 minutes
            'idle_timeout' => 600, // 10 minutes
            'health_check_interval' => 120, // 2 minutes
            'eviction_policy' => 'lru',
            'debug' => false,
        ], $config);

        $this->poolingEnabled = $this->poolConfig['enabled'];

        if ($this->poolingEnabled) {
            $this->initializeConnectionPools();
        }
    }

    /**
     * Create a transport instance with pooling support.
     */
    public function createTransport(string $type, array $config = []): TransportInterface
    {
        if (! $this->poolingEnabled) {
            return parent::createTransport($type, $config);
        }

        // Try to get connection from pool first
        $pool = $this->getConnectionPool($type);
        $connectionKey = $this->generateConnectionKey($type, $config);
        $transport = $pool->getConnection($connectionKey);

        if ($transport) {
            if ($this->poolConfig['debug']) {
                Log::debug('Transport acquired from pool', [
                    'type' => $type,
                    'connection_key' => $connectionKey,
                ]);
            }

            return $transport;
        }

        // Create new transport if none available in pool
        $transport = $this->createNewPooledTransport($type, $config);

        // Configure transport for pooling
        if (method_exists($transport, 'setConnectionPool')) {
            $transport->setConnectionPool($pool);
        }

        // Add to pool for future reuse
        $pool->addConnection($connectionKey, $transport);

        if ($this->poolConfig['debug']) {
            Log::debug('New transport created and added to pool', [
                'type' => $type,
                'connection_key' => $connectionKey,
                'pool_size' => $pool->getConnectionCount(),
            ]);
        }

        return $transport;
    }

    /**
     * Get a transport instance with pooling support.
     */
    public function driver(?string $driver = null): TransportInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (! $this->poolingEnabled) {
            return parent::driver($driver);
        }

        // Check if we have a cached instance that should be pooled
        if (isset($this->transports[$driver])) {
            return $this->transports[$driver];
        }

        // Create transport with pooling
        $transport = $this->createTransport($driver);
        $this->transports[$driver] = $transport;

        return $transport;
    }

    /**
     * Release a transport back to the pool.
     */
    public function releaseTransport(string $type, TransportInterface $transport, array $config = []): void
    {
        if (! $this->poolingEnabled) {
            return;
        }

        $pool = $this->getConnectionPool($type);
        $connectionKey = $this->generateConnectionKey($type, $config);

        $pool->releaseConnection($connectionKey, $transport);

        if ($this->poolConfig['debug']) {
            Log::debug('Transport released to pool', [
                'type' => $type,
                'connection_key' => $connectionKey,
            ]);
        }
    }

    /**
     * Get or create a connection pool for a transport type.
     */
    protected function getConnectionPool(string $type): ConnectionPool
    {
        if (! isset($this->connectionPools[$type])) {
            $poolConfig = array_merge($this->poolConfig, [
                'max_connections' => $this->poolConfig['max_connections_per_type'],
            ]);

            $this->connectionPools[$type] = new ConnectionPool($poolConfig);

            if ($this->poolConfig['debug']) {
                Log::debug('Connection pool created', [
                    'type' => $type,
                    'config' => $poolConfig,
                ]);
            }
        }

        return $this->connectionPools[$type];
    }

    /**
     * Initialize connection pools for default transport types.
     */
    protected function initializeConnectionPools(): void
    {
        $defaultTypes = ['http', 'stdio'];

        foreach ($defaultTypes as $type) {
            if ($this->hasDriver($type)) {
                $this->getConnectionPool($type);
            }
        }

        if ($this->poolConfig['debug']) {
            Log::info('Connection pools initialized', [
                'types' => array_keys($this->connectionPools),
                'pooling_enabled' => $this->poolingEnabled,
            ]);
        }
    }

    /**
     * Create a new transport instance configured for pooling.
     */
    protected function createNewPooledTransport(string $type, array $config = []): TransportInterface
    {
        if (! isset($this->drivers[$type])) {
            throw new TransportException("Unknown transport type: $type");
        }

        $factory = $this->drivers[$type];
        $mergedConfig = array_merge($this->getDriverConfig($type), $config);

        // Add pooling-specific configuration
        $mergedConfig['connection_management_enabled'] = true;
        $mergedConfig['health_check_interval'] = $this->poolConfig['health_check_interval'];
        $mergedConfig['connection_timeout'] = $this->poolConfig['connection_timeout'];

        $transport = $factory($this->container, $mergedConfig);

        if (! $transport instanceof TransportInterface) {
            throw new TransportException("Transport type '$type' must implement TransportInterface");
        }

        return $transport;
    }

    /**
     * Generate a connection key for pool management.
     */
    protected function generateConnectionKey(string $type, array $config = []): string
    {
        $driverConfig = $this->getDriverConfig($type);
        $mergedConfig = array_merge($driverConfig, $config);

        // Only use connection-relevant config for key generation
        $relevantConfig = array_filter($mergedConfig, function ($key) {
            return in_array($key, ['host', 'port', 'auth', 'timeout']);
        }, ARRAY_FILTER_USE_KEY);

        ksort($relevantConfig);

        return $type.'_'.substr(md5(serialize($relevantConfig)), 0, 16);
    }

    /**
     * Get pool statistics for all transport types.
     */
    public function getPoolStats(): array
    {
        $stats = [
            'pooling_enabled' => $this->poolingEnabled,
            'total_pools' => count($this->connectionPools),
            'pools' => [],
        ];

        foreach ($this->connectionPools as $type => $pool) {
            $stats['pools'][$type] = $pool->getStats();
        }

        return $stats;
    }

    /**
     * Get detailed pool information.
     */
    public function getPoolInfo(): array
    {
        $info = [
            'config' => $this->poolConfig,
            'pooling_enabled' => $this->poolingEnabled,
            'pools' => [],
        ];

        foreach ($this->connectionPools as $type => $pool) {
            $info['pools'][$type] = $pool->getPoolInfo();
        }

        return $info;
    }

    /**
     * Perform health checks on all pools.
     */
    public function performHealthChecks(): array
    {
        $results = [
            'timestamp' => time(),
            'pools_checked' => 0,
            'total_connections' => 0,
            'healthy_connections' => 0,
            'unhealthy_connections' => 0,
            'pools' => [],
        ];

        foreach ($this->connectionPools as $type => $pool) {
            $poolHealth = $pool->performHealthCheck();
            $results['pools'][$type] = $poolHealth;
            $results['pools_checked']++;
            $results['total_connections'] += $poolHealth['total_connections'];
            $results['healthy_connections'] += $poolHealth['healthy_connections'];
            $results['unhealthy_connections'] += $poolHealth['unhealthy_connections'];
        }

        if ($this->poolConfig['debug']) {
            Log::debug('Pool health checks completed', [
                'pools_checked' => $results['pools_checked'],
                'total_connections' => $results['total_connections'],
                'healthy_connections' => $results['healthy_connections'],
                'unhealthy_connections' => $results['unhealthy_connections'],
            ]);
        }

        return $results;
    }

    /**
     * Clear all connection pools.
     */
    public function clearPools(): void
    {
        foreach ($this->connectionPools as $type => $pool) {
            $pool->clear();
        }

        if ($this->poolConfig['debug']) {
            Log::info('All connection pools cleared', [
                'pools_cleared' => count($this->connectionPools),
            ]);
        }
    }

    /**
     * Clear a specific connection pool.
     */
    public function clearPool(string $type): bool
    {
        if (! isset($this->connectionPools[$type])) {
            return false;
        }

        $this->connectionPools[$type]->clear();

        if ($this->poolConfig['debug']) {
            Log::info('Connection pool cleared', [
                'type' => $type,
            ]);
        }

        return true;
    }

    /**
     * Enable connection pooling.
     */
    public function enablePooling(array $config = []): void
    {
        if (! empty($config)) {
            $this->poolConfig = array_merge($this->poolConfig, $config);
        }

        $this->poolingEnabled = true;
        $this->initializeConnectionPools();

        Log::info('Connection pooling enabled', [
            'config' => $this->poolConfig,
        ]);
    }

    /**
     * Disable connection pooling and clear all pools.
     */
    public function disablePooling(): void
    {
        $this->clearPools();
        $this->poolingEnabled = false;
        $this->connectionPools = [];

        Log::info('Connection pooling disabled');
    }

    /**
     * Check if pooling is enabled.
     */
    public function isPoolingEnabled(): bool
    {
        return $this->poolingEnabled;
    }

    /**
     * Get a specific connection pool.
     */
    public function getPool(string $type): ?ConnectionPool
    {
        return $this->connectionPools[$type] ?? null;
    }

    /**
     * Override cleanup to handle pools.
     */
    public function cleanup(): void
    {
        if ($this->poolingEnabled) {
            $this->clearPools();
        }

        parent::cleanup();
    }

    /**
     * Override purge to handle pooled connections.
     */
    public function purge(?string $driver = null): void
    {
        $driver = $driver ?: $this->getDefaultDriver();

        // Clear from pool if pooling is enabled
        if ($this->poolingEnabled) {
            $this->clearPool($driver);
        }

        parent::purge($driver);
    }

    /**
     * Override purgeAll to handle all pools.
     */
    public function purgeAll(): void
    {
        if ($this->poolingEnabled) {
            $this->clearPools();
        }

        parent::purgeAll();
    }

    /**
     * Get transport health including pool information.
     */
    public function getTransportHealth(): array
    {
        $health = parent::getTransportHealth();

        if ($this->poolingEnabled) {
            $health['pools'] = $this->getPoolStats();
        }

        return $health;
    }
}
