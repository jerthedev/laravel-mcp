<?php

namespace JTD\LaravelMCP\Support\Generators;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

/**
 * Configuration generator for ChatGPT Desktop.
 *
 * Generates MCP server configuration for ChatGPT Desktop application,
 * supporting both stdio and HTTP transports with proper formatting.
 */
class ChatGptGenerator implements ClientGeneratorInterface
{
    /**
     * MCP registry instance.
     */
    protected McpRegistry $registry;

    /**
     * Create a new ChatGPT generator instance.
     *
     * @param  McpRegistry  $registry  MCP registry instance
     */
    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Generate configuration for ChatGPT Desktop.
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

        // Add metadata
        $serverConfig['name'] = $serverName;
        $serverConfig['description'] = $options['description'] ?? $this->getDefaultDescription();
        $serverConfig['transport'] = $transport;
        $serverConfig['version'] = '1.0.0';

        $config['mcp_servers'][] = $serverConfig;

        return $config;
    }

    /**
     * Get the default server name for ChatGPT Desktop.
     *
     * @return string Default server name
     */
    public function getDefaultServerName(): string
    {
        $appName = config('app.name', 'Laravel');

        if ($appName === 'Laravel') {
            return 'laravel-mcp';
        }

        return $appName.' MCP';
    }

    /**
     * Get the default description for ChatGPT Desktop.
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

        return 'Laravel MCP Server with '.implode(', ', $components);
    }

    /**
     * Validate configuration for ChatGPT Desktop.
     *
     * @param  array  $config  Configuration to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateConfig(array $config): array
    {
        $errors = [];

        if (! isset($config['mcp_servers']) || ! is_array($config['mcp_servers'])) {
            $errors[] = 'Configuration must contain mcp_servers array';

            return $errors;
        }

        foreach ($config['mcp_servers'] as $index => $serverConfig) {
            if (! is_array($serverConfig)) {
                $errors[] = "Server at index {$index} must be an array";

                continue;
            }

            $serverErrors = $this->validateServerConfig($index, $serverConfig);
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
        if (! isset($merged['mcp_servers']) || ! is_array($merged['mcp_servers'])) {
            $merged['mcp_servers'] = [];
        }

        // Merge servers (append new servers to existing ones)
        $merged['mcp_servers'] = array_merge(
            $merged['mcp_servers'],
            $newConfig['mcp_servers'] ?? []
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
        $command = $options['command'] ?? ['php'];
        $args = $options['args'] ?? ['artisan', 'mcp:serve'];
        $cwd = $options['cwd'] ?? base_path();
        $env = $options['env'] ?? [];

        // Ensure command is an array, and merge with args
        if (is_string($command)) {
            $command = [$command];
        }
        
        $fullCommand = array_merge($command, $args);

        return [
            'command' => $fullCommand,
            'working_directory' => $cwd,
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
            'endpoint' => "http://{$host}:{$port}{$path}",
            'method' => 'POST',
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'Laravel-MCP/1.0',
            ], $options['headers'] ?? []),
            'timeout' => $options['timeout'] ?? 30,
            'verify_ssl' => $options['verify_ssl'] ?? false,
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
            'MCP_CLIENT' => 'chatgpt-desktop',
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
            return ['mcp_servers' => []];
        }

        try {
            $content = file_get_contents($path);
            $config = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in existing configuration');
            }

            // Ensure proper structure
            if (! isset($config['mcp_servers']) || ! is_array($config['mcp_servers'])) {
                $config['mcp_servers'] = [];
            }

            return $config;
        } catch (\Throwable $e) {
            // Return empty template if file cannot be read/parsed
            return ['mcp_servers' => []];
        }
    }

    /**
     * Validate individual server configuration.
     *
     * @param  int  $index  Server index
     * @param  array  $serverConfig  Server configuration
     * @return array Array of validation errors
     */
    protected function validateServerConfig(int $index, array $serverConfig): array
    {
        $errors = [];

        if (! isset($serverConfig['name']) || ! is_string($serverConfig['name']) || empty($serverConfig['name'])) {
            $errors[] = "Server at index {$index}: name is required and must be a non-empty string";
        }

        if (! isset($serverConfig['transport'])) {
            $errors[] = "Server at index {$index}: transport is required";
        }

        // Validate based on transport type
        $transport = $serverConfig['transport'] ?? 'stdio';

        if ($transport === 'stdio') {
            if (! isset($serverConfig['executable']) || ! is_string($serverConfig['executable'])) {
                $errors[] = "Server at index {$index}: executable is required for stdio transport";
            }

            if (isset($serverConfig['args']) && ! is_array($serverConfig['args'])) {
                $errors[] = "Server at index {$index}: args must be an array if provided";
            }

            if (isset($serverConfig['working_directory']) && (! is_string($serverConfig['working_directory']) || ! is_dir($serverConfig['working_directory']))) {
                $errors[] = "Server at index {$index}: working_directory must be a valid directory path";
            }

            if (isset($serverConfig['environment']) && ! is_array($serverConfig['environment'])) {
                $errors[] = "Server at index {$index}: environment must be an array if provided";
            }
        } elseif ($transport === 'http') {
            if (! isset($serverConfig['endpoint']) || ! is_string($serverConfig['endpoint'])) {
                $errors[] = "Server at index {$index}: endpoint is required for HTTP transport";
            }

            if (isset($serverConfig['method']) && ! in_array(strtoupper($serverConfig['method']), ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])) {
                $errors[] = "Server at index {$index}: method must be a valid HTTP method";
            }

            if (isset($serverConfig['headers']) && ! is_array($serverConfig['headers'])) {
                $errors[] = "Server at index {$index}: headers must be an array if provided";
            }
        } else {
            $errors[] = "Server at index {$index}: unsupported transport type '{$transport}'";
        }

        return $errors;
    }
}
