<?php

namespace JTD\LaravelMCP\Commands\Traits;

use Symfony\Component\Yaml\Yaml;

/**
 * Provides output formatting functionality for MCP commands.
 *
 * This trait includes methods for formatting and displaying data in various
 * formats including tables, JSON, and YAML for consistent command output.
 */
trait FormatsOutput
{
    /**
     * Format data as a table for display.
     *
     * @param  array  $headers  Table column headers
     * @param  array  $rows  Table row data
     * @param  string  $style  Table style (default, compact, borderless, etc.)
     */
    protected function formatTable(array $headers, array $rows, string $style = 'default'): void
    {
        $this->table($headers, $rows, $style);
    }

    /**
     * Format data as JSON for display.
     *
     * @param  array  $data  Data to format as JSON
     * @param  bool  $pretty  Whether to use pretty printing
     */
    protected function formatJson(array $data, bool $pretty = true): void
    {
        $flags = $pretty ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : 0;
        $this->line(json_encode($data, $flags));
    }

    /**
     * Format data as YAML for display.
     *
     * @param  array  $data  Data to format as YAML
     * @param  int  $inline  The level at which to inline arrays
     * @param  int  $indent  The amount of spaces to use for indentation
     */
    protected function formatYaml(array $data, int $inline = 2, int $indent = 4): void
    {
        if (! class_exists(Yaml::class)) {
            $this->error('YAML formatting requires the symfony/yaml package. Install it with: composer require symfony/yaml');

            return;
        }

        $yaml = Yaml::dump($data, $inline, $indent, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $this->line($yaml);
    }

    /**
     * Display data in the requested format.
     *
     * @param  array  $data  Data to display
     * @param  string  $format  Display format (table|json|yaml)
     * @param  array  $headers  Headers for table format
     */
    protected function displayInFormat(array $data, string $format, array $headers = []): void
    {
        match ($format) {
            'json' => $this->formatJson($data),
            'yaml' => $this->formatYaml($data),
            'table' => $this->formatTable($headers, $data),
            default => $this->formatTable($headers, $data),
        };
    }

    /**
     * Display a key-value list in a formatted way.
     *
     * @param  array  $data  Associative array of key-value pairs
     * @param  string  $keyLabel  Label for the key column
     * @param  string  $valueLabel  Label for the value column
     */
    protected function displayKeyValueTable(array $data, string $keyLabel = 'Key', string $valueLabel = 'Value'): void
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = [$key, is_array($value) ? json_encode($value) : (string) $value];
        }

        $this->formatTable([$keyLabel, $valueLabel], $rows);
    }

    /**
     * Display a summary table with counts or statistics.
     *
     * @param  array  $summary  Summary data with labels and counts
     */
    protected function displaySummaryTable(array $summary): void
    {
        $rows = [];
        foreach ($summary as $label => $count) {
            $rows[] = [$label, (string) $count];
        }

        $this->formatTable(['Item', 'Count'], $rows, 'compact');
    }

    /**
     * Display data as a table.
     *
     * Wrapper method for formatTable to maintain compatibility.
     *
     * @param  array  $headers  Table column headers
     * @param  array  $rows  Table row data
     * @param  string  $style  Table style (default, compact, borderless, etc.)
     */
    protected function displayTable(array $headers, array $rows, string $style = 'default'): void
    {
        $this->formatTable($headers, $rows, $style);
    }

    /**
     * Display data as JSON.
     *
     * Wrapper method for formatJson to maintain compatibility.
     *
     * @param  array  $data  Data to format as JSON
     * @param  bool  $pretty  Whether to use pretty printing
     */
    protected function displayJson(array $data, bool $pretty = true): void
    {
        $this->formatJson($data, $pretty);
    }
}
