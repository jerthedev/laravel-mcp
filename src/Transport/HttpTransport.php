<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * HTTP transport implementation for MCP.
 *
 * This class implements the MCP transport protocol over HTTP,
 * handling JSON-RPC messages via HTTP requests and responses.
 */
class HttpTransport implements TransportInterface
{
    /**
     * Transport configuration.
     */
    protected array $config = [];

    /**
     * Message handler instance.
     */
    protected ?MessageHandlerInterface $messageHandler = null;

    /**
     * Connection status.
     */
    protected bool $connected = false;

    /**
     * Current request instance.
     */
    protected ?Request $currentRequest = null;

    /**
     * Current response instance.
     */
    protected ?Response $currentResponse = null;

    /**
     * Default configuration.
     */
    protected array $defaultConfig = [
        'host' => '127.0.0.1',
        'port' => 8000,
        'path' => '/mcp',
        'timeout' => 30,
        'middleware' => [],
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
    ];

    /**
     * Initialize the transport layer.
     */
    public function initialize(array $config = []): void
    {
        $this->config = array_merge($this->defaultConfig, $config);
        $this->connected = false;
    }

    /**
     * Start listening for incoming messages.
     */
    public function listen(): void
    {
        // For HTTP transport, listening is handled by the web server
        // This method is called when the HTTP routes are registered
        $this->connected = true;

        Log::info('HTTP MCP transport listening', [
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'path' => $this->config['path'],
        ]);
    }

    /**
     * Send a message to the client.
     */
    public function send(array $message): void
    {
        if (! $this->connected) {
            throw new TransportException('Transport is not connected');
        }

        // For HTTP transport, sending is handled by returning the message
        // from the controller action. This method stores the message for retrieval.
        $this->currentResponse = response()->json($message, 200, $this->config['headers']);
    }

    /**
     * Receive a message from the client.
     */
    public function receive(): ?array
    {
        if (! $this->currentRequest) {
            return null;
        }

        try {
            $content = $this->currentRequest->getContent();

            if (empty($content)) {
                return null;
            }

            $message = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new TransportException('Invalid JSON in request body');
            }

            return is_array($message) ? $message : null;
        } catch (\Exception $e) {
            Log::error('Error receiving HTTP message', [
                'error' => $e->getMessage(),
                'request_body' => $this->currentRequest->getContent(),
            ]);

            return null;
        }
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->connected = false;
        $this->currentRequest = null;
        $this->currentResponse = null;

        Log::info('HTTP MCP transport closed');
    }

    /**
     * Check if the transport is currently connected/active.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get transport-specific configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the message handler for processing received messages.
     */
    public function setMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->messageHandler = $handler;
    }

    /**
     * Handle an HTTP request.
     */
    public function handleHttpRequest(Request $request): Response
    {
        $this->currentRequest = $request;

        try {
            if (! $this->messageHandler) {
                throw new TransportException('No message handler configured');
            }

            $message = $this->receive();

            if (! $message) {
                return response()->json([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32700,
                        'message' => 'Parse error',
                    ],
                    'id' => null,
                ], 400);
            }

            // Notify handler of connection
            $this->messageHandler->onConnect($this);

            // Process the message
            $response = $this->messageHandler->handle($message, $this);

            if ($response) {
                return response()->json($response, 200, $this->config['headers']);
            }

            // No response needed (notification)
            return response()->json(null, 204);

        } catch (\Throwable $e) {
            Log::error('HTTP transport error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->messageHandler) {
                $this->messageHandler->handleError($e, $this);
            }

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                ],
                'id' => $message['id'] ?? null,
            ], 500);

        } finally {
            // Notify handler of disconnection
            if ($this->messageHandler) {
                $this->messageHandler->onDisconnect($this);
            }
        }
    }

    /**
     * Get the base URL for this transport.
     */
    public function getBaseUrl(): string
    {
        $protocol = $this->config['ssl'] ?? false ? 'https' : 'http';
        $host = $this->config['host'];
        $port = $this->config['port'];
        $path = $this->config['path'];

        $url = "{$protocol}://{$host}";

        if (($protocol === 'http' && $port != 80) || ($protocol === 'https' && $port != 443)) {
            $url .= ":{$port}";
        }

        $url .= $path;

        return $url;
    }

    /**
     * Get the current request.
     */
    public function getCurrentRequest(): ?Request
    {
        return $this->currentRequest;
    }

    /**
     * Get the current response.
     */
    public function getCurrentResponse(): ?Response
    {
        return $this->currentResponse;
    }

    /**
     * Validate HTTP headers.
     */
    public function validateHeaders(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return str_contains($contentType ?? '', 'application/json');
    }

    /**
     * Add CORS headers to response.
     */
    public function addCorsHeaders(Response $response): Response
    {
        if ($this->config['cors']['enabled'] ?? false) {
            $corsHeaders = $this->config['cors']['headers'] ?? [
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization',
            ];

            foreach ($corsHeaders as $key => $value) {
                $response->headers->set($key, $value);
            }
        }

        return $response;
    }

    /**
     * Handle OPTIONS request for CORS preflight.
     */
    public function handleOptionsRequest(): Response
    {
        $response = response('', 204);

        return $this->addCorsHeaders($response);
    }

    /**
     * Get transport statistics.
     */
    public function getStats(): array
    {
        return [
            'transport' => 'http',
            'connected' => $this->connected,
            'base_url' => $this->getBaseUrl(),
            'has_current_request' => $this->currentRequest !== null,
            'has_message_handler' => $this->messageHandler !== null,
        ];
    }
}
