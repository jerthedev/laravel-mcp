<?php

namespace JTD\LaravelMCP\Server;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Server\Contracts\ServerInterface;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Transport\TransportManager;

class McpServer implements ServerInterface, MessageHandlerInterface
{
    private ServerInfo $serverInfo;

    private CapabilityManager $capabilityManager;

    private MessageProcessor $messageProcessor;

    private TransportManager $transportManager;

    private McpRegistry $registry;

    private bool $initialized = false;

    private bool $running = false;

    private array $configuration = [];

    private array $transports = [];

    private array $clientCapabilities = [];

    private array $metrics = [];

    public function __construct(
        ServerInfo $serverInfo,
        CapabilityManager $capabilityManager,
        MessageProcessor $messageProcessor,
        TransportManager $transportManager,
        McpRegistry $registry
    ) {
        $this->serverInfo = $serverInfo;
        $this->capabilityManager = $capabilityManager;
        $this->messageProcessor = $messageProcessor;
        $this->transportManager = $transportManager;
        $this->registry = $registry;

        $this->initializeConfiguration();
        $this->initializeMetrics();
    }

    /**
     * Initialize the MCP server.
     */
    public function initialize(array $clientInfo = []): array
    {
        if ($this->initialized) {
            Log::warning('MCP Server already initialized');

            return $this->createInitializeResponse();
        }

        try {
            Log::info('Initializing MCP Server', ['client_info' => $clientInfo]);

            // Extract client capabilities
            $this->clientCapabilities = $clientInfo['capabilities'] ?? [];

            // Negotiate capabilities
            $negotiatedCapabilities = $this->capabilityManager->negotiateWithClient($this->clientCapabilities);

            // Update server info with negotiated capabilities
            $this->updateServerInfoFromNegotiation($clientInfo);

            // Initialize components
            $this->initializeComponents();

            // Mark as initialized
            $this->initialized = true;

            Log::info('MCP Server initialized successfully', [
                'negotiated_capabilities' => $negotiatedCapabilities,
                'server_info' => $this->serverInfo->getBasicInfo(),
            ]);

            return $this->createInitializeResponse();

        } catch (\Throwable $e) {
            Log::error('MCP Server initialization failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new McpException("Server initialization failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Start the MCP server.
     */
    public function start(): void
    {
        if ($this->running) {
            Log::warning('MCP Server already running');

            return;
        }

        if (! $this->initialized) {
            throw new McpException('Server must be initialized before starting');
        }

        try {
            Log::info('Starting MCP Server');

            // Start all registered transports
            $this->transportManager->startAllTransports();

            // Mark as running
            $this->running = true;
            $this->serverInfo->resetStartTime();

            // Update metrics
            $this->metrics['start_count'] = ($this->metrics['start_count'] ?? 0) + 1;
            $this->metrics['last_started'] = time();

            Log::info('MCP Server started successfully');

        } catch (\Throwable $e) {
            Log::error('MCP Server start failed', [
                'error' => $e->getMessage(),
            ]);
            throw new McpException("Server start failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Stop the MCP server.
     */
    public function stop(): void
    {
        if (! $this->running) {
            Log::warning('MCP Server not running');

            return;
        }

        try {
            Log::info('Stopping MCP Server');

            // Stop all transports gracefully
            $this->transportManager->stopAllTransports();

            // Mark as stopped
            $this->running = false;

            // Update metrics
            $this->metrics['stop_count'] = ($this->metrics['stop_count'] ?? 0) + 1;
            $this->metrics['last_stopped'] = time();

            Log::info('MCP Server stopped successfully');

        } catch (\Throwable $e) {
            Log::error('MCP Server stop failed', [
                'error' => $e->getMessage(),
            ]);
            throw new McpException("Server stop failed: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Restart the MCP server.
     */
    public function restart(): void
    {
        Log::info('Restarting MCP Server');

        $this->stop();
        sleep(1); // Brief pause to ensure clean shutdown
        $this->start();

        Log::info('MCP Server restarted successfully');
    }

    /**
     * Check if the server is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Check if the server is initialized.
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * Get server status information.
     */
    public function getStatus(): array
    {
        return [
            'initialized' => $this->initialized,
            'running' => $this->running,
            'server_info' => $this->serverInfo->getStatus(),
            'capabilities' => $this->capabilityManager->getNegotiatedCapabilities(),
            'transports' => $this->getTransportStatus(),
            'components' => $this->getComponentStatus(),
            'performance' => $this->getPerformanceStatus(),
        ];
    }

    /**
     * Get server health check information.
     */
    public function getHealth(): array
    {
        $checks = [
            'server_initialized' => $this->isHealthCheckPassing('server_initialized'),
            'server_running' => $this->isHealthCheckPassing('server_running'),
            'transports_healthy' => $this->isHealthCheckPassing('transports_healthy'),
            'components_registered' => $this->isHealthCheckPassing('components_registered'),
            'memory_usage' => $this->isHealthCheckPassing('memory_usage'),
        ];

        $healthy = array_reduce($checks, fn ($carry, $check) => $carry && $check['healthy'], true);

        return [
            'healthy' => $healthy,
            'checks' => $checks,
            'timestamp' => now()->toISOString(),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get server information.
     */
    public function getServerInfo(): array
    {
        return $this->serverInfo->getServerInfo();
    }

    /**
     * Get server capabilities.
     */
    public function getCapabilities(): array
    {
        return $this->capabilityManager->getNegotiatedCapabilities();
    }

    /**
     * Set server configuration.
     */
    public function setConfiguration(array $config): void
    {
        $this->configuration = array_merge($this->configuration, $config);
    }

    /**
     * Get server configuration.
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Register a transport with the server.
     */
    public function registerTransport(string $name, TransportInterface $transport): void
    {
        $this->transports[$name] = $transport;
        $this->transportManager->registerTransport($name, $transport);

        Log::debug("Transport registered: {$name}");
    }

    /**
     * Remove a transport from the server.
     */
    public function removeTransport(string $name): void
    {
        unset($this->transports[$name]);
        $this->transportManager->removeTransport($name);

        Log::debug("Transport removed: {$name}");
    }

    /**
     * Get all registered transports.
     */
    public function getTransports(): array
    {
        return $this->transports;
    }

    /**
     * Handle graceful shutdown.
     */
    public function shutdown(): void
    {
        Log::info('MCP Server shutting down gracefully');

        try {
            // Stop server if running
            if ($this->running) {
                $this->stop();
            }

            // Cleanup resources
            $this->cleanupResources();

            // Final logging
            Log::info('MCP Server shutdown completed');

        } catch (\Throwable $e) {
            Log::error('Error during MCP Server shutdown', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get server uptime in seconds.
     */
    public function getUptime(): int
    {
        return $this->serverInfo->getUptime();
    }

    /**
     * Get server performance metrics.
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'uptime' => $this->getUptime(),
            'requests_processed' => $this->metrics['requests_processed'] ?? 0,
            'component_counts' => [
                'tools' => count($this->registry->getTools()),
                'resources' => count($this->registry->getResources()),
                'prompts' => count($this->registry->getPrompts()),
            ],
        ]);
    }

    /**
     * Initialize server configuration.
     */
    private function initializeConfiguration(): void
    {
        $this->configuration = [
            'server' => Config::get('laravel-mcp.server', []),
            'capabilities' => Config::get('laravel-mcp.capabilities', []),
            'transports' => Config::get('mcp-transports', []),
            'security' => Config::get('laravel-mcp.security', []),
            'performance' => Config::get('laravel-mcp.performance', []),
        ];
    }

    /**
     * Initialize server metrics.
     */
    private function initializeMetrics(): void
    {
        $this->metrics = [
            'start_count' => 0,
            'stop_count' => 0,
            'restart_count' => 0,
            'requests_processed' => 0,
            'errors_count' => 0,
        ];
    }

    /**
     * Initialize server components.
     */
    private function initializeComponents(): void
    {
        // Initialize registry
        $this->registry->initialize();

        // Setup message processor with server info
        $this->messageProcessor->setServerInfo($this->serverInfo->getBasicInfo());

        Log::debug('Server components initialized');
    }

    /**
     * Create initialize response.
     */
    private function createInitializeResponse(): array
    {
        return [
            'protocolVersion' => $this->serverInfo->getProtocolVersion(),
            'capabilities' => $this->capabilityManager->getNegotiatedCapabilities(),
            'serverInfo' => $this->serverInfo->getBasicInfo(),
        ];
    }

    /**
     * Update server info from client negotiation.
     */
    private function updateServerInfoFromNegotiation(array $clientInfo): void
    {
        if (isset($clientInfo['clientInfo']['name'])) {
            $this->serverInfo->updateRuntimeInfo([
                'connected_client' => $clientInfo['clientInfo']['name'],
                'client_version' => $clientInfo['clientInfo']['version'] ?? 'unknown',
            ]);
        }
    }

    /**
     * Get transport status.
     */
    private function getTransportStatus(): array
    {
        return [
            'registered_count' => count($this->transports),
            'active_count' => $this->transportManager->getActiveTransportCount(),
            'transports' => array_keys($this->transports),
        ];
    }

    /**
     * Get component status.
     */
    private function getComponentStatus(): array
    {
        return [
            'tools' => count($this->registry->getTools()),
            'resources' => count($this->registry->getResources()),
            'prompts' => count($this->registry->getPrompts()),
        ];
    }

    /**
     * Get performance status.
     */
    private function getPerformanceStatus(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'uptime' => $this->getUptime(),
            'requests_processed' => $this->metrics['requests_processed'] ?? 0,
        ];
    }

    /**
     * Check if a health check is passing.
     */
    private function isHealthCheckPassing(string $check): array
    {
        switch ($check) {
            case 'server_initialized':
                return [
                    'healthy' => $this->initialized,
                    'message' => $this->initialized ? 'Server is initialized' : 'Server not initialized',
                ];

            case 'server_running':
                return [
                    'healthy' => $this->running,
                    'message' => $this->running ? 'Server is running' : 'Server not running',
                ];

            case 'transports_healthy':
                $activeCount = $this->transportManager->getActiveTransportCount();

                return [
                    'healthy' => $activeCount > 0,
                    'message' => "Active transports: {$activeCount}",
                ];

            case 'components_registered':
                $componentCount = count($this->registry->getTools()) +
                                count($this->registry->getResources()) +
                                count($this->registry->getPrompts());

                return [
                    'healthy' => $componentCount > 0,
                    'message' => "Registered components: {$componentCount}",
                ];

            case 'memory_usage':
                $memoryUsage = memory_get_usage(true);
                $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
                $memoryPercent = $memoryLimit > 0 ? ($memoryUsage / $memoryLimit) * 100 : 0;

                return [
                    'healthy' => $memoryPercent < 90,
                    'message' => sprintf('Memory usage: %.1f%%', $memoryPercent),
                ];

            default:
                return [
                    'healthy' => true,
                    'message' => 'Unknown check',
                ];
        }
    }

    /**
     * Parse memory limit to bytes.
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return 0; // No limit
        }

        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));

        switch ($unit) {
            case 'g':
                $value *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $value *= 1024 * 1024;
                break;
            case 'k':
                $value *= 1024;
                break;
        }

        return $value;
    }

    /**
     * Cleanup resources.
     */
    private function cleanupResources(): void
    {
        // Cleanup transports
        foreach ($this->transports as $name => $transport) {
            try {
                $transport->disconnect();
            } catch (\Throwable $e) {
                Log::warning("Error disconnecting transport {$name}: {$e->getMessage()}");
            }
        }

        // Clear state
        $this->initialized = false;
        $this->running = false;
        $this->clientCapabilities = [];
    }

    /**
     * Increment request counter.
     */
    public function incrementRequestCount(): void
    {
        $this->metrics['requests_processed'] = ($this->metrics['requests_processed'] ?? 0) + 1;
    }

    /**
     * Increment error counter.
     */
    public function incrementErrorCount(): void
    {
        $this->metrics['errors_count'] = ($this->metrics['errors_count'] ?? 0) + 1;
    }

    /**
     * Get detailed server diagnostics.
     */
    public function getDiagnostics(): array
    {
        return [
            'server' => $this->getStatus(),
            'health' => $this->getHealth(),
            'metrics' => $this->getMetrics(),
            'capabilities' => $this->capabilityManager->getDetailedCapabilityInfo(),
            'configuration' => $this->configuration,
        ];
    }

    // MessageHandlerInterface implementation

    /**
     * Handle an incoming MCP message.
     *
     * @param  array  $message  The received MCP message
     * @param  TransportInterface  $transport  The transport that received the message
     * @return array|null Response message to send back, or null for no response
     */
    public function handle(array $message, TransportInterface $transport): ?array
    {
        try {
            // Process the message through the message processor
            return $this->messageProcessor->processMessage($message);
        } catch (\Throwable $e) {
            Log::error('Error handling message', [
                'message' => $message,
                'error' => $e->getMessage(),
            ]);
            
            // Return JSON-RPC error response
            return [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error: ' . $e->getMessage(),
                ],
                'id' => $message['id'] ?? null,
            ];
        }
    }

    /**
     * Handle a transport error.
     *
     * @param  \Throwable  $error  The error that occurred
     * @param  TransportInterface  $transport  The transport where the error occurred
     */
    public function handleError(\Throwable $error, TransportInterface $transport): void
    {
        Log::error('Transport error occurred', [
            'transport' => get_class($transport),
            'error' => $error->getMessage(),
            'trace' => $error->getTraceAsString(),
        ]);
        
        $this->incrementErrorCount();
    }

    /**
     * Handle transport connection establishment.
     *
     * @param  TransportInterface  $transport  The transport that connected
     */
    public function onConnect(TransportInterface $transport): void
    {
        Log::info('Transport connected', [
            'transport' => get_class($transport),
        ]);
        
        // Register the transport
        $transportName = spl_object_hash($transport);
        $this->registerTransport($transportName, $transport);
    }

    /**
     * Handle transport connection closure.
     *
     * @param  TransportInterface  $transport  The transport that disconnected
     */
    public function onDisconnect(TransportInterface $transport): void
    {
        Log::info('Transport disconnected', [
            'transport' => get_class($transport),
        ]);
        
        // Remove the transport
        $transportName = spl_object_hash($transport);
        $this->removeTransport($transportName);
    }

    /**
     * Check if the handler can process a specific message type.
     *
     * @param  array  $message  The message to check
     */
    public function canHandle(array $message): bool
    {
        // McpServer can handle all JSON-RPC messages
        return isset($message['jsonrpc']) && $message['jsonrpc'] === '2.0';
    }

    /**
     * Get supported message types by this handler.
     *
     * @return array Array of supported message types
     */
    public function getSupportedMessageTypes(): array
    {
        // Return all MCP protocol methods
        return [
            'initialize',
            'initialized',
            'shutdown',
            'ping',
            'tools/list',
            'tools/call',
            'resources/list',
            'resources/read',
            'resources/subscribe',
            'resources/unsubscribe',
            'prompts/list',
            'prompts/get',
            'logging/setLevel',
            'logging/getLevels',
            'sampling/createMessage',
            'completion/complete',
            'roots/list',
        ];
    }

}
