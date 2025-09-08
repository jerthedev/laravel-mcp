# Client Registration Specification

## Overview

The Client Registration system provides automated configuration generation for popular AI clients (Claude Desktop, Claude Code, ChatGPT Desktop) and manages the integration between Laravel MCP servers and these clients.

## Supported Clients

### Client Support Matrix
```
| Client           | Transport | Config Format | Status    | Notes                    |
|------------------|-----------|---------------|-----------|--------------------------|
| Claude Desktop   | Stdio     | JSON          | Supported | Primary target          |
| Claude Code      | HTTP/Stdio| JSON          | Supported | IDE integration         |
| ChatGPT Desktop  | Stdio     | JSON          | Planned   | Future support          |
| Custom Clients   | Both      | Various       | Supported | Flexible configuration  |
```

## Configuration Generator

### Base Configuration Generator
```php
<?php

namespace JTD\LaravelMCP\Support;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Exceptions\ConfigurationException;

class ConfigGenerator
{
    private McpRegistry $registry;
    private array $clientGenerators = [];

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
        $this->registerClientGenerators();
    }

    public function generateConfig(string $client, array $options = []): array
    {
        if (!isset($this->clientGenerators[$client])) {
            throw new ConfigurationException("Unsupported client: $client");
        }

        $generator = $this->clientGenerators[$client];
        return $generator->generate($options);
    }

    public function writeConfig(string $client, string $path, array $options = []): bool
    {
        $config = $this->generateConfig($client, $options);
        $content = $this->formatConfig($client, $config);
        
        return file_put_contents($path, $content) !== false;
    }

    public function getDefaultConfigPath(string $client): ?string
    {
        return match ($client) {
            'claude-desktop' => $this->getClaudeDesktopConfigPath(),
            'claude-code' => $this->getClaudeCodeConfigPath(),
            'chatgpt-desktop' => $this->getChatGptConfigPath(),
            default => null,
        };
    }

    private function registerClientGenerators(): void
    {
        $this->clientGenerators = [
            'claude-desktop' => new ClaudeDesktopGenerator($this->registry),
            'claude-code' => new ClaudeCodeGenerator($this->registry),
            'chatgpt-desktop' => new ChatGptGenerator($this->registry),
        ];
    }

    private function formatConfig(string $client, array $config): string
    {
        return match ($client) {
            'claude-desktop', 'claude-code', 'chatgpt-desktop' => 
                json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            default => json_encode($config),
        };
    }

    private function getClaudeDesktopConfigPath(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/Claude/claude_desktop_config.json',
            'Linux' => $_SERVER['HOME'] . '/.config/claude/claude_desktop_config.json',
            'Windows' => $_SERVER['APPDATA'] . '\\Claude\\claude_desktop_config.json',
            default => throw new ConfigurationException('Unsupported operating system'),
        };
    }

    private function getClaudeCodeConfigPath(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/Claude Code/config.json',
            'Linux' => $_SERVER['HOME'] . '/.config/claude-code/config.json',
            'Windows' => $_SERVER['APPDATA'] . '\\Claude Code\\config.json',
            default => throw new ConfigurationException('Unsupported operating system'),
        };
    }

    private function getChatGptConfigPath(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => $_SERVER['HOME'] . '/Library/Application Support/ChatGPT/config.json',
            'Linux' => $_SERVER['HOME'] . '/.config/chatgpt/config.json',
            'Windows' => $_SERVER['APPDATA'] . '\\ChatGPT\\config.json',
            default => throw new ConfigurationException('Unsupported operating system'),
        };
    }
}
```

## Client-Specific Generators

### Claude Desktop Generator
```php
<?php

namespace JTD\LaravelMCP\Support\Generators;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

class ClaudeDesktopGenerator implements ClientGeneratorInterface
{
    private McpRegistry $registry;

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function generate(array $options = []): array
    {
        $serverName = $options['name'] ?? $this->getDefaultServerName();
        $description = $options['description'] ?? $this->getDefaultDescription();
        $workingDirectory = $options['cwd'] ?? base_path();
        $transport = $options['transport'] ?? 'stdio';
        
        $config = $this->loadExistingConfig($options['config_path'] ?? null);
        
        $serverConfig = match ($transport) {
            'stdio' => $this->generateStdioConfig($workingDirectory, $options),
            'http' => $this->generateHttpConfig($options),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport"),
        };

        $config['mcpServers'][$serverName] = array_merge($serverConfig, [
            'description' => $description,
        ]);

        return $config;
    }

    private function generateStdioConfig(string $cwd, array $options): array
    {
        return [
            'command' => $options['command'] ?? 'php',
            'args' => $options['args'] ?? ['artisan', 'mcp:serve'],
            'cwd' => $cwd,
            'env' => array_merge($this->getDefaultEnvVars(), $options['env'] ?? []),
        ];
    }

    private function generateHttpConfig(array $options): array
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 8000;
        
        return [
            'command' => 'curl',
            'args' => [
                '-X', 'POST',
                '-H', 'Content-Type: application/json',
                "http://$host:$port/mcp",
            ],
        ];
    }

    private function getDefaultServerName(): string
    {
        return config('app.name', 'Laravel') . ' MCP Server';
    }

    private function getDefaultDescription(): string
    {
        $toolCount = count($this->registry->getTools());
        $resourceCount = count($this->registry->getResources());
        $promptCount = count($this->registry->getPrompts());

        return "Laravel MCP Server with {$toolCount} tools, {$resourceCount} resources, and {$promptCount} prompts";
    }

    private function getDefaultEnvVars(): array
    {
        return [
            'APP_ENV' => config('app.env'),
            'MCP_TRANSPORT' => 'stdio',
        ];
    }

    private function loadExistingConfig(?string $path): array
    {
        if (!$path) {
            return ['mcpServers' => []];
        }

        if (!file_exists($path)) {
            return ['mcpServers' => []];
        }

        $content = file_get_contents($path);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['mcpServers' => []];
        }

        return $config;
    }
}
```

### Claude Code Generator
```php
<?php

namespace JTD\LaravelMCP\Support\Generators;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

class ClaudeCodeGenerator implements ClientGeneratorInterface
{
    private McpRegistry $registry;

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function generate(array $options = []): array
    {
        $serverName = $options['name'] ?? $this->getDefaultServerName();
        $transport = $options['transport'] ?? 'stdio';
        
        $config = $this->loadExistingConfig($options['config_path'] ?? null);
        
        $serverConfig = match ($transport) {
            'stdio' => $this->generateStdioConfig($options),
            'http' => $this->generateHttpConfig($options),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport"),
        };

        $config['mcp']['servers'][$serverName] = $serverConfig;

        return $config;
    }

    private function generateStdioConfig(array $options): array
    {
        return [
            'command' => array_merge(
                [$options['command'] ?? 'php'],
                $options['args'] ?? ['artisan', 'mcp:serve']
            ),
            'cwd' => $options['cwd'] ?? base_path(),
            'env' => array_merge($this->getDefaultEnvVars(), $options['env'] ?? []),
        ];
    }

    private function generateHttpConfig(array $options): array
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 8000;
        
        return [
            'url' => "http://$host:$port/mcp",
            'headers' => array_merge([
                'Content-Type' => 'application/json',
            ], $options['headers'] ?? []),
        ];
    }

    private function getDefaultServerName(): string
    {
        return Str::slug(config('app.name', 'laravel') . '-mcp');
    }

    private function getDefaultEnvVars(): array
    {
        return [
            'APP_ENV' => config('app.env'),
            'MCP_TRANSPORT' => 'stdio',
        ];
    }

    private function loadExistingConfig(?string $path): array
    {
        if (!$path) {
            return ['mcp' => ['servers' => []]];
        }

        if (!file_exists($path)) {
            return ['mcp' => ['servers' => []]];
        }

        $content = file_get_contents($path);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['mcp' => ['servers' => []]];
        }

        return $config;
    }
}
```

### ChatGPT Desktop Generator
```php
<?php

namespace JTD\LaravelMCP\Support\Generators;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Contracts\ClientGeneratorInterface;

class ChatGptGenerator implements ClientGeneratorInterface
{
    private McpRegistry $registry;

    public function __construct(McpRegistry $registry)
    {
        $this->registry = $registry;
    }

    public function generate(array $options = []): array
    {
        $serverName = $options['name'] ?? $this->getDefaultServerName();
        $transport = $options['transport'] ?? 'stdio';
        
        $config = $this->loadExistingConfig($options['config_path'] ?? null);
        
        $serverConfig = match ($transport) {
            'stdio' => $this->generateStdioConfig($options),
            'http' => $this->generateHttpConfig($options),
            default => throw new \InvalidArgumentException("Unsupported transport: $transport"),
        };

        $config['mcp_servers'][] = array_merge($serverConfig, [
            'name' => $serverName,
            'description' => $options['description'] ?? $this->getDefaultDescription(),
        ]);

        return $config;
    }

    private function generateStdioConfig(array $options): array
    {
        return [
            'executable' => $options['command'] ?? 'php',
            'args' => $options['args'] ?? ['artisan', 'mcp:serve'],
            'working_directory' => $options['cwd'] ?? base_path(),
            'environment' => array_merge($this->getDefaultEnvVars(), $options['env'] ?? []),
        ];
    }

    private function generateHttpConfig(array $options): array
    {
        $host = $options['host'] ?? '127.0.0.1';
        $port = $options['port'] ?? 8000;
        
        return [
            'endpoint' => "http://$host:$port/mcp",
            'method' => 'POST',
            'headers' => array_merge([
                'Content-Type' => 'application/json',
            ], $options['headers'] ?? []),
        ];
    }

    private function getDefaultServerName(): string
    {
        return config('app.name', 'Laravel') . ' MCP';
    }

    private function getDefaultDescription(): string
    {
        return 'Laravel MCP Server providing tools, resources, and prompts';
    }

    private function getDefaultEnvVars(): array
    {
        return [
            'APP_ENV' => config('app.env'),
            'MCP_TRANSPORT' => 'stdio',
        ];
    }

    private function loadExistingConfig(?string $path): array
    {
        if (!$path) {
            return ['mcp_servers' => []];
        }

        if (!file_exists($path)) {
            return ['mcp_servers' => []];
        }

        $content = file_get_contents($path);
        $config = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['mcp_servers' => []];
        }

        return $config;
    }
}
```

## Registration Command Implementation

### Enhanced Registration Command
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Registry\McpRegistry;

class RegisterCommand extends Command
{
    protected $signature = 'mcp:register
                           {client : Client type (claude-desktop|claude-code|chatgpt)}
                           {--name= : Server name}
                           {--description= : Server description}
                           {--transport=stdio : Transport type (stdio|http)}
                           {--cwd= : Working directory}
                           {--command=php : Command to execute}
                           {--args=* : Additional command arguments}
                           {--env=* : Environment variables}
                           {--host=127.0.0.1 : HTTP host}
                           {--port=8000 : HTTP port}
                           {--output= : Output configuration file}
                           {--force : Overwrite existing configuration}
                           {--dry-run : Show configuration without writing}';

    protected $description = 'Register MCP server with AI clients';

    private ConfigGenerator $configGenerator;
    private McpRegistry $registry;

    public function __construct(ConfigGenerator $configGenerator, McpRegistry $registry)
    {
        parent::__construct();
        $this->configGenerator = $configGenerator;
        $this->registry = $registry;
    }

    public function handle(): int
    {
        $client = $this->argument('client');
        
        if (!$this->isClientSupported($client)) {
            $this->error("Unsupported client: $client");
            return 1;
        }

        try {
            $options = $this->gatherOptions();
            $configPath = $this->determineConfigPath($client, $options);
            
            if ($this->option('dry-run')) {
                return $this->showDryRun($client, $options);
            }

            return $this->registerServer($client, $configPath, $options);
        } catch (\Throwable $e) {
            $this->error("Registration failed: {$e->getMessage()}");
            return 1;
        }
    }

    private function isClientSupported(string $client): bool
    {
        return in_array($client, ['claude-desktop', 'claude-code', 'chatgpt-desktop']);
    }

    private function gatherOptions(): array
    {
        return array_filter([
            'name' => $this->option('name') ?? $this->askForServerName(),
            'description' => $this->option('description') ?? $this->askForDescription(),
            'transport' => $this->option('transport'),
            'cwd' => $this->option('cwd') ?? base_path(),
            'command' => $this->option('command'),
            'args' => $this->option('args') ?: ['artisan', 'mcp:serve'],
            'env' => $this->parseEnvVars($this->option('env')),
            'host' => $this->option('host'),
            'port' => $this->option('port'),
        ]);
    }

    private function determineConfigPath(string $client, array $options): string
    {
        if ($this->option('output')) {
            return $this->option('output');
        }

        $defaultPath = $this->configGenerator->getDefaultConfigPath($client);
        
        if (!$defaultPath) {
            throw new \RuntimeException("Cannot determine config path for client: $client");
        }

        return $defaultPath;
    }

    private function registerServer(string $client, string $configPath, array $options): int
    {
        $this->info("Registering MCP server with $client...");
        
        // Check if config file exists and handle backup
        if (file_exists($configPath) && !$this->option('force')) {
            if (!$this->confirm("Configuration file exists. Overwrite?")) {
                $this->info('Registration cancelled.');
                return 0;
            }
        }

        // Create backup if file exists
        if (file_exists($configPath)) {
            $backupPath = $configPath . '.backup.' . date('Y-m-d-H-i-s');
            copy($configPath, $backupPath);
            $this->info("Backup created: $backupPath");
        }

        // Ensure directory exists
        $directory = dirname($configPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Generate and write configuration
        $success = $this->configGenerator->writeConfig($client, $configPath, $options);
        
        if (!$success) {
            $this->error("Failed to write configuration file: $configPath");
            return 1;
        }

        $this->info("Successfully registered MCP server!");
        $this->info("Configuration written to: $configPath");
        
        $this->showServerInfo($options);
        $this->showNextSteps($client);

        return 0;
    }

    private function showDryRun(string $client, array $options): int
    {
        $this->info("Dry run - Configuration for $client:");
        
        $config = $this->configGenerator->generateConfig($client, $options);
        $this->line(json_encode($config, JSON_PRETTY_PRINT));
        
        return 0;
    }

    private function askForServerName(): string
    {
        return $this->ask('Server name', config('app.name', 'Laravel') . ' MCP Server');
    }

    private function askForDescription(): string
    {
        $toolCount = count($this->registry->getTools());
        $resourceCount = count($this->registry->getResources());
        $promptCount = count($this->registry->getPrompts());
        
        $default = "Laravel MCP Server with {$toolCount} tools, {$resourceCount} resources, and {$promptCount} prompts";
        
        return $this->ask('Description', $default);
    }

    private function parseEnvVars(array $envVars): array
    {
        $parsed = [];
        
        foreach ($envVars as $env) {
            if (strpos($env, '=') !== false) {
                [$key, $value] = explode('=', $env, 2);
                $parsed[$key] = $value;
            }
        }
        
        return $parsed;
    }

    private function showServerInfo(array $options): void
    {
        $this->info('');
        $this->info('Server Information:');
        $this->table(
            ['Setting', 'Value'],
            [
                ['Name', $options['name']],
                ['Transport', $options['transport']],
                ['Working Directory', $options['cwd']],
                ['Command', $options['command'] . ' ' . implode(' ', $options['args'])],
            ]
        );
    }

    private function showNextSteps(string $client): void
    {
        $this->info('');
        $this->info('Next Steps:');
        
        match ($client) {
            'claude-desktop' => $this->showClaudeDesktopSteps(),
            'claude-code' => $this->showClaudeCodeSteps(),
            'chatgpt-desktop' => $this->showChatGptSteps(),
            default => null,
        };
    }

    private function showClaudeDesktopSteps(): void
    {
        $this->line('1. Restart Claude Desktop');
        $this->line('2. Your MCP server should appear in the available tools');
        $this->line('3. Test the connection by asking Claude to list available tools');
    }

    private function showClaudeCodeSteps(): void
    {
        $this->line('1. Restart Claude Code');
        $this->line('2. Open a project and check the MCP panel');
        $this->line('3. Your server should be listed as connected');
    }

    private function showChatGptSteps(): void
    {
        $this->line('1. Restart ChatGPT Desktop');
        $this->line('2. Look for MCP tools in the interface');
        $this->line('3. Test the connection');
    }
}
```

## Configuration Validation

### Configuration Validator
```php
<?php

namespace JTD\LaravelMCP\Support;

class ConfigValidator
{
    public function validate(string $client, array $config): array
    {
        $errors = [];
        
        $errors = array_merge($errors, $this->validateCommon($config));
        $errors = array_merge($errors, $this->validateClient($client, $config));
        
        return $errors;
    }

    private function validateCommon(array $config): array
    {
        $errors = [];
        
        if (empty($config['name'])) {
            $errors[] = 'Server name is required';
        }
        
        return $errors;
    }

    private function validateClient(string $client, array $config): array
    {
        return match ($client) {
            'claude-desktop' => $this->validateClaudeDesktop($config),
            'claude-code' => $this->validateClaudeCode($config),
            'chatgpt-desktop' => $this->validateChatGpt($config),
            default => ['Unknown client type'],
        };
    }

    private function validateClaudeDesktop(array $config): array
    {
        $errors = [];
        
        if (empty($config['command'])) {
            $errors[] = 'Command is required for Claude Desktop';
        }
        
        if (empty($config['cwd']) || !is_dir($config['cwd'])) {
            $errors[] = 'Valid working directory is required';
        }
        
        return $errors;
    }

    private function validateClaudeCode(array $config): array
    {
        // Similar validation for Claude Code
        return [];
    }

    private function validateChatGpt(array $config): array
    {
        // Similar validation for ChatGPT
        return [];
    }
}
```