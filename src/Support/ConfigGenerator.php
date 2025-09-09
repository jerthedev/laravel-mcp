<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Registry\McpRegistry;

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
     * Default configuration templates.
     */
    protected array $configTemplates = [];

    /**
     * Create a new config generator instance.
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
        $this->initializeTemplates();
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
        $serverName = $options['server_name'] ?? 'laravel-mcp';
        $command = $options['command'] ?? ['php', 'artisan', 'mcp:serve'];
        $args = $options['args'] ?? [];
        $env = $options['env'] ?? [];

        return [
            'mcpServers' => [
                $serverName => [
                    'command' => $command[0],
                    'args' => array_merge(array_slice($command, 1), $args),
                    'env' => $env,
                ],
            ],
        ];
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
}
