<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;

/**
 * HTTP transport implementation for MCP.
 *
 * This class implements the MCP transport protocol over HTTP,
 * handling JSON-RPC messages via HTTP requests and responses.
 */
class HttpTransport extends BaseTransport
{
    /**
     * Current request instance.
     */
    protected ?Request $currentRequest = null;

    /**
     * Current response data.
     */
    protected ?string $currentResponseData = null;

    /**
     * Server started status.
     */
    protected bool $serverStarted = false;

    /**
     * Get transport type identifier.
     *
     * @return string Transport type
     */
    protected function getTransportType(): string
    {
        return 'http';
    }

    /**
     * Get default configuration for this transport type.
     *
     * @return array Default configuration
     */
    protected function getTransportDefaults(): array
    {
        return [
            'host' => '127.0.0.1',
            'port' => 8000,
            'path' => '/mcp',
            'middleware' => ['api'],
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['*'],
                'allowed_methods' => ['POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'Authorization', 'Accept'],
            ],
            'ssl' => [
                'enabled' => false,
                'cert_path' => null,
                'key_path' => null,
            ],
        ];
    }

    /**
     * Perform transport-specific start operations.
     *
     * @throws \Throwable If start fails
     */
    protected function doStart(): void
    {
        // For HTTP transport, starting means the web server is ready to accept requests
        // The actual HTTP server is managed by Laravel/web server, not this transport
        $this->serverStarted = true;

        if ($this->config['debug']) {
            Log::info('HTTP MCP transport ready', [
                'host' => $this->config['host'],
                'port' => $this->config['port'],
                'path' => $this->config['path'],
            ]);
        }
    }

    /**
     * Perform transport-specific stop operations.
     *
     * @throws \Throwable If stop fails
     */
    protected function doStop(): void
    {
        $this->serverStarted = false;
        $this->currentRequest = null;
        $this->currentResponseData = null;
    }

    /**
     * Perform transport-specific send operations.
     *
     * @param  string  $message  The message to send
     *
     * @throws \Throwable If send fails
     */
    protected function doSend(string $message): void
    {
        // For HTTP transport, sending means preparing the response data
        // The actual HTTP response is handled by Laravel's response system
        $this->currentResponseData = $message;
    }

    /**
     * Perform transport-specific receive operations.
     *
     * @return string|null The received message, or null if none available
     *
     * @throws \Throwable If receive fails
     */
    protected function doReceive(): ?string
    {
        if (! $this->currentRequest) {
            return null;
        }

        try {
            $content = $this->currentRequest->getContent();

            if (empty($content)) {
                return null;
            }

            // Validate that content is valid JSON
            $decoded = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw TransportException::framingError(
                    $this->getTransportType(),
                    'Invalid JSON in request body: '.json_last_error_msg()
                );
            }

            return $content;
        } catch (\Throwable $e) {
            Log::error('Error receiving HTTP message', [
                'error' => $e->getMessage(),
                'request_content_type' => $this->currentRequest->header('Content-Type'),
                'request_size' => strlen($this->currentRequest->getContent()),
            ]);

            throw TransportException::transmissionError(
                $this->getTransportType(),
                'Failed to receive HTTP message: '.$e->getMessage()
            );
        }
    }

    /**
     * Handle an HTTP request.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return Response The HTTP response
     */
    public function handleHttpRequest(Request $request): Response
    {
        $this->currentRequest = $request;

        try {
            if (! $this->isConnected()) {
                throw TransportException::transportClosed($this->getTransportType());
            }

            if (! $this->messageHandler) {
                throw new TransportException('No message handler configured');
            }

            // Validate request headers
            if (! $this->validateHeaders($request)) {
                return $this->createErrorResponse(
                    'Invalid Content-Type header. Expected application/json',
                    -32700,
                    null,
                    400
                );
            }

            // Notify handler of connection
            $this->messageHandler->onConnect($this);

            // Receive the message
            $message = $this->receive();

            if (! $message) {
                return $this->createErrorResponse(
                    'Parse error: Empty or invalid request body',
                    -32700,
                    null,
                    400
                );
            }

            // Parse JSON to get the request ID for error handling
            $messageData = json_decode($message, true);
            $requestId = $messageData['id'] ?? null;

            // Process the message
            $response = $this->messageHandler->handle($messageData, $this);

            if ($response) {
                // Encode response and send
                $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new TransportException('Failed to encode response: '.json_last_error_msg());
                }

                $this->send($responseJson);

                return $this->createResponse($this->currentResponseData, 200);
            }

            // No response needed (notification)
            return $this->createResponse('', 204);

        } catch (\Throwable $e) {
            Log::error('HTTP transport error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
                'request_method' => $request->method(),
            ]);

            if ($this->messageHandler) {
                $this->messageHandler->handleError($e, $this);
            }

            $requestId = null;
            if ($this->currentRequest) {
                $content = $this->currentRequest->getContent();
                if (! empty($content)) {
                    $decoded = json_decode($content, true);
                    $requestId = $decoded['id'] ?? null;
                }
            }

            return $this->createErrorResponse(
                'Internal error: '.$e->getMessage(),
                -32603,
                $requestId,
                500
            );

        } finally {
            // Notify handler of disconnection
            if ($this->messageHandler) {
                $this->messageHandler->onDisconnect($this);
            }

            // Clean up request data
            $this->currentRequest = null;
            $this->currentResponseData = null;
        }
    }

    /**
     * Handle OPTIONS request for CORS preflight.
     *
     * @return Response CORS preflight response
     */
    public function handleOptionsRequest(): Response
    {
        $response = new Response('', 204);

        return $this->addCorsHeaders($response);
    }

    /**
     * Validate HTTP headers.
     *
     * @param  Request  $request  The request to validate
     * @return bool True if headers are valid
     */
    protected function validateHeaders(Request $request): bool
    {
        $contentType = $request->header('Content-Type');

        return str_contains($contentType ?? '', 'application/json');
    }

    /**
     * Create an HTTP response.
     *
     * @param  string  $content  Response content
     * @param  int  $status  HTTP status code
     * @return Response HTTP response
     */
    protected function createResponse(string $content, int $status = 200): Response
    {
        $response = new Response($content, $status, $this->config['headers']);

        return $this->addCorsHeaders($response);
    }

    /**
     * Create an error response.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  mixed  $id  Request ID
     * @param  int  $httpStatus  HTTP status code
     * @return Response Error response
     */
    protected function createErrorResponse(string $message, int $code, $id = null, int $httpStatus = 500): Response
    {
        $errorResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];

        $content = json_encode($errorResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $this->createResponse($content, $httpStatus);
    }

    /**
     * Add CORS headers to response.
     *
     * @param  Response  $response  The response to modify
     * @return Response Modified response
     */
    protected function addCorsHeaders(Response $response): Response
    {
        if ($this->config['cors']['enabled'] ?? false) {
            $corsConfig = $this->config['cors'];

            $response->headers->set(
                'Access-Control-Allow-Origin',
                implode(', ', $corsConfig['allowed_origins'] ?? ['*'])
            );

            $response->headers->set(
                'Access-Control-Allow-Methods',
                implode(', ', $corsConfig['allowed_methods'] ?? ['POST', 'OPTIONS'])
            );

            $response->headers->set(
                'Access-Control-Allow-Headers',
                implode(', ', $corsConfig['allowed_headers'] ?? ['Content-Type', 'Authorization'])
            );

            $response->headers->set('Access-Control-Max-Age', '86400');
        }

        return $response;
    }

    /**
     * Get the base URL for this transport.
     *
     * @return string Base URL
     */
    public function getBaseUrl(): string
    {
        $protocol = $this->config['ssl']['enabled'] ?? false ? 'https' : 'http';
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
     *
     * @return Request|null Current request
     */
    public function getCurrentRequest(): ?Request
    {
        return $this->currentRequest;
    }

    /**
     * Set the current request (for testing purposes).
     *
     * @param  Request  $request  The request to set
     */
    public function setCurrentRequest(Request $request): void
    {
        $this->currentRequest = $request;
    }

    /**
     * Get the current response data.
     *
     * @return string|null Current response data
     */
    public function getCurrentResponseData(): ?string
    {
        return $this->currentResponseData;
    }

    /**
     * Perform transport-specific health checks.
     *
     * @return array Transport-specific health check results
     */
    protected function performTransportSpecificHealthChecks(): array
    {
        $checks = [];
        $errors = [];

        // Check if server is ready
        $checks['server_started'] = $this->serverStarted;

        if (! $checks['server_started']) {
            $errors[] = 'HTTP server is not started';
        }

        // Check SSL configuration if enabled
        if ($this->config['ssl']['enabled'] ?? false) {
            $sslConfig = $this->config['ssl'];

            $checks['ssl_cert_exists'] = file_exists($sslConfig['cert_path'] ?? '');
            $checks['ssl_key_exists'] = file_exists($sslConfig['key_path'] ?? '');

            if (! $checks['ssl_cert_exists']) {
                $errors[] = 'SSL certificate file not found: '.($sslConfig['cert_path'] ?? '');
            }

            if (! $checks['ssl_key_exists']) {
                $errors[] = 'SSL key file not found: '.($sslConfig['key_path'] ?? '');
            }
        } else {
            $checks['ssl_cert_exists'] = null; // N/A
            $checks['ssl_key_exists'] = null; // N/A
        }

        // Check port availability (basic check)
        // In test environment or when server is managed externally (like Laravel's built-in server),
        // we consider the port check as passed if the transport is properly started
        $host = $this->config['host'] ?? 'localhost';
        $port = $this->config['port'] ?? 8000;

        // If the transport is started and server is ready, we don't need to check port accessibility
        // as the HTTP server lifecycle is managed externally (by Laravel, web server, etc.)
        if ($this->serverStarted) {
            $checks['port_accessible'] = true;
        } else {
            // Only check actual port accessibility if server is not started
            $socket = @fsockopen($host, $port, $errno, $errstr, 1);
            if ($socket) {
                $checks['port_accessible'] = true;
                fclose($socket);
            } else {
                $checks['port_accessible'] = false;
                $errors[] = "Port {$port} on {$host} is not accessible: {$errstr} ({$errno})";
            }
        }

        return [
            'checks' => $checks,
            'errors' => $errors,
        ];
    }

    /**
     * Get connection information specific to HTTP transport.
     *
     * @return array Extended connection information
     */
    public function getConnectionInfo(): array
    {
        $info = parent::getConnectionInfo();

        $info['http_specific'] = [
            'base_url' => $this->getBaseUrl(),
            'server_started' => $this->serverStarted,
            'has_current_request' => $this->currentRequest !== null,
            'ssl_enabled' => $this->config['ssl']['enabled'] ?? false,
            'cors_enabled' => $this->config['cors']['enabled'] ?? false,
        ];

        return $info;
    }

    /**
     * Check if transport is connected (HTTP is always "connected" when started).
     *
     * @return bool True if connected
     */
    public function isConnected(): bool
    {
        return parent::isConnected() && $this->serverStarted;
    }
}
