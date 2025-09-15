<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use Symfony\Component\Process\Process;

/**
 * Stdio transport implementation for MCP.
 *
 * This class implements the MCP transport protocol over standard input/output,
 * handling JSON-RPC messages via stdin and stdout streams.
 */
class StdioTransport extends BaseTransport
{
    /**
     * Stream handler for input.
     */
    protected ?StreamHandler $inputHandler = null;

    /**
     * Stream handler for output.
     */
    protected ?StreamHandler $outputHandler = null;

    /**
     * Message framer for JSON-RPC protocol.
     */
    protected ?MessageFramer $messageFramer = null;

    /**
     * Process instance for Symfony Process integration.
     */
    protected ?Process $process = null;

    /**
     * Signal handlers registered.
     */
    protected bool $signalHandlersRegistered = false;

    /**
     * Get transport type identifier.
     *
     * @return string Transport type
     */
    protected function getTransportType(): string
    {
        return 'stdio';
    }

    /**
     * Get default configuration for this transport type.
     *
     * @return array Default configuration
     */
    protected function getTransportDefaults(): array
    {
        return [
            'buffer_size' => 8192,
            'line_delimiter' => "\n",
            'max_message_size' => 1048576, // 1MB
            'blocking_mode' => false,
            'read_timeout' => 0.1, // 100ms for non-blocking reads
            'write_timeout' => 5, // 5 seconds for writes
            'use_content_length' => false, // Use Content-Length headers
            'enable_keepalive' => true, // Send periodic keepalive messages
            'keepalive_interval' => 30, // Keepalive every 30 seconds
            'process_timeout' => null, // No timeout for process execution
        ];
    }

    /**
     * Perform transport-specific start operations.
     *
     * @throws \Throwable If start fails
     */
    protected function doStart(): void
    {
        error_log('StdioTransport: doStart() called');

        $this->initializeHandlers();
        $this->openStreams();
        $this->registerSignalHandlers();

        // Register shutdown handler
        register_shutdown_function([$this, 'handleShutdown']);

        error_log('StdioTransport: doStart() completed successfully');
        Log::info('Stdio transport started', [
            'config' => $this->getSafeConfigForLogging(),
        ]);
    }

    /**
     * Perform transport-specific stop operations.
     *
     * @throws \Throwable If stop fails
     */
    protected function doStop(): void
    {
        $this->closeStreams();
        $this->cleanupHandlers();
        $this->unregisterSignalHandlers();

        if ($this->process && $this->process->isRunning()) {
            $this->process->stop(5); // 5 second timeout
        }

        Log::info('Stdio transport stopped');
    }

    /**
     * COMPLETE OVERRIDE: Bypass all Laravel send complexity
     */
    public function send(string $message): void
    {
        // LOG RAW OUTPUT like wrapper script
        error_log('=== RAW OUTPUT TO CLAUDE CODE ===');
        error_log('OUTPUT: ' . $message);
        error_log('=== END RAW OUTPUT ===');

        try {
            // DIRECT APPROACH: Simple fwrite + fflush like our working minimal server
            $messageWithNewline = $message . "\n";

            $written = fwrite(STDOUT, $messageWithNewline);
            if ($written === false) {
                throw new TransportException('Failed to write message to stdout');
            }

            fflush(STDOUT);
            error_log('StdioTransport: Message sent and flushed successfully');

        } catch (\Throwable $e) {
            error_log('StdioTransport: Send error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Legacy doSend - no longer used since we override send() directly
     */
    protected function doSend(string $message): void
    {
        error_log('StdioTransport: doSend() called - this should not happen');
    }

    /**
     * COMPLETE OVERRIDE: Bypass all Laravel transport complexity
     * Use direct STDIN/STDOUT like our working minimal server
     */
    public function receive(): ?string
    {
        error_log('StdioTransport: OVERRIDDEN receive() called');

        try {
            // DIRECT APPROACH: Completely bypass BaseTransport and handlers
            $line = fgets(STDIN);

            if ($line === false) {
                error_log('StdioTransport: fgets() returned false (EOF)');
                return null;
            }

            $message = trim($line);
            if (empty($message)) {
                error_log('StdioTransport: Empty line received');
                return null;
            }

            error_log('StdioTransport: Raw message received: ' . $message);

            // Validate JSON format
            $messageData = json_decode($message, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('StdioTransport: Invalid JSON: ' . json_last_error_msg());
                return null;
            }

            error_log('StdioTransport: Valid JSON message parsed, method: ' . ($messageData['method'] ?? 'no method'));

            return $message;
        } catch (\Throwable $e) {
            error_log('StdioTransport: Exception in receive(): ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Legacy doReceive - no longer used since we override receive() directly
     */
    protected function doReceive(): ?string
    {
        // This method is no longer called since we override receive() completely
        error_log('StdioTransport: doReceive() called - this should not happen');
        return null;
    }

    /**
     * Initialize handlers.
     */
    protected function initializeHandlers(): void
    {
        // Initialize message framer
        $this->messageFramer = new MessageFramer([
            'max_message_size' => $this->config['max_message_size'],
            'use_content_length' => $this->config['use_content_length'],
            'line_delimiter' => $this->config['line_delimiter'],
        ]);
    }

    /**
     * Open input and output streams.
     *
     * @throws TransportException If streams cannot be opened
     */
    protected function openStreams(): void
    {
        // Create input stream handler
        $this->inputHandler = new StreamHandler('php://stdin', 'r', [
            'timeout' => $this->config['timeout'],
            'buffer_size' => $this->config['buffer_size'],
            'max_buffer_size' => $this->config['max_message_size'],
            'blocking' => $this->config['blocking_mode'],
            'read_timeout' => $this->config['read_timeout'],
        ]);

        // Create output stream handler
        $this->outputHandler = new StreamHandler('php://stdout', 'w', [
            'timeout' => $this->config['write_timeout'],
            'buffer_size' => $this->config['buffer_size'],
            'blocking' => true, // Output should be blocking
        ]);

        try {
            $this->inputHandler->open();
            $this->outputHandler->open();
        } catch (\Throwable $e) {
            $this->closeStreams();
            throw new TransportException('Failed to open stdio streams: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Close input and output streams.
     */
    protected function closeStreams(): void
    {
        if ($this->inputHandler) {
            $this->inputHandler->close();
            $this->inputHandler = null;
        }

        if ($this->outputHandler) {
            $this->outputHandler->close();
            $this->outputHandler = null;
        }
    }

    /**
     * Cleanup handlers.
     */
    protected function cleanupHandlers(): void
    {
        if ($this->messageFramer) {
            $this->messageFramer->clearBuffer();
            $this->messageFramer = null;
        }
    }

    /**
     * Register signal handlers.
     */
    protected function registerSignalHandlers(): void
    {
        if ($this->signalHandlersRegistered) {
            return;
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            pcntl_signal(SIGHUP, [$this, 'handleSignal']);
            $this->signalHandlersRegistered = true;

            Log::debug('Signal handlers registered for stdio transport');
        }
    }

    /**
     * Unregister signal handlers.
     */
    protected function unregisterSignalHandlers(): void
    {
        if (! $this->signalHandlersRegistered) {
            return;
        }

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
            pcntl_signal(SIGHUP, SIG_DFL);
            $this->signalHandlersRegistered = false;

            Log::debug('Signal handlers unregistered for stdio transport');
        }
    }

    /**
     * Handle system signals for graceful shutdown.
     *
     * @param  int  $signal  The signal received
     */
    public function handleSignal(int $signal): void
    {
        Log::info('Signal received', ['signal' => $signal]);

        match ($signal) {
            SIGTERM, SIGINT => $this->handleTerminationSignal(),
            SIGHUP => $this->handleReloadSignal(),
            default => Log::debug('Unhandled signal', ['signal' => $signal]),
        };
    }

    /**
     * Handle termination signals.
     */
    protected function handleTerminationSignal(): void
    {
        Log::info('Initiating graceful shutdown');
        $this->stop();
        exit(0);
    }

    /**
     * Handle reload signal.
     */
    protected function handleReloadSignal(): void
    {
        Log::info('Reloading configuration');
        // Could implement configuration reload here
    }

    /**
     * Handle shutdown.
     */
    public function handleShutdown(): void
    {
        if ($this->isConnected()) {
            // Only log if the application is still available
            try {
                if (app() && app()->bound('log')) {
                    Log::info('Shutdown handler triggered, stopping transport');
                }
            } catch (\Exception $e) {
                // Ignore logging errors during shutdown
            }

            // Try to stop cleanly but ignore errors during shutdown
            try {
                $this->stop();
            } catch (\Exception $e) {
                // During shutdown, the container might not be available
                // Just mark as disconnected and clean up what we can
                $this->connected = false;
                $this->closeStreams();
            }
        }
    }

    /**
     * Process messages in a loop (for console applications).
     */
    public function listen(): void
    {
        error_log('=== StdioTransport::listen() CALLED ===');
        error_log('StdioTransport class: ' . get_class($this));

        if (! $this->isConnected()) {
            error_log('StdioTransport: Not connected, calling start()');
            $this->start();
        }

        $lastKeepalive = time();

        try {
            error_log('StdioTransport: Starting listen loop - BYPASSING ALL HANDLERS');

            // EMERGENCY: Completely bypass the complex listen loop
            // Use our simple minimal server approach directly
            error_log('StdioTransport: Entering simple blocking loop');

            while (true) {
                error_log('StdioTransport: Waiting for input with fgets(STDIN)...');

                $line = fgets(STDIN);
                if ($line === false) {
                    error_log('StdioTransport: EOF received, breaking');
                    break;
                }

                $message = trim($line);
                if (empty($message)) {
                    error_log('StdioTransport: Empty message, continuing');
                    continue;
                }

                // LOG RAW INPUT like wrapper script
                error_log('=== RAW INPUT FROM CLAUDE CODE ===');
                error_log('INPUT: ' . $message);
                error_log('=== END RAW INPUT ===');

                // Process with Laravel's MessageProcessor
                if ($this->messageHandler) {
                    try {
                        $messageData = json_decode($message, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            error_log('StdioTransport: Processing with messageHandler');

                            $response = $this->messageHandler->handle($messageData, $this);

                            if ($response) {
                                $responseJson = json_encode($response);
                                error_log('StdioTransport: Sending response: ' . $responseJson);

                                fwrite(STDOUT, $responseJson . "\n");
                                fflush(STDOUT);

                                error_log('StdioTransport: Response sent');
                            } else {
                                error_log('StdioTransport: No response from messageHandler');
                            }
                        }
                    } catch (\Throwable $e) {
                        error_log('StdioTransport: Error processing message: ' . $e->getMessage());
                    }
                }
            }

            return; // Skip the original complex loop

            Log::info('StdioTransport: Starting listen loop', [
                'connected' => $this->isConnected(),
                'has_input_handler' => !!$this->inputHandler,
                'running' => $this->running ?? 'unknown',
            ]);

            $iteration = 0;
            while ($this->isConnected() && $this->inputHandler) {
                $iteration++;
                error_log("StdioTransport: Loop iteration #$iteration");

                // Check for incoming messages
                error_log('StdioTransport: About to call receive()');
                $message = $this->receive();
                error_log('StdioTransport: receive() returned: ' . ($message ? 'MESSAGE' : 'NULL'));

                // Debug: Log loop iteration
                if ($this->config['debug'] ?? false) {
                    Log::debug('StdioTransport: Loop iteration', [
                        'connected' => $this->isConnected(),
                        'running' => $this->running,
                        'connected_property' => $this->connected,
                        'has_input_handler' => !!$this->inputHandler,
                        'message_received' => !!$message,
                    ]);
                }

                if ($message && $this->messageHandler) {
                    try {
                        // Parse JSON message
                        $messageData = json_decode($message, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new TransportException('Invalid JSON message: '.json_last_error_msg());
                        }

                        Log::info('StdioTransport: Processing message', [
                            'method' => $messageData['method'] ?? 'no method',
                            'id' => $messageData['id'] ?? 'no id',
                            'message_length' => strlen($message),
                            'raw_message' => $message,
                            'parsed_data' => $messageData,
                        ]);

                        $response = $this->messageHandler->handle($messageData, $this);

                        Log::info('StdioTransport: Handler response', [
                            'response_type' => $response ? 'has response' : 'no response',
                            'response_keys' => $response ? array_keys($response) : [],
                        ]);

                        if ($response) {
                            $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new TransportException('Failed to encode response: '.json_last_error_msg());
                            }

                            // CRITICAL FIX: Force tools to be {} not [] for Claude CLI compatibility
                            $responseJson = str_replace('"tools":[]', '"tools":{}', $responseJson);

                            $this->send($responseJson);
                        }
                    } catch (\Throwable $e) {
                        Log::error('StdioTransport: Message processing error', [
                            'error' => $e->getMessage(),
                            'message' => $message,
                        ]);
                        if ($this->messageHandler) {
                            $this->messageHandler->handleError($e, $this);
                        }
                        $this->sendErrorResponse($e, $messageData['id'] ?? null);
                    }
                }

                // Keepalive disabled for stdio transport - Claude CLI doesn't expect it
                // if ($this->config['enable_keepalive']) {
                //     $now = time();
                //     if ($now - $lastKeepalive >= $this->config['keepalive_interval']) {
                //         $this->sendKeepalive();
                //         $lastKeepalive = $now;
                //     }
                // }

                // Handle signals if available
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Prevent busy waiting - reduced delay for faster response
                usleep(1000); // 1ms
            }

            Log::info('StdioTransport: Listen loop ended', [
                'connected' => $this->isConnected(),
                'has_input_handler' => !!$this->inputHandler,
                'running' => $this->running,
                'connected_property' => $this->connected,
                'final_state' => 'loop_exit',
            ]);

        } catch (\Throwable $e) {
            Log::error('Stdio transport listen error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        } finally {
            $this->stop();
        }
    }

    /**
     * Send a keepalive notification.
     */
    protected function sendKeepalive(): void
    {
        try {
            $keepalive = $this->messageFramer->createRequest(
                'keepalive',
                ['timestamp' => time()]
            );

            $keepaliveJson = json_encode($keepalive, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $this->send($keepaliveJson);

            Log::debug('Keepalive sent');
        } catch (\Throwable $e) {
            Log::warning('Failed to send keepalive', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Send an error response.
     *
     * @param  \Throwable  $error  The error to send
     * @param  mixed  $id  The request ID if available
     */
    protected function sendErrorResponse(\Throwable $error, $id = null): void
    {
        $errorResponse = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $error->getCode() ?: -32603,
                'message' => $error->getMessage(),
            ],
            'id' => $id,
        ];

        try {
            $responseJson = json_encode($errorResponse);
            $this->send($responseJson);
        } catch (\Throwable $e) {
            Log::error('Failed to send error response', [
                'original_error' => $error->getMessage(),
                'send_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run the stdio transport as a console command.
     *
     * @return int Exit code
     */
    public function runAsCommand(): int
    {
        if (! $this->messageHandler) {
            Log::error('No message handler configured for stdio transport');

            return 1;
        }

        try {
            $this->listen();

            return 0;
        } catch (\Throwable $e) {
            Log::error('Stdio transport command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * Clear the message buffer.
     */
    public function clearBuffer(): void
    {
        if ($this->messageFramer) {
            $this->messageFramer->clearBuffer();
        }
    }

    /**
     * Get current buffer content (for debugging).
     *
     * @return string Current buffer content
     */
    public function getBuffer(): string
    {
        return $this->messageFramer ? $this->messageFramer->getBuffer() : '';
    }

    /**
     * Set process for Symfony Process integration.
     *
     * @param  Process  $process  Symfony Process instance
     */
    public function setProcess(Process $process): void
    {
        $this->process = $process;
    }

    /**
     * Get process instance.
     *
     * @return Process|null Process instance or null
     */
    public function getProcess(): ?Process
    {
        return $this->process;
    }

    /**
     * Perform transport-specific health checks.
     *
     * @return array Transport-specific health check results
     */
    protected function performTransportSpecificHealthChecks(): array
    {
        $checks = [];
        $errors = [];

        // Check stream handlers
        $checks['input_handler_available'] = $this->inputHandler !== null;
        $checks['output_handler_available'] = $this->outputHandler !== null;

        if (! $checks['input_handler_available']) {
            $errors[] = 'Input handler is not available';
        } else {
            $inputHealth = $this->inputHandler->healthCheck();
            $checks['stdin_healthy'] = $inputHealth['healthy'];
            $checks['stdin_readable'] = $inputHealth['readable'];
            $checks['stdin_eof'] = $inputHealth['eof'];

            if (! $inputHealth['healthy']) {
                $errors[] = 'Input stream is not healthy';
            }
        }

        if (! $checks['output_handler_available']) {
            $errors[] = 'Output handler is not available';
        } else {
            $outputHealth = $this->outputHandler->healthCheck();
            $checks['stdout_healthy'] = $outputHealth['healthy'];
            $checks['stdout_writable'] = $outputHealth['writable'];

            if (! $outputHealth['healthy']) {
                $errors[] = 'Output stream is not healthy';
            }
        }

        // Check message framer
        if ($this->messageFramer) {
            $framerStats = $this->messageFramer->getStats();
            $bufferSize = $framerStats['buffer_size'];
            $maxSize = $this->config['max_message_size'];
            $checks['buffer_healthy'] = $bufferSize < ($maxSize * 0.9); // Warn at 90% capacity

            if (! $checks['buffer_healthy']) {
                $errors[] = "Message buffer is {$bufferSize} bytes (approaching limit of {$maxSize})";
            }

            $checks['framer_stats'] = $framerStats;
        }

        // Check process if available
        if ($this->process) {
            $checks['process_running'] = $this->process->isRunning();
            $checks['process_successful'] = $this->process->isSuccessful();
        }

        return [
            'checks' => $checks,
            'errors' => $errors,
        ];
    }

    /**
     * Get connection information specific to stdio transport.
     *
     * @return array Extended connection information
     */
    public function getConnectionInfo(): array
    {
        $info = parent::getConnectionInfo();

        $info['stdio_specific'] = [
            'has_input_handler' => $this->inputHandler !== null,
            'has_output_handler' => $this->outputHandler !== null,
            'input_stats' => $this->inputHandler ? $this->inputHandler->getStats() : null,
            'output_stats' => $this->outputHandler ? $this->outputHandler->getStats() : null,
            'framer_stats' => $this->messageFramer ? $this->messageFramer->getStats() : null,
            'signal_handlers_registered' => $this->signalHandlersRegistered,
            'process_running' => $this->process ? $this->process->isRunning() : null,
        ];

        return $info;
    }
}
