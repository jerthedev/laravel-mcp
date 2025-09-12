<?php

namespace JTD\LaravelMCP\Tests\Mocks;

/**
 * Mock MCP Client Factory
 *
 * Factory for creating pre-configured mock MCP clients.
 */
class MockMcpClientFactory
{
    /**
     * Create a Claude Desktop client.
     */
    public static function createClaudeDesktopClient(): MockMcpClient
    {
        return new MockMcpClient([
            'name' => 'Claude Desktop',
            'version' => '1.0.0',
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => false],
                'prompts' => ['listChanged' => true],
                'logging' => ['setLevel' => true],
            ],
        ]);
    }

    /**
     * Create a ChatGPT Desktop client.
     */
    public static function createChatGPTDesktopClient(): MockMcpClient
    {
        return new MockMcpClient([
            'name' => 'ChatGPT Desktop',
            'version' => '1.0.0',
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['subscribe' => true],
                'prompts' => ['listChanged' => false],
            ],
        ]);
    }

    /**
     * Create a VS Code client.
     */
    public static function createVSCodeClient(): MockMcpClient
    {
        return new MockMcpClient([
            'name' => 'VS Code MCP Extension',
            'version' => '0.1.0',
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => true],
                'prompts' => ['listChanged' => true],
                'experimental' => ['notifications' => true],
            ],
        ]);
    }

    /**
     * Create a minimal client with no capabilities.
     */
    public static function createMinimalClient(): MockMcpClient
    {
        return new MockMcpClient([
            'name' => 'Minimal Client',
            'version' => '0.0.1',
            'capabilities' => [],
        ]);
    }

    /**
     * Create a custom client with specific configuration.
     */
    public static function createCustomClient(array $config = []): MockMcpClient
    {
        return new MockMcpClient(array_merge([
            'name' => 'Custom Test Client',
            'version' => '0.1.0',
            'capabilities' => [],
        ], $config));
    }

    /**
     * Create a client that simulates errors.
     */
    public static function createErrorClient(): MockMcpClient
    {
        return new class(['name' => 'Error Client', 'version' => '1.0.0', 'capabilities' => []]) extends MockMcpClient
        {
            public function sendMessage(array $message): array
            {
                // Always return an error
                return [
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32603,
                        'message' => 'Internal error',
                        'data' => 'Simulated error for testing',
                    ],
                    'id' => $message['id'] ?? null,
                ];
            }
        };
    }

    /**
     * Create a client that simulates slow responses.
     *
     * @param  int  $delayMs  Delay in milliseconds
     */
    public static function createSlowClient(int $delayMs = 1000): MockMcpClient
    {
        return new class(['name' => 'Slow Client', 'version' => '1.0.0', 'capabilities' => []], $delayMs) extends MockMcpClient
        {
            private int $delay;

            public function __construct(array $config, int $delay)
            {
                parent::__construct($config);
                $this->delay = $delay;
            }

            public function sendMessage(array $message): array
            {
                // Simulate network delay
                usleep($this->delay * 1000);

                return parent::sendMessage($message);
            }
        };
    }

    /**
     * Create a client that tracks performance metrics.
     */
    public static function createMetricsClient(): MockMcpClient
    {
        return new class(['name' => 'Metrics Client', 'version' => '1.0.0', 'capabilities' => []]) extends MockMcpClient
        {
            private array $metrics = [];

            public function sendMessage(array $message): array
            {
                $start = microtime(true);
                $response = parent::sendMessage($message);
                $duration = microtime(true) - $start;

                $this->metrics[] = [
                    'method' => $message['method'] ?? 'unknown',
                    'duration' => $duration,
                    'timestamp' => time(),
                    'success' => ! isset($response['error']),
                ];

                return $response;
            }

            public function getMetrics(): array
            {
                return $this->metrics;
            }

            public function getAverageResponseTime(): float
            {
                if (empty($this->metrics)) {
                    return 0;
                }

                $total = array_sum(array_column($this->metrics, 'duration'));

                return $total / count($this->metrics);
            }
        };
    }
}
