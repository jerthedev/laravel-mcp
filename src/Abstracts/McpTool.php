<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Abstract base class for MCP Tools.
 *
 * This class provides the foundation for creating MCP tools that can be
 * executed by AI clients. Tools are functions that AI can call to perform
 * specific actions within the Laravel application.
 */
abstract class McpTool
{
    use HandlesMcpRequests, ManagesCapabilities, ValidatesParameters;

    /**
     * Laravel container instance.
     */
    protected Container $container;

    /**
     * Laravel validation factory.
     */
    protected ValidationFactory $validator;

    /**
     * The name of the tool.
     */
    protected string $name;

    /**
     * A description of what the tool does.
     */
    protected string $description;

    /**
     * The JSON Schema for the tool's input parameters.
     */
    protected array $parameterSchema = [];

    /**
     * Middleware to apply to this tool.
     */
    protected array $middleware = [];

    /**
     * Whether this tool requires authentication.
     */
    protected bool $requiresAuth = false;

    /**
     * Create a new tool instance.
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(ValidationFactory::class);

        $this->boot();
    }

    /**
     * Boot the tool. Override in child classes for initialization.
     */
    protected function boot(): void
    {
        // Override in child classes
    }

    /**
     * Get the tool name.
     */
    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    /**
     * Get the tool description.
     */
    public function getDescription(): string
    {
        return $this->description ?? 'MCP Tool';
    }

    /**
     * Get the tool's input schema.
     */
    public function getInputSchema(): array
    {
        // Clean the parameter schema for JSON Schema compliance
        $cleanProperties = [];
        foreach ($this->getParameterSchema() as $key => $schema) {
            $cleanProperties[$key] = $schema;
            // Remove 'required' field from individual properties - it belongs in root required array
            unset($cleanProperties[$key]['required']);
        }

        return [
            'type' => 'object',
            'properties' => $cleanProperties,
            'required' => $this->getRequiredParameters(),
            'additionalProperties' => false,
            '$schema' => 'http://json-schema.org/draft-07/schema#',
        ];
    }

    /**
     * Get the parameter schema.
     */
    protected function getParameterSchema(): array
    {
        return $this->parameterSchema;
    }

    /**
     * Get the required parameters.
     */
    protected function getRequiredParameters(): array
    {
        return array_keys(array_filter($this->parameterSchema, function ($schema) {
            return $schema['required'] ?? false;
        }));
    }

    /**
     * Execute the tool with the given arguments.
     *
     * @param  array  $parameters  The tool arguments
     * @return mixed The tool execution result
     */
    public function execute(array $parameters): mixed
    {
        // 1. Authorize the request
        if (! $this->authorize($parameters)) {
            throw new UnauthorizedHttpException('', 'Unauthorized tool execution');
        }

        // 2. Validate parameters
        $validatedParams = $this->validateParameters($parameters);

        // 3. Apply middleware
        foreach ($this->middleware as $middleware) {
            $validatedParams = $this->applyMiddleware($middleware, $validatedParams);
        }

        // 4. Execute the tool
        return $this->handle($validatedParams);
    }

    /**
     * Handle the tool execution. Override in child classes.
     */
    abstract protected function handle(array $parameters): mixed;

    /**
     * Authorize tool execution.
     */
    protected function authorize(array $parameters): bool
    {
        if (! $this->requiresAuth) {
            return true;
        }

        // Default authorization logic - can be overridden in child classes
        return true;
    }

    /**
     * Generate tool name from class name.
     */
    private function generateNameFromClass(): string
    {
        $className = class_basename($this);

        return Str::snake(str_replace('Tool', '', $className));
    }

    /**
     * Resolve a dependency from the Laravel container.
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Resolve a dependency from the Laravel container.
     */
    protected function resolve(string $abstract)
    {
        return $this->container->make($abstract);
    }

    /**
     * Get the tool definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'inputSchema' => $this->getInputSchema(),
        ];
    }
}
