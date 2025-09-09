<?php

namespace JTD\LaravelMCP\Protocol\Contracts;

/**
 * Interface for JSON-RPC 2.0 message handling.
 *
 * This interface defines the contract for handling JSON-RPC 2.0 protocol
 * messages as required by the MCP specification. It handles request/response
 * patterns, notifications, and error handling according to JSON-RPC 2.0 spec.
 */
interface JsonRpcHandlerInterface
{
    /**
     * Process a JSON-RPC 2.0 request message.
     *
     * @param  array  $request  The JSON-RPC request
     * @return array The JSON-RPC response
     */
    public function handleRequest(array $request): array;

    /**
     * Process a JSON-RPC 2.0 notification message.
     *
     * @param  array  $notification  The JSON-RPC notification
     */
    public function handleNotification(array $notification): void;

    /**
     * Process a JSON-RPC 2.0 response message.
     *
     * @param  array  $response  The JSON-RPC response
     */
    public function handleResponse(array $response): void;

    /**
     * Create a JSON-RPC 2.0 request message.
     *
     * @param  string  $method  The method name
     * @param  array  $params  The method parameters
     * @param  string|int|null  $id  The request ID (null for notifications)
     */
    public function createRequest(string $method, array $params = [], $id = null): array;

    /**
     * Create a JSON-RPC 2.0 success response message.
     *
     * @param  mixed  $result  The result data
     * @param  string|int  $id  The request ID
     */
    public function createSuccessResponse($result, $id): array;

    /**
     * Create a JSON-RPC 2.0 error response message.
     *
     * @param  int  $code  The error code
     * @param  string  $message  The error message
     * @param  mixed  $data  Additional error data
     * @param  string|int  $id  The request ID
     */
    public function createErrorResponse(int $code, string $message, $data = null, $id = null): array;

    /**
     * Validate a JSON-RPC 2.0 message format.
     *
     * @param  array  $message  The message to validate
     */
    public function validateMessage(array $message): bool;

    /**
     * Check if a message is a valid JSON-RPC 2.0 request.
     *
     * @param  array  $message  The message to check
     */
    public function isRequest(array $message): bool;

    /**
     * Check if a message is a valid JSON-RPC 2.0 notification.
     *
     * @param  array  $message  The message to check
     */
    public function isNotification(array $message): bool;

    /**
     * Check if a message is a valid JSON-RPC 2.0 response.
     *
     * @param  array  $message  The message to check
     */
    public function isResponse(array $message): bool;

    /**
     * Register a request handler.
     *
     * @param  string  $method  The method name
     * @param  callable  $handler  The handler function
     */
    public function onRequest(string $method, callable $handler): void;

    /**
     * Register a notification handler.
     *
     * @param  string  $method  The method name
     * @param  callable  $handler  The handler function
     */
    public function onNotification(string $method, callable $handler): void;

    /**
     * Register a response handler.
     *
     * @param  string|int  $id  The request ID
     * @param  callable  $handler  The handler function
     */
    public function onResponse($id, callable $handler): void;

    /**
     * Remove a request handler.
     *
     * @param  string  $method  The method name
     */
    public function removeRequestHandler(string $method): void;

    /**
     * Remove a notification handler.
     *
     * @param  string  $method  The method name
     */
    public function removeNotificationHandler(string $method): void;

    /**
     * Get all registered request methods.
     */
    public function getRequestMethods(): array;

    /**
     * Get all registered notification methods.
     */
    public function getNotificationMethods(): array;

    /**
     * Check if a request handler is registered.
     *
     * @param  string  $method  The method name
     */
    public function hasRequestHandler(string $method): bool;

    /**
     * Check if a notification handler is registered.
     *
     * @param  string  $method  The method name
     */
    public function hasNotificationHandler(string $method): bool;

    /**
     * Set debug mode.
     *
     * @param  bool  $debug  Debug mode flag
     */
    public function setDebug(bool $debug): void;

    /**
     * Check if debug mode is enabled.
     */
    public function isDebug(): bool;
}
