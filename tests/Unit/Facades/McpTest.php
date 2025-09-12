<?php

/**
 * @file tests/Unit/Facades/McpTest.php
 *
 * @description Unit tests for Mcp Facade
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Facades\Mcp
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-McpFacade
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Core Facade API
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

namespace JTD\LaravelMCP\Tests\Unit\Facades;

use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use JTD\LaravelMCP\Events\McpComponentRegistered;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Jobs\ProcessMcpRequest;
use JTD\LaravelMCP\McpManager;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Mcp::class)]
#[Group('ticket-027')]
#[Group('core')]
#[Group('facade')]
class McpTest extends UnitTestCase
{
    private McpManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = $this->createMock(McpManager::class);
        $this->app->instance('laravel-mcp', $this->manager);
    }

    #[Test]
    public function it_returns_correct_facade_accessor(): void
    {
        $reflection = new \ReflectionClass(Mcp::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        $this->assertSame('laravel-mcp', $accessor);
    }

    #[Test]
    public function it_registers_tool_with_fluent_interface(): void
    {
        $name = 'calculator';
        $tool = new \stdClass;
        $metadata = ['version' => '1.0'];

        $this->manager->expects($this->once())
            ->method('registerTool')
            ->with($name, $tool, $metadata);

        $result = Mcp::tool($name, $tool, $metadata);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_registers_resource_with_fluent_interface(): void
    {
        $name = 'users';
        $resource = new \stdClass;
        $metadata = ['version' => '1.0'];

        $this->manager->expects($this->once())
            ->method('registerResource')
            ->with($name, $resource, $metadata);

        $result = Mcp::resource($name, $resource, $metadata);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_registers_prompt_with_fluent_interface(): void
    {
        $name = 'greeting';
        $prompt = new \stdClass;
        $metadata = ['version' => '1.0'];

        $this->manager->expects($this->once())
            ->method('registerPrompt')
            ->with($name, $prompt, $metadata);

        $result = Mcp::prompt($name, $prompt, $metadata);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_configures_capabilities_with_fluent_interface(): void
    {
        $capabilities = ['tools' => true, 'resources' => true];

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with($capabilities);

        $result = Mcp::capabilities($capabilities);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_tools_capabilities(): void
    {
        $currentCapabilities = ['resources' => true];
        $toolsConfig = ['maxTokens' => 1000];

        $this->manager->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($currentCapabilities);

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with(['resources' => true, 'tools' => $toolsConfig]);

        $result = Mcp::withTools($toolsConfig);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_resources_capabilities(): void
    {
        $currentCapabilities = ['tools' => true];
        $resourcesConfig = ['subscribe' => true];

        $this->manager->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($currentCapabilities);

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with(['tools' => true, 'resources' => $resourcesConfig]);

        $result = Mcp::withResources($resourcesConfig);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_prompts_capabilities(): void
    {
        $currentCapabilities = ['tools' => true];
        $promptsConfig = ['generate' => true];

        $this->manager->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($currentCapabilities);

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with(['tools' => true, 'prompts' => $promptsConfig]);

        $result = Mcp::withPrompts($promptsConfig);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_logging_capabilities(): void
    {
        $currentCapabilities = [];
        $loggingConfig = ['level' => 'debug'];

        $this->manager->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($currentCapabilities);

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with(['logging' => $loggingConfig]);

        $result = Mcp::withLogging($loggingConfig);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_experimental_capabilities(): void
    {
        $currentCapabilities = [];
        $experimentalConfig = ['feature' => 'beta'];

        $this->manager->expects($this->once())
            ->method('getCapabilities')
            ->willReturn($currentCapabilities);

        $this->manager->expects($this->once())
            ->method('setCapabilities')
            ->with(['experimental' => $experimentalConfig]);

        $result = Mcp::withExperimental($experimentalConfig);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_discovers_components_in_paths(): void
    {
        $paths = ['/app/Mcp/Tools', '/app/Mcp/Resources'];

        $this->manager->expects($this->once())
            ->method('discover')
            ->with($paths);

        $result = Mcp::discoverIn($paths);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    #[DataProvider('componentCountProvider')]
    public function it_counts_components_by_type(string $type, string $method): void
    {
        $components = ['comp1' => new \stdClass, 'comp2' => new \stdClass];

        $this->manager->expects($this->once())
            ->method($method)
            ->willReturn($components);

        $count = Mcp::countComponents($type);

        $this->assertSame(2, $count);
    }

    public static function componentCountProvider(): array
    {
        return [
            'tools' => ['tools', 'listTools'],
            'resources' => ['resources', 'listResources'],
            'prompts' => ['prompts', 'listPrompts'],
        ];
    }

    #[Test]
    public function it_returns_zero_for_invalid_component_type(): void
    {
        $count = Mcp::countComponents('invalid');
        $this->assertSame(0, $count);
    }

    #[Test]
    public function it_counts_total_components(): void
    {
        $tools = ['tool1' => new \stdClass];
        $resources = ['res1' => new \stdClass, 'res2' => new \stdClass];
        $prompts = ['prompt1' => new \stdClass];

        $this->manager->expects($this->once())
            ->method('listTools')
            ->willReturn($tools);

        $this->manager->expects($this->once())
            ->method('listResources')
            ->willReturn($resources);

        $this->manager->expects($this->once())
            ->method('listPrompts')
            ->willReturn($prompts);

        $total = Mcp::totalComponents();

        $this->assertSame(4, $total);
    }

    #[Test]
    public function it_checks_if_has_components(): void
    {
        $this->manager->expects($this->once())
            ->method('listTools')
            ->willReturn(['tool1' => new \stdClass]);

        $this->manager->expects($this->once())
            ->method('listResources')
            ->willReturn([]);

        $this->manager->expects($this->once())
            ->method('listPrompts')
            ->willReturn([]);

        $hasComponents = Mcp::hasComponents();

        $this->assertTrue($hasComponents);
    }

    #[Test]
    public function it_gets_component_summary(): void
    {
        $tools = ['tool1' => new \stdClass];
        $resources = ['res1' => new \stdClass, 'res2' => new \stdClass];
        $prompts = [];

        $this->manager->expects($this->exactly(2))
            ->method('listTools')
            ->willReturn($tools);

        $this->manager->expects($this->exactly(2))
            ->method('listResources')
            ->willReturn($resources);

        $this->manager->expects($this->exactly(2))
            ->method('listPrompts')
            ->willReturn($prompts);

        $summary = Mcp::getComponentSummary();

        $this->assertSame([
            'tools' => 1,
            'resources' => 2,
            'prompts' => 0,
            'total' => 3,
        ], $summary);
    }

    #[Test]
    public function it_resets_all_components(): void
    {
        $tools = ['tool1' => new \stdClass, 'tool2' => new \stdClass];
        $resources = ['res1' => new \stdClass];
        $prompts = ['prompt1' => new \stdClass];

        $this->manager->expects($this->once())
            ->method('listTools')
            ->willReturn($tools);

        $this->manager->expects($this->once())
            ->method('listResources')
            ->willReturn($resources);

        $this->manager->expects($this->once())
            ->method('listPrompts')
            ->willReturn($prompts);

        $this->manager->expects($this->exactly(2))
            ->method('unregisterTool')
            ->withConsecutive(['tool1'], ['tool2']);

        $this->manager->expects($this->once())
            ->method('unregisterResource')
            ->with('res1');

        $this->manager->expects($this->once())
            ->method('unregisterPrompt')
            ->with('prompt1');

        $result = Mcp::reset();

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_fires_component_registered_event(): void
    {
        Event::fake();

        $type = 'tool';
        $name = 'calculator';
        $component = new \stdClass;
        $metadata = ['version' => '1.0'];

        $result = Mcp::fireComponentRegistered($type, $name, $component, $metadata);

        Event::assertDispatched(McpComponentRegistered::class, function ($event) use ($type, $name, $component, $metadata) {
            return $event->type === $type &&
                   $event->name === $name &&
                   $event->component === $component &&
                   $event->metadata === $metadata;
        });

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_fires_request_processed_event(): void
    {
        Event::fake();

        $requestId = 'req-123';
        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $result = ['output' => '42'];
        $executionTime = 1.23;
        $transport = 'http';
        $context = ['user_id' => 1];

        $facadeResult = Mcp::fireRequestProcessed(
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

        $this->assertInstanceOf(Mcp::class, $facadeResult);
    }

    #[Test]
    public function it_dispatches_async_request(): void
    {
        Queue::fake();

        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $context = ['user_id' => 1];
        $queue = 'mcp';

        $requestId = Mcp::async($method, $parameters, $context, $queue);

        $this->assertIsString($requestId);

        Queue::assertPushed(ProcessMcpRequest::class, function ($job) use ($method, $parameters, $context, $queue) {
            return $job->method === $method &&
                   $job->parameters === $parameters &&
                   $job->context === $context &&
                   $job->queue === $queue;
        });
    }

    #[Test]
    public function it_gets_async_result(): void
    {
        $requestId = 'req-123';
        $result = ['status' => 'completed', 'data' => 'result'];

        Cache::shouldReceive('get')
            ->with("mcp:async:result:{$requestId}")
            ->once()
            ->andReturn($result);

        $actual = Mcp::asyncResult($requestId);

        $this->assertSame($result, $actual);
    }

    #[Test]
    public function it_gets_async_status(): void
    {
        $requestId = 'req-123';
        $status = ['status' => 'processing', 'progress' => 50];

        Cache::shouldReceive('get')
            ->with("mcp:async:status:{$requestId}")
            ->once()
            ->andReturn($status);

        $actual = Mcp::asyncStatus($requestId);

        $this->assertSame($status, $actual);
    }

    #[Test]
    public function it_sends_error_notification(): void
    {
        Config::set('laravel-mcp.notifications.admin_email', 'admin@example.com');

        $errorType = 'ValidationError';
        $errorMessage = 'Invalid input';
        $method = 'tools/execute';
        $parameters = ['tool' => 'calculator'];
        $exception = new \Exception('Test error');
        $severity = 'error';

        // Mock notification
        $notifiable = $this->createMock(AnonymousNotifiable::class);
        $notifiable->expects($this->once())->method('notify');

        $this->app->bind(AnonymousNotifiable::class, fn () => $notifiable);

        $result = Mcp::notifyError(
            $errorType,
            $errorMessage,
            $method,
            $parameters,
            $exception,
            $severity
        );

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_configures_event_listeners(): void
    {
        $event = McpComponentRegistered::class;
        $listener = function () {};

        Event::shouldReceive('listen')
            ->with($event, $listener)
            ->once();

        $result = Mcp::on($event, $listener);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_configures_component_registered_listener(): void
    {
        $callback = function () {};

        Event::shouldReceive('listen')
            ->with(McpComponentRegistered::class, $callback)
            ->once();

        $result = Mcp::onComponentRegistered($callback);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_configures_request_processed_listener(): void
    {
        $callback = function () {};

        Event::shouldReceive('listen')
            ->with(McpRequestProcessed::class, $callback)
            ->once();

        $result = Mcp::onRequestProcessed($callback);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_events(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.events.enabled' => true])
            ->once();

        $result = Mcp::withEvents();

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_disables_events(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.events.enabled' => false])
            ->once();

        $result = Mcp::withoutEvents();

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_queue_processing(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.queue.enabled' => true])
            ->once();

        Config::shouldReceive('set')
            ->with(['laravel-mcp.queue.default' => 'mcp'])
            ->once();

        $result = Mcp::withQueue('mcp');

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_disables_queue_processing(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.queue.enabled' => false])
            ->once();

        $result = Mcp::withoutQueue();

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_enables_notifications(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.notifications.enabled' => true])
            ->once();

        Config::shouldReceive('set')
            ->with(['laravel-mcp.notifications.channels' => ['mail', 'database']])
            ->once();

        $result = Mcp::withNotifications(['mail', 'database']);

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_disables_notifications(): void
    {
        Config::shouldReceive('set')
            ->with(['laravel-mcp.notifications.enabled' => false])
            ->once();

        $result = Mcp::withoutNotifications();

        $this->assertInstanceOf(Mcp::class, $result);
    }

    #[Test]
    public function it_gets_notifiable_from_config(): void
    {
        Config::set('laravel-mcp.notifications.notifiable', \stdClass::class);

        $notifiable = new \stdClass;
        $this->app->bind(\stdClass::class, fn () => $notifiable);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass(Mcp::class);
        $method = $reflection->getMethod('getNotifiable');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertSame($notifiable, $result);
    }

    #[Test]
    public function it_gets_notifiable_from_auth_user(): void
    {
        Config::set('laravel-mcp.notifications.notifiable', null);

        $user = new \stdClass;
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($user);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass(Mcp::class);
        $method = $reflection->getMethod('getNotifiable');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertSame($user, $result);
    }

    #[Test]
    public function it_returns_null_when_no_notifiable_available(): void
    {
        Config::set('laravel-mcp.notifications.notifiable', null);
        Config::set('laravel-mcp.notifications.admin_email', null);

        Auth::shouldReceive('check')->andReturn(false);

        // Use reflection to test protected method
        $reflection = new \ReflectionClass(Mcp::class);
        $method = $reflection->getMethod('getNotifiable');
        $method->setAccessible(true);

        $result = $method->invoke(null);
        $this->assertNull($result);
    }
}
