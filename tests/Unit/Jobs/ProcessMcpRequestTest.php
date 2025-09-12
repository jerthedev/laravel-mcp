<?php

namespace JTD\LaravelMCP\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for ProcessMcpRequest job.
 *
 * @ticket LARAVELINTEGRATION-022
 *
 * @epic Laravel Integration
 *
 * @sprint Sprint-3
 *
 * @covers \JTD\LaravelMCP\Jobs\ProcessMcpRequest
 */
#[Group('ticket-022')]
#[Group('jobs')]
#[Group('unit')]
class ProcessMcpRequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_creates_job_with_required_parameters(): void
    {
        $job = new ProcessMcpRequest(
            'tools/calculator',
            ['operation' => 'add', 'a' => 1, 'b' => 2]
        );

        $this->assertEquals('tools/calculator', $job->method);
        $this->assertEquals(['operation' => 'add', 'a' => 1, 'b' => 2], $job->parameters);
        $this->assertNotEmpty($job->requestId);
        $this->assertIsArray($job->context);
        $this->assertTrue($job->context['async']);
        $this->assertNotEmpty($job->context['queued_at']);
    }

    #[Test]
    public function it_creates_job_with_custom_request_id_and_context(): void
    {
        $context = ['source' => 'api', 'priority' => 'high'];

        $job = new ProcessMcpRequest(
            'resources/database',
            ['table' => 'users'],
            'custom-req-123',
            $context
        );

        $this->assertEquals('custom-req-123', $job->requestId);
        $this->assertTrue($job->context['async']);
        $this->assertEquals('api', $job->context['source']);
        $this->assertEquals('high', $job->context['priority']);
    }

    #[Test]
    public function it_has_correct_job_properties(): void
    {
        $job = new ProcessMcpRequest('tools/test', []);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
        $this->assertEquals(10, $job->backoff);
        $this->assertFalse($job->shouldBeEncrypted);
    }

    #[Test]
    public function it_parses_method_correctly(): void
    {
        $job = new ProcessMcpRequest('tools/calculator', []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseMethod');
        $method->setAccessible(true);

        [$type, $name] = $method->invoke($job);

        $this->assertEquals('tools', $type);
        $this->assertEquals('calculator', $name);
    }

    #[Test]
    public function it_throws_exception_for_invalid_method_format(): void
    {
        $job = new ProcessMcpRequest('invalid_method', []);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('parseMethod');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid method format: invalid_method');

        $method->invoke($job);
    }

    #[Test]
    public function it_executes_tool_successfully(): void
    {
        Event::fake();
        Cache::shouldReceive('put')->twice();
        Log::shouldReceive('info')->once();

        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->with(['param' => 'value'])
            ->once()
            ->andReturn(['result' => 'success']);

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getTool')
            ->with('test_tool')
            ->once()
            ->andReturn($mockTool);

        $job = new ProcessMcpRequest('tools/test_tool', ['param' => 'value'], 'req-123');
        $job->handle($mockRegistry);

        Event::assertDispatched(McpRequestProcessed::class, function ($event) {
            return $event->requestId === 'req-123' &&
                   $event->method === 'tools/test_tool' &&
                   $event->result === ['result' => 'success'];
        });
    }

    #[Test]
    public function it_executes_resource_read_action(): void
    {
        Event::fake();
        Cache::shouldReceive('put')->twice();
        Log::shouldReceive('info')->once();

        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('read')
            ->with(['action' => 'read', 'id' => 1])
            ->once()
            ->andReturn(['data' => 'resource_data']);

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getResource')
            ->with('test_resource')
            ->once()
            ->andReturn($mockResource);

        $job = new ProcessMcpRequest('resources/test_resource', ['action' => 'read', 'id' => 1]);
        $job->handle($mockRegistry);

        Event::assertDispatched(McpRequestProcessed::class);
    }

    #[Test]
    public function it_executes_resource_list_action(): void
    {
        Event::fake();
        Cache::shouldReceive('put')->twice();
        Log::shouldReceive('info')->once();

        $mockResource = Mockery::mock();
        $mockResource->shouldReceive('list')
            ->with(['action' => 'list'])
            ->once()
            ->andReturn(['items' => []]);

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getResource')
            ->with('test_resource')
            ->once()
            ->andReturn($mockResource);

        $job = new ProcessMcpRequest('resources/test_resource', ['action' => 'list']);
        $job->handle($mockRegistry);

        Event::assertDispatched(McpRequestProcessed::class);
    }

    #[Test]
    public function it_executes_prompt_generation(): void
    {
        Event::fake();
        Cache::shouldReceive('put')->twice();
        Log::shouldReceive('info')->once();

        $mockPrompt = Mockery::mock();
        $mockPrompt->shouldReceive('generate')
            ->with(['template' => 'test'])
            ->once()
            ->andReturn(['prompt' => 'generated']);

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getPrompt')
            ->with('test_prompt')
            ->once()
            ->andReturn($mockPrompt);

        $job = new ProcessMcpRequest('prompts/test_prompt', ['template' => 'test']);
        $job->handle($mockRegistry);

        Event::assertDispatched(McpRequestProcessed::class);
    }

    #[Test]
    public function it_handles_tool_not_found_error(): void
    {
        Cache::shouldReceive('put')->once();
        Log::shouldReceive('error')->once();

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getTool')
            ->with('missing_tool')
            ->once()
            ->andReturn(null);

        $job = new ProcessMcpRequest('tools/missing_tool', []);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tool not found: missing_tool');

        $job->handle($mockRegistry);
    }

    #[Test]
    public function it_stores_result_in_cache(): void
    {
        Event::fake();
        Log::shouldReceive('info')->once();

        $mockTool = Mockery::mock();
        $mockTool->shouldReceive('execute')
            ->andReturn(['result' => 'success']);

        $mockRegistry = Mockery::mock(McpRegistry::class);
        $mockRegistry->shouldReceive('getTool')
            ->andReturn($mockTool);

        Cache::shouldReceive('put')
            ->once()
            ->with('mcp:async:status:test-req-123', Mockery::any(), 300);

        Cache::shouldReceive('put')
            ->once()
            ->with(
                'mcp:async:result:test-req-123',
                Mockery::on(function ($data) {
                    return $data['status'] === 'completed' &&
                           $data['result'] === ['result' => 'success'] &&
                           isset($data['execution_time_ms']) &&
                           isset($data['completed_at']) &&
                           $data['attempts'] === 1;
                }),
                3600
            );

        $job = new ProcessMcpRequest('tools/test_tool', [], 'test-req-123');
        $job->handle($mockRegistry);
    }

    #[Test]
    public function it_handles_job_failure(): void
    {
        Cache::shouldReceive('put')
            ->once()
            ->with(
                Mockery::pattern('/^mcp:async:result:/'),
                Mockery::on(function ($data) {
                    return $data['status'] === 'failed' &&
                           $data['error'] === 'Test error' &&
                           $data['error_class'] === \Exception::class &&
                           isset($data['failed_at']);
                }),
                3600
            );

        Log::shouldReceive('error')->once();
        Log::shouldReceive('critical')->once();

        $job = new ProcessMcpRequest('tools/test', []);
        $exception = new \Exception('Test error');

        $job->failed($exception);
    }

    #[Test]
    public function it_gets_correct_cache_keys(): void
    {
        $job = new ProcessMcpRequest('tools/test', [], 'custom-123');

        $this->assertEquals('mcp:async:result:custom-123', $job->getResultCacheKey());
        $this->assertEquals('mcp:async:status:custom-123', $job->getStatusCacheKey());
    }

    #[Test]
    public function it_has_correct_tags(): void
    {
        $job = new ProcessMcpRequest('tools/calculator', [], 'req-123');

        $tags = $job->tags();

        $this->assertContains('mcp', $tags);
        $this->assertContains('mcp:tools/calculator', $tags);
        $this->assertContains('request:req-123', $tags);
    }

    #[Test]
    public function it_has_correct_display_name(): void
    {
        $job = new ProcessMcpRequest('tools/calculator', []);

        $this->assertEquals('MCP Request: tools/calculator', $job->displayName());
    }

    #[Test]
    public function it_sets_retry_until_correctly(): void
    {
        $job = new ProcessMcpRequest('tools/test', []);

        $retryUntil = $job->retryUntil();

        $this->assertInstanceOf(\DateTime::class, $retryUntil);

        // Should be approximately 15 minutes from now
        $diff = $retryUntil->getTimestamp() - time();
        $this->assertGreaterThan(14 * 60, $diff);
        $this->assertLessThan(16 * 60, $diff);
    }
}
