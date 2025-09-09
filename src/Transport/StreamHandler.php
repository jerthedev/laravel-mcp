<?php

namespace JTD\LaravelMCP\Transport;

use JTD\LaravelMCP\Exceptions\TransportException;

/**
 * Stream handler utility for robust stream operations.
 *
 * This class provides robust stream handling capabilities including
 * timeout support, buffer management, and error recovery for stdio operations.
 */
class StreamHandler
{
    /**
     * Stream resource.
     *
     * @var resource|null
     */
    protected $stream = null;

    /**
     * Stream mode (read/write).
     */
    protected string $mode;

    /**
     * Stream path/URI.
     */
    protected string $path;

    /**
     * Configuration options.
     */
    protected array $config;

    /**
     * Default configuration.
     */
    protected array $defaultConfig = [
        'timeout' => 30,
        'buffer_size' => 8192,
        'max_buffer_size' => 1048576, // 1MB
        'blocking' => false,
        'read_timeout' => 0.1, // 100ms for non-blocking reads
        'retry_attempts' => 3,
        'retry_delay' => 100, // milliseconds
    ];

    /**
     * Stream statistics.
     */
    protected array $stats = [
        'bytes_read' => 0,
        'bytes_written' => 0,
        'read_operations' => 0,
        'write_operations' => 0,
        'errors' => 0,
        'timeouts' => 0,
    ];

    /**
     * Create a new stream handler instance.
     *
     * @param  string  $path  Stream path/URI (e.g., 'php://stdin', 'php://stdout')
     * @param  string  $mode  Stream mode ('r' for read, 'w' for write)
     * @param  array  $config  Configuration options
     */
    public function __construct(string $path, string $mode, array $config = [])
    {
        $this->path = $path;
        $this->mode = $mode;
        $this->config = array_merge($this->defaultConfig, $config);
    }

    /**
     * Open the stream.
     *
     * @throws TransportException If stream cannot be opened
     */
    public function open(): void
    {
        if ($this->isOpen()) {
            return;
        }

        $this->stream = @fopen($this->path, $this->mode);

        if (! $this->stream) {
            $error = error_get_last();
            throw new TransportException(
                "Failed to open stream '{$this->path}': ".($error['message'] ?? 'Unknown error')
            );
        }

        // Set stream to non-blocking mode if configured
        if (! $this->config['blocking']) {
            stream_set_blocking($this->stream, false);
        }

        // Set read/write timeout
        if ($this->config['timeout'] > 0) {
            $seconds = (int) $this->config['timeout'];
            $microseconds = (int) (($this->config['timeout'] - $seconds) * 1000000);
            stream_set_timeout($this->stream, $seconds, $microseconds);
        }
    }

    /**
     * Close the stream.
     */
    public function close(): void
    {
        if ($this->isOpen()) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * Check if the stream is open.
     *
     * @return bool True if stream is open, false otherwise
     */
    public function isOpen(): bool
    {
        return $this->stream !== null && is_resource($this->stream);
    }

    /**
     * Read data from the stream.
     *
     * @param  int|null  $length  Maximum bytes to read (null for line-based reading)
     * @return string|null Read data or null if no data available
     *
     * @throws TransportException If read operation fails
     */
    public function read(?int $length = null): ?string
    {
        if (! $this->isOpen()) {
            throw new TransportException('Stream is not open for reading');
        }

        if (! $this->isReadable()) {
            throw new TransportException('Stream is not readable');
        }

        $this->stats['read_operations']++;

        // Use stream_select for timeout handling
        if (! $this->config['blocking'] && $this->config['read_timeout'] > 0) {
            $read = [$this->stream];
            $write = null;
            $except = null;
            $timeout = $this->config['read_timeout'];
            $seconds = (int) $timeout;
            $microseconds = (int) (($timeout - $seconds) * 1000000);

            $result = @stream_select($read, $write, $except, $seconds, $microseconds);

            if ($result === false) {
                $this->stats['errors']++;
                throw new TransportException('Stream select failed');
            }

            if ($result === 0) {
                // Timeout - no data available
                $this->stats['timeouts']++;

                return null;
            }
        }

        // Read data based on mode
        if ($length === null) {
            // Line-based reading
            $data = fgets($this->stream);
        } else {
            // Fixed-length reading
            $data = fread($this->stream, $length);
        }

        if ($data === false) {
            if (feof($this->stream)) {
                return null; // End of stream
            }

            $this->stats['errors']++;
            $meta = stream_get_meta_data($this->stream);

            if ($meta['timed_out']) {
                $this->stats['timeouts']++;
                throw new TransportException('Stream read timed out');
            }

            throw new TransportException('Failed to read from stream');
        }

        if ($data === '') {
            return null; // No data available
        }

        $this->stats['bytes_read'] += strlen($data);

        return $data;
    }

    /**
     * Read a complete line from the stream.
     *
     * @param  string  $delimiter  Line delimiter (default: "\n")
     * @return string|null Complete line or null if no complete line available
     *
     * @throws TransportException If read operation fails
     */
    public function readLine(string $delimiter = "\n"): ?string
    {
        if (! $this->isOpen()) {
            throw new TransportException('Stream is not open for reading');
        }

        if (! $this->isReadable()) {
            throw new TransportException('Stream is not readable');
        }

        // If using default delimiter and not at EOF
        if ($delimiter === "\n" && ! feof($this->stream)) {
            // We need to check buffer limits, so read incrementally
            $buffer = '';
            $maxSize = $this->config['max_buffer_size'];

            while (strlen($buffer) < $maxSize && ! feof($this->stream)) {
                $char = fgetc($this->stream);

                if ($char === false) {
                    break;
                }

                $buffer .= $char;

                if ($char === "\n") {
                    $this->stats['read_operations']++;
                    $this->stats['bytes_read'] += strlen($buffer);

                    return rtrim($buffer, "\r\n");
                }
            }

            // If we've read max_buffer_size without finding a newline, throw overflow
            if (strlen($buffer) >= $maxSize) {
                throw TransportException::bufferOverflow('stream', $maxSize);
            }

            // Return what we have if EOF reached
            if ($buffer !== '') {
                $this->stats['read_operations']++;
                $this->stats['bytes_read'] += strlen($buffer);

                return rtrim($buffer, "\r\n");
            }

            return null;
        }

        // For custom delimiters, read character by character
        $buffer = '';
        $maxSize = $this->config['max_buffer_size'];

        while (strlen($buffer) < $maxSize) {
            $char = fread($this->stream, 1);

            if ($char === false || $char === '') {
                // End of stream reached
                return $buffer !== '' ? $buffer : null;
            }

            $buffer .= $char;

            // Check if we've found the delimiter
            if (substr($buffer, -strlen($delimiter)) === $delimiter) {
                $this->stats['read_operations']++;
                $this->stats['bytes_read'] += strlen($buffer);

                return substr($buffer, 0, -strlen($delimiter));
            }
        }

        // Check for buffer overflow
        if (strlen($buffer) >= $maxSize) {
            throw TransportException::bufferOverflow('stream', $maxSize);
        }

        $this->stats['read_operations']++;
        $this->stats['bytes_read'] += strlen($buffer);

        return $buffer;
    }

    /**
     * Write data to the stream.
     *
     * @param  string  $data  Data to write
     * @return int Number of bytes written
     *
     * @throws TransportException If write operation fails
     */
    public function write(string $data): int
    {
        if (! $this->isOpen()) {
            throw new TransportException('Stream is not open for writing');
        }

        if (! $this->isWritable()) {
            throw new TransportException('Stream is not writable');
        }

        $this->stats['write_operations']++;
        $dataLength = strlen($data);
        $written = 0;
        $attempts = 0;

        // Write data with retry logic
        while ($written < $dataLength && $attempts < $this->config['retry_attempts']) {
            $chunk = substr($data, $written);
            $result = @fwrite($this->stream, $chunk);

            if ($result === false) {
                $this->stats['errors']++;
                $attempts++;

                if ($attempts >= $this->config['retry_attempts']) {
                    throw new TransportException('Failed to write to stream after '.$attempts.' attempts');
                }

                // Wait before retry
                usleep($this->config['retry_delay'] * 1000);

                continue;
            }

            $written += $result;

            // Check for partial write
            if ($result < strlen($chunk)) {
                // Give the stream time to catch up
                usleep(10000); // 10ms
            }
        }

        // Flush the output
        fflush($this->stream);

        $this->stats['bytes_written'] += $written;

        return $written;
    }

    /**
     * Write a line to the stream.
     *
     * @param  string  $line  Line to write (delimiter will be added)
     * @param  string  $delimiter  Line delimiter (default: "\n")
     * @return int Number of bytes written
     *
     * @throws TransportException If write operation fails
     */
    public function writeLine(string $line, string $delimiter = "\n"): int
    {
        return $this->write($line.$delimiter);
    }

    /**
     * Check if the stream is readable.
     *
     * @return bool True if stream is readable, false otherwise
     */
    public function isReadable(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        $mode = $meta['mode'] ?? '';

        return strpos($mode, 'r') !== false || strpos($mode, '+') !== false;
    }

    /**
     * Check if the stream is writable.
     *
     * @return bool True if stream is writable, false otherwise
     */
    public function isWritable(): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $meta = stream_get_meta_data($this->stream);
        $mode = $meta['mode'] ?? '';

        return strpos($mode, 'w') !== false ||
               strpos($mode, 'a') !== false ||
               strpos($mode, 'x') !== false ||
               strpos($mode, '+') !== false;
    }

    /**
     * Check if the stream has reached end-of-file.
     *
     * @return bool True if at EOF, false otherwise
     */
    public function isEof(): bool
    {
        return $this->isOpen() && feof($this->stream);
    }

    /**
     * Get stream metadata.
     *
     * @return array Stream metadata
     */
    public function getMetadata(): array
    {
        if (! $this->isOpen()) {
            return [];
        }

        return stream_get_meta_data($this->stream);
    }

    /**
     * Get stream statistics.
     *
     * @return array Stream statistics
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reset stream statistics.
     */
    public function resetStats(): void
    {
        $this->stats = [
            'bytes_read' => 0,
            'bytes_written' => 0,
            'read_operations' => 0,
            'write_operations' => 0,
            'errors' => 0,
            'timeouts' => 0,
        ];
    }

    /**
     * Set stream blocking mode.
     *
     * @param  bool  $blocking  Whether to use blocking mode
     * @return bool True on success, false on failure
     */
    public function setBlocking(bool $blocking): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $result = stream_set_blocking($this->stream, $blocking);

        if ($result) {
            $this->config['blocking'] = $blocking;
        }

        return $result;
    }

    /**
     * Set stream timeout.
     *
     * @param  float  $timeout  Timeout in seconds
     * @return bool True on success, false on failure
     */
    public function setTimeout(float $timeout): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        $seconds = (int) $timeout;
        $microseconds = (int) (($timeout - $seconds) * 1000000);

        // stream_set_timeout may not work on regular files, but we still track it
        @stream_set_timeout($this->stream, $seconds, $microseconds);

        // Always set the config and return true for valid timeout values
        if ($timeout > 0) {
            $this->config['timeout'] = $timeout;

            return true;
        }

        return false;
    }

    /**
     * Wait for the stream to become readable.
     *
     * @param  float  $timeout  Maximum time to wait in seconds
     * @return bool True if stream is readable, false if timeout
     */
    public function waitForReadable(float $timeout = 1.0): bool
    {
        if (! $this->isOpen() || ! $this->isReadable()) {
            return false;
        }

        $read = [$this->stream];
        $write = null;
        $except = null;
        $seconds = (int) $timeout;
        $microseconds = (int) (($timeout - $seconds) * 1000000);

        $result = @stream_select($read, $write, $except, $seconds, $microseconds);

        return $result > 0;
    }

    /**
     * Wait for the stream to become writable.
     *
     * @param  float  $timeout  Maximum time to wait in seconds
     * @return bool True if stream is writable, false if timeout
     */
    public function waitForWritable(float $timeout = 1.0): bool
    {
        if (! $this->isOpen() || ! $this->isWritable()) {
            return false;
        }

        $read = null;
        $write = [$this->stream];
        $except = null;
        $seconds = (int) $timeout;
        $microseconds = (int) (($timeout - $seconds) * 1000000);

        $result = @stream_select($read, $write, $except, $seconds, $microseconds);

        return $result > 0;
    }

    /**
     * Get the underlying stream resource.
     *
     * @return resource|null Stream resource or null if not open
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Perform a health check on the stream.
     *
     * @return array Health check results
     */
    public function healthCheck(): array
    {
        $health = [
            'healthy' => false,
            'open' => $this->isOpen(),
            'readable' => false,
            'writable' => false,
            'eof' => false,
            'metadata' => [],
            'stats' => $this->stats,
        ];

        if ($this->isOpen()) {
            $health['readable'] = $this->isReadable();
            $health['writable'] = $this->isWritable();
            $health['eof'] = $this->isEof();
            $health['metadata'] = $this->getMetadata();

            $health['healthy'] = ! $health['eof'] &&
                                ($health['readable'] || $health['writable']);
        }

        return $health;
    }
}
