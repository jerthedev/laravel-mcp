<?php

namespace JTD\LaravelMCP\Tests;

use Illuminate\Foundation\Application;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\McpManager;
use JTD\LaravelMCP\Tests\Support\TestPackageManifest;
use JTD\LaravelMCP\Tests\Support\TestProviderRepository;
use JTD\LaravelMCP\Tests\Utilities\McpTestHelpers;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    use McpTestHelpers;

    /**
     * Get the base path of the application.
     * Override to use a writable directory.
     *
     * @return string
     */
    protected function getApplicationBasePath()
    {
        // Use the path set by bootstrap.php
        return $_ENV['TESTBENCH_WORKING_PATH'] ?? '/tmp/orchestra-testbench';
    }

    /**
     * Resolve the application implementation.
     * Override to use our custom ProviderRepository.
     */
    protected function resolveApplication()
    {
        // Get base path with writable cache directory
        $basePath = $this->getApplicationBasePath();

        // Create application with our base path
        $app = new Application($basePath);

        // Set up bindings before bootstrapping
        $app->bind(
            \Illuminate\Foundation\ProviderRepository::class,
            function ($app) {
                return new TestProviderRepository(
                    $app,
                    new \Illuminate\Filesystem\Filesystem,
                    $app->bootstrapPath('cache/services.php')
                );
            }
        );

        // Override PackageManifest with our cache-friendly version
        $app->bind(
            \Illuminate\Foundation\PackageManifest::class,
            function ($app) {
                return new TestPackageManifest(
                    new \Illuminate\Filesystem\Filesystem,
                    $app->basePath(),
                    $app->bootstrapPath('cache/packages.php')
                );
            }
        );

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMcpEnvironment();
        $this->setupTestComponents();
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelMcpServiceProvider::class,
        ];
    }

    /**
     * Get package aliases.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Mcp' => Mcp::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        // Set MCP_ENABLED environment variable - critical for commands
        putenv('MCP_ENABLED=true');
        $_ENV['MCP_ENABLED'] = 'true';

        // Set encryption key for Laravel (must be exactly 32 characters for AES-256-CBC)
        $app['config']->set('app.key', 'base64:'.base64_encode('12345678901234567890123456789012'));
        $app['config']->set('app.cipher', 'AES-256-CBC');

        // Set test-specific configuration
        $app['config']->set('laravel-mcp.enabled', true);
        $app['config']->set('laravel-mcp.debug', true);
        $app['config']->set('laravel-mcp.discovery.enabled', false); // Disable discovery in tests
        $app['config']->set('laravel-mcp.discovery.paths', []);
        $app['config']->set('laravel-mcp.validation.validate_handlers', false); // Disable handler validation in tests

        // Enable debug mode for commands
        $app['config']->set('app.debug', true);

        // Set app key for encryption (required for sessions/cookies)
        $app['config']->set('app.key', 'base64:'.base64_encode('32charactersoftestingencryptkey'));

        // Set storage path to writable directory
        $storagePath = '/tmp/orchestra-testbench/storage';
        if (! is_dir($storagePath)) {
            @mkdir($storagePath, 0777, true);
            @mkdir($storagePath.'/framework', 0777, true);
            @mkdir($storagePath.'/framework/cache', 0777, true);
            @mkdir($storagePath.'/framework/sessions', 0777, true);
            @mkdir($storagePath.'/framework/views', 0777, true);
            @mkdir($storagePath.'/framework/testing', 0777, true);
            @mkdir($storagePath.'/logs', 0777, true);
        }
        $app->useStoragePath($storagePath);

        // Set up test database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set test-specific transport configuration
        $app['config']->set('mcp-transports.http.enabled', true);
        $app['config']->set('mcp-transports.http.host', '127.0.0.1');
        $app['config']->set('mcp-transports.http.port', 0); // Random port for testing
        $app['config']->set('mcp-transports.stdio.enabled', false);
    }

    /**
     * Set up MCP-specific test environment.
     */
    protected function setUpMcpEnvironment(): void
    {
        // Create test directories if needed
        $this->ensureTestDirectoriesExist();

        // Clear any existing registrations
        $this->clearMcpRegistrations();

        // Register test components
        $this->registerTestComponents();

        // Set up test data if needed
        $this->setUpTestData();
    }

    /**
     * Set up test components.
     */
    protected function setupTestComponents(): void
    {
        // Register test components if needed
    }

    /**
     * Register test components for testing.
     */
    protected function registerTestComponents(): void
    {
        // Override in test classes to register specific components
    }

    /**
     * Set up test data for testing.
     */
    protected function setUpTestData(): void
    {
        // Override in test classes to set up specific test data
    }

    /**
     * Ensure test directories exist.
     */
    protected function ensureTestDirectoriesExist(): void
    {
        $directories = [
            app_path('Mcp'),
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                @mkdir($directory, 0777, true);
            }
        }
    }

    /**
     * Clear MCP component registrations.
     */
    protected function clearMcpRegistrations(): void
    {
        // Only clear registrations if we're using the real implementation
        // Skip if the manager is mocked to avoid triggering mock expectations
        try {
            $manager = app('laravel-mcp');
            if ($manager instanceof McpManager) {
                // It's the real manager, safe to reset
                if (class_exists(Mcp::class)) {
                    try {
                        Mcp::reset();
                    } catch (\Exception $e) {
                        // Ignore errors
                    }
                }
            }
        } catch (\Exception $e) {
            // Manager not bound, skip
        }

        // Also clear the registry directly if available
        try {
            $registry = app('mcp.registry');
            if ($registry && method_exists($registry, 'clear')) {
                $registry->clear();
            }
        } catch (\Exception $e) {
            // Ignore errors during setup
        }
    }

    /**
     * Create a test MCP tool instance.
     *
     * @param  string  $name  Tool name
     * @param  array  $config  Tool configuration
     * @return \JTD\LaravelMCP\Abstracts\McpTool
     */
    protected function createTestTool(string $name, array $config = [])
    {
        return new class($name, $config) extends \JTD\LaravelMCP\Abstracts\McpTool
        {
            protected string $name;

            protected string $description = 'Test tool';

            protected array $parameterSchema = [
                'input' => [
                    'type' => 'string',
                    'description' => 'Test input',
                ],
            ];

            public function __construct(string $name, array $config = [])
            {
                $this->name = $name;
                if (isset($config['description'])) {
                    $this->description = $config['description'];
                }
                if (isset($config['parameterSchema'])) {
                    $this->parameterSchema = $config['parameterSchema'];
                }

                parent::__construct();
            }

            protected function handle(array $parameters): mixed
            {
                return [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Test result: '.($parameters['input'] ?? 'no input'),
                        ],
                    ],
                ];
            }
        };
    }

    /**
     * Create a test MCP resource instance.
     *
     * @param  string  $name  Resource name
     * @param  array  $config  Resource configuration
     * @return \JTD\LaravelMCP\Abstracts\McpResource
     */
    protected function createTestResource(string $name, array $config = [])
    {
        return new class($name, $config) extends \JTD\LaravelMCP\Abstracts\McpResource
        {
            protected string $uri;

            protected string $name;

            protected string $description = 'Test resource';

            protected string $mimeType = 'text/plain';

            public function __construct(string $name, array $config = [])
            {
                $this->name = $name;
                $this->uri = $config['uri'] ?? "test://{$name}";
                if (isset($config['description'])) {
                    $this->description = $config['description'];
                }
                if (isset($config['mimeType'])) {
                    $this->mimeType = $config['mimeType'];
                }

                parent::__construct();
            }

            public function getUri(): string
            {
                return $this->uri;
            }

            public function getMimeType(): string
            {
                return $this->mimeType;
            }

            public function getDescription(): string
            {
                return $this->description;
            }

            public function read(array $options = []): array
            {
                return [
                    'contents' => [
                        [
                            'uri' => $this->uri,
                            'mimeType' => $this->mimeType,
                            'text' => 'Test resource content',
                        ],
                    ],
                ];
            }
        };
    }

    /**
     * Create a test MCP prompt instance.
     *
     * @param  string  $name  Prompt name
     * @param  array  $config  Prompt configuration
     * @return \JTD\LaravelMCP\Abstracts\McpPrompt
     */
    protected function createTestPrompt(string $name, array $config = [])
    {
        return new class($name, $config) extends \JTD\LaravelMCP\Abstracts\McpPrompt
        {
            protected string $name;

            protected string $description = 'Test prompt';

            protected array $argumentsSchema = [
                'type' => 'object',
                'properties' => [
                    'topic' => [
                        'type' => 'string',
                        'description' => 'Test topic',
                    ],
                ],
            ];

            public function __construct(string $name, array $config = [])
            {
                $this->name = $name;
                if (isset($config['description'])) {
                    $this->description = $config['description'];
                }
                if (isset($config['argumentsSchema'])) {
                    $this->argumentsSchema = $config['argumentsSchema'];
                }
            }

            public function getMessages(array $arguments): array
            {
                return [
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                'type' => 'text',
                                'text' => 'Test prompt for: '.($arguments['topic'] ?? 'no topic'),
                            ],
                        ],
                    ],
                ];
            }
        };
    }

    /**
     * Assert that an MCP component is registered.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  string  $name  Component name
     */
    protected function assertComponentRegistered(string $type, string $name): void
    {
        $method = 'has'.ucfirst(rtrim($type, 's'));
        $this->assertTrue(Mcp::{$method}($name), "Component '{$name}' of type '{$type}' should be registered");
    }

    /**
     * Assert that an MCP component is not registered.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  string  $name  Component name
     */
    protected function assertComponentNotRegistered(string $type, string $name): void
    {
        $method = 'has'.ucfirst(rtrim($type, 's'));
        $this->assertFalse(Mcp::{$method}($name), "Component '{$name}' of type '{$type}' should not be registered");
    }

    /**
     * Assert MCP response structure.
     *
     * @param  array  $response  Response to validate
     * @param  array  $expectedStructure  Expected structure
     */
    protected function assertMcpResponse(array $response, array $expectedStructure = []): void
    {
        // Basic MCP response structure
        if (isset($response['error'])) {
            $this->assertArrayHasKey('code', $response['error']);
            $this->assertArrayHasKey('message', $response['error']);
        } else {
            $this->assertArrayHasKey('result', $response);
        }

        // Validate expected structure
        foreach ($expectedStructure as $key => $value) {
            if (is_array($value)) {
                $this->assertArrayHasKey($key, $response);
                $this->assertMcpResponse($response[$key], $value);
            } else {
                $this->assertArrayHasKey($key, $response);
            }
        }
    }

    /**
     * Assert valid JSON-RPC response.
     *
     * @param  array  $response  Response to validate
     * @param  mixed  $expectedId  Expected request ID
     */
    protected function assertValidJsonRpcResponse(array $response, $expectedId = null): void
    {
        $this->assertArrayHasKey('jsonrpc', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);

        if ($expectedId !== null) {
            $this->assertArrayHasKey('id', $response);
            $this->assertEquals($expectedId, $response['id']);
        }

        $this->assertTrue(
            array_key_exists('result', $response) || array_key_exists('error', $response),
            'Response must contain either result or error'
        );
    }

    /**
     * Assert JSON-RPC error response.
     *
     * @param  array  $response  Response to validate
     * @param  int|null  $expectedCode  Expected error code
     */
    protected function assertJsonRpcError(array $response, ?int $expectedCode = null): void
    {
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);

        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['error']['code']);
        }
    }

    /**
     * Create a mock JSON-RPC request.
     *
     * @param  string  $method  Method name
     * @param  array  $params  Method parameters
     * @param  mixed  $id  Request ID
     */
    protected function createJsonRpcRequest(string $method, array $params = [], $id = 1): array
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
    protected function createJsonRpcNotification(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ];
    }

    /**
     * Get test fixture path.
     *
     * @param  string  $fixture  Fixture file name
     */
    protected function getFixturePath(string $fixture): string
    {
        return __DIR__.'/Fixtures/'.$fixture;
    }

    /**
     * Load test fixture data.
     *
     * @param  string  $fixture  Fixture file name
     * @return mixed
     */
    protected function loadFixture(string $fixture)
    {
        $path = $this->getFixturePath($fixture);

        if (! file_exists($path)) {
            throw new \InvalidArgumentException("Fixture file '{$fixture}' not found");
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'json':
                return json_decode(file_get_contents($path), true);
            case 'php':
                return require $path;
            default:
                return file_get_contents($path);
        }
    }

    /**
     * Create a test stdio transport for simulation.
     */
    protected function createStdioTransportMock()
    {
        return $this->createMock(\JTD\LaravelMCP\Transport\StdioTransport::class);
    }

    /**
     * Simulate stdio input/output for testing.
     */
    protected function simulateStdioMessage(string $input): string
    {
        // Simulate stdio message framing
        return json_encode(json_decode($input, true));
    }

    /**
     * Process JSON-RPC request through the handler.
     *
     * @param  array  $request  Request data
     * @return array Response data
     */
    protected function processJsonRpcRequest(array $request): array
    {
        $handler = $this->app->make(\JTD\LaravelMCP\Protocol\JsonRpcHandler::class);
        $response = $handler->processRequest(json_encode($request));

        return json_decode($response, true);
    }

    /**
     * Mock a transport for testing.
     *
     * @param  string  $type  Transport type
     */
    protected function mockTransport(string $type = 'stdio'): \Mockery\MockInterface
    {
        $mock = \Mockery::mock(\JTD\LaravelMCP\Transport\Contracts\TransportInterface::class);

        $this->app->bind("mcp.transport.{$type}", function () use ($mock) {
            return $mock;
        });

        return $mock;
    }

    /**
     * Cleanup after test.
     */
    protected function tearDown(): void
    {
        // Clear MCP registrations first before any facade cleanup
        try {
            $this->clearMcpRegistrations();
        } catch (\Exception $e) {
            // Ignore errors during cleanup
        }

        // Clear any facade mocks to prevent test pollution
        \Illuminate\Support\Facades\Log::clearResolvedInstances();
        \Illuminate\Support\Facades\Cache::clearResolvedInstances();
        \Illuminate\Support\Facades\Queue::clearResolvedInstances();

        parent::tearDown();
    }
}
