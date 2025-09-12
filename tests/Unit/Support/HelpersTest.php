<?php

namespace JTD\LaravelMCP\Tests\Unit\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\McpManager;
use JTD\LaravelMCP\Support\Debugger;
use JTD\LaravelMCP\Support\PerformanceMonitor;
use JTD\LaravelMCP\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Helper Functions Tests
 *
 * EPIC: Laravel Integration Layer (006)
 * SPEC: Package Specification Document
 * SPRINT: 3 - Laravel Support Utilities
 * TICKET: 023-LaravelSupport
 *
 * @group unit
 * @group support
 * @group helpers
 * @group ticket-023
 * @group epic-laravel-integration
 * @group sprint-3
 */
#[Group('unit')]
#[Group('support')]
#[Group('helpers')]
#[Group('ticket-023')]
class HelpersTest extends TestCase
{
    protected McpManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a fully mocked manager instance for testing
        $this->manager = Mockery::mock(McpManager::class);
        $this->app->instance('laravel-mcp', $this->manager);

        // Mock the list methods that are called by Mcp::reset()
        $this->manager->shouldReceive('listTools')->andReturn([]);
        $this->manager->shouldReceive('listResources')->andReturn([]);
        $this->manager->shouldReceive('listPrompts')->andReturn([]);

        // Ensure facade is properly bound
        Mcp::swap($this->manager);
    }

    #[Test]
    public function mcp_function_returns_manager_instance(): void
    {
        $result = mcp();

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_function_registers_tool(): void
    {
        $tool = Mockery::mock(McpTool::class);

        $this->manager->shouldReceive('registerTool')
            ->once()
            ->with('calculator', $tool);

        $result = mcp('tool', 'calculator', $tool);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_function_gets_tool(): void
    {
        $tool = Mockery::mock(McpTool::class);

        $this->manager->shouldReceive('getTool')
            ->once()
            ->with('calculator')
            ->andReturn($tool);

        $result = mcp('tool', 'calculator');

        $this->assertSame($tool, $result);
    }

    #[Test]
    public function mcp_function_registers_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);

        $this->manager->shouldReceive('registerResource')
            ->once()
            ->with('database', $resource);

        $result = mcp('resource', 'database', $resource);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_function_gets_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp('resource', 'database');

        $this->assertSame($resource, $result);
    }

    #[Test]
    public function mcp_function_registers_prompt(): void
    {
        $prompt = Mockery::mock(McpPrompt::class);

        $this->manager->shouldReceive('registerPrompt')
            ->once()
            ->with('email', $prompt);

        $result = mcp('prompt', 'email', $prompt);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_function_gets_prompt(): void
    {
        $prompt = Mockery::mock(McpPrompt::class);

        $this->manager->shouldReceive('getPrompt')
            ->once()
            ->with('email')
            ->andReturn($prompt);

        $result = mcp('prompt', 'email');

        $this->assertSame($prompt, $result);
    }

    #[Test]
    public function mcp_function_throws_exception_for_invalid_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid component type: invalid');

        mcp('invalid', 'name');
    }

    #[Test]
    public function mcp_function_throws_exception_when_name_missing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Component name is required');

        mcp('tool');
    }

    #[Test]
    public function mcp_tool_function_registers_tool(): void
    {
        $tool = Mockery::mock(McpTool::class);

        $this->manager->shouldReceive('registerTool')
            ->once()
            ->with('calculator', $tool);

        Cache::shouldReceive('put')
            ->once()
            ->with('mcp:tool:calculator:metadata', ['version' => '1.0'], Mockery::any());

        $result = mcp_tool('calculator', $tool, ['version' => '1.0']);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_tool_function_gets_tool(): void
    {
        $tool = Mockery::mock(McpTool::class);

        $this->manager->shouldReceive('getTool')
            ->once()
            ->with('calculator')
            ->andReturn($tool);

        $result = mcp_tool('calculator');

        $this->assertSame($tool, $result);
    }

    #[Test]
    public function mcp_resource_function_registers_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);

        $this->manager->shouldReceive('registerResource')
            ->once()
            ->with('database', $resource);

        Cache::shouldReceive('put')
            ->once()
            ->with('mcp:resource:database:metadata', ['type' => 'mysql'], Mockery::any());

        $result = mcp_resource('database', $resource, ['type' => 'mysql']);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_resource_function_gets_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp_resource('database');

        $this->assertSame($resource, $result);
    }

    #[Test]
    public function mcp_prompt_function_registers_prompt(): void
    {
        $prompt = Mockery::mock(McpPrompt::class);

        $this->manager->shouldReceive('registerPrompt')
            ->once()
            ->with('email', $prompt);

        Cache::shouldReceive('put')
            ->once()
            ->with('mcp:prompt:email:metadata', ['template' => 'default'], Mockery::any());

        $result = mcp_prompt('email', $prompt, ['template' => 'default']);

        $this->assertInstanceOf(McpManager::class, $result);
    }

    #[Test]
    public function mcp_prompt_function_gets_prompt(): void
    {
        $prompt = Mockery::mock(McpPrompt::class);

        $this->manager->shouldReceive('getPrompt')
            ->once()
            ->with('email')
            ->andReturn($prompt);

        $result = mcp_prompt('email');

        $this->assertSame($prompt, $result);
    }

    #[Test]
    public function mcp_dispatch_executes_tool(): void
    {
        $tool = Mockery::mock(McpTool::class);
        $tool->shouldReceive('execute')
            ->once()
            ->with(['param' => 'value'])
            ->andReturn(['result' => 'success']);

        $this->manager->shouldReceive('getTool')
            ->once()
            ->with('calculator')
            ->andReturn($tool);

        $result = mcp_dispatch('tools/calculator', ['param' => 'value']);

        $this->assertEquals(['result' => 'success'], $result);
    }

    #[Test]
    public function mcp_dispatch_reads_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);
        $resource->shouldReceive('read')
            ->once()
            ->with(['id' => '123'])
            ->andReturn(['data' => 'content']);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp_dispatch('resources/database/read', ['id' => '123']);

        $this->assertEquals(['data' => 'content'], $result);
    }

    #[Test]
    public function mcp_dispatch_lists_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);
        $resource->shouldReceive('list')
            ->once()
            ->with(['filter' => 'active'])
            ->andReturn(['items' => []]);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp_dispatch('resources/database/list', ['filter' => 'active']);

        $this->assertEquals(['items' => []], $result);
    }

    #[Test]
    public function mcp_dispatch_subscribes_to_resource(): void
    {
        $resource = Mockery::mock(McpResource::class);
        $resource->shouldReceive('subscribe')
            ->once()
            ->with(['channel' => 'updates'])
            ->andReturn(['subscription' => 'id-123']);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp_dispatch('resources/database/subscribe', ['channel' => 'updates']);

        $this->assertEquals(['subscription' => 'id-123'], $result);
    }

    #[Test]
    public function mcp_dispatch_generates_prompt(): void
    {
        $prompt = Mockery::mock(McpPrompt::class);
        $prompt->shouldReceive('generate')
            ->once()
            ->with(['name' => 'John'])
            ->andReturn(['prompt' => 'Hello John']);

        $this->manager->shouldReceive('getPrompt')
            ->once()
            ->with('greeting')
            ->andReturn($prompt);

        $result = mcp_dispatch('prompts/greeting', ['name' => 'John']);

        $this->assertEquals(['prompt' => 'Hello John'], $result);
    }

    #[Test]
    public function mcp_dispatch_throws_exception_for_missing_tool(): void
    {
        $this->manager->shouldReceive('getTool')
            ->once()
            ->with('missing')
            ->andReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool not found: missing');

        mcp_dispatch('tools/missing');
    }

    #[Test]
    public function mcp_dispatch_throws_exception_for_invalid_resource_action(): void
    {
        $resource = Mockery::mock(McpResource::class);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid resource action: invalid');

        mcp_dispatch('resources/database/invalid');
    }

    #[Test]
    public function mcp_dispatch_throws_exception_for_unknown_method(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown MCP method: unknown/method');

        mcp_dispatch('unknown/method');
    }

    #[Test]
    public function mcp_async_dispatches_async_request(): void
    {
        // Mock the Queue facade to prevent actual job dispatch
        Queue::fake();

        // Call the helper which will dispatch a job
        $result = mcp_async('tools/calculator', ['param' => 'value'], ['user' => 'test'], 'high');

        // Verify that a job was dispatched
        Queue::assertPushed(ProcessMcpRequest::class, function ($job) {
            return $job->method === 'tools/calculator';
        });

        // The result should be a request ID (UUID-like string)
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    #[Test]
    public function mcp_async_result_gets_async_result(): void
    {
        // Mock cache to return async result
        Cache::shouldReceive('get')
            ->once()
            ->with('mcp:async:result:request-123')
            ->andReturn(['result' => 'success']);

        $result = mcp_async_result('request-123');

        $this->assertEquals(['result' => 'success'], $result);
    }

    #[Test]
    public function mcp_async_status_gets_async_status(): void
    {
        // Store a status in cache
        Cache::put('mcp:async:status:request-123', ['status' => 'completed'], 60);

        $result = mcp_async_status('request-123');

        $this->assertEquals(['status' => 'completed'], $result);

        // Clean up
        Cache::forget('mcp:async:status:request-123');
    }

    #[Test]
    public function mcp_serialize_serializes_message(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['foo' => 'bar'],
            'id' => 1,
        ];

        $json = mcp_serialize($message);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($message, $decoded);
    }

    #[Test]
    public function mcp_serialize_with_custom_depth(): void
    {
        $message = [
            'level1' => [
                'level2' => [
                    'level3' => 'deep',
                ],
            ],
        ];

        $json = mcp_serialize($message, 5);

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertEquals($message, $decoded);
    }

    #[Test]
    public function mcp_deserialize_deserializes_json(): void
    {
        $json = '{"jsonrpc":"2.0","method":"test","params":{"foo":"bar"},"id":1}';

        $message = mcp_deserialize($json);

        $this->assertIsArray($message);
        $this->assertEquals('2.0', $message['jsonrpc']);
        $this->assertEquals('test', $message['method']);
        $this->assertEquals(['foo' => 'bar'], $message['params']);
    }

    #[Test]
    public function mcp_debug_returns_debugger_instance(): void
    {
        $debugger = mcp_debug();

        $this->assertInstanceOf(Debugger::class, $debugger);
    }

    #[Test]
    public function mcp_debug_logs_message(): void
    {
        $debugger = Mockery::mock(Debugger::class);
        $this->app->instance(Debugger::class, $debugger);

        $debugger->shouldReceive('log')
            ->once()
            ->with('Test message', ['context' => 'test']);

        mcp_debug('Test message', ['context' => 'test']);
    }

    #[Test]
    public function mcp_performance_returns_monitor_instance(): void
    {
        $monitor = mcp_performance();

        $this->assertInstanceOf(PerformanceMonitor::class, $monitor);
    }

    #[Test]
    public function mcp_performance_records_metric(): void
    {
        $monitor = Mockery::mock(PerformanceMonitor::class);
        $this->app->instance(PerformanceMonitor::class, $monitor);

        $monitor->shouldReceive('record')
            ->once()
            ->with('test.metric', 42.5, ['tag' => 'value']);

        mcp_performance('test.metric', 42.5, ['tag' => 'value']);
    }

    #[Test]
    public function mcp_measure_measures_callback_execution(): void
    {
        $monitor = Mockery::mock(PerformanceMonitor::class);
        $this->app->instance(PerformanceMonitor::class, $monitor);

        $callback = fn () => 'result';

        $monitor->shouldReceive('measure')
            ->once()
            ->with($callback, 'test.callback', ['tag' => 'value'])
            ->andReturn('result');

        $result = mcp_measure($callback, 'test.callback', ['tag' => 'value']);

        $this->assertEquals('result', $result);
    }

    #[Test]
    public function mcp_is_running_checks_server_status(): void
    {
        Mcp::shouldReceive('isServerRunning')
            ->once()
            ->andReturn(true);

        $result = mcp_is_running();

        $this->assertTrue($result);
    }

    #[Test]
    public function mcp_capabilities_gets_capabilities(): void
    {
        Mcp::shouldReceive('getCapabilities')
            ->once()
            ->andReturn(['tools' => true, 'resources' => true]);

        $result = mcp_capabilities();

        $this->assertEquals(['tools' => true, 'resources' => true], $result);
    }

    #[Test]
    public function mcp_capabilities_sets_capabilities(): void
    {
        $capabilities = ['tools' => false, 'resources' => true];

        Mcp::shouldReceive('setCapabilities')
            ->once()
            ->with($capabilities);

        Mcp::shouldReceive('getCapabilities')
            ->once()
            ->andReturn($capabilities);

        $result = mcp_capabilities($capabilities);

        $this->assertEquals($capabilities, $result);
    }

    #[Test]
    public function mcp_stats_gets_component_summary(): void
    {
        // Since getComponentSummary is a static method on the facade that calls listTools, etc.,
        // and these are already mocked to return empty arrays in setUp(),
        // the result should be all zeros
        $result = mcp_stats();

        // With empty arrays from the mocked list methods, counts should be 0
        $this->assertIsArray($result);
        $this->assertArrayHasKey('tools', $result);
        $this->assertArrayHasKey('resources', $result);
        $this->assertArrayHasKey('prompts', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEquals(0, $result['tools']);
        $this->assertEquals(0, $result['resources']);
        $this->assertEquals(0, $result['prompts']);
        $this->assertEquals(0, $result['total']);
    }

    #[Test]
    public function mcp_discover_discovers_components(): void
    {
        $paths = ['/app/Mcp', '/custom/path'];

        Mcp::shouldReceive('discover')
            ->once()
            ->with($paths)
            ->andReturn(['Tool1', 'Resource1']);

        $result = mcp_discover($paths);

        $this->assertEquals(['Tool1', 'Resource1'], $result);
    }

    #[Test]
    public function mcp_validate_message_validates_message(): void
    {
        $validMessage = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [],
            'id' => 1,
        ];

        $result = mcp_validate_message($validMessage);

        $this->assertTrue($result);
    }

    #[Test]
    public function mcp_validate_message_rejects_invalid_message(): void
    {
        $invalidMessage = [
            'method' => 'test',
            // Missing jsonrpc
        ];

        $result = mcp_validate_message($invalidMessage);

        $this->assertFalse($result);
    }

    #[Test]
    #[DataProvider('errorResponseProvider')]
    public function mcp_error_creates_error_response(int $code, string $message, $data, $id): void
    {
        $error = mcp_error($code, $message, $data, $id);

        $this->assertEquals('2.0', $error['jsonrpc']);
        $this->assertEquals($code, $error['error']['code']);
        $this->assertEquals($message, $error['error']['message']);
        $this->assertEquals($id, $error['id']);

        if ($data !== null) {
            $this->assertEquals($data, $error['error']['data']);
        } else {
            $this->assertArrayNotHasKey('data', $error['error']);
        }
    }

    public static function errorResponseProvider(): array
    {
        return [
            'basic error' => [-32600, 'Invalid Request', null, 1],
            'error with data' => [-32601, 'Method not found', ['method' => 'unknown'], 'req-123'],
            'error with null id' => [-32700, 'Parse error', null, null],
        ];
    }

    #[Test]
    public function mcp_success_creates_success_response(): void
    {
        $result = mcp_success(['data' => 'value'], 'req-123');

        $this->assertEquals('2.0', $result['jsonrpc']);
        $this->assertEquals(['data' => 'value'], $result['result']);
        $this->assertEquals('req-123', $result['id']);
    }

    #[Test]
    public function mcp_success_with_null_id(): void
    {
        $result = mcp_success('success', null);

        $this->assertEquals('2.0', $result['jsonrpc']);
        $this->assertEquals('success', $result['result']);
        $this->assertNull($result['id']);
    }

    #[Test]
    public function mcp_notification_creates_notification(): void
    {
        $notification = mcp_notification('tool.execute', ['param' => 'value']);

        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('tool.execute', $notification['method']);
        $this->assertEquals(['param' => 'value'], $notification['params']);
        $this->assertArrayNotHasKey('id', $notification);
    }

    #[Test]
    public function mcp_notification_with_empty_params(): void
    {
        $notification = mcp_notification('heartbeat');

        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('heartbeat', $notification['method']);
        $this->assertEquals([], $notification['params']);
        $this->assertArrayNotHasKey('id', $notification);
    }

    #[Test]
    public function helper_functions_handle_edge_cases(): void
    {
        // Test mcp_dispatch with default resource action
        $resource = Mockery::mock(McpResource::class);
        $resource->shouldReceive('read')
            ->once()
            ->with([])
            ->andReturn(['default' => 'read']);

        $this->manager->shouldReceive('getResource')
            ->once()
            ->with('database')
            ->andReturn($resource);

        $result = mcp_dispatch('resources/database');
        $this->assertEquals(['default' => 'read'], $result);
    }
}
