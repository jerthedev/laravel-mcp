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
    protected function handleToolsList(array $params, array $request = []): array
    {
        $this->checkInitialized();

        $context = [
            'request_id' => $request['id'] ?? null,
        ];

        return $this->toolHandler->handle('tools/list', $params, $context);
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
