<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Exceptions\TransportException;

/**
 * Message framer for JSON-RPC 2.0 protocol over stdio.
 *
 * This class handles the framing and parsing of JSON-RPC messages
 * for stdio transport, ensuring protocol compliance and proper message
 * boundaries.
 */
class MessageFramer
{
    /**
     * Protocol version.
     */
    public const PROTOCOL_VERSION = '2.0';

    /**
     * Content type for JSON-RPC messages.
     */
    public const CONTENT_TYPE = 'application/json';

    /**
     * Maximum message size (default 10MB).
     */
    protected int $maxMessageSize;

    /**
     * Use Content-Length headers for message framing.
     */
    protected bool $useContentLength;

    /**
     * Line delimiter for messages.
     */
    protected string $lineDelimiter;

    /**
     * Message buffer for incomplete messages.
     */
    protected string $buffer = '';

    /**
     * Statistics for debugging.
     */
    protected array $stats = [
        'messages_framed' => 0,
        'messages_parsed' => 0,
        'parse_errors' => 0,
        'protocol_errors' => 0,
        'buffer_overflows' => 0,
    ];

    /**
     * Create a new message framer instance.
     *
     * @param  array  $config  Configuration options
     */
    public function __construct(array $config = [])
    {
        $this->maxMessageSize = $config['max_message_size'] ?? 10485760; // 10MB
        $this->useContentLength = $config['use_content_length'] ?? false;
        $this->lineDelimiter = $config['line_delimiter'] ?? "\n";
    }

    /**
     * Frame a JSON-RPC message for transmission.
     *
     * @param  array  $message  The message to frame
     * @return string The framed message ready for transmission
     *
     * @throws TransportException If message cannot be framed
     */
    public function frame(array $message): string
    {
        // Ensure message has required JSON-RPC fields
        if (! isset($message['jsonrpc'])) {
            $message['jsonrpc'] = self::PROTOCOL_VERSION;
        }

        // Validate message structure
        $this->validateMessage($message);

        // Encode message to JSON
        $json = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new TransportException('Failed to encode message: '.json_last_error_msg());
        }

        // CRITICAL FIX: Force tools to be {} not [] for Claude CLI compatibility
        $json = str_replace('"tools":[]', '"tools":{}', $json);

        // Check message size
        if (strlen($json) > $this->maxMessageSize) {
            $this->stats['buffer_overflows']++;
            throw TransportException::bufferOverflow('message', $this->maxMessageSize);
        }

        $this->stats['messages_framed']++;

        // Frame with Content-Length header if enabled
        if ($this->useContentLength) {
            return $this->frameWithContentLength($json);
        }

        // Simple line-delimited framing
        return $json.$this->lineDelimiter;
    }

    /**
     * Parse incoming data and extract complete messages.
     *
     * @param  string  $data  Incoming data to parse
     * @return array Array of parsed messages
     *
     * @throws TransportException If parsing fails
     */
    public function parse(string $data): array
    {
        // Add data to buffer
        $this->buffer .= $data;

        // Check for buffer overflow
        if (strlen($this->buffer) > $this->maxMessageSize) {
            $this->stats['buffer_overflows']++;
            $this->buffer = ''; // Clear buffer to recover
            throw TransportException::bufferOverflow('parse buffer', $this->maxMessageSize);
        }

        $messages = [];

        // Extract messages based on framing method
        if ($this->useContentLength) {
            $messages = $this->parseWithContentLength();
        } else {
            $messages = $this->parseLineDelimited();
        }

        // Validate and decode each message
        $parsedMessages = [];
        foreach ($messages as $rawMessage) {
            try {
                $message = $this->decodeMessage($rawMessage);
                if ($message !== null) {
                    $parsedMessages[] = $message;
                    $this->stats['messages_parsed']++;
                }
            } catch (\Throwable $e) {
                $this->stats['parse_errors']++;
                // Logging disabled to avoid dependency issues in unit tests
                // Can be re-enabled with optional logging support
            }
        }

        return $parsedMessages;
    }

    /**
     * Frame a message with Content-Length header.
     *
     * @param  string  $json  JSON content to frame
     * @return string Framed message with headers
     */
    protected function frameWithContentLength(string $json): string
    {
        $contentLength = strlen($json);

        $headers = [
            "Content-Length: {$contentLength}",
            'Content-Type: '.self::CONTENT_TYPE,
        ];

        return implode("\r\n", $headers)."\r\n\r\n".$json;
    }

    /**
     * Parse messages with Content-Length headers.
     *
     * @return array Array of raw message strings
     */
    protected function parseWithContentLength(): array
    {
        $messages = [];

        while (true) {
            // Look for header/body separator
            $headerEnd = strpos($this->buffer, "\r\n\r\n");

            if ($headerEnd === false) {
                // No complete headers yet
                break;
            }

            // Extract headers
            $headers = substr($this->buffer, 0, $headerEnd);
            $contentLength = $this->extractContentLength($headers);

            if ($contentLength === null) {
                // Invalid headers, skip this message
                $this->buffer = substr($this->buffer, $headerEnd + 4);
                $this->stats['protocol_errors']++;

                continue;
            }

            // Calculate where the message body ends
            $bodyStart = $headerEnd + 4;
            $bodyEnd = $bodyStart + $contentLength;

            if (strlen($this->buffer) < $bodyEnd) {
                // Complete message not yet received
                break;
            }

            // Extract the message body
            $messageBody = substr($this->buffer, $bodyStart, $contentLength);
            $messages[] = $messageBody;

            // Remove processed message from buffer
            $this->buffer = substr($this->buffer, $bodyEnd);
        }

        return $messages;
    }

    /**
     * Parse line-delimited messages.
     *
     * @return array Array of raw message strings
     */
    protected function parseLineDelimited(): array
    {
        $messages = [];

        while (true) {
            $delimiterPos = strpos($this->buffer, $this->lineDelimiter);

            if ($delimiterPos === false) {
                // No complete message yet
                break;
            }

            // Extract message
            $message = substr($this->buffer, 0, $delimiterPos);

            if (! empty($message)) {
                $messages[] = $message;
            }

            // Remove processed message from buffer
            $this->buffer = substr($this->buffer, $delimiterPos + strlen($this->lineDelimiter));
        }

        return $messages;
    }

    /**
     * Extract Content-Length from headers.
     *
     * @param  string  $headers  Header string
     * @return int|null Content length or null if not found
     */
    protected function extractContentLength(string $headers): ?int
    {
        $lines = explode("\r\n", $headers);

        foreach ($lines as $line) {
            if (stripos($line, 'Content-Length:') === 0) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $length = trim($parts[1]);
                    if (is_numeric($length)) {
                        return (int) $length;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Decode a raw message string into an array.
     *
     * @param  string  $rawMessage  Raw message string
     * @return array|null Decoded message or null if invalid
     *
     * @throws TransportException If message is invalid
     */
    protected function decodeMessage(string $rawMessage): ?array
    {
        if (empty(trim($rawMessage))) {
            return null;
        }

        $message = json_decode($rawMessage, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TransportException('Invalid JSON: '.json_last_error_msg());
        }

        // Validate JSON-RPC structure
        $this->validateMessage($message);

        return $message;
    }

    /**
     * Validate a JSON-RPC message structure.
     *
     * @param  array  $message  Message to validate
     *
     * @throws TransportException If message is invalid
     */
    protected function validateMessage(array $message): void
    {
        // Check JSON-RPC version
        if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== self::PROTOCOL_VERSION) {
            $this->stats['protocol_errors']++;
            throw new TransportException('Invalid or missing JSON-RPC version');
        }

        // Check for either request or response structure
        $isRequest = isset($message['method']);
        $isResponse = isset($message['result']) || isset($message['error']);
        $isNotification = $isRequest && ! isset($message['id']);

        if (! $isRequest && ! $isResponse) {
            $this->stats['protocol_errors']++;
            throw new TransportException('Message must be either a request or response');
        }

        // Validate request
        if ($isRequest) {
            if (! is_string($message['method'])) {
                $this->stats['protocol_errors']++;
                throw new TransportException('Method must be a string');
            }

            if (isset($message['params']) && ! is_array($message['params'])) {
                $this->stats['protocol_errors']++;
                throw new TransportException('Params must be an array');
            }
        }

        // Validate response
        if ($isResponse) {
            // Note: Error responses can have null id if the request couldn't be parsed
            if (! array_key_exists('id', $message)) {
                $this->stats['protocol_errors']++;
                throw new TransportException('Response must have an id field');
            }

            if (isset($message['error'])) {
                $this->validateError($message['error']);
            }
        }

        // Validate id field
        if (isset($message['id'])) {
            $validId = is_string($message['id']) ||
                      is_int($message['id']) ||
                      is_null($message['id']);

            if (! $validId) {
                $this->stats['protocol_errors']++;
                throw new TransportException('Invalid id type');
            }
        }
    }

    /**
     * Validate a JSON-RPC error object.
     *
     * @param  mixed  $error  Error object to validate
     *
     * @throws TransportException If error is invalid
     */
    protected function validateError($error): void
    {
        if (! is_array($error)) {
            throw new TransportException('Error must be an object');
        }

        if (! isset($error['code']) || ! is_int($error['code'])) {
            throw new TransportException('Error code must be an integer');
        }

        if (! isset($error['message']) || ! is_string($error['message'])) {
            throw new TransportException('Error message must be a string');
        }
    }

    /**
     * Create a JSON-RPC request message.
     *
     * @param  string  $method  Method name
     * @param  array|null  $params  Method parameters
     * @param  string|int|null  $id  Request ID (null for notifications)
     * @return array Request message
     */
    public function createRequest(string $method, ?array $params = null, $id = null): array
    {
        $request = [
            'jsonrpc' => self::PROTOCOL_VERSION,
            'method' => $method,
        ];

        if ($params !== null) {
            $request['params'] = $params;
        }

        if ($id !== null) {
            $request['id'] = $id;
        }

        return $request;
    }

    /**
     * Create a JSON-RPC response message.
     *
     * @param  mixed  $result  Result data
     * @param  string|int|null  $id  Request ID
     * @return array Response message
     */
    public function createResponse($result, $id): array
    {
        return [
            'jsonrpc' => self::PROTOCOL_VERSION,
            'result' => $result,
            'id' => $id,
        ];
    }

    /**
     * Create a JSON-RPC error response.
     *
     * @param  int  $code  Error code
     * @param  string  $message  Error message
     * @param  mixed  $data  Additional error data
     * @param  string|int|null  $id  Request ID
     * @return array Error response message
     */
    public function createErrorResponse(int $code, string $message, $data = null, $id = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return [
            'jsonrpc' => self::PROTOCOL_VERSION,
            'error' => $error,
            'id' => $id,
        ];
    }

    /**
     * Get standard JSON-RPC error codes.
     *
     * @return array Error code constants
     */
    public static function getErrorCodes(): array
    {
        return [
            'PARSE_ERROR' => -32700,
            'INVALID_REQUEST' => -32600,
            'METHOD_NOT_FOUND' => -32601,
            'INVALID_PARAMS' => -32602,
            'INTERNAL_ERROR' => -32603,
            'SERVER_ERROR' => -32000, // -32000 to -32099 reserved for implementation
        ];
    }

    /**
     * Clear the message buffer.
     */
    public function clearBuffer(): void
    {
        $this->buffer = '';
    }

    /**
     * Get current buffer content (for debugging).
     *
     * @return string Buffer content
     */
    public function getBuffer(): string
    {
        return $this->buffer;
    }

    /**
     * Get buffer size.
     *
     * @return int Buffer size in bytes
     */
    public function getBufferSize(): int
    {
        return strlen($this->buffer);
    }

    /**
     * Check if buffer has data.
     *
     * @return bool True if buffer has data
     */
    public function hasBufferedData(): bool
    {
        return ! empty($this->buffer);
    }

    /**
     * Get framer statistics.
     *
     * @return array Statistics
     */
    public function getStats(): array
    {
        return array_merge($this->stats, [
            'buffer_size' => $this->getBufferSize(),
            'max_message_size' => $this->maxMessageSize,
            'use_content_length' => $this->useContentLength,
        ]);
    }

    /**
     * Reset statistics.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'messages_framed' => 0,
            'messages_parsed' => 0,
            'parse_errors' => 0,
            'protocol_errors' => 0,
            'buffer_overflows' => 0,
        ];
    }

    /**
     * Check if a message is a request.
     *
     * @param  array  $message  Message to check
     * @return bool True if message is a request
     */
    public function isRequest(array $message): bool
    {
        return isset($message['method']);
    }

    /**
     * Check if a message is a response.
     *
     * @param  array  $message  Message to check
     * @return bool True if message is a response
     */
    public function isResponse(array $message): bool
    {
        return isset($message['result']) || isset($message['error']);
    }

    /**
     * Check if a message is a notification.
     *
     * @param  array  $message  Message to check
     * @return bool True if message is a notification
     */
    public function isNotification(array $message): bool
    {
        return isset($message['method']) && ! isset($message['id']);
    }
}
