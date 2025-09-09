<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Support\Str;

/**
 * Routing patterns and conventions for MCP components.
 *
 * This class defines standardized routing patterns for MCP components,
 * handles route naming conventions, supports route caching compatibility,
 * and provides utilities for converting component names to route patterns.
 *
 * Key features:
 * - Standardized route patterns for tools, resources, and prompts
 * - Route naming conventions following Laravel patterns (mcp.tools.calculator)
 * - Middleware assignment patterns for different component types
 * - Route constraint definitions for URL parameters
 * - Component name normalization for URL safety
 * - Route caching support and optimization utilities
 * - Pattern matching utilities for route generation
 *
 * Integration:
 * - Works with RouteRegistrar for automatic route registration
 * - Integrates with Laravel's routing system via service provider
 * - Supports HTTP route generation for MCP protocol endpoints
 * - Compatible with Laravel route caching (php artisan route:cache)
 */
class RoutingPatterns
{
    /**
     * Default route patterns for MCP components.
     */
    protected array $patterns = [
        'tools' => [
            'prefix' => 'tools',
            'pattern' => 'tools/{tool}',
            'methods' => ['POST'],
            'name_pattern' => 'mcp.tools.{name}',
            'controller_action' => 'executeTool',
        ],
        'resources' => [
            'prefix' => 'resources',
            'pattern' => 'resources/{resource}',
            'methods' => ['GET', 'POST'],
            'name_pattern' => 'mcp.resources.{name}',
            'controller_action' => 'accessResource',
        ],
        'prompts' => [
            'prefix' => 'prompts',
            'pattern' => 'prompts/{prompt}',
            'methods' => ['GET', 'POST'],
            'name_pattern' => 'mcp.prompts.{name}',
            'controller_action' => 'renderPrompt',
        ],
    ];

    /**
     * Standard middleware patterns for different component types.
     */
    protected array $middlewarePatterns = [
        'tools' => ['mcp.cors', 'mcp.auth', 'mcp.validate'],
        'resources' => ['mcp.cors', 'mcp.auth', 'mcp.cache'],
        'prompts' => ['mcp.cors', 'mcp.auth'],
        'common' => ['mcp.cors'],
        'authenticated' => ['mcp.cors', 'mcp.auth'],
        'public' => ['mcp.cors'],
    ];

    /**
     * Route parameter constraints for MCP components.
     */
    protected array $constraints = [
        'tool' => '[a-zA-Z0-9_\-\.]+',
        'resource' => '[a-zA-Z0-9_\-\.\/]+',
        'prompt' => '[a-zA-Z0-9_\-\.]+',
        'id' => '[0-9]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
    ];

    /**
     * Route caching configuration.
     */
    protected array $cacheConfig = [
        'enabled' => true,
        'key_prefix' => 'mcp_routes',
        'cache_routes' => true,
        'cache_patterns' => true,
    ];

    /**
     * Route naming conventions.
     */
    protected array $namingConventions = [
        'separator' => '.',
        'prefix' => 'mcp',
        'component_type_singular' => [
            'tools' => 'tool',
            'resources' => 'resource',
            'prompts' => 'prompt',
        ],
    ];

    /**
     * Get route pattern for a specific component type.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @return array|null Route pattern configuration
     */
    public function getPattern(string $type): ?array
    {
        return $this->patterns[$type] ?? null;
    }

    /**
     * Get all route patterns.
     *
     * @return array All route patterns
     */
    public function getAllPatterns(): array
    {
        return $this->patterns;
    }

    /**
     * Generate route name for a component.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  string  $name  Component name
     * @param  string|null  $action  Optional action name
     * @return string Generated route name
     */
    public function generateRouteName(string $type, string $name, ?string $action = null): string
    {
        $basePattern = $this->patterns[$type]['name_pattern'] ?? 'mcp.{type}.{name}';

        // Replace placeholders
        $routeName = str_replace(
            ['{type}', '{name}'],
            [$type, $this->normalizeComponentName($name)],
            $basePattern
        );

        // Add action suffix if provided
        if ($action) {
            $routeName .= '.'.$this->normalizeActionName($action);
        }

        return $routeName;
    }

    /**
     * Generate route URI for a component.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  string  $name  Component name
     * @param  array  $parameters  Additional route parameters
     * @return string Generated route URI
     */
    public function generateRouteUri(string $type, string $name, array $parameters = []): string
    {
        $pattern = $this->patterns[$type]['pattern'] ?? '{type}/{name}';

        // Get the singular form for parameter binding
        $singularType = $this->namingConventions['component_type_singular'][$type] ?? $type;

        // Replace the type-specific parameter placeholder
        $uri = str_replace('{'.$singularType.'}', $this->normalizeComponentName($name), $pattern);

        // Replace additional parameters
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{'.$key.'}', $value, $uri);
        }

        return $uri;
    }

    /**
     * Get middleware for a component type.
     *
     * @param  string  $type  Component type
     * @param  array  $additional  Additional middleware to include
     * @return array Middleware array
     */
    public function getMiddleware(string $type, array $additional = []): array
    {
        $baseMiddleware = $this->middlewarePatterns[$type] ?? $this->middlewarePatterns['common'];

        return array_unique(array_merge($baseMiddleware, $additional));
    }

    /**
     * Get middleware pattern by name.
     *
     * @param  string  $pattern  Pattern name (tools, resources, prompts, common, authenticated, public)
     * @return array Middleware array
     */
    public function getMiddlewarePattern(string $pattern): array
    {
        return $this->middlewarePatterns[$pattern] ?? [];
    }

    /**
     * Get route constraint for a parameter type.
     *
     * @param  string  $type  Parameter type
     * @return string|null Constraint pattern
     */
    public function getConstraint(string $type): ?string
    {
        return $this->constraints[$type] ?? null;
    }

    /**
     * Get all route constraints.
     *
     * @return array All constraints
     */
    public function getAllConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * Check if route caching is enabled.
     *
     * @return bool Whether route caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->cacheConfig['enabled'] ?? false;
    }

    /**
     * Get cache configuration.
     *
     * @return array Cache configuration
     */
    public function getCacheConfig(): array
    {
        return $this->cacheConfig;
    }

    /**
     * Generate cache key for route patterns.
     *
     * @param  string  $suffix  Optional suffix for the key
     * @return string Cache key
     */
    public function generateCacheKey(string $suffix = ''): string
    {
        $prefix = $this->cacheConfig['key_prefix'] ?? 'mcp_routes';

        return $suffix ? "{$prefix}_{$suffix}" : $prefix;
    }

    /**
     * Normalize component name for use in routes.
     *
     * @param  string  $name  Original component name
     * @return string Normalized name
     */
    public function normalizeComponentName(string $name): string
    {
        // First replace dots with underscores, then convert to snake_case
        $name = str_replace('.', '_', $name);
        $normalized = Str::snake($name);

        // Clean up any double underscores that might be created
        return preg_replace('/_+/', '_', $normalized);
    }

    /**
     * Normalize action name for use in route names.
     *
     * @param  string  $action  Original action name
     * @return string Normalized action name
     */
    public function normalizeActionName(string $action): string
    {
        return Str::snake($action);
    }

    /**
     * Convert component name back from normalized form.
     *
     * @param  string  $normalized  Normalized component name
     * @return string Original component name
     */
    public function denormalizeComponentName(string $normalized): string
    {
        // Convert underscores back to dots and use camelCase
        return str_replace('_', '.', $normalized);
    }

    /**
     * Check if a route name matches MCP naming conventions.
     *
     * @param  string  $routeName  Route name to check
     * @return bool Whether the route name is MCP-style
     */
    public function isMcpRouteName(string $routeName): bool
    {
        $prefix = $this->namingConventions['prefix'];
        $separator = $this->namingConventions['separator'];

        return Str::startsWith($routeName, $prefix.$separator);
    }

    /**
     * Parse MCP route name into components.
     *
     * @param  string  $routeName  Route name to parse
     * @return array|null Parsed components [prefix, type, name, action?]
     */
    public function parseRouteName(string $routeName): ?array
    {
        if (! $this->isMcpRouteName($routeName)) {
            return null;
        }

        $separator = $this->namingConventions['separator'];
        $parts = explode($separator, $routeName);

        if (count($parts) < 3) {
            return null;
        }

        return [
            'prefix' => $parts[0], // 'mcp'
            'type' => $parts[1],   // 'tools', 'resources', 'prompts'
            'name' => $parts[2],   // component name
            'action' => $parts[3] ?? null, // optional action
        ];
    }

    /**
     * Generate resource-style routes for a component.
     *
     * @param  string  $type  Component type
     * @param  string  $name  Component name
     * @return array Array of route definitions
     */
    public function generateResourceRoutes(string $type, string $name): array
    {
        $basePattern = $this->patterns[$type] ?? [];
        $baseName = $this->normalizeComponentName($name);
        $baseUri = $this->generateRouteUri($type, $name);

        $routes = [];

        // Index route (list)
        $routes[] = [
            'methods' => ['GET'],
            'uri' => rtrim($basePattern['prefix'] ?? $type, '/'),
            'name' => $this->generateRouteName($type, 'index'),
            'action' => 'index',
        ];

        // Show route (single item)
        $routes[] = [
            'methods' => ['GET'],
            'uri' => $baseUri,
            'name' => $this->generateRouteName($type, $baseName, 'show'),
            'action' => 'show',
        ];

        // Store route (create)
        if (in_array('POST', $basePattern['methods'] ?? [])) {
            $routes[] = [
                'methods' => ['POST'],
                'uri' => rtrim($basePattern['prefix'] ?? $type, '/'),
                'name' => $this->generateRouteName($type, 'store'),
                'action' => 'store',
            ];
        }

        // Execute/Update route (component-specific action)
        if (in_array('POST', $basePattern['methods'] ?? [])) {
            $routes[] = [
                'methods' => ['POST'],
                'uri' => $baseUri,
                'name' => $this->generateRouteName($type, $baseName),
                'action' => $basePattern['controller_action'] ?? 'execute',
            ];
        }

        return $routes;
    }

    /**
     * Get HTTP methods for a component type.
     *
     * @param  string  $type  Component type
     * @return array HTTP methods
     */
    public function getHttpMethods(string $type): array
    {
        return $this->patterns[$type]['methods'] ?? ['GET', 'POST'];
    }

    /**
     * Get controller action for a component type.
     *
     * @param  string  $type  Component type
     * @return string Controller action method name
     */
    public function getControllerAction(string $type): string
    {
        return $this->patterns[$type]['controller_action'] ?? 'handle';
    }

    /**
     * Add or update a routing pattern.
     *
     * @param  string  $type  Component type
     * @param  array  $pattern  Pattern configuration
     */
    public function setPattern(string $type, array $pattern): void
    {
        $this->patterns[$type] = array_merge(
            $this->patterns[$type] ?? [],
            $pattern
        );
    }

    /**
     * Add or update middleware pattern.
     *
     * @param  string  $name  Pattern name
     * @param  array  $middleware  Middleware array
     */
    public function setMiddlewarePattern(string $name, array $middleware): void
    {
        $this->middlewarePatterns[$name] = $middleware;
    }

    /**
     * Add or update route constraint.
     *
     * @param  string  $type  Parameter type
     * @param  string  $constraint  Constraint pattern
     */
    public function setConstraint(string $type, string $constraint): void
    {
        $this->constraints[$type] = $constraint;
    }

    /**
     * Validate component name against naming conventions.
     *
     * @param  string  $name  Component name to validate
     * @return bool Whether the name is valid
     */
    public function validateComponentName(string $name): bool
    {
        // Check for valid characters (alphanumeric, dots, dashes, underscores)
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $name)) {
            return false;
        }

        // Check length constraints
        if (strlen($name) < 1 || strlen($name) > 100) {
            return false;
        }

        // Must not start or end with special characters
        if (preg_match('/^[._-]|[._-]$/', $name)) {
            return false;
        }

        return true;
    }

    /**
     * Get route definition template for a component type.
     *
     * @param  string  $type  Component type
     * @return array Route definition template
     */
    public function getRouteTemplate(string $type): array
    {
        $pattern = $this->getPattern($type);

        if (! $pattern) {
            return [];
        }

        return [
            'methods' => $pattern['methods'],
            'uri' => $pattern['pattern'],
            'middleware' => $this->getMiddleware($type),
            'constraints' => [
                $this->namingConventions['component_type_singular'][$type] ?? $type => $this->getConstraint($this->namingConventions['component_type_singular'][$type] ?? $type),
            ],
            'action' => $pattern['controller_action'],
        ];
    }
}
