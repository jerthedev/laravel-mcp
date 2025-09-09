<?php

namespace JTD\LaravelMCP\Registry;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Abstracts\McpPrompt;
use JTD\LaravelMCP\Abstracts\McpResource;
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface;
use ReflectionClass;

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

    protected ToolRegistry $toolRegistry;

    protected ResourceRegistry $resourceRegistry;

    protected PromptRegistry $promptRegistry;

    /**
     * Create a new component discovery instance.
     */
    public function __construct(
        McpRegistry $registry,
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry
    ) {
        $this->registry = $registry;
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;
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

            $files = $this->scanDirectory($path);

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

            $files = $this->scanDirectory($path);

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
    public function discoverComponents(array $paths): void
    {
        $discovered = $this->discover($paths);

        foreach ($discovered['tools'] as $className => $metadata) {
            $this->toolRegistry->register($metadata['name'], $className, $metadata);
        }

        foreach ($discovered['resources'] as $className => $metadata) {
            $this->resourceRegistry->register($metadata['name'], $className, $metadata);
        }

        foreach ($discovered['prompts'] as $className => $metadata) {
            $this->promptRegistry->register($metadata['name'], $className, $metadata);
        }
    }

    /**
     * Register discovered components.
     */
    public function registerDiscoveredComponents(): void
    {
        // This method is called after discovery to finalize registration
        // Implementation can be expanded based on needs
    }

    /**
     * Scan directory for PHP files.
     */
    protected function scanDirectory(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $iterator = $this->config['recursive']
            ? File::allFiles($path)
            : File::files($path);

        $files = [];
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php' && $this->passesFilters($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
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

        foreach ($lines as $line) {
            $line = trim($line, " *\t\r\n/");
            if (empty($line) || str_starts_with($line, '@')) {
                break;
            }
            $description .= ($description ? ' ' : '').$line;
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
