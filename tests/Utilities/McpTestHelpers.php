<?php

namespace JTD\LaravelMCP\Tests\Utilities;

use JTD\LaravelMCP\Registry\McpRegistry;

/**
 * MCP Testing Helper Trait
 *
 * Provides utilities for testing MCP components and protocol interactions.
 */
trait McpTestHelpers
{
    /**
     * Create a mock JSON-RPC request.
     *
     * @param  string  $method  Method name
     * @param  array  $params  Method parameters
     * @param  mixed  $id  Request ID
     */
    protected function mockJsonRpcRequest(string $method, array $params = [], $id = 1): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ];
    }

    /**
     * Create a mock JSON-RPC notification.
     *
     * @param  string  $method  Method name
     * @param  array  $params  Method parameters
     */
    protected function mockJsonRpcNotification(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * Register a mock tool for testing.
     *
     * @param  string  $name  Tool name
     * @param  \Closure  $handler  Tool handler
     */
    protected function registerMockTool(string $name, \Closure $handler): void
    {
        $mockTool = new class($name, $handler) extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            private string $toolName;

            private \Closure $toolHandler;

            public function __construct(string $name, \Closure $handler)
            {
                $this->toolName = $name;
                $this->toolHandler = $handler;
                parent::__construct();
            }

            public function getName(): string
            {
                return $this->toolName;
            }

            public function getDescription(): string
            {
                return "Mock tool for testing: {$this->toolName}";
            }

            public function getInputSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            protected function handle(array $parameters): mixed
            {
                return ($this->toolHandler)($parameters);
            }
        };

        $registry = $this->app->make(McpRegistry::class);
        $registry->register('tool', $name, $mockTool);
    }

    /**
     * Register a mock resource for testing.
     *
     * @param  string  $name  Resource name
     * @param  string  $uri  Resource URI
     * @param  \Closure  $handler  Resource handler
     */
    protected function registerMockResource(string $name, string $uri, \Closure $handler): void
    {
        $mockResource = new class($name, $uri, $handler) extends \JTD\LaravelMCP\Abstracts\McpResource
        {
            private string $resourceName;

            private string $resourceUri;

            private \Closure $resourceHandler;

            public function __construct(string $name, string $uri, \Closure $handler)
            {
                $this->resourceName = $name;
                $this->resourceUri = $uri;
                $this->resourceHandler = $handler;
                parent::__construct();
            }

            public function getName(): string
            {
                return $this->resourceName;
            }

            public function getUri(): string
            {
                return $this->resourceUri;
            }

            public function getDescription(): string
            {
                return "Mock resource for testing: {$this->resourceName}";
            }

            public function getMimeType(): string
            {
                return 'text/plain';
            }

            public function read(array $options = []): array
            {
                return ($this->resourceHandler)($options);
            }
        };

        $registry = $this->app->make(McpRegistry::class);
        $registry->register('resource', $name, $mockResource);
    }

    /**
     * Register a mock prompt for testing.
     *
     * @param  string  $name  Prompt name
     * @param  \Closure  $handler  Prompt handler
     */
    protected function registerMockPrompt(string $name, \Closure $handler): void
    {
        $mockPrompt = new class($name, $handler) extends \JTD\LaravelMCP\Abstracts\McpPrompt
        {
            private string $promptName;

            private \Closure $promptHandler;

            public function __construct(string $name, \Closure $handler)
            {
                $this->promptName = $name;
                $this->promptHandler = $handler;
            }

            public function getName(): string
            {
                return $this->promptName;
            }

            public function getDescription(): string
            {
                return "Mock prompt for testing: {$this->promptName}";
            }

            public function getArgumentsSchema(): array
            {
                return [
                    'type' => 'object',
                    'properties' => [],
                ];
            }

            public function getMessages(array $arguments): array
            {
                return ($this->promptHandler)($arguments);
            }
        };

        $registry = $this->app->make(McpRegistry::class);
        $registry->register('prompt', $name, $mockPrompt);
    }

    /**
     * Expect a tool execution with specific parameters.
     *
     * @param  string  $toolName  Tool name
     * @param  array|null  $expectedParams  Expected parameters
     */
    protected function expectsToolExecution(string $toolName, ?array $expectedParams = null): void
    {
        $this->registerMockTool($toolName, function ($params) use ($expectedParams) {
            if ($expectedParams !== null) {
                $this->assertEquals($expectedParams, $params);
            }

            return ['executed' => true, 'params' => $params];
        });
    }

    /**
     * Expect a resource read with specific options.
     *
     * @param  string  $resourceName  Resource name
     * @param  string  $uri  Resource URI
     * @param  array|null  $expectedOptions  Expected options
     */
    protected function expectsResourceRead(string $resourceName, string $uri, ?array $expectedOptions = null): void
    {
        $this->registerMockResource($resourceName, $uri, function ($options) use ($expectedOptions) {
            if ($expectedOptions !== null) {
                $this->assertEquals($expectedOptions, $options);
            }

            return [
                'contents' => [
                    [
                        'uri' => $options['uri'] ?? 'test://resource',
                        'mimeType' => 'text/plain',
                        'text' => 'Test resource content',
                    ],
                ],
            ];
        });
    }

    /**
     * Assert that a tool exists in the registry.
     *
     * @param  string  $name  Tool name
     */
    protected function assertToolExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('tool', $name), "Tool '{$name}' does not exist");
    }

    /**
     * Assert that a resource exists in the registry.
     *
     * @param  string  $name  Resource name
     */
    protected function assertResourceExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('resource', $name), "Resource '{$name}' does not exist");
    }

    /**
     * Assert that a prompt exists in the registry.
     *
     * @param  string  $name  Prompt name
     */
    protected function assertPromptExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('prompt', $name), "Prompt '{$name}' does not exist");
    }

    /**
     * Assert that a tool executes successfully.
     *
     * @param  string  $name  Tool name
     * @param  array  $params  Tool parameters
     */
    protected function assertToolExecutes(string $name, array $params = []): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);

        $this->assertNotNull($tool, "Tool '{$name}' not found");

        // Should not throw exception
        $result = $tool->execute($params);
        $this->assertNotNull($result);
    }

    /**
     * Assert that a tool returns expected result.
     *
     * @param  string  $name  Tool name
     * @param  array  $params  Tool parameters
     * @param  mixed  $expectedResult  Expected result
     */
    protected function assertToolReturns(string $name, array $params, $expectedResult): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);

        $this->assertNotNull($tool, "Tool '{$name}' not found");

        $result = $tool->execute($params);
        $this->assertEquals($expectedResult, $result);
    }

    /**
     * Assert that a resource can be read.
     *
     * @param  string  $name  Resource name
     * @param  array  $options  Read options
     */
    protected function assertResourceReads(string $name, array $options = []): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $resource = $registry->getResource($name);

        $this->assertNotNull($resource, "Resource '{$name}' not found");

        // Should not throw exception
        $result = $resource->read($options);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('contents', $result);
    }

    /**
     * Assert that a prompt generates messages.
     *
     * @param  string  $name  Prompt name
     * @param  array  $arguments  Prompt arguments
     */
    protected function assertPromptGeneratesMessages(string $name, array $arguments = []): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $prompt = $registry->getPrompt($name);

        $this->assertNotNull($prompt, "Prompt '{$name}' not found");

        // Should not throw exception
        $result = $prompt->getMessages($arguments);
        $this->assertNotNull($result);
        $this->assertArrayHasKey('messages', $result);
    }

    /**
     * Create an initialize request for MCP protocol.
     *
     * @param  array  $capabilities  Client capabilities
     * @param  array  $clientInfo  Client information
     */
    protected function createInitializeRequest(array $capabilities = [], array $clientInfo = []): array
    {
        return $this->mockJsonRpcRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => $capabilities,
            'clientInfo' => array_merge([
                'name' => 'Test Client',
                'version' => '1.0.0',
            ], $clientInfo),
        ]);
    }

    /**
     * Create a tools/list request for MCP protocol.
     *
     * @param  mixed  $id  Request ID
     */
    protected function createToolsListRequest($id = 1): array
    {
        return $this->mockJsonRpcRequest('tools/list', [], $id);
    }

    /**
     * Create a tools/call request for MCP protocol.
     *
     * @param  string  $toolName  Tool name
     * @param  array  $arguments  Tool arguments
     * @param  mixed  $id  Request ID
     */
    protected function createToolCallRequest(string $toolName, array $arguments = [], $id = 1): array
    {
        return $this->mockJsonRpcRequest('tools/call', [
            'name' => $toolName,
            'arguments' => $arguments,
        ], $id);
    }

    /**
     * Create a resources/list request for MCP protocol.
     *
     * @param  mixed  $id  Request ID
     */
    protected function createResourcesListRequest($id = 1): array
    {
        return $this->mockJsonRpcRequest('resources/list', [], $id);
    }

    /**
     * Create a resources/read request for MCP protocol.
     *
     * @param  string  $uri  Resource URI
     * @param  mixed  $id  Request ID
     */
    protected function createResourceReadRequest(string $uri, $id = 1): array
    {
        return $this->mockJsonRpcRequest('resources/read', [
            'uri' => $uri,
        ], $id);
    }

    /**
     * Create a prompts/list request for MCP protocol.
     *
     * @param  mixed  $id  Request ID
     */
    protected function createPromptsListRequest($id = 1): array
    {
        return $this->mockJsonRpcRequest('prompts/list', [], $id);
    }

    /**
     * Create a prompts/get request for MCP protocol.
     *
     * @param  string  $promptName  Prompt name
     * @param  array  $arguments  Prompt arguments
     * @param  mixed  $id  Request ID
     */
    protected function createPromptGetRequest(string $promptName, array $arguments = [], $id = 1): array
    {
        return $this->mockJsonRpcRequest('prompts/get', [
            'name' => $promptName,
            'arguments' => $arguments,
        ], $id);
    }

    /**
     * Assert that a response contains a successful result.
     *
     * @param  array  $response  Response to check
     */
    protected function assertSuccessfulResponse(array $response): void
    {
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayNotHasKey('error', $response);
    }

    /**
     * Assert that a response contains an error.
     *
     * @param  array  $response  Response to check
     * @param  int|null  $expectedCode  Expected error code
     * @param  string|null  $expectedMessage  Expected error message substring
     */
    protected function assertErrorResponse(array $response, ?int $expectedCode = null, ?string $expectedMessage = null): void
    {
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayNotHasKey('result', $response);

        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['error']['code']);
        }

        if ($expectedMessage !== null) {
            $this->assertStringContainsString($expectedMessage, $response['error']['message']);
        }
    }

    /**
     * Get the JSON-RPC error codes for reference.
     */
    protected function getJsonRpcErrorCodes(): array
    {
        return [
            'PARSE_ERROR' => -32700,
            'INVALID_REQUEST' => -32600,
            'METHOD_NOT_FOUND' => -32601,
            'INVALID_PARAMS' => -32602,
            'INTERNAL_ERROR' => -32603,
        ];
    }

    /**
     * Simulate a complete MCP session flow.
     *
     * @param  \Closure  $session  Session callback
     */
    protected function simulateMcpSession(\Closure $session): void
    {
        // Initialize session
        $initRequest = $this->createInitializeRequest();
        $initResponse = $this->processJsonRpcRequest($initRequest);
        $this->assertSuccessfulResponse($initResponse);

        // Run session callback
        $session($this);

        // Clean up session
        $this->clearMcpRegistrations();
    }
}
