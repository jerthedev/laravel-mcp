<?php

namespace JTD\LaravelMCP\Validation;

class McpValidationException extends \Exception
{
    private array $errors;

    public function __construct(string $message, array $errors = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->errors = $errors;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
