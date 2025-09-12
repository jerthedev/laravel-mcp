<?php

/**
 * @file tests/Unit/McpManagerTest.php
 *
 * @description Unit tests for McpManager facade backend
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\McpManager
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-McpManager
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Core System Components
 *
 * @testPriority Critical
 *
 * @quality Production-ready
 *
 * @coverage 95%+
 *
 * @standards PSR-12, PHPUnit 10.x
 */

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\McpManager;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\RouteRegistrar;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(McpManager::class)]
#[Group('ticket-027')]
#[Group('core')]
#[Group('facade')]
class McpManagerTest extends UnitTestCase
{
    private McpManager $manager;

    private McpRegistry $registry;

    private RouteRegistrar $registrar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = $this->createMock(McpRegistry::class);
        $this->registrar = $this->createMock(RouteRegistrar::class);
        $this->manager = new McpManager($this->registry, $this->registrar);
    }

    #[Test]
    public function it_constructs_with_required_dependencies(): void
    {
        $this->assertInstanceOf(McpManager::class, $this->manager);
        $this->assertSame($this->registry, $this->manager->getRegistry());
        $this->assertSame($this->registrar, $this->manager->getRegistrar());
    }

    #[Test]
    public function it_registers_tool_using_route_style(): void
    {
        $name = 'calculator';
        $handler = fn () => 'result';
        $options = ['description' => 'Calculator tool'];

        $this->registrar->expects($this->once())
            ->method('tool')
            ->with($name, $handler, $options);

        $result = $this->manager->tool($name, $handler, $options);

        $this->assertSame($this->manager, $result);
    }

    #[Test]
    public function it_registers_resource_using_route_style(): void
    {
        $name = 'users';
        $handler = fn () => 'users';
        $options = ['description' => 'User resource'];

        $this->registrar->expects($this->once())
            ->method('resource')
            ->with($name, $handler, $options);

        $result = $this->manager->resource($name, $handler, $options);

        $this->assertSame($this->manager, $result);
    }

    #[Test]
    public function it_registers_prompt_using_route_style(): void
    {
        $name = 'greeting';
        $handler = fn () => 'Hello';
        $options = ['description' => 'Greeting prompt'];

        $this->registrar->expects($this->once())
            ->method('prompt')
            ->with($name, $handler, $options);

        $result = $this->manager->prompt($name, $handler, $options);

        $this->assertSame($this->manager, $result);
    }

    #[Test]
    public function it_creates_component_registration_group(): void
    {
        $attributes = ['middleware' => 'auth'];
        $callback = fn () => null;

        $this->registrar->expects($this->once())
            ->method('group')
            ->with($attributes, $callback);

        $this->manager->group($attributes, $callback);
    }

    #[Test]
    public function it_delegates_to_registrar_when_method_exists(): void
    {
        $this->registrar->expects($this->once())
            ->method('middleware')
            ->with('auth')
            ->willReturn($this->registrar);

        $this->manager->middleware('auth');
    }

    #[Test]
    public function it_delegates_to_registry_when_method_exists(): void
    {
        $this->registry->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        $this->manager->clear();
    }

    #[Test]
    public function it_throws_exception_for_undefined_method(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Method undefinedMethod does not exist on McpManager');

        $this->manager->undefinedMethod();
    }

    #[Test]
    public function it_dispatches_component_registered_event_when_enabled(): void
    {
        Event::fake();
        Config::set('laravel-mcp.events.enabled', true);

        $type = 'tool';
        $name = 'calculator';
        $component = new \stdClass;
        $metadata = ['version' => '1.0'];

        $this->manager->dispatchComponentRegistered($type, $name, $component, $metadata);

        Event::assertDispatched(McpComponentRegistered::class, function ($event) use ($type, $name, $component, $metadata) {
            return $event->type === $type &&
                   $event->name === $name &&
                   $event->component === $component &&
                   $event->metadata === $metadata;
        });
    }

    #[Test]
    public function it_does_not_dispatch_component_registered_event_when_disabled(): void
    {
        Event::fake();
        Config::set('laravel-mcp.events.enabled', false);

        $this->manager->dispatchComponentRegistered('tool', 'calculator', new \stdClass);

        Event::assertNotDispatched(McpComponentRegistered::class);
    }

    #[Test]
    public function it_dispatches_request_processed_event_when_enabled(): void
    {
        Event::fake();
        Config::set('laravel-mcp.events.enabled', true);

        $requestId = 'req-123';
        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $result = ['output' => '42'];
        $executionTime = 1.23;
        $transport = 'http';
        $context = ['user_id' => 1];

        $this->manager->dispatchRequestProcessed(
            $requestId,
            $method,
            $parameters,
            $result,
            $executionTime,
            $transport,
            $context
        );

        Event::assertDispatched(McpRequestProcessed::class, function ($event) use (
            $requestId,
            $method,
            $parameters,
            $result,
            $executionTime,
            $transport,
            $context
        ) {
            return $event->requestId === $requestId &&
                   $event->method === $method &&
                   $event->parameters === $parameters &&
                   $event->result === $result &&
                   $event->executionTime === $executionTime &&
                   $event->transport === $transport &&
                   $event->context === $context;
        });
    }

    #[Test]
    public function it_dispatches_async_request_when_queue_enabled(): void
    {
        Queue::fake();
        Config::set('laravel-mcp.queue.enabled', true);
        Config::set('laravel-mcp.queue.default', 'mcp');
        Log::shouldReceive('info')->once();

        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $context = ['user_id' => 1];

        $requestId = $this->manager->dispatchAsync($method, $parameters, $context);

        $this->assertIsString($requestId);
        Queue::assertPushed(ProcessMcpRequest::class, function ($job) use ($method, $parameters, $context) {
            return $job->method === $method &&
                   $job->parameters === $parameters &&
                   $job->context === $context;
        });
    }

    #[Test]
    public function it_throws_exception_when_dispatching_async_with_queue_disabled(): void
    {
        Config::set('laravel-mcp.queue.enabled', false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Queue processing is not enabled for MCP');

        $this->manager->dispatchAsync('tools/execute');
    }

    #[Test]
    public function it_gets_async_result_from_cache(): void
    {
        $requestId = 'req-123';
        $result = ['status' => 'completed', 'data' => 'result'];

        Cache::shouldReceive('get')
            ->with("mcp:async:result:{$requestId}")
            ->once()
            ->andReturn($result);

        $actual = $this->manager->getAsyncResult($requestId);

        $this->assertSame($result, $actual);
    }

    #[Test]
    public function it_gets_async_status_from_cache(): void
    {
        $requestId = 'req-123';
        $status = ['status' => 'processing', 'progress' => 50];

        Cache::shouldReceive('get')
            ->with("mcp:async:status:{$requestId}")
            ->once()
            ->andReturn($status);

        $actual = $this->manager->getAsyncStatus($requestId);

        $this->assertSame($status, $actual);
    }

    #[Test]
    public function it_sends_error_notification_when_enabled(): void
    {
        Config::set('laravel-mcp.notifications.enabled', true);
        Config::set('laravel-mcp.notifications.admin_email', 'admin@example.com');
        Log::shouldReceive('info')->once();

        $errorType = 'ValidationError';
        $errorMessage = 'Invalid input';
        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $exception = new \Exception('Test error');
        $severity = 'error';

        // Mock notification sending
        $this->app->bind(AnonymousNotifiable::class, function () {
            $mock = $this->createMock(AnonymousNotifiable::class);
            $mock->expects($this->once())->method('notify');

            return $mock;
        });

        $this->manager->notifyError(
            $errorType,
            $errorMessage,
            $method,
            $parameters,
            $exception,
            $severity
        );
    }

    #[Test]
    public function it_does_not_send_error_notification_when_disabled(): void
    {
        Config::set('laravel-mcp.notifications.enabled', false);
        Log::shouldReceive('info')->never();

        $this->manager->notifyError('Error', 'Test error');
    }

    #[Test]
    public function it_gets_and_sets_capabilities(): void
    {
        $capabilities = ['tools' => true, 'resources' => true];

        $this->registry->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($capabilities);

        $actual = $this->manager->getCapabilities();
        $this->assertSame($capabilities, $actual);

        $newCapabilities = ['prompts' => true];
        $this->registry->expects($this->once())
            ->method('setCapabilities')
            ->with($newCapabilities);

        $this->manager->setCapabilities($newCapabilities);
    }

    #[Test]
    #[DataProvider('componentRegistrationProvider')]
    public function it_registers_components(string $type, string $method): void
    {
        Event::fake();
        Config::set('laravel-mcp.events.enabled', true);

        $name = 'test-component';
        $component = new \stdClass;
        $metadata = ['version' => '1.0'];

        $this->registry->expects($this->once())
            ->method('register')
            ->with($type, $name, $component, $metadata);

        $this->manager->$method($name, $component, $metadata);

        Event::assertDispatched(McpComponentRegistered::class);
    }

    public static function componentRegistrationProvider(): array
    {
        return [
            'tool' => ['tool', 'registerTool'],
            'resource' => ['resource', 'registerResource'],
            'prompt' => ['prompt', 'registerPrompt'],
        ];
    }

    #[Test]
    #[DataProvider('componentUnregistrationProvider')]
    public function it_unregisters_components(string $type, string $method): void
    {
        $name = 'test-component';

        $this->registry->expects($this->once())
            ->method('unregister')
            ->with($type, $name)
            ->willReturn(true);

        $result = $this->manager->$method($name);

        $this->assertTrue($result);
    }

    public static function componentUnregistrationProvider(): array
    {
        return [
            'tool' => ['tool', 'unregisterTool'],
            'resource' => ['resource', 'unregisterResource'],
            'prompt' => ['prompt', 'unregisterPrompt'],
        ];
    }

    #[Test]
    #[DataProvider('componentListingProvider')]
    public function it_lists_components(string $method, string $registryMethod): void
    {
        $components = ['component1' => new \stdClass, 'component2' => new \stdClass];

        $this->registry->expects($this->once())
            ->method($registryMethod)
            ->willReturn($components);

        $result = $this->manager->$method();

        $this->assertSame($components, $result);
    }

    public static function componentListingProvider(): array
    {
        return [
            'tools' => ['listTools', 'listTools'],
            'resources' => ['listResources', 'listResources'],
            'prompts' => ['listPrompts', 'listPrompts'],
        ];
    }

    #[Test]
    #[DataProvider('componentGetterProvider')]
    public function it_gets_components_by_name(string $type, string $method): void
    {
        $name = 'test-component';
        $component = new \stdClass;

        $this->registry->expects($this->once())
            ->method('get')
            ->with($type, $name)
            ->willReturn($component);

        $result = $this->manager->$method($name);

        $this->assertSame($component, $result);
    }

    public static function componentGetterProvider(): array
    {
        return [
            'tool' => ['tool', 'getTool'],
            'resource' => ['resource', 'getResource'],
            'prompt' => ['prompt', 'getPrompt'],
        ];
    }

    #[Test]
    #[DataProvider('componentExistenceProvider')]
    public function it_checks_component_existence(string $type, string $method): void
    {
        $name = 'test-component';

        $this->registry->expects($this->once())
            ->method('has')
            ->with($type, $name)
            ->willReturn(true);

        $result = $this->manager->$method($name);

        $this->assertTrue($result);
    }

    public static function componentExistenceProvider(): array
    {
        return [
            'tool' => ['tool', 'hasTool'],
            'resource' => ['resource', 'hasResource'],
            'prompt' => ['prompt', 'hasPrompt'],
        ];
    }

    #[Test]
    public function it_discovers_components(): void
    {
        $paths = ['/app/Mcp/Tools', '/app/Mcp/Resources'];
        $discovered = ['tools' => 2, 'resources' => 3];

        $this->registry->expects($this->once())
            ->method('discover')
            ->with($paths)
            ->willReturn($discovered);

        $result = $this->manager->discover($paths);

        $this->assertSame($discovered, $result);
    }

    #[Test]
    public function it_gets_server_info(): void
    {
        Config::set('laravel-mcp.server.name', 'Test Server');
        Config::set('laravel-mcp.server.version', '2.0.0');

        $capabilities = ['tools' => true];
        $tools = ['calc' => new \stdClass];
        $resources = ['users' => new \stdClass];
        $prompts = [];

        $this->registry->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($capabilities);

        $this->registry->expects($this->once())
            ->method('listTools')
            ->willReturn($tools);

        $this->registry->expects($this->once())
            ->method('listResources')
            ->willReturn($resources);

        $this->registry->expects($this->once())
            ->method('listPrompts')
            ->willReturn($prompts);

        $info = $this->manager->getServerInfo();

        $this->assertSame('Test Server', $info['name']);
        $this->assertSame('2.0.0', $info['version']);
        $this->assertSame('1.0', $info['protocol_version']);
        $this->assertSame($capabilities, $info['capabilities']);
        $this->assertSame(1, $info['components']['tools']);
        $this->assertSame(1, $info['components']['resources']);
        $this->assertSame(0, $info['components']['prompts']);
    }

    #[Test]
    public function it_gets_server_stats(): void
    {
        Cache::shouldReceive('get')->with('mcp:stats:requests_processed', 0)->andReturn(100);
        Cache::shouldReceive('get')->with('mcp:stats:errors_count', 0)->andReturn(5);
        Cache::shouldReceive('get')->with('mcp:stats:avg_response_time', 0)->andReturn(0.25);

        $stats = $this->manager->getServerStats();

        $this->assertArrayHasKey('uptime', $stats);
        $this->assertSame(100, $stats['requests_processed']);
        $this->assertSame(5, $stats['errors_count']);
        $this->assertSame(0.25, $stats['average_response_time']);
        $this->assertArrayHasKey('memory_usage', $stats);
        $this->assertArrayHasKey('peak_memory', $stats);
    }

    #[Test]
    public function it_manages_debug_mode(): void
    {
        Config::shouldReceive('set')->with(['laravel-mcp.debug' => true])->once();
        $this->manager->enableDebugMode();

        Config::shouldReceive('set')->with(['laravel-mcp.debug' => false])->once();
        $this->manager->disableDebugMode();

        Config::shouldReceive('get')->with('laravel-mcp.debug', false)->andReturn(true);
        $this->assertTrue($this->manager->isDebugMode());
    }

    #[Test]
    public function it_manages_server_lifecycle(): void
    {
        Log::shouldReceive('info')
            ->with('MCP server start requested', ['port' => 8080])
            ->once();

        $this->manager->startServer(['port' => 8080]);

        Log::shouldReceive('info')
            ->with('MCP server stop requested')
            ->once();

        $this->manager->stopServer();

        $this->assertFalse($this->manager->isServerRunning());
    }

    #[Test]
    public function it_provides_facade_compatible_aliases(): void
    {
        Event::fake();
        Config::set('laravel-mcp.events.enabled', true);

        // Test fireComponentRegistered alias
        $this->manager->fireComponentRegistered('tool', 'calc', new \stdClass);
        Event::assertDispatched(McpComponentRegistered::class);

        // Test fireRequestProcessed alias
        $this->manager->fireRequestProcessed(
            'req-123',
            'tools/execute',
            [],
            null,
            1.0
        );
        Event::assertDispatched(McpRequestProcessed::class);

        // Test async alias
        Queue::fake();
        Config::set('laravel-mcp.queue.enabled', true);
        Log::shouldReceive('info')->once();

        $requestId = $this->manager->async('tools/execute');
        $this->assertIsString($requestId);
        Queue::assertPushed(ProcessMcpRequest::class);

        // Test asyncResult alias
        Cache::shouldReceive('get')
            ->with('mcp:async:result:req-456')
            ->andReturn(['result' => 'data']);

        $result = $this->manager->asyncResult('req-456');
        $this->assertSame('data', $result);
    }

    #[Test]
    public function it_extracts_result_from_async_result_array(): void
    {
        Cache::shouldReceive('get')
            ->with('mcp:async:result:req-123')
            ->andReturn(['result' => 'extracted', 'meta' => 'data']);

        $result = $this->manager->asyncResult('req-123');
        $this->assertSame('extracted', $result);
    }

    #[Test]
    public function it_returns_raw_async_result_when_not_array(): void
    {
        Cache::shouldReceive('get')
            ->with('mcp:async:result:req-123')
            ->andReturn('raw-result');

        $result = $this->manager->asyncResult('req-123');
        $this->assertSame('raw-result', $result);
    }

    #[Test]
    public function it_gets_notifiable_from_config(): void
    {
        Config::set('laravel-mcp.notifications.enabled', true);
        Config::set('laravel-mcp.notifications.notifiable', \stdClass::class);

        $notifiable = new \stdClass;
        $this->app->bind(\stdClass::class, fn () => $notifiable);

        Log::shouldReceive('info')->once();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('getNotifiable');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager);
        $this->assertSame($notifiable, $result);
    }

    #[Test]
    public function it_returns_null_when_no_notifiable_available(): void
    {
        Config::set('laravel-mcp.notifications.notifiable', null);
        Config::set('laravel-mcp.notifications.admin_email', null);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->manager);
        $method = $reflection->getMethod('getNotifiable');
        $method->setAccessible(true);

        $result = $method->invoke($this->manager);
        $this->assertNull($result);
    }
}
