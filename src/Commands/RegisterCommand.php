<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\ConfigGenerator;

/**
 * Register MCP server configuration for AI clients.
 *
 * This command generates and registers MCP server configuration for various
 * AI clients including Claude Desktop, Claude Code, and ChatGPT Desktop.
 */
class RegisterCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:register 
        {client : Client type (claude-desktop|claude-code|chatgpt)} 
        {--name= : Server name} 
        {--description= : Server description} 
        {--path= : Custom server path} 
        {--args=* : Additional arguments} 
        {--env-var=* : Environment variables} 
        {--output= : Output configuration file} 
        {--force : Overwrite existing configuration}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register MCP server configuration for AI clients';

    /**
     * Configuration generator instance.
     */
    protected ConfigGenerator $configGenerator;

    /**
     * Valid client types.
     */
    protected array $validClients = ['claude-desktop', 'claude-code', 'chatgpt'];

    /**
     * Create a new command instance.
     */
    public function __construct(ConfigGenerator $configGenerator)
    {
        parent::__construct();
        $this->configGenerator = $configGenerator;
    }

    /**
     * Execute the console command.
     */
    protected function executeCommand(): int
    {
        $client = $this->argument('client');

        // Validate client type
        if (! $this->validateClient($client)) {
            return self::EXIT_INVALID_INPUT;
        }

        $this->sectionHeader("Registering MCP Server for $client");

        // Determine output path first
        $outputPath = $this->determineOutputPath($client, []);

        // Gather configuration options (including output path for existing config loading)
        $options = $this->gatherConfigurationOptions($client);
        $options['config_path'] = $outputPath; // Pass the output path for existing config loading

        // Generate configuration
        $config = $this->generateConfiguration($client, $options);

        // Save configuration
        if ($this->saveConfiguration($config, $outputPath, $client)) {
            $this->success('MCP server registered successfully!', [
                'Client' => $client,
                'Server Name' => $options['name'],
                'Configuration File' => $outputPath,
            ]);

            $this->displayNextSteps($client);

            return self::EXIT_SUCCESS;
        }

        return self::EXIT_ERROR;
    }

    /**
     * Validate command input.
     */
    protected function validateInput(): bool
    {
        $client = $this->argument('client');

        if (! $this->validateClient($client)) {
            return false;
        }

        return true;
    }

    /**
     * Validate client type.
     */
    protected function validateClient(string $client): bool
    {
        if (! in_array($client, $this->validClients)) {
            $this->displayError(
                "Invalid client type: $client",
                ['Valid clients' => implode(', ', $this->validClients)]
            );

            return false;
        }

        return true;
    }

    /**
     * Gather configuration options from user input or prompts.
     */
    protected function gatherConfigurationOptions(string $client): array
    {
        $serverCommand = $this->getServerCommand();
        
        $options = [
            'name' => $this->getServerName(),
            'description' => $this->getServerDescription($client),
            'command' => array_shift($serverCommand), // Get the command (first element)
            'args' => array_merge($serverCommand, $this->getAdditionalArgs()), // Remaining elements + additional args
            'env' => $this->getEnvironmentVariables(),
        ];

        $this->debug('Configuration options gathered', $options);

        return $options;
    }

    /**
     * Get server name from option or prompt.
     */
    protected function getServerName(): string
    {
        $name = $this->option('name');

        if (! $name) {
            // In testing or non-interactive mode, use the default
            if (! $this->input->isInteractive() || app()->environment('testing')) {
                $name = 'laravel-mcp';
            } else {
                $name = $this->ask(
                    'What should we name this MCP server?',
                    'laravel-mcp'
                );
            }
        }

        return $name;
    }

    /**
     * Get server description from option or prompt.
     */
    protected function getServerDescription(string $client): string
    {
        $description = $this->option('description');

        if (! $description) {
            $default = 'Laravel MCP Server - providing tools, resources, and prompts';
            
            // In testing or non-interactive mode, use the default
            if (! $this->input->isInteractive() || app()->environment('testing')) {
                $description = $default;
            } else {
                $description = $this->ask(
                    'Enter a description for this MCP server',
                    $default
                );
            }
        }

        return $description;
    }

    /**
     * Get server command path.
     */
    protected function getServerCommand(): array
    {
        $customPath = $this->option('path');

        if ($customPath) {
            return ['php', $customPath, 'mcp:serve'];
        }

        // Default to current artisan location
        return ['php', 'artisan', 'mcp:serve'];
    }

    /**
     * Get additional arguments.
     */
    protected function getAdditionalArgs(): array
    {
        $args = $this->option('args') ?? [];

        if (empty($args) && $this->input->isInteractive() && ! app()->environment('testing')) {
            $additionalArgs = $this->ask(
                'Any additional arguments? (comma-separated, or press Enter to skip)',
                ''
            );

            if ($additionalArgs) {
                $args = array_map('trim', explode(',', $additionalArgs));
            }
        }

        return $args;
    }

    /**
     * Get environment variables.
     */
    protected function getEnvironmentVariables(): array
    {
        $envVars = $this->option('env-var') ?? [];
        $env = [];

        // Parse env vars in KEY=VALUE format
        foreach ($envVars as $envVar) {
            if (strpos($envVar, '=') !== false) {
                [$key, $value] = explode('=', $envVar, 2);
                $env[trim($key)] = trim($value);
            }
        }

        // Interactive mode - ask for common env vars
        if (empty($env) && $this->input->isInteractive() && ! app()->environment('testing')) {
            if ($this->confirm('Do you want to set any environment variables?', false)) {
                $env = $this->promptForEnvironmentVariables();
            }
        }

        return $env;
    }

    /**
     * Prompt for environment variables interactively.
     */
    protected function promptForEnvironmentVariables(): array
    {
        $env = [];
        $commonVars = ['APP_ENV', 'MCP_DEBUG', 'MCP_LOG_LEVEL'];

        foreach ($commonVars as $var) {
            $value = $this->ask("Set $var (or press Enter to skip)", '');
            if ($value) {
                $env[$var] = $value;
            }
        }

        // Allow custom variables
        while ($this->confirm('Add another environment variable?', false)) {
            $key = $this->ask('Environment variable name');
            $value = $this->ask('Environment variable value');

            if ($key && $value) {
                $env[$key] = $value;
            }
        }

        return $env;
    }

    /**
     * Generate configuration for the specified client.
     */
    protected function generateConfiguration(string $client, array $options): array
    {
        $this->status("Generating $client configuration...");

        switch ($client) {
            case 'claude-desktop':
                return $this->configGenerator->generateClaudeDesktopConfig($options);

            case 'claude-code':
                return $this->configGenerator->generateClaudeCodeConfig($options);

            case 'chatgpt':
                return $this->configGenerator->generateChatGptDesktopConfig($options);

            default:
                throw new \InvalidArgumentException("Unsupported client: $client");
        }
    }

    /**
     * Determine output path for configuration file.
     */
    protected function determineOutputPath(string $client, array $options): string
    {
        // Use custom output path if provided
        if ($customOutput = $this->option('output')) {
            return $customOutput;
        }

        // Try to get default client config path
        $defaultPath = $this->configGenerator->getClientConfigPath($client);

        if ($defaultPath) {
            return $defaultPath;
        }

        // Fallback to current directory
        $fallbackPath = getcwd()."/{$client}_config.json";

        $this->warning('Could not determine default config path');
        $this->warning(
            "Could not determine default config path for $client",
            ['Using fallback path' => $fallbackPath]
        );

        return $fallbackPath;
    }

    /**
     * Save configuration to file.
     */
    protected function saveConfiguration(array $config, string $path, string $client): bool
    {
        $this->status("Saving configuration to: $path");

        // Check if file exists and handle overwrite
        if (File::exists($path) && ! $this->option('force')) {
            if (! $this->confirmOverwrite($path)) {
                $this->warning('Configuration not saved - file exists');

                return false;
            }
        }

        // Validate configuration before saving
        $errors = $this->configGenerator->validateClientConfig($client, $config);
        if (! empty($errors)) {
            $this->displayError('Configuration validation failed:', $errors);

            return false;
        }

        // Check if we merged with existing configuration
        if (File::exists($path)) {
            try {
                $existingContent = File::get($path);
                $existingConfig = json_decode($existingContent, true) ?? [];

                // Check if existing config had servers
                if (! empty($existingConfig['mcpServers'])) {
                    $this->info('Merged with existing configuration');
                }
            } catch (\Exception $e) {
                $this->debug('Could not read existing config file', ['error' => $e->getMessage()]);
            }
        }

        // Save configuration
        try {
            return $this->configGenerator->saveClientConfig($config, $path, true);
        } catch (\Exception $e) {
            $this->error('✗ Failed to save configuration');
            $this->displayError('Failed to save configuration', [
                'Path' => $path,
                'Error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Confirm file overwrite.
     */
    protected function confirmOverwrite(string $path): bool
    {
        return $this->confirmDestructiveAction(
            "Configuration file already exists at $path. Overwrite?",
            false
        );
    }

    /**
     * Display next steps after successful registration.
     */
    protected function displayNextSteps(string $client): void
    {
        $this->newLine();
        $this->sectionHeader('Next Steps');

        switch ($client) {
            case 'claude-desktop':
                $this->line('Restart Claude Desktop application');
                $this->line('The MCP server will be available in Claude Desktop');
                $this->line('Test the connection by starting a conversation');
                break;

            case 'claude-code':
                $this->line('Restart VS Code or reload the Claude extension');
                $this->line('The MCP server will be available in Claude Code');
                $this->line('Use MCP tools and resources in your coding workflow');
                break;

            case 'chatgpt':
                $this->line('Restart ChatGPT Desktop application');
                $this->line('The MCP server will be available in ChatGPT');
                $this->line('Access MCP tools and resources through chat');
                break;
        }

        $this->newLine();
        $this->comment('Run "php artisan mcp:serve" to start the MCP server');
        $this->comment('Run "php artisan mcp:list" to see available components');
    }
}
