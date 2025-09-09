<?php

namespace JTD\LaravelMCP\Commands;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use Symfony\Component\Yaml\Yaml;

/**
 * Command to list all registered MCP components.
 *
 * This command provides a comprehensive view of all registered MCP components
 * (tools, resources, and prompts) with support for filtering, detailed information,
 * and multiple output formats.
 */
class ListCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:list
                           {--type=all : Component type (all|tools|resources|prompts)}
                           {--format=table : Output format (table|json|yaml)}
                           {--detailed : Show detailed information}
                           {--debug : Enable debug output}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all registered MCP components';

    /**
     * Valid component types.
     */
    protected const VALID_TYPES = ['all', 'tools', 'resources', 'prompts'];

    /**
     * Valid output formats.
     */
    protected const VALID_FORMATS = ['table', 'json', 'yaml'];

    /**
     * The MCP registry instance.
     */
    protected McpRegistry $mcpRegistry;

    /**
     * The tool registry instance.
     */
    protected ToolRegistry $toolRegistry;

    /**
     * The resource registry instance.
     */
    protected ResourceRegistry $resourceRegistry;

    /**
     * The prompt registry instance.
     */
    protected PromptRegistry $promptRegistry;

    /**
     * Create a new command instance.
     */
    public function __construct(
        McpRegistry $mcpRegistry,
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry
    ) {
        parent::__construct();

        $this->mcpRegistry = $mcpRegistry;
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;
    }

    /**
     * Validate command input.
     */
    protected function validateInput(): bool
    {
        // Validate type option
        if (! $this->validateOptionInList('type', self::VALID_TYPES)) {
            return false;
        }

        // Validate format option
        if (! $this->validateOptionInList('format', self::VALID_FORMATS)) {
            return false;
        }

        return true;
    }

    /**
     * Execute the command logic.
     *
     * @return int Exit code
     */
    protected function executeCommand(): int
    {
        try {
            $type = $this->option('type') ?? 'all';
            $format = $this->option('format') ?? 'table';
            $detailed = $this->option('detailed') ?? false;

            $this->debug('Listing MCP components', [
                'type' => $type,
                'format' => $format,
                'detailed' => $detailed,
            ]);

            // Gather component data based on type
            $components = $this->gatherComponents($type);

            // Check if there are any components to display
            if ($this->isComponentsEmpty($components)) {
                $this->handleEmptyComponents($type);

                return self::EXIT_SUCCESS;
            }

            // Display components based on format
            switch ($format) {
                case 'json':
                    $this->displayJsonFormat($components, $detailed);
                    break;
                case 'yaml':
                    $this->displayYamlFormat($components, $detailed);
                    break;
                case 'table':
                default:
                    $this->displayTableFormat($components, $type, $detailed);
                    break;
            }

            // Display summary
            if ($format === 'table') {
                $this->displaySummary($components);
            }

            return self::EXIT_SUCCESS;
        } catch (\Throwable $e) {
            // Handle any exceptions that occur with more detail
            $this->error('An error occurred in executeCommand: '.$e->getMessage());
            $this->error('Class: '.get_class($e));
            $this->error('File: '.$e->getFile().':'.$e->getLine());
            if ($this->output->isVerbose()) {
                $this->error('Trace: '.$e->getTraceAsString());
            }

            return self::EXIT_ERROR;
        }
    }

    /**
     * Gather components based on the specified type.
     */
    protected function gatherComponents(string $type): array
    {
        $components = [];

        if ($type === 'all' || $type === 'tools') {
            $components['tools'] = $this->gatherToolComponents();
        }

        if ($type === 'all' || $type === 'resources') {
            $components['resources'] = $this->gatherResourceComponents();
        }

        if ($type === 'all' || $type === 'prompts') {
            $components['prompts'] = $this->gatherPromptComponents();
        }

        return $components;
    }

    /**
     * Gather tool components with their metadata.
     */
    protected function gatherToolComponents(): array
    {
        $tools = [];

        $allTools = $this->toolRegistry->all();
        if (empty($allTools)) {
            return $tools;
        }

        foreach ($allTools as $name => $tool) {
            try {
                $metadata = $this->toolRegistry->getMetadata($name);
                $tools[$name] = [
                    'name' => $name,
                    'description' => $metadata['description'] ?? 'No description available',
                    'parameters' => $metadata['parameters'] ?? [],
                    'input_schema' => $metadata['input_schema'] ?? null,
                    'registered_at' => $metadata['registered_at'] ?? null,
                    'class' => is_object($tool) ? get_class($tool) : (string) $tool,
                ];
            } catch (\Exception $e) {
                $this->debug("Failed to get metadata for tool: $name", $e->getMessage());
                $tools[$name] = [
                    'name' => $name,
                    'description' => 'Unable to retrieve metadata',
                    'parameters' => [],
                    'class' => is_object($tool) ? get_class($tool) : (string) $tool,
                ];
            }
        }

        return $tools;
    }

    /**
     * Gather resource components with their metadata.
     */
    protected function gatherResourceComponents(): array
    {
        $resources = [];

        $allResources = $this->resourceRegistry->all();
        if (empty($allResources)) {
            return $resources;
        }

        foreach ($allResources as $name => $resource) {
            try {
                $metadata = $this->resourceRegistry->getMetadata($name);
                $resources[$name] = [
                    'name' => $name,
                    'description' => $metadata['description'] ?? 'No description available',
                    'uri' => $metadata['uri'] ?? null,
                    'mime_type' => $metadata['mime_type'] ?? null,
                    'registered_at' => $metadata['registered_at'] ?? null,
                    'class' => is_object($resource) ? get_class($resource) : (string) $resource,
                ];
            } catch (\Exception $e) {
                $this->debug("Failed to get metadata for resource: $name", $e->getMessage());
                $resources[$name] = [
                    'name' => $name,
                    'description' => 'Unable to retrieve metadata',
                    'uri' => null,
                    'class' => is_object($resource) ? get_class($resource) : (string) $resource,
                ];
            }
        }

        return $resources;
    }

    /**
     * Gather prompt components with their metadata.
     */
    protected function gatherPromptComponents(): array
    {
        $prompts = [];

        $allPrompts = $this->promptRegistry->all();
        if (empty($allPrompts)) {
            return $prompts;
        }

        foreach ($allPrompts as $name => $prompt) {
            try {
                $metadata = $this->promptRegistry->getMetadata($name);
                $prompts[$name] = [
                    'name' => $name,
                    'description' => $metadata['description'] ?? 'No description available',
                    'arguments' => $metadata['arguments'] ?? [],
                    'registered_at' => $metadata['registered_at'] ?? null,
                    'class' => is_object($prompt) ? get_class($prompt) : (string) $prompt,
                ];
            } catch (\Exception $e) {
                $this->debug("Failed to get metadata for prompt: $name", $e->getMessage());
                $prompts[$name] = [
                    'name' => $name,
                    'description' => 'Unable to retrieve metadata',
                    'arguments' => [],
                    'class' => is_object($prompt) ? get_class($prompt) : (string) $prompt,
                ];
            }
        }

        return $prompts;
    }

    /**
     * Check if components array is empty.
     */
    protected function isComponentsEmpty(array $components): bool
    {
        foreach ($components as $type => $items) {
            if (! empty($items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Handle empty components display.
     */
    protected function handleEmptyComponents(string $type): void
    {
        if ($type === 'all') {
            $this->warning('No MCP components are currently registered.');
        } else {
            $this->warning("No {$type} are currently registered.");
        }

        $this->newLine();
        $this->line('To create MCP components, use the following commands:');
        $this->line('  <comment>php artisan make:mcp-tool MyTool</comment>');
        $this->line('  <comment>php artisan make:mcp-resource MyResource</comment>');
        $this->line('  <comment>php artisan make:mcp-prompt MyPrompt</comment>');
    }

    /**
     * Display components in table format.
     */
    protected function displayTableFormat(array $components, string $type, bool $detailed): void
    {
        foreach ($components as $componentType => $items) {
            if (empty($items)) {
                continue;
            }

            $this->sectionHeader(ucfirst($componentType));

            if ($detailed) {
                $this->displayDetailedTable($items, $componentType);
            } else {
                $this->displaySimpleTable($items);
            }
        }
    }

    /**
     * Display a simple table of components.
     */
    protected function displaySimpleTable(array $items): void
    {
        $headers = ['Name', 'Description', 'Class'];
        $rows = [];

        foreach ($items as $item) {
            $rows[] = [
                $item['name'],
                $this->truncate($item['description'], 50),
                $this->formatClassName($item['class']),
            ];
        }

        $this->displayTable($headers, $rows);
    }

    /**
     * Display a detailed table of components.
     */
    protected function displayDetailedTable(array $items, string $type): void
    {
        foreach ($items as $item) {
            $this->newLine();
            $this->line("<fg=green>● {$item['name']}</>");
            $this->line("  <comment>Description:</comment> {$item['description']}");
            $this->line("  <comment>Class:</comment> {$item['class']}");

            if (isset($item['registered_at'])) {
                $this->line("  <comment>Registered:</comment> {$item['registered_at']}");
            }

            // Type-specific details
            switch ($type) {
                case 'tools':
                    $this->displayToolDetails($item);
                    break;
                case 'resources':
                    $this->displayResourceDetails($item);
                    break;
                case 'prompts':
                    $this->displayPromptDetails($item);
                    break;
            }
        }
    }

    /**
     * Display tool-specific details.
     */
    protected function displayToolDetails(array $tool): void
    {
        if (! empty($tool['parameters'])) {
            $this->line('  <comment>Parameters:</comment>');
            foreach ($tool['parameters'] as $param => $details) {
                $paramInfo = is_array($details) ? json_encode($details) : $details;
                $this->line("    - {$param}: {$paramInfo}");
            }
        }

        if (! empty($tool['input_schema'])) {
            $this->line('  <comment>Input Schema:</comment>');
            $schemaLines = explode("\n", json_encode($tool['input_schema'], JSON_PRETTY_PRINT));
            foreach ($schemaLines as $line) {
                $this->line("    {$line}");
            }
        }
    }

    /**
     * Display resource-specific details.
     */
    protected function displayResourceDetails(array $resource): void
    {
        if (isset($resource['uri'])) {
            $this->line("  <comment>URI:</comment> {$resource['uri']}");
        }

        if (isset($resource['mime_type'])) {
            $this->line("  <comment>MIME Type:</comment> {$resource['mime_type']}");
        }
    }

    /**
     * Display prompt-specific details.
     */
    protected function displayPromptDetails(array $prompt): void
    {
        if (! empty($prompt['arguments'])) {
            $this->line('  <comment>Arguments:</comment>');
            foreach ($prompt['arguments'] as $arg => $details) {
                $argInfo = is_array($details) ? json_encode($details) : $details;
                $this->line("    - {$arg}: {$argInfo}");
            }
        }
    }

    /**
     * Display components in JSON format.
     */
    protected function displayJsonFormat(array $components, bool $detailed): void
    {
        $output = [];

        foreach ($components as $type => $items) {
            if ($detailed) {
                $output[$type] = $items;
            } else {
                $output[$type] = array_map(function ($item) {
                    return [
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'class' => $item['class'],
                    ];
                }, $items);
            }
        }

        $this->displayJson($output);
    }

    /**
     * Display components in YAML format.
     */
    protected function displayYamlFormat(array $components, bool $detailed): void
    {
        $output = [];

        foreach ($components as $type => $items) {
            if ($detailed) {
                $output[$type] = $items;
            } else {
                $output[$type] = array_map(function ($item) {
                    return [
                        'name' => $item['name'],
                        'description' => $item['description'],
                        'class' => $item['class'],
                    ];
                }, $items);
            }
        }

        $yaml = Yaml::dump($output, 4, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $this->line($yaml);
    }

    /**
     * Display a summary of registered components.
     */
    protected function displaySummary(array $components): void
    {
        $counts = [];
        $total = 0;

        foreach ($components as $type => $items) {
            $count = count($items);
            $counts[$type] = $count;
            $total += $count;
        }

        $this->newLine();
        $this->sectionHeader('Summary');

        if ($total === 0) {
            $this->line('No components registered');
        } else {
            foreach ($counts as $type => $count) {
                $this->line(sprintf(
                    '  <comment>%s:</comment> %d',
                    ucfirst($type),
                    $count
                ));
            }
            $this->line(sprintf('  <comment>Total:</comment> <info>%d</info>', $total));
        }
    }

    /**
     * Truncate a string to a specified length.
     */
    protected function truncate(string $string, int $length): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - 3).'...';
    }

    /**
     * Format a class name for display.
     */
    protected function formatClassName(string $className): string
    {
        // If it's a fully qualified class name, show only the last part
        if (str_contains($className, '\\')) {
            $parts = explode('\\', $className);

            return end($parts);
        }

        return $className;
    }
}
