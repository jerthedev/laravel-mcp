<?php

namespace JTD\LaravelMCP\Tests\Unit\Server;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Server\CapabilityManager;
use JTD\LaravelMCP\Server\Contracts\ServerInterface;
use JTD\LaravelMCP\Server\McpServer;
use JTD\LaravelMCP\Server\ServerInfo;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use JTD\LaravelMCP\Tests\TestCase;

class McpServerTest extends TestCase
{
    private McpServer $server;

    private ServerInfo $mockServerInfo;

    private CapabilityManager $mockCapabilityManager;

    private MessageProcessor $mockMessageProcessor;

    private TransportManager $mockTransportManager;

    private McpRegistry $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockServerInfo = Mockery::mock(ServerInfo::class);
        $this->mockCapabilityManager = Mockery::mock(CapabilityManager::class);
        $this->mockMessageProcessor = Mockery::mock(MessageProcessor::class);
        $this->mockTransportManager = Mockery::mock(TransportManager::class);
        $this->mockRegistry = Mockery::mock(McpRegistry::class);

        $this->server = new McpServer(
            $this->mockServerInfo,
            $this->mockCapabilityManager,
            $this->mockMessageProcessor,
            $this->mockTransportManager,
            $this->mockRegistry
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Set up common mock expectations that many tests need.
     */
    private function setupCommonMockExpectations(): void
    {
        $this->mockServerInfo->shouldReceive('getUptime')->andReturn(100)->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('getStatus')->andReturn([])->zeroOrMoreTimes();
        $this->mockCapabilityManager->shouldReceive('getNegotiatedCapabilities')->andReturn([])->zeroOrMoreTimes();
        $this->mockCapabilityManager->shouldReceive('getDetailedCapabilityInfo')->andReturn([])->zeroOrMoreTimes();
        $this->mockTransportManager->shouldReceive('getActiveTransportCount')->andReturn(0)->zeroOrMoreTimes();
        $this->mockRegistry->shouldReceive('getTools')->andReturn([])->zeroOrMoreTimes();
        $this->mockRegistry->shouldReceive('getResources')->andReturn([])->zeroOrMoreTimes();
        $this->mockRegistry->shouldReceive('getPrompts')->andReturn([])->zeroOrMoreTimes();
    }

    public function test_implements_server_interface(): void
    {
        $this->assertInstanceOf(ServerInterface::class, $this->server);
    }

    public function test_can_create_server_instance(): void
    {
        $this->assertInstanceOf(McpServer::class, $this->server);
    }

    public function test_server_starts_uninitialized_and_not_running(): void
    {
        $this->assertFalse($this->server->isInitialized());
        $this->assertFalse($this->server->isRunning());
    }

    public function test_can_initialize_server(): void
    {
        $this->setupCommonMockExpectations();

        $clientInfo = [
            'clientInfo' => ['name' => 'Test Client', 'version' => '1.0'],
            'capabilities' => ['tools' => ['listChanged' => true]],
        ];

        $this->mockCapabilityManager
            ->shouldReceive('negotiateWithClient')
            ->with($clientInfo['capabilities'])
            ->andReturn(['tools' => ['listChanged' => true]]);

        $this->mockServerInfo
            ->shouldReceive('updateRuntimeInfo')
            ->once();

        $this->mockServerInfo
            ->shouldReceive('getProtocolVersion')
            ->andReturn('2024-11-05');

        $this->mockServerInfo
            ->shouldReceive('getBasicInfo')
            ->andReturn(['name' => 'Test Server', 'version' => '1.0']);

        $this->mockRegistry
            ->shouldReceive('initialize')
            ->once();

        $this->mockMessageProcessor
            ->shouldReceive('setServerInfo')
            ->once();

        $response = $this->server->initialize($clientInfo);

        $this->assertTrue($this->server->isInitialized());
        $this->assertIsArray($response);
        $this->assertArrayHasKey('protocolVersion', $response);
        $this->assertArrayHasKey('capabilities', $response);
        $this->assertArrayHasKey('serverInfo', $response);
    }

    public function test_cannot_initialize_server_twice(): void
    {
        $this->setupCommonMockExpectations();

        // First initialization
        $this->mockCapabilityManager
            ->shouldReceive('negotiateWithClient')
            ->andReturn(['tools' => []])
            ->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('updateRuntimeInfo')->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('getProtocolVersion')->andReturn('2024-11-05')->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('getBasicInfo')->andReturn([])->zeroOrMoreTimes();
        $this->mockRegistry->shouldReceive('initialize')->zeroOrMoreTimes();
        $this->mockMessageProcessor->shouldReceive('setServerInfo')->zeroOrMoreTimes();

        $this->server->initialize([]);

        // Second initialization should just return response without re-initializing
        $response = $this->server->initialize([]);

        $this->assertIsArray($response);
    }

    public function test_can_start_server_after_initialization(): void
    {
        // Initialize first
        $this->initializeServer();

        $this->mockTransportManager
            ->shouldReceive('startAllTransports')
            ->once();

        $this->mockServerInfo
            ->shouldReceive('resetStartTime')
            ->once();

        $this->server->start();

        $this->assertTrue($this->server->isRunning());
    }

    public function test_cannot_start_uninitialized_server(): void
    {
        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Server must be initialized before starting');

        $this->server->start();
    }

    public function test_start_is_idempotent(): void
    {
        $this->initializeServer();

        $this->mockTransportManager
            ->shouldReceive('startAllTransports')
            ->once();
        $this->mockServerInfo->shouldReceive('resetStartTime')->once();

        $this->server->start();
        $this->server->start(); // Second call should not do anything

        $this->assertTrue($this->server->isRunning());
    }

    public function test_can_stop_running_server(): void
    {
        $this->initializeAndStartServer();

        $this->mockTransportManager
            ->shouldReceive('stopAllTransports')
            ->once();

        $this->server->stop();

        $this->assertFalse($this->server->isRunning());
    }

    public function test_stop_is_idempotent(): void
    {
        $this->initializeAndStartServer();

        $this->mockTransportManager
            ->shouldReceive('stopAllTransports')
            ->once();

        $this->server->stop();
        $this->server->stop(); // Second call should not do anything

        $this->assertFalse($this->server->isRunning());
    }

    public function test_can_restart_server(): void
    {
        $this->initializeAndStartServer();

        $this->mockTransportManager
            ->shouldReceive('stopAllTransports')
            ->once();

        $this->server->restart();

        $this->assertTrue($this->server->isRunning());
    }

    public function test_can_get_server_status(): void
    {
        $this->setupCommonMockExpectations();

        $status = $this->server->getStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('initialized', $status);
        $this->assertArrayHasKey('running', $status);
        $this->assertArrayHasKey('server_info', $status);
        $this->assertArrayHasKey('capabilities', $status);
        $this->assertArrayHasKey('transports', $status);
        $this->assertArrayHasKey('components', $status);
        $this->assertArrayHasKey('performance', $status);
    }

    public function test_can_get_server_health(): void
    {
        $this->mockTransportManager
            ->shouldReceive('getActiveTransportCount')
            ->andReturn(1);

        $this->mockRegistry
            ->shouldReceive('getTools')
            ->andReturn(['tool1']);
        $this->mockRegistry
            ->shouldReceive('getResources')
            ->andReturn([]);
        $this->mockRegistry
            ->shouldReceive('getPrompts')
            ->andReturn([]);

        $this->mockServerInfo
            ->shouldReceive('getUptime')
            ->andReturn(100);

        $health = $this->server->getHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('healthy', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('timestamp', $health);
        $this->assertArrayHasKey('uptime', $health);

        $this->assertArrayHasKey('server_initialized', $health['checks']);
        $this->assertArrayHasKey('server_running', $health['checks']);
        $this->assertArrayHasKey('transports_healthy', $health['checks']);
        $this->assertArrayHasKey('components_registered', $health['checks']);
        $this->assertArrayHasKey('memory_usage', $health['checks']);
    }

    public function test_can_get_server_info(): void
    {
        $expectedInfo = [
            'name' => 'Test Server',
            'version' => '1.0',
            'description' => 'Test Description',
        ];

        $this->mockServerInfo
            ->shouldReceive('getServerInfo')
            ->andReturn($expectedInfo);

        $info = $this->server->getServerInfo();

        $this->assertEquals($expectedInfo, $info);
    }

    public function test_can_get_capabilities(): void
    {
        $expectedCapabilities = ['tools' => ['listChanged' => true]];

        $this->mockCapabilityManager
            ->shouldReceive('getNegotiatedCapabilities')
            ->andReturn($expectedCapabilities);

        $capabilities = $this->server->getCapabilities();

        $this->assertEquals($expectedCapabilities, $capabilities);
    }

    public function test_can_set_and_get_configuration(): void
    {
        $config = ['test' => 'value'];

        $this->server->setConfiguration($config);
        $serverConfig = $this->server->getConfiguration();

        $this->assertArrayHasKey('test', $serverConfig);
        $this->assertEquals('value', $serverConfig['test']);
    }

    public function test_can_register_transport(): void
    {
        $transport = Mockery::mock(\JTD\LaravelMCP\Transport\Contracts\TransportInterface::class);

        $this->mockTransportManager
            ->shouldReceive('registerTransport')
            ->with('test', $transport)
            ->once();

        $this->server->registerTransport('test', $transport);

        $transports = $this->server->getTransports();
        $this->assertArrayHasKey('test', $transports);
    }

    public function test_can_remove_transport(): void
    {
        $transport = Mockery::mock(\JTD\LaravelMCP\Transport\Contracts\TransportInterface::class);

        $this->mockTransportManager
            ->shouldReceive('registerTransport')
            ->with('test', $transport);
        $this->mockTransportManager
            ->shouldReceive('removeTransport')
            ->with('test')
            ->once();

        $this->server->registerTransport('test', $transport);
        $this->server->removeTransport('test');

        $transports = $this->server->getTransports();
        $this->assertArrayNotHasKey('test', $transports);
    }

    public function test_can_get_uptime(): void
    {
        $expectedUptime = 123;

        $this->mockServerInfo
            ->shouldReceive('getUptime')
            ->andReturn($expectedUptime);

        $uptime = $this->server->getUptime();

        $this->assertEquals($expectedUptime, $uptime);
    }

    public function test_can_get_metrics(): void
    {
        $this->mockServerInfo
            ->shouldReceive('getUptime')
            ->andReturn(100);

        $this->mockRegistry
            ->shouldReceive('getTools')
            ->andReturn(['tool1', 'tool2']);
        $this->mockRegistry
            ->shouldReceive('getResources')
            ->andReturn(['resource1']);
        $this->mockRegistry
            ->shouldReceive('getPrompts')
            ->andReturn([]);

        $metrics = $this->server->getMetrics();

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertArrayHasKey('peak_memory', $metrics);
        $this->assertArrayHasKey('uptime', $metrics);
        $this->assertArrayHasKey('component_counts', $metrics);

        $this->assertEquals(2, $metrics['component_counts']['tools']);
        $this->assertEquals(1, $metrics['component_counts']['resources']);
        $this->assertEquals(0, $metrics['component_counts']['prompts']);
    }

    public function test_can_shutdown_gracefully(): void
    {
        $this->initializeAndStartServer();

        $this->mockTransportManager
            ->shouldReceive('stopAllTransports')
            ->once();

        $this->server->shutdown();

        $this->assertFalse($this->server->isRunning());
    }

    public function test_can_increment_request_count(): void
    {
        $this->setupCommonMockExpectations();

        $this->server->incrementRequestCount();
        $metrics = $this->server->getMetrics();

        $this->assertEquals(1, $metrics['requests_processed']);
    }

    public function test_can_increment_error_count(): void
    {
        $this->setupCommonMockExpectations();

        $this->server->incrementErrorCount();
        $metrics = $this->server->getMetrics();

        $this->assertEquals(1, $metrics['errors_count']);
    }

    public function test_can_get_diagnostics(): void
    {
        $this->mockServerInfo->shouldReceive('getStatus')->andReturn([]);
        $this->mockServerInfo->shouldReceive('getUptime')->andReturn(100);
        $this->mockCapabilityManager->shouldReceive('getNegotiatedCapabilities')->andReturn([]);
        $this->mockCapabilityManager->shouldReceive('getDetailedCapabilityInfo')->andReturn([]);
        $this->mockTransportManager->shouldReceive('getActiveTransportCount')->andReturn(0);
        $this->mockRegistry->shouldReceive('getTools')->andReturn([]);
        $this->mockRegistry->shouldReceive('getResources')->andReturn([]);
        $this->mockRegistry->shouldReceive('getPrompts')->andReturn([]);

        $diagnostics = $this->server->getDiagnostics();

        $this->assertIsArray($diagnostics);
        $this->assertArrayHasKey('server', $diagnostics);
        $this->assertArrayHasKey('health', $diagnostics);
        $this->assertArrayHasKey('metrics', $diagnostics);
        $this->assertArrayHasKey('capabilities', $diagnostics);
        $this->assertArrayHasKey('configuration', $diagnostics);
    }

    public function test_handles_initialization_errors(): void
    {
        $this->mockCapabilityManager
            ->shouldReceive('negotiateWithClient')
            ->andThrow(new \Exception('Negotiation failed'));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Server initialization failed');

        $this->server->initialize([]);
    }

    public function test_handles_start_errors(): void
    {
        $this->initializeServer();

        $this->mockTransportManager
            ->shouldReceive('startAllTransports')
            ->andThrow(new \Exception('Transport start failed'));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Server start failed');

        $this->server->start();
    }

    public function test_handles_stop_errors(): void
    {
        $this->initializeAndStartServer();

        $this->mockTransportManager
            ->shouldReceive('stopAllTransports')
            ->andThrow(new \Exception('Transport stop failed'));

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('Server stop failed');

        $this->server->stop();
    }

    private function initializeServer(): void
    {
        $this->setupCommonMockExpectations();

        $this->mockCapabilityManager
            ->shouldReceive('negotiateWithClient')
            ->andReturn([])
            ->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('updateRuntimeInfo')->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('getProtocolVersion')->andReturn('2024-11-05')->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('getBasicInfo')->andReturn([])->zeroOrMoreTimes();
        $this->mockRegistry->shouldReceive('initialize')->zeroOrMoreTimes();
        $this->mockMessageProcessor->shouldReceive('setServerInfo')->zeroOrMoreTimes();

        $this->server->initialize([]);
    }

    private function initializeAndStartServer(): void
    {
        $this->initializeServer();

        $this->mockTransportManager->shouldReceive('startAllTransports')->zeroOrMoreTimes();
        $this->mockServerInfo->shouldReceive('resetStartTime')->zeroOrMoreTimes();

        $this->server->start();
    }
}
