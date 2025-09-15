<?php

namespace JTD\LaravelMCP\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;

/**
 * JSON-RPC 2.0 message handler for MCP.
 *
 * This class implements JSON-RPC 2.0 protocol handling as required
 * by the MCP specification, managing requests, responses, and notifications.
 */
class JsonRpcHandler implements JsonRpcHandlerInterface
{
    /**
     * JSON-RPC version.
     */
    protected const JSONRPC_VERSION = '2.0';

    /**
     * JSON-RPC error codes.
     */
    protected const ERROR_PARSE_ERROR = -32700;

    protected const ERROR_INVALID_REQUEST = -32600;

    protected const ERROR_METHOD_NOT_FOUND = -32601;

    protected const ERROR_INVALID_PARAMS = -32602;

    protected const ERROR_INTERNAL_ERROR = -32603;

    /**
     * Request handlers.
     */
    protected array $requestHandlers = [];

    /**
     * Notification handlers.
     */
    protected array $notificationHandlers = [];

    /**
     * Response handlers.
     */
    protected array $responseHandlers = [];

    /**
     * Debug mode flag.
     */
    protected bool $debug = false;

    /**
     * Create a new JSON-RPC handler instance.
     */
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Process a JSON-RPC 2.0 request message.
     */
    public function handleRequest(array $request): array
    {
        try {
            if (! $this->isRequest($request)) {
                return $this->createErrorResponse(
                    self::ERROR_INVALID_REQUEST,
                    'Invalid request format',
                    null,
                    $request['id'] ?? null
                );
            }

            $method = $request['method'];
            $params = $request['params'] ?? [];
            $id = $request['id'];

            // Validate params structure - must be array or object (null is OK)
            if (isset($request['params']) && ! is_array($request['params']) && ! is_null($request['params'])) {
                return $this->createErrorResponse(
                    self::ERROR_INVALID_PARAMS,
                    'Invalid params: parameters must be structured values (array or object)',
                    null,
                    $id
                );
            }

            if ($this->debug) {
                Log::debug('Processing JSON-RPC request', [
                    'method' => $method,
                    'id' => $id,
                    'params' => $params,
                ]);
            }

            // Find and execute handler
            if (! isset($this->requestHandlers[$method])) {
                Log::warning('JsonRpcHandler: Method not found', [
                    'method' => $method,
                    'available_methods' => array_keys($this->requestHandlers),
                ]);
                return $this->createErrorResponse(
                    self::ERROR_METHOD_NOT_FOUND,
                    "Method '{$method}' not found",
                    null,
                    $id
                );
            }

            $handler = $this->requestHandlers[$method];

            try {
                $result = $handler($params, $request);

                return $this->createSuccessResponse($result, $id);
            } catch (\InvalidArgumentException $e) {
                return $this->createErrorResponse(
                    self::ERROR_INVALID_PARAMS,
                    $e->getMessage(),
                    null,
                    $id
                );
            } catch (ProtocolException $e) {
                // Handle protocol exceptions with their specific error codes
                Log::warning('Protocol error in request handler', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                return $this->createErrorResponse(
                    $e->getCode(),
                    $e->getMessage(),
                    $e->getData(),
                    $id
                );
            } catch (\Throwable $e) {
                Log::error('Request handler error', [
                    'method' => $method,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return $this->createErrorResponse(
                    self::ERROR_INTERNAL_ERROR,
                    'Internal error',
                    $this->debug ? ['message' => $e->getMessage()] : null,
                    $id
                );
            }

        } catch (\Throwable $e) {
            Log::error('JSON-RPC request processing error', [
                'error' => $e->getMessage(),
                'request' => $request,
            ]);

            return $this->createErrorResponse(
                self::ERROR_INTERNAL_ERROR,
                'Internal error',
                null,
                $request['id'] ?? null
            );
        }
    }

    /**
     * Process a JSON-RPC 2.0 notification message.
     */
    public function handleNotification(array $notification): void
    {
        try {
            if (! $this->isNotification($notification)) {
                Log::warning('Invalid notification format', ['notification' => $notification]);

                return;
            }

            $method = $notification['method'];
            $params = $notification['params'] ?? [];

            if ($this->debug) {
                Log::debug('Processing JSON-RPC notification', [
                    'method' => $method,
                    'params' => $params,
                ]);
            }

            // Find and execute handler
            if (isset($this->notificationHandlers[$method])) {
                $handler = $this->notificationHandlers[$method];
                $handler($params, $notification);

                // Dispatch NotificationSent event
                $notificationData = [
                    'id' => uniqid('notif_', true),
                    'type' => $method,
                    'params' => $params,
                    'timestamp' => now()->toISOString(),
                ];

                event(new \JTD\LaravelMCP\Events\NotificationSent($notificationData, 'http-client'));
            } else {
                Log::info("No handler for notification method: {$method}");
            }

        } catch (\Throwable $e) {
            Log::error('Notification handler error', [
                'method' => $notification['method'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Process a JSON-RPC 2.0 response message.
     */
    public function handleResponse(array $response): void
    {
        try {
            if (! $this->isResponse($response)) {
                Log::warning('Invalid response format', ['response' => $response]);

                return;
            }

            $id = $response['id'];

            if ($this->debug) {
                Log::debug('Processing JSON-RPC response', [
                    'id' => $id,
                    'has_result' => isset($response['result']),
                    'has_error' => isset($response['error']),
                ]);
            }

            // Find and execute response handler
            if (isset($this->responseHandlers[$id])) {
                $handler = $this->responseHandlers[$id];
                $handler($response);

                // Remove handler after processing
                unset($this->responseHandlers[$id]);
            }

        } catch (\Throwable $e) {
            Log::error('Response handler error', [
                'id' => $response['id'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create a JSON-RPC 2.0 request message.
     */
    public function createRequest(string $method, array $params = [], $id = null): array
    {
        $request = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'method' => $method,
        ];

        if (! empty($params)) {
            $request['params'] = $params;
        }

        // If id is null, it's a notification
        if ($id !== null) {
            $request['id'] = $id;
        }

        return $request;
    }

    /**
     * Create a JSON-RPC 2.0 success response message.
     */
    public function createSuccessResponse($result, $id): array
    {
        return [
            'jsonrpc' => self::JSONRPC_VERSION,
            'result' => $result,
            'id' => $id,
        ];
    }

    /**
     * Create a JSON-RPC 2.0 error response message.
     */
    public function createErrorResponse(int $code, string $message, $data = null, $id = null): array
    {
        $error = [
            'jsonrpc' => self::JSONRPC_VERSION,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];

        if ($data !== null) {
            $error['error']['data'] = $data;
        }

        return $error;
    }

    /**
     * Validate a JSON-RPC 2.0 message format.
     */
    public function validateMessage(array $message): bool
    {
        // Must have jsonrpc field with value "2.0"
        if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== self::JSONRPC_VERSION) {
            return false;
        }

        return $this->isRequest($message) ||
               $this->isNotification($message) ||
               $this->isResponse($message);
    }

    /**
     * Check if a message is a valid JSON-RPC 2.0 request.
     */
    public function isRequest(array $message): bool
    {
        return isset($message['jsonrpc'], $message['method'], $message['id']) &&
               $message['jsonrpc'] === self::JSONRPC_VERSION &&
               is_string($message['method']) &&
               $message['method'] !== '';
    }

    /**
     * Check if a message is a valid JSON-RPC 2.0 notification.
     */
    public function isNotification(array $message): bool
    {
        return isset($message['jsonrpc'], $message['method']) &&
               ! isset($message['id']) &&
               $message['jsonrpc'] === self::JSONRPC_VERSION &&
               is_string($message['method']) &&
               $message['method'] !== '';
    }

    /**
     * Check if a message is a valid JSON-RPC 2.0 response.
     */
    public function isResponse(array $message): bool
    {
        return isset($message['jsonrpc'], $message['id']) &&
               $message['jsonrpc'] === self::JSONRPC_VERSION &&
               (isset($message['result']) || isset($message['error'])) &&
               ! (isset($message['result']) && isset($message['error']));
    }

    /**
     * Register a request handler.
     */
    public function onRequest(string $method, callable $handler): void
    {
        $this->requestHandlers[$method] = $handler;
    }

    /**
     * Register a notification handler.
     */
    public function onNotification(string $method, callable $handler): void
    {
        $this->notificationHandlers[$method] = $handler;
    }

    /**
     * Register a response handler.
     */
    public function onResponse($id, callable $handler): void
    {
        $this->responseHandlers[$id] = $handler;
    }

    /**
     * Remove a request handler.
     */
    public function removeRequestHandler(string $method): void
    {
        unset($this->requestHandlers[$method]);
    }

    /**
     * Remove a notification handler.
     */
    public function removeNotificationHandler(string $method): void
    {
        unset($this->notificationHandlers[$method]);
    }

    /**
     * Get all registered request methods.
     */
    public function getRequestMethods(): array
    {
        return array_keys($this->requestHandlers);
    }

    /**
     * Get all registered notification methods.
     */
    public function getNotificationMethods(): array
    {
        return array_keys($this->notificationHandlers);
    }

    /**
     * Check if a request method is registered.
     */
    public function hasRequestHandler(string $method): bool
    {
        return isset($this->requestHandlers[$method]);
    }

    /**
     * Check if a notification method is registered.
     */
    public function hasNotificationHandler(string $method): bool
    {
        return isset($this->notificationHandlers[$method]);
    }

    /**
     * Set debug mode.
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Get debug mode status.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }
}
