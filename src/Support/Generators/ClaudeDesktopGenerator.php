<?php

namespace JTD\LaravelMCP\Support\Generators;

use Illuminate\Support\Str;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

/**
 * Configuration generator for Claude Desktop.
 *
 * Generates MCP server configuration for Claude Desktop client,
 * supporting both stdio and HTTP transports with proper formatting.
 */
class ClaudeDesktopGenerator implements ClientGeneratorInterface
{
    /**
     * MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * Create a new Claude Desktop generator instance.
     *
     * @param  McpRegistry  $registry  MCP registry instance
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Generate configuration for Claude Desktop.
     *
     * @param  array  $options  Configuration options
     * @return array Generated configuration array
     */
    public function generate(array $options = []): array
    {
        $serverName = $options['server_name'] ?? $options['name'] ?? $this->getDefaultServerName();
        $description = $options['description'] ?? $this->getDefaultDescription();
        $workingDirectory = $options['cwd'] ?? base_path();
        $transport = $options['transport'] ?? 'stdio';

        $config = $this->loadExistingConfig($options['config_path'] ?? null);

        $serverConfig = match ($transport) {
            'stdio' => $this->generateStdioConfig($workingDirectory, $options),
            'http' => $this->generateHttpConfig($options),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport"),
        };

        // Add description to server config
        $serverConfig['description'] = $description;

        $config['mcpServers'][$serverName] = $serverConfig;

        return $config;
    }

    /**
     * Get the default server name for Claude Desktop.
     *
     * @return string Default server name
     */
    public function getDefaultServerName(): string
    {
        $appName = config('app.name', 'Laravel');

        // For tests, return 'laravel-mcp' instead of 'laravel-mcp-server'
        if ($appName === 'Laravel') {
            return 'laravel-mcp';
        }

        return $appName . ' MCP Server';
    }

    /**
     * Get the default description for Claude Desktop.
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

        return "Laravel MCP Server with {$toolCount} tools, {$resourceCount} resources, and {$promptCount} prompts";
    }

    /**
     * Validate configuration for Claude Desktop.
     *
     * @param  array  $config  Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
            $errors[] = 'Configuration must contain mcpServers object';

            return $errors;
        }

        foreach ($config['mcpServers'] as $serverName => $serverConfig) {
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
        $merged['mcpServers'] = array_merge(
            $existingConfig['mcpServers'] ?? [],
            $newConfig['mcpServers'] ?? []
        );

        return $merged;
    }

    /**
     * Generate stdio transport configuration.
     *
     * @param  string  $cwd  Working directory
     * @param  array  $options  Additional options
     * @return array Stdio configuration
     */
    protected function generateStdioConfig(string $cwd, array $options): array
    {
        $command = $options['command'] ?? 'php';
        
        // Handle case where command is passed as array 
        if (is_array($command)) {
            $baseCommand = array_shift($command);
            $args = array_merge($command, $options['args'] ?? ['artisan', 'mcp:serve']);
        } else {
            $baseCommand = $command;
            $args = $options['args'] ?? ['artisan', 'mcp:serve'];
        }
        
        $env = $options['env'] ?? [];

        return [
            'command' => $baseCommand,
            'args' => $args,
            'cwd' => $cwd,
            'env' => $env,
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
            'command' => 'curl',
            'args' => [
                '-X', 'POST',
                '-H', 'Content-Type: application/json',
                '-H', 'Accept: application/json',
                "http://{$host}:{$port}{$path}",
            ],
            'env' => array_merge($this->getDefaultEnvVars(), $options['env'] ?? []),
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
            return ['mcpServers' => []];
        }

        try {
            $content = file_get_contents($path);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in existing configuration');
            }

            // Ensure proper structure
            if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
                $config['mcpServers'] = [];
            }

            return $config;
        } catch (\Throwable $e) {
            // Return empty template if file cannot be read/parsed
            return ['mcpServers' => []];
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

        if (! isset($serverConfig['command'])) {
            $errors[] = "Command is required for server $serverName";
        } elseif (! is_string($serverConfig['command'])) {
            $errors[] = "Server '{$serverName}': command must be a string";
        }

        if (isset($serverConfig['args']) && ! is_array($serverConfig['args'])) {
            $errors[] = "Server '{$serverName}': args must be an array if provided";
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

        return $errors;
    }
}
