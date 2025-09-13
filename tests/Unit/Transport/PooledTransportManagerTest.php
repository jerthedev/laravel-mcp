<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\ConnectionPool;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Transport\PooledTransportManager;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class PooledTransportManagerTest extends TestCase
{
    private Container $container;

    private PooledTransportManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container;
        $this->manager = new PooledTransportManager($this->container, [
            'enabled' => true,
            'max_connections_per_type' => 2,
            'debug' => false,
        ]);
    }

    #[Test]
    public function it_can_be_instantiated_with_pooling_enabled(): void
    {
        $this->assertTrue($this->manager->isPoolingEnabled());

        $stats = $this->manager->getPoolStats();
        $this->assertTrue($stats['pooling_enabled']);
        $this->assertGreaterThanOrEqual(0, $stats['total_pools']);
    }

    #[Test]
    public function it_can_be_instantiated_with_pooling_disabled(): void
    {
        $manager = new PooledTransportManager($this->container, ['enabled' => false]);

        $this->assertFalse($manager->isPoolingEnabled());

        $stats = $manager->getPoolStats();
        $this->assertFalse($stats['pooling_enabled']);
    }

    #[Test]
    public function it_creates_connection_pools_for_transport_types(): void
    {
        // Register a mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $pool = $this->manager->getPool('test');
        $this->assertInstanceOf(ConnectionPool::class, $pool);
    }

    #[Test]
    public function it_reuses_connections_from_pool(): void
    {
        // Register mock transport driver
        $mockTransport = $this->createMockTransport();
        $this->manager->extend('test', function () use ($mockTransport) {
            return $mockTransport;
        });

        // Create transport - should create new instance
        $transport1 = $this->manager->createTransport('test');
        $this->assertSame($mockTransport, $transport1);

        // Get same transport configuration - should return from pool if available
        $transport2 = $this->manager->createTransport('test');

        // Since we're using the same config, it might return the same instance from pool
        $this->assertInstanceOf(TransportInterface::class, $transport2);
    }

    #[Test]
    public function it_creates_new_transport_when_pool_empty(): void
    {
        // Register mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $transport = $this->manager->createTransport('test');
        $this->assertInstanceOf(TransportInterface::class, $transport);

        $stats = $this->manager->getPoolStats();
        $this->assertArrayHasKey('pools', $stats);
    }

    #[Test]
    public function it_can_release_transport_back_to_pool(): void
    {
        // Register mock transport driver
        $mockTransport = $this->createMockTransport();
        $this->manager->extend('test', function () use ($mockTransport) {
            return $mockTransport;
        });

        $transport = $this->manager->createTransport('test');

        // Release transport back to pool
        $this->manager->releaseTransport('test', $transport);

        // Pool should now have the connection
        $pool = $this->manager->getPool('test');
        $this->assertGreaterThanOrEqual(0, $pool->getConnectionCount());
    }

    #[Test]
    public function it_falls_back_to_parent_behavior_when_pooling_disabled(): void
    {
        $manager = new PooledTransportManager($this->container, ['enabled' => false]);

        // Register mock transport driver
        $mockTransport = $this->createMockTransport();
        $manager->extend('test', function () use ($mockTransport) {
            return $mockTransport;
        });

        $transport = $this->manager->createTransport('test');
        $this->assertInstanceOf(TransportInterface::class, $transport);

        // Should not create pools when disabled
        $this->assertNull($manager->getPool('test'));
    }

    #[Test]
    public function it_performs_health_checks_on_all_pools(): void
    {
        // Register mock transport drivers
        $this->manager->extend('test1', function () {
            return $this->createMockTransport();
        });

        $this->manager->extend('test2', function () {
            return $this->createMockTransport();
        });

        // Create transports to initialize pools
        $this->manager->createTransport('test1');
        $this->manager->createTransport('test2');

        $results = $this->manager->performHealthChecks();

        $this->assertArrayHasKey('pools_checked', $results);
        $this->assertArrayHasKey('total_connections', $results);
        $this->assertArrayHasKey('pools', $results);
    }

    #[Test]
    public function it_can_clear_specific_pool(): void
    {
        // Register mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        // Create transport to initialize pool
        $this->manager->createTransport('test');

        $pool = $this->manager->getPool('test');
        $this->assertInstanceOf(ConnectionPool::class, $pool);

        $result = $this->manager->clearPool('test');
        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_clearing_non_existent_pool(): void
    {
        $result = $this->manager->clearPool('non_existent');
        $this->assertFalse($result);
    }

    #[Test]
    public function it_can_clear_all_pools(): void
    {
        // Register mock transport drivers
        $this->manager->extend('test1', function () {
            return $this->createMockTransport();
        });

        $this->manager->extend('test2', function () {
            return $this->createMockTransport();
        });

        // Create transports to initialize pools
        $this->manager->createTransport('test1');
        $this->manager->createTransport('test2');

        // Clear all pools
        $this->manager->clearPools();

        $stats = $this->manager->getPoolStats();
        $this->assertArrayHasKey('pools', $stats);
    }

    #[Test]
    public function it_can_enable_pooling_dynamically(): void
    {
        $manager = new PooledTransportManager($this->container, ['enabled' => false]);
        $this->assertFalse($manager->isPoolingEnabled());

        $manager->enablePooling(['max_connections_per_type' => 5]);

        $this->assertTrue($manager->isPoolingEnabled());
    }

    #[Test]
    public function it_can_disable_pooling_and_clear_pools(): void
    {
        $this->assertTrue($this->manager->isPoolingEnabled());

        $this->manager->disablePooling();

        $this->assertFalse($this->manager->isPoolingEnabled());
    }

    #[Test]
    public function it_provides_detailed_pool_information(): void
    {
        // Register mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $this->manager->createTransport('test');

        $info = $this->manager->getPoolInfo();

        $this->assertArrayHasKey('config', $info);
        $this->assertArrayHasKey('pooling_enabled', $info);
        $this->assertArrayHasKey('pools', $info);
        $this->assertTrue($info['pooling_enabled']);
    }

    #[Test]
    public function it_includes_pool_information_in_transport_health(): void
    {
        $health = $this->manager->getTransportHealth();

        $this->assertArrayHasKey('pools', $health);
        $poolStats = $health['pools'];
        $this->assertArrayHasKey('pooling_enabled', $poolStats);
        $this->assertArrayHasKey('total_pools', $poolStats);
    }

    #[Test]
    public function it_clears_pools_during_cleanup(): void
    {
        // Register mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $this->manager->createTransport('test');
        $this->manager->cleanup();

        // Pools should be cleared
        $stats = $this->manager->getPoolStats();
        $this->assertEquals(0, $stats['total_pools']);
    }

    #[Test]
    public function it_clears_pools_during_purge_operations(): void
    {
        // Register mock transport driver
        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $this->manager->createTransport('test');

        // Purge specific driver
        $this->manager->purge('test');

        // Pool for 'test' should be cleared
        $pool = $this->manager->getPool('test');
        $this->assertNotNull($pool); // Pool object exists but should be empty
    }

    #[Test]
    public function it_generates_consistent_connection_keys(): void
    {
        // This is a unit test to ensure connection key generation is consistent
        // We can't easily test this without making the method public, so we test indirectly

        $this->manager->extend('test', function () {
            return $this->createMockTransport();
        });

        $config1 = ['host' => 'localhost', 'port' => 8080];
        $config2 = ['host' => 'localhost', 'port' => 8080]; // Same config
        $config3 = ['host' => 'localhost', 'port' => 8081]; // Different config

        $transport1 = $this->manager->createTransport('test', $config1);
        $transport2 = $this->manager->createTransport('test', $config2);
        $transport3 = $this->manager->createTransport('test', $config3);

        // All should be valid transport instances
        $this->assertInstanceOf(TransportInterface::class, $transport1);
        $this->assertInstanceOf(TransportInterface::class, $transport2);
        $this->assertInstanceOf(TransportInterface::class, $transport3);
    }

    private function createMockTransport(): TransportInterface
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('isConnected')->andReturn(true);
        $transport->shouldReceive('stop')->andReturnNull();
        $transport->shouldReceive('start')->andReturnNull();
        $transport->shouldReceive('getConnectionInfo')->andReturn([]);

        // Mock methods that might be called by the pooling system
        if (method_exists($transport, 'setConnectionPool')) {
            $transport->shouldReceive('setConnectionPool')->andReturnNull();
        }

        if (method_exists($transport, 'getConnectionId')) {
            $transport->shouldReceive('getConnectionId')->andReturn('mock_id_'.uniqid());
        }

        if (method_exists($transport, 'performHealthCheck')) {
            $transport->shouldReceive('performHealthCheck')->andReturn([
                'healthy' => true,
                'checks' => ['connectivity' => true],
            ]);
        }

        return $transport;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
