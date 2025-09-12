<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * @group Laravel Integration
 * @group Middleware
 * @group ticket-021
 * @group laravelintegration-021
 *
 * @testdox MCP Logging Middleware
 *
 * Test Coverage:
 * - Request logging with parameters
 * - Response logging with execution time
 * - Sensitive data redaction
 * - Performance warning logging
 * - Error logging
 * - Configurable logging levels
 *
 * Related Specifications:
 * - docs/Specs/10-LaravelIntegration.md: Laravel Integration spec
 * - docs/Tickets/021-LaravelMiddleware.md: Middleware implementation ticket
 */
class McpLoggingMiddlewareTest extends TestCase
{
    private McpLoggingMiddleware $middleware;

    private Request $request;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new McpLoggingMiddleware;
        $this->request = new Request;
        $this->request->headers->set('Content-Type', 'application/json');
    }

    /**
     * @test
     */
    public function it_skips_logging_when_disabled(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(false);

        Log::shouldReceive('withContext')->never();
        Log::shouldReceive('channel')->never();

        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_logs_request_and_response(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.request_log_level', 'info')
            ->andReturn('info');

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.response_log_level', 'info')
            ->andReturn('info');

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.channel', 'stack')
            ->andReturn('stack');

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.log_headers', false)
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.log_response_headers', false)
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.log_response_body', false)
            ->andReturn(false);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.slow_request_threshold', 5.0)
            ->andReturn(5.0);

        $this->request->setJson([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'test-tool'],
            'id' => 1,
        ]);

        Log::shouldReceive('withContext')
            ->once()
            ->with(\Mockery::on(function ($context) {
                return isset($context['mcp_request_id']) && Str::isUuid($context['mcp_request_id']);
            }));

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')
            ->with('stack')
            ->andReturn($logger);

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Request', \Mockery::on(function ($data) {
                return isset($data['request_id']) &&
                       isset($data['timestamp']) &&
                       isset($data['mcp_method']) &&
                       $data['mcp_method'] === 'tools/call' &&
                       isset($data['params']) &&
                       isset($data['jsonrpc_id']) &&
                       $data['jsonrpc_id'] === 1;
            }));

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Response', \Mockery::on(function ($data) {
                return isset($data['request_id']) &&
                       isset($data['execution_time_ms']) &&
                       isset($data['status_code']) &&
                       $data['status_code'] === 200 &&
                       isset($data['success']) &&
                       $data['success'] === true;
            }));

        $next = function ($request) {
            return new Response('{"result": "success"}');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertNotNull($this->request->headers->get('X-Request-ID'));
    }

    /**
     * @test
     */
    public function it_redacts_sensitive_data_from_logs(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.sensitive_fields', [])
            ->andReturn(['credit_card']);

        Config::shouldReceive('get')->andReturn('info');

        $this->request->setJson([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'username' => 'john',
                'password' => 'secret123',
                'api_key' => 'key-123',
                'credit_card' => '1234-5678-9012-3456',
                'data' => [
                    'token' => 'bearer-token',
                    'safe_field' => 'visible',
                ],
            ],
            'id' => 1,
        ]);

        Log::shouldReceive('withContext')->once();

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')->andReturn($logger);

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Request', \Mockery::on(function ($data) {
                $params = $data['params'] ?? [];

                return $params['password'] === '[REDACTED]' &&
                       $params['api_key'] === '[REDACTED]' &&
                       $params['credit_card'] === '[REDACTED]' &&
                       $params['data']['token'] === '[REDACTED]' &&
                       $params['data']['safe_field'] === 'visible' &&
                       $params['username'] === 'john';
            }));

        $logger->shouldReceive('log')->once()->with('info', 'MCP Response', \Mockery::any());

        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_logs_performance_warning_for_slow_requests(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.slow_request_threshold', 5.0)
            ->andReturn(0.001); // Set very low threshold to trigger warning

        Config::shouldReceive('get')->andReturn('info');

        Log::shouldReceive('withContext')->once();

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')->andReturn($logger);

        $logger->shouldReceive('log')->twice(); // Request and response

        $logger->shouldReceive('warning')
            ->once()
            ->with('Slow MCP request detected', \Mockery::on(function ($data) {
                return isset($data['request_id']) &&
                       isset($data['execution_time_seconds']) &&
                       isset($data['threshold_seconds']) &&
                       $data['threshold_seconds'] === 0.001;
            }));

        $next = function ($request) {
            usleep(10000); // Sleep for 10ms to ensure it's "slow"

            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_logs_exceptions_as_errors(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')->andReturn('info');
        Config::shouldReceive('get')
            ->with('app.debug')
            ->andReturn(true);

        Log::shouldReceive('withContext')->once();

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')->andReturn($logger);

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Request', \Mockery::any());

        $logger->shouldReceive('log')
            ->once()
            ->with('error', 'MCP Response', \Mockery::on(function ($data) {
                return isset($data['error']) &&
                       $data['error']['message'] === 'Test exception' &&
                       isset($data['error']['trace']);
            }));

        $next = function ($request) {
            throw new \Exception('Test exception');
        };

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Test exception');

        $this->middleware->handle($this->request, $next);
    }

    /**
     * @test
     */
    public function it_truncates_large_values_in_logs(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')->andReturn('info');

        $largeString = str_repeat('x', 2000);

        $this->request->setJson([
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'large_field' => $largeString,
                'normal_field' => 'normal',
            ],
            'id' => 1,
        ]);

        Log::shouldReceive('withContext')->once();

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')->andReturn($logger);

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Request', \Mockery::on(function ($data) {
                $params = $data['params'] ?? [];

                return str_ends_with($params['large_field'], '...[TRUNCATED]') &&
                       strlen($params['large_field']) === 1016 && // 1000 chars + truncation message
                       $params['normal_field'] === 'normal';
            }));

        $logger->shouldReceive('log')->once()->with('info', 'MCP Response', \Mockery::any());

        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function it_adds_user_context_to_logs_when_available(): void
    {
        Config::shouldReceive('get')
            ->with('laravel-mcp.logging.enabled', true)
            ->andReturn(true);

        Config::shouldReceive('get')->andReturn('info');

        $this->request->attributes->set('mcp_user_id', 123);

        Log::shouldReceive('withContext')->once();

        $logger = \Mockery::mock();
        Log::shouldReceive('channel')->andReturn($logger);

        $logger->shouldReceive('log')
            ->once()
            ->with('info', 'MCP Request', \Mockery::on(function ($data) {
                return isset($data['user_id']) && $data['user_id'] === 123;
            }));

        $logger->shouldReceive('log')->once()->with('info', 'MCP Response', \Mockery::any());

        $next = function ($request) {
            return new Response('OK');
        };

        $response = $this->middleware->handle($this->request, $next);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
