<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;

/**
 * Stdio transport implementation for MCP.
 *
 * This class implements the MCP transport protocol over standard input/output,
 * handling JSON-RPC messages via stdin and stdout streams.
 */
class StdioTransport extends BaseTransport
{
    /**
     * Input stream resource.
     */
    protected $inputStream = null;

    /**
     * Output stream resource.
     */
    protected $outputStream = null;

    /**
     * Message buffer for partial messages.
     */
    protected string $messageBuffer = '';

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
        ];
    }

    /**
     * Perform transport-specific start operations.
     *
     * @throws \Throwable If start fails
     */
    protected function doStart(): void
    {
        $this->openStreams();

        // Register signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }

        // Register shutdown handler
        register_shutdown_function([$this, 'stop']);
    }

    /**
     * Perform transport-specific stop operations.
     *
     * @throws \Throwable If stop fails
     */
    protected function doStop(): void
    {
        $this->closeStreams();
        $this->messageBuffer = '';
    }

    /**
     * Perform transport-specific send operations.
     *
     * @param string $message The message to send
     * @throws \Throwable If send fails
     */
    protected function doSend(string $message): void
    {
        if (!$this->outputStream) {
            throw new TransportException('Output stream not available');
        }

        $output = $message . $this->config['line_delimiter'];
        $written = fwrite($this->outputStream, $output);

        if ($written === false) {
            throw new TransportException('Failed to write message to stdout');
        }

        if ($written < strlen($output)) {
            throw new TransportException('Incomplete message write to stdout');
        }

        fflush($this->outputStream);
    }

    /**
     * Perform transport-specific receive operations.
     *
     * @return string|null The received message, or null if none available
     * @throws \Throwable If receive fails
     */
    protected function doReceive(): ?string
    {
        if (!$this->inputStream) {
            return null;
        }

        // Read data from input stream
        $data = fread($this->inputStream, $this->config['buffer_size']);

        if ($data === false) {
            return null;
        }

        if (empty($data)) {
            // Check if stream is still open
            if (feof($this->inputStream)) {
                $this->connected = false;
            }
            return null;
        }

        // Add to buffer
        $this->messageBuffer .= $data;

        // Check for buffer overflow
        if (strlen($this->messageBuffer) > $this->config['max_message_size']) {
            throw TransportException::bufferOverflow(
                $this->getTransportType(),
                $this->config['max_message_size']
            );
        }

        // Look for complete messages (delimited by line delimiter)
        $delimiter = $this->config['line_delimiter'];
        $delimiterPos = strpos($this->messageBuffer, $delimiter);

        if ($delimiterPos === false) {
            // No complete message yet
            return null;
        }

        // Extract the complete message
        $messageContent = substr($this->messageBuffer, 0, $delimiterPos);
        $this->messageBuffer = substr($this->messageBuffer, $delimiterPos + strlen($delimiter));

        return empty($messageContent) ? null : $messageContent;
    }

    /**
     * Open input and output streams.
     *
     * @throws TransportException If streams cannot be opened
     */
    protected function openStreams(): void
    {
        // Open stdin for reading
        $this->inputStream = fopen('php://stdin', 'r');
        if (!$this->inputStream) {
            throw new TransportException('Failed to open stdin for reading');
        }

        // Open stdout for writing
        $this->outputStream = fopen('php://stdout', 'w');
        if (!$this->outputStream) {
            throw new TransportException('Failed to open stdout for writing');
        }

        // Set blocking mode if supported
        if (function_exists('stream_set_blocking')) {
            stream_set_blocking($this->inputStream, $this->config['blocking_mode']);
        }
    }

    /**
     * Close input and output streams.
     */
    protected function closeStreams(): void
    {
        if ($this->inputStream && is_resource($this->inputStream)) {
            fclose($this->inputStream);
            $this->inputStream = null;
        }

        if ($this->outputStream && is_resource($this->outputStream)) {
            fclose($this->outputStream);
            $this->outputStream = null;
        }
    }

    /**
     * Handle system signals for graceful shutdown.
     *
     * @param int $signal The signal received
     */
    public function handleSignal(int $signal): void
    {
        match ($signal) {
            SIGTERM, SIGINT => $this->stop(),
            default => null,
        };
    }

    /**
     * Process messages in a loop (for console applications).
     */
    public function listen(): void
    {
        if (!$this->isConnected()) {
            $this->start();
        }

        try {
            while ($this->isConnected() && !feof($this->inputStream)) {
                $message = $this->receive();

                if ($message && $this->messageHandler) {
                    try {
                        // Parse JSON message
                        $messageData = json_decode($message, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new TransportException('Invalid JSON message: ' . json_last_error_msg());
                        }

                        $response = $this->messageHandler->handle($messageData, $this);

                        if ($response) {
                            $responseJson = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            if (json_last_error() !== JSON_ERROR_NONE) {
                                throw new TransportException('Failed to encode response: ' . json_last_error_msg());
                            }
                            $this->send($responseJson);
                        }
                    } catch (\Throwable $e) {
                        if ($this->messageHandler) {
                            $this->messageHandler->handleError($e, $this);
                        }
                        $this->sendErrorResponse($e, $messageData['id'] ?? null);
                    }
                }

                // Handle signals if available
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }

                // Prevent busy waiting
                usleep(10000); // 10ms
            }
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
     * Send an error response.
     *
     * @param \Throwable $error The error to send
     * @param mixed $id The request ID if available
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
        if (!$this->messageHandler) {
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
        $this->messageBuffer = '';
    }

    /**
     * Get current buffer content (for debugging).
     *
     * @return string Current buffer content
     */
    public function getBuffer(): string
    {
        return $this->messageBuffer;
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

        // Check stream resources
        $checks['stdin_available'] = $this->inputStream && is_resource($this->inputStream);
        $checks['stdout_available'] = $this->outputStream && is_resource($this->outputStream);

        if (!$checks['stdin_available']) {
            $errors[] = 'stdin stream is not available';
        }

        if (!$checks['stdout_available']) {
            $errors[] = 'stdout stream is not available';
        }

        // Check if streams are readable/writable
        if ($this->inputStream && is_resource($this->inputStream)) {
            $checks['stdin_readable'] = !feof($this->inputStream);
        } else {
            $checks['stdin_readable'] = false;
        }

        if ($this->outputStream && is_resource($this->outputStream)) {
            $stream_meta = stream_get_meta_data($this->outputStream);
            $checks['stdout_writable'] = $stream_meta['mode'] === 'w' && !$stream_meta['eof'];
        } else {
            $checks['stdout_writable'] = false;
        }

        // Check buffer size
        $bufferSize = strlen($this->messageBuffer);
        $maxSize = $this->config['max_message_size'];
        $checks['buffer_healthy'] = $bufferSize < ($maxSize * 0.9); // Warn at 90% capacity

        if (!$checks['buffer_healthy']) {
            $errors[] = "Message buffer is {$bufferSize} bytes (approaching limit of {$maxSize})";
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
            'has_stdin' => $this->inputStream !== null,
            'has_stdout' => $this->outputStream !== null,
            'buffer_size' => strlen($this->messageBuffer),
            'max_buffer_size' => $this->config['max_message_size'],
        ];

        return $info;
    }
}