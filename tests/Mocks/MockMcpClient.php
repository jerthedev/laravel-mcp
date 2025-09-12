<?php

namespace JTD\LaravelMCP\Tests\Mocks;

/**
 * Mock MCP Client
 *
 * Simulates an MCP client for testing server implementations.
 */
class MockMcpClient
{
    /**
     * Client configuration.
     */
    private array $config;

    /**
     * Messages received by the client.
     */
    private array $receivedMessages = [];

    /**
     * Messages sent by the client.
     */
    private array $sentMessages = [];

    /**
     * Client state.
     */
    private string $state = 'disconnected';

    /**
     * Protocol version.
     */
    private string $protocolVersion = '2024-11-05';

    /**
     * Create a new mock MCP client.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'name' => 'Mock MCP Client',
            'version' => '1.0.0',
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => false],
                'prompts' => ['listChanged' => true],
            ],
        ], $config);
    }

    /**
     * Send a message to the server.
     */
    public function sendMessage(array $message): array
    {
        $this->sentMessages[] = $message;

        // Simulate server response based on method
        return $this->simulateServerResponse($message);
    }

    /**
     * Send an initialization request.
     */
    public function initialize(): array
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => $this->protocolVersion,
                'capabilities' => $this->config['capabilities'],
                'clientInfo' => [
                    'name' => $this->config['name'],
                    'version' => $this->config['version'],
                ],
            ],
            'id' => $this->generateId(),
        ];

        $response = $this->sendMessage($request);

        if (! isset($response['error'])) {
            $this->state = 'initialized';
        }

        return $response;
    }

    /**
     * List available tools.
     */
    public function listTools(): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => $this->generateId(),
        ]);
    }

    /**
     * Call a tool.
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => $name,
                'arguments' => $arguments,
            ],
            'id' => $this->generateId(),
        ]);
    }

    /**
     * List available resources.
     */
    public function listResources(): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'resources/list',
            'id' => $this->generateId(),
        ]);
    }

    /**
     * Read a resource.
     */
    public function readResource(string $uri): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [
                'uri' => $uri,
            ],
            'id' => $this->generateId(),
        ]);
    }

    /**
     * List available prompts.
     */
    public function listPrompts(): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'prompts/list',
            'id' => $this->generateId(),
        ]);
    }

    /**
     * Get a prompt.
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        return $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => 'prompts/get',
            'params' => [
                'name' => $name,
                'arguments' => $arguments,
            ],
            'id' => $this->generateId(),
        ]);
    }

    /**
     * Send a notification.
     */
    public function sendNotification(string $method, array $params = []): void
    {
        $this->sendMessage([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ]);
    }

    /**
     * Receive a message from the server.
     */
    public function receiveMessage(array $message): void
    {
        $this->receivedMessages[] = $message;
    }

    /**
     * Get all sent messages.
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Get all received messages.
     */
    public function getReceivedMessages(): array
    {
        return $this->receivedMessages;
    }

    /**
     * Get the client state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Set the client state.
     */
    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * Clear message history.
     */
    public function clearHistory(): void
    {
        $this->sentMessages = [];
        $this->receivedMessages = [];
    }

    /**
     * Generate a unique request ID.
     */
    private function generateId(): int
    {
        static $id = 0;

        return ++$id;
    }

    /**
     * Simulate server response based on method.
     */
    private function simulateServerResponse(array $message): array
    {
        $method = $message['method'] ?? '';
        $id = $message['id'] ?? null;

        // Notification - no response
        if ($id === null) {
            return [];
        }

        return match ($method) {
            'initialize' => $this->simulateInitializeResponse($message),
            'tools/list' => $this->simulateToolsListResponse($message),
            'tools/call' => $this->simulateToolCallResponse($message),
            'resources/list' => $this->simulateResourcesListResponse($message),
            'resources/read' => $this->simulateResourceReadResponse($message),
            'prompts/list' => $this->simulatePromptsListResponse($message),
            'prompts/get' => $this->simulatePromptGetResponse($message),
            default => [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found',
                ],
                'id' => $id,
            ],
        };
    }

    /**
     * Simulate initialize response.
     */
    private function simulateInitializeResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => $this->protocolVersion,
                'capabilities' => [
                    'tools' => ['listChanged' => true],
                    'resources' => ['subscribe' => true],
                    'prompts' => ['listChanged' => true],
                ],
                'serverInfo' => [
                    'name' => 'Mock MCP Server',
                    'version' => '1.0.0',
                ],
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate tools/list response.
     */
    private function simulateToolsListResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'tools' => [
                    [
                        'name' => 'mock_tool',
                        'description' => 'A mock tool for testing',
                        'inputSchema' => [
                            'type' => 'object',
                            'properties' => [],
                        ],
                    ],
                ],
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate tools/call response.
     */
    private function simulateToolCallResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Mock tool executed successfully',
                    ],
                ],
                'isError' => false,
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate resources/list response.
     */
    private function simulateResourcesListResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'resources' => [
                    [
                        'uri' => 'mock://resource',
                        'name' => 'mock_resource',
                        'description' => 'A mock resource for testing',
                        'mimeType' => 'text/plain',
                    ],
                ],
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate resources/read response.
     */
    private function simulateResourceReadResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'contents' => [
                    [
                        'uri' => $message['params']['uri'] ?? 'mock://resource',
                        'mimeType' => 'text/plain',
                        'text' => 'Mock resource content',
                    ],
                ],
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate prompts/list response.
     */
    private function simulatePromptsListResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'prompts' => [
                    [
                        'name' => 'mock_prompt',
                        'description' => 'A mock prompt for testing',
                        'argumentsSchema' => [
                            'type' => 'object',
                            'properties' => [],
                        ],
                    ],
                ],
            ],
            'id' => $message['id'],
        ];
    }

    /**
     * Simulate prompts/get response.
     */
    private function simulatePromptGetResponse(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            'type' => 'text',
                            'text' => 'Mock prompt message',
                        ],
                    ],
                ],
            ],
            'id' => $message['id'],
        ];
    }
}
