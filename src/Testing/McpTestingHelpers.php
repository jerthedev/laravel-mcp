<?php

namespace JTD\LaravelMCP\Testing;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Registry\McpRegistry;

trait McpTestingHelpers
{
    protected function registerMockTool(string $name, $handler): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $registry->register('tool', $name, $handler);
    }

    protected function assertToolExists(string $name): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $this->assertTrue($registry->has('tool', $name));
    }

    protected function assertToolExecutes(string $name, array $parameters, $expectedResult = null): void
    {
        $registry = $this->app->make(McpRegistry::class);
        $tool = $registry->getTool($name);

        $this->assertNotNull($tool);

        $result = $tool->execute($parameters);

        if ($expectedResult !== null) {
            $this->assertEquals($expectedResult, $result);
        }
    }

    protected function mockMcpRequest(string $method, array $params = []): array
    {
        return [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];
    }

    protected function assertMcpResponse(array $response, $expectedResult = null): void
    {
        $this->assertArrayHasKey('jsonrpc', $response);
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('id', $response);

        if ($expectedResult !== null) {
            $this->assertArrayHasKey('result', $response);
            $this->assertEquals($expectedResult, $response['result']);
        }
    }

    protected function assertEventDispatched(string $eventClass, ?callable $callback = null): void
    {
        Event::assertDispatched($eventClass, $callback);
    }

    protected function assertAsyncJobDispatched(string $jobClass, ?callable $callback = null): void
    {
        Queue::assertPushed($jobClass, $callback);
    }

    protected function mockAsyncResult(string $requestId, mixed $result): void
    {
        cache()->put("mcp:async:result:{$requestId}", [
            'status' => 'completed',
            'result' => $result,
            'completed_at' => now()->toISOString(),
        ], 3600);
    }

    protected function simulateSlowRequest(): void
    {
        // Add artificial delay for testing slow request handling
        sleep(2);
    }
}
