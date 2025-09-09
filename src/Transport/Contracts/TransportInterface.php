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
     * Start listening for incoming messages.
     */
    public function listen(): void;

    /**
     * Send a message to the client.
     *
     * @param  array  $message  The MCP message to send
     */
    public function send(array $message): void;

    /**
     * Receive a message from the client.
     *
     * @return array|null The received MCP message, or null if no message available
     */
    public function receive(): ?array;

    /**
     * Close the transport connection.
     */
    public function close(): void;

    /**
     * Check if the transport is currently connected/active.
     */
    public function isConnected(): bool;

    /**
     * Get transport-specific configuration.
     */
    public function getConfig(): array;

    /**
     * Set the message handler for processing received messages.
     */
    public function setMessageHandler(MessageHandlerInterface $handler): void;
}
