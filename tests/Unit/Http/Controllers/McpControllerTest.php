<?php

namespace JTD\LaravelMCP\Tests\Unit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Http\Controllers\McpController;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[CoversClass(McpController::class)]
class McpControllerTest extends TestCase
{
    private McpController $controller;

    private TransportManager $mockTransportManager;

    private HttpTransport $mockHttpTransport;

    private \JTD\LaravelMCP\Protocol\MessageProcessor $mockMessageProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockTransportManager = Mockery::mock(TransportManager::class);
        $this->mockHttpTransport = Mockery::mock(HttpTransport::class);
        $this->mockMessageProcessor = Mockery::mock(\JTD\LaravelMCP\Protocol\MessageProcessor::class);

        $this->controller = new McpController($this->mockTransportManager, $this->mockMessageProcessor);

        // Setup routes for testing
        Route::post('/mcp', [McpController::class, 'handle'])->name('mcp.handle');
        Route::get('/mcp/events', [McpController::class, 'events'])->name('mcp.events');
        Route::get('/mcp/health', [McpController::class, 'health'])->name('mcp.health');
        Route::get('/mcp/info', [McpController::class, 'info'])->name('mcp.info');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_handles_mcp_request_successfully(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        $expectedResponse = new Response('{"jsonrpc":"2.0","result":"success","id":1}', 200);

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->with('http', Mockery::type('array'))
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->once()
            ->andReturn(false);

        $this->mockHttpTransport->shouldReceive('start')
            ->once();

        $this->mockHttpTransport->shouldReceive('handleHttpRequest')
            ->once()
            ->with($request)
            ->andReturn($expectedResponse);

        $response = $this->controller->handle($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('{"jsonrpc":"2.0","result":"success","id":1}', $response->getContent());
    }

    #[Test]
    public function it_handles_transport_exception(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        $exception = new TransportException('Transport error', -32000, 'http', ['foo' => 'bar']);

        Log::shouldReceive('error')
            ->once()
            ->with('MCP Controller transport error', Mockery::type('array'));

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow($exception);

        $response = $this->controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32000, $content['error']['code']);
        $this->assertEquals('Transport error', $content['error']['message']);
    }

    #[Test]
    public function it_handles_unexpected_exception(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        Log::shouldReceive('error')
            ->once()
            ->with('MCP Controller unexpected error', Mockery::type('array'));

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow(new \RuntimeException('Unexpected error'));

        $response = $this->controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32603, $content['error']['code']);
        $this->assertEquals('Internal server error', $content['error']['message']);
    }

    #[Test]
    public function it_handles_options_request(): void
    {
        $request = Request::create('/mcp', 'OPTIONS');

        $expectedResponse = new Response('', 204);

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->once()
            ->andReturn(true);

        $this->mockHttpTransport->shouldReceive('handleOptionsRequest')
            ->once()
            ->andReturn($expectedResponse);

        $response = $this->controller->options($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
    }

    #[Test]
    public function it_handles_options_request_with_error(): void
    {
        $request = Request::create('/mcp', 'OPTIONS');

        Log::shouldReceive('error')
            ->once()
            ->with('MCP Controller OPTIONS error', Mockery::type('array'));

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow(new \RuntimeException('Options error'));

        $response = $this->controller->options($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function it_provides_health_check(): void
    {
        $request = Request::create('/mcp/health', 'GET');

        $healthInfo = [
            'healthy' => true,
            'checks' => [
                'server_started' => true,
                'port_accessible' => true,
            ],
            'errors' => [],
        ];

        $stats = [
            'messages_sent' => 10,
            'messages_received' => 15,
        ];

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->twice()
            ->andReturn(true);

        $this->mockHttpTransport->shouldReceive('performHealthCheck')
            ->once()
            ->andReturn($healthInfo);

        $this->mockHttpTransport->shouldReceive('getStatistics')
            ->once()
            ->andReturn($stats);

        $response = $this->controller->health($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('healthy', $content['status']);
        $this->assertArrayHasKey('timestamp', $content);
        $this->assertArrayHasKey('checks', $content);
        $this->assertArrayHasKey('transport', $content);
        $this->assertEquals('http', $content['transport']['type']);
        $this->assertTrue($content['transport']['connected']);
    }

    #[Test]
    public function it_returns_unhealthy_status(): void
    {
        $request = Request::create('/mcp/health', 'GET');

        $healthInfo = [
            'healthy' => false,
            'checks' => [
                'server_started' => false,
            ],
            'errors' => ['Server not started'],
        ];

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->twice()
            ->andReturn(false);

        $this->mockHttpTransport->shouldReceive('start')
            ->once();

        $this->mockHttpTransport->shouldReceive('performHealthCheck')
            ->once()
            ->andReturn($healthInfo);

        $this->mockHttpTransport->shouldReceive('getStatistics')
            ->once()
            ->andReturn([]);

        $response = $this->controller->health($request);

        $this->assertEquals(503, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('unhealthy', $content['status']);
        $this->assertArrayHasKey('errors', $content);
        $this->assertNotEmpty($content['errors']);
    }

    #[Test]
    public function it_handles_health_check_exception(): void
    {
        $request = Request::create('/mcp/health', 'GET');

        Log::shouldReceive('error')
            ->once()
            ->with('Health check failed', Mockery::type('array'));

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow(new \RuntimeException('Health check error'));

        $response = $this->controller->health($request);

        $this->assertEquals(503, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('error', $content['status']);
        $this->assertEquals('Health check error', $content['error']);
    }

    #[Test]
    public function it_provides_server_info(): void
    {
        $request = Request::create('/mcp/info', 'GET');

        config([
            'laravel-mcp.server.name' => 'Test MCP Server',
            'laravel-mcp.server.version' => '1.0.0',
            'laravel-mcp.server.description' => 'Test Description',
            'laravel-mcp.server.vendor' => 'TestVendor',
            'laravel-mcp.capabilities.tools' => ['tool1', 'tool2'],
            'laravel-mcp.capabilities.resources' => ['resource1'],
            'laravel-mcp.capabilities.prompts' => ['prompt1'],
            'laravel-mcp.capabilities.logging' => ['debug', 'info'],
        ]);

        $connectionInfo = [
            'transport_type' => 'http',
            'connected' => true,
        ];

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->once()
            ->andReturn(true);

        $this->mockHttpTransport->shouldReceive('getConnectionInfo')
            ->once()
            ->andReturn($connectionInfo);

        $response = $this->controller->info($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);

        $this->assertEquals('Test MCP Server', $content['server']['name']);
        $this->assertEquals('1.0.0', $content['server']['version']);
        $this->assertEquals('1.0', $content['protocol']['version']);
        $this->assertEquals('http', $content['protocol']['transport']);
        $this->assertArrayHasKey('capabilities', $content);
        $this->assertArrayHasKey('endpoints', $content);
        $this->assertArrayHasKey('transport', $content);
        $this->assertArrayHasKey('timestamp', $content);
    }

    #[Test]
    public function it_handles_info_with_transport_unavailable(): void
    {
        $request = Request::create('/mcp/info', 'GET');

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow(new \RuntimeException('Transport unavailable'));

        $response = $this->controller->info($request);

        $this->assertEquals(200, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('transport', $content);
        $this->assertEquals('unavailable', $content['transport']['status']);
    }

    #[Test]
    public function it_handles_info_exception(): void
    {
        $request = Request::create('/mcp/info', 'GET');

        // Create a controller that will fail when getting transport
        $mockTransportManager = Mockery::mock(TransportManager::class);
        $mockTransportManager->shouldReceive('createTransport')
            ->zeroOrMoreTimes()  // Transport creation is optional in info endpoint
            ->andThrow(new \RuntimeException('Transport creation failed'));

        $controller = new McpController($mockTransportManager, $this->mockMessageProcessor);
        
        // Clear named routes to cause route() function to fail
        $originalRoutes = app('router')->getRoutes();
        $newRouteCollection = new \Illuminate\Routing\RouteCollection();
        app('router')->setRoutes($newRouteCollection);

        Log::shouldReceive('error')
            ->once()
            ->with('Server info failed', Mockery::type('array'));

        $response = $controller->info($request);

        // Restore routes for other tests
        app('router')->setRoutes($originalRoutes);

        $this->assertEquals(500, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Failed to retrieve server information', $content['error']);
    }

    #[Test]
    public function it_provides_server_sent_events(): void
    {
        $request = Request::create('/mcp/events', 'GET');

        $response = $this->controller->events($request);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        // We can't easily test the streaming content, but we can verify it's a StreamedResponse
        $this->assertTrue($response->isOk());
    }

    #[Test]
    public function it_reuses_existing_http_transport(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        $expectedResponse = new Response('{"jsonrpc":"2.0","result":"success","id":1}', 200);

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once() // Only once for both calls
            ->andReturn($this->mockHttpTransport);

        $this->mockHttpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with(Mockery::type(\JTD\LaravelMCP\Server\McpServer::class));

        $this->mockHttpTransport->shouldReceive('isConnected')
            ->once()
            ->andReturn(true); // Already connected

        $this->mockHttpTransport->shouldReceive('handleHttpRequest')
            ->twice()
            ->andReturn($expectedResponse);

        // First request
        $response1 = $this->controller->handle($request);
        // Second request should reuse transport
        $response2 = $this->controller->handle($request);

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
    }

    #[Test]
    public function it_throws_exception_for_invalid_transport_type(): void
    {
        $request = Request::create('/mcp', 'POST');

        // Create a mock that implements TransportInterface but is not HttpTransport
        $invalidTransport = Mockery::mock(\JTD\LaravelMCP\Transport\Contracts\TransportInterface::class);

        Log::shouldReceive('error')
            ->once()
            ->with('MCP Controller transport error', Mockery::type('array'));

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andReturn($invalidTransport);

        $response = $this->controller->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32603, $content['error']['code']);
        $this->assertEquals('Invalid transport type. Expected HttpTransport.', $content['error']['message']);
    }

    #[Test]
    public function it_adds_cors_headers_to_error_response(): void
    {
        $request = Request::create('/mcp', 'POST');

        config([
            'laravel-mcp.cors.allowed_origins' => ['http://example.com'],
            'laravel-mcp.cors.allowed_methods' => ['GET', 'POST'],
            'laravel-mcp.cors.allowed_headers' => ['X-Custom'],
            'laravel-mcp.cors.max_age' => 3600,
        ]);

        $this->mockTransportManager->shouldReceive('createTransport')
            ->once()
            ->andThrow(new TransportException('Test error'));

        Log::shouldReceive('error')->once();

        $response = $this->controller->handle($request);

        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertEquals('http://example.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('X-Custom', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Max-Age'));
    }
}
