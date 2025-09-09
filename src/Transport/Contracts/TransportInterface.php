<?php

namespace JTD\LaravelMCP\Transport\Contracts;

/**
 * Interface for MCP transport implementations.
 *
 * This interface defines the contract for all transport layers that handle
 * communication between MCP clients and the Laravel MCP server. Implementations
 * can handle various transport methods such as HTTP, stdio, websockets, etc.
 */
interface TransportInterface
{
    /**
     * Initialize the transport layer.
     *
     * @param  array  $config  Transport-specific configuration options
     */
    public function initialize(array $config = []): void;

    /**
     * Start the transport.
     */
    public function start(): void;

    /**
     * Stop the transport.
     */
    public function stop(): void;

    /**
     * Send a message to the client.
     *
     * @param  string  $message  The message to send
     */
    public function send(string $message): void;

    /**
     * Receive a message from the client.
     *
     * @return string|null The received message, or null if no message available
     */
    public function receive(): ?string;

    /**
     * Check if the transport is currently connected/active.
     */
    public function isConnected(): bool;

    /**
     * Get connection information.
     *
     * @return array Connection information
     */
    public function getConnectionInfo(): array;

    /**
     * Set the message handler for processing received messages.
     */
    public function setMessageHandler(MessageHandlerInterface $handler): void;
}
