# MCP Server Specification

## Overview

The MCP Server is the core component that implements the Model Context Protocol specification. It handles client connections, processes JSON-RPC messages, manages capabilities, and orchestrates communication between AI clients and Laravel application components.

## Server Architecture

### Core Components
```
McpServer
├── Protocol Layer
│   ├── JsonRpcHandler           # JSON-RPC 2.0 implementation
│   ├── MessageProcessor         # MCP message processing
│   ├── CapabilityNegotiator     # Client capability negotiation
│   └── NotificationHandler      # Real-time notifications
├── Transport Layer
│   ├── HttpTransport            # HTTP-based transport
│   ├── StdioTransport           # Stdio-based transport
│   └── TransportManager         # Transport orchestration
├── Registry Layer
│   ├── McpRegistry              # Component registry
│   ├── ToolRegistry             # Tool management
│   ├── ResourceRegistry         # Resource management
│   └── PromptRegistry           # Prompt management
└── Integration Layer
    ├── LaravelBridge            # Laravel service integration
    ├── SecurityManager          # Authentication & authorization
    └── ErrorHandler             # Error management
```

## Server Implementation

### Main Server Class
```php
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
use JTD\LaravelMCP\Server\RequestContext;
use JTD\LaravelMCP\Server\RequestPipeline;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use Illuminate\Support\Str;

class McpServer implements MessageHandlerInterface, ServerInterface
{
    private ServerInfo $serverInfo;
    private CapabilityManager $capabilityManager;
    private MessageProcessor $messageProcessor;
    private TransportManager $transportManager;
    private McpRegistry $registry;
    private RequestPipeline $requestPipeline;
    private bool $initialized = false;
    private bool $running = false;
    private array $configuration = [];
    private array $transports = [];
    private array $clientCapabilities = [];
    private array $metrics = [];
    private array $activeRequests = [];

    public function __construct(
        ServerInfo $serverInfo,
        CapabilityManager $capabilityManager,
        MessageProcessor $messageProcessor,
        TransportManager $transportManager,
        McpRegistry $registry,
        RequestPipeline $requestPipeline
    ) {
        $this->serverInfo = $serverInfo;
        $this->capabilityManager = $capabilityManager;
        $this->messageProcessor = $messageProcessor;
        $this->transportManager = $transportManager;
        $this->registry = $registry;
        $this->requestPipeline = $requestPipeline;
        
        $this->initializeConfiguration();
        $this->initializeMetrics();
    }
}
```

## Protocol Implementation

### Request Context Management
```php
<?php

namespace JTD\LaravelMCP\Server;

use Carbon\Carbon;
use Illuminate\Support\Str;

class RequestContext
{
    public string $requestId;
    public string $clientId;
    public array $metadata;
    public Carbon $startTime;
    public ?Carbon $endTime = null;
    public string $method;
    public array $params;
    public ?string $transportType = null;
    public array $capabilities = [];
    public array $metrics = [];

    public function __construct(
        string $method,
        array $params = [],
        string $clientId = 'unknown',
        array $metadata = []
    ) {
        $this->requestId = Str::uuid()->toString();
        $this->clientId = $clientId;
        $this->metadata = $metadata;
        $this->startTime = now();
        $this->method = $method;
        $this->params = $params;
    }

    public function complete(): void
    {
        $this->endTime = now();
        $this->metrics['duration_ms'] = $this->startTime->diffInMilliseconds($this->endTime);
    }

    public function getDuration(): int
    {
        $endTime = $this->endTime ?? now();
        return $this->startTime->diffInMilliseconds($endTime);
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    public function toArray(): array
    {
        return [
            'request_id' => $this->requestId,
            'client_id' => $this->clientId,
            'method' => $this->method,
            'params' => $this->params,
            'transport_type' => $this->transportType,
            'start_time' => $this->startTime->toISOString(),
            'end_time' => $this->endTime?->toISOString(),
            'duration_ms' => $this->getDuration(),
            'metadata' => $this->metadata,
            'metrics' => $this->metrics,
        ];
    }
}
```

### Request Pipeline
```php
<?php

namespace JTD\LaravelMCP\Server;

use Closure;
use JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpRateLimitMiddleware;
use JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware;

class RequestPipeline
{
    protected array $middleware = [
        McpValidationMiddleware::class,
        McpAuthMiddleware::class,
        McpRateLimitMiddleware::class,
        McpLoggingMiddleware::class,
    ];

    protected array $asyncMethods = [
        'tools/call',
        'resources/read',
        'prompts/get',
        'completion/complete',
    ];

    public function process(RequestContext $context, Closure $next): mixed
    {
        // Check if request should be processed asynchronously
        if ($this->shouldProcessAsync($context)) {
            return $this->processAsync($context);
        }

        // Process through middleware pipeline
        return $this->processSync($context, $next);
    }

    private function shouldProcessAsync(RequestContext $context): bool
    {
        return in_array($context->method, $this->asyncMethods) &&
               config('laravel-mcp.async.enabled', false);
    }

    private function processAsync(RequestContext $context): array
    {
        $jobId = Str::uuid()->toString();
        
        ProcessMcpRequest::dispatch([
            'method' => $context->method,
            'params' => $context->params,
            'context' => $context->toArray(),
        ], $jobId);

        return [
            'jsonrpc' => '2.0',
            'result' => [
                'async' => true,
                'job_id' => $jobId,
                'status' => 'queued',
                'estimated_completion' => now()->addSeconds(30)->toISOString(),
            ],
            'id' => $context->requestId,
        ];
    }

    private function processSync(RequestContext $context, Closure $next): mixed
    {
        $pipeline = array_reverse($this->middleware);
        
        return array_reduce($pipeline, function ($stack, $middleware) {
            return function ($passable) use ($stack, $middleware) {
                return app($middleware)->handle($passable, $stack);
            };
        }, $next)($context);
    }
}
```

### Enhanced JSON-RPC Handler
```php
<?php

namespace JTD\LaravelMCP\Protocol;

use JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Server\RequestContext;
use JTD\LaravelMCP\Server\RequestPipeline;
use Illuminate\Support\Facades\Log;

class JsonRpcHandler implements JsonRpcHandlerInterface
{
    private RequestPipeline $pipeline;
    private array $circuitBreakers = [];
    private array $retryConfig = [];

    public function __construct(RequestPipeline $pipeline)
    {
        $this->pipeline = $pipeline;
        $this->initializeRetryConfig();
    }

    public function processRequest(string $rawMessage): string
    {
        $context = null;
        
        try {
            $request = $this->parseRequest($rawMessage);
            $this->validateRequest($request);
            
            $context = new RequestContext(
                $request['method'],
                $request['params'] ?? [],
                $request['client_id'] ?? 'unknown',
                ['raw_message_size' => strlen($rawMessage)]
            );
            
            // Check circuit breaker
            if ($this->isCircuitOpen($request['method'])) {
                throw new ProtocolException('Service temporarily unavailable');
            }
            
            $response = $this->pipeline->process($context, function ($ctx) use ($request) {
                return $this->handleRequestWithRetry($request, $ctx);
            });
            
            $context->complete();
            return $this->formatResponse($response, $request['id'] ?? null);
            
        } catch (\Throwable $e) {
            if ($context) {
                $context->complete();
                $this->recordFailure($context->method);
            }
            
            Log::error('JSON-RPC request failed', [
                'error' => $e->getMessage(),
                'context' => $context?->toArray(),
            ]);
            
            return $this->formatError($e, $request['id'] ?? null);
        }
    }

    private function handleRequestWithRetry(array $request, RequestContext $context): mixed
    {
        $method = $request['method'];
        $maxRetries = $this->retryConfig[$method]['max_retries'] ?? 3;
        $backoffMs = $this->retryConfig[$method]['backoff_ms'] ?? 100;
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                return $this->handleRequest($request, $context);
            } catch (\Throwable $e) {
                if ($attempt === $maxRetries || !$this->shouldRetry($e)) {
                    throw $e;
                }
                
                usleep($backoffMs * 1000 * pow(2, $attempt - 1)); // Exponential backoff
            }
        }
    }

    private function handleRequest(array $request, RequestContext $context): mixed
    {
        $method = $request['method'];
        $params = $request['params'] ?? [];
        
        return match ($method) {
            'initialize' => $this->handleInitialize($params, $context),
            'tools/list' => $this->handleToolsList($params, $context),
            'tools/call' => $this->handleToolsCall($params, $context),
            'resources/list' => $this->handleResourcesList($params, $context),
            'resources/read' => $this->handleResourcesRead($params, $context),
            'resources/subscribe' => $this->handleResourcesSubscribe($params, $context),
            'resources/unsubscribe' => $this->handleResourcesUnsubscribe($params, $context),
            'prompts/list' => $this->handlePromptsList($params, $context),
            'prompts/get' => $this->handlePromptsGet($params, $context),
            'completion/complete' => $this->handleCompletionComplete($params, $context),
            'sampling/createMessage' => $this->handleSamplingCreateMessage($params, $context),
            'roots/list' => $this->handleRootsList($params, $context),
            'logging/setLevel' => $this->handleLoggingSetLevel($params, $context),
            default => throw new ProtocolException("Unknown method: $method")
        };
    }

    private function isCircuitOpen(string $method): bool
    {
        $breaker = $this->circuitBreakers[$method] ?? null;
        if (!$breaker) {
            return false;
        }
        
        $now = time();
        $threshold = config('laravel-mcp.circuit_breaker.failure_threshold', 5);
        $timeWindow = config('laravel-mcp.circuit_breaker.time_window', 60);
        
        if ($breaker['failures'] >= $threshold && 
            ($now - $breaker['last_failure']) < $timeWindow) {
            return true;
        }
        
        // Reset if window has passed
        if (($now - $breaker['last_failure']) >= $timeWindow) {
            unset($this->circuitBreakers[$method]);
        }
        
        return false;
    }

    private function recordFailure(string $method): void
    {
        if (!isset($this->circuitBreakers[$method])) {
            $this->circuitBreakers[$method] = ['failures' => 0, 'last_failure' => 0];
        }
        
        $this->circuitBreakers[$method]['failures']++;
        $this->circuitBreakers[$method]['last_failure'] = time();
    }

    private function shouldRetry(\Throwable $e): bool
    {
        // Don't retry validation errors or authentication failures
        return !($e instanceof ProtocolException && 
                str_contains($e->getMessage(), 'validation'));
    }

    private function initializeRetryConfig(): void
    {
        $this->retryConfig = config('laravel-mcp.retry', [
            'tools/call' => ['max_retries' => 3, 'backoff_ms' => 100],
            'resources/read' => ['max_retries' => 2, 'backoff_ms' => 50],
            'prompts/get' => ['max_retries' => 2, 'backoff_ms' => 50],
        ]);
    }
}
```

### Enhanced Message Processor
```php
<?php

namespace JTD\LaravelMCP\Protocol;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Protocol\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Server\RequestContext;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class MessageProcessor implements MessageHandlerInterface
{
    private McpRegistry $registry;
    private array $messageHandlers = [];
    private array $serverInfo = [];
    private array $streamingConnections = [];
    private array $subscriptions = [];

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
        $this->registerMessageHandlers();
    }

    public function processMessage(array $message): ?array
    {
        $method = $message['method'] ?? null;
        $params = $message['params'] ?? [];
        $id = $message['id'] ?? null;
        
        if (!$method) {
            throw new ProtocolException('Missing method parameter');
        }

        if (!isset($this->messageHandlers[$method])) {
            throw new ProtocolException("No handler for method: $method");
        }

        try {
            $handler = $this->messageHandlers[$method];
            $result = $handler($params);
            
            // Emit event for monitoring
            Event::dispatch(new McpRequestProcessed($method, $params, $result));
            
            // Return null for notifications (no id)
            if ($id === null) {
                return null;
            }
            
            return [
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $id,
            ];
            
        } catch (\Throwable $e) {
            Log::error("Message processing failed for method: $method", [
                'error' => $e->getMessage(),
                'params' => $params,
            ]);
            
            if ($id === null) {
                return null;
            }
            
            return [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                ],
                'id' => $id,
            ];
        }
    }

    public function setServerInfo(array $serverInfo): void
    {
        $this->serverInfo = $serverInfo;
    }

    private function registerMessageHandlers(): void
    {
        $this->messageHandlers = [
            // Core MCP methods
            'initialize' => [$this, 'handleInitialize'],
            'initialized' => [$this, 'handleInitialized'],
            'shutdown' => [$this, 'handleShutdown'],
            'ping' => [$this, 'handlePing'],
            
            // Tools
            'tools/list' => [$this, 'handleToolsList'],
            'tools/call' => [$this, 'handleToolCall'],
            
            // Resources
            'resources/list' => [$this, 'handleResourcesList'],
            'resources/read' => [$this, 'handleResourceRead'],
            'resources/subscribe' => [$this, 'handleResourceSubscribe'],
            'resources/unsubscribe' => [$this, 'handleResourceUnsubscribe'],
            
            // Prompts
            'prompts/list' => [$this, 'handlePromptsList'],
            'prompts/get' => [$this, 'handlePromptGet'],
            
            // Advanced features
            'completion/complete' => [$this, 'handleCompletion'],
            'sampling/createMessage' => [$this, 'handleSamplingCreateMessage'],
            'roots/list' => [$this, 'handleRootsList'],
            
            // Logging
            'logging/setLevel' => [$this, 'handleLoggingSetLevel'],
        ];
    }

    public function handleInitialize(array $params): array
    {
        return [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $this->getServerCapabilities(),
            'serverInfo' => array_merge([
                'name' => config('laravel-mcp.server.name', 'Laravel MCP Server'),
                'version' => $this->getServerVersion(),
            ], $this->serverInfo),
        ];
    }

    public function handleInitialized(array $params): array
    {
        Log::info('MCP client initialized');
        return ['acknowledged' => true];
    }

    public function handleShutdown(array $params): array
    {
        Log::info('MCP shutdown requested');
        return ['acknowledged' => true];
    }

    public function handlePing(array $params): array
    {
        return ['pong' => true, 'timestamp' => now()->toISOString()];
    }

    public function handleResourceSubscribe(array $params): array
    {
        $uri = $params['uri'] ?? throw new ProtocolException('URI required for subscription');
        
        $subscriptionId = uniqid('sub_');
        $this->subscriptions[$subscriptionId] = [
            'uri' => $uri,
            'created_at' => now(),
        ];
        
        Log::info("Resource subscription created: $uri", ['subscription_id' => $subscriptionId]);
        
        return [
            'subscription_id' => $subscriptionId,
            'uri' => $uri,
        ];
    }

    public function handleResourceUnsubscribe(array $params): array
    {
        $subscriptionId = $params['subscription_id'] ?? throw new ProtocolException('Subscription ID required');
        
        if (isset($this->subscriptions[$subscriptionId])) {
            unset($this->subscriptions[$subscriptionId]);
            Log::info("Resource subscription removed: $subscriptionId");
        }
        
        return ['acknowledged' => true];
    }

    public function handleSamplingCreateMessage(array $params): array
    {
        $messages = $params['messages'] ?? throw new ProtocolException('Messages required');
        $maxTokens = $params['max_tokens'] ?? 1000;
        $modelPreferences = $params['model_preferences'] ?? [];
        
        // This would integrate with an AI service
        // For now, return a mock response
        return [
            'model' => $modelPreferences['model'] ?? 'claude-3-sonnet',
            'stopReason' => 'end_turn',
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'This is a mock response for sampling/createMessage',
                ]
            ],
        ];
    }

    public function handleCompletion(array $params): array
    {
        $argument = $params['argument'] ?? throw new ProtocolException('Argument required for completion');
        
        // This would integrate with completion services
        // For now, return mock completions
        return [
            'completion' => [
                'values' => [
                    $argument . '_option1',
                    $argument . '_option2',
                    $argument . '_option3',
                ],
                'total' => 3,
                'hasMore' => false,
            ]
        ];
    }

    public function handleRootsList(array $params): array
    {
        // Return available filesystem roots
        $roots = config('laravel-mcp.roots', [
            ['name' => 'project', 'uri' => 'file://' . base_path()],
            ['name' => 'storage', 'uri' => 'file://' . storage_path()],
        ]);
        
        return ['roots' => $roots];
    }

    public function handleLoggingSetLevel(array $params): array
    {
        $level = $params['level'] ?? throw new ProtocolException('Level required');
        
        // This would set the logging level
        Log::info("Logging level set to: $level");
        
        return ['acknowledged' => true, 'level' => $level];
    }

    public function handleToolsList(array $params): array
    {
        $tools = $this->registry->getTools();
        
        return [
            'tools' => array_map(function ($tool) {
                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => $tool->getInputSchema(),
                ];
            }, $tools),
        ];
    }

    public function handleToolCall(array $params): array
    {
        $name = $params['name'] ?? throw new ProtocolException('Tool name is required');
        $arguments = $params['arguments'] ?? [];

        $tool = $this->registry->getTool($name);
        if (!$tool) {
            throw new ProtocolException("Tool not found: $name");
        }

        $result = $tool->execute($arguments);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => is_string($result) ? $result : json_encode($result),
                ]
            ],
            'isError' => false,
        ];
    }

    private function getServerCapabilities(): array
    {
        return config('laravel-mcp.capabilities', [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
            'completion' => ['argument_completion' => true],
            'logging' => [],
            'sampling' => [],
        ]);
    }

    private function getServerVersion(): string
    {
        return config('laravel-mcp.server.version', '1.0.0');
    }

    // Additional handler methods would follow the same pattern...
}
```

### Enhanced Capability Negotiation
```php
<?php

namespace JTD\LaravelMCP\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;

class CapabilityNegotiator
{
    private array $serverCapabilities = [];
    private array $clientCapabilities = [];
    private array $negotiatedCapabilities = [];
    private array $capabilityVersions = [];
    private array $featureCompatibilityMatrix = [];

    public function __construct()
    {
        $this->initializeServerCapabilities();
        $this->initializeCompatibilityMatrix();
    }

    public function negotiateCapabilities(array $clientCapabilities): array
    {
        $this->clientCapabilities = $clientCapabilities;
        
        // Validate protocol version compatibility
        $this->validateProtocolVersion($clientCapabilities);
        
        // Negotiate each capability
        $this->negotiatedCapabilities = $this->performNegotiation();
        
        Log::info('Capability negotiation completed', [
            'client_capabilities' => $clientCapabilities,
            'server_capabilities' => $this->serverCapabilities,
            'negotiated_capabilities' => $this->negotiatedCapabilities,
        ]);
        
        return [
            'capabilities' => $this->negotiatedCapabilities,
            'protocolVersion' => $this->getProtocolVersion(),
            'negotiation_info' => $this->getNegotiationInfo(),
        ];
    }

    public function getNegotiatedCapabilities(): array
    {
        return $this->negotiatedCapabilities;
    }

    public function getDetailedCapabilityInfo(): array
    {
        return [
            'server_capabilities' => $this->serverCapabilities,
            'client_capabilities' => $this->clientCapabilities,
            'negotiated_capabilities' => $this->negotiatedCapabilities,
            'compatibility_matrix' => $this->featureCompatibilityMatrix,
            'version_support' => $this->capabilityVersions,
        ];
    }

    private function validateProtocolVersion(array $clientCapabilities): void
    {
        $clientVersion = $clientCapabilities['protocolVersion'] ?? null;
        $serverVersion = $this->getProtocolVersion();
        
        if ($clientVersion && !$this->isVersionCompatible($clientVersion, $serverVersion)) {
            throw new ProtocolException(
                "Protocol version mismatch. Client: $clientVersion, Server: $serverVersion"
            );
        }
    }

    private function performNegotiation(): array
    {
        $negotiated = [];

        // Start with server capabilities as base
        foreach ($this->serverCapabilities as $capability => $features) {
            if ($this->clientSupports($capability)) {
                $negotiated[$capability] = $this->negotiateCapability(
                    $capability,
                    $features,
                    $this->clientCapabilities[$capability] ?? []
                );
            } else {
                // Check if we can provide fallback support
                $fallback = $this->getFallbackCapability($capability);
                if ($fallback) {
                    $negotiated[$capability] = $fallback;
                }
            }
        }

        // Add client-specific capabilities we can support
        foreach ($this->clientCapabilities as $capability => $features) {
            if (!isset($negotiated[$capability]) && $this->canSupportCapability($capability)) {
                $defaultFeatures = $this->getDefaultCapabilityFeatures($capability);
                $negotiated[$capability] = $this->negotiateCapability(
                    $capability,
                    $defaultFeatures,
                    $features
                );
            }
        }

        return $negotiated;
    }

    private function negotiateCapability(string $capability, array $serverFeatures, array $clientFeatures): array
    {
        $negotiated = [];
        
        // Handle version-specific negotiation
        if (isset($this->capabilityVersions[$capability])) {
            $serverVersion = $this->capabilityVersions[$capability]['current'];
            $clientVersion = $clientFeatures['version'] ?? $serverVersion;
            
            if (!$this->isVersionCompatible($clientVersion, $serverVersion)) {
                // Use fallback version
                $fallbackVersion = $this->capabilityVersions[$capability]['fallback'] ?? $serverVersion;
                $negotiated['version'] = $fallbackVersion;
                Log::warning("Using fallback version for $capability", [
                    'client_version' => $clientVersion,
                    'server_version' => $serverVersion,
                    'fallback_version' => $fallbackVersion,
                ]);
            } else {
                $negotiated['version'] = $clientVersion;
            }
        }

        // Negotiate individual features
        foreach ($serverFeatures as $feature => $value) {
            if ($this->isFeatureCompatible($capability, $feature, $clientFeatures)) {
                $negotiated[$feature] = $this->resolveFeatureValue($feature, $value, $clientFeatures[$feature] ?? null);
            } else {
                // Try fallback
                $fallbackValue = $this->getFeatureFallback($capability, $feature);
                if ($fallbackValue !== null) {
                    $negotiated[$feature] = $fallbackValue;
                }
            }
        }

        return $negotiated;
    }

    private function isVersionCompatible(string $clientVersion, string $serverVersion): bool
    {
        // Simple version compatibility check
        // In practice, this would implement semantic version comparison
        return version_compare($clientVersion, $serverVersion, '>=');
    }

    private function isFeatureCompatible(string $capability, string $feature, array $clientFeatures): bool
    {
        $matrix = $this->featureCompatibilityMatrix[$capability] ?? [];
        $featureInfo = $matrix[$feature] ?? [];
        
        // Check if client supports this feature
        if (!array_key_exists($feature, $clientFeatures)) {
            return $featureInfo['optional'] ?? true;
        }
        
        // Check feature constraints
        if (isset($featureInfo['requires'])) {
            foreach ($featureInfo['requires'] as $requiredFeature) {
                if (!isset($clientFeatures[$requiredFeature])) {
                    return false;
                }
            }
        }
        
        return true;
    }

    private function resolveFeatureValue(string $feature, mixed $serverValue, mixed $clientValue): mixed
    {
        // If client doesn't specify a value, use server value
        if ($clientValue === null) {
            return $serverValue;
        }
        
        // For boolean features, both must agree
        if (is_bool($serverValue) && is_bool($clientValue)) {
            return $serverValue && $clientValue;
        }
        
        // For numeric features, use minimum
        if (is_numeric($serverValue) && is_numeric($clientValue)) {
            return min($serverValue, $clientValue);
        }
        
        // Default to server value
        return $serverValue;
    }

    private function getFallbackCapability(string $capability): ?array
    {
        $fallbacks = [
            'resources' => ['subscribe' => false, 'listChanged' => false],
            'prompts' => ['listChanged' => false],
            'completion' => ['argument_completion' => false],
        ];
        
        return $fallbacks[$capability] ?? null;
    }

    private function getFeatureFallback(string $capability, string $feature): mixed
    {
        $fallbacks = [
            'resources' => [
                'subscribe' => false,
                'listChanged' => false,
            ],
            'tools' => [
                'listChanged' => false,
            ],
        ];
        
        return $fallbacks[$capability][$feature] ?? null;
    }

    private function canSupportCapability(string $capability): bool
    {
        $supportedCapabilities = [
            'tools', 'resources', 'prompts', 'logging', 
            'completion', 'sampling', 'roots'
        ];
        
        return in_array($capability, $supportedCapabilities);
    }

    private function getDefaultCapabilityFeatures(string $capability): array
    {
        $defaults = [
            'tools' => ['listChanged' => false],
            'resources' => ['subscribe' => false, 'listChanged' => false],
            'prompts' => ['listChanged' => false],
            'logging' => [],
            'completion' => ['argument_completion' => false],
            'sampling' => [],
            'roots' => [],
        ];
        
        return $defaults[$capability] ?? [];
    }

    private function clientSupports(string $capability): bool
    {
        return isset($this->clientCapabilities[$capability]);
    }

    private function initializeServerCapabilities(): void
    {
        $this->serverCapabilities = [
            'tools' => [
                'listChanged' => config('laravel-mcp.capabilities.tools.list_changed_notifications', true),
            ],
            'resources' => [
                'listChanged' => config('laravel-mcp.capabilities.resources.list_changed_notifications', true),
                'subscribe' => config('laravel-mcp.capabilities.resources.subscriptions', true),
            ],
            'prompts' => [
                'listChanged' => config('laravel-mcp.capabilities.prompts.list_changed_notifications', true),
            ],
            'completion' => [
                'argument_completion' => config('laravel-mcp.capabilities.completion.enabled', true),
            ],
            'logging' => [
                'level' => config('laravel-mcp.logging.level', 'info'),
            ],
            'sampling' => [],
            'roots' => [],
        ];
    }

    private function initializeCompatibilityMatrix(): void
    {
        $this->featureCompatibilityMatrix = [
            'resources' => [
                'subscribe' => [
                    'optional' => true,
                    'requires' => ['listChanged'],
                ],
                'listChanged' => [
                    'optional' => false,
                ],
            ],
            'tools' => [
                'listChanged' => [
                    'optional' => false,
                ],
            ],
        ];
        
        $this->capabilityVersions = [
            'tools' => ['current' => '1.0', 'fallback' => '1.0'],
            'resources' => ['current' => '1.1', 'fallback' => '1.0'],
            'prompts' => ['current' => '1.0', 'fallback' => '1.0'],
        ];
    }

    private function getProtocolVersion(): string
    {
        return config('laravel-mcp.protocol.version', '2024-11-05');
    }

    private function getNegotiationInfo(): array
    {
        return [
            'negotiation_timestamp' => now()->toISOString(),
            'server_version' => $this->getProtocolVersion(),
            'features_count' => count(array_flatten($this->negotiatedCapabilities)),
            'fallbacks_used' => $this->countFallbacksUsed(),
        ];
    }

    private function countFallbacksUsed(): int
    {
        // This would track how many fallback features were used
        // Implementation would depend on tracking during negotiation
        return 0;
    }
}
```

## Transport Integration

### Message Framing Specifications

#### Stdio Transport Message Framing
```php
<?php

namespace JTD\LaravelMCP\Transport\Framing;

interface MessageFramerInterface
{
    public function frame(string $message): string;
    public function unframe(string $data): array;
    public function isComplete(string $buffer): bool;
}

class StdioFramer implements MessageFramerInterface
{
    private const FRAME_DELIMITER = "\r\n\r\n";
    
    public function frame(string $message): string
    {
        $contentLength = strlen($message);
        return "Content-Length: {$contentLength}" . self::FRAME_DELIMITER . $message;
    }
    
    public function unframe(string $data): array
    {
        $messages = [];
        $position = 0;
        
        while ($position < strlen($data)) {
            $headerEnd = strpos($data, self::FRAME_DELIMITER, $position);
            
            if ($headerEnd === false) {
                break; // Incomplete frame
            }
            
            $headers = substr($data, $position, $headerEnd - $position);
            $contentLength = $this->parseContentLength($headers);
            
            if ($contentLength === null) {
                throw new TransportException('Invalid Content-Length header');
            }
            
            $messageStart = $headerEnd + strlen(self::FRAME_DELIMITER);
            $messageEnd = $messageStart + $contentLength;
            
            if ($messageEnd > strlen($data)) {
                break; // Incomplete message
            }
            
            $message = substr($data, $messageStart, $contentLength);
            $messages[] = $message;
            
            $position = $messageEnd;
        }
        
        return $messages;
    }
    
    public function isComplete(string $buffer): bool
    {
        $headerEnd = strpos($buffer, self::FRAME_DELIMITER);
        
        if ($headerEnd === false) {
            return false;
        }
        
        $headers = substr($buffer, 0, $headerEnd);
        $contentLength = $this->parseContentLength($headers);
        
        if ($contentLength === null) {
            return false;
        }
        
        $messageStart = $headerEnd + strlen(self::FRAME_DELIMITER);
        return strlen($buffer) >= $messageStart + $contentLength;
    }
    
    private function parseContentLength(string $headers): ?int
    {
        if (preg_match('/Content-Length:\s*(\d+)/i', $headers, $matches)) {
            return (int) $matches[1];
        }
        
        return null;
    }
}
```

#### HTTP Transport Message Framing
```php
<?php

namespace JTD\LaravelMCP\Transport\Framing;

class HttpFramer implements MessageFramerInterface
{
    public function frame(string $message): string
    {
        // HTTP transport doesn't need special framing
        // Laravel handles HTTP request/response framing
        return $message;
    }
    
    public function unframe(string $data): array
    {
        // Single message per HTTP request
        return [$data];
    }
    
    public function isComplete(string $buffer): bool
    {
        // HTTP requests are always complete when received
        return true;
    }
}
```

#### WebSocket Transport Message Framing
```php
<?php

namespace JTD\LaravelMCP\Transport\Framing;

use Ratchet\RFC6455\Messaging\MessageInterface;

class WebSocketFramer implements MessageFramerInterface
{
    public function frame(string $message): string
    {
        // WebSocket framing is handled by the WebSocket library
        // This just ensures proper JSON structure
        return json_encode(json_decode($message, true), JSON_UNESCAPED_SLASHES);
    }
    
    public function unframe(string $data): array
    {
        // WebSocket messages are already framed
        return [$data];
    }
    
    public function isComplete(string $buffer): bool
    {
        // WebSocket messages are always complete
        return true;
    }
}
```

### Enhanced Transport Implementations

#### Stdio Transport with Streaming
```php
<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Transport\Framing\StdioFramer;
use JTD\LaravelMCP\Transport\Contracts\StreamingTransportInterface;

class StdioTransport extends BaseTransport implements StreamingTransportInterface
{
    private StdioFramer $framer;
    private $inputStream;
    private $outputStream;
    private string $inputBuffer = '';
    private bool $streamingMode = false;
    private array $streamingConnections = [];
    
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->framer = new StdioFramer();
        $this->inputStream = STDIN;
        $this->outputStream = STDOUT;
    }
    
    protected function doStart(): void
    {
        // Set non-blocking mode for streaming
        if ($this->config['streaming'] ?? false) {
            stream_set_blocking($this->inputStream, false);
            $this->streamingMode = true;
        }
        
        Log::info('Stdio MCP transport started', [
            'streaming_mode' => $this->streamingMode,
            'config' => $this->config,
        ]);
    }
    
    protected function doStop(): void
    {
        // Close streaming connections
        foreach ($this->streamingConnections as $connection) {
            $this->closeStreamingConnection($connection);
        }
        
        $this->streamingConnections = [];
        Log::info('Stdio MCP transport stopped');
    }
    
    public function send(string $message): bool
    {
        try {
            $framedMessage = $this->framer->frame($message);
            $bytesWritten = fwrite($this->outputStream, $framedMessage);
            fflush($this->outputStream);
            
            $this->updateStatistics('sent', strlen($framedMessage));
            
            return $bytesWritten !== false;
        } catch (\Throwable $e) {
            Log::error('Failed to send message via Stdio', [
                'error' => $e->getMessage(),
                'message_length' => strlen($message),
            ]);
            return false;
        }
    }
    
    public function receive(): ?string
    {
        try {
            if ($this->streamingMode) {
                return $this->receiveStreaming();
            }
            
            return $this->receiveBlocking();
        } catch (\Throwable $e) {
            Log::error('Failed to receive message via Stdio', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
    
    private function receiveStreaming(): ?string
    {
        // Read available data without blocking
        $data = fread($this->inputStream, 8192);
        
        if ($data === false || $data === '') {
            return null;
        }
        
        $this->inputBuffer .= $data;
        
        // Try to extract complete messages
        $messages = $this->framer->unframe($this->inputBuffer);
        
        if (empty($messages)) {
            return null;
        }
        
        // Remove processed data from buffer
        $processedLength = $this->calculateProcessedLength($messages);
        $this->inputBuffer = substr($this->inputBuffer, $processedLength);
        
        // Return first complete message
        $message = array_shift($messages);
        $this->updateStatistics('received', strlen($message));
        
        return $message;
    }
    
    private function receiveBlocking(): ?string
    {
        $message = '';
        
        while (!$this->framer->isComplete($this->inputBuffer)) {
            $data = fread($this->inputStream, 1024);
            
            if ($data === false || feof($this->inputStream)) {
                return null;
            }
            
            $this->inputBuffer .= $data;
        }
        
        $messages = $this->framer->unframe($this->inputBuffer);
        
        if (empty($messages)) {
            return null;
        }
        
        $message = array_shift($messages);
        $processedLength = $this->calculateProcessedLength([$message]);
        $this->inputBuffer = substr($this->inputBuffer, $processedLength);
        
        $this->updateStatistics('received', strlen($message));
        
        return $message;
    }
    
    // Streaming interface implementation
    public function startStreaming(string $streamId, array $options = []): bool
    {
        $this->streamingConnections[$streamId] = [
            'id' => $streamId,
            'created_at' => now(),
            'options' => $options,
            'message_count' => 0,
        ];
        
        Log::info("Started streaming connection: $streamId");
        return true;
    }
    
    public function stopStreaming(string $streamId): bool
    {
        if (isset($this->streamingConnections[$streamId])) {
            $this->closeStreamingConnection($this->streamingConnections[$streamId]);
            unset($this->streamingConnections[$streamId]);
            Log::info("Stopped streaming connection: $streamId");
            return true;
        }
        
        return false;
    }
    
    public function sendToStream(string $streamId, string $message): bool
    {
        if (!isset($this->streamingConnections[$streamId])) {
            return false;
        }
        
        $success = $this->send($message);
        
        if ($success) {
            $this->streamingConnections[$streamId]['message_count']++;
        }
        
        return $success;
    }
    
    private function closeStreamingConnection(array $connection): void
    {
        Log::debug('Closing streaming connection', [
            'stream_id' => $connection['id'],
            'message_count' => $connection['message_count'],
            'duration' => now()->diffInSeconds($connection['created_at']),
        ]);
    }
    
    private function calculateProcessedLength(array $messages): int
    {
        $totalLength = 0;
        
        foreach ($messages as $message) {
            $frameLength = strlen($this->framer->frame($message));
            $totalLength += $frameLength;
        }
        
        return $totalLength;
    }
}
```

#### WebSocket Transport Implementation
```php
<?php

namespace JTD\LaravelMCP\Transport;

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use JTD\LaravelMCP\Transport\Framing\WebSocketFramer;

class WebSocketTransport extends BaseTransport implements MessageComponentInterface
{
    private WebSocketFramer $framer;
    private ?IoServer $server = null;
    private array $connections = [];
    private int $port;
    private string $host;
    
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->framer = new WebSocketFramer();
        $this->host = $config['host'] ?? '127.0.0.1';
        $this->port = $config['port'] ?? 8080;
    }
    
    protected function doStart(): void
    {
        $this->server = IoServer::factory(
            new HttpServer(
                new WsServer($this)
            ),
            $this->port,
            $this->host
        );
        
        Log::info('WebSocket MCP transport started', [
            'host' => $this->host,
            'port' => $this->port,
        ]);
        
        // Run in non-blocking mode if configured
        if ($this->config['non_blocking'] ?? false) {
            $this->server->loop->addPeriodicTimer(0.1, function () {
                // Process pending operations
            });
        } else {
            $this->server->run();
        }
    }
    
    protected function doStop(): void
    {
        if ($this->server) {
            $this->server->loop->stop();
            $this->server = null;
        }
        
        // Close all connections
        foreach ($this->connections as $connection) {
            $connection['websocket']->close();
        }
        
        $this->connections = [];
        Log::info('WebSocket MCP transport stopped');
    }
    
    // Ratchet MessageComponentInterface implementation
    public function onOpen(ConnectionInterface $conn): void
    {
        $connectionId = $conn->resourceId;
        
        $this->connections[$connectionId] = [
            'websocket' => $conn,
            'created_at' => now(),
            'message_count' => 0,
        ];
        
        Log::info("WebSocket connection opened: $connectionId");
        
        // Notify message handler
        if ($this->messageHandler) {
            $this->messageHandler->onConnect($this);
        }
    }
    
    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $connectionId = $from->resourceId;
        
        if (!isset($this->connections[$connectionId])) {
            Log::warning("Message from unknown connection: $connectionId");
            return;
        }
        
        try {
            $framedMessage = $this->framer->unframe($msg)[0] ?? null;
            
            if ($framedMessage) {
                $this->connections[$connectionId]['message_count']++;
                $this->updateStatistics('received', strlen($framedMessage));
                
                // Process message through handler
                if ($this->messageHandler) {
                    $response = $this->messageHandler->handle(
                        json_decode($framedMessage, true),
                        $this
                    );
                    
                    if ($response) {
                        $this->sendToConnection($connectionId, json_encode($response));
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WebSocket message processing failed', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            
            if ($this->messageHandler) {
                $this->messageHandler->handleError($e, $this);
            }
        }
    }
    
    public function onClose(ConnectionInterface $conn): void
    {
        $connectionId = $conn->resourceId;
        
        if (isset($this->connections[$connectionId])) {
            Log::info("WebSocket connection closed: $connectionId", [
                'message_count' => $this->connections[$connectionId]['message_count'],
                'duration' => now()->diffInSeconds($this->connections[$connectionId]['created_at']),
            ]);
            
            unset($this->connections[$connectionId]);
        }
        
        // Notify message handler
        if ($this->messageHandler) {
            $this->messageHandler->onDisconnect($this);
        }
    }
    
    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        Log::error('WebSocket connection error', [
            'connection_id' => $conn->resourceId,
            'error' => $e->getMessage(),
        ]);
        
        $conn->close();
    }
    
    public function send(string $message): bool
    {
        // Broadcast to all connections
        $success = true;
        
        foreach ($this->connections as $connection) {
            if (!$this->sendToConnection($connection['websocket']->resourceId, $message)) {
                $success = false;
            }
        }
        
        return $success;
    }
    
    public function sendToConnection(int $connectionId, string $message): bool
    {
        if (!isset($this->connections[$connectionId])) {
            return false;
        }
        
        try {
            $framedMessage = $this->framer->frame($message);
            $this->connections[$connectionId]['websocket']->send($framedMessage);
            $this->updateStatistics('sent', strlen($framedMessage));
            
            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to send WebSocket message', [
                'connection_id' => $connectionId,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    public function receive(): ?string
    {
        // WebSocket is event-driven, messages are handled in onMessage
        return null;
    }
    
    public function getActiveConnections(): array
    {
        return array_keys($this->connections);
    }
    
    public function getConnectionCount(): int
    {
        return count($this->connections);
    }
}
```

## Server Lifecycle

### Initialization Process
```php
public function initialize(array $clientInfo): array
{
    // 1. Validate client information
    $this->validateClientInfo($clientInfo);
    
    // 2. Negotiate capabilities
    $capabilities = $this->capabilityNegotiator->negotiateCapabilities(
        $clientInfo['capabilities'] ?? []
    );
    
    // 3. Initialize components
    $this->initializeComponents();
    
    // 4. Start background services
    $this->startBackgroundServices();
    
    // 5. Return server information
    return [
        'protocolVersion' => '2024-11-05',
        'capabilities' => $capabilities['capabilities'],
        'serverInfo' => $this->getServerInfo(),
    ];
}

private function initializeComponents(): void
{
    // Initialize registries
    $this->registry->initialize();
    
    // Start transport layers
    $this->transportManager->initialize();
    
    // Setup error handling
    $this->setupErrorHandling();
    
    // Initialize logging
    $this->initializeLogging();
}
```

## Error Recovery & Resilience Patterns

### Circuit Breaker Implementation
```php
<?php

namespace JTD\LaravelMCP\Resilience;

class CircuitBreaker
{
    private array $breakers = [];
    private array $config;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'failure_threshold' => 5,
            'recovery_timeout' => 60,
            'half_open_max_calls' => 3,
        ], $config);
    }
    
    public function call(string $service, callable $operation, callable $fallback = null): mixed
    {
        $breaker = $this->getBreaker($service);
        
        if ($breaker['state'] === 'open') {
            if ($this->shouldAttemptReset($breaker)) {
                $this->transitionToHalfOpen($service);
            } else {
                return $this->executeFallback($fallback, "Circuit breaker open for $service");
            }
        }
        
        try {
            $result = $operation();
            $this->recordSuccess($service);
            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure($service, $e);
            
            if ($breaker['state'] === 'half_open') {
                $this->transitionToOpen($service);
            }
            
            return $this->executeFallback($fallback, $e->getMessage());
        }
    }
    
    private function getBreaker(string $service): array
    {
        if (!isset($this->breakers[$service])) {
            $this->breakers[$service] = [
                'state' => 'closed',
                'failures' => 0,
                'last_failure_time' => null,
                'half_open_calls' => 0,
            ];
        }
        
        return $this->breakers[$service];
    }
    
    private function shouldAttemptReset(array $breaker): bool
    {
        if ($breaker['last_failure_time'] === null) {
            return false;
        }
        
        return (time() - $breaker['last_failure_time']) >= $this->config['recovery_timeout'];
    }
    
    private function transitionToHalfOpen(string $service): void
    {
        $this->breakers[$service]['state'] = 'half_open';
        $this->breakers[$service]['half_open_calls'] = 0;
        
        Log::info("Circuit breaker transitioning to half-open: $service");
    }
    
    private function transitionToOpen(string $service): void
    {
        $this->breakers[$service]['state'] = 'open';
        $this->breakers[$service]['last_failure_time'] = time();
        
        Log::warning("Circuit breaker opened for service: $service");
    }
    
    private function recordSuccess(string $service): void
    {
        $breaker = &$this->breakers[$service];
        
        if ($breaker['state'] === 'half_open') {
            $breaker['half_open_calls']++;
            
            if ($breaker['half_open_calls'] >= $this->config['half_open_max_calls']) {
                $breaker['state'] = 'closed';
                $breaker['failures'] = 0;
                $breaker['last_failure_time'] = null;
                $breaker['half_open_calls'] = 0;
                
                Log::info("Circuit breaker reset to closed: $service");
            }
        } else {
            $breaker['failures'] = max(0, $breaker['failures'] - 1);
        }
    }
    
    private function recordFailure(string $service, \Throwable $e): void
    {
        $breaker = &$this->breakers[$service];
        $breaker['failures']++;
        $breaker['last_failure_time'] = time();
        
        if ($breaker['failures'] >= $this->config['failure_threshold'] && 
            $breaker['state'] === 'closed') {
            $this->transitionToOpen($service);
        }
        
        Log::error("Circuit breaker recorded failure for $service", [
            'error' => $e->getMessage(),
            'failures' => $breaker['failures'],
            'state' => $breaker['state'],
        ]);
    }
    
    private function executeFallback(callable $fallback = null, string $reason = ''): mixed
    {
        if ($fallback) {
            return $fallback($reason);
        }
        
        throw new ServiceUnavailableException("Service unavailable: $reason");
    }
}
```

### Retry Mechanism with Exponential Backoff
```php
<?php

namespace JTD\LaravelMCP\Resilience;

class RetryManager
{
    private array $retryPolicies = [];
    
    public function __construct()
    {
        $this->initializeDefaultPolicies();
    }
    
    public function executeWithRetry(
        string $operation,
        callable $callback,
        array $customPolicy = []
    ): mixed {
        $policy = array_merge(
            $this->retryPolicies[$operation] ?? $this->retryPolicies['default'],
            $customPolicy
        );
        
        $attempt = 1;
        $lastException = null;
        
        while ($attempt <= $policy['max_attempts']) {
            try {
                return $callback();
            } catch (\Throwable $e) {
                $lastException = $e;
                
                if (!$this->shouldRetry($e, $policy) || $attempt === $policy['max_attempts']) {
                    break;
                }
                
                $delay = $this->calculateDelay($attempt, $policy);
                
                Log::warning("Retry attempt $attempt for $operation failed", [
                    'error' => $e->getMessage(),
                    'next_attempt_delay_ms' => $delay,
                    'max_attempts' => $policy['max_attempts'],
                ]);
                
                usleep($delay * 1000); // Convert to microseconds
                $attempt++;
            }
        }
        
        Log::error("All retry attempts exhausted for $operation", [
            'attempts' => $attempt - 1,
            'final_error' => $lastException->getMessage(),
        ]);
        
        throw $lastException;
    }
    
    private function shouldRetry(\Throwable $e, array $policy): bool
    {
        // Don't retry on certain types of errors
        $nonRetryableErrors = $policy['non_retryable_errors'] ?? [
            'JTD\\LaravelMCP\\Exceptions\\ValidationException',
            'JTD\\LaravelMCP\\Exceptions\\AuthenticationException',
        ];
        
        foreach ($nonRetryableErrors as $errorClass) {
            if ($e instanceof $errorClass) {
                return false;
            }
        }
        
        // Check for specific error messages that shouldn't be retried
        $nonRetryableMessages = $policy['non_retryable_messages'] ?? [
            'validation failed',
            'unauthorized',
            'forbidden',
        ];
        
        foreach ($nonRetryableMessages as $message) {
            if (stripos($e->getMessage(), $message) !== false) {
                return false;
            }
        }
        
        return true;
    }
    
    private function calculateDelay(int $attempt, array $policy): int
    {
        $baseDelay = $policy['base_delay_ms'] ?? 100;
        $maxDelay = $policy['max_delay_ms'] ?? 30000;
        $multiplier = $policy['multiplier'] ?? 2;
        $jitter = $policy['jitter'] ?? true;
        
        // Exponential backoff
        $delay = min($baseDelay * pow($multiplier, $attempt - 1), $maxDelay);
        
        // Add jitter to prevent thundering herd
        if ($jitter) {
            $jitterRange = $delay * 0.1; // 10% jitter
            $delay += mt_rand(-$jitterRange, $jitterRange);
        }
        
        return max(0, (int) $delay);
    }
    
    private function initializeDefaultPolicies(): void
    {
        $this->retryPolicies = [
            'default' => [
                'max_attempts' => 3,
                'base_delay_ms' => 100,
                'max_delay_ms' => 5000,
                'multiplier' => 2,
                'jitter' => true,
            ],
            'tool_execution' => [
                'max_attempts' => 3,
                'base_delay_ms' => 200,
                'max_delay_ms' => 10000,
                'multiplier' => 2.5,
                'jitter' => true,
            ],
            'resource_access' => [
                'max_attempts' => 2,
                'base_delay_ms' => 50,
                'max_delay_ms' => 2000,
                'multiplier' => 2,
                'jitter' => true,
            ],
            'transport_send' => [
                'max_attempts' => 5,
                'base_delay_ms' => 10,
                'max_delay_ms' => 1000,
                'multiplier' => 1.5,
                'jitter' => true,
            ],
        ];
    }
}
```

### Graceful Degradation System
```php
<?php

namespace JTD\LaravelMCP\Resilience;

class DegradationManager
{
    private array $degradationPolicies = [];
    private array $serviceHealth = [];
    private array $activeTimeouts = [];
    
    public function __construct(array $policies = [])
    {
        $this->degradationPolicies = array_merge($this->getDefaultPolicies(), $policies);
    }
    
    public function executeWithDegradation(string $service, callable $primary, callable $degraded = null): mixed
    {
        $health = $this->getServiceHealth($service);
        
        // Check if service should be degraded
        if ($this->shouldDegrade($service, $health)) {
            Log::info("Service degraded: $service", ['health' => $health]);
            
            if ($degraded) {
                return $degraded();
            }
            
            return $this->getDefaultDegradedResponse($service);
        }
        
        // Execute with timeout
        return $this->executeWithTimeout($service, $primary);
    }
    
    public function updateServiceHealth(string $service, bool $healthy, array $metrics = []): void
    {
        if (!isset($this->serviceHealth[$service])) {
            $this->serviceHealth[$service] = [
                'healthy' => true,
                'consecutive_failures' => 0,
                'last_success' => time(),
                'last_failure' => null,
                'metrics' => [],
            ];
        }
        
        $health = &$this->serviceHealth[$service];
        
        if ($healthy) {
            $health['healthy'] = true;
            $health['consecutive_failures'] = 0;
            $health['last_success'] = time();
        } else {
            $health['consecutive_failures']++;
            $health['last_failure'] = time();
            
            // Mark as unhealthy if threshold exceeded
            $policy = $this->degradationPolicies[$service] ?? $this->degradationPolicies['default'];
            if ($health['consecutive_failures'] >= $policy['failure_threshold']) {
                $health['healthy'] = false;
            }
        }
        
        $health['metrics'] = array_merge($health['metrics'], $metrics);
    }
    
    private function shouldDegrade(string $service, array $health): bool
    {
        $policy = $this->degradationPolicies[$service] ?? $this->degradationPolicies['default'];
        
        // Check health status
        if (!$health['healthy']) {
            return true;
        }
        
        // Check response time degradation
        if (isset($health['metrics']['avg_response_time'])) {
            $responseTime = $health['metrics']['avg_response_time'];
            $threshold = $policy['response_time_threshold_ms'] ?? 5000;
            
            if ($responseTime > $threshold) {
                return true;
            }
        }
        
        // Check error rate
        if (isset($health['metrics']['error_rate'])) {
            $errorRate = $health['metrics']['error_rate'];
            $threshold = $policy['error_rate_threshold'] ?? 0.1; // 10%
            
            if ($errorRate > $threshold) {
                return true;
            }
        }
        
        return false;
    }
    
    private function executeWithTimeout(string $service, callable $callback): mixed
    {
        $policy = $this->degradationPolicies[$service] ?? $this->degradationPolicies['default'];
        $timeout = $policy['timeout_seconds'] ?? 30;
        
        $startTime = microtime(true);
        
        try {
            // Set timeout using pcntl_alarm if available
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm($timeout);
            }
            
            $result = $callback();
            
            // Clear alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->updateServiceHealth($service, true, ['response_time' => $responseTime]);
            
            return $result;
            
        } catch (\Throwable $e) {
            // Clear alarm
            if (function_exists('pcntl_alarm')) {
                pcntl_alarm(0);
            }
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $this->updateServiceHealth($service, false, [
                'response_time' => $responseTime,
                'error' => $e->getMessage(),
            ]);
            
            throw $e;
        }
    }
    
    private function getServiceHealth(string $service): array
    {
        return $this->serviceHealth[$service] ?? [
            'healthy' => true,
            'consecutive_failures' => 0,
            'last_success' => time(),
            'last_failure' => null,
            'metrics' => [],
        ];
    }
    
    private function getDefaultDegradedResponse(string $service): array
    {
        return [
            'degraded' => true,
            'service' => $service,
            'message' => 'Service temporarily degraded, limited functionality available',
            'timestamp' => now()->toISOString(),
        ];
    }
    
    private function getDefaultPolicies(): array
    {
        return [
            'default' => [
                'failure_threshold' => 3,
                'timeout_seconds' => 30,
                'response_time_threshold_ms' => 5000,
                'error_rate_threshold' => 0.1,
            ],
            'tool_execution' => [
                'failure_threshold' => 2,
                'timeout_seconds' => 60,
                'response_time_threshold_ms' => 10000,
                'error_rate_threshold' => 0.05,
            ],
            'resource_access' => [
                'failure_threshold' => 5,
                'timeout_seconds' => 15,
                'response_time_threshold_ms' => 2000,
                'error_rate_threshold' => 0.15,
            ],
        ];
    }
}
```

### Comprehensive Error Handler
```php
<?php

namespace JTD\LaravelMCP\Resilience;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use Illuminate\Support\Facades\Log;

class ErrorHandler
{
    private CircuitBreaker $circuitBreaker;
    private RetryManager $retryManager;
    private DegradationManager $degradationManager;
    private array $errorMetrics = [];
    
    public function __construct(
        CircuitBreaker $circuitBreaker,
        RetryManager $retryManager,
        DegradationManager $degradationManager
    ) {
        $this->circuitBreaker = $circuitBreaker;
        $this->retryManager = $retryManager;
        $this->degradationManager = $degradationManager;
    }
    
    public function handleWithResilience(
        string $operation,
        string $service,
        callable $callback,
        callable $fallback = null
    ): mixed {
        return $this->circuitBreaker->call(
            $service,
            function () use ($operation, $service, $callback) {
                return $this->degradationManager->executeWithDegradation(
                    $service,
                    function () use ($operation, $callback) {
                        return $this->retryManager->executeWithRetry(
                            $operation,
                            $callback
                        );
                    }
                );
            },
            $fallback
        );
    }
    
    public function formatErrorResponse(\Throwable $e, ?string $requestId = null): array
    {
        $this->recordErrorMetrics($e);
        
        // Determine error code based on exception type
        $code = match (true) {
            $e instanceof \JsonException => -32700, // Parse error
            $e instanceof ProtocolException => -32600, // Invalid request
            $e instanceof \BadMethodCallException => -32601, // Method not found
            $e instanceof \InvalidArgumentException => -32602, // Invalid params
            $e instanceof TransportException => -32001, // Transport error
            $e instanceof McpException => -32000, // Server error
            default => -32603, // Internal error
        };
        
        $error = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ],
            'id' => $requestId,
        ];
        
        // Add debug information in development
        if (app()->environment(['local', 'development', 'testing'])) {
            $error['error']['data'] = [
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => collect($e->getTrace())->take(5)->toArray(),
            ];
        }
        
        // Add error ID for tracking
        $errorId = uniqid('err_');
        $error['error']['error_id'] = $errorId;
        
        Log::error('MCP error response generated', [
            'error_id' => $errorId,
            'request_id' => $requestId,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $code,
        ]);
        
        return $error;
    }
    
    public function getErrorMetrics(): array
    {
        return [
            'total_errors' => array_sum($this->errorMetrics),
            'error_breakdown' => $this->errorMetrics,
            'error_rate' => $this->calculateErrorRate(),
            'most_common_error' => $this->getMostCommonError(),
        ];
    }
    
    private function recordErrorMetrics(\Throwable $e): void
    {
        $errorType = get_class($e);
        $this->errorMetrics[$errorType] = ($this->errorMetrics[$errorType] ?? 0) + 1;
    }
    
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove sensitive information from error messages
        $sensitivePatterns = [
            '/password=\w+/i',
            '/token=\w+/i',
            '/key=\w+/i',
            '/secret=\w+/i',
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            $message = preg_replace($pattern, '[REDACTED]', $message);
        }
        
        return $message;
    }
    
    private function calculateErrorRate(): float
    {
        $totalRequests = cache()->get('mcp.total_requests', 1);
        $totalErrors = array_sum($this->errorMetrics);
        
        return $totalRequests > 0 ? $totalErrors / $totalRequests : 0;
    }
    
    private function getMostCommonError(): ?string
    {
        if (empty($this->errorMetrics)) {
            return null;
        }
        
        return array_key_first(
            array_slice(
                arsort($this->errorMetrics),
                0,
                1,
                true
            )
        );
    }
}
```

### Shutdown Process
```php
public function shutdown(): void
{
    try {
        // 1. Stop accepting new connections
        $this->transportManager->stopAcceptingConnections();
        
        // 2. Complete pending requests with timeout
        $this->completePendingRequests();
        
        // 3. Clean up resources
        $this->cleanupResources();
        
        // 4. Close connections
        $this->transportManager->closeConnections();
        
        // 5. Log shutdown
        $this->logShutdown();
    } catch (\Throwable $e) {
        // Log error but don't throw during shutdown
        logger()->error('Error during MCP server shutdown', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}

private function completePendingRequests(int $timeoutSeconds = 30): void
{
    $startTime = time();
    
    while (!empty($this->activeRequests) && (time() - $startTime) < $timeoutSeconds) {
        Log::info('Waiting for pending requests to complete', [
            'pending_count' => count($this->activeRequests),
            'remaining_timeout' => $timeoutSeconds - (time() - $startTime),
        ]);
        
        sleep(1);
    }
    
    // Force terminate remaining requests
    if (!empty($this->activeRequests)) {
        Log::warning('Force terminating pending requests', [
            'terminated_count' => count($this->activeRequests),
        ]);
        
        foreach ($this->activeRequests as $requestId => $context) {
            $this->terminateRequest($requestId, 'Server shutdown');
        }
    }
}
```

## Advanced Protocol Features

### Resource Subscription System
```php
<?php

namespace JTD\LaravelMCP\Features;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\ResourceChanged;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

class ResourceSubscriptionManager
{
    private array $subscriptions = [];
    private array $transportConnections = [];
    private array $resourceWatchers = [];
    
    public function subscribe(
        string $uri,
        TransportInterface $transport,
        array $options = []
    ): string {
        $subscriptionId = uniqid('sub_');
        
        $this->subscriptions[$subscriptionId] = [
            'id' => $subscriptionId,
            'uri' => $uri,
            'transport' => $transport,
            'options' => $options,
            'created_at' => now(),
            'last_notification' => null,
            'notification_count' => 0,
        ];
        
        $this->transportConnections[spl_object_hash($transport)][] = $subscriptionId;
        
        // Start watching the resource if not already watched
        if (!isset($this->resourceWatchers[$uri])) {
            $this->startWatching($uri);
        }
        
        Log::info("Resource subscription created", [
            'subscription_id' => $subscriptionId,
            'uri' => $uri,
            'options' => $options,
        ]);
        
        return $subscriptionId;
    }
    
    public function unsubscribe(string $subscriptionId): bool
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return false;
        }
        
        $subscription = $this->subscriptions[$subscriptionId];
        $transportHash = spl_object_hash($subscription['transport']);
        
        // Remove from transport connections
        if (isset($this->transportConnections[$transportHash])) {
            $this->transportConnections[$transportHash] = array_filter(
                $this->transportConnections[$transportHash],
                fn($id) => $id !== $subscriptionId
            );
        }
        
        // Stop watching resource if no more subscriptions
        $uri = $subscription['uri'];
        $remainingSubscriptions = array_filter(
            $this->subscriptions,
            fn($sub) => $sub['uri'] === $uri && $sub['id'] !== $subscriptionId
        );
        
        if (empty($remainingSubscriptions)) {
            $this->stopWatching($uri);
        }
        
        unset($this->subscriptions[$subscriptionId]);
        
        Log::info("Resource subscription removed", [
            'subscription_id' => $subscriptionId,
            'uri' => $uri,
        ]);
        
        return true;
    }
    
    public function notifySubscribers(string $uri, array $changeData): void
    {
        $relevantSubscriptions = array_filter(
            $this->subscriptions,
            function ($subscription) use ($uri) {
                return $this->uriMatches($subscription['uri'], $uri);
            }
        );
        
        foreach ($relevantSubscriptions as $subscription) {
            $this->sendNotification($subscription, $changeData);
        }
    }
    
    public function cleanupTransportSubscriptions(TransportInterface $transport): void
    {
        $transportHash = spl_object_hash($transport);
        
        if (isset($this->transportConnections[$transportHash])) {
            foreach ($this->transportConnections[$transportHash] as $subscriptionId) {
                $this->unsubscribe($subscriptionId);
            }
            
            unset($this->transportConnections[$transportHash]);
        }
    }
    
    private function startWatching(string $uri): void
    {
        // Implementation would depend on the resource type
        // For file resources, use file system watchers
        // For database resources, use change streams
        // For external APIs, use webhooks or polling
        
        $this->resourceWatchers[$uri] = [
            'uri' => $uri,
            'type' => $this->determineResourceType($uri),
            'watcher' => $this->createWatcher($uri),
            'started_at' => now(),
        ];
        
        Log::debug("Started watching resource: $uri");
    }
    
    private function stopWatching(string $uri): void
    {
        if (isset($this->resourceWatchers[$uri])) {
            $watcher = $this->resourceWatchers[$uri]['watcher'];
            
            if (is_callable([$watcher, 'stop'])) {
                $watcher->stop();
            }
            
            unset($this->resourceWatchers[$uri]);
            Log::debug("Stopped watching resource: $uri");
        }
    }
    
    private function sendNotification(array $subscription, array $changeData): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/resources/updated',
            'params' => [
                'uri' => $subscription['uri'],
                'subscription_id' => $subscription['id'],
                'changes' => $changeData,
                'timestamp' => now()->toISOString(),
            ],
        ];
        
        try {
            $success = $subscription['transport']->send(json_encode($notification));
            
            if ($success) {
                $this->subscriptions[$subscription['id']]['last_notification'] = now();
                $this->subscriptions[$subscription['id']]['notification_count']++;
            }
            
            Log::debug('Resource notification sent', [
                'subscription_id' => $subscription['id'],
                'uri' => $subscription['uri'],
                'success' => $success,
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Failed to send resource notification', [
                'subscription_id' => $subscription['id'],
                'uri' => $subscription['uri'],
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    private function uriMatches(string $subscriptionUri, string $changeUri): bool
    {
        // Support wildcard matching
        if (str_contains($subscriptionUri, '*')) {
            $pattern = str_replace('*', '.*', preg_quote($subscriptionUri, '/'));
            return preg_match("/^$pattern$/", $changeUri);
        }
        
        return $subscriptionUri === $changeUri;
    }
    
    private function determineResourceType(string $uri): string
    {
        if (str_starts_with($uri, 'file://')) {
            return 'file';
        }
        
        if (str_starts_with($uri, 'db://')) {
            return 'database';
        }
        
        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return 'http';
        }
        
        return 'custom';
    }
    
    private function createWatcher(string $uri): mixed
    {
        $type = $this->determineResourceType($uri);
        
        return match ($type) {
            'file' => $this->createFileWatcher($uri),
            'database' => $this->createDatabaseWatcher($uri),
            'http' => $this->createHttpWatcher($uri),
            default => $this->createCustomWatcher($uri),
        };
    }
    
    private function createFileWatcher(string $uri): mixed
    {
        // Use inotify or similar file system watcher
        // This is a simplified example
        return new class($uri, $this) {
            private string $uri;
            private ResourceSubscriptionManager $manager;
            
            public function __construct(string $uri, ResourceSubscriptionManager $manager)
            {
                $this->uri = $uri;
                $this->manager = $manager;
                // Start file watching logic here
            }
            
            public function stop(): void
            {
                // Stop file watching
            }
        };
    }
    
    private function createDatabaseWatcher(string $uri): mixed
    {
        // Implement database change streams
        return new class($uri, $this) {
            public function stop(): void {}
        };
    }
    
    private function createHttpWatcher(string $uri): mixed
    {
        // Implement HTTP polling or webhook registration
        return new class($uri, $this) {
            public function stop(): void {}
        };
    }
    
    private function createCustomWatcher(string $uri): mixed
    {
        // Fallback for custom resource types
        return new class($uri, $this) {
            public function stop(): void {}
        };
    }
}
```

### Sampling and Completion Services
```php
<?php

namespace JTD\LaravelMCP\Features;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;

class SamplingService
{
    private array $providerConfig;
    private array $modelCapabilities;
    
    public function __construct(array $config = [])
    {
        $this->providerConfig = array_merge([
            'default_provider' => 'openai',
            'providers' => [
                'openai' => [
                    'api_key' => env('OPENAI_API_KEY'),
                    'base_url' => 'https://api.openai.com/v1',
                    'models' => ['gpt-4', 'gpt-3.5-turbo'],
                ],
                'anthropic' => [
                    'api_key' => env('ANTHROPIC_API_KEY'),
                    'base_url' => 'https://api.anthropic.com/v1',
                    'models' => ['claude-3-sonnet-20240229', 'claude-3-opus-20240229'],
                ],
            ],
        ], $config);
        
        $this->initializeModelCapabilities();
    }
    
    public function createMessage(
        array $messages,
        array $modelPreferences = [],
        array $samplingParams = []
    ): array {
        $provider = $this->selectProvider($modelPreferences);
        $model = $this->selectModel($provider, $modelPreferences);
        
        $params = array_merge([
            'max_tokens' => 1000,
            'temperature' => 0.7,
            'top_p' => 1.0,
            'stop' => null,
        ], $samplingParams);
        
        try {
            $response = $this->callProvider($provider, $model, $messages, $params);
            
            return [
                'model' => $model,
                'provider' => $provider,
                'stopReason' => $response['stop_reason'] ?? 'end_turn',
                'role' => 'assistant',
                'content' => $response['content'],
                'usage' => $response['usage'] ?? [],
                'metadata' => [
                    'provider_response_time' => $response['response_time'] ?? null,
                    'tokens_used' => $response['usage']['total_tokens'] ?? 0,
                ],
            ];
            
        } catch (\Throwable $e) {
            Log::error('Sampling request failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
            
            throw new ProtocolException("Sampling failed: {$e->getMessage()}");
        }
    }
    
    private function selectProvider(array $preferences): string
    {
        if (isset($preferences['provider']) && 
            isset($this->providerConfig['providers'][$preferences['provider']])) {
            return $preferences['provider'];
        }
        
        return $this->providerConfig['default_provider'];
    }
    
    private function selectModel(string $provider, array $preferences): string
    {
        $availableModels = $this->providerConfig['providers'][$provider]['models'] ?? [];
        
        if (isset($preferences['model']) && in_array($preferences['model'], $availableModels)) {
            return $preferences['model'];
        }
        
        // Return first available model as default
        return $availableModels[0] ?? 'default';
    }
    
    private function callProvider(
        string $provider,
        string $model,
        array $messages,
        array $params
    ): array {
        $startTime = microtime(true);
        
        $response = match ($provider) {
            'openai' => $this->callOpenAI($model, $messages, $params),
            'anthropic' => $this->callAnthropic($model, $messages, $params),
            default => throw new ProtocolException("Unsupported provider: $provider"),
        };
        
        $response['response_time'] = (microtime(true) - $startTime) * 1000;
        
        return $response;
    }
    
    private function callOpenAI(string $model, array $messages, array $params): array
    {
        $config = $this->providerConfig['providers']['openai'];
        
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ])->post($config['base_url'] . '/chat/completions', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $params['max_tokens'],
            'temperature' => $params['temperature'],
            'top_p' => $params['top_p'],
            'stop' => $params['stop'],
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("OpenAI API error: {$response->body()}");
        }
        
        $data = $response->json();
        
        return [
            'content' => [[
                'type' => 'text',
                'text' => $data['choices'][0]['message']['content'],
            ]],
            'stop_reason' => $data['choices'][0]['finish_reason'],
            'usage' => $data['usage'],
        ];
    }
    
    private function callAnthropic(string $model, array $messages, array $params): array
    {
        $config = $this->providerConfig['providers']['anthropic'];
        
        $response = Http::withHeaders([
            'x-api-key' => $config['api_key'],
            'Content-Type' => 'application/json',
            'anthropic-version' => '2023-06-01',
        ])->post($config['base_url'] . '/messages', [
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => $params['max_tokens'],
            'temperature' => $params['temperature'],
            'top_p' => $params['top_p'],
            'stop_sequences' => $params['stop'] ? [$params['stop']] : null,
        ]);
        
        if (!$response->successful()) {
            throw new \Exception("Anthropic API error: {$response->body()}");
        }
        
        $data = $response->json();
        
        return [
            'content' => $data['content'],
            'stop_reason' => $data['stop_reason'],
            'usage' => $data['usage'],
        ];
    }
    
    private function initializeModelCapabilities(): void
    {
        $this->modelCapabilities = [
            'gpt-4' => [
                'max_tokens' => 8192,
                'supports_functions' => true,
                'supports_vision' => true,
            ],
            'gpt-3.5-turbo' => [
                'max_tokens' => 4096,
                'supports_functions' => true,
                'supports_vision' => false,
            ],
            'claude-3-sonnet-20240229' => [
                'max_tokens' => 4096,
                'supports_functions' => false,
                'supports_vision' => true,
            ],
        ];
    }
}

class CompletionService
{
    private array $completionProviders = [];
    
    public function __construct()
    {
        $this->registerDefaultProviders();
    }
    
    public function complete(string $argument, array $context = []): array
    {
        $completions = [];
        
        foreach ($this->completionProviders as $provider) {
            try {
                $providerCompletions = $provider->getCompletions($argument, $context);
                $completions = array_merge($completions, $providerCompletions);
            } catch (\Throwable $e) {
                Log::warning('Completion provider failed', [
                    'provider' => get_class($provider),
                    'argument' => $argument,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Sort by relevance and limit results
        usort($completions, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        $completions = array_slice($completions, 0, 50);
        
        return [
            'completion' => [
                'values' => array_column($completions, 'value'),
                'total' => count($completions),
                'hasMore' => false, // Could implement pagination
            ]
        ];
    }
    
    public function registerProvider(CompletionProviderInterface $provider): void
    {
        $this->completionProviders[] = $provider;
    }
    
    private function registerDefaultProviders(): void
    {
        $this->registerProvider(new FilePathCompletionProvider());
        $this->registerProvider(new ToolNameCompletionProvider());
        $this->registerProvider(new ResourceUriCompletionProvider());
        $this->registerProvider(new PromptNameCompletionProvider());
    }
}

interface CompletionProviderInterface
{
    public function getCompletions(string $argument, array $context = []): array;
    public function canComplete(string $argument, array $context = []): bool;
}

class FilePathCompletionProvider implements CompletionProviderInterface
{
    public function getCompletions(string $argument, array $context = []): array
    {
        if (!$this->canComplete($argument, $context)) {
            return [];
        }
        
        $basePath = dirname($argument);
        $filename = basename($argument);
        
        if (!is_dir($basePath)) {
            return [];
        }
        
        $completions = [];
        $files = scandir($basePath);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            if (str_starts_with($file, $filename)) {
                $fullPath = $basePath . '/' . $file;
                $completions[] = [
                    'value' => $fullPath,
                    'score' => $this->calculateScore($file, $filename),
                    'type' => is_dir($fullPath) ? 'directory' : 'file',
                ];
            }
        }
        
        return $completions;
    }
    
    public function canComplete(string $argument, array $context = []): bool
    {
        return str_contains($argument, '/') || str_contains($argument, '\\');
    }
    
    private function calculateScore(string $file, string $prefix): float
    {
        $score = 0.5; // Base score
        
        if (str_starts_with($file, $prefix)) {
            $score += 0.3;
        }
        
        if (strlen($prefix) > 0) {
            $score += (strlen($prefix) / strlen($file)) * 0.2;
        }
        
        return $score;
    }
}
```

### Roots Directory Service
```php
<?php

namespace JTD\LaravelMCP\Features;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class RootsService
{
    private array $configuredRoots;
    private array $securityPolicies;
    
    public function __construct(array $config = [])
    {
        $this->configuredRoots = $config['roots'] ?? $this->getDefaultRoots();
        $this->securityPolicies = $config['security'] ?? $this->getDefaultSecurityPolicies();
    }
    
    public function listRoots(): array
    {
        $roots = [];
        
        foreach ($this->configuredRoots as $root) {
            if ($this->isRootAccessible($root)) {
                $roots[] = [
                    'name' => $root['name'],
                    'uri' => $root['uri'],
                    'description' => $root['description'] ?? null,
                    'metadata' => $this->getRootMetadata($root),
                ];
            }
        }
        
        return ['roots' => $roots];
    }
    
    public function getRootContents(string $rootName, string $path = '', array $options = []): array
    {
        $root = $this->findRoot($rootName);
        
        if (!$root) {
            throw new \InvalidArgumentException("Root not found: $rootName");
        }
        
        if (!$this->isPathAllowed($root, $path)) {
            throw new \UnauthorizedAccessException("Access denied to path: $path");
        }
        
        $fullPath = $this->resolvePath($root, $path);
        
        if (!File::exists($fullPath)) {
            throw new \InvalidArgumentException("Path not found: $path");
        }
        
        $items = [];
        $showHidden = $options['show_hidden'] ?? false;
        $recursive = $options['recursive'] ?? false;
        $maxDepth = $options['max_depth'] ?? 3;
        
        if (File::isDirectory($fullPath)) {
            $items = $this->listDirectoryContents($fullPath, $showHidden, $recursive, $maxDepth);
        } else {
            $items = [$this->getFileInfo($fullPath)];
        }
        
        return [
            'root' => $rootName,
            'path' => $path,
            'items' => $items,
            'total' => count($items),
        ];
    }
    
    private function getDefaultRoots(): array
    {
        return [
            [
                'name' => 'project',
                'uri' => 'file://' . base_path(),
                'description' => 'Project root directory',
                'allowed_paths' => ['*'],
                'denied_paths' => ['.env*', 'vendor/*', 'node_modules/*'],
            ],
            [
                'name' => 'storage',
                'uri' => 'file://' . storage_path(),
                'description' => 'Storage directory',
                'allowed_paths' => ['app/*', 'logs/*'],
                'denied_paths' => ['framework/*'],
            ],
            [
                'name' => 'config',
                'uri' => 'file://' . config_path(),
                'description' => 'Configuration files',
                'allowed_paths' => ['*.php'],
                'denied_paths' => [],
            ],
        ];
    }
    
    private function getDefaultSecurityPolicies(): array
    {
        return [
            'max_file_size' => 10 * 1024 * 1024, // 10MB
            'allowed_extensions' => [
                '.php', '.js', '.ts', '.json', '.xml', '.yaml', '.yml',
                '.md', '.txt', '.log', '.csv', '.sql',
            ],
            'denied_extensions' => [
                '.exe', '.bat', '.sh', '.ps1', '.bin',
            ],
            'follow_symlinks' => false,
        ];
    }
    
    private function isRootAccessible(array $root): bool
    {
        $path = parse_url($root['uri'], PHP_URL_PATH);
        
        if (!$path || !File::exists($path)) {
            return false;
        }
        
        if (!File::isReadable($path)) {
            return false;
        }
        
        return true;
    }
    
    private function findRoot(string $name): ?array
    {
        foreach ($this->configuredRoots as $root) {
            if ($root['name'] === $name) {
                return $root;
            }
        }
        
        return null;
    }
    
    private function isPathAllowed(array $root, string $path): bool
    {
        $allowedPaths = $root['allowed_paths'] ?? ['*'];
        $deniedPaths = $root['denied_paths'] ?? [];
        
        // Check denied paths first
        foreach ($deniedPaths as $deniedPattern) {
            if ($this->matchesPattern($path, $deniedPattern)) {
                return false;
            }
        }
        
        // Check allowed paths
        foreach ($allowedPaths as $allowedPattern) {
            if ($this->matchesPattern($path, $allowedPattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function matchesPattern(string $path, string $pattern): bool
    {
        // Convert glob pattern to regex
        $regex = str_replace(
            ['*', '?'],
            ['.*', '.'],
            preg_quote($pattern, '/')
        );
        
        return preg_match("/^$regex$/", $path);
    }
    
    private function resolvePath(array $root, string $path): string
    {
        $rootPath = parse_url($root['uri'], PHP_URL_PATH);
        $fullPath = rtrim($rootPath, '/') . '/' . ltrim($path, '/');
        
        // Resolve relative paths and prevent directory traversal
        $realPath = realpath($fullPath);
        
        if (!$realPath || !str_starts_with($realPath, realpath($rootPath))) {
            throw new \UnauthorizedAccessException('Invalid path');
        }
        
        return $realPath;
    }
    
    private function listDirectoryContents(
        string $path,
        bool $showHidden,
        bool $recursive,
        int $maxDepth,
        int $currentDepth = 0
    ): array {
        $items = [];
        
        if ($currentDepth >= $maxDepth) {
            return $items;
        }
        
        $files = File::files($path);
        $directories = File::directories($path);
        
        // Add files
        foreach ($files as $file) {
            if (!$showHidden && str_starts_with($file->getFilename(), '.')) {
                continue;
            }
            
            if (!$this->isFileAllowed($file->getPathname())) {
                continue;
            }
            
            $items[] = $this->getFileInfo($file->getPathname());
        }
        
        // Add directories
        foreach ($directories as $directory) {
            if (!$showHidden && str_starts_with(basename($directory), '.')) {
                continue;
            }
            
            $dirInfo = $this->getDirectoryInfo($directory);
            
            if ($recursive) {
                $dirInfo['children'] = $this->listDirectoryContents(
                    $directory,
                    $showHidden,
                    $recursive,
                    $maxDepth,
                    $currentDepth + 1
                );
            }
            
            $items[] = $dirInfo;
        }
        
        return $items;
    }
    
    private function getFileInfo(string $path): array
    {
        $stat = File::stat($path);
        
        return [
            'name' => basename($path),
            'type' => 'file',
            'size' => $stat['size'],
            'modified' => date('c', $stat['mtime']),
            'permissions' => substr(sprintf('%o', $stat['mode']), -4),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'mime_type' => File::mimeType($path),
        ];
    }
    
    private function getDirectoryInfo(string $path): array
    {
        $stat = File::stat($path);
        
        return [
            'name' => basename($path),
            'type' => 'directory',
            'modified' => date('c', $stat['mtime']),
            'permissions' => substr(sprintf('%o', $stat['mode']), -4),
            'item_count' => count(File::allFiles($path)),
        ];
    }
    
    private function isFileAllowed(string $path): bool
    {
        $extension = '.' . pathinfo($path, PATHINFO_EXTENSION);
        
        // Check denied extensions
        if (in_array($extension, $this->securityPolicies['denied_extensions'])) {
            return false;
        }
        
        // Check allowed extensions (if specified)
        $allowedExtensions = $this->securityPolicies['allowed_extensions'];
        if (!empty($allowedExtensions) && !in_array($extension, $allowedExtensions)) {
            return false;
        }
        
        // Check file size
        $fileSize = File::size($path);
        if ($fileSize > $this->securityPolicies['max_file_size']) {
            return false;
        }
        
        return true;
    }
    
    private function getRootMetadata(array $root): array
    {
        $path = parse_url($root['uri'], PHP_URL_PATH);
        
        if (!File::exists($path)) {
            return ['accessible' => false];
        }
        
        return [
            'accessible' => true,
            'readable' => File::isReadable($path),
            'writable' => File::isWritable($path),
            'type' => File::isDirectory($path) ? 'directory' : 'file',
        ];
    }
}
```

## Server Configuration

### Enhanced Configuration Schema
```php
return [
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
        'version' => '1.0.0',
        'description' => env('MCP_SERVER_DESCRIPTION', 'MCP Server built with Laravel'),
    ],
    
    'protocol' => [
        'version' => '2024-11-05',
        'timeout' => env('MCP_PROTOCOL_TIMEOUT', 30),
        'max_message_size' => env('MCP_MAX_MESSAGE_SIZE', 1048576), // 1MB
    ],
    
    'capabilities' => [
        'tools' => [
            'enabled' => env('MCP_TOOLS_ENABLED', true),
            'list_changed_notifications' => true,
        ],
        'resources' => [
            'enabled' => env('MCP_RESOURCES_ENABLED', true),
            'list_changed_notifications' => true,
            'subscriptions' => env('MCP_RESOURCE_SUBSCRIPTIONS', true),
        ],
        'prompts' => [
            'enabled' => env('MCP_PROMPTS_ENABLED', true),
            'list_changed_notifications' => true,
        ],
        'completion' => [
            'enabled' => env('MCP_COMPLETION_ENABLED', true),
            'argument_completion' => true,
        ],
        'sampling' => [
            'enabled' => env('MCP_SAMPLING_ENABLED', false),
            'providers' => ['openai', 'anthropic'],
        ],
        'roots' => [
            'enabled' => env('MCP_ROOTS_ENABLED', true),
            'max_depth' => env('MCP_ROOTS_MAX_DEPTH', 5),
        ],
        'logging' => [
            'enabled' => env('MCP_LOGGING_ENABLED', true),
            'level' => env('MCP_LOG_LEVEL', 'info'),
        ],
    ],
    
    // Async processing configuration
    'async' => [
        'enabled' => env('MCP_ASYNC_ENABLED', false),
        'queue' => env('MCP_ASYNC_QUEUE', 'mcp'),
        'timeout' => env('MCP_ASYNC_TIMEOUT', 300),
        'retry_attempts' => env('MCP_ASYNC_RETRY_ATTEMPTS', 3),
    ],
    
    // Circuit breaker configuration
    'circuit_breaker' => [
        'failure_threshold' => env('MCP_CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_timeout' => env('MCP_CIRCUIT_BREAKER_TIMEOUT', 60),
        'half_open_max_calls' => env('MCP_CIRCUIT_BREAKER_HALF_OPEN_CALLS', 3),
    ],
    
    // Retry configuration
    'retry' => [
        'tools/call' => [
            'max_attempts' => 3,
            'base_delay_ms' => 200,
            'max_delay_ms' => 10000,
            'multiplier' => 2.5,
        ],
        'resources/read' => [
            'max_attempts' => 2,
            'base_delay_ms' => 50,
            'max_delay_ms' => 2000,
            'multiplier' => 2,
        ],
    ],
    
    'security' => [
        'authentication' => [
            'enabled' => env('MCP_AUTH_ENABLED', false),
            'method' => env('MCP_AUTH_METHOD', 'token'), // token, basic, oauth, mtls
            'token' => env('MCP_AUTH_TOKEN'),
            'oauth' => [
                'client_id' => env('MCP_OAUTH_CLIENT_ID'),
                'client_secret' => env('MCP_OAUTH_CLIENT_SECRET'),
                'authorization_url' => env('MCP_OAUTH_AUTH_URL'),
                'token_url' => env('MCP_OAUTH_TOKEN_URL'),
            ],
            'mtls' => [
                'ca_cert' => env('MCP_MTLS_CA_CERT'),
                'client_cert' => env('MCP_MTLS_CLIENT_CERT'),
                'client_key' => env('MCP_MTLS_CLIENT_KEY'),
            ],
        ],
        'authorization' => [
            'enabled' => env('MCP_AUTHZ_ENABLED', false),
            'rules' => [
                // Authorization rules would be defined here
            ],
        ],
        'rate_limiting' => [
            'enabled' => env('MCP_RATE_LIMITING_ENABLED', true),
            'max_requests_per_minute' => env('MCP_RATE_LIMIT', 60),
            'burst_limit' => env('MCP_RATE_BURST_LIMIT', 10),
        ],
        'request_signing' => [
            'enabled' => env('MCP_REQUEST_SIGNING_ENABLED', false),
            'algorithm' => env('MCP_SIGNING_ALGORITHM', 'HS256'),
            'secret' => env('MCP_SIGNING_SECRET'),
        ],
    ],
    
    'performance' => [
        'max_concurrent_requests' => env('MCP_MAX_CONCURRENT_REQUESTS', 10),
        'request_timeout' => env('MCP_REQUEST_TIMEOUT', 30),
        'memory_limit' => env('MCP_MEMORY_LIMIT', '256M'),
        'connection_pooling' => [
            'enabled' => env('MCP_CONNECTION_POOLING_ENABLED', true),
            'max_connections' => env('MCP_MAX_CONNECTIONS', 100),
            'idle_timeout' => env('MCP_CONNECTION_IDLE_TIMEOUT', 300),
        ],
    ],
];
```

## Production Deployment Architecture

### Horizontal Scaling Implementation
```php
<?php

namespace JTD\LaravelMCP\Scaling;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Registry\McpRegistry;

class DistributedRegistry
{
    private McpRegistry $localRegistry;
    private string $instanceId;
    private array $clusterNodes = [];
    private int $syncInterval;
    
    public function __construct(McpRegistry $localRegistry, array $config = [])
    {
        $this->localRegistry = $localRegistry;
        $this->instanceId = config('app.name') . '-' . gethostname() . '-' . getmypid();
        $this->syncInterval = $config['sync_interval'] ?? 30; // seconds
        $this->clusterNodes = $config['cluster_nodes'] ?? [];
    }
    
    public function registerComponent(string $type, string $name, array $definition): void
    {
        // Register locally first
        $this->localRegistry->register($type, $name, $definition);
        
        // Broadcast to cluster
        $this->broadcastComponentRegistration($type, $name, $definition);
        
        // Update distributed state
        $this->updateDistributedState($type, $name, $definition, 'register');
    }
    
    public function unregisterComponent(string $type, string $name): void
    {
        // Unregister locally
        $this->localRegistry->unregister($type, $name);
        
        // Broadcast to cluster
        $this->broadcastComponentUnregistration($type, $name);
        
        // Update distributed state
        $this->updateDistributedState($type, $name, null, 'unregister');
    }
    
    public function getComponents(string $type): array
    {
        // Get local components
        $localComponents = $this->localRegistry->getComponents($type);
        
        // Get distributed components
        $distributedComponents = $this->getDistributedComponents($type);
        
        // Merge and deduplicate
        return $this->mergeComponents($localComponents, $distributedComponents);
    }
    
    public function syncWithCluster(): void
    {
        $startTime = microtime(true);
        
        try {
            // Get cluster state
            $clusterState = Redis::hgetall('mcp:cluster:components');
            
            // Update local registry with cluster components
            foreach ($clusterState as $key => $data) {
                [$instanceId, $type, $name] = explode(':', $key, 3);
                
                if ($instanceId !== $this->instanceId) {
                    $definition = json_decode($data, true);
                    $this->localRegistry->registerRemote($type, $name, $definition, $instanceId);
                }
            }
            
            // Update instance heartbeat
            $this->updateHeartbeat();
            
            // Clean up stale instances
            $this->cleanupStaleInstances();
            
            $syncTime = (microtime(true) - $startTime) * 1000;
            
            Log::debug('Cluster sync completed', [
                'sync_time_ms' => $syncTime,
                'cluster_components' => count($clusterState),
            ]);
            
        } catch (\Throwable $e) {
            Log::error('Cluster sync failed', [
                'error' => $e->getMessage(),
                'instance_id' => $this->instanceId,
            ]);
        }
    }
    
    public function startSyncProcess(): void
    {
        // Start background sync process
        $this->schedulePeriodicSync();
        
        // Register shutdown handler
        register_shutdown_function([$this, 'cleanupOnShutdown']);
        
        Log::info('Distributed registry sync started', [
            'instance_id' => $this->instanceId,
            'sync_interval' => $this->syncInterval,
        ]);
    }
    
    private function broadcastComponentRegistration(string $type, string $name, array $definition): void
    {
        $message = [
            'action' => 'register',
            'type' => $type,
            'name' => $name,
            'definition' => $definition,
            'instance_id' => $this->instanceId,
            'timestamp' => now()->toISOString(),
        ];
        
        Redis::publish('mcp:cluster:events', json_encode($message));
    }
    
    private function broadcastComponentUnregistration(string $type, string $name): void
    {
        $message = [
            'action' => 'unregister',
            'type' => $type,
            'name' => $name,
            'instance_id' => $this->instanceId,
            'timestamp' => now()->toISOString(),
        ];
        
        Redis::publish('mcp:cluster:events', json_encode($message));
    }
    
    private function updateDistributedState(string $type, string $name, ?array $definition, string $action): void
    {
        $key = "{$this->instanceId}:{$type}:{$name}";
        
        if ($action === 'register') {
            Redis::hset('mcp:cluster:components', $key, json_encode($definition));
        } else {
            Redis::hdel('mcp:cluster:components', $key);
        }
    }
    
    private function getDistributedComponents(string $type): array
    {
        $pattern = "*:{$type}:*";
        $components = [];
        
        $allComponents = Redis::hgetall('mcp:cluster:components');
        
        foreach ($allComponents as $key => $data) {
            if (fnmatch($pattern, $key)) {
                [$instanceId, , $name] = explode(':', $key, 3);
                
                if ($instanceId !== $this->instanceId) {
                    $definition = json_decode($data, true);
                    $definition['instance_id'] = $instanceId;
                    $definition['is_remote'] = true;
                    
                    $components[$name] = $definition;
                }
            }
        }
        
        return $components;
    }
    
    private function mergeComponents(array $local, array $distributed): array
    {
        $merged = $local;
        
        foreach ($distributed as $name => $definition) {
            if (!isset($merged[$name])) {
                $merged[$name] = $definition;
            } else {
                // Local components take precedence
                $merged[$name]['cluster_alternatives'][] = $definition;
            }
        }
        
        return $merged;
    }
    
    private function updateHeartbeat(): void
    {
        $heartbeat = [
            'instance_id' => $this->instanceId,
            'timestamp' => now()->toISOString(),
            'hostname' => gethostname(),
            'pid' => getmypid(),
            'memory_usage' => memory_get_usage(true),
            'load_average' => sys_getloadavg()[0] ?? null,
        ];
        
        Redis::hset('mcp:cluster:heartbeats', $this->instanceId, json_encode($heartbeat));
        Redis::expire('mcp:cluster:heartbeats', 120); // 2 minute TTL
    }
    
    private function cleanupStaleInstances(): void
    {
        $heartbeats = Redis::hgetall('mcp:cluster:heartbeats');
        $staleThreshold = now()->subSeconds(90); // 90 seconds
        
        foreach ($heartbeats as $instanceId => $data) {
            $heartbeat = json_decode($data, true);
            $lastSeen = \Carbon\Carbon::parse($heartbeat['timestamp']);
            
            if ($lastSeen->lt($staleThreshold)) {
                $this->removeStaleInstance($instanceId);
            }
        }
    }
    
    private function removeStaleInstance(string $staleInstanceId): void
    {
        // Remove from heartbeats
        Redis::hdel('mcp:cluster:heartbeats', $staleInstanceId);
        
        // Remove components from stale instance
        $allComponents = Redis::hgetall('mcp:cluster:components');
        
        foreach ($allComponents as $key => $data) {
            if (str_starts_with($key, $staleInstanceId . ':')) {
                Redis::hdel('mcp:cluster:components', $key);
            }
        }
        
        Log::info("Cleaned up stale instance: $staleInstanceId");
    }
    
    private function schedulePeriodicSync(): void
    {
        // Use Laravel's scheduler or a simple loop for sync
        if (function_exists('pcntl_fork')) {
            $this->forkSyncProcess();
        } else {
            // Fallback to periodic execution during requests
            $this->scheduleRequestBasedSync();
        }
    }
    
    private function forkSyncProcess(): void
    {
        $pid = pcntl_fork();
        
        if ($pid === 0) {
            // Child process - run sync loop
            while (true) {
                $this->syncWithCluster();
                sleep($this->syncInterval);
            }
        } else if ($pid > 0) {
            // Parent process - continue normal operation
            Log::info("Cluster sync process forked with PID: $pid");
        } else {
            Log::error('Failed to fork cluster sync process');
        }
    }
    
    private function scheduleRequestBasedSync(): void
    {
        // Schedule sync to run every N requests or time interval
        Cache::remember('mcp:last_sync', $this->syncInterval, function () {
            $this->syncWithCluster();
            return now();
        });
    }
    
    public function cleanupOnShutdown(): void
    {
        // Remove this instance's components from cluster
        $allComponents = Redis::hgetall('mcp:cluster:components');
        
        foreach ($allComponents as $key => $data) {
            if (str_starts_with($key, $this->instanceId . ':')) {
                Redis::hdel('mcp:cluster:components', $key);
            }
        }
        
        // Remove heartbeat
        Redis::hdel('mcp:cluster:heartbeats', $this->instanceId);
        
        Log::info('Cluster cleanup completed on shutdown');
    }
}
```

### Load Balancing Strategy
```php
<?php

namespace JTD\LaravelMCP\Scaling;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

class LoadBalancer
{
    private array $backends = [];
    private string $strategy;
    private array $healthChecks = [];
    private array $metrics = [];
    
    public function __construct(array $config = [])
    {
        $this->strategy = $config['strategy'] ?? 'round_robin';
        $this->backends = $config['backends'] ?? [];
        $this->initializeHealthChecks();
    }
    
    public function selectBackend(array $context = []): ?array
    {
        $healthyBackends = $this->getHealthyBackends();
        
        if (empty($healthyBackends)) {
            Log::error('No healthy backends available');
            return null;
        }
        
        return match ($this->strategy) {
            'round_robin' => $this->roundRobinSelection($healthyBackends),
            'least_connections' => $this->leastConnectionsSelection($healthyBackends),
            'weighted_round_robin' => $this->weightedRoundRobinSelection($healthyBackends),
            'least_response_time' => $this->leastResponseTimeSelection($healthyBackends),
            'consistent_hash' => $this->consistentHashSelection($healthyBackends, $context),
            'client_affinity' => $this->clientAffinitySelection($healthyBackends, $context),
            default => $this->roundRobinSelection($healthyBackends),
        };
    }
    
    public function recordRequestMetrics(string $backendId, array $metrics): void
    {
        if (!isset($this->metrics[$backendId])) {
            $this->metrics[$backendId] = [
                'total_requests' => 0,
                'active_connections' => 0,
                'avg_response_time' => 0,
                'error_count' => 0,
                'last_updated' => now(),
            ];
        }
        
        $backendMetrics = &$this->metrics[$backendId];
        $backendMetrics['total_requests']++;
        $backendMetrics['last_updated'] = now();
        
        if (isset($metrics['response_time'])) {
            $backendMetrics['avg_response_time'] = (
                ($backendMetrics['avg_response_time'] * ($backendMetrics['total_requests'] - 1)) +
                $metrics['response_time']
            ) / $backendMetrics['total_requests'];
        }
        
        if (isset($metrics['error'])) {
            $backendMetrics['error_count']++;
        }
        
        if (isset($metrics['connection_started'])) {
            $backendMetrics['active_connections']++;
        }
        
        if (isset($metrics['connection_ended'])) {
            $backendMetrics['active_connections'] = max(0, $backendMetrics['active_connections'] - 1);
        }
    }
    
    private function getHealthyBackends(): array
    {
        return array_filter($this->backends, function ($backend) {
            return $this->isBackendHealthy($backend['id']);
        });
    }
    
    private function isBackendHealthy(string $backendId): bool
    {
        $healthCheck = $this->healthChecks[$backendId] ?? null;
        
        if (!$healthCheck) {
            return true; // Assume healthy if no health check configured
        }
        
        // Check if recent health check passed
        $lastCheck = $healthCheck['last_check'] ?? null;
        $healthStatus = $healthCheck['healthy'] ?? true;
        
        if (!$lastCheck || $lastCheck->addSeconds(30)->lt(now())) {
            // Perform health check
            $this->performHealthCheck($backendId);
            $healthStatus = $this->healthChecks[$backendId]['healthy'] ?? false;
        }
        
        return $healthStatus;
    }
    
    private function performHealthCheck(string $backendId): void
    {
        $backend = collect($this->backends)->firstWhere('id', $backendId);
        
        if (!$backend) {
            return;
        }
        
        try {
            $startTime = microtime(true);
            
            // Perform HTTP health check
            $response = \Illuminate\Support\Facades\Http::timeout(5)
                ->get($backend['health_check_url'] ?? $backend['url'] . '/health');
            
            $responseTime = (microtime(true) - $startTime) * 1000;
            $healthy = $response->successful();
            
            $this->healthChecks[$backendId] = [
                'healthy' => $healthy,
                'last_check' => now(),
                'response_time' => $responseTime,
                'status_code' => $response->status(),
            ];
            
            if (!$healthy) {
                Log::warning("Backend health check failed: $backendId", [
                    'status_code' => $response->status(),
                    'response_time' => $responseTime,
                ]);
            }
            
        } catch (\Throwable $e) {
            $this->healthChecks[$backendId] = [
                'healthy' => false,
                'last_check' => now(),
                'error' => $e->getMessage(),
            ];
            
            Log::error("Backend health check error: $backendId", [
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    private function roundRobinSelection(array $backends): array
    {
        static $currentIndex = 0;
        
        $backend = $backends[$currentIndex % count($backends)];
        $currentIndex++;
        
        return $backend;
    }
    
    private function leastConnectionsSelection(array $backends): array
    {
        $leastConnections = PHP_INT_MAX;
        $selectedBackend = null;
        
        foreach ($backends as $backend) {
            $connections = $this->metrics[$backend['id']]['active_connections'] ?? 0;
            
            if ($connections < $leastConnections) {
                $leastConnections = $connections;
                $selectedBackend = $backend;
            }
        }
        
        return $selectedBackend ?? $backends[0];
    }
    
    private function weightedRoundRobinSelection(array $backends): array
    {
        $totalWeight = array_sum(array_column($backends, 'weight'));
        $random = mt_rand(1, $totalWeight);
        $currentWeight = 0;
        
        foreach ($backends as $backend) {
            $currentWeight += $backend['weight'] ?? 1;
            
            if ($random <= $currentWeight) {
                return $backend;
            }
        }
        
        return $backends[0];
    }
    
    private function leastResponseTimeSelection(array $backends): array
    {
        $leastResponseTime = PHP_FLOAT_MAX;
        $selectedBackend = null;
        
        foreach ($backends as $backend) {
            $responseTime = $this->metrics[$backend['id']]['avg_response_time'] ?? 0;
            
            if ($responseTime < $leastResponseTime) {
                $leastResponseTime = $responseTime;
                $selectedBackend = $backend;
            }
        }
        
        return $selectedBackend ?? $backends[0];
    }
    
    private function consistentHashSelection(array $backends, array $context): array
    {
        $key = $context['client_id'] ?? $context['session_id'] ?? 'default';
        $hash = crc32($key);
        $index = $hash % count($backends);
        
        return $backends[$index];
    }
    
    private function clientAffinitySelection(array $backends, array $context): array
    {
        $clientId = $context['client_id'] ?? null;
        
        if (!$clientId) {
            return $this->roundRobinSelection($backends);
        }
        
        // Check if client has an existing affinity
        $affinityKey = "mcp:affinity:$clientId";
        $existingBackend = \Illuminate\Support\Facades\Cache::get($affinityKey);
        
        if ($existingBackend) {
            // Verify backend is still healthy and available
            $backend = collect($backends)->firstWhere('id', $existingBackend);
            
            if ($backend) {
                return $backend;
            }
        }
        
        // Create new affinity
        $selectedBackend = $this->leastConnectionsSelection($backends);
        \Illuminate\Support\Facades\Cache::put($affinityKey, $selectedBackend['id'], 3600); // 1 hour affinity
        
        return $selectedBackend;
    }
    
    private function initializeHealthChecks(): void
    {
        foreach ($this->backends as $backend) {
            $this->healthChecks[$backend['id']] = [
                'healthy' => true,
                'last_check' => null,
            ];
        }
    }
    
    public function getBackendMetrics(): array
    {
        return [
            'backends' => $this->backends,
            'health_checks' => $this->healthChecks,
            'metrics' => $this->metrics,
            'strategy' => $this->strategy,
        ];
    }
}
```

### Session Management Across Instances
```php
<?php

namespace JTD\LaravelMCP\Scaling;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Server\RequestContext;

class SessionManager
{
    private string $instanceId;
    private array $activeSessions = [];
    private int $sessionTtl;
    
    public function __construct(array $config = [])
    {
        $this->instanceId = gethostname() . '-' . getmypid();
        $this->sessionTtl = $config['session_ttl'] ?? 3600; // 1 hour
    }
    
    public function createSession(string $clientId, array $clientInfo = []): string
    {
        $sessionId = uniqid('session_');
        
        $session = [
            'id' => $sessionId,
            'client_id' => $clientId,
            'client_info' => $clientInfo,
            'instance_id' => $this->instanceId,
            'created_at' => now(),
            'last_activity' => now(),
            'requests_count' => 0,
            'capabilities' => [],
            'state' => [],
        ];
        
        // Store locally
        $this->activeSessions[$sessionId] = $session;
        
        // Store in distributed cache
        $this->storeSessionInCache($session);
        
        Log::info("Session created: $sessionId", [
            'client_id' => $clientId,
            'instance_id' => $this->instanceId,
        ]);
        
        return $sessionId;
    }
    
    public function getSession(string $sessionId): ?array
    {
        // Try local cache first
        if (isset($this->activeSessions[$sessionId])) {
            return $this->activeSessions[$sessionId];
        }
        
        // Fallback to distributed cache
        $session = Cache::get("mcp:session:$sessionId");
        
        if ($session) {
            // Cache locally for subsequent requests
            $this->activeSessions[$sessionId] = $session;
        }
        
        return $session;
    }
    
    public function updateSession(string $sessionId, array $updates): bool
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        // Update session data
        $session = array_merge($session, $updates);
        $session['last_activity'] = now();
        
        // Update locally
        $this->activeSessions[$sessionId] = $session;
        
        // Update distributed cache
        $this->storeSessionInCache($session);
        
        return true;
    }
    
    public function recordRequest(string $sessionId, RequestContext $context): void
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            return;
        }
        
        $session['requests_count']++;
        $session['last_activity'] = now();
        $session['last_request'] = [
            'method' => $context->method,
            'timestamp' => $context->startTime,
            'duration_ms' => $context->getDuration(),
        ];
        
        $this->updateSession($sessionId, $session);
    }
    
    public function destroySession(string $sessionId): bool
    {
        // Remove locally
        unset($this->activeSessions[$sessionId]);
        
        // Remove from distributed cache
        Cache::forget("mcp:session:$sessionId");
        
        Log::info("Session destroyed: $sessionId");
        
        return true;
    }
    
    public function cleanupExpiredSessions(): void
    {
        $expiredThreshold = now()->subSeconds($this->sessionTtl);
        
        foreach ($this->activeSessions as $sessionId => $session) {
            $lastActivity = \Carbon\Carbon::parse($session['last_activity']);
            
            if ($lastActivity->lt($expiredThreshold)) {
                $this->destroySession($sessionId);
            }
        }
    }
    
    public function migrateSession(string $sessionId, string $targetInstanceId): bool
    {
        $session = $this->getSession($sessionId);
        
        if (!$session) {
            return false;
        }
        
        // Update instance ownership
        $session['instance_id'] = $targetInstanceId;
        $session['migration_history'][] = [
            'from' => $this->instanceId,
            'to' => $targetInstanceId,
            'timestamp' => now(),
        ];
        
        // Store updated session
        $this->storeSessionInCache($session);
        
        // Remove from local cache
        unset($this->activeSessions[$sessionId]);
        
        Log::info("Session migrated: $sessionId", [
            'from' => $this->instanceId,
            'to' => $targetInstanceId,
        ]);
        
        return true;
    }
    
    public function getSessionAffinity(string $clientId): ?string
    {
        // Find existing session for client
        foreach ($this->activeSessions as $session) {
            if ($session['client_id'] === $clientId) {
                return $session['instance_id'];
            }
        }
        
        // Check distributed cache
        $allSessions = $this->getAllActiveSessions();
        
        foreach ($allSessions as $session) {
            if ($session['client_id'] === $clientId) {
                return $session['instance_id'];
            }
        }
        
        return null;
    }
    
    private function storeSessionInCache(array $session): void
    {
        Cache::put(
            "mcp:session:{$session['id']}",
            $session,
            $this->sessionTtl
        );
        
        // Also store client-to-session mapping
        Cache::put(
            "mcp:client_session:{$session['client_id']}",
            $session['id'],
            $this->sessionTtl
        );
    }
    
    private function getAllActiveSessions(): array
    {
        // This would typically use a more efficient method
        // like scanning Redis keys or using a session index
        $sessions = [];
        
        // For demonstration, we'll use a simple approach
        $sessionKeys = Cache::get('mcp:active_sessions', []);
        
        foreach ($sessionKeys as $sessionId) {
            $session = Cache::get("mcp:session:$sessionId");
            if ($session) {
                $sessions[] = $session;
            }
        }
        
        return $sessions;
    }
    
    public function getInstanceMetrics(): array
    {
        return [
            'instance_id' => $this->instanceId,
            'active_sessions' => count($this->activeSessions),
            'total_requests' => array_sum(array_column($this->activeSessions, 'requests_count')),
            'average_session_duration' => $this->calculateAverageSessionDuration(),
        ];
    }
    
    private function calculateAverageSessionDuration(): float
    {
        if (empty($this->activeSessions)) {
            return 0;
        }
        
        $totalDuration = 0;
        
        foreach ($this->activeSessions as $session) {
            $created = \Carbon\Carbon::parse($session['created_at']);
            $lastActivity = \Carbon\Carbon::parse($session['last_activity']);
            $totalDuration += $created->diffInSeconds($lastActivity);
        }
        
        return $totalDuration / count($this->activeSessions);
    }
}
```

## Error Handling

### Error Response Format
```php
private function formatError(\Throwable $e, ?string $id = null): string
{
    $code = match (true) {
        $e instanceof ParseException => -32700,
        $e instanceof InvalidRequestException => -32600,
        $e instanceof MethodNotFoundException => -32601,
        $e instanceof InvalidParamsException => -32602,
        $e instanceof InternalErrorException => -32603,
        default => -32000, // Server error
    };

    $error = [
        'jsonrpc' => '2.0',
        'error' => [
            'code' => $code,
            'message' => $e->getMessage(),
        ],
        'id' => $id,
    ];

    // Add debug information in development
    if (app()->environment('local', 'development')) {
        $error['error']['data'] = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];
    }

    return json_encode($error);
}
```

## Security Implementation

### Comprehensive Authentication System
```php
<?php

namespace JTD\LaravelMCP\Security;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\AuthenticationException;
use JTD\LaravelMCP\Exceptions\AuthorizationException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthenticationManager
{
    private array $config;
    private array $providers;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'default_method' => 'token',
            'token_ttl' => 3600,
            'refresh_token_ttl' => 604800, // 7 days
            'max_failed_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
        ], $config);
        
        $this->initializeProviders();
    }
    
    public function authenticate(array $credentials, string $method = null): array
    {
        $method = $method ?? $this->config['default_method'];
        
        if (!isset($this->providers[$method])) {
            throw new AuthenticationException("Unsupported authentication method: $method");
        }
        
        $provider = $this->providers[$method];
        
        try {
            $result = $provider->authenticate($credentials);
            
            if ($result['success']) {
                $this->recordSuccessfulAuth($credentials, $method);
                return $this->generateTokens($result['user'], $method);
            } else {
                $this->recordFailedAuth($credentials, $method);
                throw new AuthenticationException($result['message'] ?? 'Authentication failed');
            }
            
        } catch (\Throwable $e) {
            $this->recordFailedAuth($credentials, $method);
            throw new AuthenticationException("Authentication error: {$e->getMessage()}");
        }
    }
    
    public function validateToken(string $token, string $type = 'access'): array
    {
        try {
            $key = new Key($this->getSigningKey(), $this->getSigningAlgorithm());
            $decoded = JWT::decode($token, $key);
            
            $payload = (array) $decoded;
            
            // Validate token type
            if (($payload['type'] ?? 'access') !== $type) {
                throw new AuthenticationException('Invalid token type');
            }
            
            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($payload['jti'] ?? '')) {
                throw new AuthenticationException('Token has been revoked');
            }
            
            return $payload;
            
        } catch (\Throwable $e) {
            throw new AuthenticationException("Token validation failed: {$e->getMessage()}");
        }
    }
    
    public function refreshToken(string $refreshToken): array
    {
        $payload = $this->validateToken($refreshToken, 'refresh');
        
        // Generate new access token
        $user = $this->getUserById($payload['sub']);
        
        if (!$user) {
            throw new AuthenticationException('User not found');
        }
        
        // Blacklist old refresh token
        $this->blacklistToken($payload['jti']);
        
        return $this->generateTokens($user, $payload['auth_method'] ?? 'token');
    }
    
    public function revokeToken(string $token): bool
    {
        try {
            $payload = $this->validateToken($token);
            $this->blacklistToken($payload['jti']);
            
            Log::info('Token revoked', [
                'token_id' => $payload['jti'],
                'user_id' => $payload['sub'],
            ]);
            
            return true;
            
        } catch (\Throwable $e) {
            Log::error('Token revocation failed', [
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    private function generateTokens(array $user, string $authMethod): array
    {
        $now = time();
        $accessTokenId = uniqid('access_');
        $refreshTokenId = uniqid('refresh_');
        
        $accessTokenPayload = [
            'iss' => config('app.url'),
            'sub' => $user['id'],
            'aud' => 'mcp-client',
            'iat' => $now,
            'exp' => $now + $this->config['token_ttl'],
            'jti' => $accessTokenId,
            'type' => 'access',
            'auth_method' => $authMethod,
            'permissions' => $user['permissions'] ?? [],
            'metadata' => [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ],
        ];
        
        $refreshTokenPayload = [
            'iss' => config('app.url'),
            'sub' => $user['id'],
            'aud' => 'mcp-client',
            'iat' => $now,
            'exp' => $now + $this->config['refresh_token_ttl'],
            'jti' => $refreshTokenId,
            'type' => 'refresh',
            'auth_method' => $authMethod,
        ];
        
        $accessToken = JWT::encode($accessTokenPayload, $this->getSigningKey(), $this->getSigningAlgorithm());
        $refreshToken = JWT::encode($refreshTokenPayload, $this->getSigningKey(), $this->getSigningAlgorithm());
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->config['token_ttl'],
            'user' => $user,
        ];
    }
    
    private function initializeProviders(): void
    {
        $this->providers = [
            'token' => new TokenAuthProvider($this->config),
            'basic' => new BasicAuthProvider($this->config),
            'oauth' => new OAuthProvider($this->config),
            'mtls' => new MTLSAuthProvider($this->config),
            'api_key' => new ApiKeyAuthProvider($this->config),
        ];
    }
    
    private function recordSuccessfulAuth(array $credentials, string $method): void
    {
        $identifier = $this->getIdentifierFromCredentials($credentials);
        
        // Clear failed attempts
        \Illuminate\Support\Facades\Cache::forget("auth_failures:$identifier");
        
        Log::info('Successful authentication', [
            'method' => $method,
            'identifier' => $identifier,
            'ip_address' => request()->ip(),
        ]);
    }
    
    private function recordFailedAuth(array $credentials, string $method): void
    {
        $identifier = $this->getIdentifierFromCredentials($credentials);
        $failureKey = "auth_failures:$identifier";
        
        $failures = \Illuminate\Support\Facades\Cache::get($failureKey, 0);
        $failures++;
        
        \Illuminate\Support\Facades\Cache::put($failureKey, $failures, $this->config['lockout_duration']);
        
        if ($failures >= $this->config['max_failed_attempts']) {
            Log::warning('Account locked due to failed authentication attempts', [
                'method' => $method,
                'identifier' => $identifier,
                'failures' => $failures,
                'ip_address' => request()->ip(),
            ]);
        }
        
        Log::warning('Failed authentication attempt', [
            'method' => $method,
            'identifier' => $identifier,
            'failures' => $failures,
            'ip_address' => request()->ip(),
        ]);
    }
    
    private function getIdentifierFromCredentials(array $credentials): string
    {
        return $credentials['username'] ?? $credentials['email'] ?? $credentials['client_id'] ?? 'unknown';
    }
    
    private function isTokenBlacklisted(string $tokenId): bool
    {
        return \Illuminate\Support\Facades\Cache::has("blacklisted_token:$tokenId");
    }
    
    private function blacklistToken(string $tokenId): void
    {
        \Illuminate\Support\Facades\Cache::put("blacklisted_token:$tokenId", true, 86400); // 24 hours
    }
    
    private function getUserById(string $userId): ?array
    {
        // This would typically query a user database
        // For this example, we'll return a mock user
        return [
            'id' => $userId,
            'name' => 'Test User',
            'permissions' => ['tools:read', 'resources:read', 'prompts:read'],
        ];
    }
    
    private function getSigningKey(): string
    {
        return config('laravel-mcp.security.jwt_secret') ?? config('app.key');
    }
    
    private function getSigningAlgorithm(): string
    {
        return config('laravel-mcp.security.jwt_algorithm', 'HS256');
    }
}

// Authentication Provider Interfaces and Implementations
interface AuthProviderInterface
{
    public function authenticate(array $credentials): array;
    public function supports(array $credentials): bool;
}

class TokenAuthProvider implements AuthProviderInterface
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function authenticate(array $credentials): array
    {
        $token = $credentials['token'] ?? null;
        
        if (!$token) {
            return ['success' => false, 'message' => 'Token required'];
        }
        
        $validToken = config('laravel-mcp.security.authentication.token');
        
        if (!$validToken || !hash_equals($validToken, $token)) {
            return ['success' => false, 'message' => 'Invalid token'];
        }
        
        return [
            'success' => true,
            'user' => [
                'id' => 'token_user',
                'name' => 'Token User',
                'permissions' => ['*'], // Full permissions for valid token
            ],
        ];
    }
    
    public function supports(array $credentials): bool
    {
        return isset($credentials['token']);
    }
}

class BasicAuthProvider implements AuthProviderInterface
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function authenticate(array $credentials): array
    {
        $username = $credentials['username'] ?? null;
        $password = $credentials['password'] ?? null;
        
        if (!$username || !$password) {
            return ['success' => false, 'message' => 'Username and password required'];
        }
        
        // This would typically check against a user database
        $users = config('laravel-mcp.security.users', []);
        
        foreach ($users as $user) {
            if ($user['username'] === $username && Hash::check($password, $user['password'])) {
                return [
                    'success' => true,
                    'user' => [
                        'id' => $user['id'],
                        'name' => $user['name'],
                        'permissions' => $user['permissions'] ?? [],
                    ],
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    public function supports(array $credentials): bool
    {
        return isset($credentials['username']) && isset($credentials['password']);
    }
}

class MTLSAuthProvider implements AuthProviderInterface
{
    private array $config;
    
    public function __construct(array $config)
    {
        $this->config = $config;
    }
    
    public function authenticate(array $credentials): array
    {
        $clientCert = $credentials['client_cert'] ?? null;
        
        if (!$clientCert) {
            return ['success' => false, 'message' => 'Client certificate required'];
        }
        
        // Validate client certificate
        if (!$this->validateClientCertificate($clientCert)) {
            return ['success' => false, 'message' => 'Invalid client certificate'];
        }
        
        $certInfo = $this->parseCertificate($clientCert);
        
        return [
            'success' => true,
            'user' => [
                'id' => $certInfo['subject']['CN'] ?? 'unknown',
                'name' => $certInfo['subject']['CN'] ?? 'Certificate User',
                'permissions' => $this->getPermissionsForCertificate($certInfo),
            ],
        ];
    }
    
    public function supports(array $credentials): bool
    {
        return isset($credentials['client_cert']);
    }
    
    private function validateClientCertificate(string $cert): bool
    {
        // Implement certificate validation logic
        $caCert = config('laravel-mcp.security.authentication.mtls.ca_cert');
        
        if (!$caCert) {
            return false;
        }
        
        // This would use OpenSSL functions to validate the certificate
        return openssl_x509_checkpurpose($cert, X509_PURPOSE_SSL_CLIENT, [$caCert]);
    }
    
    private function parseCertificate(string $cert): array
    {
        return openssl_x509_parse($cert);
    }
    
    private function getPermissionsForCertificate(array $certInfo): array
    {
        // Map certificate attributes to permissions
        $cn = $certInfo['subject']['CN'] ?? '';
        
        if (str_starts_with($cn, 'admin-')) {
            return ['*'];
        }
        
        return ['tools:read', 'resources:read'];
    }
}
```

### Authorization System
```php
<?php

namespace JTD\LaravelMCP\Security;

use JTD\LaravelMCP\Exceptions\AuthorizationException;
use Illuminate\Support\Facades\Log;

class AuthorizationManager
{
    private array $policies = [];
    private array $rolePermissions = [];
    private array $resourcePolicies = [];
    
    public function __construct(array $config = [])
    {
        $this->initializePolicies($config);
        $this->initializeRolePermissions($config);
        $this->initializeResourcePolicies($config);
    }
    
    public function authorize(array $user, string $action, ?string $resource = null, array $context = []): bool
    {
        try {
            // Check if user has required permissions
            if (!$this->hasPermission($user, $action, $resource)) {
                return false;
            }
            
            // Apply resource-specific policies
            if ($resource && !$this->checkResourcePolicy($user, $action, $resource, $context)) {
                return false;
            }
            
            // Apply custom policies
            if (!$this->checkCustomPolicies($user, $action, $resource, $context)) {
                return false;
            }
            
            $this->logAuthorizationSuccess($user, $action, $resource, $context);
            return true;
            
        } catch (\Throwable $e) {
            Log::error('Authorization check failed', [
                'user' => $user['id'] ?? 'unknown',
                'action' => $action,
                'resource' => $resource,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }
    
    public function authorizeOrFail(array $user, string $action, ?string $resource = null, array $context = []): void
    {
        if (!$this->authorize($user, $action, $resource, $context)) {
            $this->logAuthorizationFailure($user, $action, $resource, $context);
            
            throw new AuthorizationException(
                "Access denied. User does not have permission to $action" . 
                ($resource ? " on $resource" : '')
            );
        }
    }
    
    public function getPermissions(array $user): array
    {
        $permissions = [];
        
        // Direct permissions
        $directPermissions = $user['permissions'] ?? [];
        $permissions = array_merge($permissions, $directPermissions);
        
        // Role-based permissions
        $roles = $user['roles'] ?? [];
        foreach ($roles as $role) {
            $rolePermissions = $this->rolePermissions[$role] ?? [];
            $permissions = array_merge($permissions, $rolePermissions);
        }
        
        return array_unique($permissions);
    }
    
    public function hasPermission(array $user, string $action, ?string $resource = null): bool
    {
        $permissions = $this->getPermissions($user);
        
        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Check for exact permission match
        $requiredPermission = $resource ? "$resource:$action" : $action;
        
        if (in_array($requiredPermission, $permissions)) {
            return true;
        }
        
        // Check for wildcard resource permissions
        if ($resource && in_array("$resource:*", $permissions)) {
            return true;
        }
        
        // Check for action wildcards
        if (in_array("*:$action", $permissions)) {
            return true;
        }
        
        return false;
    }
    
    private function checkResourcePolicy(array $user, string $action, string $resource, array $context): bool
    {
        $policy = $this->resourcePolicies[$resource] ?? null;
        
        if (!$policy) {
            return true; // No specific policy, allow if has permission
        }
        
        foreach ($policy as $condition) {
            if (!$this->evaluateCondition($condition, $user, $action, $context)) {
                return false;
            }
        }
        
        return true;
    }
    
    private function checkCustomPolicies(array $user, string $action, ?string $resource, array $context): bool
    {
        foreach ($this->policies as $policy) {
            if ($policy['applies_to']($action, $resource, $context)) {
                if (!$policy['check']($user, $action, $resource, $context)) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function evaluateCondition(array $condition, array $user, string $action, array $context): bool
    {
        $type = $condition['type'];
        $params = $condition['params'] ?? [];
        
        return match ($type) {
            'time_based' => $this->checkTimeBasedCondition($params),
            'ip_based' => $this->checkIpBasedCondition($params, $context),
            'rate_limit' => $this->checkRateLimitCondition($params, $user, $action),
            'resource_owner' => $this->checkResourceOwnerCondition($params, $user, $context),
            'custom' => $this->checkCustomCondition($params, $user, $action, $context),
            default => true,
        };
    }
    
    private function checkTimeBasedCondition(array $params): bool
    {
        $currentTime = now();
        
        if (isset($params['allowed_hours'])) {
            $currentHour = $currentTime->hour;
            return in_array($currentHour, $params['allowed_hours']);
        }
        
        if (isset($params['allowed_days'])) {
            $currentDay = $currentTime->dayOfWeek;
            return in_array($currentDay, $params['allowed_days']);
        }
        
        return true;
    }
    
    private function checkIpBasedCondition(array $params, array $context): bool
    {
        $clientIp = $context['ip_address'] ?? request()->ip();
        
        if (isset($params['allowed_ips'])) {
            return in_array($clientIp, $params['allowed_ips']);
        }
        
        if (isset($params['blocked_ips'])) {
            return !in_array($clientIp, $params['blocked_ips']);
        }
        
        if (isset($params['allowed_networks'])) {
            foreach ($params['allowed_networks'] as $network) {
                if ($this->ipInNetwork($clientIp, $network)) {
                    return true;
                }
            }
            return false;
        }
        
        return true;
    }
    
    private function checkRateLimitCondition(array $params, array $user, string $action): bool
    {
        $userId = $user['id'];
        $key = "rate_limit:{$userId}:{$action}";
        $limit = $params['limit'] ?? 100;
        $window = $params['window'] ?? 3600; // 1 hour
        
        $current = \Illuminate\Support\Facades\Cache::get($key, 0);
        
        if ($current >= $limit) {
            return false;
        }
        
        \Illuminate\Support\Facades\Cache::put($key, $current + 1, $window);
        
        return true;
    }
    
    private function checkResourceOwnerCondition(array $params, array $user, array $context): bool
    {
        $resourceOwnerId = $context['resource_owner_id'] ?? null;
        $userId = $user['id'];
        
        // User can access their own resources
        if ($resourceOwnerId === $userId) {
            return true;
        }
        
        // Check if user has admin privileges
        if (in_array('admin', $user['roles'] ?? [])) {
            return true;
        }
        
        return false;
    }
    
    private function checkCustomCondition(array $params, array $user, string $action, array $context): bool
    {
        $callback = $params['callback'] ?? null;
        
        if (!$callback || !is_callable($callback)) {
            return true;
        }
        
        return $callback($user, $action, $context);
    }
    
    private function ipInNetwork(string $ip, string $network): bool
    {
        [$networkIp, $prefixLength] = explode('/', $network);
        
        $ipLong = ip2long($ip);
        $networkLong = ip2long($networkIp);
        $mask = -1 << (32 - $prefixLength);
        
        return ($ipLong & $mask) === ($networkLong & $mask);
    }
    
    private function logAuthorizationSuccess(array $user, string $action, ?string $resource, array $context): void
    {
        Log::info('Authorization granted', [
            'user_id' => $user['id'] ?? 'unknown',
            'action' => $action,
            'resource' => $resource,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
        ]);
    }
    
    private function logAuthorizationFailure(array $user, string $action, ?string $resource, array $context): void
    {
        Log::warning('Authorization denied', [
            'user_id' => $user['id'] ?? 'unknown',
            'action' => $action,
            'resource' => $resource,
            'ip_address' => $context['ip_address'] ?? request()->ip(),
            'user_permissions' => $this->getPermissions($user),
        ]);
    }
    
    private function initializePolicies(array $config): void
    {
        $this->policies = [
            [
                'name' => 'admin_only_tools',
                'applies_to' => fn($action, $resource) => $resource === 'tools' && in_array($action, ['create', 'update', 'delete']),
                'check' => fn($user) => in_array('admin', $user['roles'] ?? []),
            ],
            [
                'name' => 'business_hours_only',
                'applies_to' => fn($action, $resource) => in_array($action, ['execute', 'call']),
                'check' => fn($user, $action, $resource, $context) => $this->checkTimeBasedCondition([
                    'allowed_hours' => range(9, 17), // 9 AM to 5 PM
                ]),
            ],
        ];
        
        // Add custom policies from config
        $customPolicies = $config['policies'] ?? [];
        $this->policies = array_merge($this->policies, $customPolicies);
    }
    
    private function initializeRolePermissions(array $config): void
    {
        $this->rolePermissions = array_merge([
            'admin' => ['*'],
            'operator' => [
                'tools:read', 'tools:call',
                'resources:read',
                'prompts:read', 'prompts:get',
            ],
            'viewer' => [
                'tools:read',
                'resources:read',
                'prompts:read',
            ],
        ], $config['role_permissions'] ?? []);
    }
    
    private function initializeResourcePolicies(array $config): void
    {
        $this->resourcePolicies = array_merge([
            'sensitive_tools' => [
                ['type' => 'ip_based', 'params' => ['allowed_networks' => ['192.168.0.0/16', '10.0.0.0/8']]],
                ['type' => 'time_based', 'params' => ['allowed_hours' => range(9, 17)]],
            ],
            'admin_resources' => [
                ['type' => 'rate_limit', 'params' => ['limit' => 10, 'window' => 3600]],
            ],
        ], $config['resource_policies'] ?? []);
    }
}
```

### Request Signing and Verification
```php
<?php

namespace JTD\LaravelMCP\Security;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\SecurityException;

class RequestSigner
{
    private string $algorithm;
    private string $secret;
    private int $timestampTolerance;
    
    public function __construct(array $config = [])
    {
        $this->algorithm = $config['algorithm'] ?? 'HS256';
        $this->secret = $config['secret'] ?? config('app.key');
        $this->timestampTolerance = $config['timestamp_tolerance'] ?? 300; // 5 minutes
    }
    
    public function signRequest(array $request, string $secret = null): array
    {
        $secret = $secret ?? $this->secret;
        $timestamp = time();
        $nonce = uniqid();
        
        // Create signature payload
        $payload = [
            'method' => $request['method'] ?? '',
            'params' => $request['params'] ?? [],
            'timestamp' => $timestamp,
            'nonce' => $nonce,
        ];
        
        // Generate signature
        $signature = $this->generateSignature($payload, $secret);
        
        // Add signature headers to request
        $request['headers'] = array_merge($request['headers'] ?? [], [
            'X-MCP-Timestamp' => $timestamp,
            'X-MCP-Nonce' => $nonce,
            'X-MCP-Signature' => $signature,
        ]);
        
        return $request;
    }
    
    public function verifyRequest(array $request, string $secret = null): bool
    {
        $secret = $secret ?? $this->secret;
        
        try {
            // Extract signature components
            $headers = $request['headers'] ?? [];
            $timestamp = $headers['X-MCP-Timestamp'] ?? null;
            $nonce = $headers['X-MCP-Nonce'] ?? null;
            $signature = $headers['X-MCP-Signature'] ?? null;
            
            if (!$timestamp || !$nonce || !$signature) {
                throw new SecurityException('Missing required signature headers');
            }
            
            // Check timestamp tolerance
            if (abs(time() - $timestamp) > $this->timestampTolerance) {
                throw new SecurityException('Request timestamp outside tolerance window');
            }
            
            // Check for replay attacks
            if ($this->isNonceUsed($nonce)) {
                throw new SecurityException('Nonce has already been used');
            }
            
            // Verify signature
            $payload = [
                'method' => $request['method'] ?? '',
                'params' => $request['params'] ?? [],
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ];
            
            $expectedSignature = $this->generateSignature($payload, $secret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                throw new SecurityException('Invalid signature');
            }
            
            // Mark nonce as used
            $this->markNonceUsed($nonce);
            
            Log::debug('Request signature verified successfully', [
                'nonce' => $nonce,
                'timestamp' => $timestamp,
            ]);
            
            return true;
            
        } catch (SecurityException $e) {
            Log::warning('Request signature verification failed', [
                'error' => $e->getMessage(),
                'request_method' => $request['method'] ?? 'unknown',
            ]);
            
            return false;
        }
    }
    
    private function generateSignature(array $payload, string $secret): string
    {
        // Create canonical string representation
        $canonicalString = $this->createCanonicalString($payload);
        
        // Generate signature based on algorithm
        return match ($this->algorithm) {
            'HS256' => hash_hmac('sha256', $canonicalString, $secret),
            'HS512' => hash_hmac('sha512', $canonicalString, $secret),
            'RS256' => $this->generateRSASignature($canonicalString, $secret),
            default => throw new SecurityException("Unsupported signature algorithm: {$this->algorithm}"),
        };
    }
    
    private function createCanonicalString(array $payload): string
    {
        // Sort keys for consistent signature generation
        ksort($payload);
        
        // Convert to canonical representation
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
    
    private function generateRSASignature(string $data, string $privateKey): string
    {
        $signature = '';
        
        if (!openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new SecurityException('Failed to generate RSA signature');
        }
        
        return base64_encode($signature);
    }
    
    private function isNonceUsed(string $nonce): bool
    {
        return \Illuminate\Support\Facades\Cache::has("nonce:$nonce");
    }
    
    private function markNonceUsed(string $nonce): void
    {
        \Illuminate\Support\Facades\Cache::put(
            "nonce:$nonce",
            true,
            $this->timestampTolerance * 2 // Cache for twice the tolerance window
        );
    }
}
```

## Monitoring and Diagnostics

### Server Status
```php
public function getStatus(): array
{
    return [
        'server' => [
            'name' => $this->getServerName(),
            'version' => $this->getServerVersion(),
            'uptime' => $this->getUptime(),
            'status' => 'running',
        ],
        'connections' => [
            'active' => $this->transportManager->getActiveConnectionCount(),
            'total' => $this->transportManager->getTotalConnectionCount(),
        ],
        'components' => [
            'tools' => count($this->registry->getTools()),
            'resources' => count($this->registry->getResources()),
            'prompts' => count($this->registry->getPrompts()),
        ],
        'performance' => [
            'requests_processed' => $this->getRequestsProcessed(),
            'average_response_time' => $this->getAverageResponseTime(),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ],
    ];
}
```

### Health Checks
```php
public function healthCheck(): array
{
    $checks = [
        'server' => $this->checkServerHealth(),
        'transports' => $this->checkTransportHealth(),
        'registry' => $this->checkRegistryHealth(),
        'database' => $this->checkDatabaseHealth(),
        'dependencies' => $this->checkDependencyHealth(),
    ];

    $healthy = array_reduce($checks, fn($carry, $check) => $carry && $check['healthy'], true);

    return [
        'healthy' => $healthy,
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ];
}
```

## Observability & Monitoring

### Comprehensive Metrics Collection
```php
<?php

namespace JTD\LaravelMCP\Observability;

use Illuminate\Support\Facades\Log;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\Redis;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;

class MetricsCollector
{
    private CollectorRegistry $registry;
    private array $counters = [];
    private array $histograms = [];
    private array $gauges = [];
    private TracerProviderInterface $tracerProvider;
    private MeterProviderInterface $meterProvider;
    
    public function __construct(
        TracerProviderInterface $tracerProvider,
        MeterProviderInterface $meterProvider
    ) {
        $this->registry = new CollectorRegistry(new Redis());
        $this->tracerProvider = $tracerProvider;
        $this->meterProvider = $meterProvider;
        $this->initializeMetrics();
    }
    
    public function recordRequest(
        string $method,
        string $transport,
        float $duration,
        int $statusCode,
        array $labels = []
    ): void {
        // Record request count
        $this->counters['requests_total']->incBy(1, [
            'method' => $method,
            'transport' => $transport,
            'status_code' => (string) $statusCode,
            ...$labels
        ]);
        
        // Record request duration
        $this->histograms['request_duration_seconds']->observe($duration / 1000, [
            'method' => $method,
            'transport' => $transport,
            ...$labels
        ]);
        
        // Record error count if applicable
        if ($statusCode >= 400) {
            $this->counters['errors_total']->incBy(1, [
                'method' => $method,
                'transport' => $transport,
                'error_type' => $this->categorizeError($statusCode),
                ...$labels
            ]);
        }
    }
    
    public function recordComponentMetrics(string $type, int $count): void
    {
        $this->gauges['components_registered']->set($count, ['type' => $type]);
    }
    
    public function recordConnectionMetrics(string $transport, int $active, int $total): void
    {
        $this->gauges['connections_active']->set($active, ['transport' => $transport]);
        $this->gauges['connections_total']->set($total, ['transport' => $transport]);
    }
    
    public function recordMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        $this->gauges['memory_usage_bytes']->set($memoryUsage);
        $this->gauges['memory_peak_bytes']->set($peakMemory);
    }
    
    public function recordCircuitBreakerState(string $service, string $state): void
    {
        $this->gauges['circuit_breaker_state']->set(
            $this->stateToNumber($state),
            ['service' => $service]
        );
    }
    
    public function startTrace(string $operationName, array $attributes = []): \OpenTelemetry\API\Trace\SpanInterface
    {
        $tracer = $this->tracerProvider->getTracer('mcp-server');
        
        return $tracer->spanBuilder($operationName)
            ->setAttributes($attributes)
            ->startSpan();
    }
    
    public function recordCustomMetric(string $name, float $value, array $labels = []): void
    {
        if (!isset($this->gauges[$name])) {
            $this->gauges[$name] = $this->registry->getOrRegisterGauge(
                'mcp_server',
                $name,
                'Custom metric: ' . $name,
                array_keys($labels)
            );
        }
        
        $this->gauges[$name]->set($value, $labels);
    }
    
    public function getMetrics(): array
    {
        $renderer = new \Prometheus\RenderTextFormat();
        return $renderer->render($this->registry->getMetricFamilySamples());
    }
    
    private function initializeMetrics(): void
    {
        // Request metrics
        $this->counters['requests_total'] = $this->registry->getOrRegisterCounter(
            'mcp_server',
            'requests_total',
            'Total number of MCP requests',
            ['method', 'transport', 'status_code']
        );
        
        $this->counters['errors_total'] = $this->registry->getOrRegisterCounter(
            'mcp_server',
            'errors_total',
            'Total number of errors',
            ['method', 'transport', 'error_type']
        );
        
        // Duration metrics
        $this->histograms['request_duration_seconds'] = $this->registry->getOrRegisterHistogram(
            'mcp_server',
            'request_duration_seconds',
            'Request duration in seconds',
            ['method', 'transport'],
            [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
        );
        
        // System metrics
        $this->gauges['memory_usage_bytes'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'memory_usage_bytes',
            'Current memory usage in bytes'
        );
        
        $this->gauges['memory_peak_bytes'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'memory_peak_bytes',
            'Peak memory usage in bytes'
        );
        
        // Component metrics
        $this->gauges['components_registered'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'components_registered',
            'Number of registered components',
            ['type']
        );
        
        // Connection metrics
        $this->gauges['connections_active'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'connections_active',
            'Number of active connections',
            ['transport']
        );
        
        $this->gauges['connections_total'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'connections_total',
            'Total number of connections',
            ['transport']
        );
        
        // Circuit breaker metrics
        $this->gauges['circuit_breaker_state'] = $this->registry->getOrRegisterGauge(
            'mcp_server',
            'circuit_breaker_state',
            'Circuit breaker state (0=closed, 1=half-open, 2=open)',
            ['service']
        );
    }
    
    private function categorizeError(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'server_error',
            $statusCode >= 400 => 'client_error',
            default => 'unknown',
        };
    }
    
    private function stateToNumber(string $state): int
    {
        return match ($state) {
            'closed' => 0,
            'half_open' => 1,
            'open' => 2,
            default => -1,
        };
    }
}
```

### Distributed Tracing Integration
```php
<?php

namespace JTD\LaravelMCP\Observability;

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use Illuminate\Support\Facades\Log;

class TracingManager
{
    private TracerProviderInterface $tracerProvider;
    private array $activeSpans = [];
    
    public function __construct(TracerProviderInterface $tracerProvider)
    {
        $this->tracerProvider = $tracerProvider;
    }
    
    public function traceRequest(
        string $method,
        array $params,
        string $requestId,
        callable $operation
    ): mixed {
        $tracer = $this->tracerProvider->getTracer('mcp-server');
        
        $span = $tracer->spanBuilder("mcp.request.$method")
            ->setAttributes([
                'mcp.method' => $method,
                'mcp.request_id' => $requestId,
                'mcp.params_count' => count($params),
            ])
            ->startSpan();
        
        $this->activeSpans[$requestId] = $span;
        
        try {
            $context = Context::getCurrent()->withContextValue($span);
            
            return Context::attach($context, function () use ($operation, $span, $method, $params) {
                $result = $operation();
                
                $span->setStatus(StatusCode::STATUS_OK);
                $span->setAttributes([
                    'mcp.response.success' => true,
                    'mcp.response.type' => is_array($result) ? 'array' : gettype($result),
                ]);
                
                return $result;
            });
            
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->setAttributes([
                'mcp.error.type' => get_class($e),
                'mcp.error.message' => $e->getMessage(),
                'mcp.error.file' => $e->getFile(),
                'mcp.error.line' => $e->getLine(),
            ]);
            
            throw $e;
            
        } finally {
            $span->end();
            unset($this->activeSpans[$requestId]);
        }
    }
    
    public function traceToolExecution(
        string $toolName,
        array $arguments,
        callable $execution
    ): mixed {
        $tracer = $this->tracerProvider->getTracer('mcp-tools');
        
        $span = $tracer->spanBuilder("tool.execute.$toolName")
            ->setAttributes([
                'tool.name' => $toolName,
                'tool.arguments_count' => count($arguments),
                'tool.arguments' => json_encode($arguments),
            ])
            ->startSpan();
        
        try {
            $context = Context::getCurrent()->withContextValue($span);
            
            return Context::attach($context, function () use ($execution, $span) {
                $startTime = microtime(true);
                
                $result = $execution();
                
                $duration = (microtime(true) - $startTime) * 1000;
                
                $span->setStatus(StatusCode::STATUS_OK);
                $span->setAttributes([
                    'tool.execution.duration_ms' => $duration,
                    'tool.execution.success' => true,
                ]);
                
                return $result;
            });
            
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->setAttributes([
                'tool.execution.error' => $e->getMessage(),
                'tool.execution.success' => false,
            ]);
            
            throw $e;
            
        } finally {
            $span->end();
        }
    }
    
    public function traceTransportOperation(
        string $transport,
        string $operation,
        callable $transportOperation
    ): mixed {
        $tracer = $this->tracerProvider->getTracer('mcp-transport');
        
        $span = $tracer->spanBuilder("transport.$operation")
            ->setAttributes([
                'transport.type' => $transport,
                'transport.operation' => $operation,
            ])
            ->startSpan();
        
        try {
            $context = Context::getCurrent()->withContextValue($span);
            
            return Context::attach($context, function () use ($transportOperation, $span) {
                $result = $transportOperation();
                
                $span->setStatus(StatusCode::STATUS_OK);
                
                if (is_string($result)) {
                    $span->setAttributes([
                        'transport.message_size' => strlen($result),
                    ]);
                }
                
                return $result;
            });
            
        } catch (\Throwable $e) {
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->setAttributes([
                'transport.error' => $e->getMessage(),
            ]);
            
            throw $e;
            
        } finally {
            $span->end();
        }
    }
    
    public function addSpanAttribute(string $requestId, string $key, mixed $value): void
    {
        if (isset($this->activeSpans[$requestId])) {
            $this->activeSpans[$requestId]->setAttributes([$key => $value]);
        }
    }
    
    public function addSpanEvent(string $requestId, string $name, array $attributes = []): void
    {
        if (isset($this->activeSpans[$requestId])) {
            $this->activeSpans[$requestId]->addEvent($name, $attributes);
        }
    }
}
```

### Performance Monitoring
```php
<?php

namespace JTD\LaravelMCP\Observability;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PerformanceMonitor
{
    private array $measurements = [];
    private array $thresholds;
    private array $alerts = [];
    
    public function __construct(array $config = [])
    {
        $this->thresholds = array_merge([
            'response_time_ms' => 1000,
            'memory_usage_mb' => 256,
            'cpu_usage_percent' => 80,
            'error_rate_percent' => 5,
            'connection_count' => 100,
        ], $config['thresholds'] ?? []);
    }
    
    public function startMeasurement(string $operation, string $id = null): string
    {
        $measurementId = $id ?? uniqid('perf_');
        
        $this->measurements[$measurementId] = [
            'operation' => $operation,
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
            'start_cpu' => $this->getCpuUsage(),
        ];
        
        return $measurementId;
    }
    
    public function endMeasurement(string $measurementId): array
    {
        if (!isset($this->measurements[$measurementId])) {
            return [];
        }
        
        $measurement = $this->measurements[$measurementId];
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endCpu = $this->getCpuUsage();
        
        $result = [
            'operation' => $measurement['operation'],
            'duration_ms' => ($endTime - $measurement['start_time']) * 1000,
            'memory_used_mb' => ($endMemory - $measurement['start_memory']) / 1024 / 1024,
            'cpu_used_percent' => $endCpu - $measurement['start_cpu'],
            'timestamp' => now()->toISOString(),
        ];
        
        // Check for performance issues
        $this->checkPerformanceThresholds($result);
        
        // Store measurement for analysis
        $this->storeMeasurement($result);
        
        unset($this->measurements[$measurementId]);
        
        return $result;
    }
    
    public function recordSystemMetrics(): array
    {
        $metrics = [
            'timestamp' => now()->toISOString(),
            'memory' => [
                'current_mb' => memory_get_usage(true) / 1024 / 1024,
                'peak_mb' => memory_get_peak_usage(true) / 1024 / 1024,
                'limit_mb' => $this->getMemoryLimit() / 1024 / 1024,
            ],
            'cpu' => [
                'usage_percent' => $this->getCpuUsage(),
                'load_average' => sys_getloadavg(),
            ],
            'disk' => [
                'free_gb' => disk_free_space('/') / 1024 / 1024 / 1024,
                'total_gb' => disk_total_space('/') / 1024 / 1024 / 1024,
            ],
        ];
        
        $this->checkSystemThresholds($metrics);
        
        return $metrics;
    }
    
    public function getPerformanceReport(string $period = '1h'): array
    {
        $measurements = $this->getStoredMeasurements($period);
        
        if (empty($measurements)) {
            return ['no_data' => true];
        }
        
        $operations = [];
        
        foreach ($measurements as $measurement) {
            $op = $measurement['operation'];
            
            if (!isset($operations[$op])) {
                $operations[$op] = [
                    'count' => 0,
                    'total_duration' => 0,
                    'min_duration' => PHP_FLOAT_MAX,
                    'max_duration' => 0,
                    'total_memory' => 0,
                    'errors' => 0,
                ];
            }
            
            $operations[$op]['count']++;
            $operations[$op]['total_duration'] += $measurement['duration_ms'];
            $operations[$op]['min_duration'] = min($operations[$op]['min_duration'], $measurement['duration_ms']);
            $operations[$op]['max_duration'] = max($operations[$op]['max_duration'], $measurement['duration_ms']);
            $operations[$op]['total_memory'] += $measurement['memory_used_mb'];
            
            if ($measurement['duration_ms'] > $this->thresholds['response_time_ms']) {
                $operations[$op]['errors']++;
            }
        }
        
        // Calculate averages and rates
        foreach ($operations as $op => &$stats) {
            $stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
            $stats['avg_memory'] = $stats['total_memory'] / $stats['count'];
            $stats['error_rate'] = ($stats['errors'] / $stats['count']) * 100;
        }
        
        return [
            'period' => $period,
            'total_measurements' => count($measurements),
            'operations' => $operations,
            'generated_at' => now()->toISOString(),
        ];
    }
    
    private function checkPerformanceThresholds(array $measurement): void
    {
        $alerts = [];
        
        if ($measurement['duration_ms'] > $this->thresholds['response_time_ms']) {
            $alerts[] = [
                'type' => 'slow_response',
                'message' => "Operation '{$measurement['operation']}' took {$measurement['duration_ms']}ms",
                'threshold' => $this->thresholds['response_time_ms'],
                'actual' => $measurement['duration_ms'],
            ];
        }
        
        if ($measurement['memory_used_mb'] > $this->thresholds['memory_usage_mb']) {
            $alerts[] = [
                'type' => 'high_memory_usage',
                'message' => "Operation '{$measurement['operation']}' used {$measurement['memory_used_mb']}MB",
                'threshold' => $this->thresholds['memory_usage_mb'],
                'actual' => $measurement['memory_used_mb'],
            ];
        }
        
        foreach ($alerts as $alert) {
            $this->triggerAlert($alert);
        }
    }
    
    private function checkSystemThresholds(array $metrics): void
    {
        $alerts = [];
        
        if ($metrics['memory']['current_mb'] > $this->thresholds['memory_usage_mb']) {
            $alerts[] = [
                'type' => 'high_system_memory',
                'message' => "System memory usage: {$metrics['memory']['current_mb']}MB",
                'threshold' => $this->thresholds['memory_usage_mb'],
                'actual' => $metrics['memory']['current_mb'],
            ];
        }
        
        if ($metrics['cpu']['usage_percent'] > $this->thresholds['cpu_usage_percent']) {
            $alerts[] = [
                'type' => 'high_cpu_usage',
                'message' => "CPU usage: {$metrics['cpu']['usage_percent']}%",
                'threshold' => $this->thresholds['cpu_usage_percent'],
                'actual' => $metrics['cpu']['usage_percent'],
            ];
        }
        
        foreach ($alerts as $alert) {
            $this->triggerAlert($alert);
        }
    }
    
    private function triggerAlert(array $alert): void
    {
        $alertKey = md5(json_encode($alert));
        
        // Rate limit alerts (don't spam the same alert)
        if (Cache::has("alert_sent:$alertKey")) {
            return;
        }
        
        Log::warning('Performance alert triggered', $alert);
        
        // Store alert
        $this->alerts[] = array_merge($alert, [
            'timestamp' => now()->toISOString(),
        ]);
        
        // Rate limit for 5 minutes
        Cache::put("alert_sent:$alertKey", true, 300);
        
        // Send notification if configured
        $this->sendAlertNotification($alert);
    }
    
    private function sendAlertNotification(array $alert): void
    {
        // This would integrate with notification systems
        // Slack, email, PagerDuty, etc.
        
        $notificationConfig = config('laravel-mcp.monitoring.notifications');
        
        if ($notificationConfig['slack']['enabled'] ?? false) {
            $this->sendSlackAlert($alert, $notificationConfig['slack']);
        }
        
        if ($notificationConfig['email']['enabled'] ?? false) {
            $this->sendEmailAlert($alert, $notificationConfig['email']);
        }
    }
    
    private function sendSlackAlert(array $alert, array $config): void
    {
        // Implement Slack notification
        $webhookUrl = $config['webhook_url'];
        $message = [
            'text' => "MCP Server Alert: {$alert['message']}",
            'attachments' => [
                [
                    'color' => 'warning',
                    'fields' => [
                        [
                            'title' => 'Alert Type',
                            'value' => $alert['type'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Threshold',
                            'value' => $alert['threshold'],
                            'short' => true,
                        ],
                        [
                            'title' => 'Actual Value',
                            'value' => $alert['actual'],
                            'short' => true,
                        ],
                    ],
                ],
            ],
        ];
        
        // Send HTTP request to Slack webhook
        \Illuminate\Support\Facades\Http::post($webhookUrl, $message);
    }
    
    private function sendEmailAlert(array $alert, array $config): void
    {
        // Implement email notification
        // This would use Laravel's mail system
    }
    
    private function getCpuUsage(): float
    {
        // Simple CPU usage calculation
        // In production, you'd want a more sophisticated approach
        $load = sys_getloadavg()[0] ?? 0;
        return min($load * 100, 100);
    }
    
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return PHP_INT_MAX;
        }
        
        return $this->parseBytes($limit);
    }
    
    private function parseBytes(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int) $size;
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
    
    private function storeMeasurement(array $measurement): void
    {
        // Store in cache or database for later analysis
        $key = 'perf_measurements:' . date('Y-m-d-H');
        $measurements = Cache::get($key, []);
        $measurements[] = $measurement;
        
        // Keep measurements for 24 hours
        Cache::put($key, $measurements, 86400);
    }
    
    private function getStoredMeasurements(string $period): array
    {
        $measurements = [];
        $hours = $this->periodToHours($period);
        
        for ($i = 0; $i < $hours; $i++) {
            $timestamp = now()->subHours($i);
            $key = 'perf_measurements:' . $timestamp->format('Y-m-d-H');
            $hourMeasurements = Cache::get($key, []);
            $measurements = array_merge($measurements, $hourMeasurements);
        }
        
        return $measurements;
    }
    
    private function periodToHours(string $period): int
    {
        return match ($period) {
            '15m' => 1,
            '1h' => 1,
            '6h' => 6,
            '24h' => 24,
            default => 1,
        };
    }
}
```

### Health Check System
```php
<?php

namespace JTD\LaravelMCP\Observability;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HealthChecker
{
    private array $checks = [];
    private array $criticalChecks = [];
    
    public function __construct(array $config = [])
    {
        $this->initializeChecks($config);
    }
    
    public function performHealthCheck(): array
    {
        $results = [];
        $overallHealth = true;
        $criticalFailures = [];
        
        foreach ($this->checks as $checkName => $check) {
            $startTime = microtime(true);
            
            try {
                $result = $check['callable']();
                $duration = (microtime(true) - $startTime) * 1000;
                
                $checkResult = [
                    'name' => $checkName,
                    'healthy' => $result['healthy'] ?? true,
                    'message' => $result['message'] ?? 'OK',
                    'duration_ms' => round($duration, 2),
                    'metadata' => $result['metadata'] ?? [],
                    'critical' => in_array($checkName, $this->criticalChecks),
                ];
                
                if (!$checkResult['healthy']) {
                    $overallHealth = false;
                    
                    if ($checkResult['critical']) {
                        $criticalFailures[] = $checkName;
                    }
                }
                
                $results[$checkName] = $checkResult;
                
            } catch (\Throwable $e) {
                $duration = (microtime(true) - $startTime) * 1000;
                
                $checkResult = [
                    'name' => $checkName,
                    'healthy' => false,
                    'message' => $e->getMessage(),
                    'duration_ms' => round($duration, 2),
                    'metadata' => ['exception' => get_class($e)],
                    'critical' => in_array($checkName, $this->criticalChecks),
                ];
                
                $overallHealth = false;
                
                if ($checkResult['critical']) {
                    $criticalFailures[] = $checkName;
                }
                
                $results[$checkName] = $checkResult;
                
                Log::error("Health check failed: $checkName", [
                    'error' => $e->getMessage(),
                    'duration_ms' => $duration,
                ]);
            }
        }
        
        return [
            'healthy' => $overallHealth,
            'critical_failures' => $criticalFailures,
            'checks' => $results,
            'timestamp' => now()->toISOString(),
            'summary' => [
                'total_checks' => count($results),
                'passed' => count(array_filter($results, fn($r) => $r['healthy'])),
                'failed' => count(array_filter($results, fn($r) => !$r['healthy'])),
                'critical_failed' => count($criticalFailures),
            ],
        ];
    }
    
    public function addCustomCheck(string $name, callable $check, bool $critical = false): void
    {
        $this->checks[$name] = ['callable' => $check];
        
        if ($critical) {
            $this->criticalChecks[] = $name;
        }
    }
    
    private function initializeChecks(array $config): void
    {
        $this->checks = [
            'database' => ['callable' => [$this, 'checkDatabase']],
            'redis' => ['callable' => [$this, 'checkRedis']],
            'memory' => ['callable' => [$this, 'checkMemory']],
            'disk_space' => ['callable' => [$this, 'checkDiskSpace']],
            'mcp_components' => ['callable' => [$this, 'checkMcpComponents']],
        ];
        
        $this->criticalChecks = [
            'database',
            'memory',
            'disk_space',
        ];
        
        // Add external service checks if configured
        $externalServices = $config['external_services'] ?? [];
        
        foreach ($externalServices as $service => $url) {
            $this->checks["external_$service"] = [
                'callable' => function () use ($service, $url) {
                    return $this->checkExternalService($service, $url);
                }
            ];
        }
    }
    
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            DB::select('SELECT 1');
            $duration = (microtime(true) - $startTime) * 1000;
            
            return [
                'healthy' => true,
                'message' => 'Database connection successful',
                'metadata' => [
                    'query_duration_ms' => round($duration, 2),
                    'connection' => config('database.default'),
                ],
            ];
            
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Database connection failed: ' . $e->getMessage(),
                'metadata' => ['error' => get_class($e)],
            ];
        }
    }
    
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            Redis::ping();
            $duration = (microtime(true) - $startTime) * 1000;
            
            $info = Redis::info();
            
            return [
                'healthy' => true,
                'message' => 'Redis connection successful',
                'metadata' => [
                    'ping_duration_ms' => round($duration, 2),
                    'version' => $info['Server']['redis_version'] ?? 'unknown',
                    'memory_used_mb' => isset($info['Memory']['used_memory']) 
                        ? round($info['Memory']['used_memory'] / 1024 / 1024, 2)
                        : null,
                ],
            ];
            
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'Redis connection failed: ' . $e->getMessage(),
                'metadata' => ['error' => get_class($e)],
            ];
        }
    }
    
    private function checkMemory(): array
    {
        $currentUsage = memory_get_usage(true);
        $peakUsage = memory_get_peak_usage(true);
        $memoryLimit = $this->getMemoryLimit();
        
        $usagePercent = $memoryLimit > 0 ? ($currentUsage / $memoryLimit) * 100 : 0;
        $healthy = $usagePercent < 90;
        
        return [
            'healthy' => $healthy,
            'message' => $healthy 
                ? sprintf('Memory usage: %.1f%%', $usagePercent)
                : sprintf('High memory usage: %.1f%%', $usagePercent),
            'metadata' => [
                'current_mb' => round($currentUsage / 1024 / 1024, 2),
                'peak_mb' => round($peakUsage / 1024 / 1024, 2),
                'limit_mb' => $memoryLimit > 0 ? round($memoryLimit / 1024 / 1024, 2) : 'unlimited',
                'usage_percent' => round($usagePercent, 1),
            ],
        ];
    }
    
    private function checkDiskSpace(): array
    {
        $freeBytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        
        $usagePercent = (($totalBytes - $freeBytes) / $totalBytes) * 100;
        $healthy = $usagePercent < 90;
        
        return [
            'healthy' => $healthy,
            'message' => $healthy
                ? sprintf('Disk usage: %.1f%%', $usagePercent)
                : sprintf('High disk usage: %.1f%%', $usagePercent),
            'metadata' => [
                'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
                'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
                'usage_percent' => round($usagePercent, 1),
            ],
        ];
    }
    
    private function checkMcpComponents(): array
    {
        try {
            // This would check if MCP components are properly registered
            $registry = app(\JTD\LaravelMCP\Registry\McpRegistry::class);
            
            $toolCount = count($registry->getTools());
            $resourceCount = count($registry->getResources());
            $promptCount = count($registry->getPrompts());
            
            $totalComponents = $toolCount + $resourceCount + $promptCount;
            $healthy = $totalComponents > 0;
            
            return [
                'healthy' => $healthy,
                'message' => $healthy
                    ? "$totalComponents MCP components registered"
                    : 'No MCP components registered',
                'metadata' => [
                    'tools' => $toolCount,
                    'resources' => $resourceCount,
                    'prompts' => $promptCount,
                    'total' => $totalComponents,
                ],
            ];
            
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => 'MCP component check failed: ' . $e->getMessage(),
                'metadata' => ['error' => get_class($e)],
            ];
        }
    }
    
    private function checkExternalService(string $service, string $url): array
    {
        try {
            $startTime = microtime(true);
            $response = Http::timeout(10)->get($url);
            $duration = (microtime(true) - $startTime) * 1000;
            
            $healthy = $response->successful();
            
            return [
                'healthy' => $healthy,
                'message' => $healthy
                    ? "$service is responding"
                    : "$service returned {$response->status()}",
                'metadata' => [
                    'url' => $url,
                    'status_code' => $response->status(),
                    'response_time_ms' => round($duration, 2),
                ],
            ];
            
        } catch (\Throwable $e) {
            return [
                'healthy' => false,
                'message' => "$service is unreachable: " . $e->getMessage(),
                'metadata' => [
                    'url' => $url,
                    'error' => get_class($e),
                ],
            ];
        }
    }
    
    private function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');
        
        if ($limit === '-1') {
            return 0; // Unlimited
        }
        
        $value = (int) $limit;
        $unit = strtolower(substr($limit, -1));
        
        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }
}
```

## Performance Optimization

### Request Processing
- Asynchronous message processing where possible
- Connection pooling for HTTP transport
- Message batching for bulk operations
- Caching for frequently accessed data

### Resource Management
- Memory usage monitoring and cleanup
- Connection lifecycle management
- Garbage collection optimization
- Resource pooling for expensive operations

### Scalability Features
- Horizontal scaling via load balancing
- State externalization for multi-instance deployments
- Background job processing for long-running tasks
- Real-time notification distribution