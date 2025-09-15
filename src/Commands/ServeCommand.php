<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use JTD\LaravelMCP\Transport\HttpTransport;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Transport\TransportManager;

/**
 * Artisan command to start the MCP server.
 *
 * This command starts the MCP server using either stdio or HTTP transport,
 * configuring the transport layer and message processor to handle incoming
 * MCP protocol messages.
 */
class ServeCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:serve
                           {--host=127.0.0.1 : The host to serve on}
                           {--port=8000 : The port to serve on}
                           {--transport=stdio : Transport type (stdio|http)}
                           {--timeout=30 : Request timeout in seconds}
                           {--debug : Enable debug mode}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the MCP server';

    /**
     * Transport manager instance.
     */
    protected TransportManager $transportManager;

    /**
     * Message processor instance.
     */
    protected MessageProcessor $messageProcessor;

    /**
     * Active transport instance.
     */
    protected ?object $transport = null;

    /**
     * Flag to track if server should continue running.
     */
    protected bool $shouldRun = true;

    /**
     * Create a new command instance.
     */
    public function __construct(TransportManager $transportManager, MessageProcessor $messageProcessor)
    {
        error_log('=== ServeCommand: __construct() CALLED ===');
        parent::__construct();

        $this->transportManager = $transportManager;
        $this->messageProcessor = $messageProcessor;
        error_log('ServeCommand: Constructor completed successfully');
    }

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        error_log('=== ServeCommand: executeCommand() STARTED ===');

        // Ensure we're in the correct project directory
        $currentDir = getcwd();
        $projectRoot = base_path();
        error_log('ServeCommand: Current working directory: ' . $currentDir);
        error_log('ServeCommand: Laravel project root: ' . $projectRoot);

        if ($currentDir !== $projectRoot) {
            error_log('ServeCommand: Changing working directory to project root');
            if (!chdir($projectRoot)) {
                error_log('ServeCommand: FAILED to change to project directory');
                $this->displayError('Failed to change to project directory: ' . $projectRoot);
                return self::EXIT_ERROR;
            }
            error_log('ServeCommand: Successfully changed to project directory');
        } else {
            error_log('ServeCommand: Already in correct project directory');
        }

        try {
            // Suppress all logging unless debug mode is enabled (for clean JSON-RPC)
            if (!$this->option('debug')) {
                // Set minimum log level to emergency to suppress most logs
                $logger = Log::getLogger();
                if (method_exists($logger, 'setLevel')) {
                    $logger->setLevel(\Monolog\Level::Emergency);
                } else {
                    // For newer Monolog versions, we need to modify handlers
                    foreach ($logger->getHandlers() as $handler) {
                        $handler->setLevel(\Monolog\Level::Emergency);
                    }
                }
            }

            // Register signal handlers for graceful shutdown
            $this->registerSignalHandlers();

            // Get and validate transport type
            $transportType = $this->option('transport');
            error_log('ServeCommand: Transport type: ' . $transportType);

            if (! $this->validateTransportType($transportType)) {
                error_log('ServeCommand: Transport validation FAILED');
                return self::EXIT_INVALID_INPUT;
            }
            error_log('ServeCommand: Transport validation PASSED');

            // Display startup information (only in debug mode)
            if ($this->option('debug')) {
                $this->displayStartupInfo($transportType);
            }

            // Start the appropriate transport
            error_log('ServeCommand: About to call startTransport()');
            $exitCode = $this->startTransport($transportType);
            error_log('ServeCommand: startTransport() returned: ' . $exitCode);

            // Display shutdown message (only in debug mode)
            if ($exitCode === self::EXIT_SUCCESS && $this->option('debug')) {
                $this->success('MCP server stopped gracefully');
            }

            return $exitCode;

        } catch (TransportException $e) {
            return $this->handleError($e, 'Transport error');
        } catch (\Throwable $e) {
            return $this->handleError($e, 'Server error');
        }
    }

    /**
     * Validate input options.
     */
    protected function validateInput(): bool
    {
        // Validate transport type
        if (! $this->validateOptionInList('transport', ['stdio', 'http'])) {
            return false;
        }

        // Validate timeout
        if (! $this->validateNumericOption('timeout', 1, 600)) {
            return false;
        }

        // Validate port for HTTP transport
        if ($this->option('transport') === 'http') {
            if (! $this->validateNumericOption('port', 1, 65535)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate transport type.
     */
    protected function validateTransportType(string $transportType): bool
    {
        if (! $this->transportManager->hasDriver($transportType)) {
            $this->displayError("Transport type '{$transportType}' is not supported", [
                'Available transports' => implode(', ', $this->transportManager->getDrivers()),
            ]);

            return false;
        }

        if (! $this->isTransportEnabled($transportType)) {
            $this->displayError("Transport '{$transportType}' is disabled in configuration", [
                'Enable in' => 'config/mcp-transports.php',
            ]);

            return false;
        }

        return true;
    }

    /**
     * Display startup information.
     */
    protected function displayStartupInfo(string $transportType): void
    {
        $this->sectionHeader('MCP Server Starting');

        $this->status('Initializing MCP server...');

        $info = [
            'Transport' => $transportType,
            'Timeout' => $this->option('timeout').' seconds',
            'Debug' => $this->option('debug') ? 'Enabled' : 'Disabled',
        ];

        if ($transportType === 'http') {
            $info['Host'] = $this->option('host');
            $info['Port'] = $this->option('port');
            $info['URL'] = "http://{$this->option('host')}:{$this->option('port')}/mcp";
        }

        foreach ($info as $key => $value) {
            $this->line("  <comment>$key:</comment> $value");
        }

        $this->newLine();
    }

    /**
     * Start the transport based on type.
     */
    protected function startTransport(string $transportType): int
    {
        // Build configuration from command options
        $config = $this->buildTransportConfig($transportType);

        // Create transport instance with custom configuration
        error_log('ServeCommand: Creating transport of type: ' . $transportType);
        $this->transport = $this->transportManager->createCustomTransport($transportType, $config);
        error_log('ServeCommand: Created transport class: ' . get_class($this->transport));

        // Set the message handler
        error_log('ServeCommand: Setting message handler');
        $this->transport->setMessageHandler($this->messageProcessor);
        error_log('ServeCommand: Message handler set');

        // Configure server information
        $this->messageProcessor->setServerInfo([
            'name' => config('app.name', 'Laravel').' MCP Server',
            'version' => config('laravel-mcp.version', '1.0.0'),
        ]);

        // CRITICAL: Pre-load all MCP components before starting server
        // This ensures tools are available when Claude Code calls tools/list
        error_log('ServeCommand: Pre-loading MCP components');
        try {
            $registry = app('mcp.registry');
            $discovery = app('mcp.component-discovery');

            // Force component discovery to run synchronously
            $discovery->discoverComponents();
            $discovery->registerDiscoveredComponents();

            $toolCount = count($registry->getTools());
            $resourceCount = count($registry->getResources());
            $promptCount = count($registry->getPrompts());

            error_log("ServeCommand: Pre-loaded {$toolCount} tools, {$resourceCount} resources, {$promptCount} prompts");

            if ($this->option('debug')) {
                $this->info("Pre-loaded {$toolCount} tools, {$resourceCount} resources, {$promptCount} prompts");
            }
        } catch (\Throwable $e) {
            error_log('ServeCommand: Component pre-loading failed: ' . $e->getMessage());
            if ($this->option('debug')) {
                $this->warning('Component pre-loading failed: ' . $e->getMessage());
            }
        }

        // Start based on transport type
        if ($transportType === 'stdio') {
            return $this->startStdioTransport();
        } else {
            return $this->startHttpTransport();
        }
    }

    /**
     * Build transport configuration from command options.
     */
    protected function buildTransportConfig(string $transportType): array
    {
        $baseConfig = config("mcp-transports.{$transportType}", []);

        $config = array_merge($baseConfig, [
            'timeout' => (int) $this->option('timeout'),
            'debug' => (bool) $this->option('debug'),
        ]);

        if ($transportType === 'http') {
            $config['host'] = $this->option('host');
            $config['port'] = (int) $this->option('port');
        }

        return $config;
    }

    /**
     * Start the stdio transport.
     */
    protected function startStdioTransport(): int
    {
        if (! $this->transport instanceof StdioTransport) {
            $this->displayError('Failed to create stdio transport');

            return self::EXIT_ERROR;
        }

        // Only show startup messages in debug mode
        if ($this->option('debug')) {
            $this->success('MCP server started (stdio transport)');
            $this->info('Listening on standard input/output...');
            $this->comment('Press Ctrl+C to stop the server');
            $this->newLine();
        }

        // Enable debug logging if requested
        if ($this->option('debug')) {
            Log::channel('stderr')->info('MCP stdio server started in debug mode');
        }

        try {
            // Debug transport information
            error_log('ServeCommand: About to call transport->listen()');
            error_log('ServeCommand: Transport class: ' . get_class($this->transport));
            error_log('ServeCommand: Transport connected: ' . ($this->transport->isConnected() ? 'YES' : 'NO'));

            // Start listening (blocking call)
            error_log('ServeCommand: Calling transport->listen()');
            $this->transport->listen();

            error_log('ServeCommand: transport->listen() returned');
            return self::EXIT_SUCCESS;
        } catch (\Throwable $e) {
            $this->displayError('Stdio transport error', [
                'Error' => $e->getMessage(),
            ]);

            if ($this->option('debug')) {
                $this->debug('Stack trace', $e->getTraceAsString());
            }

            return self::EXIT_ERROR;
        } finally {
            $this->transport->stop();
        }
    }

    /**
     * Start the HTTP transport.
     */
    protected function startHttpTransport(): int
    {
        if (! $this->transport instanceof HttpTransport) {
            $this->displayError('Failed to create HTTP transport');

            return self::EXIT_ERROR;
        }

        $url = $this->transport->getBaseUrl();

        // Only show startup messages in debug mode
        if ($this->option('debug')) {
            $this->success('MCP server started (HTTP transport)');
            $this->info("Server listening at: $url");
            $this->comment('Press Ctrl+C to stop the server');
            $this->newLine();

            // Note: For HTTP transport, the actual serving is handled by Laravel's web server
            // This command would typically be used with `php artisan serve` or a proper web server

            $this->warning('Note: HTTP transport requires a web server to handle requests.');
            $this->line('You can use one of the following:');
            $this->line('  - php artisan serve (for development)');
            $this->line('  - nginx/apache (for production)');
            $this->line('  - Laravel Octane (for high performance)');
            $this->newLine();
        }

        // Start the transport (registers routes and middleware)
        $this->transport->start();

        // Keep the command running until interrupted
        $this->info('HTTP transport initialized. Waiting for requests...');

        while ($this->shouldRun) {
            sleep(1);

            // Check transport health periodically
            if (! $this->transport->isConnected()) {
                $this->warning('Transport disconnected. Attempting to reconnect...');
                $this->transport->start();
            }
        }

        return self::EXIT_SUCCESS;
    }

    /**
     * Register signal handlers for graceful shutdown.
     */
    protected function registerSignalHandlers(): void
    {
        if (! extension_loaded('pcntl')) {
            $this->debug('PCNTL extension not available, signal handling disabled');

            return;
        }

        // Handle SIGINT (Ctrl+C)
        pcntl_signal(SIGINT, [$this, 'handleShutdownSignal']);

        // Handle SIGTERM (kill command)
        pcntl_signal(SIGTERM, [$this, 'handleShutdownSignal']);

        // Enable async signal handling
        pcntl_async_signals(true);

        $this->debug('Signal handlers registered for graceful shutdown');
    }

    /**
     * Handle shutdown signal.
     */
    public function handleShutdownSignal(int $signal): void
    {
        $this->newLine();
        $this->warning('Shutdown signal received. Stopping server...');

        $this->shouldRun = false;

        // Close the transport if it's active
        if ($this->transport) {
            try {
                $this->transport->stop();
                $this->debug('Transport closed successfully');
            } catch (\Throwable $e) {
                $this->debug('Error closing transport', $e->getMessage());
            }
        }
    }

    /**
     * Get transport statistics for monitoring.
     */
    protected function getTransportStats(): array
    {
        if (! $this->transport) {
            return [];
        }

        $stats = [];

        if (method_exists($this->transport, 'getStats')) {
            $stats = $this->transport->getStats();
        }

        $stats['processor'] = [
            'initialized' => $this->messageProcessor->isInitialized(),
            'supported_types' => $this->messageProcessor->getSupportedMessageTypes(),
        ];

        return $stats;
    }

    /**
     * Display periodic status if in verbose mode.
     */
    protected function displayStatus(): void
    {
        if (! $this->isVerbose()) {
            return;
        }

        $stats = $this->getTransportStats();

        if (! empty($stats)) {
            $this->debug('Transport status', $stats);
        }
    }
}
