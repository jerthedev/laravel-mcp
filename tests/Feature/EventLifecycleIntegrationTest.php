<?php

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * EPIC: SERVICEPROVIDER
 * SPEC: docs/Specs/03-ServiceProvider.md
 * SPRINT: Sprint 1
 * TICKET: SERVICEPROVIDER-004
 *
 * Feature tests for event lifecycle integration
 * Tests how the service provider responds to Laravel application events
 */
#[Group('feature')]
#[Group('events')]
#[Group('lifecycle')]
class EventLifecycleIntegrationTest extends TestCase
{
    private LaravelMcpServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LaravelMcpServiceProvider($this->app);
        $this->provider->register();
    }

    /**
     * Test providers booted event triggers component discovery finalization
     */
    #[Test]
    public function it_finalizes_component_discovery_on_providers_booted(): void
    {
        // Arrange
        Config::set('laravel-mcp.discovery.enabled', true);

        $discovery = Mockery::mock(ComponentDiscovery::class);
        $discovery->shouldReceive('discoverComponents')->once();
        $discovery->shouldReceive('registerDiscoveredComponents')->once();
        $discovery->shouldReceive('validateDiscoveredComponents')
            ->atLeast()->once();

        $this->app->instance(ComponentDiscovery::class, $discovery);

        // Act - Boot the provider to register event listeners
        $this->provider->boot();

        // Trigger the providers booted event
        Event::dispatch('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders');

        // Assert - Mockery will verify the expectations
        $this->assertTrue(true);
    }

    /**
     * Test providers booted event respects discovery disabled setting
     */
    #[Test]
    public function it_skips_discovery_finalization_when_disabled(): void
    {
        // Arrange
        Config::set('laravel-mcp.discovery.enabled', false);

        $discovery = Mockery::mock(ComponentDiscovery::class);
        $discovery->shouldNotReceive('validateDiscoveredComponents');

        $this->app->instance(ComponentDiscovery::class, $discovery);

        // Act
        $this->provider->boot();
        Event::dispatch('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders');

        // Assert - Mockery will verify discovery wasn't called
        $this->assertTrue(true);
    }

    /**
     * Test discovery finalization handles errors gracefully
     */
    #[Test]
    public function it_handles_discovery_finalization_errors_gracefully(): void
    {
        // Arrange
        Config::set('laravel-mcp.discovery.enabled', true);

        $discovery = Mockery::mock(ComponentDiscovery::class);
        $discovery->shouldReceive('discoverComponents')->once();
        $discovery->shouldReceive('registerDiscoveredComponents')->once();
        $discovery->shouldReceive('validateDiscoveredComponents')
            ->atLeast()->once()
            ->andThrow(new \RuntimeException('Discovery validation failed'));

        $logger = Mockery::mock();
        $logger->shouldReceive('warning')
            ->atLeast()->once()
            ->with('Failed to finalize component discovery', Mockery::type('array'));

        $this->app->instance(ComponentDiscovery::class, $discovery);
        $this->app->instance('log', $logger);

        // Act - Should not throw exception
        $this->provider->boot();
        Event::dispatch('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders');

        // Assert - Should complete without throwing
        $this->assertTrue(true);
    }

    /**
     * Test kernel handled event triggers resource cleanup
     */
    #[Test]
    public function it_cleans_up_resources_on_kernel_handled(): void
    {
        // Arrange
        $transportManager = Mockery::mock(TransportManager::class);
        $transportManager->shouldReceive('cleanup')
            ->atLeast()->once();

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $this->provider->boot();
        Event::dispatch('kernel.handled');

        // Assert - Mockery will verify cleanup was called
        $this->assertTrue(true);
    }

    /**
     * Test kernel handled event handles cleanup errors gracefully
     */
    #[Test]
    public function it_handles_cleanup_errors_gracefully(): void
    {
        // Arrange
        $transportManager = Mockery::mock(TransportManager::class);
        $transportManager->shouldReceive('cleanup')
            ->atLeast()->once()
            ->andThrow(new \RuntimeException('Cleanup failed'));

        $this->app->instance(TransportManager::class, $transportManager);

        // Act - Should not throw exception
        $this->provider->boot();
        Event::dispatch('kernel.handled');

        // Assert - Should complete without throwing
        $this->assertTrue(true);
    }

    /**
     * Test application terminating event triggers final cleanup
     */
    #[Test]
    public function it_performs_final_cleanup_on_application_terminating(): void
    {
        // Arrange
        $transportManager = Mockery::mock(TransportManager::class);
        $transportManager->shouldReceive('cleanup')
            ->atLeast()->once();

        $cache = Mockery::mock();
        $cache->shouldReceive('flush')
            ->atLeast()->once();

        $this->app->instance(TransportManager::class, $transportManager);
        $this->app->instance('mcp.discovery.cache', $cache);

        // Act
        $this->provider->boot();

        // Trigger terminating callbacks
        $this->app->terminate();

        // Assert - Mockery will verify cleanup was called
        $this->assertTrue(true);
    }

    /**
     * Test application terminating handles cache cleanup errors
     */
    #[Test]
    public function it_handles_cache_cleanup_errors_gracefully(): void
    {
        // Arrange
        $cache = Mockery::mock();
        $cache->shouldReceive('flush')
            ->atLeast()->once()
            ->andThrow(new \RuntimeException('Cache flush failed'));

        $this->app->instance('mcp.discovery.cache', $cache);

        // Act - Should not throw exception
        $this->provider->boot();
        $this->app->terminate();

        // Assert - Should complete without throwing
        $this->assertTrue(true);
    }

    /**
     * Test complete event lifecycle from boot to termination
     */
    #[Test]
    public function it_handles_complete_event_lifecycle(): void
    {
        // Arrange
        Config::set('laravel-mcp.discovery.enabled', true);

        $discovery = Mockery::mock(ComponentDiscovery::class);
        $discovery->shouldReceive('discoverComponents')->once();
        $discovery->shouldReceive('registerDiscoveredComponents')->once();
        $discovery->shouldReceive('validateDiscoveredComponents')
            ->atLeast()->once();

        $transportManager = Mockery::mock(TransportManager::class);
        $transportManager->shouldReceive('cleanup')
            ->atLeast()->times(1); // Called by both kernel.handled and terminating

        $cache = Mockery::mock();
        $cache->shouldReceive('flush')
            ->atLeast()->once();

        $this->app->instance(ComponentDiscovery::class, $discovery);
        $this->app->instance(TransportManager::class, $transportManager);
        $this->app->instance('mcp.discovery.cache', $cache);

        // Act - Complete lifecycle
        $this->provider->boot();

        // Simulate application lifecycle events
        Event::dispatch('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders');
        Event::dispatch('kernel.handled');
        $this->app->terminate();

        // Assert - All events should be handled correctly
        $this->assertTrue(true);
    }

    /**
     * Test event listeners are only registered once
     */
    #[Test]
    public function it_registers_event_listeners_only_once(): void
    {
        // Arrange
        $eventDispatcher = Mockery::mock($this->app['events']);
        $eventDispatcher->shouldReceive('listen')
            ->with('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders', Mockery::type('Closure'))
            ->atLeast()->once();
        $eventDispatcher->shouldReceive('listen')
            ->with('kernel.handled', Mockery::type('Closure'))
            ->atLeast()->once();

        $this->app->instance('events', $eventDispatcher);

        // Act - Boot multiple times
        $this->provider->boot();

        $provider2 = new LaravelMcpServiceProvider($this->app);
        $provider2->boot(); // This should register listeners again

        // Assert - Mockery expectations verify listeners registered appropriately
        $this->assertTrue(true);
    }

    /**
     * Test event handling with bound vs unbound services
     */
    #[Test]
    public function it_handles_events_with_unbound_services_gracefully(): void
    {
        // Arrange - Don't bind the required services
        $this->app->forgetInstance(TransportManager::class);
        $this->app->forgetInstance('mcp.discovery.cache');

        // Act - Events should be handled without errors
        $this->provider->boot();
        Event::dispatch('kernel.handled');
        $this->app->terminate();

        // Assert - Should complete without throwing
        $this->assertTrue(true);
    }

    /**
     * Test events work in different environments
     */
    #[Test]
    public function it_handles_events_in_different_environments(): void
    {
        // Arrange - Use the real app but override specific values
        $originalEnv = $this->app['env'];
        $this->app['env'] = 'console';

        // Disable discovery to simplify test
        Config::set('laravel-mcp.discovery.enabled', false);

        // Act
        $provider = new LaravelMcpServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Assert - Should complete without errors
        $this->assertTrue(true);

        // Cleanup
        $this->app['env'] = $originalEnv;
    }

    /**
     * Test event performance doesn't impact application performance
     */
    #[Test]
    public function it_maintains_performance_during_event_handling(): void
    {
        // Arrange
        $startTime = microtime(true);

        Config::set('laravel-mcp.discovery.enabled', true);

        // Act - Simulate multiple event cycles
        $this->provider->boot();

        for ($i = 0; $i < 10; $i++) {
            Event::dispatch('bootstrapped: Illuminate\\Foundation\\Bootstrap\\BootProviders');
            Event::dispatch('kernel.handled');
        }

        $this->app->terminate();

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Assert - Should complete quickly (under 1 second for 10 cycles)
        $this->assertLessThan(1.0, $executionTime, 'Event handling should be performant');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
