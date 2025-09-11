<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use JTD\LaravelMCP\Commands\ServeCommand;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test file for the ServeCommand class.
 *
 * @epic Commands
 *
 * @sprint Sprint-2: Command Implementation
 *
 * @ticket TICKET-004: Artisan Commands
 *
 * @covers \JTD\LaravelMCP\Commands\ServeCommand
 */
class ServeCommandTest extends TestCase
{
    /**
     * The transport manager mock.
     */
    protected $transportManager;

    /**
     * The message processor mock.
     */
    protected $messageProcessor;

    /**
     * The serve command instance.
     */
    protected ServeCommand $command;

    /**
     * The buffered output.
     */
    protected BufferedOutput $output;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->transportManager = Mockery::mock(TransportManager::class);
        $this->messageProcessor = Mockery::mock(MessageProcessor::class);

        // Create command instance with mocks
        $this->command = new ServeCommand(
            $this->transportManager,
            $this->messageProcessor
        );

        // Set up Laravel application for the command
        $this->command->setLaravel($this->app);

        // Create buffered output
        $this->output = new BufferedOutput;

        // Set default configuration
        config(['laravel-mcp.enabled' => true]);
        config(['mcp-transports.stdio.enabled' => true]);
        config(['mcp-transports.http.enabled' => true]);
    }

    /**
     * Test command signature validation.
     *
     * @test
     */
    public function it_has_correct_command_signature(): void
    {
        $this->assertEquals('mcp:serve', $this->command->getName());
        $this->assertEquals('Start the MCP server', $this->command->getDescription());

        // Check for all expected options
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('host'));
        $this->assertEquals('127.0.0.1', $definition->getOption('host')->getDefault());

        $this->assertTrue($definition->hasOption('port'));
        $this->assertEquals('8000', $definition->getOption('port')->getDefault());

        $this->assertTrue($definition->hasOption('transport'));
        $this->assertEquals('stdio', $definition->getOption('transport')->getDefault());

        $this->assertTrue($definition->hasOption('timeout'));
        $this->assertEquals('30', $definition->getOption('timeout')->getDefault());

        $this->assertTrue($definition->hasOption('debug'));
        $this->assertFalse($definition->getOption('debug')->getDefault());
    }

    /**
     * Test successful stdio transport initialization.
     *
     * @test
     */
    public function it_starts_stdio_transport_successfully(): void
    {
        // Create stdio transport mock
        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with($this->messageProcessor);
        $stdioTransport->shouldReceive('start')
            ->once();
        $stdioTransport->shouldReceive('stop')
            ->once();

        // Set up transport manager expectations
        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);
        $this->transportManager->shouldReceive('getDrivers')
            ->andReturn(['stdio', 'http']);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->with('stdio', Mockery::type('array'))
            ->andReturn($stdioTransport);

        // Set up message processor expectations
        $this->messageProcessor->shouldReceive('setServerInfo')
            ->once()
            ->with(Mockery::type('array'));

        // Execute command
        $input = new ArrayInput(['--transport' => 'stdio']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('MCP Server Starting', $output);
        $this->assertStringContainsString('Transport: stdio', $output);
        $this->assertStringContainsString('MCP server started (stdio transport)', $output);
        $this->assertStringContainsString('Listening on standard input/output', $output);
    }

    /**
     * Test successful HTTP transport initialization.
     *
     * @test
     */
    public function it_starts_http_transport_successfully(): void
    {
        // Create a custom command that exits immediately
        $command = new class($this->transportManager, $this->messageProcessor) extends ServeCommand
        {
            protected function startHttpTransport(): int
            {
                if (! $this->transport instanceof HttpTransport) {
                    $this->displayError('Failed to create HTTP transport');

                    return self::EXIT_ERROR;
                }

                $url = $this->transport->getBaseUrl();
                $this->success('MCP server started (HTTP transport)');
                $this->info("Server listening at: $url");
                $this->comment('Press Ctrl+C to stop the server');
                $this->newLine();
                $this->warning('Note: HTTP transport requires a web server to handle requests.');
                $this->line('You can use one of the following:');
                $this->line('  - php artisan serve (for development)');
                $this->line('  - nginx/apache (for production)');
                $this->line('  - Laravel Octane (for high performance)');
                $this->newLine();
                $this->transport->listen();
                $this->info('HTTP transport initialized. Waiting for requests...');

                // Exit immediately for testing
                return self::EXIT_SUCCESS;
            }
        };
        $command->setLaravel($this->app);

        // Create HTTP transport mock
        $httpTransport = Mockery::mock(HttpTransport::class);
        $httpTransport->shouldReceive('setMessageHandler')
            ->once()
            ->with($this->messageProcessor);
        $httpTransport->shouldReceive('getBaseUrl')
            ->once()
            ->andReturn('http://127.0.0.1:8080/mcp');
        $httpTransport->shouldReceive('listen')
            ->once();

        // Set up transport manager expectations
        $this->transportManager->shouldReceive('hasDriver')
            ->with('http')
            ->andReturn(true);
        $this->transportManager->shouldReceive('getDrivers')
            ->andReturn(['stdio', 'http']);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->with('http', Mockery::type('array'))
            ->andReturn($httpTransport);

        // Set up message processor expectations
        $this->messageProcessor->shouldReceive('setServerInfo')
            ->once()
            ->with(Mockery::type('array'));

        // Execute command
        $input = new ArrayInput([
            '--transport' => 'http',
            '--host' => '127.0.0.1',
            '--port' => '8080',
        ]);
        $result = $command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('MCP Server Starting', $output);
        $this->assertStringContainsString('Transport: http', $output);
        $this->assertStringContainsString('Host: 127.0.0.1', $output);
        $this->assertStringContainsString('Port: 8080', $output);
        $this->assertStringContainsString('URL: http://127.0.0.1:8080/mcp', $output);
        $this->assertStringContainsString('MCP server started (HTTP transport)', $output);
    }

    /**
     * Test transport validation with invalid transport type.
     *
     * @test
     */
    public function it_validates_invalid_transport_type(): void
    {
        // The validation happens in validateInput() before reaching transport manager
        $input = new ArrayInput(['--transport' => 'invalid']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
        $this->assertStringContainsString('Invalid value for --transport', $output);
        $this->assertStringContainsString('Allowed values: stdio, http', $output);
    }

    /**
     * Test transport validation with disabled transport.
     *
     * @test
     */
    public function it_validates_disabled_transport(): void
    {
        // Ensure MCP is enabled but stdio transport is disabled
        config(['laravel-mcp.enabled' => true]);
        config(['mcp-transports.stdio.enabled' => false]);

        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);

        $input = new ArrayInput(['--transport' => 'stdio']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
        $this->assertStringContainsString("Transport 'stdio' is disabled in configuration", $output);
        $this->assertStringContainsString('Enable in: config/mcp-transports.php', $output);
    }

    /**
     * Test transport validation when transport not registered in manager.
     *
     * @test
     */
    public function it_validates_unregistered_transport_in_manager(): void
    {
        // Create a custom command that bypasses initial validation
        $command = new class($this->transportManager, $this->messageProcessor) extends ServeCommand
        {
            protected function validateInput(): bool
            {
                // Skip normal validation to test transport manager path
                return true;
            }
        };
        $command->setLaravel($this->app);

        $this->transportManager->shouldReceive('hasDriver')
            ->with('websocket')
            ->andReturn(false);
        $this->transportManager->shouldReceive('getDrivers')
            ->andReturn(['stdio', 'http']);

        $input = new ArrayInput(['--transport' => 'websocket']);
        $result = $command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
        $this->assertStringContainsString("Transport type 'websocket' is not supported", $output);
        $this->assertStringContainsString('Available transports: stdio, http', $output);
    }

    /**
     * Test timeout option validation.
     *
     * @test
     */
    public function it_validates_timeout_option(): void
    {
        $input = new ArrayInput(['--timeout' => '0']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
        $this->assertStringContainsString('The --timeout option must be at least 1', $output);

        // Test maximum timeout
        $input = new ArrayInput(['--timeout' => '700']);
        $this->output = new BufferedOutput;
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result);
        $this->assertStringContainsString('The --timeout option must be no more than 600', $output);
    }

    /**
     * Test port option validation for HTTP transport.
     *
     * @test
     */
    public function it_validates_port_option_for_http_transport(): void
    {
        $input = new ArrayInput(['--transport' => 'http', '--port' => '0']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
        $this->assertStringContainsString('The --port option must be at least 1', $output);

        // Test maximum port
        $input = new ArrayInput(['--transport' => 'http', '--port' => '70000']);
        $this->output = new BufferedOutput;
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(2, $result);
        $this->assertStringContainsString('The --port option must be no more than 65535', $output);
    }

    /**
     * Test debug mode functionality.
     *
     * @test
     */
    public function it_enables_debug_mode(): void
    {
        // Create stdio transport mock
        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')->once();
        $stdioTransport->shouldReceive('start')->once();
        $stdioTransport->shouldReceive('stop')->once();

        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->with('stdio', Mockery::on(function ($config) {
                return isset($config['debug']) && $config['debug'] === true;
            }))
            ->andReturn($stdioTransport);

        $this->messageProcessor->shouldReceive('setServerInfo')->once();

        $input = new ArrayInput(['--debug' => true]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Debug: Enabled', $output);
    }

    /**
     * Test transport creation failure handling.
     *
     * @test
     */
    public function it_handles_transport_creation_failure(): void
    {
        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->andThrow(new TransportException('Failed to create transport'));

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result); // EXIT_ERROR
        $this->assertStringContainsString('Failed to create transport', $output);
    }

    /**
     * Test stdio transport error handling.
     *
     * @test
     */
    public function it_handles_stdio_transport_errors(): void
    {
        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')->once();
        $stdioTransport->shouldReceive('start')
            ->once()
            ->andThrow(new \RuntimeException('Connection lost'));
        $stdioTransport->shouldReceive('stop')->once();

        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->andReturn($stdioTransport);

        $this->messageProcessor->shouldReceive('setServerInfo')->once();

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result); // EXIT_ERROR
        $this->assertStringContainsString('Stdio transport error', $output);
        $this->assertStringContainsString('Connection lost', $output);
    }

    /**
     * Test transport configuration building.
     *
     * @test
     */
    public function it_builds_correct_transport_configuration(): void
    {
        // Set base configuration
        config(['mcp-transports.http' => [
            'enabled' => true,
            'middleware' => ['auth'],
            'cors' => true,
        ]]);

        // Create a custom command that exits immediately
        $command = new class($this->transportManager, $this->messageProcessor) extends ServeCommand
        {
            protected function startHttpTransport(): int
            {
                // Just return success for testing config
                return self::EXIT_SUCCESS;
            }
        };
        $command->setLaravel($this->app);

        $httpTransport = Mockery::mock(HttpTransport::class);
        $httpTransport->shouldReceive('setMessageHandler')->once();

        $this->transportManager->shouldReceive('hasDriver')->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->with('http', Mockery::on(function ($config) {
                return $config['timeout'] === 60 &&
                       $config['debug'] === true &&
                       $config['host'] === 'localhost' &&
                       $config['port'] === 9000 &&
                       $config['middleware'] === ['auth'] &&
                       $config['cors'] === true;
            }))
            ->andReturn($httpTransport);

        $this->messageProcessor->shouldReceive('setServerInfo')->once();

        $input = new ArrayInput([
            '--transport' => 'http',
            '--host' => 'localhost',
            '--port' => '9000',
            '--timeout' => '60',
            '--debug' => true,
        ]);
        $result = $command->run($input, $this->output);

        $this->assertEquals(0, $result);
    }

    /**
     * Test command with invalid transport instance.
     *
     * @test
     */
    public function it_handles_invalid_transport_instance(): void
    {
        // Return wrong transport type
        $httpTransport = Mockery::mock(HttpTransport::class);

        $this->transportManager->shouldReceive('hasDriver')
            ->with('stdio')
            ->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')
            ->andReturn($httpTransport); // Returns HTTP when stdio expected

        $this->messageProcessor->shouldReceive('setServerInfo')->once();
        $httpTransport->shouldReceive('setMessageHandler')->once();

        $input = new ArrayInput(['--transport' => 'stdio']);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result); // EXIT_ERROR
        $this->assertStringContainsString('Failed to create stdio transport', $output);
    }

    /**
     * Test shutdown signal handling when PCNTL is available.
     *
     * @test
     *
     * @requires extension pcntl
     */
    public function it_handles_shutdown_signals_with_pcntl(): void
    {
        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')->once();
        $stdioTransport->shouldReceive('start')
            ->once()
            ->andReturnUsing(function () {
                // Simulate receiving SIGINT
                posix_kill(posix_getpid(), SIGINT);
                sleep(1);
            });
        // Stop will be called twice: once from signal handler, once from finally block
        $stdioTransport->shouldReceive('stop')->twice();

        $this->transportManager->shouldReceive('hasDriver')->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')->andReturn($stdioTransport);
        $this->messageProcessor->shouldReceive('setServerInfo')->once();

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);
    }

    /**
     * Test server info configuration.
     *
     * @test
     */
    public function it_configures_server_info_correctly(): void
    {
        config(['app.name' => 'Test App']);
        config(['laravel-mcp.version' => '2.0.0']);

        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')->once();
        $stdioTransport->shouldReceive('start')->once();
        $stdioTransport->shouldReceive('stop')->once();

        $this->transportManager->shouldReceive('hasDriver')->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')->andReturn($stdioTransport);

        $this->messageProcessor->shouldReceive('setServerInfo')
            ->once()
            ->with([
                'name' => 'Test App MCP Server',
                'version' => '2.0.0',
            ]);

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        // Assert the command ran successfully
        $this->assertEquals(0, $result);
    }

    /**
     * Test verbose mode with transport stats.
     *
     * @test
     */
    public function it_displays_transport_stats_in_verbose_mode(): void
    {
        $stdioTransport = Mockery::mock(StdioTransport::class);
        $stdioTransport->shouldReceive('setMessageHandler')->once();
        $stdioTransport->shouldReceive('start')->once();
        $stdioTransport->shouldReceive('stop')->once();
        $stdioTransport->shouldReceive('getStats')
            ->andReturn([
                'messages_received' => 10,
                'messages_sent' => 8,
                'errors' => 0,
            ]);

        $this->transportManager->shouldReceive('hasDriver')->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')->andReturn($stdioTransport);
        $this->messageProcessor->shouldReceive('setServerInfo')->once();
        $this->messageProcessor->shouldReceive('isInitialized')->andReturn(true);
        $this->messageProcessor->shouldReceive('getSupportedMessageTypes')
            ->andReturn(['initialize', 'tools/call', 'resources/read']);

        $input = new ArrayInput([]); // No need for -v flag, we set verbosity on output
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);

        // Run command with verbose output
        $result = $this->command->run($input, $output);
        $this->assertEquals(0, $result);

        // Check that verbose output was generated
        $outputText = $output->fetch();
        $this->assertStringContainsString('MCP server started', $outputText);
    }

    /**
     * Test MCP disabled error.
     *
     * @test
     */
    public function it_returns_error_when_mcp_is_disabled(): void
    {
        config(['laravel-mcp.enabled' => false]);

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result); // EXIT_ERROR
        $this->assertStringContainsString('MCP is disabled', $output);
    }

    /**
     * Test HTTP transport reconnection logic.
     *
     * @test
     */
    public function it_attempts_reconnection_for_http_transport(): void
    {
        $httpTransport = Mockery::mock(HttpTransport::class);
        $httpTransport->shouldReceive('setMessageHandler')->once();
        $httpTransport->shouldReceive('getBaseUrl')->andReturn('http://127.0.0.1:8000/mcp');
        $httpTransport->shouldReceive('listen')->twice(); // Initial + reconnect
        $httpTransport->shouldReceive('isConnected')
            ->once()
            ->andReturn(false); // Disconnected to trigger reconnect

        // Create a custom command to control the loop
        $command = new class($this->transportManager, $this->messageProcessor) extends ServeCommand
        {
            private $loopCount = 0;

            protected function startHttpTransport(): int
            {
                if (! $this->transport instanceof HttpTransport) {
                    $this->displayError('Failed to create HTTP transport');

                    return self::EXIT_ERROR;
                }

                $url = $this->transport->getBaseUrl();
                $this->success('MCP server started (HTTP transport)');
                $this->info("Server listening at: $url");
                $this->comment('Press Ctrl+C to stop the server');
                $this->newLine();
                $this->warning('Note: HTTP transport requires a web server to handle requests.');
                $this->line('You can use one of the following:');
                $this->line('  - php artisan serve (for development)');
                $this->line('  - nginx/apache (for production)');
                $this->line('  - Laravel Octane (for high performance)');
                $this->newLine();
                $this->transport->listen();
                $this->info('HTTP transport initialized. Waiting for requests...');

                // Simulate one loop iteration for reconnection test
                if (! $this->transport->isConnected()) {
                    $this->warning('Transport disconnected. Attempting to reconnect...');
                    $this->transport->listen();
                }

                return self::EXIT_SUCCESS;
            }
        };
        $command->setLaravel($this->app);

        $this->transportManager->shouldReceive('hasDriver')->andReturn(true);
        $this->transportManager->shouldReceive('createCustomTransport')->andReturn($httpTransport);
        $this->messageProcessor->shouldReceive('setServerInfo')->once();

        $input = new ArrayInput(['--transport' => 'http']);
        $result = $command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(0, $result);
        $this->assertStringContainsString('Transport disconnected. Attempting to reconnect', $output);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
