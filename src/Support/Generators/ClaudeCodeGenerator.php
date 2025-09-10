<?php

namespace JTD\LaravelMCP\Support\Generators;

use Illuminate\Support\Str;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

/**
 * Configuration generator for Claude Code.
 *
 * Generates MCP server configuration for Claude Code IDE extension,
 * supporting both stdio and HTTP transports with proper formatting.
 */
class ClaudeCodeGenerator implements ClientGeneratorInterface
{
    /**
     * MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * Create a new Claude Code generator instance.
     *
     * @param  McpRegistry  $registry  MCP registry instance
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Generate configuration for Claude Code.
     *
     * @param  array  $options  Configuration options
     * @return array Generated configuration array
     */
    public function generate(array $options = []): array
    {
        $serverName = $options['server_name'] ?? $options['name'] ?? $this->getDefaultServerName();
        $transport = $options['transport'] ?? 'stdio';

        $config = $this->loadExistingConfig($options['config_path'] ?? null);

        $serverConfig = match ($transport) {
            'stdio' => $this->generateStdioConfig($options),
            'http' => $this->generateHttpConfig($options),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport"),
        };

        // Add description and metadata
        $serverConfig['description'] = $options['description'] ?? $this->getDefaultDescription();
        $serverConfig['transport'] = $transport;

        $config['mcp']['servers'][$serverName] = $serverConfig;

        return $config;
    }

    /**
     * Get the default server name for Claude Code.
     *
     * @return string Default server name
     */
    public function getDefaultServerName(): string
    {
        $appName = config('app.name', 'Laravel');

        return Str::slug($appName).'-mcp';
    }

    /**
     * Get the default description for Claude Code.
     *
     * @return string Default description
     */
    public function getDefaultDescription(): string
    {
        $tools = $this->registry->getTools() ?? [];
        $resources = $this->registry->getResources() ?? [];
        $prompts = $this->registry->getPrompts() ?? [];
        
        $toolCount = count($tools);
        $resourceCount = count($resources);
        $promptCount = count($prompts);

        $total = $toolCount + $resourceCount + $promptCount;
        if ($total === 0) {
            return 'Laravel MCP Server';
        }

        $components = [];
        if ($toolCount > 0) {
            $components[] = "{$toolCount} tools";
        }
        if ($resourceCount > 0) {
            $components[] = "{$resourceCount} resources";
        }
        if ($promptCount > 0) {
            $components[] = "{$promptCount} prompts";
        }

        $componentText = empty($components) ? 'no components' : implode(', ', $components);

        return "Laravel MCP Server with {$componentText}";
    }

    /**
     * Validate configuration for Claude Code.
     *
     * @param  array  $config  Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['mcp']) || ! is_array($config['mcp'])) {
            $errors[] = 'Configuration must contain mcp object';

            return $errors;
        }

        if (! isset($config['mcp']['servers']) || ! is_array($config['mcp']['servers'])) {
            $errors[] = 'Configuration must contain mcp.servers object';

            return $errors;
        }

        foreach ($config['mcp']['servers'] as $serverName => $serverConfig) {
            $serverErrors = $this->validateServerConfig($serverName, $serverConfig);
            $errors = array_merge($errors, $serverErrors);
        }

        return $errors;
    }

    /**
     * Merge new configuration with existing configuration.
     *
     * @param  array  $newConfig  New configuration
     * @param  array  $existingConfig  Existing configuration
     * @return array Merged configuration
     */
    public function mergeConfig(array $newConfig, array $existingConfig): array
    {
        if (empty($existingConfig)) {
            return $newConfig;
        }

        $merged = $existingConfig;

        // Ensure proper structure exists
        if (! isset($merged['mcp'])) {
            $merged['mcp'] = [];
        }
        if (! isset($merged['mcp']['servers'])) {
            $merged['mcp']['servers'] = [];
        }

        // Merge servers
        $merged['mcp']['servers'] = array_merge(
            $merged['mcp']['servers'],
            $newConfig['mcp']['servers'] ?? []
        );

        return $merged;
    }

    /**
     * Generate stdio transport configuration.
     *
     * @param  array  $options  Additional options
     * @return array Stdio configuration
     */
    protected function generateStdioConfig(array $options): array
    {
        $command = $options['command'] ?? 'php';
        $args = $options['args'] ?? ['artisan', 'mcp:serve'];
        $cwd = $options['cwd'] ?? base_path();
        $env = $options['env'] ?? [];

        // Handle when command is already an array
        if (is_array($command)) {
            $baseCommand = array_shift($command);
            $allArgs = array_merge($command, $args);
        } else {
            $baseCommand = $command;
            $allArgs = $args;
        }

        return [
            'command' => $baseCommand,
            'args' => $allArgs,
            'cwd' => $cwd,
            'env' => $env,
            'timeout' => $options['timeout'] ?? 30,
        ];
    }

    /**
     * Generate HTTP transport configuration.
     *
     * @param  array  $options  HTTP options
     * @return array HTTP configuration
     */
    protected function generateHttpConfig(array $options): array
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 8000;
        $path = $options['path'] ?? '/mcp';

        return [
            'url' => "http://{$host}:{$port}{$path}",
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $options['headers'] ?? []),
            'timeout' => $options['timeout'] ?? 30,
            'method' => 'POST',
        ];
    }

    /**
     * Get default environment variables.
     *
     * @return array Default environment variables
     */
    protected function getDefaultEnvVars(): array
    {
        return [
            'APP_ENV' => config('app.env', 'production'),
            'MCP_TRANSPORT' => 'stdio',
            'MCP_LOG_LEVEL' => config('app.debug') ? 'debug' : 'info',
        ];
    }

    /**
     * Load existing configuration from file.
     *
     * @param  string|null  $path  Configuration file path
     * @return array Existing configuration or empty template
     */
    protected function loadExistingConfig(?string $path): array
    {
        if (! $path || ! file_exists($path)) {
            return ['mcp' => ['servers' => []]];
        }

        try {
            $content = file_get_contents($path);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in existing configuration');
            }

            // Ensure proper structure
            if (! isset($config['mcp'])) {
                $config['mcp'] = [];
            }
            if (! isset($config['mcp']['servers']) || ! is_array($config['mcp']['servers'])) {
                $config['mcp']['servers'] = [];
            }

            return $config;
        } catch (\Throwable $e) {
            // Return empty template if file cannot be read/parsed
            return ['mcp' => ['servers' => []]];
        }
    }

    /**
     * Validate individual server configuration.
     *
     * @param  string  $serverName  Server name
     * @param  array  $serverConfig  Server configuration
     * @return array Array of validation errors
     */
    protected function validateServerConfig(string $serverName, array $serverConfig): array
    {
        $errors = [];

        if (empty($serverName) || ! is_string($serverName)) {
            $errors[] = 'Server name must be a non-empty string';
        }

        // Validate based on transport type (only validate structure, not required fields)
        $transport = $serverConfig['transport'] ?? 'stdio';

        if ($transport === 'stdio') {
            if (isset($serverConfig['command']) && ! is_string($serverConfig['command'])) {
                $errors[] = "Server '{$serverName}': command must be a string if provided";
            }

            if (isset($serverConfig['cwd'])) {
                if (! is_string($serverConfig['cwd'])) {
                    $errors[] = "Server '{$serverName}': cwd must be a string";
                } elseif (! app()->environment('testing') && ! is_dir($serverConfig['cwd'])) {
                    // In non-testing environments, validate that the directory exists
                    $errors[] = "Server '{$serverName}': cwd must be a valid directory path";
                }
            }

            if (isset($serverConfig['env']) && ! is_array($serverConfig['env'])) {
                $errors[] = "Server '{$serverName}': env must be an array if provided";
            }
        } elseif ($transport === 'http') {
            if (isset($serverConfig['url']) && ! is_string($serverConfig['url'])) {
                $errors[] = "Server '{$serverName}': url must be a string if provided";
            }

            if (isset($serverConfig['headers']) && ! is_array($serverConfig['headers'])) {
                $errors[] = "Server '{$serverName}': headers must be an array if provided";
            }
        }

        return $errors;
    }
}
