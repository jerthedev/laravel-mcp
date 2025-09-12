<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Exceptions;

use Exception;

/**
 * MCP Validation Exception
 *
 * A simple validation exception that doesn't depend on Laravel's facades
 */
class McpValidationException extends Exception
{
    /**
     * @var array The validation errors
     */
    protected array $errors;

    /**
     * Create a new validation exception
     */
    public function __construct(array $errors, string $message = 'The given data was invalid.')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * Create an exception with messages
     */
    public static function withMessages(array $messages): self
    {
        return new self($messages);
    }

    /**
     * Get the validation errors
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error message
     */
    public function getFirstError(): string
    {
        foreach ($this->errors as $fieldErrors) {
            if (is_array($fieldErrors) && ! empty($fieldErrors)) {
                return $fieldErrors[0];
            }
            if (is_string($fieldErrors)) {
                return $fieldErrors;
            }
        }

        return 'Validation failed';
    }
}
