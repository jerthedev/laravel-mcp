<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\ConnectionPool;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class ConnectionPoolTest extends TestCase
{
    private ConnectionPool $pool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pool = new ConnectionPool([
            'max_connections' => 3,
            'timeout' => 60,
            'idle_timeout' => 30,
            'health_check_interval' => 10,
            'eviction_policy' => 'lru',
            'debug' => false,
        ]);
    }

    #[Test]
    public function it_can_be_instantiated_with_default_configuration(): void
    {
        $pool = new ConnectionPool;

        $info = $pool->getPoolInfo();
        $this->assertEquals(10, $info['config']['max_connections']);
        $this->assertEquals(30, $info['config']['timeout']);
        $this->assertEquals('lru', $info['config']['eviction_policy']);
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $this->assertTrue($this->pool->isEmpty());
        $this->assertFalse($this->pool->isFull());
        $this->assertEquals(0, $this->pool->getConnectionCount());
    }

    #[Test]
    public function it_can_add_and_retrieve_connections(): void
    {
        $transport = $this->createMockTransport();
        $key = 'test_connection';

        $this->pool->addConnection($key, $transport);

        $this->assertFalse($this->pool->isEmpty());
        $this->assertEquals(1, $this->pool->getConnectionCount());

        $retrieved = $this->pool->getConnection($key);
        $this->assertSame($transport, $retrieved);
    }

    #[Test]
    public function it_returns_null_for_non_existent_connections(): void
    {
        $connection = $this->pool->getConnection('non_existent');
        $this->assertNull($connection);
    }

    #[Test]
    public function it_evicts_oldest_connection_when_at_capacity(): void
    {
        // Fill the pool to capacity
        for ($i = 1; $i <= 3; $i++) {
            $transport = $this->createMockTransport();
            $this->pool->addConnection("connection_{$i}", $transport);
        }

        $this->assertTrue($this->pool->isFull());

        // Add one more connection, should evict the oldest
        $newTransport = $this->createMockTransport();
        $this->pool->addConnection('connection_4', $newTransport);

        $this->assertEquals(3, $this->pool->getConnectionCount());
        $this->assertNull($this->pool->getConnection('connection_1')); // Should be evicted
        $this->assertNotNull($this->pool->getConnection('connection_4')); // Should be present
    }

    #[Test]
    public function it_removes_connections_manually(): void
    {
        $transport = $this->createMockTransport();
        $key = 'test_connection';

        $this->pool->addConnection($key, $transport);
        $this->assertEquals(1, $this->pool->getConnectionCount());

        $removed = $this->pool->removeConnection($key);
        $this->assertTrue($removed);
        $this->assertEquals(0, $this->pool->getConnectionCount());
        $this->assertNull($this->pool->getConnection($key));
    }

    #[Test]
    public function it_returns_false_when_removing_non_existent_connection(): void
    {
        $removed = $this->pool->removeConnection('non_existent');
        $this->assertFalse($removed);
    }

    #[Test]
    public function it_releases_connections_back_to_pool(): void
    {
        $transport = $this->createMockTransport();
        $key = 'test_connection';

        $this->pool->addConnection($key, $transport);
        $this->pool->releaseConnection($key, $transport);

        // Connection should still be available
        $retrieved = $this->pool->getConnection($key);
        $this->assertSame($transport, $retrieved);
    }

    #[Test]
    public function it_tracks_connection_statistics(): void
    {
        $transport = $this->createMockTransport();

        $this->pool->addConnection('test1', $transport);
        $this->pool->getConnection('test1');
        $this->pool->releaseConnection('test1', $transport);

        $stats = $this->pool->getStats();
        $this->assertEquals(1, $stats['connections_created']);
        $this->assertEquals(1, $stats['connections_acquired']);
        $this->assertEquals(1, $stats['connections_released']);
        $this->assertEquals(1, $stats['current_connections']);
    }

    #[Test]
    public function it_performs_health_checks(): void
    {
        $healthyTransport = $this->createMockTransport(true);
        $unhealthyTransport = $this->createMockTransport(false);

        $this->pool->addConnection('healthy', $healthyTransport);
        $this->pool->addConnection('unhealthy', $unhealthyTransport);

        $results = $this->pool->performHealthCheck();

        $this->assertEquals(2, $results['total_connections']);
        $this->assertEquals(1, $results['healthy_connections']);
        $this->assertEquals(1, $results['unhealthy_connections']);

        // Unhealthy connection should be removed
        $this->assertEquals(1, $this->pool->getConnectionCount());
        $this->assertNotNull($this->pool->getConnection('healthy'));
        $this->assertNull($this->pool->getConnection('unhealthy'));
    }

    #[Test]
    public function it_can_clear_all_connections(): void
    {
        $transport1 = $this->createMockTransport();
        $transport2 = $this->createMockTransport();

        $this->pool->addConnection('test1', $transport1);
        $this->pool->addConnection('test2', $transport2);

        $this->assertEquals(2, $this->pool->getConnectionCount());

        $this->pool->clear();

        $this->assertEquals(0, $this->pool->getConnectionCount());
        $this->assertTrue($this->pool->isEmpty());
    }

    #[Test]
    public function it_provides_detailed_pool_information(): void
    {
        $transport = $this->createMockTransport();
        $this->pool->addConnection('test', $transport);

        $info = $this->pool->getPoolInfo();

        $this->assertArrayHasKey('config', $info);
        $this->assertArrayHasKey('stats', $info);
        $this->assertArrayHasKey('connections', $info);
        $this->assertArrayHasKey('test', $info['connections']);

        $connectionInfo = $info['connections']['test'];
        $this->assertArrayHasKey('created', $connectionInfo);
        $this->assertArrayHasKey('last_accessed', $connectionInfo);
        $this->assertArrayHasKey('access_count', $connectionInfo);
        $this->assertArrayHasKey('connected', $connectionInfo);
    }

    #[Test]
    public function it_implements_lru_eviction_policy(): void
    {
        // Add connections
        $transport1 = $this->createMockTransport();
        $transport2 = $this->createMockTransport();
        $transport3 = $this->createMockTransport();

        $this->pool->addConnection('conn1', $transport1);
        $this->pool->addConnection('conn2', $transport2);
        $this->pool->addConnection('conn3', $transport3);

        // Access conn1 to make it recently used
        $this->pool->getConnection('conn1');

        // Add another connection to trigger eviction
        $transport4 = $this->createMockTransport();
        $this->pool->addConnection('conn4', $transport4);

        // conn2 should be evicted (least recently used)
        $this->assertNotNull($this->pool->getConnection('conn1')); // Recently accessed
        $this->assertNull($this->pool->getConnection('conn2'));    // Should be evicted
        $this->assertNotNull($this->pool->getConnection('conn3')); // Still present
        $this->assertNotNull($this->pool->getConnection('conn4')); // New connection
    }

    #[Test]
    public function it_implements_fifo_eviction_policy(): void
    {
        $pool = new ConnectionPool([
            'max_connections' => 2,
            'eviction_policy' => 'fifo',
        ]);

        $transport1 = $this->createMockTransport();
        $transport2 = $this->createMockTransport();

        $pool->addConnection('first', $transport1);
        sleep(1); // Ensure different creation times
        $pool->addConnection('second', $transport2);

        // Add third connection to trigger eviction
        $transport3 = $this->createMockTransport();
        $pool->addConnection('third', $transport3);

        // First connection should be evicted (FIFO)
        $this->assertNull($pool->getConnection('first'));
        $this->assertNotNull($pool->getConnection('second'));
        $this->assertNotNull($pool->getConnection('third'));
    }

    #[Test]
    public function it_implements_lifo_eviction_policy(): void
    {
        $pool = new ConnectionPool([
            'max_connections' => 2,
            'eviction_policy' => 'lifo',
        ]);

        $transport1 = $this->createMockTransport();
        $transport2 = $this->createMockTransport();

        $pool->addConnection('first', $transport1);
        sleep(1); // Ensure different creation times
        $pool->addConnection('second', $transport2);

        // Add third connection to trigger eviction
        $transport3 = $this->createMockTransport();
        $pool->addConnection('third', $transport3);

        // Second connection should be evicted (LIFO)
        $this->assertNotNull($pool->getConnection('first'));
        $this->assertNull($pool->getConnection('second'));
        $this->assertNotNull($pool->getConnection('third'));
    }

    #[Test]
    public function it_validates_connections_before_returning_them(): void
    {
        $expiredTransport = $this->createMockTransport(false); // Will fail health check
        $this->pool->addConnection('expired', $expiredTransport);

        // Try to get the connection - should return null due to failed validation
        $retrieved = $this->pool->getConnection('expired');
        $this->assertNull($retrieved);

        // Connection should be removed from pool
        $this->assertEquals(0, $this->pool->getConnectionCount());
    }

    private function createMockTransport(bool $isConnected = true): TransportInterface
    {
        $transport = Mockery::mock(TransportInterface::class);
        $transport->shouldReceive('isConnected')->andReturn($isConnected);
        $transport->shouldReceive('stop')->andReturnNull();

        if (method_exists($transport, 'getConnectionId')) {
            $transport->shouldReceive('getConnectionId')->andReturn('mock_id_'.uniqid());
        }

        if (method_exists($transport, 'performHealthCheck')) {
            $transport->shouldReceive('performHealthCheck')->andReturn([
                'healthy' => $isConnected,
                'checks' => ['connectivity' => $isConnected],
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
