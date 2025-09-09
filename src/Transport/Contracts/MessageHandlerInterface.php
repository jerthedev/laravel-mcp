<?php

namespace JTD\LaravelMCP\Transport\Contracts;

/**
 * Interface for handling MCP messages received from transport layers.
 *
 * This interface defines the contract for message handlers that process
 * incoming MCP messages from various transport implementations. Handlers
 * are responsible for routing messages to appropriate protocol handlers
 * and managing the message lifecycle.
 */
interface MessageHandlerInterface
{
    /**
     * Handle an incoming MCP message.
     *
     * @param  array  $message  The received MCP message
     * @param  TransportInterface  $transport  The transport that received the message
     * @return array|null Response message to send back, or null for no response
     */
    public function handle(array $message, TransportInterface $transport): ?array;

    /**
     * Handle a transport error.
     *
     * @param  \Throwable  $error  The error that occurred
     * @param  TransportInterface  $transport  The transport where the error occurred
     */
    public function handleError(\Throwable $error, TransportInterface $transport): void;

    /**
     * Handle transport connection establishment.
     *
     * @param  TransportInterface  $transport  The transport that connected
     */
    public function onConnect(TransportInterface $transport): void;

    /**
     * Handle transport connection closure.
     *
     * @param  TransportInterface  $transport  The transport that disconnected
     */
    public function onDisconnect(TransportInterface $transport): void;

    /**
     * Check if the handler can process a specific message type.
     *
     * @param  array  $message  The message to check
     */
    public function canHandle(array $message): bool;

    /**
     * Get supported message types by this handler.
     *
     * @return array Array of supported message types
     */
    public function getSupportedMessageTypes(): array;
}
