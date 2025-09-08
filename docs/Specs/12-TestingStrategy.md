# Testing Strategy Specification

## Overview

The Testing Strategy specification defines comprehensive testing approaches for the Laravel MCP package, including unit tests, integration tests, feature tests, and end-to-end testing scenarios that ensure the package works reliably across different Laravel versions and MCP client implementations.

## Testing Architecture

### Testing Framework Stack
```
Testing Stack:
├── PHPUnit (Core testing framework)
├── Orchestra Testbench (Laravel package testing)
├── Mockery (Mocking framework)
├── Laravel Testing Utilities
├── Custom MCP Testing Tools
└── Client Integration Testing
```

### Test Directory Structure
```
tests/
├── TestCase.php                        # Base test case
├── CreatesApplication.php               # Application bootstrapping
├── Unit/                               # Unit tests (isolated components)
│   ├── Abstracts/                      # Base class tests
│   │   ├── McpToolTest.php
│   │   ├── McpResourceTest.php
│   │   └── McpPromptTest.php
│   ├── Commands/                       # Artisan command tests
│   │   ├── ServeCommandTest.php
│   │   ├── MakeToolCommandTest.php
│   │   └── RegisterCommandTest.php
│   ├── Protocol/                       # Protocol implementation tests
│   │   ├── JsonRpcHandlerTest.php
│   │   ├── MessageProcessorTest.php
│   │   └── CapabilityNegotiatorTest.php
│   ├── Registry/                       # Registry system tests
│   │   ├── McpRegistryTest.php
│   │   ├── ComponentDiscoveryTest.php
│   │   └── RouteRegistrarTest.php
│   ├── Transport/                      # Transport layer tests
│   │   ├── HttpTransportTest.php
│   │   ├── StdioTransportTest.php
│   │   └── TransportManagerTest.php
│   └── Support/                        # Support class tests
│       ├── ConfigGeneratorTest.php
│       └── DocumentationGeneratorTest.php
├── Integration/                        # Integration tests (component interactions)
│   ├── ServiceProviderTest.php
│   ├── LaravelIntegrationTest.php
│   ├── MiddlewareIntegrationTest.php
│   └── EventIntegrationTest.php
├── Feature/                            # Feature tests (end-to-end scenarios)
│   ├── ToolExecutionTest.php
│   ├── ResourceAccessTest.php
│   ├── PromptGenerationTest.php
│   ├── ClientRegistrationTest.php
│   └── HttpTransportTest.php
├── Browser/                            # Browser tests (Laravel Dusk)
│   └── DocumentationInterfaceTest.php
├── Fixtures/                           # Test fixtures and data
│   ├── Tools/                          # Sample tool implementations
│   │   ├── TestCalculatorTool.php
│   │   └── TestDatabaseTool.php
│   ├── Resources/                      # Sample resource implementations
│   │   └── TestUserResource.php
│   ├── Prompts/                        # Sample prompt implementations
│   │   └── TestEmailPrompt.php
│   ├── config/                         # Test configuration files
│   └── mcp/                           # MCP protocol test messages
└── Stubs/                             # Test application stubs
    └── app/                           # Minimal Laravel app structure
```

## Base Testing Classes

### Main Test Case
```php
<?php

namespace JTD\LaravelMCP\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Transport\TransportManager;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setupMcpEnvironment();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LaravelMcpServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test environment
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Configure MCP for testing
        $app['config']->set('laravel-mcp.discovery.enabled', false);
        $app['config']->set('laravel-mcp.transports.default', 'stdio');
        $app['config']->set('laravel-mcp.logging.enabled', false);
    }

    protected function setupMcpEnvironment(): void
    {
        // Register test components
        $this->registerTestComponents();
        
        // Set up test data if needed
        $this->setUpTestData();
    }

    protected function registerTestComponents(): void
    {
        $registry = $this->app->make(McpRegistry::class);
        
        // Register test tools
        $registry->register('tool', 'test_calculator', TestCalculatorTool::class);
        $registry->register('tool', 'test_database', TestDatabaseTool::class);
        
        // Register test resources
        $registry->register('resource', 'test_users', TestUserResource::class);
        
        // Register test prompts
        $registry->register('prompt', 'test_email', TestEmailPrompt::class);
    }

    protected function setUpTestData(): void
    {
        // Create test database tables if needed
        $this->artisan('migrate:fresh');
    }
}
```

### MCP Testing Traits
```php
<?php

namespace JTD\LaravelMCP\Tests\Concerns;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;

trait McpTestingHelpers
{
    protected function mockJsonRpcRequest(string $method, array $params = [], $id = 1): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ];
    }

    protected function processJsonRpcRequest(array $request): array
    {
        $handler = $this->app->make(JsonRpcHandler::class);
        $response = $handler->processRequest(json_encode($request));
        
        return json_decode($response, true);
    }

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

    protected function assertJsonRpcError(array $response, int $expectedCode = null): void
    {
        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('code', $response['error']);
        $this->assertArrayHasKey('message', $response['error']);
        
        if ($expectedCode !== null) {
            $this->assertEquals($expectedCode, $response['error']['code']);
        }
    }

    protected function registerMockTool(string $name, \Closure $handler): void
    {
        $mockTool = new class($name, $handler) extends \JTD\LaravelMCP\Abstracts\McpTool {
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

            protected function handle(array $parameters): mixed
            {
                return ($this->toolHandler)($parameters);
            }
        };

        $registry = $this->app->make(McpRegistry::class);
        $registry->register('tool', $name, $mockTool);
    }

    protected function expectsToolExecution(string $toolName, array $expectedParams = null): void
    {
        $this->registerMockTool($toolName, function ($params) use ($expectedParams) {
            if ($expectedParams !== null) {
                $this->assertEquals($expectedParams, $params);
            }
            return ['executed' => true, 'params' => $params];
        });
    }

    protected function mockTransport(string $type = 'stdio'): \Mockery\MockInterface
    {
        $mock = \Mockery::mock(\JTD\LaravelMCP\Transport\Contracts\TransportInterface::class);
        
        $this->app->bind("mcp.transport.{$type}", function () use ($mock) {
            return $mock;
        });
        
        return $mock;
    }
}

trait AssertsComponents
{
    protected function assertToolExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('tool', $name), "Tool '{$name}' does not exist");
    }

    protected function assertResourceExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('resource', $name), "Resource '{$name}' does not exist");
    }

    protected function assertPromptExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('prompt', $name), "Prompt '{$name}' does not exist");
    }

    protected function assertToolExecutes(string $name, array $params = []): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);
        
        $this->assertNotNull($tool, "Tool '{$name}' not found");
        
        // Should not throw exception
        $result = $tool->execute($params);
        $this->assertNotNull($result);
    }

    protected function assertToolReturns(string $name, array $params, $expectedResult): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);
        
        $this->assertNotNull($tool, "Tool '{$name}' not found");
        
        $result = $tool->execute($params);
        $this->assertEquals($expectedResult, $result);
    }
}
```

## Unit Testing Examples

### Testing Base Classes
```php
<?php

namespace JTD\LaravelMCP\Tests\Unit\Abstracts;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Tests\Fixtures\Tools\TestCalculatorTool;

class McpToolTest extends TestCase
{
    private TestCalculatorTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new TestCalculatorTool();
    }

    public function test_tool_has_name(): void
    {
        $this->assertEquals('calculator', $this->tool->getName());
    }

    public function test_tool_has_description(): void
    {
        $this->assertNotEmpty($this->tool->getDescription());
    }

    public function test_tool_has_input_schema(): void
    {
        $schema = $this->tool->getInputSchema();
        
        $this->assertArrayHasKey('type', $schema);
        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
    }

    public function test_tool_validates_parameters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        
        // Missing required parameters
        $this->tool->execute([]);
    }

    public function test_tool_executes_successfully(): void
    {
        $result = $this->tool->execute([
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);
        
        $this->assertEquals(8, $result);
    }

    public function test_tool_handles_division_by_zero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Division by zero');
        
        $this->tool->execute([
            'operation' => 'divide',
            'a' => 10,
            'b' => 0,
        ]);
    }

    public function test_tool_supports_dependency_injection(): void
    {
        // Test that Laravel services are properly injected
        $this->assertInstanceOf(
            \Illuminate\Container\Container::class,
            $this->tool->getContainer()
        );
    }
}
```

### Testing Protocol Implementation
```php
<?php

namespace JTD\LaravelMCP\Tests\Unit\Protocol;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Tests\Concerns\McpTestingHelpers;
use JTD\LaravelMCP\Protocol\JsonRpcHandler;

class JsonRpcHandlerTest extends TestCase
{
    use McpTestingHelpers;

    private JsonRpcHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = $this->app->make(JsonRpcHandler::class);
    }

    public function test_handles_initialize_request(): void
    {
        $request = $this->mockJsonRpcRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => [
                'name' => 'Test Client',
                'version' => '1.0.0',
            ],
        ]);

        $response = $this->processJsonRpcRequest($request);
        
        $this->assertValidJsonRpcResponse($response, 1);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('protocolVersion', $response['result']);
        $this->assertArrayHasKey('capabilities', $response['result']);
        $this->assertArrayHasKey('serverInfo', $response['result']);
    }

    public function test_handles_tools_list_request(): void
    {
        $request = $this->mockJsonRpcRequest('tools/list');
        $response = $this->processJsonRpcRequest($request);
        
        $this->assertValidJsonRpcResponse($response, 1);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertIsArray($response['result']['tools']);
    }

    public function test_handles_tool_call_request(): void
    {
        $this->expectsToolExecution('test_calculator', [
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);

        $request = $this->mockJsonRpcRequest('tools/call', [
            'name' => 'test_calculator',
            'arguments' => [
                'operation' => 'add',
                'a' => 5,
                'b' => 3,
            ],
        ]);

        $response = $this->processJsonRpcRequest($request);
        
        $this->assertValidJsonRpcResponse($response, 1);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
    }

    public function test_handles_invalid_json(): void
    {
        $invalidJson = '{"jsonrpc": "2.0", "method": "test"'; // Missing closing brace
        
        $response = json_decode($this->handler->processRequest($invalidJson), true);
        
        $this->assertJsonRpcError($response, -32700); // Parse error
    }

    public function test_handles_unknown_method(): void
    {
        $request = $this->mockJsonRpcRequest('unknown/method');
        $response = $this->processJsonRpcRequest($request);
        
        $this->assertJsonRpcError($response, -32601); // Method not found
    }

    public function test_handles_invalid_parameters(): void
    {
        $request = $this->mockJsonRpcRequest('tools/call', [
            'name' => 'nonexistent_tool',
        ]);

        $response = $this->processJsonRpcRequest($request);
        
        $this->assertJsonRpcError($response, -32602); // Invalid params
    }
}
```

## Integration Testing

### Service Provider Integration
```php
<?php

namespace JTD\LaravelMCP\Tests\Integration;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Transport\TransportManager;

class ServiceProviderTest extends TestCase
{
    public function test_service_provider_registers_services(): void
    {
        $this->assertInstanceOf(McpRegistry::class, $this->app->make(McpRegistry::class));
        $this->assertInstanceOf(TransportManager::class, $this->app->make(TransportManager::class));
    }

    public function test_service_provider_registers_commands(): void
    {
        $this->assertTrue($this->app->make('artisan')->has('mcp:serve'));
        $this->assertTrue($this->app->make('artisan')->has('make:mcp-tool'));
        $this->assertTrue($this->app->make('artisan')->has('mcp:register'));
    }

    public function test_service_provider_publishes_config(): void
    {
        $this->artisan('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-config',
        ])
        ->assertExitCode(0);

        $this->assertFileExists(config_path('laravel-mcp.php'));
    }

    public function test_auto_discovery_works(): void
    {
        // Enable auto-discovery
        config(['laravel-mcp.discovery.enabled' => true]);
        
        // Create test component files
        $this->createTestComponents();
        
        // Trigger discovery
        $discovery = $this->app->make(\JTD\LaravelMCP\Registry\ComponentDiscovery::class);
        $components = $discovery->discoverComponents();
        
        $this->assertNotEmpty($components);
        $this->assertArrayHasKey('test_auto_tool', array_column($components, 'name', null));
    }

    private function createTestComponents(): void
    {
        // This would create temporary test component files
        // Implementation depends on test setup
    }
}
```

## Feature Testing

### End-to-End Scenarios
```php
<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Tests\Concerns\McpTestingHelpers;
use JTD\LaravelMCP\Tests\Concerns\AssertsComponents;

class ToolExecutionTest extends TestCase
{
    use McpTestingHelpers, AssertsComponents;

    public function test_complete_tool_workflow(): void
    {
        // 1. Verify tool is registered
        $this->assertToolExists('test_calculator');
        
        // 2. Get tool list via JSON-RPC
        $listRequest = $this->mockJsonRpcRequest('tools/list');
        $listResponse = $this->processJsonRpcRequest($listRequest);
        
        $this->assertValidJsonRpcResponse($listResponse);
        
        $tools = $listResponse['result']['tools'];
        $calculatorTool = collect($tools)->firstWhere('name', 'test_calculator');
        
        $this->assertNotNull($calculatorTool);
        $this->assertArrayHasKey('description', $calculatorTool);
        $this->assertArrayHasKey('inputSchema', $calculatorTool);
        
        // 3. Execute tool via JSON-RPC
        $callRequest = $this->mockJsonRpcRequest('tools/call', [
            'name' => 'test_calculator',
            'arguments' => [
                'operation' => 'multiply',
                'a' => 6,
                'b' => 7,
            ],
        ]);
        
        $callResponse = $this->processJsonRpcRequest($callRequest);
        
        $this->assertValidJsonRpcResponse($callResponse);
        $this->assertArrayHasKey('content', $callResponse['result']);
        $this->assertFalse($callResponse['result']['isError']);
        
        // 4. Verify result
        $content = $callResponse['result']['content'][0]['text'];
        $this->assertEquals('42', $content);
    }

    public function test_tool_validation_workflow(): void
    {
        // Test parameter validation
        $request = $this->mockJsonRpcRequest('tools/call', [
            'name' => 'test_calculator',
            'arguments' => [
                'operation' => 'invalid_operation',
                'a' => 'not_a_number',
                'b' => 3,
            ],
        ]);

        $response = $this->processJsonRpcRequest($request);
        
        $this->assertValidJsonRpcResponse($response);
        $this->assertTrue($response['result']['isError']);
    }

    public function test_middleware_application(): void
    {
        // Register a tool with middleware
        $this->registerMockTool('auth_required_tool', function ($params) {
            return ['authenticated_result' => true];
        });

        // Configure tool to require authentication
        config(['laravel-mcp.middleware.global_middleware' => ['auth']]);
        
        // Test without authentication - should fail
        $request = $this->mockJsonRpcRequest('tools/call', [
            'name' => 'auth_required_tool',
            'arguments' => [],
        ]);

        $response = $this->processJsonRpcRequest($request);
        
        // Should return an error due to missing authentication
        $this->assertValidJsonRpcResponse($response);
        $this->assertTrue($response['result']['isError']);
    }
}
```

### HTTP Transport Testing
```php
<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;
use Illuminate\Http\Response;

class HttpTransportTest extends TestCase
{
    public function test_http_endpoint_handles_json_rpc(): void
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ];

        $response = $this->postJson('/mcp', $request);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'jsonrpc',
                     'result' => [
                         'tools'
                     ],
                     'id'
                 ]);
    }

    public function test_http_cors_headers(): void
    {
        $response = $this->options('/mcp');

        $response->assertStatus(200)
                 ->assertHeader('Access-Control-Allow-Origin')
                 ->assertHeader('Access-Control-Allow-Methods')
                 ->assertHeader('Access-Control-Allow-Headers');
    }

    public function test_http_health_endpoint(): void
    {
        $response = $this->get('/mcp/health');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'healthy',
                     'checks',
                     'timestamp'
                 ]);
    }

    public function test_http_server_info_endpoint(): void
    {
        $response = $this->get('/mcp/info');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'server',
                     'connections',
                     'components',
                     'performance'
                 ]);
    }
}
```

## Performance Testing

### Performance Test Suite
```php
<?php

namespace JTD\LaravelMCP\Tests\Performance;

use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Tests\Concerns\McpTestingHelpers;

class PerformanceTest extends TestCase
{
    use McpTestingHelpers;

    public function test_tool_execution_performance(): void
    {
        $iterations = 100;
        $maxExecutionTime = 0.1; // 100ms per execution
        
        $times = [];
        
        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);
            
            $this->processJsonRpcRequest($this->mockJsonRpcRequest('tools/call', [
                'name' => 'test_calculator',
                'arguments' => [
                    'operation' => 'add',
                    'a' => rand(1, 100),
                    'b' => rand(1, 100),
                ],
            ]));
            
            $times[] = microtime(true) - $start;
        }
        
        $averageTime = array_sum($times) / count($times);
        $maxTime = max($times);
        
        $this->assertLessThan($maxExecutionTime, $averageTime, 
            "Average execution time ({$averageTime}s) exceeds limit ({$maxExecutionTime}s)");
        
        $this->assertLessThan($maxExecutionTime * 3, $maxTime,
            "Maximum execution time ({$maxTime}s) is too high");
    }

    public function test_concurrent_requests_performance(): void
    {
        // Test multiple concurrent requests
        $requests = 10;
        $maxTotalTime = 2.0; // 2 seconds for all requests
        
        $start = microtime(true);
        
        $promises = [];
        for ($i = 0; $i < $requests; $i++) {
            // Simulate concurrent requests
            $promises[] = $this->processJsonRpcRequest($this->mockJsonRpcRequest('tools/list'));
        }
        
        $totalTime = microtime(true) - $start;
        
        $this->assertLessThan($maxTotalTime, $totalTime,
            "Concurrent requests took too long: {$totalTime}s");
    }

    public function test_memory_usage(): void
    {
        $initialMemory = memory_get_usage(true);
        
        // Execute multiple operations
        for ($i = 0; $i < 50; $i++) {
            $this->processJsonRpcRequest($this->mockJsonRpcRequest('tools/list'));
        }
        
        $finalMemory = memory_get_usage(true);
        $memoryIncrease = $finalMemory - $initialMemory;
        
        // Memory increase should be reasonable (less than 10MB)
        $this->assertLessThan(10 * 1024 * 1024, $memoryIncrease,
            "Memory usage increased too much: " . number_format($memoryIncrease / 1024 / 1024, 2) . "MB");
    }
}
```

## Testing Configuration

### PHPUnit Configuration
```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="./vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory suffix="Test.php">./tests/Integration</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
        <testsuite name="Performance">
            <directory suffix="Test.php">./tests/Performance</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">./src</directory>
        </include>
        <exclude>
            <directory suffix=".php">./src/resources</directory>
            <file>./src/LaravelMcpServiceProvider.php</file>
        </exclude>
        <report>
            <html outputDirectory="coverage-report"/>
            <text outputFile="php://stdout"/>
        </report>
    </coverage>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="BCRYPT_ROUNDS" value="4"/>
        <env name="CACHE_DRIVER" value="array"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MAIL_MAILER" value="array"/>
        <env name="QUEUE_CONNECTION" value="sync"/>
        <env name="SESSION_DRIVER" value="array"/>
        <env name="MCP_DISCOVERY_ENABLED" value="false"/>
        <env name="MCP_EVENTS_ENABLED" value="false"/>
    </php>
</phpunit>
```

### GitHub Actions CI/CD
```yaml
name: Tests

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  tests:
    runs-on: ubuntu-latest
    
    strategy:
      matrix:
        php: [8.2, 8.3]
        laravel: [11.0]
        include:
          - laravel: 11.0
            testbench: 9.0

    name: PHP ${{ matrix.php }} - Laravel ${{ matrix.laravel }}

    steps:
      - name: Checkout code
        uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: dom, curl, libxml, mbstring, zip, pcntl, pdo, sqlite, pdo_sqlite
          coverage: xdebug

      - name: Install dependencies
        run: |
          composer require "laravel/framework:${{ matrix.laravel }}.*" "orchestra/testbench:${{ matrix.testbench }}.*" --no-interaction --no-update
          composer install --prefer-dist --no-interaction --no-progress

      - name: Execute tests
        run: vendor/bin/phpunit --coverage-clover=coverage.xml

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          file: ./coverage.xml
          fail_ci_if_error: true
```

## Test Utilities and Helpers

### Mock Factories
```php
<?php

namespace JTD\LaravelMCP\Tests\Factories;

class MockMcpClientFactory
{
    public static function createClaudeDesktopClient(): MockMcpClient
    {
        return new MockMcpClient([
            'name' => 'Claude Desktop',
            'version' => '1.0.0',
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => false],
                'prompts' => ['listChanged' => true],
            ],
        ]);
    }

    public static function createCustomClient(array $config = []): MockMcpClient
    {
        return new MockMcpClient(array_merge([
            'name' => 'Test Client',
            'version' => '0.1.0',
            'capabilities' => [],
        ], $config));
    }
}

class MockMcpClient
{
    private array $config;
    private array $receivedMessages = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function sendMessage(array $message): array
    {
        $this->receivedMessages[] = $message;
        
        return match ($message['method'] ?? '') {
            'initialize' => $this->handleInitialize($message),
            'tools/list' => $this->handleToolsList($message),
            'tools/call' => $this->handleToolCall($message),
            default => ['jsonrpc' => '2.0', 'error' => ['code' => -32601, 'message' => 'Method not found'], 'id' => $message['id'] ?? null],
        };
    }

    public function getReceivedMessages(): array
    {
        return $this->receivedMessages;
    }

    private function handleInitialize(array $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => $this->config['capabilities'],
                'serverInfo' => [
                    'name' => $this->config['name'],
                    'version' => $this->config['version'],
                ],
            ],
            'id' => $message['id'],
        ];
    }
}
```

## Quality Assurance Metrics

### Coverage Requirements
- **Minimum Coverage**: 90% line coverage
- **Critical Components**: 95% coverage for core protocol and registry
- **New Features**: 100% coverage requirement
- **Integration Points**: Full coverage of Laravel integrations

### Performance Benchmarks
- **Tool Execution**: < 100ms average response time
- **Protocol Processing**: < 50ms for standard JSON-RPC messages  
- **Memory Usage**: < 10MB increase per 100 operations
- **Concurrent Requests**: Handle 50 concurrent requests within 2 seconds

### Code Quality Standards
- **PHPStan Level**: Level 8 (maximum strictness)
- **PHP CS Fixer**: Laravel coding standards
- **Complexity**: Cyclomatic complexity < 10 per method
- **Documentation**: 100% public API documentation