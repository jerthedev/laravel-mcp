<?php

namespace JTD\LaravelMCP\Tests\Unit\Console;

use JTD\LaravelMCP\Console\OutputFormatter;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * OutputFormatter Tests
 *
 * EPIC: Laravel Integration Layer (006)
 * SPEC: Package Specification Document
 * SPRINT: 3 - Laravel Support Utilities
 * TICKET: 023-LaravelSupport
 *
 * @group unit
 * @group console
 * @group ticket-023
 * @group epic-laravel-integration
 * @group sprint-3
 */
#[Group('unit')]
#[Group('console')]
#[Group('ticket-023')]
class OutputFormatterTest extends TestCase
{
    protected BufferedOutput $output;

    protected OutputFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = new BufferedOutput;
        $this->formatter = new OutputFormatter($this->output);
    }

    #[Test]
    public function it_writes_basic_lines(): void
    {
        $this->formatter->line('Test message');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Test message', $output);
    }

    #[Test]
    public function it_writes_styled_lines(): void
    {
        $this->formatter->line('Test message', 'success');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Test message', $output);
        $this->assertStringContainsString('success', $output);
    }

    #[Test]
    public function it_writes_multiple_lines(): void
    {
        $messages = ['Line 1', 'Line 2', 'Line 3'];
        $this->formatter->lines($messages);

        $output = $this->output->fetch();
        foreach ($messages as $message) {
            $this->assertStringContainsString($message, $output);
        }
    }

    #[Test]
    public function it_writes_success_messages_with_checkmark(): void
    {
        $this->formatter->success('Operation completed');

        $output = $this->output->fetch();
        $this->assertStringContainsString('✓', $output);
        $this->assertStringContainsString('Operation completed', $output);
    }

    #[Test]
    public function it_writes_error_messages_with_cross(): void
    {
        $this->formatter->error('Operation failed');

        $output = $this->output->fetch();
        $this->assertStringContainsString('✗', $output);
        $this->assertStringContainsString('Operation failed', $output);
    }

    #[Test]
    public function it_writes_warning_messages_with_warning_sign(): void
    {
        $this->formatter->warning('Caution required');

        $output = $this->output->fetch();
        $this->assertStringContainsString('⚠', $output);
        $this->assertStringContainsString('Caution required', $output);
    }

    #[Test]
    public function it_writes_info_messages_with_info_sign(): void
    {
        $this->formatter->info('Information message');

        $output = $this->output->fetch();
        $this->assertStringContainsString('ℹ', $output);
        $this->assertStringContainsString('Information message', $output);
    }

    #[Test]
    public function it_writes_comment_messages(): void
    {
        $this->formatter->comment('This is a comment');

        $output = $this->output->fetch();
        $this->assertStringContainsString('This is a comment', $output);
    }

    #[Test]
    public function it_writes_question_messages(): void
    {
        $this->formatter->question('What is your choice?');

        $output = $this->output->fetch();
        $this->assertStringContainsString('What is your choice?', $output);
    }

    #[Test]
    public function it_writes_blank_lines(): void
    {
        $this->formatter->line('Before');
        $this->formatter->newLine();
        $this->formatter->line('After');

        $output = $this->output->fetch();
        $lines = explode("\n", $output);
        $this->assertGreaterThanOrEqual(3, count($lines));
    }

    #[Test]
    public function it_displays_titles_with_borders(): void
    {
        $this->formatter->title('Test Title');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Test Title', $output);
        $this->assertStringContainsString('=============', $output);
    }

    #[Test]
    public function it_displays_titles_with_custom_style(): void
    {
        $this->formatter->title('Custom Title', 'component');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Custom Title', $output);
    }

    #[Test]
    public function it_displays_section_headers(): void
    {
        $this->formatter->section('Section Header');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Section Header', $output);
        $this->assertStringContainsString('--------------', $output);
    }

    #[Test]
    public function it_displays_section_with_custom_style(): void
    {
        $this->formatter->section('Custom Section', 'info');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Custom Section', $output);
    }

    #[Test]
    public function it_displays_server_information(): void
    {
        $serverInfo = [
            'name' => 'MCP Test Server',
            'version' => '1.0.0',
            'description' => 'Test server for MCP',
            'url' => 'http://localhost:8080',
        ];

        $this->formatter->displayServerInfo($serverInfo);

        $output = $this->output->fetch();
        $this->assertStringContainsString('MCP Server Information', $output);
        $this->assertStringContainsString('MCP Test Server', $output);
        $this->assertStringContainsString('1.0.0', $output);
        $this->assertStringContainsString('Test server for MCP', $output);
        $this->assertStringContainsString('http://localhost:8080', $output);
    }

    #[Test]
    public function it_handles_missing_server_info_fields(): void
    {
        $serverInfo = [];

        $this->formatter->displayServerInfo($serverInfo);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Unknown', $output);
    }

    #[Test]
    public function it_displays_server_capabilities(): void
    {
        $capabilities = [
            'tools' => ['execute' => true, 'list' => true],
            'resources' => ['read' => true, 'write' => false],
            'prompts' => [],
        ];

        $this->formatter->displayCapabilities($capabilities);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Server Capabilities', $output);
        $this->assertStringContainsString('tools:', $output);
        $this->assertStringContainsString('resources:', $output);
        $this->assertStringContainsString('prompts:', $output);
        $this->assertStringContainsString('✓', $output); // For true values
        $this->assertStringContainsString('✗', $output); // For false values
    }

    #[Test]
    public function it_displays_empty_capabilities(): void
    {
        $capabilities = [
            'tools' => false,
            'resources' => null,
        ];

        $this->formatter->displayCapabilities($capabilities);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Disabled', $output);
    }

    #[Test]
    public function it_displays_component_lists(): void
    {
        $components = [
            'calculator' => 'App\\Mcp\\Tools\\CalculatorTool',
            'weather' => new class
            {
                public function getDescription(): string
                {
                    return 'Weather information tool';
                }
            },
        ];

        $this->formatter->displayComponents('tools', $components);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Registered Tools', $output);
        $this->assertStringContainsString('calculator', $output);
        $this->assertStringContainsString('CalculatorTool', $output);
        $this->assertStringContainsString('weather', $output);
        $this->assertStringContainsString('Weather information tool', $output);
    }

    #[Test]
    public function it_displays_empty_component_lists(): void
    {
        $this->formatter->displayComponents('resources', []);

        $output = $this->output->fetch();
        $this->assertStringContainsString('No resources registered', $output);
    }

    #[Test]
    public function it_extracts_component_descriptions_from_docblocks(): void
    {
        $testClass = new class
        {
            // No getDescription method
        };

        $reflection = new \ReflectionMethod($this->formatter, 'getComponentDescription');
        $reflection->setAccessible(true);

        $description = $reflection->invoke($this->formatter, $testClass);
        $this->assertEquals('No description available', $description);

        $description = $reflection->invoke($this->formatter, 'NonExistentClass');
        $this->assertEquals('No description available', $description);
    }

    #[Test]
    public function it_displays_component_statistics(): void
    {
        $stats = [
            'tools' => 5,
            'resources' => 3,
            'prompts' => 2,
            'total' => 10,
        ];

        $this->formatter->displayStats($stats);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Component Statistics', $output);
        $this->assertStringContainsString('Tools:', $output);
        $this->assertStringContainsString('5', $output);
        $this->assertStringContainsString('Resources:', $output);
        $this->assertStringContainsString('3', $output);
        $this->assertStringContainsString('Prompts:', $output);
        $this->assertStringContainsString('2', $output);
        $this->assertStringContainsString('Total:', $output);
        $this->assertStringContainsString('10', $output);
    }

    #[Test]
    public function it_displays_server_status(): void
    {
        $details = [
            'transport' => 'HTTP',
            'host' => 'localhost',
            'port' => 8080,
            'pid' => 12345,
            'uptime' => '2 hours 15 minutes',
        ];

        $this->formatter->displayServerStatus(true, $details);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Running', $output);
        $this->assertStringContainsString('HTTP', $output);
        $this->assertStringContainsString('http://localhost:8080', $output);
        $this->assertStringContainsString('12345', $output);
        $this->assertStringContainsString('2 hours 15 minutes', $output);
    }

    #[Test]
    public function it_displays_stopped_server_status(): void
    {
        $this->formatter->displayServerStatus(false);

        $output = $this->output->fetch();
        $this->assertStringContainsString('Stopped', $output);
    }

    #[Test]
    public function it_displays_progress_indicators(): void
    {
        $this->formatter->progress('Processing', 0);
        $output1 = $this->output->fetch();
        $this->assertStringContainsString('Processing', $output1);
        $this->assertStringContainsString('0%', $output1);

        $this->formatter->progress('Processing', 50);
        $output2 = $this->output->fetch();
        $this->assertStringContainsString('50%', $output2);

        $this->formatter->progress('Processing', 100);
        $output3 = $this->output->fetch();
        $this->assertStringContainsString('100%', $output3);
        $this->assertStringContainsString("\n", $output3);
    }

    #[Test]
    #[DataProvider('progressBarProvider')]
    public function it_renders_progress_bars_correctly(float $percent, int $expectedFilled): void
    {
        $this->formatter->progress('Test', $percent);

        $output = $this->output->fetch();
        $filledCount = substr_count($output, '█');
        $emptyCount = substr_count($output, '░');

        $this->assertEquals($expectedFilled, $filledCount);
        $this->assertEquals(50 - $expectedFilled, $emptyCount);
    }

    public static function progressBarProvider(): array
    {
        return [
            'zero percent' => [0, 0],
            'twenty-five percent' => [25, 13],
            'fifty percent' => [50, 25],
            'seventy-five percent' => [75, 38],
            'one hundred percent' => [100, 50],
        ];
    }

    #[Test]
    public function it_displays_formatted_tables(): void
    {
        $headers = ['Name', 'Type', 'Status'];
        $rows = [
            ['Tool1', 'Calculator', 'Active'],
            ['Tool2', 'Weather', 'Inactive'],
            ['Tool3', 'Database', 'Active'],
        ];

        $this->formatter->table($headers, $rows);

        $output = $this->output->fetch();
        foreach ($headers as $header) {
            $this->assertStringContainsString($header, $output);
        }
        foreach ($rows as $row) {
            foreach ($row as $cell) {
                $this->assertStringContainsString($cell, $output);
            }
        }
        $this->assertStringContainsString('------', $output); // Separator line
    }

    #[Test]
    public function it_handles_empty_tables(): void
    {
        $this->formatter->table([], []);
        $output = $this->output->fetch();
        $this->assertEmpty($output);

        $this->formatter->table(['Header'], []);
        $output = $this->output->fetch();
        $this->assertEmpty($output);

        $this->formatter->table([], [['Data']]);
        $output = $this->output->fetch();
        $this->assertEmpty($output);
    }

    #[Test]
    public function it_calculates_column_widths_correctly(): void
    {
        $headers = ['Short', 'Very Long Header Name', 'Mid'];
        $rows = [
            ['A', 'B', 'C'],
            ['Longer content here', 'X', 'Y'],
        ];

        $this->formatter->table($headers, $rows);

        $output = $this->output->fetch();
        $lines = explode("\n", $output);

        // Header line should accommodate the longest content in each column
        $headerLine = $lines[0];
        $this->assertStringContainsString('Very Long Header Name', $headerLine);
        $this->assertStringContainsString('Longer content here', $lines[2]);
    }

    #[Test]
    public function it_clears_console_screen(): void
    {
        $this->formatter->clear();

        $output = $this->output->fetch();
        $this->assertStringContainsString("\033[H\033[2J", $output);
    }

    #[Test]
    public function it_registers_custom_styles(): void
    {
        // Create a new formatter to test style registration
        $bufferedOutput = new BufferedOutput;
        $formatter = new OutputFormatter($bufferedOutput);

        // Use a custom style
        $formatter->line('MCP Component', 'mcp');

        $output = $bufferedOutput->fetch();
        $this->assertStringContainsString('MCP Component', $output);
    }

    #[Test]
    public function it_handles_various_data_types_in_tables(): void
    {
        $headers = ['String', 'Number', 'Boolean', 'Null'];
        $rows = [
            ['text', 123, true, null],
            ['another', 456.78, false, null],
        ];

        $this->formatter->table($headers, $rows);

        $output = $this->output->fetch();
        $this->assertStringContainsString('text', $output);
        $this->assertStringContainsString('123', $output);
        $this->assertStringContainsString('456.78', $output);
        $this->assertStringContainsString('1', $output); // true cast to string
        $this->assertStringContainsString('', $output); // false and null cast to empty string
    }

    #[Test]
    public function it_handles_styled_table_display(): void
    {
        $headers = ['Component', 'Status'];
        $rows = [
            ['Tool1', 'Active'],
            ['Tool2', 'Inactive'],
        ];

        $this->formatter->table($headers, $rows, 'highlight');

        $output = $this->output->fetch();
        $this->assertStringContainsString('Component', $output);
        $this->assertStringContainsString('Status', $output);
    }

    #[Test]
    public function it_handles_capabilities_with_json_values(): void
    {
        $capabilities = [
            'tools' => [
                'config' => ['timeout' => 30, 'retries' => 3],
            ],
        ];

        $this->formatter->displayCapabilities($capabilities);

        $output = $this->output->fetch();
        $this->assertStringContainsString('{"timeout":30,"retries":3}', $output);
    }

    #[Test]
    public function it_handles_component_class_with_docblock(): void
    {
        $className = 'JTD\\LaravelMCP\\Console\\OutputFormatter';

        $reflection = new \ReflectionMethod($this->formatter, 'getComponentDescription');
        $reflection->setAccessible(true);

        $description = $reflection->invoke($this->formatter, $className);
        $this->assertStringContainsString('Console output formatter', $description);
    }
}
