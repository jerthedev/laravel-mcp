<?php

namespace JTD\LaravelMCP\Tests;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use Orchestra\Testbench\TestCase;

/**
 * Base test case for MCP components.
 *
 * This class provides testing infrastructure for MCP Tools, Resources, and Prompts,
 * including mock creation helpers and assertion methods for validating MCP responses.
 */
abstract class McpComponentTestCase extends TestCase
{
    /**
     * Get package providers.
     */
    protected function getPackageProviders($app): array
    {
        return [LaravelMcpServiceProvider::class];
    }

    /**
     * Set up the test environment.
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('laravel-mcp.discovery.enabled', false);
        $app['config']->set('laravel-mcp.logging.enabled', false);
    }

    /**
     * Create a mock MCP tool for testing.
     */
    protected function createMockTool(string $name = 'test_tool', array $schema = []): McpTool
    {
        return new class($name, $schema) extends McpTool
        {
            private string $toolName;

            private array $toolSchema;

            public function __construct(string $name, array $schema = [])
            {
                $this->toolName = $name;
                $this->toolSchema = $schema;
                parent::__construct();
            }

            protected function handle(array $parameters): mixed
            {
                return ['result' => 'test', 'parameters' => $parameters];
            }

            public function getName(): string
            {
                return $this->toolName;
            }

            protected function getParameterSchema(): array
            {
                return $this->toolSchema;
            }

            protected function getComponentType(): string
            {
                return 'tool';
            }
        };
    }

    /**
     * Create a mock MCP resource for testing.
     */
    protected function createMockResource(string $name = 'test_resource', ?string $uriTemplate = null): McpResource
    {
        return new class($name, $uriTemplate) extends McpResource
        {
            private string $resourceName;

            private ?string $resourceUriTemplate;

            public function __construct(string $name, ?string $uriTemplate = null)
            {
                $this->resourceName = $name;
                $this->resourceUriTemplate = $uriTemplate;
                parent::__construct();
            }

            protected function customRead(array $params): mixed
            {
                return ['data' => 'test', 'params' => $params];
            }

            protected function customList(array $params): array
            {
                return [
                    'data' => [['id' => 1, 'name' => 'Test Item']],
                    'params' => $params,
                ];
            }

            public function getName(): string
            {
                return $this->resourceName;
            }

            public function getUriTemplate(): string
            {
                return $this->resourceUriTemplate ?? parent::getUriTemplate();
            }

            protected function getComponentType(): string
            {
                return 'resource';
            }
        };
    }

    /**
     * Create a mock MCP prompt for testing.
     */
    protected function createMockPrompt(string $name = 'test_prompt', array $arguments = []): McpPrompt
    {
        return new class($name, $arguments) extends McpPrompt
        {
            private string $promptName;

            private array $promptArguments;

            public function __construct(string $name, array $arguments = [])
            {
                $this->promptName = $name;
                $this->promptArguments = $arguments;
                parent::__construct();
            }

            protected function customContent(array $arguments): string
            {
                return 'Test prompt content with arguments: '.json_encode($arguments);
            }

            public function getName(): string
            {
                return $this->promptName;
            }

            public function getArguments(): array
            {
                return $this->promptArguments;
            }

            protected function getComponentType(): string
            {
                return 'prompt';
            }
        };
    }

    /**
     * Assert that a response is a valid MCP response.
     */
    protected function assertValidMcpResponse(array $response): void
    {
        $this->assertIsArray($response);

        if (isset($response['error'])) {
            $this->assertArrayHasKey('code', $response['error']);
            $this->assertArrayHasKey('message', $response['error']);
        } else {
            $this->assertTrue(true); // Valid non-error response
        }
    }

    /**
     * Assert that a response is a valid MCP tool response.
     */
    protected function assertValidToolResponse(array $response): void
    {
        $this->assertArrayHasKey('content', $response);
        $this->assertIsArray($response['content']);

        foreach ($response['content'] as $content) {
            $this->assertArrayHasKey('type', $content);
            $this->assertArrayHasKey('text', $content);
        }
    }

    /**
     * Assert that a response is a valid MCP resource response.
     */
    protected function assertValidResourceResponse(array $response): void
    {
        $this->assertArrayHasKey('contents', $response);
        $this->assertIsArray($response['contents']);

        foreach ($response['contents'] as $content) {
            $this->assertArrayHasKey('uri', $content);
            $this->assertArrayHasKey('mimeType', $content);
            $this->assertArrayHasKey('text', $content);
        }
    }

    /**
     * Assert that a response is a valid MCP prompt response.
     */
    protected function assertValidPromptResponse(array $response): void
    {
        $this->assertArrayHasKey('description', $response);
        $this->assertArrayHasKey('messages', $response);
        $this->assertIsArray($response['messages']);

        foreach ($response['messages'] as $message) {
            $this->assertArrayHasKey('role', $message);
            $this->assertArrayHasKey('content', $message);
        }
    }

    /**
     * Assert that a JSON-RPC response is valid.
     */
    protected function assertValidJsonRpcResponse(array $response): void
    {
        $this->assertArrayHasKey('jsonrpc', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);

        // Must have either result or error, but not both
        $hasResult = array_key_exists('result', $response);
        $hasError = array_key_exists('error', $response);

        $this->assertTrue($hasResult !== $hasError, 'Response must have either result or error, but not both');

        if ($hasError) {
            $this->assertArrayHasKey('code', $response['error']);
            $this->assertArrayHasKey('message', $response['error']);
        }
    }

    /**
     * Assert that an error response has the expected structure.
     */
    protected function assertValidErrorResponse(array $response, ?int $expectedCode = null): void
    {
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);

        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['error']['code']);
        }
    }

    /**
     * Create a test parameter schema for validation testing.
     */
    protected function createTestParameterSchema(): array
    {
        return [
            'name' => [
                'type' => 'string',
                'description' => 'Test name parameter',
                'required' => true,
            ],
            'count' => [
                'type' => 'integer',
                'description' => 'Test count parameter',
                'minimum' => 1,
                'maximum' => 100,
                'required' => false,
            ],
            'active' => [
                'type' => 'boolean',
                'description' => 'Test active flag',
                'required' => false,
            ],
        ];
    }

    /**
     * Create test arguments for prompt testing.
     */
    protected function createTestPromptArguments(): array
    {
        return [
            'template' => [
                'type' => 'string',
                'description' => 'Template type',
                'enum' => ['welcome', 'notification', 'reminder'],
                'required' => true,
            ],
            'recipient_name' => [
                'type' => 'string',
                'description' => 'Recipient name',
                'max_length' => 100,
                'required' => false,
            ],
        ];
    }

    /**
     * Helper to simulate middleware application.
     */
    protected function simulateMiddleware(array $params, string $middleware): array
    {
        // Simple middleware simulation - just adds a processed flag
        return array_merge($params, ['_middleware_processed' => $middleware]);
    }

    /**
     * Helper to create test validation errors.
     */
    protected function createValidationErrors(): array
    {
        return [
            'name' => ['The name field is required.'],
            'count' => ['The count must be at least 1.'],
        ];
    }
}
