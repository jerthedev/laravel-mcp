<?php

namespace JTD\LaravelMCP\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HTTP controller for MCP message handling.
 *
 * This controller provides HTTP endpoints for MCP protocol communication,
 * including message handling, Server-Sent Events, health checks, and server info.
 */
class McpController extends Controller
{
    /**
     * Transport manager instance.
     */
    protected TransportManager $transportManager;

    /**
     * Message processor instance.
     */
    protected MessageProcessor $messageProcessor;

    /**
     * HTTP transport instance.
     */
    protected ?HttpTransport $httpTransport = null;

    /**
     * Create a new MCP controller instance.
     *
     * @param  TransportManager  $transportManager  Transport manager instance
     * @param  MessageProcessor  $messageProcessor  Message processor instance
     */
    public function __construct(TransportManager $transportManager, MessageProcessor $messageProcessor)
    {
        $this->transportManager = $transportManager;
        $this->messageProcessor = $messageProcessor;
    }

    /**
     * Handle incoming MCP message via HTTP.
     *
     * This is the main endpoint for JSON-RPC over HTTP communication.
     * It processes incoming MCP messages and returns appropriate responses.
     *
     * @param  Request  $request  The incoming HTTP request
     * @return Response The HTTP response
     */
    public function handle(Request $request): Response
    {
        try {
            // Get or create HTTP transport instance
            $transport = $this->getHttpTransport();

            // Delegate to the transport's HTTP request handler
            return $transport->handleHttpRequest($request);

        } catch (TransportException $e) {
            Log::error('MCP Controller transport error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'context' => $e->getContext(),
            ]);

            return $this->createErrorResponse(
                $e->getMessage(),
                $e->getCode() ?: -32603,
                null,
                500
            );

        } catch (\Throwable $e) {
            Log::error('MCP Controller unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->createErrorResponse(
                'Internal server error',
                -32603,
                null,
                500
            );
        }
    }

    /**
     * Handle OPTIONS request for CORS preflight.
     *
     * @param  Request  $request  The incoming OPTIONS request
     * @return Response CORS preflight response
     */
    public function options(Request $request): Response
    {
        try {
            $transport = $this->getHttpTransport();

            return $transport->handleOptionsRequest();

        } catch (\Throwable $e) {
            Log::error('MCP Controller OPTIONS error', [
                'error' => $e->getMessage(),
            ]);

            // Return basic CORS response even on error
            $response = new Response('', 204);

            return $response;
        }
    }

    /**
     * Server-Sent Events endpoint for MCP notifications.
     *
     * This endpoint provides a real-time event stream for MCP notifications
     * and updates. Clients can subscribe to receive push notifications about
     * state changes, new resources, or other events.
     *
     * @param  Request  $request  The incoming SSE request
     * @return StreamedResponse SSE stream response
     */
    public function events(Request $request): StreamedResponse
    {
        return new StreamedResponse(function () {
            // Set appropriate headers for SSE
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('X-Accel-Buffering: no'); // Disable Nginx buffering

            // Send initial connection event
            $this->sendEvent('connected', [
                'message' => 'Connected to MCP event stream',
                'timestamp' => now()->toIso8601String(),
            ]);

            // Keep connection alive with heartbeat
            $lastHeartbeat = time();
            $heartbeatInterval = 30; // Send heartbeat every 30 seconds

            while (true) {
                // Check if client is still connected
                if (connection_aborted()) {
                    break;
                }

                // Send heartbeat if needed
                if (time() - $lastHeartbeat >= $heartbeatInterval) {
                    $this->sendEvent('heartbeat', [
                        'timestamp' => now()->toIso8601String(),
                    ]);
                    $lastHeartbeat = time();
                }

                // TODO: Check for actual MCP events from event queue/bus
                // This would integrate with Laravel's event system to push
                // real-time updates about tools, resources, prompts, etc.

                // Flush output buffer
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();

                // Sleep briefly to prevent CPU spinning
                usleep(100000); // 100ms
            }
        });
    }

    /**
     * Health check endpoint.
     *
     * Provides a simple health check endpoint to verify the MCP server
     * is running and can process requests.
     *
     * @param  Request  $request  The incoming health check request
     * @return JsonResponse Health check response
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $transport = $this->getHttpTransport();
            $healthInfo = $transport->healthCheck();

            $status = $healthInfo['healthy'] ? 'healthy' : 'unhealthy';
            $httpStatus = $healthInfo['healthy'] ? 200 : 503;

            return response()->json([
                'status' => $status,
                'timestamp' => now()->toIso8601String(),
                'checks' => $healthInfo['checks'] ?? [],
                'errors' => $healthInfo['errors'] ?? [],
                'transport' => [
                    'type' => 'http',
                    'connected' => $transport->isConnected(),
                    'stats' => $transport->getStats(),
                ],
            ], $httpStatus);

        } catch (\Throwable $e) {
            Log::error('Health check failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'timestamp' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Server information endpoint.
     *
     * Returns information about the MCP server implementation,
     * including version, capabilities, and supported features.
     *
     * @param  Request  $request  The incoming info request
     * @return JsonResponse Server information response
     */
    public function info(Request $request): JsonResponse
    {
        try {
            $config = config('laravel-mcp');

            $info = [
                'server' => [
                    'name' => $config['server']['name'] ?? 'Laravel MCP Server',
                    'version' => $config['server']['version'] ?? '1.0.0',
                    'description' => $config['server']['description'] ?? 'MCP Server built with Laravel',
                    'vendor' => $config['server']['vendor'] ?? 'JTD/LaravelMCP',
                ],
                'protocol' => [
                    'version' => '1.0',
                    'transport' => 'http',
                ],
                'capabilities' => [
                    'tools' => $config['capabilities']['tools'] ?? [],
                    'resources' => $config['capabilities']['resources'] ?? [],
                    'prompts' => $config['capabilities']['prompts'] ?? [],
                    'logging' => $config['capabilities']['logging'] ?? [],
                ],
                'endpoints' => [
                    'message' => route('mcp.handle'),
                    'events' => route('mcp.events'),
                    'health' => route('mcp.health'),
                    'info' => route('mcp.info'),
                ],
                'timestamp' => now()->toIso8601String(),
            ];

            // Add transport info if available
            try {
                $transport = $this->getHttpTransport();
                $info['transport'] = $transport->getConnectionInfo();
            } catch (\Throwable $e) {
                // Transport info is optional
                $info['transport'] = ['status' => 'unavailable'];
            }

            return response()->json($info);

        } catch (\Throwable $e) {
            Log::error('Server info failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to retrieve server information',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get or create HTTP transport instance.
     *
     * @return HttpTransport HTTP transport instance
     *
     * @throws TransportException If transport creation fails
     */
    protected function getHttpTransport(): HttpTransport
    {
        if ($this->httpTransport === null) {
            // Get HTTP transport configuration
            $config = config('mcp-transports.http', []);

            // Create HTTP transport via manager
            $transport = $this->transportManager->createTransport('http', $config);

            if (! $transport instanceof HttpTransport) {
                throw new TransportException('Invalid transport type. Expected HttpTransport.');
            }

            // Set the message handler
            $transport->setMessageHandler($this->messageProcessor);

            // Start the transport if not already started
            if (! $transport->isConnected()) {
                $transport->start();
            }

            $this->httpTransport = $transport;
        }

        return $this->httpTransport;
    }

    /**
     * Send a Server-Sent Event.
     *
     * @param  string  $event  Event name
     * @param  array  $data  Event data
     * @param  string|null  $id  Optional event ID
     */
    protected function sendEvent(string $event, array $data, ?string $id = null): void
    {
        if ($id !== null) {
            echo "id: {$id}\n";
        }

        echo "event: {$event}\n";
        echo 'data: '.json_encode($data)."\n\n";

        // Flush output immediately
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Create a JSON-RPC error response.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  mixed  $id  Request ID
     * @param  int  $httpStatus  HTTP status code
     * @return Response Error response
     */
    protected function createErrorResponse(
        string $message,
        int $code,
        $id = null,
        int $httpStatus = 500
    ): Response {
        $errorResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];

        $response = new Response(
            json_encode($errorResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $httpStatus,
            ['Content-Type' => 'application/json']
        );

        return $response;
    }

    /**
     * Add CORS headers to response.
     *
     * @param  Response  $response  The response to modify
     * @return Response Modified response with CORS headers
     */
    protected function addCorsHeaders(Response $response): Response
    {
        $corsConfig = config('laravel-mcp.cors', []);

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

        $response->headers->set(
            'Access-Control-Max-Age',
            (string) ($corsConfig['max_age'] ?? 86400)
        );

        return $response;
    }
}
