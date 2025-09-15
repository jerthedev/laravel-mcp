<?php

namespace JTD\LaravelMCP\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Server\Handlers\PromptHandler;
use JTD\LaravelMCP\Server\Handlers\ResourceHandler;
use JTD\LaravelMCP\Server\Handlers\ToolHandler;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * MCP message processor.
 *
 * This class processes MCP protocol messages, routing them to appropriate
 * handlers and managing the MCP server lifecycle and capabilities.
 */
class MessageProcessor implements MessageHandlerInterface
{
    /**
     * JSON-RPC handler instance.
     */
    protected JsonRpcHandlerInterface $jsonRpcHandler;

    /**
     * MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * Component registries.
     */
    protected ToolRegistry $toolRegistry;

    protected ResourceRegistry $resourceRegistry;

    protected PromptRegistry $promptRegistry;

    /**
     * Capability negotiator.
     */
    protected CapabilityNegotiator $capabilityNegotiator;

    /**
     * Message handlers.
     */
    protected ToolHandler $toolHandler;

    protected ResourceHandler $resourceHandler;

    protected PromptHandler $promptHandler;

    /**
     * Server information.
     */
    protected array $serverInfo = [
        'name' => 'Laravel MCP Server',
        'version' => '1.0.0',
    ];

    /**
     * Client capabilities.
     */
    protected array $clientCapabilities = [];

    /**
     * Server capabilities.
     */
    protected array $serverCapabilities = [];

    /**
     * Initialization status.
     */
    protected bool $initialized = false;

    /**
     * Current transport instance for sending proactive messages.
     */
    protected ?TransportInterface $currentTransport = null;

    /**
     * Create a new message processor instance.
     */
    public function __construct(
        JsonRpcHandlerInterface $jsonRpcHandler,
        McpRegistry $registry,
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry,
        CapabilityNegotiator $capabilityNegotiator
    ) {
        $this->jsonRpcHandler = $jsonRpcHandler;
        $this->registry = $registry;
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;
        $this->capabilityNegotiator = $capabilityNegotiator;

        // Initialize message handlers
        $this->toolHandler = new ToolHandler($toolRegistry, app()->environment('local'));
        $this->resourceHandler = new ResourceHandler($resourceRegistry, app()->environment('local'));
        $this->promptHandler = new PromptHandler($promptRegistry, app()->environment('local'));

        $this->setupHandlers();
        $this->setupServerCapabilities();
    }

    /**
     * Handle an incoming MCP message.
     */
    public function handle(array $message, TransportInterface $transport): ?array
    {
        // Store transport reference for proactive messaging
        $this->currentTransport = $transport;

        // Check if this is a batch request (array of messages)
        if ($this->isBatchRequest($message)) {
            return $this->handleBatchRequest($message, $transport);
        }

        // Handle single message
        return $this->handleSingleMessage($message, $transport);
    }

    /**
     * Handle a transport error.
     */
    public function handleError(\Throwable $error, TransportInterface $transport): void
    {
        Log::error('Transport error', [
            'transport' => get_class($transport),
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);
    }

    /**
     * Handle transport connection establishment.
     */
    public function onConnect(TransportInterface $transport): void
    {
        Log::info('MCP transport connected', [
            'transport' => get_class($transport),
            'config' => $transport->getConfig(),
        ]);
    }

    /**
     * Handle transport connection closure.
     */
    public function onDisconnect(TransportInterface $transport): void
    {
        Log::info('MCP transport disconnected', [
            'transport' => get_class($transport),
        ]);

        $this->initialized = false;
        $this->clientCapabilities = [];
    }

    /**
     * Check if the handler can process a specific message type.
     */
    public function canHandle(array $message): bool
    {
        return $this->jsonRpcHandler->validateMessage($message);
    }

    /**
     * Get supported message types by this handler.
     */
    public function getSupportedMessageTypes(): array
    {
        return [
            'initialize',
            'initialized',
            'ping',
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'resources/templates/list',
            'roots/list',
            'prompts/list',
            'prompts/get',
        ];
    }

    /**
     * Setup JSON-RPC handlers for MCP methods.
     */
    protected function setupHandlers(): void
    {
        // Initialize method
        $this->jsonRpcHandler->onRequest('initialize', function (array $params) {
            return $this->handleInitialize($params);
        });

        // Initialized notification
        $this->jsonRpcHandler->onNotification('initialized', function (array $params) {
            $this->handleInitialized($params);
        });

        // Ping method
        $this->jsonRpcHandler->onRequest('ping', function (array $params) {
            return $this->handlePing($params);
        });

        // Tool methods
        $this->jsonRpcHandler->onRequest('tools/list', function (array $params, array $request = []) {
            return $this->handleToolsList($params, $request);
        });

        $this->jsonRpcHandler->onRequest('tools/call', function (array $params, array $request = []) {
            return $this->handleToolsCall($params, $request);
        });

        // Resource methods
        $this->jsonRpcHandler->onRequest('resources/list', function (array $params, array $request = []) {
            return $this->handleResourcesList($params, $request);
        });

        $this->jsonRpcHandler->onRequest('resources/read', function (array $params, array $request = []) {
            return $this->handleResourcesRead($params, $request);
        });

        $this->jsonRpcHandler->onRequest('resources/templates/list', function (array $params) {
            return $this->handleResourceTemplatesList($params);
        });

        // Roots methods
        $this->jsonRpcHandler->onRequest('roots/list', function (array $params, array $request = []) {
            return $this->handleRootsList($params, $request);
        });

        // Prompt methods
        $this->jsonRpcHandler->onRequest('prompts/list', function (array $params, array $request = []) {
            return $this->handlePromptsList($params, $request);
        });

        $this->jsonRpcHandler->onRequest('prompts/get', function (array $params, array $request = []) {
            return $this->handlePromptsGet($params, $request);
        });
    }

    /**
     * Setup server capabilities.
     */
    protected function setupServerCapabilities(): void
    {
        $this->serverCapabilities = [
            'tools' => [
                'listChanged' => false,
            ],
            'resources' => [
                'subscribe' => false,
                'listChanged' => false,
            ],
            'roots' => [
                'listChanged' => false,
            ],
            'prompts' => [
                'listChanged' => false,
            ],
        ];
    }

    /**
     * Handle initialize request.
     */
    protected function handleInitialize(array $params): array
    {
        $this->clientCapabilities = $params['capabilities'] ?? [];

        // Negotiate protocol version - use client's requested version if supported
        $clientProtocolVersion = $params['protocolVersion'] ?? '2024-11-05';
        $negotiatedProtocolVersion = $this->negotiateProtocolVersion($clientProtocolVersion);

        // Store client capabilities but don't negotiate complex capabilities for Claude CLI
        // Claude CLI expects simple Playwright-style capabilities
        Log::info('MCP initialization', [
            'client_info' => $params['clientInfo'] ?? 'Unknown client',
            'client_protocol_version' => $clientProtocolVersion,
            'negotiated_protocol_version' => $negotiatedProtocolVersion,
            'client_capabilities' => $this->clientCapabilities,
        ]);

        // Match Playwright's exact response format for Claude CLI compatibility
        // Use proper MCP specification capabilities format with comprehensive capabilities
        $response = [
            'protocolVersion' => $negotiatedProtocolVersion,
            'capabilities' => [
                'tools' => [
                    'listChanged' => true  // Support for tools/list notifications
                ],
                'resources' => [
                    'listChanged' => true,
                    'subscribe' => false
                ],
                'prompts' => [
                    'listChanged' => true
                ],
                'logging' => []
            ],
            'serverInfo' => $this->serverInfo,
        ];


        return $response;
    }

    /**
     * Negotiate protocol version with client.
     */
    protected function negotiateProtocolVersion(string $clientVersion): string
    {
        // Use supported versions from constants
        $supportedVersions = \JTD\LaravelMCP\Support\McpConstants::SUPPORTED_MCP_VERSIONS;

        // If client requests a supported version, use it
        if (in_array($clientVersion, $supportedVersions)) {
            return $clientVersion;
        }

        // Fall back to default MCP version for compatibility
        return \JTD\LaravelMCP\Support\McpConstants::MCP_PROTOCOL_VERSION;
    }

    /**
     * Handle initialized notification.
     */
    protected function handleInitialized(array $params): void
    {
        $this->initialized = true;

        Log::info('MCP server initialized - sending proactive roots/list request like Playwright', [
            'params' => $params,
            'has_transport' => !!$this->currentTransport
        ]);

        // Send proactive roots/list request like Playwright does
        $this->sendProactiveRootsList();
    }

    /**
     * Send proactive roots/list request like Playwright does.
     */
    protected function sendProactiveRootsList(): void
    {
        Log::info('sendProactiveRootsList called', [
            'has_transport' => !!$this->currentTransport,
            'transport_type' => $this->currentTransport ? get_class($this->currentTransport) : 'none'
        ]);

        $rootsListRequest = [
            'method' => 'roots/list',
            'jsonrpc' => '2.0',
            'id' => 0
        ];

        if ($this->currentTransport) {
            try {
                $requestJson = json_encode($rootsListRequest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                Log::info('About to call send()', ['json' => $requestJson]);
                $this->currentTransport->send($requestJson);
                Log::info('Sent proactive roots/list request like Playwright', ['request' => $rootsListRequest]);

                // For STDIO transport, we should exit after sending like Playwright does
                // But not during testing
                if ($this->currentTransport instanceof \JTD\LaravelMCP\Transport\StdioTransport &&
                    !app()->environment('testing')) {
                    Log::info('Exiting after proactive roots/list like Playwright');
                    exit(0);
                }
            } catch (\Throwable $e) {
                Log::error('Failed to send proactive roots/list request', [
                    'error' => $e->getMessage(),
                    'request' => $rootsListRequest
                ]);
            }
        }
    }

    /**
     * Handle ping request.
     */
    protected function handlePing(array $params): array
    {
        return [];
    }

    /**
     * Handle tools/list request.
     */
    protected function handleToolsList(array $params, array $request = []): array
    {
        try {
            Log::info('MessageProcessor: handleToolsList starting', [
                'params' => $params,
                'request_id' => $request['id'] ?? null,
                'initialized' => $this->initialized,
                'tool_handler_exists' => isset($this->toolHandler),
            ]);

            $this->checkInitialized();

            Log::info('MessageProcessor: checkInitialized passed');

            $context = [
                'request_id' => $request['id'] ?? null,
            ];

            Log::info('MessageProcessor: calling toolHandler->handle');

            $result = $this->toolHandler->handle('tools/list', $params, $context);

            Log::info('MessageProcessor: tools/list result', [
                'result_keys' => array_keys($result),
                'tool_count' => isset($result['tools']) ? count($result['tools']) : 'no tools key',
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('MessageProcessor: handleToolsList failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    /**
     * Handle tools/call request.
     */
    protected function handleToolsCall(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->toolHandler->handle('tools/call', $params, $context);
    }

    /**
     * Handle resources/list request.
     */
    protected function handleResourcesList(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->resourceHandler->handle('resources/list', $params, $context);
    }

    /**
     * Handle resources/read request.
     */
    protected function handleResourcesRead(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->resourceHandler->handle('resources/read', $params, $context);
    }

    /**
     * Handle resources/templates/list request.
     */
    protected function handleResourceTemplatesList(array $params): array
    {
        $this->checkInitialized();

        return [
            'resourceTemplates' => $this->resourceRegistry->getResourceTemplates(),
        ];
    }

    /**
     * Handle roots/list request.
     */
    protected function handleRootsList(array $params, array $request = []): array
    {
        $this->checkInitialized();

        // Return empty roots list - can be extended later for filesystem roots
        return [
            'roots' => [],
        ];
    }

    /**
     * Handle prompts/list request.
     */
    protected function handlePromptsList(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->promptHandler->handle('prompts/list', $params, $context);
    }

    /**
     * Handle prompts/get request.
     */
    protected function handlePromptsGet(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->promptHandler->handle('prompts/get', $params, $context);
    }

    /**
     * Check if the server is initialized.
     */
    protected function checkInitialized(): void
    {
        if (! $this->initialized) {
            throw new ProtocolException(
                -32002,
                'Server not initialized',
                null,
                'Server must be initialized before processing requests'
            );
        }
    }


    /**
     * Get initialization status.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get client capabilities.
     */
    public function getClientCapabilities(): array
    {
        return $this->clientCapabilities;
    }

    /**
     * Get server capabilities.
     */
    public function getServerCapabilities(): array
    {
        return $this->serverCapabilities;
    }

    /**
     * Set server information.
     */
    public function setServerInfo(array $info): void
    {
        $this->serverInfo = array_merge($this->serverInfo, $info);
    }

    /**
     * Get server information.
     */
    public function getServerInfo(): array
    {
        return $this->serverInfo;
    }

    /**
     * Check if the message is a batch request.
     */
    protected function isBatchRequest(array $message): bool
    {
        // Check if this is a numerically indexed array (batch)
        return array_keys($message) === range(0, count($message) - 1);
    }

    /**
     * Handle a batch request.
     */
    protected function handleBatchRequest(array $batchRequest, TransportInterface $transport): ?array
    {
        $responses = [];

        foreach ($batchRequest as $singleRequest) {
            if (! is_array($singleRequest)) {
                $responses[] = $this->jsonRpcHandler->createErrorResponse(
                    -32600,
                    'Invalid request',
                    null,
                    null
                );

                continue;
            }

            // Process individual request directly without recursive call
            $response = $this->handleSingleMessage($singleRequest, $transport);

            // Only add response if it's not null (notifications return null)
            if ($response !== null) {
                $responses[] = $response;
            }
        }

        // Return the responses array or null if all were notifications
        return empty($responses) ? null : $responses;
    }

    /**
     * Handle a single message (non-batch).
     */
    protected function handleSingleMessage(array $message, TransportInterface $transport): ?array
    {
        try {
            if (! $this->jsonRpcHandler->validateMessage($message)) {
                Log::warning('Invalid JSON-RPC message received', ['message' => $message]);

                return $this->jsonRpcHandler->createErrorResponse(-32600, 'Invalid request', null, $message['id'] ?? null);
            }

            if ($this->jsonRpcHandler->isRequest($message)) {
                return $this->jsonRpcHandler->handleRequest($message);
            }

            if ($this->jsonRpcHandler->isNotification($message)) {
                $this->jsonRpcHandler->handleNotification($message);

                return null; // Notifications don't require responses
            }

            if ($this->jsonRpcHandler->isResponse($message)) {
                $this->jsonRpcHandler->handleResponse($message);

                return null; // Responses don't require responses
            }

            return $this->jsonRpcHandler->createErrorResponse(-32600, 'Invalid request', null, $message['id'] ?? null);

        } catch (ProtocolException $e) {
            // Handle protocol exceptions with their specific error codes
            Log::warning('Protocol error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'method' => $e->getMethod(),
                'message' => $message,
            ]);

            return $this->jsonRpcHandler->createErrorResponse(
                $e->getCode(),
                $e->getMessage(),
                $e->getData(),
                $message['id'] ?? null
            );

        } catch (\Throwable $e) {
            Log::error('Message processing error', [
                'error' => $e->getMessage(),
                'message' => $message,
                'trace' => $e->getTraceAsString(),
            ]);

            // Log more detailed error in debug mode
            $errorMessage = config('app.debug') ? 'Internal error: '.$e->getMessage() : 'Internal error';

            return $this->jsonRpcHandler->createErrorResponse(-32603, $errorMessage, null, $message['id'] ?? null);
        }
    }
}
