<?php

namespace JTD\LaravelMCP\Tests\Unit\Commands;

use JTD\LaravelMCP\Commands\BaseCommand;
use Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Test implementation of BaseCommand for testing purposes.
 */
class TestCommand extends BaseCommand
{
    protected $signature = 'test:command
                           {--debug : Enable debug mode}
                           {--force : Force the operation}
                           {--type= : Type of operation}
                           {--timeout= : Timeout in seconds}';

    protected $description = 'Test command for BaseCommand testing';

    public $executeResult = 0;

    public $shouldThrowException = false;

    public $exceptionMessage = 'Test exception';

    public $validationResult = true;

    // Public wrappers for testing protected methods
    public function testIsMcpEnabled(): bool
    {
        return $this->isMcpEnabled();
    }

    public function testGetDefaultTransport(): string
    {
        return $this->getDefaultTransport();
    }

    public function testIsTransportEnabled(string $transport): bool
    {
        return $this->isTransportEnabled($transport);
    }

    protected function executeCommand(): int
    {
        if ($this->shouldThrowException) {
            throw new \RuntimeException($this->exceptionMessage);
        }

        // Test various output methods
        $this->success('Test success message', ['key' => 'value']);
        $this->warning('Test warning message');
        $this->status('Test status message');
        $this->debug('Test debug message', ['debug' => 'data']);

        return $this->executeResult;
    }

    protected function validateInput(): bool
    {
        return $this->validationResult;
    }
}

/**
 * Tests for BaseCommand abstract class.
 */
class BaseCommandTest extends TestCase
{
    protected TestCommand $command;

    protected BufferedOutput $output;

    protected ArrayInput $input;

    protected function setUp(): void
    {
        parent::setUp();

        $this->command = new TestCommand;
        $this->output = new BufferedOutput;

        // Set up Laravel application for the command
        $this->command->setLaravel($this->app);
    }

    /**
     * Test successful command execution.
     */
    public function test_successful_command_execution(): void
    {
        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(0, $result);
        $this->assertStringContainsString('✓ Test success message', $this->output->fetch());
    }

    /**
     * Test command with validation failure.
     */
    public function test_command_with_validation_failure(): void
    {
        $this->command->validationResult = false;

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $this->assertEquals(2, $result); // EXIT_INVALID_INPUT
    }

    /**
     * Test error handling without debug mode.
     */
    public function test_error_handling_without_debug(): void
    {
        $this->command->shouldThrowException = true;

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result); // EXIT_ERROR
        $this->assertStringContainsString('Test exception', $output);
        $this->assertStringContainsString('Run with --debug or -v for more details', $output);
        $this->assertStringNotContainsString('Stack trace:', $output);
    }

    /**
     * Test error handling with debug mode.
     */
    public function test_error_handling_with_debug_mode(): void
    {
        $this->command->shouldThrowException = true;

        $input = new ArrayInput(['--debug' => true]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result);
        $this->assertStringContainsString('Test exception', $output);
        $this->assertStringContainsString('Stack trace:', $output);
    }

    /**
     * Test output formatting methods.
     */
    public function test_output_formatting_methods(): void
    {
        $input = new ArrayInput([]);
        $this->command->run($input, $this->output);

        $output = $this->output->fetch();

        // Check success message formatting
        $this->assertStringContainsString('✓ Test success message', $output);
        $this->assertStringContainsString('key: value', $output);

        // Check warning message formatting
        $this->assertStringContainsString('⚠ Test warning message', $output);

        // Check status message formatting
        $this->assertStringContainsString('→ Test status message', $output);
    }

    /**
     * Test debug output in verbose mode.
     */
    public function test_debug_output_in_verbose_mode(): void
    {
        $input = new ArrayInput([]);
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);
        $this->command->run($input, $output);

        $outputText = $output->fetch();
        $this->assertStringContainsString('[DEBUG] Test debug message', $outputText);
    }

    /**
     * Test configuration helper methods.
     */
    public function test_configuration_helper_methods(): void
    {
        // Set up test configuration
        config(['laravel-mcp.enabled' => true]);
        config(['laravel-mcp.transports.default' => 'stdio']);
        config(['mcp-transports.stdio.enabled' => true]);

        $this->assertTrue($this->command->testIsMcpEnabled());
        $this->assertEquals('stdio', $this->command->testGetDefaultTransport());
        $this->assertTrue($this->command->testIsTransportEnabled('stdio'));
    }

    /**
     * Test MCP disabled check.
     */
    public function test_mcp_disabled_check(): void
    {
        config(['laravel-mcp.enabled' => false]);

        $input = new ArrayInput([]);
        $result = $this->command->run($input, $this->output);

        $output = $this->output->fetch();
        $this->assertEquals(1, $result);
        $this->assertStringContainsString('MCP is disabled', $output);
    }

    /**
     * Test option validation methods.
     */
    public function test_option_validation_methods(): void
    {
        // Create a command that tests validation methods
        $validationCommand = new class extends BaseCommand
        {
            protected $signature = 'validate:test
                                   {--required= : Required option}
                                   {--choice= : Choice option}
                                   {--number= : Numeric option}';

            protected $description = 'Test validation';

            protected function executeCommand(): int
            {
                // Test required option validation
                if (! $this->validateRequiredOption('required')) {
                    return self::EXIT_INVALID_INPUT;
                }

                // Test choice validation
                if (! $this->validateOptionInList('choice', ['foo', 'bar', 'baz'])) {
                    return self::EXIT_INVALID_INPUT;
                }

                // Test numeric validation
                if (! $this->validateNumericOption('number', 1, 100)) {
                    return self::EXIT_INVALID_INPUT;
                }

                return self::EXIT_SUCCESS;
            }
        };

        $validationCommand->setLaravel($this->app);

        // Test with missing required option
        $input = new ArrayInput([]);
        $output = new BufferedOutput;
        $result = $validationCommand->run($input, $output);
        $this->assertEquals(2, $result);
        $this->assertStringContainsString('The --required option is required', $output->fetch());

        // Test with invalid choice
        $input = new ArrayInput(['--required' => 'test', '--choice' => 'invalid']);
        $output = new BufferedOutput;
        $result = $validationCommand->run($input, $output);
        $this->assertEquals(2, $result);
        $this->assertStringContainsString('Allowed values: foo, bar, baz', $output->fetch());

        // Test with invalid numeric value
        $input = new ArrayInput(['--required' => 'test', '--number' => '150']);
        $output = new BufferedOutput;
        $result = $validationCommand->run($input, $output);
        $this->assertEquals(2, $result);
        $this->assertStringContainsString('must be no more than 100', $output->fetch());

        // Test with all valid options
        $input = new ArrayInput(['--required' => 'test', '--choice' => 'foo', '--number' => '50']);
        $output = new BufferedOutput;
        $result = $validationCommand->run($input, $output);
        $this->assertEquals(0, $result);
    }
}
