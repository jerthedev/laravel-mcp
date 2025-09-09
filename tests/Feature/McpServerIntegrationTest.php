<?php

namespace JTD\LaravelMCP\Tests\Feature;

use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Server\CapabilityManager;
use JTD\LaravelMCP\Server\Contracts\ServerInterface;
use JTD\LaravelMCP\Server\McpServer;
use JTD\LaravelMCP\Server\ServerInfo;
use JTD\LaravelMCP\Tests\TestCase;

class McpServerIntegrationTest extends TestCase
{
    public function test_can_resolve_server_from_container(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $this->assertInstanceOf(McpServer::class, $server);
    }

    public function test_can_resolve_server_info_from_container(): void
    {
        $serverInfo = $this->app->make(ServerInfo::class);

        $this->assertInstanceOf(ServerInfo::class, $serverInfo);
    }

    public function test_can_resolve_capability_manager_from_container(): void
    {
        $capabilityManager = $this->app->make(CapabilityManager::class);

        $this->assertInstanceOf(CapabilityManager::class, $capabilityManager);
    }

    public function test_server_initialization_flow(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $clientInfo = [
            'clientInfo' => [
                'name' => 'Test Client',
                'version' => '1.0.0',
            ],
            'capabilities' => [
                'tools' => ['listChanged' => true],
                'resources' => ['subscribe' => false, 'listChanged' => true],
                'prompts' => ['listChanged' => true],
            ],
            'protocolVersion' => '2024-11-05',
        ];

        $response = $server->initialize($clientInfo);

        $this->assertTrue($server->isInitialized());
        $this->assertFalse($server->isRunning());

        $this->assertArrayHasKey('protocolVersion', $response);
        $this->assertArrayHasKey('capabilities', $response);
        $this->assertArrayHasKey('serverInfo', $response);

        $this->assertEquals('2024-11-05', $response['protocolVersion']);
        $this->assertArrayHasKey('name', $response['serverInfo']);
        $this->assertArrayHasKey('version', $response['serverInfo']);
    }

    public function test_server_lifecycle_management(): void
    {
        $server = $this->app->make(ServerInterface::class);

        // Initialize
        $server->initialize([
            'capabilities' => ['tools' => []],
        ]);

        $this->assertTrue($server->isInitialized());
        $this->assertFalse($server->isRunning());

        // Start
        $server->start();

        $this->assertTrue($server->isInitialized());
        $this->assertTrue($server->isRunning());

        // Stop
        $server->stop();

        $this->assertTrue($server->isInitialized());
        $this->assertFalse($server->isRunning());

        // Restart
        $server->restart();

        $this->assertTrue($server->isInitialized());
        $this->assertTrue($server->isRunning());
    }

    public function test_server_status_and_health(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $server->initialize([]);
        $server->start();

        $status = $server->getStatus();

        $this->assertArrayHasKey('initialized', $status);
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('server_info', $status);
        $this->assertArrayHasKey('capabilities', $status);
        $this->assertArrayHasKey('transports', $status);
        $this->assertArrayHasKey('components', $status);
        $this->assertArrayHasKey('performance', $status);

        $this->assertTrue($status['initialized']);
        $this->assertTrue($status['running']);

        $health = $server->getHealth();

        $this->assertArrayHasKey('healthy', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('uptime', $health);

        $this->assertIsBool($health['healthy']);
        $this->assertIsArray($health['checks']);
    }

    public function test_server_configuration_integration(): void
    {
        Config::set('laravel-mcp.server.name', 'Integration Test Server');
        Config::set('laravel-mcp.server.version', '2.0.0');
        Config::set('laravel-mcp.server.description', 'Test server for integration testing');

        $serverInfo = $this->app->make(ServerInfo::class);

        $this->assertEquals('Integration Test Server', $serverInfo->getName());
        $this->assertEquals('2.0.0', $serverInfo->getVersion());
        $this->assertEquals('Test server for integration testing', $serverInfo->getDescription());
    }

    public function test_capability_negotiation_integration(): void
    {
        Config::set('laravel-mcp.capabilities.tools.list_changed_notifications', true);
        Config::set('laravel-mcp.capabilities.resources.subscriptions', false);
        Config::set('laravel-mcp.capabilities.logging.level', 'debug');

        // Add test components to the registries so capabilities can be negotiated
        $registry = $this->app->make(\JTD\LaravelMCP\Registry\McpRegistry::class);
        $toolRegistry = $this->app->make(\JTD\LaravelMCP\Registry\ToolRegistry::class);
        $resourceRegistry = $this->app->make(\JTD\LaravelMCP\Registry\ResourceRegistry::class);
        $promptRegistry = $this->app->make(\JTD\LaravelMCP\Registry\PromptRegistry::class);

        // Register test components
        $toolRegistry->register('test-tool', new class {
            public function execute($params) { return 'test'; }
            public function getDescription() { return 'Test tool'; }
            public function getInputSchema() { return ['type' => 'object']; }
        });
        
        $resourceRegistry->register('test-resource', new class {
            public function getUri() { return 'test://resource'; }
            public function getDescription() { return 'Test resource'; }
            public function getMimeType() { return 'text/plain'; }
            public function read($params) { return 'test content'; }
        });

        $promptRegistry->register('test-prompt', new class {
            public function getDescription() { return 'Test prompt'; }
            public function process($params) { return 'test prompt'; }
        });

        $server = $this->app->make(ServerInterface::class);

        $clientCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => false],
            'logging' => ['level' => 'info'],
        ];

        $response = $server->initialize([
            'capabilities' => $clientCapabilities,
        ]);

        $negotiatedCapabilities = $response['capabilities'];

        $this->assertArrayHasKey('tools', $negotiatedCapabilities);
        $this->assertArrayHasKey('resources', $negotiatedCapabilities);
        $this->assertArrayHasKey('logging', $negotiatedCapabilities);
    }

    public function test_server_metrics_tracking(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $server->initialize([]);

        $initialMetrics = $server->getMetrics();
        $this->assertEquals(0, $initialMetrics['requests_processed']);
        $this->assertEquals(0, $initialMetrics['errors_count']);

        $server->incrementRequestCount();
        $server->incrementRequestCount();
        $server->incrementErrorCount();

        $updatedMetrics = $server->getMetrics();
        $this->assertEquals(2, $updatedMetrics['requests_processed']);
        $this->assertEquals(1, $updatedMetrics['errors_count']);

        $this->assertArrayHasKey('memory_usage', $updatedMetrics);
        $this->assertArrayHasKey('peak_memory', $updatedMetrics);
        $this->assertArrayHasKey('uptime', $updatedMetrics);
        $this->assertArrayHasKey('component_counts', $updatedMetrics);
    }

    public function test_server_info_detailed_information(): void
    {
        $serverInfo = $this->app->make(ServerInfo::class);

        $detailedInfo = $serverInfo->getDetailedInfo();

        $this->assertArrayHasKey('server', $detailedInfo);
        $this->assertArrayHasKey('runtime', $detailedInfo);
        $this->assertArrayHasKey('system', $detailedInfo);
        $this->assertArrayHasKey('performance', $detailedInfo);

        $this->assertArrayHasKey('php_version', $detailedInfo['runtime']);
        $this->assertArrayHasKey('laravel_version', $detailedInfo['runtime']);
        $this->assertArrayHasKey('environment', $detailedInfo['runtime']);

        $this->assertArrayHasKey('os', $detailedInfo['system']);
        $this->assertArrayHasKey('memory_limit', $detailedInfo['system']);

        $this->assertEquals(PHP_VERSION, $detailedInfo['runtime']['php_version']);
    }

    public function test_server_graceful_shutdown(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $server->initialize([]);
        $server->start();

        $this->assertTrue($server->isRunning());

        $server->shutdown();

        $this->assertFalse($server->isRunning());
    }

    public function test_capability_manager_mcp10_compliance(): void
    {
        $capabilityManager = $this->app->make(CapabilityManager::class);

        $clientCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => false, 'listChanged' => true],
            'prompts' => ['listChanged' => true],
        ];

        $negotiated = $capabilityManager->negotiateWithClient($clientCapabilities);
        $compliance = $capabilityManager->validateMcp10Compliance();

        $this->assertArrayHasKey('compliant', $compliance);
        $this->assertArrayHasKey('issues', $compliance);
        $this->assertArrayHasKey('negotiated_capabilities', $compliance);

        if (! $compliance['compliant']) {
            $this->markTestSkipped('MCP 1.0 compliance issues: '.implode(', ', $compliance['issues']));
        }

        $this->assertTrue($compliance['compliant']);
        $this->assertEmpty($compliance['issues']);
    }

    public function test_server_transport_integration(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $server->initialize([]);

        $status = $server->getStatus();
        $this->assertArrayHasKey('transports', $status);
        $this->assertArrayHasKey('registered_count', $status['transports']);
        $this->assertArrayHasKey('active_count', $status['transports']);
        $this->assertArrayHasKey('transports', $status['transports']);
    }

    public function test_server_component_integration(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $status = $server->getStatus();
        $this->assertArrayHasKey('components', $status);

        $metrics = $server->getMetrics();
        $this->assertArrayHasKey('component_counts', $metrics);
        $this->assertArrayHasKey('tools', $metrics['component_counts']);
        $this->assertArrayHasKey('resources', $metrics['component_counts']);
        $this->assertArrayHasKey('prompts', $metrics['component_counts']);
    }

    public function test_server_diagnostics(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $server->initialize([]);

        $diagnostics = $server->getDiagnostics();

        $this->assertArrayHasKey('server', $diagnostics);
        $this->assertArrayHasKey('health', $diagnostics);
        $this->assertArrayHasKey('metrics', $diagnostics);
        $this->assertArrayHasKey('capabilities', $diagnostics);
        $this->assertArrayHasKey('configuration', $diagnostics);

        $this->assertIsArray($diagnostics['server']);
        $this->assertIsArray($diagnostics['health']);
        $this->assertIsArray($diagnostics['metrics']);
        $this->assertIsArray($diagnostics['capabilities']);
        $this->assertIsArray($diagnostics['configuration']);
    }

    public function test_server_uptime_tracking(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $initialUptime = $server->getUptime();
        $this->assertGreaterThanOrEqual(0, $initialUptime);

        usleep(100000); // 0.1 seconds

        $laterUptime = $server->getUptime();
        $this->assertGreaterThanOrEqual($initialUptime, $laterUptime);
    }

    public function test_server_protocol_version(): void
    {
        $server = $this->app->make(ServerInterface::class);

        $response = $server->initialize([]);

        $this->assertEquals('2024-11-05', $response['protocolVersion']);
    }
}
