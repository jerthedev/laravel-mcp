<?php

namespace JTD\LaravelMCP\Registry;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Pipeline\Pipeline;
use JTD\LaravelMCP\Exceptions\RegistrationErrorCodes;
use JTD\LaravelMCP\Exceptions\RegistrationException;

/**
 * Middleware execution pipeline for MCP components.
 *
 * This class manages middleware execution for MCP components,
 * providing a Laravel-like pipeline for processing requests
 * through a series of middleware layers.
 */
class MiddlewarePipeline
{
    /**
     * Container instance for dependency injection.
     */
    private Container $container;

    /**
     * Registered middleware definitions.
     */
    private array $middleware = [];

    /**
     * Global middleware applied to all components.
     */
    private array $globalMiddleware = [];

    /**
     * Middleware groups for convenient registration.
     */
    private array $middlewareGroups = [];

    /**
     * Middleware priorities for ordering.
     */
    private array $middlewarePriority = [
        'auth' => 100,
        'throttle' => 90,
        'cors' => 80,
        'validate' => 70,
        'cache' => 60,
        'log' => 50,
    ];

    /**
     * Create a new middleware pipeline.
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->loadDefaultMiddleware();
    }

    /**
     * Register a middleware.
     *
     * @param  string  $name  Middleware name
     * @param  string|Closure  $middleware  Middleware class or closure
     * @param  int|null  $priority  Optional priority for ordering
     */
    public function register(string $name, $middleware, ?int $priority = null): void
    {
        $this->middleware[$name] = [
            'handler' => $middleware,
            'priority' => $priority ?? $this->middlewarePriority[$name] ?? 50,
        ];
    }

    /**
     * Register a global middleware.
     *
     * @param  string|Closure  $middleware  Middleware to apply globally
     * @param  int  $priority  Priority for ordering
     */
    public function registerGlobal($middleware, int $priority = 50): void
    {
        $this->globalMiddleware[] = [
            'handler' => $middleware,
            'priority' => $priority,
        ];

        // Sort by priority
        usort($this->globalMiddleware, fn ($a, $b) => $b['priority'] - $a['priority']);
    }

    /**
     * Register a middleware group.
     *
     * @param  string  $name  Group name
     * @param  array  $middleware  Array of middleware names
     */
    public function registerGroup(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Execute middleware pipeline for a component.
     *
     * @param  mixed  $passable  The data to pass through the pipeline
     * @param  array  $middleware  Middleware to execute
     * @param  Closure  $destination  Final handler
     * @return mixed Result of the pipeline
     */
    public function execute($passable, array $middleware, Closure $destination): mixed
    {
        try {
            $pipeline = new Pipeline($this->container);

            // Resolve and merge middleware
            $resolvedMiddleware = $this->resolveMiddleware($middleware);

            // Add global middleware
            $resolvedMiddleware = array_merge(
                $this->getGlobalMiddlewareHandlers(),
                $resolvedMiddleware
            );

            // Execute pipeline
            return $pipeline
                ->send($passable)
                ->through($resolvedMiddleware)
                ->then($destination);

        } catch (\Throwable $e) {
            throw RegistrationException::middlewareExecutionFailed(
                implode(', ', $middleware),
                $e->getMessage(),
                ['exception' => get_class($e)]
            );
        }
    }

    /**
     * Execute middleware for a component with options.
     *
     * @param  string  $componentType  Component type
     * @param  string  $componentName  Component name
     * @param  array  $options  Component options with middleware
     * @param  Closure  $handler  Component handler
     * @return mixed Result of execution
     */
    public function executeForComponent(
        string $componentType,
        string $componentName,
        array $options,
        Closure $handler
    ): mixed {
        $middleware = $options['middleware'] ?? [];

        if (empty($middleware) && empty($this->globalMiddleware)) {
            // No middleware to execute
            return $handler();
        }

        $passable = [
            'type' => $componentType,
            'name' => $componentName,
            'options' => $options,
        ];

        return $this->execute($passable, $middleware, function ($passable) use ($handler) {
            return $handler($passable);
        });
    }

    /**
     * Resolve middleware from names to handlers.
     *
     * @param  array  $middleware  Middleware names or handlers
     * @return array Resolved middleware handlers
     */
    private function resolveMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $item) {
            if (is_string($item)) {
                // Check if it's a group
                if (isset($this->middlewareGroups[$item])) {
                    $resolved = array_merge(
                        $resolved,
                        $this->resolveMiddleware($this->middlewareGroups[$item])
                    );

                    continue;
                }

                // Check if it's a registered middleware
                if (isset($this->middleware[$item])) {
                    $resolved[] = $this->middleware[$item]['handler'];

                    continue;
                }

                // Check if it's a class name
                if (class_exists($item)) {
                    $resolved[] = $item;

                    continue;
                }

                throw new RegistrationException(
                    "Middleware '{$item}' not found",
                    RegistrationErrorCodes::MIDDLEWARE_NOT_FOUND
                );
            } elseif ($item instanceof Closure || is_callable($item)) {
                $resolved[] = $item;
            } else {
                throw new RegistrationException(
                    'Invalid middleware type: '.gettype($item),
                    RegistrationErrorCodes::MIDDLEWARE_NOT_FOUND
                );
            }
        }

        return $resolved;
    }

    /**
     * Get global middleware handlers.
     *
     * @return array Array of middleware handlers
     */
    private function getGlobalMiddlewareHandlers(): array
    {
        return array_map(fn ($m) => $m['handler'], $this->globalMiddleware);
    }

    /**
     * Load default middleware definitions.
     */
    private function loadDefaultMiddleware(): void
    {
        // Register default middleware if classes exist
        $defaults = [
            'auth' => \JTD\LaravelMCP\Http\Middleware\McpAuthMiddleware::class,
            'validate' => \JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware::class,
            'cors' => \JTD\LaravelMCP\Http\Middleware\McpCorsMiddleware::class,
            'throttle' => \JTD\LaravelMCP\Http\Middleware\McpThrottleMiddleware::class,
            'cache' => \JTD\LaravelMCP\Http\Middleware\McpCacheMiddleware::class,
            'log' => \JTD\LaravelMCP\Http\Middleware\McpLoggingMiddleware::class,
        ];

        foreach ($defaults as $name => $class) {
            if (class_exists($class)) {
                $this->register($name, $class);
            }
        }

        // Register default groups
        $this->registerGroup('api', ['throttle', 'cors']);
        $this->registerGroup('web', ['auth', 'validate']);
        $this->registerGroup('admin', ['auth', 'validate', 'log']);
    }

    /**
     * Create a middleware wrapper for a component.
     *
     * @param  mixed  $component  Component to wrap
     * @param  array  $middleware  Middleware to apply
     * @return Closure Wrapped component
     */
    public function wrap($component, array $middleware): Closure
    {
        return function (...$args) use ($component, $middleware) {
            return $this->execute(
                ['component' => $component, 'args' => $args],
                $middleware,
                function ($passable) {
                    $component = $passable['component'];
                    $args = $passable['args'];

                    if (is_callable($component)) {
                        return $component(...$args);
                    }

                    if (is_object($component) && method_exists($component, 'handle')) {
                        return $component->handle(...$args);
                    }

                    throw new RegistrationException(
                        'Component is not executable',
                        RegistrationErrorCodes::INVALID_HANDLER
                    );
                }
            );
        };
    }

    /**
     * Check if middleware exists.
     *
     * @param  string  $name  Middleware name
     * @return bool True if middleware exists
     */
    public function has(string $name): bool
    {
        return isset($this->middleware[$name]) || isset($this->middlewareGroups[$name]);
    }

    /**
     * Get all registered middleware.
     *
     * @return array All middleware definitions
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get all middleware groups.
     *
     * @return array All middleware groups
     */
    public function getGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Set middleware priority.
     *
     * @param  string  $name  Middleware name
     * @param  int  $priority  Priority value
     */
    public function setPriority(string $name, int $priority): void
    {
        $this->middlewarePriority[$name] = $priority;

        if (isset($this->middleware[$name])) {
            $this->middleware[$name]['priority'] = $priority;
        }
    }

    /**
     * Clear all middleware.
     */
    public function clear(): void
    {
        $this->middleware = [];
        $this->globalMiddleware = [];
        $this->middlewareGroups = [];
    }
}
