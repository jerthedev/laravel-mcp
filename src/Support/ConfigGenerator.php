<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Exceptions\ConfigurationException;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;
use JTD\LaravelMCP\Support\Generators\ChatGptGenerator;
use JTD\LaravelMCP\Support\Generators\ClaudeCodeGenerator;
use JTD\LaravelMCP\Support\Generators\ClaudeDesktopGenerator;

/**
 * Configuration generator for MCP server.
 *
 * This class generates configuration files and documentation for MCP server
 * setup, including transport configurations and AI client configurations.
 */
class ConfigGenerator
{
    /**
     * MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * Client detector instance.
     */
    protected ClientDetector $clientDetector;

    /**
     * Client-specific generators.
     */
    protected array $clientGenerators = [];

    /**
     * Default configuration templates.
     */
    protected array $configTemplates = [];

    /**
     * Create a new config generator instance.
     */
    public function __construct(McpRegistry $registry, ?ClientDetector $clientDetector = null)
    {
        $this->registry = $registry;
        $this->clientDetector = $clientDetector ?? new ClientDetector;
        $this->initializeTemplates();
        $this->initializeClientGenerators();
    }

    /**
     * Generate MCP server configuration.
     */
    public function generateServerConfig(array $options = []): array
    {
        $config = [
            'server' => [
                'name' => $options['name'] ?? 'Laravel MCP Server',
                'version' => $options['version'] ?? '1.0.0',
                'description' => $options['description'] ?? 'MCP server built with Laravel',
            ],
            'transports' => $this->generateTransportConfig($options['transports'] ?? []),
            'capabilities' => $this->generateCapabilitiesConfig($options['capabilities'] ?? []),
            'components' => $this->generateComponentsConfig(),
            'logging' => $this->generateLoggingConfig($options['logging'] ?? []),
        ];

        return $config;
    }

    /**
     * Generate transport configuration.
     */
    public function generateTransportConfig(array $options = []): array
    {
        $default = $options['default'] ?? 'stdio';

        $transports = [
            'default' => $default,
            'stdio' => [
                'enabled' => $options['stdio']['enabled'] ?? true,
                'timeout' => $options['stdio']['timeout'] ?? 30,
                'debug' => $options['stdio']['debug'] ?? false,
            ],
            'http' => [
                'enabled' => $options['http']['enabled'] ?? true,
                'host' => $options['http']['host'] ?? '127.0.0.1',
                'port' => $options['http']['port'] ?? 8000,
                'path' => $options['http']['path'] ?? '/mcp',
                'ssl' => $options['http']['ssl'] ?? false,
                'middleware' => $options['http']['middleware'] ?? ['mcp.cors'],
                'cors' => [
                    'enabled' => $options['http']['cors']['enabled'] ?? true,
                    'headers' => $options['http']['cors']['headers'] ?? [
                        'Access-Control-Allow-Origin' => '*',
                        'Access-Control-Allow-Methods' => 'POST, OPTIONS',
                        'Access-Control-Allow-Headers' => 'Content-Type, Accept, Authorization',
                    ],
                ],
            ],
        ];

        return $transports;
    }

    /**
     * Generate capabilities configuration.
     */
    public function generateCapabilitiesConfig(array $options = []): array
    {
        return [
            'tools' => [
                'listChanged' => $options['tools']['listChanged'] ?? false,
            ],
            'resources' => [
                'subscribe' => $options['resources']['subscribe'] ?? false,
                'listChanged' => $options['resources']['listChanged'] ?? false,
            ],
            'prompts' => [
                'listChanged' => $options['prompts']['listChanged'] ?? false,
            ],
            'logging' => $options['logging'] ?? [],
        ];
    }

    /**
     * Generate components configuration.
     */
    public function generateComponentsConfig(): array
    {
        $components = [
            'discovery' => [
                'enabled' => true,
                'paths' => [
                    'app/Mcp/Tools',
                    'app/Mcp/Resources',
                    'app/Mcp/Prompts',
                ],
                'recursive' => true,
                'exclude_patterns' => ['*Test.php', '*test.php'],
            ],
            'statistics' => [
                'tools' => $this->registry->getTypeRegistry('tools')?->count() ?? 0,
                'resources' => $this->registry->getTypeRegistry('resources')?->count() ?? 0,
                'prompts' => $this->registry->getTypeRegistry('prompts')?->count() ?? 0,
                'total' => $this->registry->count(),
            ],
        ];

        return $components;
    }

    /**
     * Generate logging configuration.
     */
    public function generateLoggingConfig(array $options = []): array
    {
        return [
            'level' => $options['level'] ?? 'info',
            'channels' => $options['channels'] ?? ['single'],
            'mcp_requests' => $options['mcp_requests'] ?? true,
            'transport_errors' => $options['transport_errors'] ?? true,
            'component_discovery' => $options['component_discovery'] ?? false,
        ];
    }

    /**
     * Generate Claude Desktop configuration.
     */
    public function generateClaudeDesktopConfig(array $options = []): array
    {
        if (! isset($this->clientGenerators['claude-desktop'])) {
            throw new ConfigurationException('Claude Desktop generator not available');
        }

        return $this->clientGenerators['claude-desktop']->generate($options);
    }

    /**
     * Generate Claude Code configuration.
     */
    public function generateClaudeCodeConfig(array $options = []): array
    {
        if (! isset($this->clientGenerators['claude-code'])) {
            throw new ConfigurationException('Claude Code generator not available');
        }

        return $this->clientGenerators['claude-code']->generate($options);
    }

    /**
     * Generate ChatGPT Desktop configuration.
     */
    public function generateChatGptDesktopConfig(array $options = []): array
    {
        if (! isset($this->clientGenerators['chatgpt'])) {
            throw new ConfigurationException('ChatGPT Desktop generator not available');
        }

        return $this->clientGenerators['chatgpt']->generate($options);
    }

    /**
     * Get client configuration file path based on OS.
     */
    public function getClientConfigPath(string $client): ?string
    {
        return $this->clientDetector->getDefaultConfigPath($client);
    }

    /**
     * Validate client configuration.
     */
    public function validateClientConfig(string $client, array $config): array
    {
        if (! isset($this->clientGenerators[$client])) {
            return ["Unknown client type: $client"];
        }

        // Basic structural validation only - not field completeness
        $generator = $this->clientGenerators[$client];

        if ($client === 'claude-desktop') {
            if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
                return ['Configuration must contain mcpServers object'];
            }

            return []; // Pass basic structure check
        }

        if ($client === 'claude-code') {
            $errors = [];
            if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
                $errors[] = 'Configuration must contain mcpServers object';
            }

            return $errors;
        }

        if ($client === 'chatgpt' || $client === 'chatgpt-desktop') {
            if (! isset($config['mcp_servers']) || ! is_array($config['mcp_servers'])) {
                return ['Configuration must contain mcp_servers array'];
            }

            return []; // Pass basic structure check
        }

        // Fallback to strict validation for other clients
        return $generator->validateConfig($config);
    }

    /**
     * Save client configuration to file.
     */
    public function saveClientConfig(array $config, string $path, bool $force = false): bool
    {
        try {
            // Check if file exists and force is not enabled
            if (! $force && file_exists($path)) {
                return false;
            }

            // Ensure directory exists
            $directory = dirname($path);
            if (! is_dir($directory)) {
                if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                    return false;
                }
            }

            // Save as JSON
            $content = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if ($content === false) {
                return false;
            }

            return file_put_contents($path, $content) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Merge client configuration with existing config.
     */
    public function mergeClientConfig(string $client, array $newConfig, array $existingConfig = []): array
    {
        if (! isset($this->clientGenerators[$client])) {
            return $newConfig;
        }

        return $this->clientGenerators[$client]->mergeConfig($newConfig, $existingConfig);
    }

    /**
     * Generate development configuration.
     */
    public function generateDevelopmentConfig(): array
    {
        return [
            'debug' => true,
            'transports' => [
                'default' => 'stdio',
                'stdio' => [
                    'debug' => true,
                    'timeout' => 60,
                ],
                'http' => [
                    'enabled' => true,
                    'port' => 8001,
                    'debug' => true,
                ],
            ],
            'logging' => [
                'level' => 'debug',
                'mcp_requests' => true,
                'transport_errors' => true,
                'component_discovery' => true,
            ],
        ];
    }

    /**
     * Generate production configuration.
     */
    public function generateProductionConfig(): array
    {
        return [
            'debug' => false,
            'transports' => [
                'default' => 'stdio',
                'stdio' => [
                    'debug' => false,
                    'timeout' => 30,
                ],
                'http' => [
                    'enabled' => false,
                ],
            ],
            'logging' => [
                'level' => 'warning',
                'mcp_requests' => false,
                'transport_errors' => true,
                'component_discovery' => false,
            ],
        ];
    }

    /**
     * Save configuration to file.
     */
    public function saveConfig(array $config, string $path): bool
    {
        try {
            $content = $this->renderConfigFile($config);

            // Ensure directory exists
            $directory = dirname($path);
            if (! File::exists($directory)) {
                File::makeDirectory($directory, 0755, true);
            }

            File::put($path, $content);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Render configuration as PHP file content.
     */
    public function renderConfigFile(array $config): string
    {
        $export = var_export($config, true);

        // Clean up the export formatting
        $export = preg_replace('/=>\s*\n\s*array \(/', '=> [', $export);
        $export = preg_replace('/\),\n/', '],'."\n", $export);
        $export = str_replace('array (', '[', $export);
        $export = str_replace(')', ']', $export);

        return "<?php\n\nreturn {$export};\n";
    }

    /**
     * Generate environment variables template.
     */
    public function generateEnvironmentTemplate(array $options = []): string
    {
        $lines = [
            '# Laravel MCP Server Configuration',
            '',
            '# Transport Settings',
            'MCP_DEFAULT_TRANSPORT='.($options['default_transport'] ?? 'stdio'),
            'MCP_HTTP_ENABLED='.($options['http_enabled'] ?? 'true'),
            'MCP_HTTP_HOST='.($options['http_host'] ?? '127.0.0.1'),
            'MCP_HTTP_PORT='.($options['http_port'] ?? '8000'),
            'MCP_STDIO_ENABLED='.($options['stdio_enabled'] ?? 'true'),
            'MCP_STDIO_TIMEOUT='.($options['stdio_timeout'] ?? '30'),
            '',
            '# Discovery Settings',
            'MCP_AUTO_DISCOVERY='.($options['auto_discovery'] ?? 'true'),
            'MCP_DISCOVERY_PATHS='.($options['discovery_paths'] ?? 'app/Mcp/Tools,app/Mcp/Resources,app/Mcp/Prompts'),
            '',
            '# Debug Settings',
            'MCP_DEBUG='.($options['debug'] ?? 'false'),
            'MCP_LOG_REQUESTS='.($options['log_requests'] ?? 'true'),
            'MCP_LOG_LEVEL='.($options['log_level'] ?? 'info'),
        ];

        return implode("\n", $lines)."\n";
    }

    /**
     * Generate component manifest.
     */
    public function generateComponentManifest(): array
    {
        $manifest = [
            'generated_at' => now()->toISOString(),
            'components' => [],
        ];

        foreach ($this->registry->getTypeRegistries() as $type => $registry) {
            $manifest['components'][$type] = [];

            foreach ($registry->all() as $name => $component) {
                $metadata = $registry->getMetadata($name);

                $manifest['components'][$type][$name] = [
                    'name' => $name,
                    'description' => $metadata['description'] ?? '',
                    'class' => $metadata['class'] ?? null,
                    'file' => $metadata['file'] ?? null,
                    'type' => $type,
                ];
            }
        }

        return $manifest;
    }

    /**
     * Initialize configuration templates.
     */
    protected function initializeTemplates(): void
    {
        $this->configTemplates = [
            'server' => [
                'name' => 'Laravel MCP Server',
                'version' => '1.0.0',
                'description' => 'MCP server built with Laravel',
            ],
            'transports' => [
                'default' => 'stdio',
                'stdio' => ['enabled' => true, 'timeout' => 30],
                'http' => ['enabled' => true, 'port' => 8000],
            ],
            'capabilities' => [
                'tools' => ['listChanged' => false],
                'resources' => ['subscribe' => false, 'listChanged' => false],
                'prompts' => ['listChanged' => false],
            ],
        ];
    }

    /**
     * Initialize client-specific generators.
     */
    protected function initializeClientGenerators(): void
    {
        $this->clientGenerators = [
            'claude-desktop' => new ClaudeDesktopGenerator($this->registry),
            'claude-code' => new ClaudeCodeGenerator($this->registry),
            'chatgpt' => new ChatGptGenerator($this->registry),
            'chatgpt-desktop' => new ChatGptGenerator($this->registry), // Alias for consistency
        ];
    }

    /**
     * Get client generator for a specific client.
     *
     * @param  string  $client  Client identifier
     * @return ClientGeneratorInterface|null Generator instance or null if not found
     */
    public function getClientGenerator(string $client): ?ClientGeneratorInterface
    {
        return $this->clientGenerators[$client] ?? null;
    }

    /**
     * Check if a client is supported.
     *
     * @param  string  $client  Client identifier
     * @return bool True if client is supported
     */
    public function isClientSupported(string $client): bool
    {
        return isset($this->clientGenerators[$client]);
    }

    /**
     * Get list of supported clients.
     *
     * @return array Array of supported client identifiers
     */
    public function getSupportedClients(): array
    {
        return array_keys($this->clientGenerators);
    }

    /**
     * Get available configuration templates.
     */
    public function getTemplates(): array
    {
        return $this->configTemplates;
    }

    /**
     * Set configuration template.
     */
    public function setTemplate(string $key, array $template): void
    {
        $this->configTemplates[$key] = $template;
    }

    /**
     * Generate configuration for a specific client (Specification-compliant wrapper).
     *
     * This method provides the API defined in the specification while delegating
     * to the existing client-specific methods.
     *
     * @param  string  $client  Client type (claude-desktop, claude-code, chatgpt-desktop)
     * @param  array  $options  Configuration options
     * @return array Generated configuration
     *
     * @throws ConfigurationException If client is not supported
     */
    public function generateConfig(string $client, array $options = []): array
    {
        return match ($client) {
            'claude-desktop' => $this->generateClaudeDesktopConfig($options),
            'claude-code' => $this->generateClaudeCodeConfig($options),
            'chatgpt', 'chatgpt-desktop' => $this->generateChatGptDesktopConfig($options),
            default => throw new ConfigurationException("Unsupported client: $client")
        };
    }

    /**
     * Write configuration to file (Specification-compliant wrapper).
     *
     * This method provides the API defined in the specification while delegating
     * to the existing saveClientConfig method.
     *
     * @param  string  $client  Client type (claude-desktop, claude-code, chatgpt-desktop)
     * @param  string  $path  File path to write configuration
     * @param  array  $options  Configuration options
     * @return bool True if successful, false otherwise
     */
    public function writeConfig(string $client, string $path, array $options = []): bool
    {
        try {
            $config = $this->generateConfig($client, $options);

            return $this->saveClientConfig($config, $path, $options['force'] ?? false);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get default configuration path for a client (Specification-compliant wrapper).
     *
     * This method provides the API defined in the specification while delegating
     * to the existing getClientConfigPath method.
     *
     * @param  string  $client  Client type (claude-desktop, claude-code, chatgpt-desktop)
     * @return string|null Configuration file path or null if not determinable
     */
    public function getDefaultConfigPath(string $client): ?string
    {
        // Map chatgpt-desktop to chatgpt for backward compatibility
        if ($client === 'chatgpt-desktop') {
            $client = 'chatgpt';
        }

        return $this->getClientConfigPath($client);
    }

    /**
     * Generate configuration diff between two configs.
     */
    public function generateConfigDiff(array $oldConfig, array $newConfig): array
    {
        $diff = [
            'added' => [],
            'modified' => [],
            'removed' => [],
        ];

        $this->compareArrays($oldConfig, $newConfig, $diff, '');

        return $diff;
    }

    /**
     * Compare two arrays recursively for diff generation.
     */
    protected function compareArrays(array $old, array $new, array &$diff, string $prefix): void
    {
        foreach ($new as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (! array_key_exists($key, $old)) {
                $diff['added'][$fullKey] = $value;
            } elseif (is_array($value) && is_array($old[$key])) {
                $this->compareArrays($old[$key], $value, $diff, $fullKey);
            } elseif ($value !== $old[$key]) {
                $diff['modified'][$fullKey] = ['old' => $old[$key], 'new' => $value];
            }
        }

        foreach ($old as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (! array_key_exists($key, $new)) {
                $diff['removed'][$fullKey] = $value;
            }
        }
    }

    /**
     * Detect operating system (delegate to ClientDetector).
     */
    protected function detectOperatingSystem(): string
    {
        return $this->clientDetector->detectOperatingSystem();
    }

    /**
     * Get home directory (delegate to ClientDetector).
     */
    protected function getHomeDirectory(): string
    {
        return $this->clientDetector->getHomeDirectory();
    }
}
