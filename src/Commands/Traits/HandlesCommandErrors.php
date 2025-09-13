<?php

namespace JTD\LaravelMCP\Commands\Traits;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Provides error handling functionality for MCP commands.
 *
 * This trait includes methods for handling exceptions, validating input,
 * and confirming destructive actions with consistent error reporting.
 */
trait HandlesCommandErrors
{
    /**
     * Handle command errors with proper formatting and debugging support.
     *
     * @param  \Throwable  $exception  The exception to handle
     * @param  string|null  $context  Additional context for the error
     * @return int Exit code
     */
    protected function handleError(\Throwable $exception, ?string $context = null): int
    {
        $message = $exception->getMessage();

        if ($context) {
            $message = "$context: $message";
        }

        $this->error($message);

        // Show stack trace in debug or verbose mode
        if ($this->isDebugMode() || $this->isVerboseMode()) {
            $this->newLine();
            $this->error('Stack trace:');
            $this->line($exception->getTraceAsString());

            // Show previous exceptions if available
            if ($previous = $exception->getPrevious()) {
                $this->newLine();
                $this->error('Previous exception:');
                $this->error($previous->getMessage());

                if ($this->output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $this->line($previous->getTraceAsString());
                }
            }
        } else {
            $this->comment('Run with --debug or -v for more details.');
        }

        return $this->getErrorExitCode();
    }

    /**
     * Validate command input and options.
     *
     * This method should be overridden by child classes to implement
     * specific validation logic for their commands.
     *
     * @return bool Whether the input is valid
     */
    protected function validateInput(): bool
    {
        // Default implementation - override in child classes
        return true;
    }

    /**
     * Validate that a required option is present and not empty.
     *
     * @param  string  $option  The option name to validate
     * @param  string|null  $message  Custom error message
     * @return bool Whether the option is valid
     */
    protected function validateRequiredOption(string $option, ?string $message = null): bool
    {
        $value = $this->option($option);

        if (empty($value)) {
            $message = $message ?: "The --$option option is required.";
            $this->displayError($message);

            return false;
        }

        return true;
    }

    /**
     * Validate that an option value is in a list of allowed values.
     *
     * @param  string  $option  The option name to validate
     * @param  array  $allowedValues  List of allowed values
     * @param  string|null  $message  Custom error message
     * @return bool Whether the option value is valid
     */
    protected function validateOptionInList(string $option, array $allowedValues, ?string $message = null): bool
    {
        $value = $this->option($option);

        if ($value && ! in_array($value, $allowedValues)) {
            $message = $message ?: sprintf(
                'Invalid value for --%s. Allowed values: %s',
                $option,
                implode(', ', $allowedValues)
            );
            $this->displayError($message);

            return false;
        }

        return true;
    }

    /**
     * Validate that a numeric option is within a specified range.
     *
     * @param  string  $option  The option name to validate
     * @param  int|null  $min  Minimum allowed value
     * @param  int|null  $max  Maximum allowed value
     * @param  string|null  $message  Custom error message
     * @return bool Whether the option value is valid
     */
    protected function validateNumericOption(string $option, ?int $min = null, ?int $max = null, ?string $message = null): bool
    {
        $value = $this->option($option);

        if ($value === null) {
            return true;
        }

        if (! is_numeric($value)) {
            $this->displayError("The --$option option must be numeric.");

            return false;
        }

        $numericValue = (int) $value;

        if ($min !== null && $numericValue < $min) {
            $message = $message ?: "The --$option option must be at least $min.";
            $this->displayError($message);

            return false;
        }

        if ($max !== null && $numericValue > $max) {
            $message = $message ?: "The --$option option must be no more than $max.";
            $this->displayError($message);

            return false;
        }

        return true;
    }

    /**
     * Validate that a string option matches a pattern.
     *
     * @param  string  $option  The option name to validate
     * @param  string  $pattern  Regular expression pattern
     * @param  string|null  $message  Custom error message
     * @return bool Whether the option value is valid
     */
    protected function validateOptionPattern(string $option, string $pattern, ?string $message = null): bool
    {
        $value = $this->option($option);

        if ($value && ! preg_match($pattern, $value)) {
            $message = $message ?: "The --$option option format is invalid.";
            $this->displayError($message);

            return false;
        }

        return true;
    }

    /**
     * Validate that a required argument is present and not empty.
     *
     * @param  string  $argument  The argument name to validate
     * @param  string|null  $message  Custom error message
     * @return bool Whether the argument is valid
     */
    protected function validateRequiredArgument(string $argument, ?string $message = null): bool
    {
        $value = $this->argument($argument);

        if (empty($value)) {
            $message = $message ?: "The $argument argument is required.";
            $this->displayError($message);

            return false;
        }

        return true;
    }

    /**
     * Confirm a destructive action with the user.
     *
     * @param  string  $message  Confirmation message to display
     * @param  bool  $default  Default response if running non-interactively
     * @return bool Whether the user confirmed the action
     */
    protected function confirmDestructiveAction(string $message, bool $default = false): bool
    {
        // In non-interactive mode, use the default
        if (! $this->input->isInteractive()) {
            return $default;
        }

        // If force option is set, skip confirmation
        if ($this->hasOption('force') && $this->option('force')) {
            return true;
        }

        return $this->confirm($message, $default);
    }

    /**
     * Display a formatted error message.
     *
     * @param  string  $message  Error message
     * @param  array  $details  Optional additional details
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
     * Display a formatted warning message.
     *
     * @param  string  $message  Warning message
     * @param  array  $details  Optional additional details
     */
    protected function displayWarning(string $message, array $details = []): void
    {
        $this->warn("⚠ $message");

        if (! empty($details)) {
            foreach ($details as $key => $value) {
                $this->line("  <comment>$key:</comment> $value");
            }
        }
    }

    /**
     * Check if the command is running in debug mode.
     *
     * @return bool Whether debug mode is enabled
     */
    protected function isDebugMode(): bool
    {
        return $this->hasOption('debug') && $this->option('debug');
    }

    /**
     * Check if the command is running in verbose mode.
     *
     * @return bool Whether verbose mode is enabled
     */
    protected function isVerboseMode(): bool
    {
        return $this->output->isVerbose();
    }

    /**
     * Get the exit code for general errors.
     *
     * @return int Error exit code
     */
    protected function getErrorExitCode(): int
    {
        return defined('static::EXIT_ERROR') ? static::EXIT_ERROR : 1;
    }

    /**
     * Get the exit code for invalid input.
     *
     * @return int Invalid input exit code
     */
    protected function getInvalidInputExitCode(): int
    {
        return defined('static::EXIT_INVALID_INPUT') ? static::EXIT_INVALID_INPUT : 2;
    }

    /**
     * Get the exit code for successful execution.
     *
     * @return int Success exit code
     */
    protected function getSuccessExitCode(): int
    {
        return defined('static::EXIT_SUCCESS') ? static::EXIT_SUCCESS : 0;
    }

    /**
     * Validate multiple options at once.
     *
     * @param  array  $validations  Array of validation configurations
     * @return bool Whether all validations passed
     */
    protected function validateMultipleOptions(array $validations): bool
    {
        $valid = true;

        foreach ($validations as $validation) {
            $option = $validation['option'];
            $type = $validation['type'] ?? 'required';

            $result = match ($type) {
                'required' => $this->validateRequiredOption($option, $validation['message'] ?? null),
                'in_list' => $this->validateOptionInList($option, $validation['values'], $validation['message'] ?? null),
                'numeric' => $this->validateNumericOption($option, $validation['min'] ?? null, $validation['max'] ?? null, $validation['message'] ?? null),
                'pattern' => $this->validateOptionPattern($option, $validation['pattern'], $validation['message'] ?? null),
                default => true,
            };

            if (! $result) {
                $valid = false;
            }
        }

        return $valid;
    }
}
