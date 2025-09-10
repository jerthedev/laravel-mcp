<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface;
use JTD\LaravelMCP\Registry\RoutingPatterns;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * Component discovery service for MCP components.
 *
 * This class automatically discovers and analyzes MCP components
 * in Laravel applications, scanning directories and extracting metadata.
 */
class ComponentDiscovery implements DiscoveryInterface
{
    /**
     * Discovery configuration.
     */
    protected array $config = [
        'recursive' => true,
        'file_patterns' => ['*.php'],
        'exclude_patterns' => ['*Test.php', '*test.php'],
    ];

    /**
     * Discovery filters.
     */
    protected array $filters = [];

    /**
     * Supported component types and their base classes.
     */
    protected array $supportedTypes = [
        'tools' => McpTool::class,
        'resources' => McpResource::class,
        'prompts' => McpPrompt::class,
    ];

    /**
     * Component registries.
     */
    protected McpRegistry $registry;

    /**
     * Discovered components cache.
     */
    private array $discoveredComponents = [];

    /**
     * Discovery paths from configuration.
     */
    private array $discoveryPaths;

    /**
     * Whether caching is enabled.
     */
    private bool $cacheEnabled;

    /**
     * Cache key for discovered components.
     */
    private string $cacheKey = 'laravel_mcp_discovered_components';

    /**
     * Routing patterns instance for route generation.
     */
    protected RoutingPatterns $routingPatterns;

    /**
     * Create a new component discovery instance.
     */
    public function __construct(McpRegistry $registry, RoutingPatterns $routingPatterns)
    {
        $this->registry = $registry;
        $this->routingPatterns = $routingPatterns;

        $this->discoveryPaths = config('laravel-mcp.discovery.paths', [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ]);

        $this->cacheEnabled = config('laravel-mcp.discovery.cache', true);
    }

    /**
     * Discover components in the specified paths.
     */
    public function discover(array $paths): array
    {
        $discovered = [
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = $this->getPhpFiles($path);

            if (! $files) {
                continue;
            }

            foreach ($files as $file) {
                foreach ($this->supportedTypes as $type => $baseClass) {
                    if ($this->isValidComponent($file, $type)) {
                        $className = $this->getClassFromFile($file);
                        if ($className) {
                            $metadata = $this->extractMetadata($file);
                            $discovered[$type][$className] = $metadata;
                        }
                    }
                }
            }
        }

        return $discovered;
    }

    /**
     * Discover components of a specific type.
     */
    public function discoverType(string $type, array $paths): array
    {
        if (! isset($this->supportedTypes[$type])) {
            return [];
        }

        $discovered = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $files = $this->getPhpFiles($path);

            if (! $files) {
                continue;
            }

            foreach ($files as $file) {
                if ($this->isValidComponent($file, $type)) {
                    $className = $this->getClassFromFile($file);
                    if ($className) {
                        $metadata = $this->extractMetadata($file);
                        $discovered[$className] = $metadata;
                    }
                }
            }
        }

        return $discovered;
    }

    /**
     * Check if a file contains a valid component.
     */
    public function isValidComponent(string $filePath, string $type): bool
    {
        if (! file_exists($filePath) || ! isset($this->supportedTypes[$type])) {
            return false;
        }

        $className = $this->getClassFromFile($filePath);

        return $className && $this->isValidComponentClass($className, $type);
    }

    /**
     * Extract component metadata from a file.
     */
    public function extractMetadata(string $filePath): array
    {
        $className = $this->getClassFromFile($filePath);

        if (! $className || ! class_exists($className)) {
            return [];
        }

        try {
            $reflection = new ReflectionClass($className);
            $docComment = $reflection->getDocComment();

            $metadata = [
                'class' => $className,
                'file' => $filePath,
                'namespace' => $reflection->getNamespaceName(),
                'name' => $reflection->getShortName(),
                'description' => $this->parseDescription($docComment),
                'methods' => $this->getPublicMethods($reflection),
                'properties' => $this->getPublicProperties($reflection),
                'route_metadata' => $this->extractRouteMetadata($reflection),
            ];

            // Extract component-specific metadata
            if ($reflection->isSubclassOf(McpTool::class)) {
                $metadata['type'] = 'tool';
                $metadata['parameters'] = $this->extractToolParameters($reflection);
            } elseif ($reflection->isSubclassOf(McpResource::class)) {
                $metadata['type'] = 'resource';
                $metadata['uri'] = $this->extractResourceUri($reflection);
                $metadata['mime_type'] = $this->extractResourceMimeType($reflection);
            } elseif ($reflection->isSubclassOf(McpPrompt::class)) {
                $metadata['type'] = 'prompt';
                $metadata['arguments'] = $this->extractPromptArguments($reflection);
            }

            return $metadata;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the class name from a file path.
     */
    public function getClassFromFile(string $filePath): ?string
    {
        if (! file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);

        // Extract namespace
        $namespace = '';
        if (preg_match('/^\s*namespace\s+([^\s;]+)/m', $content, $matches)) {
            $namespace = trim($matches[1]).'\\';
        } else {
            // No namespace found - MCP components must have namespaces
            return null;
        }

        // Extract class name
        if (preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+([^\s\{]+)/m', $content, $matches)) {
            return $namespace.trim($matches[1]);
        }

        return null;
    }

    /**
     * Validate that a class is a valid MCP component.
     */
    public function isValidComponentClass(string $className, string $type): bool
    {
        if (! class_exists($className) || ! isset($this->supportedTypes[$type])) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($className);
            $baseClass = $this->supportedTypes[$type];

            return $reflection->isSubclassOf($baseClass) &&
                   ! $reflection->isAbstract() &&
                   ! $reflection->isInterface();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get supported component types for discovery.
     */
    public function getSupportedTypes(): array
    {
        return array_keys($this->supportedTypes);
    }

    /**
     * Set discovery configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Get current discovery configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Add a discovery filter/rule.
     */
    public function addFilter(callable $filter): void
    {
        $this->filters[] = $filter;
    }

    /**
     * Get all active discovery filters.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Discover and register components.
     *
     * @param  array  $paths  Paths to scan for components
     * @param  bool  $registerRoutes  Whether to automatically register routes
     */
    public function discoverComponents(array $paths = []): array
    {
        $searchPaths = empty($paths) ? $this->discoveryPaths : $paths;

        if ($this->cacheEnabled && $cached = $this->getCachedDiscovery()) {
            $this->discoveredComponents = $cached;

            return $cached;
        }

        $this->discoveredComponents = [];

        foreach ($searchPaths as $path) {
            if (is_dir($path)) {
                $this->scanDirectory($path);
            }
        }

        if ($this->cacheEnabled) {
            $this->cacheDiscovery();
        }

        return $this->discoveredComponents;
    }

    /**
     * Register discovered components.
     *
     * @param  bool  $registerRoutes  Whether to automatically register routes
     */
    public function registerDiscoveredComponents(bool $registerRoutes = true): void
    {
        foreach ($this->discoveredComponents as $component) {
            try {
                $this->registry->registerWithType(
                    $component['type'],
                    $component['name'],
                    $component['class'],
                    $component['options'] ?? []
                );

                Log::debug('Registered discovered component', [
                    'type' => $component['type'],
                    'name' => $component['name'],
                    'class' => $component['class'],
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to register discovered component', [
                    'component' => $component,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Register routes for discovered components if requested
        if ($registerRoutes) {
            // Group components by type for route registration
            $groupedComponents = [];
            foreach ($this->discoveredComponents as $component) {
                $type = $component['type'];
                $groupedComponents[$type.'s'] ??= [];
                $groupedComponents[$type.'s'][$component['class']] = $component;
            }
            $this->registerRoutes($groupedComponents);
        }
    }

    /**
     * Validate discovered components.
     *
     * This method validates all discovered components to ensure they
     * properly implement their respective base classes and meet all
     * requirements for MCP components.
     */
    public function validateDiscoveredComponents(): void
    {
        $discovered = $this->discover([]);

        foreach ($discovered as $component) {
            try {
                // Validate the component class exists
                if (! class_exists($component['class'])) {
                    logger()->warning('Discovered component class does not exist', [
                        'class' => $component['class'],
                        'type' => $component['type'],
                    ]);

                    continue;
                }

                // Validate the component extends the correct base class
                $reflection = new ReflectionClass($component['class']);
                $baseClass = $this->supportedTypes[$component['type']] ?? null;

                if ($baseClass && ! $reflection->isSubclassOf($baseClass)) {
                    logger()->warning('Discovered component does not extend required base class', [
                        'class' => $component['class'],
                        'type' => $component['type'],
                        'expected_base' => $baseClass,
                    ]);

                    continue;
                }

                logger()->debug('Validated discovered component', [
                    'type' => $component['type'],
                    'name' => $component['name'],
                    'class' => $component['class'],
                ]);
            } catch (\Exception $e) {
                logger()->warning('Failed to validate discovered component', [
                    'component' => $component,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Scan directory for PHP files.
     */
    protected function scanDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $finder = new Finder;
        $finder->files()->name('*.php')->in($path);

        foreach ($finder as $file) {
            $this->analyzeFile($file->getRealPath());
        }
    }

    /**
     * Get PHP files from a directory.
     */
    private function getPhpFiles(string $directory): ?array
    {
        if (! is_dir($directory)) {
            return null;
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Analyze a file for MCP components.
     */
    private function analyzeFile(string $filePath): void
    {
        try {
            $className = $this->extractClassName($filePath);

            if (! $className || ! class_exists($className)) {
                return;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract() || $reflection->isInterface() || $reflection->isTrait()) {
                return;
            }

            $component = $this->classifyComponent($reflection);

            if ($component) {
                $this->discoveredComponents[] = $component;
            }
        } catch (\Throwable $e) {
            logger()->debug('Failed to analyze file for MCP discovery', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract class name from file.
     */
    private function extractClassName(string $filePath): ?string
    {
        $content = file_get_contents($filePath);

        // Extract namespace
        if (! preg_match('/namespace\s+([^;]+);/', $content, $namespaceMatches)) {
            return null;
        }
        $namespace = trim($namespaceMatches[1]);

        // Extract class name
        if (! preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            return null;
        }
        $className = trim($classMatches[1]);

        return $namespace.'\\'.$className;
    }

    /**
     * Classify a component based on its reflection.
     */
    private function classifyComponent(ReflectionClass $reflection): ?array
    {
        $className = $reflection->getName();

        if ($reflection->isSubclassOf(McpTool::class)) {
            return [
                'type' => 'tool',
                'name' => $this->generateComponentName($className, 'Tool'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        if ($reflection->isSubclassOf(McpResource::class)) {
            return [
                'type' => 'resource',
                'name' => $this->generateComponentName($className, 'Resource'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        if ($reflection->isSubclassOf(McpPrompt::class)) {
            return [
                'type' => 'prompt',
                'name' => $this->generateComponentName($className, 'Prompt'),
                'class' => $className,
                'file' => $reflection->getFileName(),
            ];
        }

        return null;
    }

    /**
     * Generate component name from class name.
     */
    private function generateComponentName(string $className, string $suffix): string
    {
        $baseName = class_basename($className);
        $name = str_replace($suffix, '', $baseName);

        return Str::snake($name);
    }

    /**
     * Check if file passes discovery filters.
     */
    protected function passesFilters(string $filePath): bool
    {
        // Check exclude patterns
        foreach ($this->config['exclude_patterns'] ?? [] as $pattern) {
            if (fnmatch($pattern, basename($filePath))) {
                return false;
            }
        }

        // Apply custom filters
        foreach ($this->filters as $filter) {
            if (! $filter($filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse description from doc comment.
     */
    protected function parseDescription(string|false $docComment): string
    {
        if (! $docComment) {
            return '';
        }

        $lines = explode("\n", $docComment);
        $description = '';
        $startedParsing = false;
        $emptyLineCount = 0;

        foreach ($lines as $line) {
            // Remove leading/trailing whitespace and asterisks
            $line = trim($line);
            $line = trim($line, '/*');
            $line = trim($line);

            // Skip empty lines at the beginning
            if (! $startedParsing && empty($line)) {
                continue;
            }

            // Stop at @annotations
            if (str_starts_with($line, '@')) {
                break;
            }

            // If we have content, add it to description
            if (! empty($line)) {
                $startedParsing = true;
                $emptyLineCount = 0;
                $description .= ($description ? ' ' : '').$line;
            } elseif ($startedParsing) {
                // Count consecutive empty lines
                $emptyLineCount++;
                // Two consecutive empty lines indicate end of description
                if ($emptyLineCount >= 2) {
                    break;
                }
            }
        }

        return $description;
    }

    /**
     * Get public methods of a class.
     */
    protected function getPublicMethods(ReflectionClass $reflection): array
    {
        $methods = [];

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if (! $method->isConstructor() && ! $method->isDestructor()) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }

    /**
     * Get public properties of a class.
     */
    protected function getPublicProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $properties[] = $property->getName();
        }

        return $properties;
    }

    /**
     * Extract tool parameters from reflection.
     */
    protected function extractToolParameters(ReflectionClass $reflection): array
    {
        // This could be enhanced to parse method signatures or annotations
        return [];
    }

    /**
     * Extract resource URI from reflection.
     */
    protected function extractResourceUri(ReflectionClass $reflection): string
    {
        // This could be enhanced to parse class constants or properties
        return '';
    }

    /**
     * Extract resource MIME type from reflection.
     */
    protected function extractResourceMimeType(ReflectionClass $reflection): string
    {
        return 'application/json';
    }

    /**
     * Extract prompt arguments from reflection.
     */
    protected function extractPromptArguments(ReflectionClass $reflection): array
    {
        // This could be enhanced to parse method signatures or annotations
        return [];
    }

    /**
     * Register routes for discovered components.
     *
     * @param  array  $discovered  Discovered components grouped by type
     */
    public function registerRoutes(array $discovered): void
    {
        try {
            $registeredRoutes = [];

            foreach ($discovered as $componentType => $components) {
                // Get the singular form for component type (tools -> tool)
                $singularType = rtrim($componentType, 's');

                foreach ($components as $className => $metadata) {
                    $componentName = $metadata['name'];
                    $routeMetadata = $metadata['route_metadata'] ?? [];

                    // Generate route definitions for this component
                    $routes = $this->generateComponentRoutes($singularType, $componentName, $routeMetadata);

                    foreach ($routes as $route) {
                        try {
                            // Register the route with Laravel's router
                            $this->registerSingleRoute($route, $className, $metadata);

                            $registeredRoutes[] = [
                                'name' => $route['name'],
                                'uri' => $route['uri'],
                                'methods' => $route['methods'],
                                'component' => $componentName,
                                'type' => $singularType,
                            ];

                            Log::debug('Registered route for component', [
                                'route_name' => $route['name'],
                                'uri' => $route['uri'],
                                'component' => $componentName,
                                'type' => $singularType,
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to register route for component', [
                                'component' => $componentName,
                                'route' => $route,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }

            // Cache the registered routes if caching is enabled
            if ($this->routingPatterns->isCacheEnabled()) {
                $this->cacheRegisteredRoutes($registeredRoutes);
            }

            Log::info('Completed route registration for discovered components', [
                'total_routes' => count($registeredRoutes),
                'components' => array_sum(array_map('count', $discovered)),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to register routes for discovered components', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate route definitions for a component.
     *
     * @param  string  $type  Component type (tool, resource, prompt)
     * @param  string  $name  Component name
     * @param  array  $routeMetadata  Route-specific metadata from component
     * @return array Array of route definitions
     */
    protected function generateComponentRoutes(string $type, string $name, array $routeMetadata = []): array
    {
        $routes = [];
        $useResourceRoutes = $routeMetadata['resource_routes'] ?? false;

        if ($useResourceRoutes) {
            // Generate full resource-style routes (index, show, store, etc.)
            $routes = $this->routingPatterns->generateResourceRoutes($type.'s', $name);
        } else {
            // Generate basic route for the component
            $routeName = $this->routingPatterns->generateRouteName($type.'s', $name);
            $routeUri = $this->routingPatterns->generateRouteUri($type.'s', $name);
            $middleware = $this->routingPatterns->getMiddleware($type.'s');
            $httpMethods = $this->routingPatterns->getHttpMethods($type.'s');
            $controllerAction = $this->routingPatterns->getControllerAction($type.'s');

            // Apply custom middleware from route metadata
            if (isset($routeMetadata['middleware'])) {
                $customMiddleware = is_array($routeMetadata['middleware'])
                    ? $routeMetadata['middleware']
                    : [$routeMetadata['middleware']];
                $middleware = array_unique(array_merge($middleware, $customMiddleware));
            }

            $routes[] = [
                'name' => $routeName,
                'uri' => $routeUri,
                'methods' => $httpMethods,
                'middleware' => $middleware,
                'action' => $controllerAction,
                'constraints' => $this->getRouteConstraints($type),
            ];
        }

        return $routes;
    }

    /**
     * Register a single route with Laravel's router.
     *
     * @param  array  $route  Route definition
     * @param  string  $className  Component class name
     * @param  array  $metadata  Component metadata
     */
    protected function registerSingleRoute(array $route, string $className, array $metadata): void
    {
        $router = app('router');

        // Create the route registration
        $routeRegistration = $router->match(
            $route['methods'],
            $route['uri'],
            [
                'uses' => 'JTD\LaravelMCP\Http\Controllers\McpController@'.$route['action'],
                'as' => $route['name'],
                'middleware' => $route['middleware'] ?? [],
            ]
        );

        // Apply route constraints if specified
        if (isset($route['constraints'])) {
            foreach ($route['constraints'] as $parameter => $pattern) {
                if ($pattern) {
                    $routeRegistration->where($parameter, $pattern);
                }
            }
        }

        // Add component metadata to route for runtime access
        $routeRegistration->defaults('component_class', $className);
        $routeRegistration->defaults('component_metadata', $metadata);
        $routeRegistration->defaults('component_type', $metadata['type']);
    }

    /**
     * Get route constraints for a component type.
     *
     * @param  string  $type  Component type
     * @return array Route constraints
     */
    protected function getRouteConstraints(string $type): array
    {
        return [
            $type => $this->routingPatterns->getConstraint($type),
        ];
    }

    /**
     * Cache registered routes for performance.
     *
     * @param  array  $routes  Array of registered route information
     */
    protected function cacheRegisteredRoutes(array $routes): void
    {
        try {
            $cacheKey = $this->routingPatterns->generateCacheKey('discovered_routes');

            if (function_exists('cache')) {
                cache()->put($cacheKey, $routes, now()->addHours(24));
            }
        } catch (\Exception $e) {
            Log::warning('Failed to cache registered routes', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract route-specific metadata from component reflection.
     *
     * @param  ReflectionClass  $reflection  Component reflection
     * @return array Route metadata
     */
    protected function extractRouteMetadata(ReflectionClass $reflection): array
    {
        $metadata = [];
        $docComment = $reflection->getDocComment();

        if ($docComment) {
            // Extract @route annotations
            if (preg_match('/@route\s+([^\n]+)/i', $docComment, $matches)) {
                $routeAnnotation = trim($matches[1]);
                $metadata['custom_route'] = $routeAnnotation;
            }

            // Extract @middleware annotations
            if (preg_match_all('/@middleware\s+([^\n]+)/i', $docComment, $matches)) {
                $middleware = [];
                foreach ($matches[1] as $middlewareList) {
                    $middleware = array_merge($middleware, array_map('trim', explode(',', $middlewareList)));
                }
                $metadata['middleware'] = array_unique($middleware);
            }

            // Extract @resource annotation for resource-style routes
            if (preg_match('/@resource/i', $docComment)) {
                $metadata['resource_routes'] = true;
            }

            // Extract @methods annotation
            if (preg_match('/@methods\s+([^\n]+)/i', $docComment, $matches)) {
                $methods = array_map('trim', explode(',', $matches[1]));
                $metadata['http_methods'] = array_map('strtoupper', $methods);
            }
        }

        return $metadata;
    }

    /**
     * Get cached discovery results.
     */
    private function getCachedDiscovery(): ?array
    {
        if (!$this->cacheEnabled) {
            return null;
        }

        try {
            return Cache::get($this->cacheKey);
        } catch (\Exception $e) {
            logger()->warning('Failed to retrieve cached discovery results', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Cache discovery results.
     */
    private function cacheDiscovery(): void
    {
        if (!$this->cacheEnabled || empty($this->discoveredComponents)) {
            return;
        }

        try {
            Cache::put($this->cacheKey, $this->discoveredComponents, now()->addHours(1));
        } catch (\Exception $e) {
            logger()->warning('Failed to cache discovery results', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
