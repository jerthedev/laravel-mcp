<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Container\Container;
use InvalidArgumentException;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Transport manager for MCP transport implementations.
 *
 * This class manages different transport implementations (HTTP, Stdio)
 * and provides a factory interface for creating transport instances.
 */
class TransportManager
{
    /**
     * Registered transport drivers.
     */
    protected array $drivers = [];

    /**
     * Transport instances.
     */
    protected array $transports = [];

    /**
     * Default transport driver.
     */
    protected string $defaultDriver = 'stdio';

    /**
     * Whether the default driver was explicitly set.
     */
    protected bool $defaultDriverExplicitlySet = false;

    /**
     * Active transport instance.
     */
    protected ?TransportInterface $activeTransport = null;

    /**
     * Laravel container instance.
     */
    protected Container $container;

    /**
     * Create a new transport manager instance.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;

        try {
            $this->registerDefaultDrivers();
        } catch (\Throwable $e) {
            throw new TransportException(
                'Failed to register default transport drivers: '.$e->getMessage(),
                0,
                null,
                ['original_error' => $e->getMessage()],
                [],
                $e
            );
        }
    }

    /**
     * Set the active transport.
     */
    public function setActiveTransport(string $type, array $config = []): void
    {
        $this->activeTransport = $this->createTransport($type, $config);
    }

    /**
     * Get the active transport.
     */
    public function getActiveTransport(): ?TransportInterface
    {
        return $this->activeTransport;
    }

    /**
     * Create a transport instance.
     */
    public function createTransport(string $type, array $config = []): TransportInterface
    {
        if (! isset($this->drivers[$type])) {
            throw new TransportException("Unknown transport type: $type");
        }

        $factory = $this->drivers[$type];
        $transport = $factory($this->container, array_merge($this->getDriverConfig($type), $config));

        if (! $transport instanceof TransportInterface) {
            throw new TransportException("Transport type '$type' must implement TransportInterface");
        }

        return $transport;
    }

    /**
     * Get a transport instance.
     */
    public function driver(?string $driver = null): TransportInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (! isset($this->transports[$driver])) {
            $this->transports[$driver] = $this->createTransportForDriver($driver);
        }

        return $this->transports[$driver];
    }

    /**
     * Get the default transport instance.
     */
    public function getDefaultTransport(): TransportInterface
    {
        return $this->driver();
    }

    /**
     * Set the default transport driver.
     */
    public function setDefaultDriver(string $driver): void
    {
        if (! $this->hasDriver($driver)) {
            throw new InvalidArgumentException("Transport driver '{$driver}' is not registered");
        }

        $this->defaultDriver = $driver;
        $this->defaultDriverExplicitlySet = true;
    }

    /**
     * Get the default transport driver name.
     */
    public function getDefaultDriver(): string
    {
        // If a driver was explicitly set via setDefaultDriver, use that
        // Otherwise, fall back to config, then to the default
        if ($this->defaultDriverExplicitlySet) {
            return $this->defaultDriver;
        }

        return config('mcp-transports.default') ?? $this->defaultDriver;
    }

    /**
     * Register a transport driver.
     */
    public function extend(string $driver, \Closure $callback): void
    {
        $this->drivers[$driver] = $callback;
    }

    /**
     * Check if a driver is registered.
     */
    public function hasDriver(string $driver): bool
    {
        return isset($this->drivers[$driver]);
    }

    /**
     * Get all registered drivers.
     */
    public function getDrivers(): array
    {
        return array_keys($this->drivers);
    }

    /**
     * Create a transport instance for a driver.
     */
    protected function createTransportForDriver(string $driver): TransportInterface
    {
        if (! $this->hasDriver($driver)) {
            throw new TransportException("Transport driver '{$driver}' is not registered");
        }

        $factory = $this->drivers[$driver];
        $transport = $factory($this->container, $this->getDriverConfig($driver));

        if (! $transport instanceof TransportInterface) {
            throw new TransportException(
                "Transport driver '{$driver}' must return a TransportInterface instance"
            );
        }

        return $transport;
    }

    /**
     * Get configuration for a driver.
     */
    protected function getDriverConfig(string $driver): array
    {
        return config("mcp-transports.transports.{$driver}", []);
    }

    /**
     * Register default transport drivers.
     */
    protected function registerDefaultDrivers(): void
    {
        // Validate that the container can resolve the transport classes
        try {
            $this->container->make(HttpTransport::class);
            $this->container->make(StdioTransport::class);
        } catch (\Throwable $e) {
            throw new \Exception('Failed to resolve transport classes: '.$e->getMessage(), 0, $e);
        }

        // Register HTTP transport driver
        $this->extend('http', function (Container $container, array $config) {
            $transport = $container->make(HttpTransport::class);
            $transport->initialize($config);

            return $transport;
        });

        // Register Stdio transport driver
        $this->extend('stdio', function (Container $container, array $config) {
            $transport = $container->make(StdioTransport::class);
            $transport->initialize($config);

            return $transport;
        });
    }

    /**
     * Purge a transport instance.
     */
    public function purge(?string $driver = null): void
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (isset($this->transports[$driver])) {
            $this->transports[$driver]->stop();
            unset($this->transports[$driver]);
        }
    }

    /**
     * Purge all transport instances.
     */
    public function purgeAll(): void
    {
        foreach (array_keys($this->transports) as $driver) {
            $this->purge($driver);
        }
    }

    /**
     * Get all active transport instances.
     */
    public function getActiveTransports(): array
    {
        return $this->transports;
    }

    /**
     * Check if any transports are active.
     */
    public function hasActiveTransports(): bool
    {
        return ! empty($this->transports);
    }

    /**
     * Get transport instance by name (alias for driver method).
     */
    public function transport(string $name): TransportInterface
    {
        return $this->driver($name);
    }

    /**
     * Create a transport with custom configuration.
     */
    public function createCustomTransport(string $driver, array $config): TransportInterface
    {
        if (! $this->hasDriver($driver)) {
            throw new TransportException("Transport driver '{$driver}' is not registered");
        }

        $factory = $this->drivers[$driver];
        $transport = $factory($this->container, $config);

        if (! $transport instanceof TransportInterface) {
            throw new TransportException(
                "Transport driver '{$driver}' must return a TransportInterface instance"
            );
        }

        return $transport;
    }

    /**
     * Get transport health status.
     */
    public function getTransportHealth(): array
    {
        $health = [];

        foreach ($this->transports as $driver => $transport) {
            $health[$driver] = [
                'connected' => $transport->isConnected(),
                'config' => $transport->getConfig(),
            ];
        }

        return $health;
    }

    /**
     * Force recreation of a transport instance.
     */
    public function refresh(?string $driver = null): TransportInterface
    {
        $driver = $driver ?: $this->getDefaultDriver();

        $this->purge($driver);

        return $this->driver($driver);
    }

    /**
     * Register a transport instance with a custom name.
     */
    public function registerTransport(string $name, TransportInterface $transport): void
    {
        $this->transports[$name] = $transport;
    }

    /**
     * Remove a transport instance.
     */
    public function removeTransport(string $name): void
    {
        if (isset($this->transports[$name])) {
            $this->transports[$name]->stop();
            unset($this->transports[$name]);
        }
    }

    /**
     * Start all registered transports.
     */
    public function startAllTransports(): void
    {
        foreach ($this->transports as $name => $transport) {
            try {
                if (! $transport->isConnected()) {
                    $transport->start();
                }
            } catch (\Throwable $e) {
                throw new TransportException("Failed to start transport '{$name}': {$e->getMessage()}", 0, $e);
            }
        }
    }

    /**
     * Stop all registered transports.
     */
    public function stopAllTransports(): void
    {
        foreach ($this->transports as $name => $transport) {
            try {
                if ($transport->isConnected()) {
                    $transport->stop();
                }
            } catch (\Throwable $e) {
                // Log but don't throw during shutdown
                error_log("Error stopping transport '{$name}': {$e->getMessage()}");
            }
        }
    }

    /**
     * Get count of active transports.
     */
    public function getActiveTransportCount(): int
    {
        return count(array_filter($this->transports, fn ($transport) => $transport->isConnected()));
    }

    /**
     * Cleanup resources.
     */
    public function cleanup(): void
    {
        $this->stopAllTransports();
        $this->transports = [];
    }

    /**
     * Initialize the transport manager.
     */
    public function initialize(): void
    {
        // Initialize default transports based on configuration
        $defaultTransports = config('mcp-transports.auto_start', []);

        foreach ($defaultTransports as $driver) {
            if ($this->hasDriver($driver)) {
                try {
                    $this->driver($driver);
                } catch (\Throwable $e) {
                    error_log("Failed to initialize transport '{$driver}': {$e->getMessage()}");
                }
            }
        }
    }
}
