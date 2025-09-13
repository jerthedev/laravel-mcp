<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Commands\Traits\FormatsOutput;
use JTD\LaravelMCP\Commands\Traits\HandlesCommandErrors;
use JTD\LaravelMCP\Commands\Traits\HandlesConfiguration;

/**
 * Abstract base class for all MCP commands.
 *
 * Provides shared functionality including error handling, output formatting,
 * validation methods, and configuration access helpers.
 */
abstract class BaseCommand extends Command
{
    use FormatsOutput, HandlesCommandErrors, HandlesConfiguration;

    /**
     * The exit code for successful execution.
     */
    protected const EXIT_SUCCESS = 0;

    /**
     * The exit code for general errors.
     */
    protected const EXIT_ERROR = 1;

    /**
     * The exit code for invalid input.
     */
    protected const EXIT_INVALID_INPUT = 2;

    /**
     * Display a success message with consistent formatting.
     *
     * @param  array  $details  Optional details to display
     */
    protected function success(string $message, array $details = []): void
    {
        $this->info("✓ $message");

        if (! empty($details)) {
            foreach ($details as $key => $value) {
                $this->line("  <comment>$key:</comment> $value");
            }
        }
    }

    /**
     * Display a warning message with consistent formatting.
     *
     * @param  array  $details  Optional details to display
     */
    protected function warning(string $message, array $details = []): void
    {
        $this->warn("⚠ $message");

        if (! empty($details)) {
            foreach ($details as $key => $value) {
                $this->line("  <comment>$key:</comment> $value");
            }
        }
    }

    /**
     * Display an error message with consistent formatting.
     *
     * @param  array  $details  Optional details to display
     */
    protected function displayError(string $message, array $details = []): void
    {
        $this->error("✗ $message");

        if (! empty($details)) {
            foreach ($details as $key => $value) {
                $this->line("  <comment>$key:</comment> $value", 'error');
            }
        }
    }

    /**
     * Display a status message during long-running operations.
     */
    protected function status(string $message): void
    {
        $this->line("<fg=cyan>→</> $message");
    }

    /**
     * Display a debug message if debug mode is enabled.
     *
     * @param  mixed  $data  Optional data to display
     */
    protected function debug(string $message, $data = null): void
    {
        $debugEnabled = ($this->hasOption('debug') && $this->option('debug')) || $this->output->isVerbose();

        if ($debugEnabled) {
            $this->line("[DEBUG] $message");

            if ($data !== null) {
                $formatted = is_string($data) ? $data : json_encode($data, JSON_PRETTY_PRINT);
                $this->line($formatted);
            }
        }
    }

    /**
     * Check if MCP is enabled.
     */
    protected function isMcpEnabled(): bool
    {
        return (bool) config('laravel-mcp.enabled', true);
    }

    /**
     * Get the default transport type.
     */
    protected function getDefaultTransport(): string
    {
        return config('laravel-mcp.transports.default', 'stdio');
    }

    /**
     * Check if a specific transport is enabled.
     */
    protected function isTransportEnabled(string $transport): bool
    {
        return (bool) config("mcp-transports.{$transport}.enabled", false);
    }

    /**
     * Get discovery paths for MCP components.
     */
    protected function getDiscoveryPaths(): array
    {
        return config('laravel-mcp.discovery.paths', [
            app_path('Mcp/Tools'),
            app_path('Mcp/Resources'),
            app_path('Mcp/Prompts'),
        ]);
    }

    /**
     * Display a section header for better output organization.
     */
    protected function sectionHeader(string $title): void
    {
        $this->newLine();
        $this->line("<fg=cyan;options=bold>$title</>");
        $this->line(str_repeat('─', strlen($title)));
    }

    /**
     * Display command execution time if in verbose mode.
     */
    protected function displayExecutionTime(float $startTime): void
    {
        if ($this->output->isVerbose()) {
            $executionTime = round(microtime(true) - $startTime, 3);
            $this->newLine();
            $this->comment("Execution time: {$executionTime}s");
        }
    }

    /**
     * Create a progress bar for long-running operations.
     */
    protected function createProgressBar(int $max, ?string $message = null): \Symfony\Component\Console\Helper\ProgressBar
    {
        $progressBar = $this->output->createProgressBar($max);

        if ($message) {
            $progressBar->setMessage($message);
            $progressBar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
        }

        return $progressBar;
    }

    /**
     * Check if the command should run in quiet mode.
     */
    protected function isQuiet(): bool
    {
        return $this->output->isQuiet();
    }

    /**
     * Check if the command should run in verbose mode.
     */
    protected function isVerbose(): bool
    {
        return $this->output->isVerbose();
    }

    /**
     * Handle command execution with consistent error handling.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        try {
            $this->debug('Starting command execution', [
                'command' => get_class($this),
                'arguments' => $this->arguments(),
                'options' => $this->options(),
            ]);

            // Check if MCP is enabled
            $mcpEnabled = $this->isMcpEnabled();
            if (! $mcpEnabled) {
                $this->displayError('MCP is disabled. Enable it by setting MCP_ENABLED=true in your .env file.');

                return self::EXIT_ERROR;
            }

            $this->debug('MCP is enabled, proceeding with validation');

            // Validate input
            if (! $this->validateInput()) {
                $this->debug('Input validation failed');

                return self::EXIT_INVALID_INPUT;
            }

            $this->debug('Input validation passed, executing command');

            // Execute the command logic
            $result = $this->executeCommand();

            $this->debug('Command execution completed', ['result' => $result]);

            // Display execution time in verbose mode
            $this->displayExecutionTime($startTime);

            return $result;
        } catch (\Throwable $e) {
            $this->debug('Exception caught in handle method', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->handleError($e);
        }
    }

    /**
     * Execute the command logic.
     *
     * This method must be implemented by child classes.
     *
     * @return int Exit code
     */
    abstract protected function executeCommand(): int;
}
