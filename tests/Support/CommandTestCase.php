<?php

namespace JTD\LaravelMCP\Tests\Support;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\PendingCommand;

/**
 * Base test case for MCP command testing.
 *
 * Provides common functionality for testing Artisan commands including
 * command execution helpers, output assertion methods, and success/failure validation.
 */
abstract class CommandTestCase extends BaseTestCase
{
    /**
     * Execute an Artisan command and return the exit code.
     *
     * @param  string  $command  The command name
     * @param  array  $parameters  Command parameters and options
     * @return int The command exit code
     */
    protected function artisan(string $command, array $parameters = []): int
    {
        $result = $this->app['artisan']->call($command, $parameters);

        return $result;
    }

    /**
     * Execute an Artisan command using the Laravel testing helper.
     *
     * @param  string  $command  The command name
     * @param  array  $parameters  Command parameters and options
     * @return PendingCommand The pending command instance for chaining
     */
    protected function artisanCommand(string $command, array $parameters = []): PendingCommand
    {
        return $this->artisan($command, $parameters);
    }

    /**
     * Get the output from the last executed Artisan command.
     *
     * @return string The command output
     */
    protected function artisanOutput(): string
    {
        return $this->app['artisan']->output();
    }

    /**
     * Get the last exit code from an executed Artisan command.
     *
     * @return int The exit code
     */
    protected function getLastExitCode(): int
    {
        return $this->app['artisan']->getLastExitCode();
    }

    /**
     * Assert that the last command executed successfully.
     *
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertCommandSuccessful(?string $message = null): void
    {
        $exitCode = $this->getLastExitCode();
        $output = $this->artisanOutput();

        $this->assertEquals(0, $exitCode, $message ?: "Command failed with exit code {$exitCode}. Output: {$output}");
    }

    /**
     * Assert that the last command failed.
     *
     * @param  int|null  $expectedExitCode  Expected exit code (default: any non-zero)
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertCommandFailed(?int $expectedExitCode = null, ?string $message = null): void
    {
        $exitCode = $this->getLastExitCode();

        if ($expectedExitCode !== null) {
            $this->assertEquals($expectedExitCode, $exitCode, $message ?: "Expected exit code {$expectedExitCode}, got {$exitCode}");
        } else {
            $this->assertNotEquals(0, $exitCode, $message ?: 'Expected command to fail but it succeeded');
        }
    }

    /**
     * Assert that the command output contains a specific string.
     *
     * @param  string  $needle  String to search for
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertOutputContains(string $needle, ?string $message = null): void
    {
        $output = $this->artisanOutput();
        $this->assertStringContainsString($needle, $output, $message ?: "Output does not contain '{$needle}'. Actual output: {$output}");
    }

    /**
     * Assert that the command output does not contain a specific string.
     *
     * @param  string  $needle  String to search for
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertOutputNotContains(string $needle, ?string $message = null): void
    {
        $output = $this->artisanOutput();
        $this->assertStringNotContainsString($needle, $output, $message ?: "Output should not contain '{$needle}'. Actual output: {$output}");
    }

    /**
     * Assert that the command output matches a regular expression.
     *
     * @param  string  $pattern  Regular expression pattern
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertOutputMatches(string $pattern, ?string $message = null): void
    {
        $output = $this->artisanOutput();
        $this->assertMatchesRegularExpression($pattern, $output, $message ?: "Output does not match pattern '{$pattern}'. Actual output: {$output}");
    }

    /**
     * Assert that the command output contains success indicators.
     *
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertOutputIndicatesSuccess(?string $message = null): void
    {
        $output = $this->artisanOutput();
        $successIndicators = ['✓', 'successfully', 'completed', 'created'];

        $containsSuccess = false;
        foreach ($successIndicators as $indicator) {
            if (stripos($output, $indicator) !== false) {
                $containsSuccess = true;
                break;
            }
        }

        $this->assertTrue($containsSuccess, $message ?: "Output does not contain success indicators. Actual output: {$output}");
    }

    /**
     * Assert that the command output contains error indicators.
     *
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertOutputIndicatesError(?string $message = null): void
    {
        $output = $this->artisanOutput();
        $errorIndicators = ['✗', 'error', 'failed', 'exception'];

        $containsError = false;
        foreach ($errorIndicators as $indicator) {
            if (stripos($output, $indicator) !== false) {
                $containsError = true;
                break;
            }
        }

        $this->assertTrue($containsError, $message ?: "Output does not contain error indicators. Actual output: {$output}");
    }

    /**
     * Assert that the command created a file.
     *
     * @param  string  $path  File path to check
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertFileWasCreated(string $path, ?string $message = null): void
    {
        $this->assertFileExists($path, $message ?: "Expected file was not created: {$path}");
    }

    /**
     * Assert that the command created a file with specific content.
     *
     * @param  string  $path  File path to check
     * @param  string  $expectedContent  Expected file content (substring)
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertFileWasCreatedWithContent(string $path, string $expectedContent, ?string $message = null): void
    {
        $this->assertFileExists($path, "Expected file was not created: {$path}");

        $actualContent = file_get_contents($path);
        $this->assertStringContainsString(
            $expectedContent,
            $actualContent,
            $message ?: "File does not contain expected content. Expected: {$expectedContent}. Actual: {$actualContent}"
        );
    }

    /**
     * Assert that the command did not create a file.
     *
     * @param  string  $path  File path to check
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertFileWasNotCreated(string $path, ?string $message = null): void
    {
        $this->assertFileDoesNotExist($path, $message ?: "File should not have been created: {$path}");
    }

    /**
     * Execute a command and assert it succeeds.
     *
     * @param  string  $command  The command name
     * @param  array  $parameters  Command parameters and options
     * @param  string|null  $message  Optional assertion message
     * @return int The command exit code
     */
    protected function executeAndAssertSuccess(string $command, array $parameters = [], ?string $message = null): int
    {
        $exitCode = $this->artisan($command, $parameters);
        $this->assertCommandSuccessful($message);

        return $exitCode;
    }

    /**
     * Execute a command and assert it fails.
     *
     * @param  string  $command  The command name
     * @param  array  $parameters  Command parameters and options
     * @param  int|null  $expectedExitCode  Expected exit code
     * @param  string|null  $message  Optional assertion message
     * @return int The command exit code
     */
    protected function executeAndAssertFailure(string $command, array $parameters = [], ?int $expectedExitCode = null, ?string $message = null): int
    {
        $exitCode = $this->artisan($command, $parameters);
        $this->assertCommandFailed($expectedExitCode, $message);

        return $exitCode;
    }

    /**
     * Execute a command and assert it contains specific output.
     *
     * @param  string  $command  The command name
     * @param  array  $parameters  Command parameters and options
     * @param  string  $expectedOutput  Expected output substring
     * @param  string|null  $message  Optional assertion message
     * @return int The command exit code
     */
    protected function executeAndAssertOutput(string $command, array $parameters = [], string $expectedOutput = '', ?string $message = null): int
    {
        $exitCode = $this->artisan($command, $parameters);

        if (! empty($expectedOutput)) {
            $this->assertOutputContains($expectedOutput, $message);
        }

        return $exitCode;
    }

    /**
     * Create a temporary file for testing.
     *
     * @param  string  $content  File content
     * @param  string|null  $extension  File extension (default: .tmp)
     * @return string The temporary file path
     */
    protected function createTempFile(string $content = '', ?string $extension = '.tmp'): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'mcp_test').($extension ?: '.tmp');
        file_put_contents($tempFile, $content);

        return $tempFile;
    }

    /**
     * Clean up temporary files created during testing.
     *
     * @param  array  $files  Array of file paths to clean up
     */
    protected function cleanupTempFiles(array $files): void
    {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Mock user input for interactive commands.
     *
     * @param  array  $inputs  Array of input responses
     */
    protected function mockUserInput(array $inputs): void
    {
        $this->mock(\Symfony\Component\Console\Helper\QuestionHelper::class, function ($mock) use ($inputs) {
            foreach ($inputs as $input) {
                $mock->shouldReceive('ask')->andReturn($input);
            }
        });
    }

    /**
     * Assert that a command table contains specific data.
     *
     * @param  array  $expectedRows  Expected table rows
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertTableContains(array $expectedRows, ?string $message = null): void
    {
        $output = $this->artisanOutput();

        foreach ($expectedRows as $row) {
            if (is_array($row)) {
                foreach ($row as $cell) {
                    $this->assertOutputContains((string) $cell, $message);
                }
            } else {
                $this->assertOutputContains((string) $row, $message);
            }
        }
    }

    /**
     * Assert that the command ran within a specific time limit.
     *
     * @param  callable  $commandCallback  Callback that executes the command
     * @param  float  $maxSeconds  Maximum execution time in seconds
     * @param  string|null  $message  Optional assertion message
     */
    protected function assertExecutionTime(callable $commandCallback, float $maxSeconds, ?string $message = null): void
    {
        $startTime = microtime(true);
        $commandCallback();
        $executionTime = microtime(true) - $startTime;

        $this->assertLessThanOrEqual(
            $maxSeconds,
            $executionTime,
            $message ?: "Command took {$executionTime}s, expected less than {$maxSeconds}s"
        );
    }
}
