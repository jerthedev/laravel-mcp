<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use JTD\LaravelMCP\Traits\ValidatesParameters;

/**
 * Abstract base class providing shared functionality for all MCP components.
 *
 * This class provides the foundation for MCP Tools, Resources, and Prompts,
 * including Laravel integration, dependency injection, validation, and
 * authorization support.
 */
abstract class BaseComponent
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
     * The component name.
     */
    protected string $name;

    /**
     * The component description.
     */
    protected string $description;

    /**
     * Middleware to apply to this component.
     */
    protected array $middleware = [];

    /**
     * Whether this component requires authentication.
     */
    protected bool $requiresAuth = false;

    /**
     * Create a new component instance.
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(ValidationFactory::class);

        $this->boot();
    }

    /**
     * Boot the component. Override in child classes for initialization.
     */
    protected function boot(): void
    {
        // Override in child classes
    }

    /**
     * Get the component name.
     */
    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    /**
     * Get the component description.
     */
    public function getDescription(): string
    {
        return $this->description ?? 'MCP Component';
    }

    /**
     * Get the component middleware.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Check if component requires authentication.
     */
    public function requiresAuth(): bool
    {
        return $this->requiresAuth;
    }

    /**
     * Authorize access to this component.
     *
     * @param  array  $params  Request parameters
     * @param  string|null  $action  Specific action being authorized
     */
    protected function authorize(array $params = [], ?string $action = null): bool
    {
        if (! $this->requiresAuth) {
            return true;
        }

        // Default authorization logic - can be overridden in child classes
        return true;
    }

    /**
     * Generate component name from class name.
     */
    private function generateNameFromClass(): string
    {
        $className = class_basename($this);

        // Remove common suffixes
        $suffixes = ['Tool', 'Resource', 'Prompt', 'Component'];
        foreach ($suffixes as $suffix) {
            if (Str::endsWith($className, $suffix)) {
                $className = str_replace($suffix, '', $className);
                break;
            }
        }

        return Str::snake($className);
    }

    /**
     * Resolve a dependency from the Laravel container.
     *
     * @return mixed
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Resolve a dependency from the Laravel container.
     *
     * @return mixed
     */
    protected function resolve(string $abstract)
    {
        return $this->container->make($abstract);
    }

    /**
     * Apply middleware to the component request.
     */
    protected function applyComponentMiddleware(array $params): array
    {
        foreach ($this->middleware as $middleware) {
            $params = $this->applyMiddleware($middleware, $params);
        }

        return $params;
    }

    /**
     * Log component activity.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if (config('laravel-mcp.logging.enabled', false)) {
            logger()->{$level}($message, array_merge([
                'component' => static::class,
                'name' => $this->getName(),
            ], $context));
        }
    }

    /**
     * Fire a Laravel event.
     */
    protected function fireEvent(string $event, array $payload = []): void
    {
        if (function_exists('event')) {
            event($event, array_merge(['component' => $this], $payload));
        }
    }

    /**
     * Get metadata about this component.
     */
    public function getMetadata(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'class' => static::class,
            'requiresAuth' => $this->requiresAuth(),
            'middleware' => $this->getMiddleware(),
            'capabilities' => $this->getCapabilities(),
        ];
    }

    /**
     * Convert the component to an array representation.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
        ];
    }
}
