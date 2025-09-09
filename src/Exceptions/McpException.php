<?php

namespace JTD\LaravelMCP\Exceptions;

use Exception;

/**
 * Base exception for MCP-related errors.
 *
 * This is the base exception class for all MCP-related exceptions in the
 * Laravel MCP package. It provides common functionality and structure
 * for error handling throughout the package.
 */
class McpException extends Exception
{
    /**
     * Additional error data.
     *
     * @var mixed
     */
    protected $data;

    /**
     * Error context information.
     */
    protected array $context = [];

    /**
     * Create a new MCP exception instance.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Error code (JSON-RPC error codes)
     * @param  mixed  $data  Additional error data
     * @param  array  $context  Error context
     * @param  \Throwable|null  $previous  Previous exception
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        $data = null,
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
        $this->context = $context;
    }

    /**
     * Get additional error data.
     *
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set additional error data.
     *
     * @param  mixed  $data  Error data
     */
    public function setData($data): self
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get error context.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set error context.
     *
     * @param  array  $context  Error context
     */
    public function setContext(array $context): self
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Add context item.
     *
     * @param  string  $key  Context key
     * @param  mixed  $value  Context value
     */
    public function addContext(string $key, $value): self
    {
        $this->context[$key] = $value;

        return $this;
    }

    /**
     * Convert exception to array format.
     */
    public function toArray(): array
    {
        $result = [
            'error' => [
                'code' => $this->getCode(),
                'message' => $this->getMessage(),
            ],
            'timestamp' => now()->toISOString(),
        ];

        if ($this->data !== null) {
            $result['error']['data'] = $this->data;
        }

        if (! empty($this->context)) {
            $result['context'] = $this->context;
        }

        if ($this->getPrevious()) {
            $result['previous'] = [
                'type' => get_class($this->getPrevious()),
                'message' => $this->getPrevious()->getMessage(),
                'code' => $this->getPrevious()->getCode(),
            ];
        }

        return $result;
    }

    /**
     * Convert exception to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT);
    }

    /**
     * Get error type based on code.
     */
    public function getErrorType(): string
    {
        $code = $this->getCode();

        // Check specific JSON-RPC error codes first
        switch ($code) {
            case -32700:
                return 'Parse Error';
            case -32600:
                return 'Invalid Request';
            case -32601:
                return 'Method Not Found';
            case -32602:
                return 'Invalid Params';
            case -32603:
                return 'Internal Error';
        }

        // Check server error range
        if ($code >= -32099 && $code <= -32000) {
            return 'Server Error';
        }

        // Check general JSON-RPC error range
        if ($code >= -32768 && $code <= -32000) {
            return 'JSON-RPC Error';
        }

        // Default to application error
        return 'Application Error';
    }

    /**
     * Check if this is a client error (4xx equivalent).
     */
    public function isClientError(): bool
    {
        $code = $this->getCode();

        return $code === -32600 || $code === -32602 || $code === -32601;
    }

    /**
     * Check if this is a server error (5xx equivalent).
     */
    public function isServerError(): bool
    {
        $code = $this->getCode();

        return $code === -32603 || ($code >= -32099 && $code <= -32000);
    }

    /**
     * Create a parse error exception.
     *
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function parseError(string $message = 'Parse error', $data = null): self
    {
        return new static($message, -32700, $data);
    }

    /**
     * Create an invalid request exception.
     *
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidRequest(string $message = 'Invalid Request', $data = null): self
    {
        return new static($message, -32600, $data);
    }

    /**
     * Create a method not found exception.
     *
     * @param  string  $method  Method name
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function methodNotFound(string $method, $data = null): self
    {
        return new static("Method not found: {$method}", -32601, $data);
    }

    /**
     * Create an invalid params exception.
     *
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function invalidParams(string $message = 'Invalid params', $data = null): self
    {
        return new static($message, -32602, $data);
    }

    /**
     * Create an internal error exception.
     *
     * @param  string  $message  Custom message
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function internalError(string $message = 'Internal error', $data = null): self
    {
        return new static($message, -32603, $data);
    }

    /**
     * Create an application error exception.
     *
     * @param  string  $message  Error message
     * @param  int  $code  Application-specific error code
     * @param  mixed  $data  Additional data
     * @return static
     */
    public static function applicationError(string $message, int $code = -32000, $data = null): self
    {
        return new static($message, $code, $data);
    }

    /**
     * Create exception from throwable.
     *
     * @param  \Throwable  $throwable  Source throwable
     * @param  int|null  $code  Override error code
     * @return static
     */
    public static function fromThrowable(\Throwable $throwable, ?int $code = null): self
    {
        return new static(
            $throwable->getMessage(),
            $code ?? -32603,
            [
                'type' => get_class($throwable),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $throwable->getTraceAsString(),
            ],
            [],
            $throwable
        );
    }
}
