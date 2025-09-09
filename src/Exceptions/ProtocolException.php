<?php

namespace JTD\LaravelMCP\Exceptions;

/**
 * Exception for protocol-related errors.
 *
 * This exception is thrown when errors occur in the MCP protocol layer,
 * such as invalid message formats, unsupported protocol versions,
 * capability negotiation failures, or JSON-RPC violations.
 */
class ProtocolException extends McpException
{
    /**
     * Protocol version that caused the error.
     */
    protected ?string $protocolVersion = null;

    /**
     * Method name that caused the error.
     */
    protected ?string $method = null;

    /**
     * Create a new protocol exception instance.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code
     * @param  string|null  $method  Method name
     * @param  string|null  $protocolVersion  Protocol version
     * @param  mixed  $data  Additional error data
     * @param  array  $context  Error context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?string $method = null,
        ?string $protocolVersion = null,
        $data = null,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $data, $context, $previous);
        $this->method = $method;
        $this->protocolVersion = $protocolVersion;
    }

    /**
     * Get the protocol version.
     */
    public function getProtocolVersion(): ?string
    {
        return $this->protocolVersion;
    }

    /**
     * Set the protocol version.
     *
     * @param  string  $protocolVersion  Protocol version
     */
    public function setProtocolVersion(string $protocolVersion): self
    {
        $this->protocolVersion = $protocolVersion;

        return $this;
    }

    /**
     * Get the method name.
     */
    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * Set the method name.
     *
     * @param  string  $method  Method name
     */
    public function setMethod(string $method): self
    {
        $this->method = $method;

        return $this;
    }

    /**
     * Convert exception to array format with protocol info.
     */
    public function toArray(): array
    {
        $result = parent::toArray();

        if ($this->protocolVersion) {
            $result['protocol_version'] = $this->protocolVersion;
        }

        if ($this->method) {
            $result['method'] = $this->method;
        }

        return $result;
    }

    /**
     * Create an unsupported protocol version exception.
     *
     * @param  string  $version  Unsupported version
     * @param  array  $supportedVersions  Supported versions
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function unsupportedVersion(string $version, array $supportedVersions = [], $data = null): self
    {
        $message = "Unsupported protocol version: {$version}";
        if (! empty($supportedVersions)) {
            $message .= '. Supported versions: '.implode(', ', $supportedVersions);
        }

        return new static(
            $message,
            -32020,
            null,
            $version,
            array_merge($data ?? [], ['supported_versions' => $supportedVersions])
        );
    }

    /**
     * Create a capability negotiation failure exception.
     *
     * @param  string  $capability  Capability that failed
     * @param  string  $reason  Failure reason
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function capabilityNegotiationFailed(string $capability, string $reason = '', $data = null): self
    {
        $message = "Capability negotiation failed for '{$capability}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        return new static(
            $message,
            -32021,
            null,
            null,
            array_merge($data ?? [], ['capability' => $capability, 'reason' => $reason])
        );
    }

    /**
     * Create an invalid JSON-RPC format exception.
     *
     * @param  string  $reason  Specific format issue
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidJsonRpc(string $reason = 'Invalid JSON-RPC format', $data = null): self
    {
        return new static($reason, -32600, null, null, $data);
    }

    /**
     * Create a message too large exception.
     *
     * @param  int  $size  Message size
     * @param  int  $maxSize  Maximum allowed size
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function messageTooLarge(int $size, int $maxSize, $data = null): self
    {
        return new static(
            "Message size {$size} bytes exceeds maximum {$maxSize} bytes",
            -32022,
            null,
            null,
            array_merge($data ?? [], ['size' => $size, 'max_size' => $maxSize])
        );
    }

    /**
     * Create an unsupported method exception.
     *
     * @param  string  $method  Method name
     * @param  array  $supportedMethods  Supported methods
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function unsupportedMethod(string $method, array $supportedMethods = [], $data = null): self
    {
        $message = "Unsupported method: {$method}";
        if (! empty($supportedMethods)) {
            $message .= '. Supported methods: '.implode(', ', $supportedMethods);
        }

        return new static(
            $message,
            -32601,
            $method,
            null,
            array_merge($data ?? [], ['supported_methods' => $supportedMethods])
        );
    }

    /**
     * Create an initialization required exception.
     *
     * @param  string  $method  Method that requires initialization
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function initializationRequired(string $method, $data = null): self
    {
        return new static(
            "Method '{$method}' requires server initialization",
            -32023,
            $method,
            null,
            $data
        );
    }

    /**
     * Create a server already initialized exception.
     *
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function alreadyInitialized($data = null): self
    {
        return new static(
            'Server is already initialized',
            -32024,
            'initialize',
            null,
            $data
        );
    }

    /**
     * Create a request timeout exception.
     *
     * @param  string  $method  Method name
     * @param  int  $timeout  Timeout duration
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function requestTimeout(string $method, int $timeout, $data = null): self
    {
        return new static(
            "Request timeout for method '{$method}' after {$timeout}ms",
            -32025,
            $method,
            null,
            array_merge($data ?? [], ['timeout' => $timeout])
        );
    }

    /**
     * Create a concurrent request limit exception.
     *
     * @param  int  $limit  Request limit
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function concurrentRequestLimit(int $limit, $data = null): self
    {
        return new static(
            "Concurrent request limit of {$limit} exceeded",
            -32026,
            null,
            null,
            array_merge($data ?? [], ['limit' => $limit])
        );
    }

    /**
     * Create an invalid message ID exception.
     *
     * @param  mixed  $id  Invalid ID
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidMessageId($id, $data = null): self
    {
        return new static(
            'Invalid message ID: '.json_encode($id),
            -32027,
            null,
            null,
            array_merge($data ?? [], ['invalid_id' => $id])
        );
    }

    /**
     * Create a duplicate request ID exception.
     *
     * @param  mixed  $id  Duplicate ID
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function duplicateRequestId($id, $data = null): self
    {
        return new static(
            'Duplicate request ID: '.json_encode($id),
            -32028,
            null,
            null,
            array_merge($data ?? [], ['duplicate_id' => $id])
        );
    }

    /**
     * Create exception from protocol validation error.
     *
     * @param  string  $message  Error message
     * @param  string|null  $method  Method name
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function validationError(string $message, ?string $method = null, $data = null): self
    {
        return new static($message, -32602, $method, null, $data);
    }
}
