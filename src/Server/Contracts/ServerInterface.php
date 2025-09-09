<?php

namespace JTD\LaravelMCP\Server\Contracts;

use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

interface ServerInterface
{
    /**
     * Initialize the MCP server.
     */
    public function initialize(array $clientInfo = []): array;

    /**
     * Start the MCP server.
     */
    public function start(): void;

    /**
     * Stop the MCP server.
     */
    public function stop(): void;

    /**
     * Restart the MCP server.
     */
    public function restart(): void;

    /**
     * Check if the server is running.
     */
    public function isRunning(): bool;

    /**
     * Check if the server is initialized.
     */
    public function isInitialized(): bool;

    /**
     * Get server status information.
     */
    public function getStatus(): array;

    /**
     * Get server health check information.
     */
    public function getHealth(): array;

    /**
     * Get server information.
     */
    public function getServerInfo(): array;

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array;

    /**
     * Set server configuration.
     */
    public function setConfiguration(array $config): void;

    /**
     * Get server configuration.
     */
    public function getConfiguration(): array;

    /**
     * Register a transport with the server.
     */
    public function registerTransport(string $name, TransportInterface $transport): void;

    /**
     * Remove a transport from the server.
     */
    public function removeTransport(string $name): void;

    /**
     * Get all registered transports.
     */
    public function getTransports(): array;

    /**
     * Handle graceful shutdown.
     */
    public function shutdown(): void;

    /**
     * Get server uptime in seconds.
     */
    public function getUptime(): int;

    /**
     * Get server performance metrics.
     */
    public function getMetrics(): array;
}
