<?php

namespace JTD\LaravelMCP\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
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

        $this->setupHandlers();
        $this->setupServerCapabilities();
    }

    /**
     * Handle an incoming MCP message.
     */
    public function handle(array $message, TransportInterface $transport): ?array
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

        } catch (\Throwable $e) {
            Log::error('Message processing error', [
                'error' => $e->getMessage(),
                'message' => $message,
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->jsonRpcHandler->createErrorResponse(-32603, 'Internal error', null, $message['id'] ?? null);
        }
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
        $this->jsonRpcHandler->onRequest('tools/list', function (array $params) {
            return $this->handleToolsList($params);
        });

        $this->jsonRpcHandler->onRequest('tools/call', function (array $params) {
            return $this->handleToolsCall($params);
        });

        // Resource methods
        $this->jsonRpcHandler->onRequest('resources/list', function (array $params) {
            return $this->handleResourcesList($params);
        });

        $this->jsonRpcHandler->onRequest('resources/read', function (array $params) {
            return $this->handleResourcesRead($params);
        });

        $this->jsonRpcHandler->onRequest('resources/templates/list', function (array $params) {
            return $this->handleResourceTemplatesList($params);
        });

        // Prompt methods
        $this->jsonRpcHandler->onRequest('prompts/list', function (array $params) {
            return $this->handlePromptsList($params);
        });

        $this->jsonRpcHandler->onRequest('prompts/get', function (array $params) {
            return $this->handlePromptsGet($params);
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

        // Negotiate capabilities
        $negotiatedCapabilities = $this->capabilityNegotiator->negotiate(
            $this->clientCapabilities,
            $this->serverCapabilities
        );

        $this->serverCapabilities = $negotiatedCapabilities;

        Log::info('MCP initialization', [
            'client_info' => $params['clientInfo'] ?? 'Unknown client',
            'protocol_version' => $params['protocolVersion'] ?? 'Unknown',
            'client_capabilities' => $this->clientCapabilities,
            'server_capabilities' => $this->serverCapabilities,
        ]);

        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $this->serverCapabilities,
            'serverInfo' => $this->serverInfo,
        ];
    }

    /**
     * Handle initialized notification.
     */
    protected function handleInitialized(array $params): void
    {
        $this->initialized = true;

        Log::info('MCP server initialized');
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
    protected function handleToolsList(array $params): array
    {
        $this->checkInitialized();

        return [
            'tools' => $this->toolRegistry->getToolDefinitions(),
        ];
    }

    /**
     * Handle tools/call request.
     */
    protected function handleToolsCall(array $params): array
    {
        $this->checkInitialized();

        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (! $this->toolRegistry->has($name)) {
            throw new ProtocolException("Tool '{$name}' not found", -32601);
        }

        if (! $this->toolRegistry->validateParameters($name, $arguments)) {
            throw new ProtocolException("Invalid parameters for tool '{$name}'", -32602);
        }

        try {
            $result = $this->toolRegistry->executeTool($name, $arguments);

            return [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : json_encode($result),
                    ],
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("Tool execution error: {$name}", [
                'error' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            throw new ProtocolException("Tool execution failed: {$e->getMessage()}", -32603);
        }
    }

    /**
     * Handle resources/list request.
     */
    protected function handleResourcesList(array $params): array
    {
        $this->checkInitialized();

        $cursor = $params['cursor'] ?? null;

        return $this->resourceRegistry->listResources($cursor);
    }

    /**
     * Handle resources/read request.
     */
    protected function handleResourcesRead(array $params): array
    {
        $this->checkInitialized();

        $uri = $params['uri'] ?? '';

        if (! $uri) {
            throw new ProtocolException('Resource URI is required', -32602);
        }

        // Find resource by URI
        $resources = $this->resourceRegistry->getResourcesByUri($uri);

        if (empty($resources)) {
            throw new ProtocolException("Resource not found: {$uri}", -32601);
        }

        $resourceName = array_key_first($resources);

        try {
            return $this->resourceRegistry->getResourceContent($resourceName, $params);
        } catch (\Throwable $e) {
            Log::error("Resource read error: {$uri}", [
                'error' => $e->getMessage(),
            ]);

            throw new ProtocolException("Resource read failed: {$e->getMessage()}", -32603);
        }
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
     * Handle prompts/list request.
     */
    protected function handlePromptsList(array $params): array
    {
        $this->checkInitialized();

        $cursor = $params['cursor'] ?? null;

        return $this->promptRegistry->listPrompts($cursor);
    }

    /**
     * Handle prompts/get request.
     */
    protected function handlePromptsGet(array $params): array
    {
        $this->checkInitialized();

        $name = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        if (! $this->promptRegistry->has($name)) {
            throw new ProtocolException("Prompt '{$name}' not found", -32601);
        }

        if (! $this->promptRegistry->validateArguments($name, $arguments)) {
            throw new ProtocolException("Invalid arguments for prompt '{$name}'", -32602);
        }

        try {
            return $this->promptRegistry->getPrompt($name, $arguments);
        } catch (\Throwable $e) {
            Log::error("Prompt processing error: {$name}", [
                'error' => $e->getMessage(),
                'arguments' => $arguments,
            ]);

            throw new ProtocolException("Prompt processing failed: {$e->getMessage()}", -32603);
        }
    }

    /**
     * Check if the server is initialized.
     */
    protected function checkInitialized(): void
    {
        if (! $this->initialized) {
            throw new ProtocolException('Server not initialized', -32002);
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
}
