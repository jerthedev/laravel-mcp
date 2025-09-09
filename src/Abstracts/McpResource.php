<?php

namespace JTD\LaravelMCP\Abstracts;

/**
 * Abstract base class for MCP Resources.
 *
 * This class provides the foundation for creating MCP resources that can be
 * read by AI clients. Resources represent data sources that AI can access
 * within the Laravel application.
 */
abstract class McpResource
{
    /**
     * The resource URI.
     */
    protected string $uri;

    /**
     * The name of the resource.
     */
    protected string $name;

    /**
     * A description of what the resource provides.
     */
    protected string $description;

    /**
     * The MIME type of the resource content.
     */
    protected string $mimeType = 'text/plain';

    /**
     * Read the resource with optional parameters.
     *
     * @param  array  $options  Optional read parameters
     * @return array The resource content
     */
    abstract public function read(array $options = []): array;

    /**
     * Get the resource URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the resource name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the resource description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Get the resource MIME type.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the resource definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->getUri(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'mimeType' => $this->getMimeType(),
        ];
    }

    /**
     * List available resources.
     */
    public function list(): array
    {
        return [
            'resources' => [
                $this->toArray(),
            ],
        ];
    }

    /**
     * Check if the resource exists and is readable.
     */
    public function exists(): bool
    {
        // Default implementation - can be overridden
        return true;
    }

    /**
     * Get metadata about the resource.
     */
    public function getMetadata(): array
    {
        return [
            'uri' => $this->getUri(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'mimeType' => $this->getMimeType(),
            'exists' => $this->exists(),
        ];
    }

    /**
     * Validate read options.
     *
     * @param  array  $options  The options to validate
     *
     * @throws \InvalidArgumentException
     */
    public function validateOptions(array $options): bool
    {
        // Base implementation - can be overridden in child classes
        return true;
    }

    /**
     * Format the resource content for MCP response.
     *
     * @param  mixed  $content  The raw content
     */
    protected function formatContent(mixed $content): array
    {
        return [
            'contents' => [
                [
                    'uri' => $this->getUri(),
                    'mimeType' => $this->getMimeType(),
                    'text' => is_string($content) ? $content : json_encode($content),
                ],
            ],
        ];
    }

    /**
     * Create a resource error response.
     *
     * @param  string  $message  The error message
     * @param  int  $code  The error code
     */
    protected function createErrorResponse(string $message, int $code = -32603): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
    }
}
