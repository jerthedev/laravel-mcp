<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface;
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
     * Create a new component discovery instance.
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;

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
     */
    public function registerDiscoveredComponents(): void
    {
        $discovered = $this->discover([]);

        foreach ($discovered as $component) {
            try {
                $this->registry->register(
                    $component['type'],
                    $component['name'],
                    $component['class'],
                    $component['metadata'] ?? []
                );

                logger()->debug('Registered discovered component', [
                    'type' => $component['type'],
                    'name' => $component['name'],
                    'class' => $component['class'],
                ]);
            } catch (\Exception $e) {
                logger()->warning('Failed to register discovered component', [
                    'component' => $component,
                    'error' => $e->getMessage(),
                ]);
            }
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
            if (!$startedParsing && empty($line)) {
                continue;
            }
            
            // Stop at @annotations
            if (str_starts_with($line, '@')) {
                break;
            }
            
            // If we have content, add it to description
            if (!empty($line)) {
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
}
