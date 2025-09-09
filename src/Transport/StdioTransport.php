<?php

namespace JTD\LaravelMCP\Transport;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface;
use JTD\LaravelMCP\Transport\Contracts\TransportInterface;

/**
 * Stdio transport implementation for MCP.
 *
 * This class implements the MCP transport protocol over standard input/output,
 * handling JSON-RPC messages via stdin and stdout streams.
 */
class StdioTransport implements TransportInterface
{
    /**
     * Transport configuration.
     */
    protected array $config = [];

    /**
     * Message handler instance.
     */
    protected ?MessageHandlerInterface $messageHandler = null;

    /**
     * Connection status.
     */
    protected bool $connected = false;

    /**
     * Input stream resource.
     */
    protected $inputStream = null;

    /**
     * Output stream resource.
     */
    protected $outputStream = null;

    /**
     * Default configuration.
     */
    protected array $defaultConfig = [
        'timeout' => 30,
        'buffer_size' => 8192,
        'line_delimiter' => "\n",
        'debug' => false,
    ];

    /**
     * Message buffer for partial messages.
     */
    protected string $messageBuffer = '';

    /**
     * Initialize the transport layer.
     */
    public function initialize(array $config = []): void
    {
        $this->config = array_merge($this->defaultConfig, $config);
        $this->connected = false;
        $this->messageBuffer = '';
    }

    /**
     * Start listening for incoming messages.
     */
    public function listen(): void
    {
        try {
            $this->openStreams();
            $this->connected = true;

            if ($this->config['debug']) {
                Log::info('Stdio MCP transport listening');
            }

            // Main message loop
            while ($this->connected && ! feof($this->inputStream)) {
                $message = $this->receive();

                if ($message && $this->messageHandler) {
                    try {
                        $response = $this->messageHandler->handle($message, $this);

                        if ($response) {
                            $this->send($response);
                        }
                    } catch (\Throwable $e) {
                        if ($this->messageHandler) {
                            $this->messageHandler->handleError($e, $this);
                        }

                        $this->sendError($e, $message['id'] ?? null);
                    }
                }
            }

        } catch (\Throwable $e) {
            Log::error('Stdio transport error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->messageHandler) {
                $this->messageHandler->handleError($e, $this);
            }
        } finally {
            $this->close();
        }
    }

    /**
     * Send a message to the client.
     */
    public function send(array $message): void
    {
        if (! $this->connected || ! $this->outputStream) {
            throw new TransportException('Transport is not connected');
        }

        try {
            $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new TransportException('Failed to encode message as JSON: '.json_last_error_msg());
            }

            $output = $json.$this->config['line_delimiter'];

            $bytesWritten = fwrite($this->outputStream, $output);

            if ($bytesWritten === false) {
                throw new TransportException('Failed to write message to output stream');
            }

            fflush($this->outputStream);

            if ($this->config['debug']) {
                Log::debug('Stdio message sent', ['message' => $message]);
            }

        } catch (\Throwable $e) {
            Log::error('Failed to send stdio message', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            throw new TransportException('Failed to send message: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Receive a message from the client.
     */
    public function receive(): ?array
    {
        if (! $this->connected || ! $this->inputStream) {
            return null;
        }

        try {
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

            // Look for complete messages (delimited by line delimiter)
            $delimiter = $this->config['line_delimiter'];
            $delimiterPos = strpos($this->messageBuffer, $delimiter);

            if ($delimiterPos === false) {
                // No complete message yet
                return null;
            }

            // Extract the complete message
            $messageJson = substr($this->messageBuffer, 0, $delimiterPos);
            $this->messageBuffer = substr($this->messageBuffer, $delimiterPos + strlen($delimiter));

            // Parse JSON
            $message = json_decode($messageJson, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Invalid JSON received', [
                    'json' => $messageJson,
                    'error' => json_last_error_msg(),
                ]);

                return null;
            }

            if ($this->config['debug']) {
                Log::debug('Stdio message received', ['message' => $message]);
            }

            return is_array($message) ? $message : null;

        } catch (\Throwable $e) {
            Log::error('Error receiving stdio message', [
                'error' => $e->getMessage(),
                'buffer' => $this->messageBuffer,
            ]);

            return null;
        }
    }

    /**
     * Close the transport connection.
     */
    public function close(): void
    {
        $this->connected = false;

        if ($this->inputStream && is_resource($this->inputStream)) {
            fclose($this->inputStream);
            $this->inputStream = null;
        }

        if ($this->outputStream && is_resource($this->outputStream)) {
            fclose($this->outputStream);
            $this->outputStream = null;
        }

        $this->messageBuffer = '';

        if ($this->config['debug']) {
            Log::info('Stdio MCP transport closed');
        }
    }

    /**
     * Check if the transport is currently connected/active.
     */
    public function isConnected(): bool
    {
        return $this->connected &&
               $this->inputStream && is_resource($this->inputStream) &&
               $this->outputStream && is_resource($this->outputStream);
    }

    /**
     * Get transport-specific configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set the message handler for processing received messages.
     */
    public function setMessageHandler(MessageHandlerInterface $handler): void
    {
        $this->messageHandler = $handler;
    }

    /**
     * Open input and output streams.
     */
    protected function openStreams(): void
    {
        // Open stdin for reading
        $this->inputStream = fopen('php://stdin', 'r');
        if (! $this->inputStream) {
            throw new TransportException('Failed to open stdin for reading');
        }

        // Open stdout for writing
        $this->outputStream = fopen('php://stdout', 'w');
        if (! $this->outputStream) {
            throw new TransportException('Failed to open stdout for writing');
        }

        // Set streams to non-blocking mode if supported
        if (function_exists('stream_set_blocking')) {
            stream_set_blocking($this->inputStream, false);
        }

        // Notify handler of connection
        if ($this->messageHandler) {
            $this->messageHandler->onConnect($this);
        }
    }

    /**
     * Send an error response.
     */
    protected function sendError(\Throwable $error, $id = null): void
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
            $this->send($errorResponse);
        } catch (\Throwable $e) {
            Log::error('Failed to send error response', [
                'original_error' => $error->getMessage(),
                'send_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run the stdio transport as a console command.
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
     * Get transport statistics.
     */
    public function getStats(): array
    {
        return [
            'transport' => 'stdio',
            'connected' => $this->connected,
            'has_input_stream' => $this->inputStream !== null,
            'has_output_stream' => $this->outputStream !== null,
            'buffer_size' => strlen($this->messageBuffer),
            'has_message_handler' => $this->messageHandler !== null,
        ];
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
     */
    public function getBuffer(): string
    {
        return $this->messageBuffer;
    }
}
