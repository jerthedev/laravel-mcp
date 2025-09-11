<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('Epic-Transport')]
#[Group('Sprint-Core')]
#[Group('ticket-010')]
class TransportDiscoveryAndLifecycleTest extends TestCase
{
    #[Test]
    public function it_discovers_and_registers_default_transports()
    {
        $manager = $this->app->make(TransportManager::class);

        $drivers = $manager->getDrivers();

        $this->assertContains('http', $drivers);
        $this->assertContains('stdio', $drivers);
        $this->assertCount(2, $drivers);
    }

    #[Test]
    public function it_resolves_transport_interface_from_container()
    {
        // Set HTTP as default transport
        config(['mcp-transports.default' => 'http']);

        $transport = $this->app->make(TransportInterface::class);

        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_resolves_stdio_transport_as_default_when_not_configured()
    {
        // Ensure no default is set
        config(['mcp-transports.default' => null]);

        $transport = $this->app->make(TransportInterface::class);

        $this->assertInstanceOf(StdioTransport::class, $transport);
    }

    #[Test]
    public function it_manages_complete_transport_lifecycle()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create and initialize transport
        $transport = $manager->createTransport('http', [
            'host' => '127.0.0.1',
            'port' => 8080,
        ]);

        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertFalse($transport->isConnected());

        // Start transport
        $transport->start();
        $this->assertTrue($transport->isConnected());

        // Verify connection info
        $info = $transport->getConnectionInfo();
        $this->assertEquals('http', $info['transport_type']);
        $this->assertTrue($info['connected']);
        $this->assertIsArray($info['stats']);

        // Stop transport
        $transport->stop();
        $this->assertFalse($transport->isConnected());
    }

    #[Test]
    public function it_manages_multiple_transport_instances()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create multiple transports
        $httpTransport = $manager->createTransport('http');
        $stdioTransport = $manager->createTransport('stdio');

        $this->assertInstanceOf(HttpTransport::class, $httpTransport);
        $this->assertInstanceOf(StdioTransport::class, $stdioTransport);

        // Start both transports
        $httpTransport->start();
        $stdioTransport->start();

        $this->assertTrue($httpTransport->isConnected());
        $this->assertTrue($stdioTransport->isConnected());

        // Stop both transports
        $httpTransport->stop();
        $stdioTransport->stop();

        $this->assertFalse($httpTransport->isConnected());
        $this->assertFalse($stdioTransport->isConnected());
    }

    #[Test]
    public function it_handles_active_transport_management()
    {
        $manager = $this->app->make(TransportManager::class);

        $this->assertNull($manager->getActiveTransport());

        // Set active transport
        $manager->setActiveTransport('http', ['host' => 'test-host']);

        $activeTransport = $manager->getActiveTransport();
        $this->assertInstanceOf(HttpTransport::class, $activeTransport);

        $config = $activeTransport->getConfig();
        $this->assertEquals('test-host', $config['host']);
    }

    #[Test]
    public function it_performs_transport_health_monitoring()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create and start transports
        $httpTransport = $manager->driver('http');
        $stdioTransport = $manager->driver('stdio');

        $httpTransport->start();
        $stdioTransport->start();

        // Check individual transport health
        $httpHealth = $httpTransport->healthCheck();
        $this->assertTrue($httpHealth['healthy']);
        $this->assertEquals('http', $httpHealth['transport_type']);
        $this->assertTrue($httpHealth['checks']['connectivity']);

        $stdioHealth = $stdioTransport->healthCheck();
        $this->assertTrue($stdioHealth['healthy']);
        $this->assertEquals('stdio', $stdioHealth['transport_type']);

        // Check overall transport health via manager
        $overallHealth = $manager->getTransportHealth();
        $this->assertArrayHasKey('http', $overallHealth);
        $this->assertArrayHasKey('stdio', $overallHealth);

        $this->assertTrue($overallHealth['http']['connected']);
        $this->assertTrue($overallHealth['stdio']['connected']);
    }

    #[Test]
    public function it_handles_transport_configuration_integration()
    {
        // Set transport configuration
        config([
            'mcp-transports.transports.http.host' => 'configured-host',
            'mcp-transports.transports.http.port' => 9090,
            'mcp-transports.transports.http.timeout' => 45,
        ]);

        $manager = $this->app->make(TransportManager::class);
        $transport = $manager->createTransport('http');

        $config = $transport->getConfig();
        $this->assertEquals('configured-host', $config['host']);
        $this->assertEquals(9090, $config['port']);
        $this->assertEquals(45, $config['timeout']);
    }

    #[Test]
    public function it_manages_transport_discovery_through_service_container()
    {
        // Test that transports are properly bound in the service container
        $this->assertTrue($this->app->bound(TransportManager::class));
        $this->assertTrue($this->app->bound(TransportInterface::class));
        $this->assertTrue($this->app->bound('mcp.transport.http'));
        $this->assertTrue($this->app->bound('mcp.transport.stdio'));

        // Test resolution
        $manager1 = $this->app->make(TransportManager::class);
        $manager2 = $this->app->make(TransportManager::class);

        // Should be the same instance (singleton)
        $this->assertSame($manager1, $manager2);
    }

    #[Test]
    public function it_handles_transport_lifecycle_with_error_recovery()
    {
        $manager = $this->app->make(TransportManager::class);
        $transport = $manager->createTransport('http');

        // Start transport
        $transport->start();
        $this->assertTrue($transport->isConnected());

        // Simulate reconnection
        $transport->reconnect();
        $this->assertTrue($transport->isConnected());

        // Verify transport is still functional
        $info = $transport->getConnectionInfo();
        $this->assertTrue($info['connected']);
        $this->assertEquals('http', $info['transport_type']);
    }

    #[Test]
    public function it_manages_transport_statistics_and_monitoring()
    {
        $manager = $this->app->make(TransportManager::class);
        $transport = $manager->createTransport('http');

        $transport->start();

        // Get initial stats
        $stats = $transport->getStats();
        $this->assertEquals(0, $stats['messages_sent']);
        $this->assertEquals(0, $stats['messages_received']);
        $this->assertEquals(0, $stats['errors_count']);
        $this->assertEquals('http', $stats['transport_type']);

        // Send a message (using the transport's send method)
        try {
            $transport->send('{"test": "message"}');
        } catch (\Exception $e) {
            // Expected for HTTP transport without proper request context
        }

        // Verify stats updated
        $updatedStats = $transport->getStats();
        $this->assertIsInt($updatedStats['uptime']);
        $this->assertTrue($updatedStats['connected']);
    }

    #[Test]
    public function it_handles_transport_manager_bulk_operations()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create multiple transports
        $manager->driver('http');
        $manager->driver('stdio');

        $this->assertEquals(2, count($manager->getActiveTransports()));
        $this->assertTrue($manager->hasActiveTransports());

        // Start all transports
        $manager->startAllTransports();
        $this->assertEquals(2, $manager->getActiveTransportCount());

        // Stop all transports
        $manager->stopAllTransports();
        $this->assertEquals(0, $manager->getActiveTransportCount());

        // Cleanup
        $manager->cleanup();
        $this->assertFalse($manager->hasActiveTransports());
    }

    #[Test]
    public function it_handles_transport_instance_caching_and_purging()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create transport instance
        $transport1 = $manager->driver('http');
        $transport2 = $manager->driver('http');

        // Should be the same cached instance
        $this->assertSame($transport1, $transport2);

        // Purge specific transport
        $manager->purge('http');

        // Create new instance - should be different
        $transport3 = $manager->driver('http');
        $this->assertNotSame($transport1, $transport3);

        // Create another transport and purge all
        $manager->driver('stdio');
        $manager->purgeAll();

        // All should be cleared
        $this->assertCount(0, $manager->getActiveTransports());
    }

    #[Test]
    public function it_integrates_with_laravel_configuration_system()
    {
        // Test that configuration is properly loaded and merged
        config([
            'laravel-mcp.transports.enabled' => true,
            'laravel-mcp.transports.default' => 'http',
            'mcp-transports.transports.http.debug' => true,
        ]);

        $manager = $this->app->make(TransportManager::class);
        $transport = $manager->createTransport('http');

        $config = $transport->getConfig();
        $this->assertTrue($config['debug']);
    }

    #[Test]
    public function it_manages_transport_custom_extensions()
    {
        $manager = $this->app->make(TransportManager::class);

        // Extend with custom transport
        $manager->extend('custom', function ($container, $config) {
            return new class implements TransportInterface
            {
                private array $config;

                private bool $connected = false;

                public function __construct(array $config = [])
                {
                    $this->config = $config;
                }

                public function initialize(array $config = []): void
                {
                    $this->config = array_merge($this->config, $config);
                }

                public function start(): void
                {
                    $this->connected = true;
                }

                public function stop(): void
                {
                    $this->connected = false;
                }

                public function send(string $message): void {}

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
                    return [
                        'transport_type' => 'custom',
                        'connected' => $this->connected,
                    ];
                }

                public function setMessageHandler($handler): void {}
            };
        });

        // Test custom transport
        $this->assertTrue($manager->hasDriver('custom'));

        $customTransport = $manager->createTransport('custom');
        $this->assertInstanceOf(TransportInterface::class, $customTransport);

        $customTransport->start();
        $this->assertTrue($customTransport->isConnected());

        $info = $customTransport->getConnectionInfo();
        $this->assertEquals('custom', $info['transport_type']);
    }

    #[Test]
    public function it_handles_transport_registration_and_removal()
    {
        $manager = $this->app->make(TransportManager::class);

        // Register a custom transport instance
        $mockTransport = $this->createMock(TransportInterface::class);
        $mockTransport->method('isConnected')->willReturn(true);

        $manager->registerTransport('mock', $mockTransport);

        $activeTransports = $manager->getActiveTransports();
        $this->assertArrayHasKey('mock', $activeTransports);
        $this->assertSame($mockTransport, $activeTransports['mock']);

        // Remove the transport
        $manager->removeTransport('mock');

        $activeTransports = $manager->getActiveTransports();
        $this->assertArrayNotHasKey('mock', $activeTransports);
    }

    #[Test]
    public function it_handles_transport_refresh_operations()
    {
        $manager = $this->app->make(TransportManager::class);

        // Create initial transport
        $transport1 = $manager->driver('http');
        $transport1->start();

        $this->assertTrue($transport1->isConnected());

        // Refresh transport
        $transport2 = $manager->refresh('http');

        // Should be a new instance
        $this->assertNotSame($transport1, $transport2);
        $this->assertInstanceOf(HttpTransport::class, $transport2);

        // Old transport should be stopped
        $this->assertFalse($transport1->isConnected());
    }
}
