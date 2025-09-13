<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JTD\LaravelMCP\Http\Exceptions\McpValidationException;
use JTD\LaravelMCP\Support\McpConstants;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MCP Validation Middleware
 *
 * Provides comprehensive validation for MCP requests:
 * - JSON-RPC 2.0 structure validation
 * - MCP protocol compliance validation
 * - Parameter validation using Laravel's validation system
 * - Custom validation rules for MCP-specific requirements
 * - Detailed error messages for invalid requests
 */
class McpValidationMiddleware
{
    /**
     * @var ValidationFactory Laravel validation factory
     */
    protected ValidationFactory $validator;

    /**
     * @var LoggerInterface|null Logger instance
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @var array Configuration settings
     */
    protected array $config = [
        'enabled' => true,
        'strict_content_type' => true,
        'max_request_size' => 10485760,
        'strict_mcp_methods' => false,
        'supported_protocol_versions' => [],
        'allow_custom_tools' => true,
        'custom_methods' => [],
        'method_rules' => [],
        'capabilities' => [
            'tools' => true,
            'resources' => true,
            'prompts' => true,
            'logging' => true,
            'completion' => true,
            'sampling' => true,
        ],
    ];

    /**
     * @var array Valid JSON-RPC methods for MCP
     */
    protected array $validMcpMethods = [
        // Tool methods
        'tools/list',
        'tools/call',
        // Resource methods
        'resources/list',
        'resources/read',
        'resources/write',
        'resources/delete',
        'resources/subscribe',
        'resources/unsubscribe',
        // Prompt methods
        'prompts/list',
        'prompts/get',
        // Server methods
        'initialize',
        'initialized',
        'shutdown',
        'ping',
        // Notification methods
        'notifications/cancelled',
        'notifications/progress',
        'notifications/message',
        'notifications/resources/updated',
        'notifications/resources/list/changed',
        'notifications/tools/list/changed',
        'notifications/prompts/list/changed',
        // Logging methods
        'logging/setLevel',
        // Completion methods
        'completion/complete',
        // Sampling methods
        'sampling/createMessage',
    ];

    /**
     * Create a new middleware instance.
     */
    public function __construct(ValidationFactory $validator, ?array $config = null, ?LoggerInterface $logger = null)
    {
        $this->validator = $validator;
        $this->logger = $logger ?? new NullLogger;

        // Initialize supported protocol versions
        if (empty($this->config['supported_protocol_versions'])) {
            $this->config['supported_protocol_versions'] = McpConstants::getSupportedVersionsForValidation();
        }

        // Override default config if provided
        if ($config !== null) {
            $this->config = array_replace_recursive($this->config, $config);
        } elseif (function_exists('config')) {
            // Load from Laravel config if available
            $this->loadConfigFromLaravel();
        }
    }

    /**
     * Load configuration from Laravel config files.
     */
    protected function loadConfigFromLaravel(): void
    {
        $this->config['enabled'] = config('laravel-mcp.validation.enabled', true);
        $this->config['strict_content_type'] = config('laravel-mcp.validation.strict_content_type', true);
        $this->config['max_request_size'] = config('laravel-mcp.validation.max_request_size', 10485760);
        $this->config['strict_mcp_methods'] = config('laravel-mcp.validation.strict_mcp_methods', false);
        $this->config['supported_protocol_versions'] = config('laravel-mcp.validation.supported_protocol_versions', McpConstants::getSupportedVersionsForValidation());
        $this->config['allow_custom_tools'] = config('laravel-mcp.validation.allow_custom_tools', true);
        $this->config['custom_methods'] = config('laravel-mcp.validation.custom_methods', []);
        $this->config['method_rules'] = config('laravel-mcp.validation.method_rules', []);

        // Load capabilities
        $this->config['capabilities']['tools'] = config('laravel-mcp.capabilities.tools', true);
        $this->config['capabilities']['resources'] = config('laravel-mcp.capabilities.resources', true);
        $this->config['capabilities']['prompts'] = config('laravel-mcp.capabilities.prompts', true);
        $this->config['capabilities']['logging'] = config('laravel-mcp.capabilities.logging', true);
        $this->config['capabilities']['completion'] = config('laravel-mcp.capabilities.completion', true);
        $this->config['capabilities']['sampling'] = config('laravel-mcp.capabilities.sampling', true);
    }

    /**
     * Set configuration value.
     */
    public function setConfig(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (! isset($config[$k]) || ! is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    /**
     * Get configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $config = $this->config;

        foreach ($keys as $k) {
            if (! isset($config[$k])) {
                return $default;
            }
            $config = $config[$k];
        }

        return $config;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Skip validation if disabled
        if (! $this->isValidationEnabled()) {
            return $next($request);
        }

        try {
            // Validate request structure
            $this->validateRequestStructure($request);

            // Validate JSON-RPC format if JSON request
            if ($request->isJson()) {
                $this->validateJsonRpcRequest($request);
            }

            // Validate MCP-specific requirements
            $this->validateMcpRequest($request);

            // Validate method-specific parameters
            $this->validateMethodParameters($request);

            return $next($request);
        } catch (McpValidationException $e) {
            return $this->validationErrorResponse($e, $request);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('MCP validation error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }

            return $this->errorResponse('Validation error occurred', $request);
        }
    }

    /**
     * Check if validation is enabled.
     */
    protected function isValidationEnabled(): bool
    {
        return $this->getConfig('enabled', true);
    }

    /**
     * Validate basic request structure.
     */
    protected function validateRequestStructure(Request $request): void
    {
        // Ensure request has proper content type for JSON-RPC
        if ($request->isMethod('POST') && ! $request->isJson()) {
            if ($this->getConfig('strict_content_type', true)) {
                throw McpValidationException::withMessages([
                    'content_type' => ['Request must have Content-Type: application/json'],
                ]);
            }
        }

        // Validate request size
        $maxSize = $this->getConfig('max_request_size', 10485760); // 10MB default
        if ($request->getContent() && strlen($request->getContent()) > $maxSize) {
            throw McpValidationException::withMessages([
                'request_size' => ['Request size exceeds maximum allowed size'],
            ]);
        }
    }

    /**
     * Validate JSON-RPC request format.
     */
    protected function validateJsonRpcRequest(Request $request): void
    {
        $data = $request->json()->all();

        // Basic JSON-RPC structure validation
        $rules = [
            'jsonrpc' => 'required|in:2.0',
            'method' => 'required|string',
            'id' => 'nullable',
        ];

        $messages = [
            'jsonrpc.required' => 'JSON-RPC version is required',
            'jsonrpc.in' => 'JSON-RPC version must be 2.0',
            'method.required' => 'Method name is required',
            'method.string' => 'Method must be a string',
        ];

        $validator = $this->validator->make($data, $rules, $messages);

        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors) {
                throw McpValidationException::withMessages($errors->toArray());
            }
            throw McpValidationException::withMessages(['validation' => ['Validation failed']]);
        }

        // Validate method format
        if (! $this->isValidMethodFormat($data['method'])) {
            throw McpValidationException::withMessages([
                'method' => ['Invalid method format. Must be in format: category/action'],
            ]);
        }

        // Validate params structure if present
        if (isset($data['params']) && ! is_array($data['params'])) {
            throw McpValidationException::withMessages([
                'params' => ['Parameters must be an object or array'],
            ]);
        }

        // Validate ID format
        if (isset($data['id'])) {
            if (! is_string($data['id']) && ! is_numeric($data['id']) && ! is_null($data['id'])) {
                throw McpValidationException::withMessages([
                    'id' => ['ID must be a string, number, or null'],
                ]);
            }
        }
    }

    /**
     * Validate MCP-specific request requirements.
     */
    protected function validateMcpRequest(Request $request): void
    {
        if (! $request->isJson()) {
            return;
        }

        $data = $request->json()->all();
        $method = $data['method'] ?? '';

        // Check if method is valid for MCP
        if ($this->getConfig('strict_mcp_methods', false)) {
            if (! $this->isValidMcpMethod($method)) {
                throw McpValidationException::withMessages([
                    'method' => ["Unknown MCP method: {$method}"],
                ]);
            }
        }

        // Validate protocol version for certain methods
        if ($method === 'initialize') {
            $this->validateInitializeRequest($data);
        }

        // Validate capabilities for certain methods
        if (in_array($method, ['tools/call', 'resources/read', 'prompts/get'])) {
            $this->validateCapabilityRequest($method, $data);
        }
    }

    /**
     * Validate method-specific parameters.
     */
    protected function validateMethodParameters(Request $request): void
    {
        if (! $request->isJson()) {
            return;
        }

        $data = $request->json()->all();
        $method = $data['method'] ?? '';
        $params = $data['params'] ?? [];

        // Get validation rules for the specific method
        $rules = $this->getMethodValidationRules($method);

        if (empty($rules)) {
            return;
        }

        // Apply custom validation rules from config
        $customRules = $this->getConfig("method_rules.{$method}", []);
        $rules = array_merge($rules, $customRules);

        // Validate parameters
        $validator = $this->validator->make($params, $rules);

        if ($validator->fails()) {
            $errors = $validator->errors();
            if ($errors) {
                throw McpValidationException::withMessages($errors->toArray());
            }
            throw McpValidationException::withMessages(['validation' => ['Parameter validation failed']]);
        }
    }

    /**
     * Get validation rules for specific method.
     */
    protected function getMethodValidationRules(string $method): array
    {
        return match ($method) {
            'initialize' => [
                'protocolVersion' => 'required|string',
                'capabilities' => 'required|array',
                'clientInfo' => 'required|array',
                'clientInfo.name' => 'required|string',
                'clientInfo.version' => 'required|string',
            ],
            'tools/call' => [
                'name' => 'required|string',
                'arguments' => 'nullable|array',
            ],
            'resources/read' => [
                'uri' => 'required|string',
            ],
            'resources/write' => [
                'uri' => 'required|string',
                'contents' => 'required',
            ],
            'resources/delete' => [
                'uri' => 'required|string',
            ],
            'resources/subscribe' => [
                'uri' => 'required|string',
            ],
            'resources/unsubscribe' => [
                'uri' => 'required|string',
            ],
            'prompts/get' => [
                'name' => 'required|string',
                'arguments' => 'nullable|array',
            ],
            'logging/setLevel' => [
                'level' => 'required|in:debug,info,warning,error',
            ],
            'completion/complete' => [
                'ref' => 'required',
                'argument' => 'required|array',
                'argument.name' => 'required|string',
                'argument.value' => 'required|string',
            ],
            'sampling/createMessage' => [
                'messages' => 'required|array|min:1',
                'messages.*.role' => 'required|in:user,assistant',
                'messages.*.content' => 'required',
                'modelPreferences' => 'nullable|array',
                'systemPrompt' => 'nullable|string',
                'includeContext' => 'nullable|in:none,thisServer,allServers',
                'temperature' => 'nullable|numeric|min:0|max:2',
                'maxTokens' => 'nullable|integer|min:1',
                'stopSequences' => 'nullable|array',
                'metadata' => 'nullable|array',
            ],
            default => [],
        };
    }

    /**
     * Validate initialize request.
     */
    protected function validateInitializeRequest(array $data): void
    {
        $params = $data['params'] ?? [];

        // Validate protocol version
        if (isset($params['protocolVersion'])) {
            $supportedVersions = $this->getConfig('supported_protocol_versions', McpConstants::getSupportedVersionsForValidation());
            if (! in_array($params['protocolVersion'], $supportedVersions)) {
                throw McpValidationException::withMessages([
                    'protocolVersion' => ["Unsupported protocol version: {$params['protocolVersion']}"],
                ]);
            }
        }

        // Validate required capabilities
        if (isset($params['capabilities'])) {
            $this->validateCapabilities($params['capabilities']);
        }
    }

    /**
     * Validate capabilities.
     */
    protected function validateCapabilities(array $capabilities): void
    {
        $validCapabilities = [
            'tools',
            'resources',
            'prompts',
            'logging',
            'completion',
            'sampling',
        ];

        foreach ($capabilities as $capability => $config) {
            if (! in_array($capability, $validCapabilities)) {
                if ($this->logger) {
                    $this->logger->warning('Unknown capability requested', ['capability' => $capability]);
                }
            }

            // Validate capability configuration
            if (! is_array($config) && ! is_bool($config)) {
                throw McpValidationException::withMessages([
                    "capabilities.{$capability}" => ['Capability configuration must be an array or boolean'],
                ]);
            }
        }
    }

    /**
     * Validate capability-specific request.
     */
    protected function validateCapabilityRequest(string $method, array $data): void
    {
        $capability = explode('/', $method)[0];
        $enabled = $this->getConfig("capabilities.{$capability}", true);

        if (! $enabled) {
            throw McpValidationException::withMessages([
                'method' => ["Capability '{$capability}' is not enabled"],
            ]);
        }
    }

    /**
     * Check if method format is valid.
     */
    protected function isValidMethodFormat(string $method): bool
    {
        // Allow single-word methods (initialize, shutdown, ping)
        if (! str_contains($method, '/')) {
            return in_array($method, ['initialize', 'initialized', 'shutdown', 'ping']);
        }

        // Check category/action format
        $parts = explode('/', $method);
        if (count($parts) < 2) {
            return false;
        }

        // Validate each part
        foreach ($parts as $part) {
            if (empty($part) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $part)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if method is valid MCP method.
     */
    protected function isValidMcpMethod(string $method): bool
    {
        // Check against known MCP methods
        if (in_array($method, $this->validMcpMethods)) {
            return true;
        }

        // Check custom methods from config
        $customMethods = $this->getConfig('custom_methods', []);
        if (in_array($method, $customMethods)) {
            return true;
        }

        // Allow custom tool methods if pattern matches
        if (str_starts_with($method, 'tools/') && $this->getConfig('allow_custom_tools', true)) {
            return true;
        }

        return false;
    }

    /**
     * Return validation error response.
     */
    protected function validationErrorResponse(McpValidationException $exception, Request $request): JsonResponse
    {
        $errors = $exception->errors();
        $firstError = reset($errors);
        $message = is_array($firstError) ? $firstError[0] : $firstError;

        $data = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32602,
                'message' => 'Invalid params',
                'data' => [
                    'validation_errors' => $errors,
                    'message' => $message,
                ],
            ],
            'id' => $request->isJson() ? $request->json('id') : null,
        ];

        return new JsonResponse($data, Response::HTTP_BAD_REQUEST);
    }

    /**
     * Return error response.
     */
    protected function errorResponse(string $message, ?Request $request = null): JsonResponse
    {
        $data = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32603,
                'message' => $message,
                'data' => [
                    'type' => 'validation_error',
                ],
            ],
            'id' => null,
        ];

        // Try to get request ID if available
        if ($request && $request->isJson()) {
            $data['id'] = $request->json('id');
        }

        return new JsonResponse($data, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
