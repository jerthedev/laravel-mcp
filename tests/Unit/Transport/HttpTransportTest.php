<?php

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\HttpTransport;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(HttpTransport::class)]
class HttpTransportTest extends TestCase
{
    private HttpTransport $transport;

    private MessageHandlerInterface $mockHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transport = new HttpTransport;
        $this->transport->initialize(); // Initialize with default config
        $this->mockHandler = Mockery::mock(MessageHandlerInterface::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_initializes_with_default_configuration(): void
    {
        $this->assertInstanceOf(HttpTransport::class, $this->transport);
        $this->assertFalse($this->transport->isConnected());
    }

    #[Test]
    public function it_starts_and_stops_correctly(): void
    {
        $this->transport->setMessageHandler($this->mockHandler);
        $this->mockHandler->shouldReceive('onConnect')->once();

        $this->transport->start();
        $this->assertTrue($this->transport->isConnected());

        $this->transport->stop();
        $this->assertFalse($this->transport->isConnected());
    }

    #[Test]
    public function it_handles_http_request_successfully(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['foo' => 'bar'],
            'id' => 1,
        ];

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => ['success' => true],
            'id' => 1,
        ];

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));

        // onConnect is called both in start() and in handleHttpRequest()
        $this->mockHandler->shouldReceive('onConnect')->twice();
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->with($requestData, $this->transport)
            ->andReturn($responseData);
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals($responseData, $content);
    }

    #[Test]
    public function it_handles_notification_without_response(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'notify',
            'params' => ['foo' => 'bar'],
        ];

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));

        $this->mockHandler->shouldReceive('onConnect')->twice(); // Called in start() and handleHttpRequest()
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->with($requestData, $this->transport)
            ->andReturn(null);
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
    }

    #[Test]
    public function it_returns_error_for_invalid_content_type(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'text/plain',
        ], 'invalid content');

        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->mockHandler->shouldReceive('onDisconnect')->atLeast()->once();
        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32700, $content['error']['code']);
        $this->assertStringContainsString('Invalid Content-Type', $content['error']['message']);
    }

    #[Test]
    public function it_returns_error_for_invalid_json(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $this->mockHandler->shouldReceive('onConnect')->twice(); // Called in start() and handleHttpRequest()
        $this->mockHandler->shouldReceive('handleError')->twice(); // Called for JSON parse error
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(500, $response->getStatusCode()); // Error is caught in outer try-catch
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32603, $content['error']['code']); // Internal error code
    }

    #[Test]
    public function it_returns_error_for_empty_request_body(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->mockHandler->shouldReceive('onConnect')->twice(); // Called in start() and handleHttpRequest()
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(400, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32700, $content['error']['code']);
    }

    #[Test]
    public function it_handles_options_request_for_cors(): void
    {
        $this->transport->start();

        $response = $this->transport->handleOptionsRequest();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertTrue($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Methods'));
        $this->assertTrue($response->headers->has('Access-Control-Allow-Headers'));
        $this->assertTrue($response->headers->has('Access-Control-Max-Age'));
    }

    #[Test]
    public function it_adds_cors_headers_when_enabled(): void
    {
        $config = [
            'cors' => [
                'enabled' => true,
                'allowed_origins' => ['http://localhost:3000'],
                'allowed_methods' => ['POST', 'OPTIONS'],
                'allowed_headers' => ['Content-Type', 'X-Custom-Header'],
            ],
        ];

        $this->transport->initialize($config);
        $this->transport->start();

        $response = $this->transport->handleOptionsRequest();

        $this->assertEquals('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('Content-Type, X-Custom-Header', $response->headers->get('Access-Control-Allow-Headers'));
    }

    #[Test]
    public function it_does_not_add_cors_headers_when_disabled(): void
    {
        $config = [
            'cors' => [
                'enabled' => false,
            ],
        ];

        $this->transport->initialize($config);
        $this->transport->start();

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '');

        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);

        $response = $this->transport->handleHttpRequest($request);

        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
    }

    #[Test]
    public function it_returns_error_when_transport_is_closed(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        // Create a new transport without starting it
        $transport = new HttpTransport;
        $transport->initialize(); // Initialize but don't start

        $response = $transport->handleHttpRequest($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32603, $content['error']['code']);
    }

    #[Test]
    public function it_returns_error_when_no_handler_configured(): void
    {
        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertStringContainsString('No message handler', $content['error']['message']);
    }

    #[Test]
    public function it_handles_handler_exceptions(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [],
            'id' => 1,
        ];

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));

        $exception = new \RuntimeException('Handler error');

        $this->mockHandler->shouldReceive('onConnect')->twice();
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->andThrow($exception);
        $this->mockHandler->shouldReceive('handleError')
            ->once()
            ->with(Mockery::type(\Throwable::class), $this->transport);
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertEquals(-32603, $content['error']['code']);
        $this->assertEquals(1, $content['id']);
    }

    #[Test]
    public function it_generates_correct_base_url(): void
    {
        $config = [
            'host' => 'example.com',
            'port' => 8080,
            'path' => '/api/mcp',
            'ssl' => ['enabled' => false],
        ];

        $this->transport->initialize($config);

        $this->assertEquals('http://example.com:8080/api/mcp', $this->transport->getBaseUrl());
    }

    #[Test]
    public function it_generates_correct_https_base_url(): void
    {
        $config = [
            'host' => 'example.com',
            'port' => 443,
            'path' => '/api/mcp',
            'ssl' => ['enabled' => true],
        ];

        $this->transport->initialize($config);

        $this->assertEquals('https://example.com/api/mcp', $this->transport->getBaseUrl());
    }

    #[Test]
    public function it_omits_default_ports_in_base_url(): void
    {
        $config = [
            'host' => 'example.com',
            'port' => 80,
            'path' => '/mcp',
            'ssl' => ['enabled' => false],
        ];

        $this->transport->initialize($config);

        $this->assertEquals('http://example.com/mcp', $this->transport->getBaseUrl());
    }

    #[Test]
    public function it_performs_health_checks(): void
    {
        $this->transport->setMessageHandler($this->mockHandler);
        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->transport->start();

        $health = $this->transport->healthCheck();

        $this->assertArrayHasKey('healthy', $health);
        $this->assertArrayHasKey('checks', $health);
        $this->assertArrayHasKey('errors', $health);
    }

    #[Test]
    public function it_performs_health_checks_with_ssl_enabled(): void
    {
        $config = [
            'ssl' => [
                'enabled' => true,
                'cert_path' => '/path/to/cert.pem',
                'key_path' => '/path/to/key.pem',
            ],
        ];

        $this->transport->initialize($config);
        $this->transport->setMessageHandler($this->mockHandler);
        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->transport->start();

        $health = $this->transport->healthCheck();

        $this->assertArrayHasKey('checks', $health);
        $this->assertTrue($health['checks']['server_started']); // In testing, this should be true
    }

    #[Test]
    public function it_returns_connection_info(): void
    {
        $this->transport->start();

        $info = $this->transport->getConnectionInfo();

        $this->assertArrayHasKey('transport_type', $info);
        $this->assertArrayHasKey('http_specific', $info);
        $this->assertEquals('http', $info['transport_type']);
        $this->assertTrue($info['http_specific']['server_started']);
        $this->assertFalse($info['http_specific']['has_current_request']);
    }

    #[Test]
    public function it_manages_current_request(): void
    {
        $request = Request::create('/test', 'POST');

        $this->assertNull($this->transport->getCurrentRequest());

        $this->transport->setCurrentRequest($request);
        $this->assertSame($request, $this->transport->getCurrentRequest());
    }

    #[Test]
    public function it_manages_current_response_data(): void
    {
        $this->assertNull($this->transport->getCurrentResponseData());

        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();
        $this->transport->send('test response');

        $this->assertEquals('test response', $this->transport->getCurrentResponseData());
    }

    #[Test]
    public function it_validates_json_content_in_receive(): void
    {
        // Create a new transport and start it
        $transport = new HttpTransport;
        $transport->initialize();
        $transport->start(); // Transport needs to be connected for receive to work

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $transport->setCurrentRequest($request);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Failed to receive HTTP message');

        $transport->receive();
    }

    #[Test]
    public function it_logs_debug_information_when_enabled(): void
    {
        Log::shouldReceive('debug')
            ->once()
            ->with('Transport initialized', Mockery::type('array'));
        Log::shouldReceive('info')
            ->once()
            ->with('HTTP MCP transport ready', Mockery::type('array'));
        Log::shouldReceive('info')
            ->once()
            ->with('Transport started', Mockery::type('array'));
        Log::shouldReceive('debug')
            ->once()
            ->with('Message handler set', Mockery::type('array'));

        $config = [
            'debug' => true,
            'host' => '127.0.0.1',
            'port' => 8000,
            'path' => '/mcp',
        ];

        $this->transport->initialize($config);
        $this->transport->setMessageHandler($this->mockHandler);
        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->transport->start();

        // Verify that the transport is connected after start
        $this->assertTrue($this->transport->isConnected());
    }

    #[Test]
    public function it_logs_errors_during_receive(): void
    {
        Log::shouldReceive('error')->atLeast()->once()->with(Mockery::type('string'), Mockery::type('array'));

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], 'invalid json');

        $this->transport->setCurrentRequest($request);
        $this->transport->setMessageHandler($this->mockHandler);
        $this->mockHandler->shouldReceive('onConnect')->once();
        $this->mockHandler->shouldReceive('handleError')->atLeast()->once();
        $this->transport->start();

        try {
            $this->transport->receive();
            $this->fail('Expected TransportException to be thrown');
        } catch (TransportException $e) {
            $this->assertStringContainsString('Invalid JSON', $e->getMessage());
        }
    }

    #[Test]
    public function it_logs_errors_during_request_handling(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('HTTP transport error', Mockery::type('array'));

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]));

        $this->mockHandler->shouldReceive('onConnect')->twice();
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->andThrow(new \RuntimeException('Test error'));
        $this->mockHandler->shouldReceive('handleError')->once();
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);
        $this->assertEquals(500, $response->getStatusCode());
    }

    #[Test]
    public function it_encodes_response_with_proper_json_flags(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        $responseData = [
            'jsonrpc' => '2.0',
            'result' => [
                'text' => 'Test with / slashes and unicode: 你好',
            ],
            'id' => 1,
        ];

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));

        $this->mockHandler->shouldReceive('onConnect')->twice(); // Called in start() and handleHttpRequest()
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->andReturn($responseData);
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $content = $response->getContent();
        $this->assertStringContainsString('/', $content);
        $this->assertStringContainsString('你好', $content);
        $this->assertStringNotContainsString('\\/', $content);
        $this->assertStringNotContainsString('\\u', $content);
    }

    #[Test]
    public function it_handles_json_encoding_errors(): void
    {
        $requestData = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => 1,
        ];

        // Create a response with invalid UTF-8
        $responseData = [
            'jsonrpc' => '2.0',
            'result' => ['invalid' => "\xB1\x31"], // Invalid UTF-8
            'id' => 1,
        ];

        $request = Request::create('/mcp', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode($requestData));

        $this->mockHandler->shouldReceive('onConnect')->twice();
        $this->mockHandler->shouldReceive('handle')
            ->once()
            ->andReturn($responseData);
        $this->mockHandler->shouldReceive('handleError')->once();
        $this->mockHandler->shouldReceive('onDisconnect')->once();

        $this->transport->setMessageHandler($this->mockHandler);
        $this->transport->start();

        $response = $this->transport->handleHttpRequest($request);

        $this->assertEquals(500, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $content);
    }
}
