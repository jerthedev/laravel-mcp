<?php

namespace JTD\LaravelMCP\Tests;

use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Tests\Support\TestPackageManifest;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Get the base path for the application.
     * Override to use a writable temp directory.
     *
     * @return string
     */
    protected function getBasePath()
    {
        $basePath = '/tmp/laravel-mcp-test-app';
        
        // Create directory structure
        $dirs = [
            $basePath,
            $basePath . '/bootstrap',
            $basePath . '/bootstrap/cache',
            $basePath . '/storage',
            $basePath . '/storage/framework',
            $basePath . '/storage/framework/cache',
            $basePath . '/storage/framework/sessions',
            $basePath . '/storage/framework/views',
            $basePath . '/storage/logs',
            $basePath . '/app',
            $basePath . '/config',
            $basePath . '/database',
            $basePath . '/public',
            $basePath . '/resources',
            $basePath . '/routes',
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        
        // Create necessary cache files
        $cacheDir = $basePath . '/bootstrap/cache';
        if (!file_exists($cacheDir . '/packages.php')) {
            file_put_contents($cacheDir . '/packages.php', '<?php return [];');
        }
        if (!file_exists($cacheDir . '/services.php')) {
            file_put_contents($cacheDir . '/services.php', '<?php return [];');
        }
        
        return $basePath;
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
    }

    /**
     * Set up test components.
     */
    protected function setupTestComponents(): void
    {
        // Register test components if needed
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
                mkdir($directory, 0755, true);
            }
        }
    }

    /**
     * Clear MCP component registrations.
     */
    protected function clearMcpRegistrations(): void
    {
        // Clear registrations using the facade
        if (class_exists(Mcp::class)) {
            try {
                Mcp::reset();
            } catch (\Exception $e) {
                // Ignore errors during setup
            }
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
     * Cleanup after test.
     */
    protected function tearDown(): void
    {
        $this->clearMcpRegistrations();
        parent::tearDown();
    }
}
