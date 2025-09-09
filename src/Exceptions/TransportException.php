<?php

namespace JTD\LaravelMCP\Exceptions;

/**
 * Exception for transport-related errors.
 *
 * This exception is thrown when errors occur in the transport layer,
 * such as connection failures, message transmission errors, or
 * transport-specific configuration issues.
 */
class TransportException extends McpException
{
    /**
     * Transport type that caused the error.
     */
    protected ?string $transportType = null;

    /**
     * Create a new transport exception instance.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  string|null  $transportType  Transport type
     * @param  mixed  $data  Additional error data
     * @param  array  $context  Error context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $transportType = null,
        $data = null,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $data, $context, $previous);
        $this->transportType = $transportType;
    }

    /**
     * Get the transport type that caused the error.
     */
    public function getTransportType(): ?string
    {
        return $this->transportType;
    }

    /**
     * Set the transport type.
     *
     * @param  string  $transportType  Transport type
     */
    public function setTransportType(string $transportType): self
    {
        $this->transportType = $transportType;

        return $this;
    }

    /**
     * Convert exception to array format with transport info.
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        if ($this->transportType) {
            $result['transport_type'] = $this->transportType;
        }

        return $result;
    }

    /**
     * Create a connection error exception.
     *
     * @param  string  $transportType  Transport type
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function connectionError(string $transportType, string $message = 'Connection error', $data = null): self
    {
        return new static($message, -32001, $transportType, $data);
    }

    /**
     * Create a connection timeout exception.
     *
     * @param  string  $transportType  Transport type
     * @param  int  $timeout  Timeout duration
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function connectionTimeout(string $transportType, int $timeout, $data = null): self
    {
        return new static(
            "Connection timeout after {$timeout}ms",
            -32002,
            $transportType,
            array_merge($data ?? [], ['timeout' => $timeout])
        );
    }

    /**
     * Create a message transmission error exception.
     *
     * @param  string  $transportType  Transport type
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function transmissionError(string $transportType, string $message = 'Message transmission error', $data = null): self
    {
        return new static($message, -32003, $transportType, $data);
    }

    /**
     * Create a transport configuration error exception.
     *
     * @param  string  $transportType  Transport type
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function configurationError(string $transportType, string $message = 'Transport configuration error', $data = null): self
    {
        return new static($message, -32004, $transportType, $data);
    }

    /**
     * Create a transport not supported exception.
     *
     * @param  string  $transportType  Transport type
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function notSupported(string $transportType, $data = null): self
    {
        return new static(
            "Transport type '{$transportType}' is not supported",
            -32005,
            $transportType,
            $data
        );
    }

    /**
     * Create a message framing error exception.
     *
     * @param  string  $transportType  Transport type
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function framingError(string $transportType, string $message = 'Message framing error', $data = null): self
    {
        return new static($message, -32006, $transportType, $data);
    }

    /**
     * Create a buffer overflow exception.
     *
     * @param  string  $transportType  Transport type
     * @param  int  $bufferSize  Buffer size
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function bufferOverflow(string $transportType, int $bufferSize, $data = null): self
    {
        return new static(
            "Buffer overflow, exceeded {$bufferSize} bytes",
            -32007,
            $transportType,
            array_merge($data ?? [], ['buffer_size' => $bufferSize])
        );
    }

    /**
     * Create an encoding error exception.
     *
     * @param  string  $transportType  Transport type
     * @param  string  $encoding  Encoding type
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function encodingError(string $transportType, string $encoding = 'UTF-8', $data = null): self
    {
        return new static(
            "Encoding error for {$encoding}",
            -32008,
            $transportType,
            array_merge($data ?? [], ['encoding' => $encoding])
        );
    }

    /**
     * Create a transport closed exception.
     *
     * @param  string  $transportType  Transport type
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function transportClosed(string $transportType, $data = null): self
    {
        return new static(
            'Transport connection is closed',
            -32009,
            $transportType,
            $data
        );
    }

    /**
     * Create exception from transport error.
     *
     * @param  \Throwable  $throwable  Source throwable
     * @param  string  $transportType  Transport type
     * @return static
     */
    public static function fromTransportError(\Throwable $throwable, string $transportType): self
    {
        return new static(
            $throwable->getMessage(),
            -32010,
            $transportType,
            [
                'type' => get_class($throwable),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ],
            [],
            $throwable
        );
    }
}
