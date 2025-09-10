<?php

namespace JTD\LaravelMCP\Tests\Feature\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Feature tests for the MCP ServeCommand.
 *
 * This test validates the complete integration of the ServeCommand with
 * the Laravel application container, service providers, and all dependencies.
 *
 * @epic Commands
 *
 * @spec MCP-SPEC-004: Artisan Commands
 *
 * @sprint Sprint-2: Command Implementation
 *
 * @ticket TICKET-004: Artisan Commands
 *
 * @group feature
 * @group commands
 * @group serve-command
 *
 * @covers \JTD\LaravelMCP\Commands\ServeCommand
 * @covers \JTD\LaravelMCP\Transport\TransportManager
 * @covers \JTD\LaravelMCP\Protocol\MessageProcessor
 */
class ServeCommandFeatureTest extends TestCase
{
    /**
     * Test that the serve command is registered in Laravel's artisan.
     */
    public function test_serve_command_is_registered_in_artisan(): void
    {
        // Arrange & Act
        $commands = Artisan::all();

        // Assert
        $this->assertArrayHasKey('mcp:serve', $commands);
        $this->assertInstanceOf(
            \JTD\LaravelMCP\Commands\ServeCommand::class,
            $commands['mcp:serve']
        );
    }

    /**
     * Test command registration and availability.
     */
    public function test_command_can_be_discovered_by_artisan(): void
    {
        // Act
        $exitCode = Artisan::call('list', ['--raw' => true]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('mcp:serve', $output);
    }

    /**
     * Test command help output displays correct information.
     */
    public function test_command_help_displays_correct_information(): void
    {
        // Act
        $exitCode = Artisan::call('help', ['command_name' => 'mcp:serve']);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('Start the MCP server', $output);
        $this->assertStringContainsString('--host', $output);
        $this->assertStringContainsString('--port', $output);
        $this->assertStringContainsString('--transport', $output);
        $this->assertStringContainsString('--timeout', $output);
        $this->assertStringContainsString('--debug', $output);
    }

    /**
     * Test command execution with stdio transport configuration.
     */
    public function test_command_initializes_with_stdio_transport(): void
    {
        // Arrange
        Config::set('mcp-transports.stdio.enabled', true);
        Config::set('mcp-transports.stdio.buffer_size', 8192);

        // Create a mock stdio transport that immediately returns
        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->expects($this->once())
            ->method('setMessageHandler')
            ->with($this->isInstanceOf(MessageProcessor::class));
        $mockTransport->expects($this->once())
            ->method('start')
            ->willThrowException(new \RuntimeException('Test mode - stopping immediately'));
        $mockTransport->expects($this->once())
            ->method('stop');

        // Replace transport manager with mock
        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->expects($this->once())
            ->method('hasDriver')
            ->with('stdio')
            ->willReturn(true);
        $transportManager->expects($this->once())
            ->method('createCustomTransport')
            ->with('stdio', $this->isType('array'))
            ->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
            '--timeout' => 5,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode); // Error due to mock exception
        $this->assertStringContainsString('MCP Server Starting', $output);
        $this->assertStringContainsString('Transport: stdio', $output);
        $this->assertStringContainsString('Timeout: 5 seconds', $output);
    }

    /**
     * Test command execution with HTTP transport configuration.
     */
    public function test_command_initializes_with_http_transport(): void
    {
        // Arrange
        Config::set('mcp-transports.http.enabled', true);
        Config::set('mcp-transports.http.host', '127.0.0.1');
        Config::set('mcp-transports.http.port', 8080);

        // Create a mock HTTP transport that immediately fails
        $mockTransport = $this->createMock(HttpTransport::class);
        $mockTransport->expects($this->once())
            ->method('setMessageHandler')
            ->with($this->isInstanceOf(MessageProcessor::class));
        $mockTransport->expects($this->once())
            ->method('getBaseUrl')
            ->willReturn('http://127.0.0.1:8080/mcp');
        $mockTransport->expects($this->once())
            ->method('start');

        // Mock isConnected to throw an exception immediately, breaking the while loop
        $mockTransport->expects($this->once())
            ->method('isConnected')
            ->willThrowException(new \RuntimeException('Test mode - exit immediately'));

        $mockTransport->expects($this->any())
            ->method('stop');

        // Replace transport manager with mock
        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->expects($this->once())
            ->method('hasDriver')
            ->with('http')
            ->willReturn(true);
        $transportManager->expects($this->once())
            ->method('createCustomTransport')
            ->with('http', $this->callback(function ($config) {
                return $config['host'] === '127.0.0.1'
                    && $config['port'] === 8080
                    && $config['timeout'] === 30;
            }))
            ->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'http',
            '--host' => '127.0.0.1',
            '--port' => 8080,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode); // Exit code 1 due to exception
        $this->assertStringContainsString('MCP Server Starting', $output);
        $this->assertStringContainsString('Transport: http', $output);
        $this->assertStringContainsString('Host: 127.0.0.1', $output);
        $this->assertStringContainsString('Port: 8080', $output);
        $this->assertStringContainsString('URL: http://127.0.0.1:8080/mcp', $output);
    }

    /**
     * Test command with invalid transport type.
     */
    public function test_command_fails_with_invalid_transport_type(): void
    {
        // Arrange
        $transportManager = $this->app->make(TransportManager::class);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'invalid',
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(2, $exitCode); // Invalid input exit code
        $this->assertStringContainsString('Invalid value for --transport', $output);
        $this->assertStringContainsString('Allowed values: stdio, http', $output);
    }

    /**
     * Test command with disabled transport.
     */
    public function test_command_fails_when_transport_is_disabled(): void
    {
        // Arrange
        Config::set('mcp-transports.stdio.enabled', false);
        Config::set('mcp-transports.http.enabled', true);

        // Create a mock transport manager that reports stdio as available but disabled
        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->expects($this->once())
            ->method('hasDriver')
            ->with('stdio')
            ->willReturn(true);
        // Add getDrivers method for validation error message
        $transportManager->expects($this->any())
            ->method('getDrivers')
            ->willReturn(['stdio', 'http']);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
        ]);
        $output = Artisan::output();

        // Assert - Exit code is 2 (invalid input) when transport is disabled
        $this->assertEquals(2, $exitCode);
        $this->assertStringContainsString("Transport 'stdio' is disabled in configuration", $output);
    }

    /**
     * Test command with debug mode enabled.
     */
    public function test_command_runs_with_debug_mode_enabled(): void
    {
        // Arrange
        Config::set('mcp-transports.stdio.enabled', true);

        // Create a mock transport
        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->method('setMessageHandler');
        $mockTransport->method('start')
            ->willThrowException(new \RuntimeException('Test mode'));
        $mockTransport->method('stop');

        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->method('hasDriver')->willReturn(true);
        $transportManager->method('createCustomTransport')
            ->with('stdio', $this->callback(function ($config) {
                return $config['debug'] === true;
            }))
            ->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Spy on Log facade
        Log::shouldReceive('channel')
            ->with('stderr')
            ->once()
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->with('MCP stdio server started in debug mode')
            ->once();

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
            '--debug' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertStringContainsString('Debug: Enabled', $output);
    }

    /**
     * Test command validates numeric options correctly.
     */
    public function test_command_validates_numeric_options(): void
    {
        // Test invalid port
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'http',
            '--port' => 99999,
        ]);
        $this->assertEquals(2, $exitCode);

        // Test invalid timeout
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
            '--timeout' => 1000,
        ]);
        $this->assertEquals(2, $exitCode);
    }

    /**
     * Test command handles transport exceptions gracefully.
     */
    public function test_command_handles_transport_exceptions_gracefully(): void
    {
        // Arrange
        Config::set('mcp-transports.stdio.enabled', true);

        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->method('setMessageHandler');
        $mockTransport->method('start')
            ->willThrowException(new \JTD\LaravelMCP\Exceptions\TransportException('Connection failed'));
        $mockTransport->method('stop');

        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->method('hasDriver')->willReturn(true);
        $transportManager->method('createCustomTransport')->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertEquals(1, $exitCode);
        // The error message is "Stdio transport error" not "Transport error"
        $this->assertStringContainsString('transport error', strtolower($output));
        $this->assertStringContainsString('Connection failed', $output);
    }

    /**
     * Test command integrates with real transport manager.
     */
    public function test_command_integrates_with_real_transport_manager(): void
    {
        // Arrange - Use real transport manager
        Config::set('mcp-transports.stdio.enabled', true);
        Config::set('mcp-transports.http.enabled', true);

        // Act - Get available drivers
        $transportManager = $this->app->make(TransportManager::class);
        $drivers = $transportManager->getDrivers();

        // Assert
        $this->assertContains('stdio', $drivers);
        $this->assertContains('http', $drivers);

        // Test that command can see these drivers
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'invalid',
        ]);
        $output = Artisan::output();

        $this->assertStringContainsString('Invalid value for --transport', $output);
        $this->assertStringContainsString('Allowed values: stdio, http', $output);
    }

    /**
     * Test command configuration merging from options.
     */
    public function test_command_merges_configuration_from_options(): void
    {
        // Arrange
        Config::set('mcp-transports.http.enabled', true);
        Config::set('mcp-transports.http.host', '0.0.0.0');
        Config::set('mcp-transports.http.port', 3000);

        $mockTransport = $this->createMock(HttpTransport::class);
        $mockTransport->method('setMessageHandler');
        $mockTransport->method('getBaseUrl')->willReturn('http://localhost:9000/mcp');
        $mockTransport->expects($this->once())
            ->method('start');
        // Make isConnected throw an exception to break the loop
        $mockTransport->expects($this->once())
            ->method('isConnected')
            ->willThrowException(new \RuntimeException('Test mode - exit'));
        $mockTransport->method('stop');

        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->method('hasDriver')->willReturn(true);
        $transportManager->expects($this->once())
            ->method('createCustomTransport')
            ->with('http', $this->callback(function ($config) {
                // Verify options override config
                return $config['host'] === 'localhost'
                    && $config['port'] === 9000
                    && $config['timeout'] === 60
                    && $config['debug'] === true;
            }))
            ->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'http',
            '--host' => 'localhost',
            '--port' => 9000,
            '--timeout' => 60,
            '--debug' => true,
        ]);

        // Assert - Exit code 1 due to exception, but configuration was validated
        $this->assertEquals(1, $exitCode);
    }

    /**
     * Test command sets server info on message processor.
     */
    public function test_command_sets_server_info_on_message_processor(): void
    {
        // Arrange
        Config::set('app.name', 'TestApp');
        Config::set('laravel-mcp.version', '2.0.0');
        Config::set('mcp-transports.stdio.enabled', true);

        $messageProcessor = $this->createMock(MessageProcessor::class);
        $messageProcessor->expects($this->once())
            ->method('setServerInfo')
            ->with([
                'name' => 'TestApp MCP Server',
                'version' => '2.0.0',
            ]);

        $this->app->instance(MessageProcessor::class, $messageProcessor);

        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->method('setMessageHandler');
        $mockTransport->method('start')
            ->willThrowException(new \RuntimeException('Test mode'));
        $mockTransport->method('stop');

        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->method('hasDriver')->willReturn(true);
        $transportManager->method('createCustomTransport')->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        Artisan::call('mcp:serve', ['--transport' => 'stdio']);

        // Assert - expectations verified by mock
    }

    /**
     * Test command displays verbose output when requested.
     */
    public function test_command_displays_verbose_output(): void
    {
        // Arrange
        Config::set('mcp-transports.stdio.enabled', true);

        $mockTransport = $this->createMock(StdioTransport::class);
        $mockTransport->method('setMessageHandler');
        $mockTransport->method('start')
            ->willThrowException(new \RuntimeException('Test mode'));
        $mockTransport->method('stop');
        $mockTransport->method('getStats')
            ->willReturn(['messages_received' => 10, 'messages_sent' => 8]);

        $transportManager = $this->createMock(TransportManager::class);
        $transportManager->method('hasDriver')->willReturn(true);
        $transportManager->method('createCustomTransport')->willReturn($mockTransport);

        $this->app->instance(TransportManager::class, $transportManager);

        // Act
        $exitCode = Artisan::call('mcp:serve', [
            '--transport' => 'stdio',
            '-vvv' => true,
        ]);
        $output = Artisan::output();

        // Assert
        $this->assertStringContainsString('Stdio transport error', $output);
    }

    /**
     * Test full integration with service provider and real components.
     */
    public function test_full_integration_with_service_provider(): void
    {
        // This test verifies the command works with the actual service provider
        // and all real dependencies properly registered

        // Act
        $commands = Artisan::all();
        $command = $commands['mcp:serve'];

        // Assert
        $this->assertInstanceOf(\JTD\LaravelMCP\Commands\ServeCommand::class, $command);

        // Verify dependencies are injected
        $reflection = new \ReflectionClass($command);
        $transportManagerProp = $reflection->getProperty('transportManager');
        $transportManagerProp->setAccessible(true);
        $messageProcessorProp = $reflection->getProperty('messageProcessor');
        $messageProcessorProp->setAccessible(true);

        $this->assertInstanceOf(TransportManager::class, $transportManagerProp->getValue($command));
        $this->assertInstanceOf(MessageProcessor::class, $messageProcessorProp->getValue($command));
    }
}
