<?php

namespace JTD\LaravelMCP\Console;

use Symfony\Component\Console\Formatter\OutputFormatterStyle;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console output formatter for MCP commands.
 *
 * This utility provides consistent formatting and styling for MCP-related
 * console output. It includes methods for displaying component lists,
 * server status, errors, and other MCP-specific information in a
 * user-friendly format.
 */
class OutputFormatter
{
    /**
     * Output interface instance.
     */
    protected OutputInterface $output;

    /**
     * Custom output styles.
     */
    protected array $styles = [
        'success' => ['color' => 'green'],
        'error' => ['color' => 'red'],
        'warning' => ['color' => 'yellow'],
        'info' => ['color' => 'blue'],
        'comment' => ['color' => 'yellow'],
        'question' => ['color' => 'cyan'],
        'highlight' => ['color' => 'magenta'],
        'mcp' => ['color' => 'blue', 'options' => ['bold']],
        'component' => ['color' => 'cyan', 'options' => ['bold']],
        'url' => ['color' => 'blue', 'options' => ['underscore']],
    ];

    /**
     * Create a new output formatter instance.
     *
     * @param  OutputInterface  $output  Console output interface
     */
    public function __construct(OutputInterface $output)
    {
        $this->output = $output;
        $this->registerStyles();
    }

    /**
     * Register custom output styles.
     */
    protected function registerStyles(): void
    {
        $formatter = $this->output->getFormatter();

        foreach ($this->styles as $name => $style) {
            $outputStyle = new OutputFormatterStyle(
                $style['color'] ?? null,
                $style['background'] ?? null,
                $style['options'] ?? []
            );

            $formatter->setStyle($name, $outputStyle);
        }
    }

    /**
     * Write a line with optional styling.
     *
     * @param  string  $message  Message to write
     * @param  string|null  $style  Style to apply
     */
    public function line(string $message, ?string $style = null): void
    {
        if ($style) {
            $message = "<{$style}>{$message}</{$style}>";
        }

        $this->output->writeln($message);
    }

    /**
     * Write multiple lines.
     *
     * @param  array  $messages  Messages to write
     * @param  string|null  $style  Style to apply
     */
    public function lines(array $messages, ?string $style = null): void
    {
        foreach ($messages as $message) {
            $this->line($message, $style);
        }
    }

    /**
     * Write a success message.
     *
     * @param  string  $message  Success message
     */
    public function success(string $message): void
    {
        $this->line("✓ {$message}", 'success');
    }

    /**
     * Write an error message.
     *
     * @param  string  $message  Error message
     */
    public function error(string $message): void
    {
        $this->line("✗ {$message}", 'error');
    }

    /**
     * Write a warning message.
     *
     * @param  string  $message  Warning message
     */
    public function warning(string $message): void
    {
        $this->line("⚠ {$message}", 'warning');
    }

    /**
     * Write an info message.
     *
     * @param  string  $message  Info message
     */
    public function info(string $message): void
    {
        $this->line("ℹ {$message}", 'info');
    }

    /**
     * Write a comment message.
     *
     * @param  string  $message  Comment message
     */
    public function comment(string $message): void
    {
        $this->line($message, 'comment');
    }

    /**
     * Write a question message.
     *
     * @param  string  $message  Question message
     */
    public function question(string $message): void
    {
        $this->line($message, 'question');
    }

    /**
     * Write a blank line.
     */
    public function newLine(): void
    {
        $this->output->writeln('');
    }

    /**
     * Write a title with decorative border.
     *
     * @param  string  $title  Title text
     * @param  string  $style  Style to apply
     */
    public function title(string $title, string $style = 'mcp'): void
    {
        $length = strlen($title) + 4;
        $border = str_repeat('=', $length);

        $this->line($border, $style);
        $this->line("  {$title}  ", $style);
        $this->line($border, $style);
        $this->newLine();
    }

    /**
     * Write a section header.
     *
     * @param  string  $header  Section header
     * @param  string  $style  Style to apply
     */
    public function section(string $header, string $style = 'highlight'): void
    {
        $this->line($header, $style);
        $this->line(str_repeat('-', strlen($header)), $style);
        $this->newLine();
    }

    /**
     * Display MCP server information.
     *
     * @param  array  $serverInfo  Server information
     */
    public function displayServerInfo(array $serverInfo): void
    {
        $this->title('MCP Server Information');

        $this->line('<component>Server Name:</component> '.($serverInfo['name'] ?? 'Unknown'));
        $this->line('<component>Version:</component> '.($serverInfo['version'] ?? 'Unknown'));

        if (isset($serverInfo['description'])) {
            $this->line('<component>Description:</component> '.$serverInfo['description']);
        }

        if (isset($serverInfo['url'])) {
            $this->line('<component>URL:</component> <url>'.$serverInfo['url'].'</url>');
        }

        $this->newLine();
    }

    /**
     * Display MCP capabilities.
     *
     * @param  array  $capabilities  Server capabilities
     */
    public function displayCapabilities(array $capabilities): void
    {
        $this->section('Server Capabilities');

        foreach ($capabilities as $category => $config) {
            $this->line("<component>{$category}:</component>");

            if (is_array($config) && ! empty($config)) {
                foreach ($config as $key => $value) {
                    if (is_bool($value)) {
                        $status = $value ? '<success>✓</success>' : '<error>✗</error>';
                        $this->line("  {$key}: {$status}");
                    } else {
                        $this->line("  {$key}: ".json_encode($value));
                    }
                }
            } else {
                $enabled = ! empty($config) ? '<success>Enabled</success>' : '<comment>Disabled</comment>';
                $this->line("  Status: {$enabled}");
            }

            $this->newLine();
        }
    }

    /**
     * Display component list in a table format.
     *
     * @param  string  $type  Component type (tools, resources, prompts)
     * @param  array  $components  Component list
     */
    public function displayComponents(string $type, array $components): void
    {
        $title = ucfirst($type);
        $this->section("Registered {$title}");

        if (empty($components)) {
            $this->comment("No {$type} registered.");
            $this->newLine();

            return;
        }

        $this->line(sprintf(
            '<component>%-30s %-50s %s</component>',
            'Name',
            'Class',
            'Description'
        ));

        $this->line(str_repeat('-', 100));

        foreach ($components as $name => $component) {
            $className = is_string($component) ? $component : get_class($component);
            $description = $this->getComponentDescription($component);

            $this->line(sprintf(
                '%-30s %-50s %s',
                $name,
                $className,
                $description
            ));
        }

        $this->newLine();
    }

    /**
     * Get component description for display.
     *
     * @param  mixed  $component  Component instance or class name
     */
    protected function getComponentDescription($component): string
    {
        if (is_object($component) && method_exists($component, 'getDescription')) {
            return $component->getDescription();
        }

        if (is_string($component) && class_exists($component)) {
            $reflection = new \ReflectionClass($component);
            $docComment = $reflection->getDocComment();

            if ($docComment) {
                // Extract first line of class documentation
                preg_match('/\*\s*([^@\n\r]+)/', $docComment, $matches);
                if (isset($matches[1])) {
                    return trim($matches[1], ' .');
                }
            }
        }

        return 'No description available';
    }

    /**
     * Display component statistics.
     *
     * @param  array  $stats  Component statistics
     */
    public function displayStats(array $stats): void
    {
        $this->section('Component Statistics');

        $this->line('<component>Tools:</component> '.($stats['tools'] ?? 0));
        $this->line('<component>Resources:</component> '.($stats['resources'] ?? 0));
        $this->line('<component>Prompts:</component> '.($stats['prompts'] ?? 0));
        $this->line('<component>Total:</component> '.($stats['total'] ?? 0));

        $this->newLine();
    }

    /**
     * Display server status with colored indicator.
     *
     * @param  bool  $isRunning  Whether server is running
     * @param  array  $details  Additional status details
     */
    public function displayServerStatus(bool $isRunning, array $details = []): void
    {
        $status = $isRunning ? '<success>Running</success>' : '<error>Stopped</error>';
        $this->line("<component>Server Status:</component> {$status}");

        if ($isRunning && ! empty($details)) {
            if (isset($details['transport'])) {
                $this->line('<component>Transport:</component> '.$details['transport']);
            }

            if (isset($details['host']) && isset($details['port'])) {
                $this->line("<component>Address:</component> <url>http://{$details['host']}:{$details['port']}</url>");
            }

            if (isset($details['pid'])) {
                $this->line('<component>Process ID:</component> '.$details['pid']);
            }

            if (isset($details['uptime'])) {
                $this->line('<component>Uptime:</component> '.$details['uptime']);
            }
        }

        $this->newLine();
    }

    /**
     * Display progress indicator.
     *
     * @param  string  $message  Progress message
     * @param  float  $percent  Completion percentage (0-100)
     */
    public function progress(string $message, float $percent = 0): void
    {
        $barLength = 50;
        $filledLength = (int) round($barLength * $percent / 100);
        $bar = str_repeat('█', $filledLength).str_repeat('░', $barLength - $filledLength);

        $this->output->write("\r<comment>{$message}</comment> [<info>{$bar}</info>] <highlight>{$percent}%</highlight>");

        if ($percent >= 100) {
            $this->newLine();
        }
    }

    /**
     * Display a formatted table.
     *
     * @param  array  $headers  Table headers
     * @param  array  $rows  Table rows
     * @param  string  $style  Table style
     */
    public function table(array $headers, array $rows, string $style = 'component'): void
    {
        if (empty($headers) || empty($rows)) {
            return;
        }

        // Calculate column widths
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
            }
        }

        // Display headers
        $headerLine = '';
        foreach ($headers as $i => $header) {
            $headerLine .= sprintf("%-{$widths[$i]}s  ", $header);
        }
        $this->line(trim($headerLine), $style);

        // Display separator
        $separatorLine = '';
        foreach ($widths as $width) {
            $separatorLine .= str_repeat('-', $width).'  ';
        }
        $this->line(trim($separatorLine));

        // Display rows
        foreach ($rows as $row) {
            $rowLine = '';
            foreach ($row as $i => $cell) {
                $rowLine .= sprintf("%-{$widths[$i]}s  ", (string) $cell);
            }
            $this->line(trim($rowLine));
        }

        $this->newLine();
    }

    /**
     * Clear the console screen.
     */
    public function clear(): void
    {
        $this->output->write("\033[H\033[2J");
    }
}
