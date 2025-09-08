# Transport Layer Specification

## Overview

The Transport Layer provides communication mechanisms between MCP clients and the Laravel MCP server. It implements both HTTP and Stdio transports as specified by the MCP protocol, with Laravel-specific optimizations and integrations.

## Transport Architecture

### Transport Interface
```php
<?php

namespace JTD\LaravelMCP\Transport\Contracts;

interface TransportInterface
{
    public function initialize(array $config = []): void;
    public function start(): void;
    public function stop(): void;
    public function send(string $message): void;
    public function receive(): ?string;
    public function isConnected(): bool;
    public function getConnectionInfo(): array;
}
```

### Transport Manager
```php
<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Exceptions\TransportException;

class TransportManager
{
    private array $transports = [];
    private ?TransportInterface $activeTransport = null;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge(config('laravel-mcp.transports', []), $config);
        $this->registerTransports();
    }

    public function createTransport(string $type, array $config = []): TransportInterface
    {
        if (!isset($this->transports[$type])) {
            throw new TransportException("Unknown transport type: $type");
        }

        $transportClass = $this->transports[$type];
        return new $transportClass(array_merge($this->config[$type] ?? [], $config));
    }

    public function getActiveTransport(): ?TransportInterface
    {
        return $this->activeTransport;
    }

    public function setActiveTransport(string $type, array $config = []): void
    {
        $this->activeTransport = $this->createTransport($type, $config);
    }

    private function registerTransports(): void
    {
        $this->transports = [
            'stdio' => StdioTransport::class,
            'http' => HttpTransport::class,
        ];
    }
}
```

## Stdio Transport

### Implementation
```php
<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use Symfony\Component\Process\Process;

class StdioTransport implements TransportInterface
{
    private $stdin;
    private $stdout;
    private bool $initialized = false;
    private bool $running = false;
    private JsonRpcHandler $jsonRpcHandler;
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'timeout' => 30,
            'buffer_size' => 8192,
            'max_message_size' => 1048576, // 1MB
        ], $config);
    }

    public function initialize(array $config = []): void
    {
        if ($this->initialized) {
            return;
        }

        $this->config = array_merge($this->config, $config);
        
        // Initialize stdio streams
        $this->stdin = fopen('php://stdin', 'r');
        $this->stdout = fopen('php://stdout', 'w');
        
        if (!$this->stdin || !$this->stdout) {
            throw new TransportException('Failed to initialize stdio streams');
        }

        // Set non-blocking mode
        stream_set_blocking($this->stdin, false);
        stream_set_blocking($this->stdout, false);

        $this->initialized = true;
    }

    public function start(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $this->running = true;
        
        // Register shutdown handler
        register_shutdown_function([$this, 'stop']);
        
        // Handle signals for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        $this->processMessages();
    }

    public function stop(): void
    {
        $this->running = false;
        
        if ($this->stdin) {
            fclose($this->stdin);
        }
        
        if ($this->stdout) {
            fclose($this->stdout);
        }
    }

    public function send(string $message): void
    {
        if (!$this->stdout) {
            throw new TransportException('Output stream not available');
        }

        $written = fwrite($this->stdout, $message . "\n");
        
        if ($written === false) {
            throw new TransportException('Failed to write message to stdout');
        }

        fflush($this->stdout);
    }

    public function receive(): ?string
    {
        if (!$this->stdin) {
            return null;
        }

        $message = '';
        $buffer = fgets($this->stdin, $this->config['buffer_size']);
        
        if ($buffer === false) {
            return null;
        }

        $message .= $buffer;
        
        // Check for complete message (ends with newline)
        if (substr($message, -1) === "\n") {
            return trim($message);
        }

        // Continue reading if message is incomplete
        while (strlen($message) < $this->config['max_message_size']) {
            $buffer = fgets($this->stdin, $this->config['buffer_size']);
            
            if ($buffer === false) {
                break;
            }
            
            $message .= $buffer;
            
            if (substr($buffer, -1) === "\n") {
                break;
            }
        }

        return strlen($message) > 0 ? trim($message) : null;
    }

    private function processMessages(): void
    {
        while ($this->running) {
            $message = $this->receive();
            
            if ($message !== null) {
                try {
                    $response = $this->jsonRpcHandler->processRequest($message);
                    $this->send($response);
                } catch (\Throwable $e) {
                    $this->handleError($e);
                }
            }

            // Prevent busy waiting
            usleep(10000); // 10ms
            
            // Handle signals if available
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    public function handleSignal(int $signal): void
    {
        match ($signal) {
            SIGTERM, SIGINT => $this->stop(),
            default => null,
        };
    }
}
```

## HTTP Transport

### Implementation
```php
<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Transport\Contracts\TransportInterface;
use JTD\LaravelMCP\Http\Controllers\McpController;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HttpTransport implements TransportInterface
{
    private bool $initialized = false;
    private bool $running = false;
    private array $config;
    private McpController $controller;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 8000,
            'path' => '/mcp',
            'middleware' => ['api'],
            'cors_enabled' => true,
            'auth_enabled' => false,
        ], $config);
    }

    public function initialize(array $config = []): void
    {
        if ($this->initialized) {
            return;
        }

        $this->config = array_merge($this->config, $config);
        $this->controller = app(McpController::class);
        
        $this->registerRoutes();
        $this->initialized = true;
    }

    public function start(): void
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        $this->running = true;
        
        // HTTP transport doesn't need active listening
        // as Laravel handles HTTP requests automatically
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function send(string $message): void
    {
        // HTTP transport sends responses through Laravel's response system
        // This method is used internally by the controller
    }

    public function receive(): ?string
    {
        // HTTP transport receives messages through Laravel's request system
        // This method is used internally by the controller
        return null;
    }

    private function registerRoutes(): void
    {
        Route::group([
            'middleware' => $this->config['middleware'],
            'prefix' => ltrim($this->config['path'], '/'),
        ], function () {
            // JSON-RPC endpoint
            Route::post('/', [McpController::class, 'handle']);
            
            // Server-Sent Events endpoint for notifications
            Route::get('/events', [McpController::class, 'events']);
            
            // Health check endpoint
            Route::get('/health', [McpController::class, 'health']);
            
            // Server info endpoint
            Route::get('/info', [McpController::class, 'info']);
        });
    }
}
```

### HTTP Controller
```php
<?php

namespace JTD\LaravelMCP\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;
use JTD\LaravelMCP\McpServer;

class McpController extends Controller
{
    private JsonRpcHandler $jsonRpcHandler;
    private McpServer $mcpServer;

    public function __construct(JsonRpcHandler $jsonRpcHandler, McpServer $mcpServer)
    {
        $this->jsonRpcHandler = $jsonRpcHandler;
        $this->mcpServer = $mcpServer;
    }

    public function handle(Request $request): Response
    {
        try {
            $rawMessage = $request->getContent();
            $response = $this->jsonRpcHandler->processRequest($rawMessage);
            
            return new Response($response, 200, [
                'Content-Type' => 'application/json',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Content-Type, Authorization',
            ]);
        } catch (\Throwable $e) {
            return $this->handleError($e);
        }
    }

    public function events(Request $request): Response
    {
        $response = new Response();
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');
        $response->headers->set('Access-Control-Allow-Origin', '*');

        // Set up Server-Sent Events stream
        $callback = function () {
            $this->streamEvents();
        };

        $response->setCallback($callback);
        return $response;
    }

    public function health(): Response
    {
        $status = $this->mcpServer->healthCheck();
        
        return response()->json($status, $status['healthy'] ? 200 : 503);
    }

    public function info(): Response
    {
        $info = $this->mcpServer->getStatus();
        
        return response()->json($info);
    }

    private function streamEvents(): void
    {
        while (true) {
            // Send keepalive
            echo "event: keepalive\n";
            echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
            
            if (connection_aborted()) {
                break;
            }
            
            sleep(30); // Send keepalive every 30 seconds
        }
    }
}
```

## Transport Configuration

### Configuration Schema
```php
return [
    'default' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),
    
    'stdio' => [
        'enabled' => env('MCP_STDIO_ENABLED', true),
        'timeout' => env('MCP_STDIO_TIMEOUT', 30),
        'buffer_size' => env('MCP_STDIO_BUFFER_SIZE', 8192),
        'max_message_size' => env('MCP_STDIO_MAX_MESSAGE_SIZE', 1048576),
    ],
    
    'http' => [
        'enabled' => env('MCP_HTTP_ENABLED', true),
        'host' => env('MCP_HTTP_HOST', '127.0.0.1'),
        'port' => env('MCP_HTTP_PORT', 8000),
        'path' => env('MCP_HTTP_PATH', '/mcp'),
        'middleware' => [
            'mcp.cors',
            'throttle:60,1',
        ],
        'cors' => [
            'enabled' => env('MCP_CORS_ENABLED', true),
            'allowed_origins' => explode(',', env('MCP_CORS_ORIGINS', '*')),
            'allowed_methods' => ['POST', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
        ],
        'ssl' => [
            'enabled' => env('MCP_SSL_ENABLED', false),
            'cert_path' => env('MCP_SSL_CERT'),
            'key_path' => env('MCP_SSL_KEY'),
        ],
    ],
];
```

## Middleware Implementation

### CORS Middleware
```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class McpCorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $this->getCorsHeaders());
        }

        $response = $next($request);
        
        foreach ($this->getCorsHeaders() as $header => $value) {
            $response->headers->set($header, $value);
        }

        return $response;
    }

    private function getCorsHeaders(): array
    {
        $config = config('laravel-mcp.transports.http.cors', []);
        
        return [
            'Access-Control-Allow-Origin' => implode(', ', $config['allowed_origins'] ?? ['*']),
            'Access-Control-Allow-Methods' => implode(', ', $config['allowed_methods'] ?? ['POST', 'OPTIONS']),
            'Access-Control-Allow-Headers' => implode(', ', $config['allowed_headers'] ?? ['Content-Type', 'Authorization']),
            'Access-Control-Max-Age' => '86400',
        ];
    }
}
```

### Authentication Middleware
```php
<?php

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use JTD\LaravelMCP\Exceptions\AuthenticationException;

class McpAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $config = config('laravel-mcp.security.authentication', []);
        
        if (!($config['enabled'] ?? false)) {
            return $next($request);
        }

        $method = $config['method'] ?? 'token';
        
        switch ($method) {
            case 'token':
                $this->validateTokenAuth($request, $config);
                break;
            case 'basic':
                $this->validateBasicAuth($request, $config);
                break;
            default:
                throw new AuthenticationException('Invalid authentication method');
        }

        return $next($request);
    }

    private function validateTokenAuth(Request $request, array $config): void
    {
        $token = $request->bearerToken() ?? $request->input('token');
        $expectedToken = $config['token'] ?? null;
        
        if (!$token || !$expectedToken || !hash_equals($expectedToken, $token)) {
            throw new AuthenticationException('Invalid authentication token');
        }
    }

    private function validateBasicAuth(Request $request, array $config): void
    {
        $credentials = $request->getUser() ? [
            'username' => $request->getUser(),
            'password' => $request->getPassword(),
        ] : null;
        
        if (!$credentials || 
            $credentials['username'] !== ($config['username'] ?? '') ||
            $credentials['password'] !== ($config['password'] ?? '')) {
            throw new AuthenticationException('Invalid authentication credentials');
        }
    }
}
```

## Performance Optimization

### Connection Pooling
```php
<?php

namespace JTD\LaravelMCP\Transport;

class ConnectionPool
{
    private array $connections = [];
    private int $maxConnections;
    private int $timeout;

    public function __construct(int $maxConnections = 10, int $timeout = 30)
    {
        $this->maxConnections = $maxConnections;
        $this->timeout = $timeout;
    }

    public function getConnection(string $key): ?TransportInterface
    {
        if (isset($this->connections[$key])) {
            $connection = $this->connections[$key];
            
            if ($connection['expires'] > time() && $connection['transport']->isConnected()) {
                return $connection['transport'];
            }
            
            unset($this->connections[$key]);
        }

        return null;
    }

    public function addConnection(string $key, TransportInterface $transport): void
    {
        if (count($this->connections) >= $this->maxConnections) {
            $this->evictOldestConnection();
        }

        $this->connections[$key] = [
            'transport' => $transport,
            'expires' => time() + $this->timeout,
            'created' => time(),
        ];
    }

    private function evictOldestConnection(): void
    {
        if (empty($this->connections)) {
            return;
        }

        $oldest = array_reduce($this->connections, function ($carry, $item) {
            return $carry === null || $item['created'] < $carry['created'] ? $item : $carry;
        });

        if ($oldest) {
            $key = array_search($oldest, $this->connections);
            $oldest['transport']->stop();
            unset($this->connections[$key]);
        }
    }
}
```

### Message Batching
```php
trait SupportsBatching
{
    private array $batchedMessages = [];
    private int $batchSize = 10;
    private int $batchTimeout = 100; // milliseconds

    public function addToBatch(string $message): void
    {
        $this->batchedMessages[] = [
            'message' => $message,
            'timestamp' => microtime(true),
        ];

        if (count($this->batchedMessages) >= $this->batchSize) {
            $this->processBatch();
        }
    }

    public function processBatch(): void
    {
        if (empty($this->batchedMessages)) {
            return;
        }

        $batch = array_map(fn($item) => $item['message'], $this->batchedMessages);
        $this->sendBatch($batch);
        
        $this->batchedMessages = [];
    }

    protected function sendBatch(array $messages): void
    {
        // Implementation depends on transport type
    }
}
```

## Error Handling and Recovery

### Transport Resilience
```php
trait SupportsResilience
{
    private int $maxRetries = 3;
    private int $retryDelay = 1000; // milliseconds
    
    public function sendWithRetry(string $message): bool
    {
        $attempts = 0;
        
        while ($attempts < $this->maxRetries) {
            try {
                $this->send($message);
                return true;
            } catch (TransportException $e) {
                $attempts++;
                
                if ($attempts >= $this->maxRetries) {
                    throw $e;
                }
                
                usleep($this->retryDelay * 1000 * $attempts); // Exponential backoff
            }
        }
        
        return false;
    }

    public function reconnect(): void
    {
        $this->stop();
        sleep(1);
        $this->initialize();
        $this->start();
    }
}
```