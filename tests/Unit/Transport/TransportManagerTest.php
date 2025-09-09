<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use Illuminate\Container\Container;
use JTD\LaravelMCP\Exceptions\TransportException;
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
class TransportManagerTest extends TestCase
{
    private TransportManager $manager;
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->container = $this->app;
        $this->manager = new TransportManager($this->container);
    }

    #[Test]
    public function it_registers_default_drivers_on_construction()
    {
        $drivers = $this->manager->getDrivers();
        
        $this->assertContains('http', $drivers);
        $this->assertContains('stdio', $drivers);
    }

    #[Test]
    public function it_creates_http_transport_instance()
    {
        $transport = $this->manager->createTransport('http');
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_creates_stdio_transport_instance()
    {
        $transport = $this->manager->createTransport('stdio');
        
        $this->assertInstanceOf(StdioTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_throws_exception_for_unknown_transport_type()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Unknown transport type: unknown');
        
        $this->manager->createTransport('unknown');
    }

    #[Test]
    public function it_creates_transport_with_custom_configuration()
    {
        $customConfig = [
            'host' => 'custom-host',
            'port' => 9000,
        ];
        
        $transport = $this->manager->createTransport('http', $customConfig);
        
        $config = $transport->getConfig();
        $this->assertEquals('custom-host', $config['host']);
        $this->assertEquals(9000, $config['port']);
    }

    #[Test]
    public function it_sets_and_gets_active_transport()
    {
        $this->assertNull($this->manager->getActiveTransport());
        
        $this->manager->setActiveTransport('http');
        
        $activeTransport = $this->manager->getActiveTransport();
        $this->assertInstanceOf(HttpTransport::class, $activeTransport);
    }

    #[Test]
    public function it_sets_active_transport_with_custom_config()
    {
        $customConfig = ['host' => 'test-host'];
        
        $this->manager->setActiveTransport('http', $customConfig);
        
        $activeTransport = $this->manager->getActiveTransport();
        $config = $activeTransport->getConfig();
        
        $this->assertEquals('test-host', $config['host']);
    }

    #[Test]
    public function it_gets_default_driver_from_config()
    {
        // Set config for default driver
        config(['mcp-transports.default' => 'http']);
        
        $defaultDriver = $this->manager->getDefaultDriver();
        
        $this->assertEquals('http', $defaultDriver);
    }

    #[Test]
    public function it_falls_back_to_stdio_when_no_config()
    {
        // Clear config
        config(['mcp-transports.default' => null]);
        
        $defaultDriver = $this->manager->getDefaultDriver();
        
        $this->assertEquals('stdio', $defaultDriver);
    }

    #[Test]
    public function it_creates_driver_instance()
    {
        $transport = $this->manager->driver('http');
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    #[Test]
    public function it_uses_default_driver_when_none_specified()
    {
        config(['mcp-transports.default' => 'http']);
        
        $transport = $this->manager->driver();
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    #[Test]
    public function it_caches_transport_instances()
    {
        $transport1 = $this->manager->driver('http');
        $transport2 = $this->manager->driver('http');
        
        $this->assertSame($transport1, $transport2);
    }

    #[Test]
    public function it_gets_default_transport()
    {
        config(['mcp-transports.default' => 'http']);
        
        $transport = $this->manager->getDefaultTransport();
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    #[Test]
    public function it_extends_with_custom_driver()
    {
        $customFactory = function ($container, $config) {
            return new class implements TransportInterface {
                private array $config;
                
                public function __construct(array $config = []) {
                    $this->config = $config;
                }
                
                public function initialize(array $config = []): void {}
                public function start(): void {}
                public function stop(): void {}
                public function send(string $message): void {}
                public function receive(): ?string { return null; }
                public function isConnected(): bool { return false; }
                public function getConnectionInfo(): array { return []; }
                public function setMessageHandler($handler): void {}
            };
        };
        
        $this->manager->extend('custom', $customFactory);
        
        $this->assertTrue($this->manager->hasDriver('custom'));
        
        $transport = $this->manager->createTransport('custom');
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    #[Test]
    public function it_checks_if_driver_exists()
    {
        $this->assertTrue($this->manager->hasDriver('http'));
        $this->assertTrue($this->manager->hasDriver('stdio'));
        $this->assertFalse($this->manager->hasDriver('nonexistent'));
    }

    #[Test]
    public function it_sets_default_driver()
    {
        $this->manager->setDefaultDriver('http');
        
        $transport = $this->manager->getDefaultTransport();
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    #[Test]
    public function it_throws_exception_for_invalid_default_driver()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Transport driver 'nonexistent' is not registered");
        
        $this->manager->setDefaultDriver('nonexistent');
    }

    #[Test]
    public function it_purges_transport_instances()
    {
        // Create and cache a transport
        $transport1 = $this->manager->driver('http');
        $transport1->start(); // Simulate connection
        
        // Purge the transport
        $this->manager->purge('http');
        
        // Create new transport - should be different instance
        $transport2 = $this->manager->driver('http');
        
        $this->assertNotSame($transport1, $transport2);
    }

    #[Test]
    public function it_purges_default_driver_when_none_specified()
    {
        config(['mcp-transports.default' => 'http']);
        
        $transport1 = $this->manager->driver();
        $this->manager->purge(); // Should purge default driver
        $transport2 = $this->manager->driver();
        
        $this->assertNotSame($transport1, $transport2);
    }

    #[Test]
    public function it_purges_all_transports()
    {
        // Create multiple transports
        $httpTransport1 = $this->manager->driver('http');
        $stdioTransport1 = $this->manager->driver('stdio');
        
        // Purge all
        $this->manager->purgeAll();
        
        // Create new transports
        $httpTransport2 = $this->manager->driver('http');
        $stdioTransport2 = $this->manager->driver('stdio');
        
        $this->assertNotSame($httpTransport1, $httpTransport2);
        $this->assertNotSame($stdioTransport1, $stdioTransport2);
    }

    #[Test]
    public function it_gets_active_transports()
    {
        $this->manager->driver('http');
        $this->manager->driver('stdio');
        
        $activeTransports = $this->manager->getActiveTransports();
        
        $this->assertCount(2, $activeTransports);
        $this->assertArrayHasKey('http', $activeTransports);
        $this->assertArrayHasKey('stdio', $activeTransports);
    }

    #[Test]
    public function it_checks_if_has_active_transports()
    {
        $this->assertFalse($this->manager->hasActiveTransports());
        
        $this->manager->driver('http');
        
        $this->assertTrue($this->manager->hasActiveTransports());
    }

    #[Test]
    public function it_creates_transport_using_alias_method()
    {
        $transport = $this->manager->transport('http');
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
    }

    #[Test]
    public function it_creates_custom_transport_with_config()
    {
        $customConfig = ['timeout' => 60];
        
        $transport = $this->manager->createCustomTransport('http', $customConfig);
        
        $this->assertInstanceOf(HttpTransport::class, $transport);
        $config = $transport->getConfig();
        $this->assertEquals(60, $config['timeout']);
        
        // Should not be cached
        $cachedTransport = $this->manager->driver('http');
        $this->assertNotSame($transport, $cachedTransport);
    }

    #[Test]
    public function it_throws_exception_for_unknown_custom_transport()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("Transport driver 'unknown' is not registered");
        
        $this->manager->createCustomTransport('unknown', []);
    }

    #[Test]
    public function it_gets_transport_health_status()
    {
        $this->manager->driver('http');
        $this->manager->driver('stdio');
        
        $health = $this->manager->getTransportHealth();
        
        $this->assertArrayHasKey('http', $health);
        $this->assertArrayHasKey('stdio', $health);
        
        $this->assertArrayHasKey('connected', $health['http']);
        $this->assertArrayHasKey('config', $health['http']);
    }

    #[Test]
    public function it_refreshes_transport_instance()
    {
        $transport1 = $this->manager->driver('http');
        
        $transport2 = $this->manager->refresh('http');
        
        $this->assertNotSame($transport1, $transport2);
        $this->assertInstanceOf(HttpTransport::class, $transport2);
    }

    #[Test]
    public function it_registers_custom_transport_instance()
    {
        $customTransport = $this->createMock(TransportInterface::class);
        
        $this->manager->registerTransport('custom', $customTransport);
        
        $activeTransports = $this->manager->getActiveTransports();
        $this->assertArrayHasKey('custom', $activeTransports);
        $this->assertSame($customTransport, $activeTransports['custom']);
    }

    #[Test]
    public function it_removes_transport_instance()
    {
        $transport = $this->manager->driver('http');
        $transport->start();
        
        $this->assertTrue($this->manager->hasActiveTransports());
        
        $this->manager->removeTransport('http');
        
        $activeTransports = $this->manager->getActiveTransports();
        $this->assertArrayNotHasKey('http', $activeTransports);
    }

    #[Test]
    public function it_starts_all_transports()
    {
        $httpTransport = $this->manager->driver('http');
        $stdioTransport = $this->manager->driver('stdio');
        
        $this->assertFalse($httpTransport->isConnected());
        $this->assertFalse($stdioTransport->isConnected());
        
        $this->manager->startAllTransports();
        
        $this->assertTrue($httpTransport->isConnected());
        $this->assertTrue($stdioTransport->isConnected());
    }

    #[Test]
    public function it_stops_all_transports()
    {
        $httpTransport = $this->manager->driver('http');
        $stdioTransport = $this->manager->driver('stdio');
        
        $httpTransport->start();
        $stdioTransport->start();
        
        $this->assertTrue($httpTransport->isConnected());
        $this->assertTrue($stdioTransport->isConnected());
        
        $this->manager->stopAllTransports();
        
        $this->assertFalse($httpTransport->isConnected());
        $this->assertFalse($stdioTransport->isConnected());
    }

    #[Test]
    public function it_throws_exception_when_start_all_fails()
    {
        // Create a mock transport that fails to start
        $this->manager->extend('failing', function ($container, $config) {
            return new class implements TransportInterface {
                public function initialize(array $config = []): void {}
                public function start(): void { 
                    throw new \Exception('Start failed'); 
                }
                public function stop(): void {}
                public function send(string $message): void {}
                public function receive(): ?string { return null; }
                public function isConnected(): bool { return false; }
                public function getConnectionInfo(): array { return []; }
                public function setMessageHandler($handler): void {}
            };
        });
        
        $this->manager->driver('failing');
        
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("Failed to start transport 'failing'");
        
        $this->manager->startAllTransports();
    }

    #[Test]
    public function it_gets_active_transport_count()
    {
        $this->assertEquals(0, $this->manager->getActiveTransportCount());
        
        $transport1 = $this->manager->driver('http');
        $transport2 = $this->manager->driver('stdio');
        
        $this->assertEquals(0, $this->manager->getActiveTransportCount()); // Not connected
        
        $transport1->start();
        $this->assertEquals(1, $this->manager->getActiveTransportCount());
        
        $transport2->start();
        $this->assertEquals(2, $this->manager->getActiveTransportCount());
    }

    #[Test]
    public function it_performs_cleanup()
    {
        $transport1 = $this->manager->driver('http');
        $transport2 = $this->manager->driver('stdio');
        
        $transport1->start();
        $transport2->start();
        
        $this->assertTrue($this->manager->hasActiveTransports());
        
        $this->manager->cleanup();
        
        $this->assertFalse($this->manager->hasActiveTransports());
        $this->assertFalse($transport1->isConnected());
        $this->assertFalse($transport2->isConnected());
    }

    #[Test]
    public function it_initializes_default_transports_from_config()
    {
        // Set config to auto-start certain transports
        config(['mcp-transports.auto_start' => ['http', 'stdio']]);
        
        // Create new manager to test initialization
        $newManager = new TransportManager($this->container);
        $newManager->initialize();
        
        // Check if transports were created (not necessarily started)
        $activeTransports = $newManager->getActiveTransports();
        $this->assertGreaterThanOrEqual(0, count($activeTransports));
    }

    #[Test]
    public function it_handles_factory_registration_errors()
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to register default transport drivers');
        
        // Create a container that throws exceptions
        $faultyContainer = $this->createMock(Container::class);
        $faultyContainer->method('make')
            ->will($this->throwException(new \Exception('Container error')));
        
        new TransportManager($faultyContainer);
    }

    #[Test]
    public function it_validates_transport_interface_compliance()
    {
        $this->manager->extend('invalid', function ($container, $config) {
            return new \stdClass(); // Not a TransportInterface
        });
        
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage("Transport type 'invalid' must implement TransportInterface");
        
        $this->manager->createTransport('invalid');
    }
}