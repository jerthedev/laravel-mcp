<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Support\SchemaDocumenter;

/**
 * Generate documentation for MCP server components.
 *
 * This command generates comprehensive documentation for MCP server components,
 * including tools, resources, prompts, API reference, and usage guides.
 */
class DocumentationCommand extends BaseCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mcp:docs 
        {--format=markdown : Documentation format (markdown|html|json)} 
        {--output= : Output directory for documentation} 
        {--include-schemas : Include detailed schema documentation} 
        {--include-examples : Include usage examples} 
        {--api-only : Generate only API documentation} 
        {--components-only : Generate only component documentation} 
        {--single-file : Generate single consolidated documentation file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate comprehensive documentation for MCP server';

    /**
     * Documentation generator instance.
     */
    protected DocumentationGenerator $documentationGenerator;

    /**
     * Schema documenter instance.
     */
    protected SchemaDocumenter $schemaDocumenter;

    /**
     * Create a new command instance.
     */
    public function __construct(
        DocumentationGenerator $documentationGenerator,
        SchemaDocumenter $schemaDocumenter
    ) {
        parent::__construct();
        $this->documentationGenerator = $documentationGenerator;
        $this->schemaDocumenter = $schemaDocumenter;
    }

    /**
     * Execute the console command.
     */
    protected function executeCommand(): int
    {
        $this->sectionHeader('Generating MCP Server Documentation');

        // Determine what to generate
        $options = $this->gatherOptions();

        // Generate documentation
        $documentation = $this->generateDocumentation($options);

        // Save documentation
        if ($this->saveDocumentation($documentation, $options)) {
            $this->success('Documentation generated successfully!', [
                'Format' => $options['format'],
                'Output Directory' => $options['output'],
                'Files Generated' => count($documentation),
            ]);

            $this->displayGeneratedFiles($documentation, $options);

            return self::EXIT_SUCCESS;
        }

        return self::EXIT_ERROR;
    }

    /**
     * Gather options for documentation generation.
     */
    protected function gatherOptions(): array
    {
        $options = [
            'format' => $this->option('format') ?? 'markdown',
            'output' => $this->determineOutputDirectory(),
            'include_schemas' => $this->option('include-schemas'),
            'include_examples' => $this->option('include-examples'),
            'api_only' => $this->option('api-only'),
            'components_only' => $this->option('components-only'),
            'single_file' => $this->option('single-file'),
        ];

        // Add server information
        $options['server_name'] = config('laravel-mcp.server.name', 'Laravel MCP Server');
        $options['server_version'] = config('laravel-mcp.server.version', '1.0.0');
        $options['server_description'] = config(
            'laravel-mcp.server.description',
            'A Model Context Protocol server built with Laravel'
        );

        $this->debug('Documentation options', $options);

        return $options;
    }

    /**
     * Determine output directory for documentation.
     */
    protected function determineOutputDirectory(): string
    {
        if ($customOutput = $this->option('output')) {
            return $customOutput;
        }

        // Default to docs directory in project root
        return base_path('docs/mcp');
    }

    /**
     * Generate documentation based on options.
     */
    protected function generateDocumentation(array $options): array
    {
        $this->status('Generating documentation...');

        $documentation = [];

        if ($options['api_only']) {
            // Generate only API documentation
            $documentation = $this->generateApiDocumentation($options);
        } elseif ($options['components_only']) {
            // Generate only component documentation
            $documentation = $this->generateComponentDocumentation($options);
        } else {
            // Generate complete documentation
            $documentation = $this->generateCompleteDocumentation($options);
        }

        return $documentation;
    }

    /**
     * Generate complete documentation.
     */
    protected function generateCompleteDocumentation(array $options): array
    {
        $docs = $this->documentationGenerator->generateCompleteDocumentation([
            'name' => $options['server_name'],
            'version' => $options['server_version'],
            'description' => $options['server_description'],
        ]);

        // Add schema documentation if requested
        if ($options['include_schemas']) {
            $docs = $this->enrichWithSchemaDocumentation($docs);
        }

        // Add examples if requested
        if ($options['include_examples']) {
            $docs = $this->enrichWithExamples($docs);
        }

        return $docs;
    }

    /**
     * Generate API documentation only.
     */
    protected function generateApiDocumentation(array $options): array
    {
        $apiDoc = $this->documentationGenerator->generateApiReference();

        // Add schema documentation if requested
        if ($options['include_schemas']) {
            $apiDoc = $this->enrichApiWithSchemas($apiDoc);
        }

        return ['api-reference' => $apiDoc];
    }

    /**
     * Generate component documentation only.
     */
    protected function generateComponentDocumentation(array $options): array
    {
        $componentDocs = $this->documentationGenerator->generateComponentDocumentation();

        // Add schema documentation if requested
        if ($options['include_schemas']) {
            foreach ($componentDocs as $type => &$doc) {
                $doc = $this->enrichComponentWithSchemas($type, $doc);
            }
        }

        return $componentDocs;
    }

    /**
     * Enrich documentation with schema information.
     */
    protected function enrichWithSchemaDocumentation(array $docs): array
    {
        $this->status('Adding schema documentation...');

        // Add schema documentation to tools
        if (isset($docs['components']['tools'])) {
            $docs['components']['tools'] = $this->enrichToolsWithSchemas(
                $docs['components']['tools']
            );
        }

        // Add schema documentation to resources
        if (isset($docs['components']['resources'])) {
            $docs['components']['resources'] = $this->enrichResourcesWithSchemas(
                $docs['components']['resources']
            );
        }

        // Add schema documentation to prompts
        if (isset($docs['components']['prompts'])) {
            $docs['components']['prompts'] = $this->enrichPromptsWithSchemas(
                $docs['components']['prompts']
            );
        }

        return $docs;
    }

    /**
     * Enrich tools documentation with schemas.
     */
    protected function enrichToolsWithSchemas(string $toolsDoc): string
    {
        $registry = app('mcp.registry.tool');
        $enrichedDoc = $toolsDoc;

        foreach ($registry->all() as $name => $tool) {
            $metadata = $registry->getMetadata($name);

            if (! empty($metadata['input_schema'])) {
                $schemaDoc = $this->schemaDocumenter->documentToolSchema($metadata);
                $enrichedDoc = str_replace(
                    "## {$name}",
                    "## {$name}\n\n{$schemaDoc}",
                    $enrichedDoc
                );
            }
        }

        return $enrichedDoc;
    }

    /**
     * Enrich resources documentation with schemas.
     */
    protected function enrichResourcesWithSchemas(string $resourcesDoc): string
    {
        $registry = app('mcp.registry.resource');
        $enrichedDoc = $resourcesDoc;

        foreach ($registry->all() as $name => $resource) {
            $metadata = $registry->getMetadata($name);

            if (! empty($metadata['schema'])) {
                $schemaDoc = $this->schemaDocumenter->documentResourceSchema($metadata);
                $enrichedDoc = str_replace(
                    "## {$name}",
                    "## {$name}\n\n{$schemaDoc}",
                    $enrichedDoc
                );
            }
        }

        return $enrichedDoc;
    }

    /**
     * Enrich prompts documentation with schemas.
     */
    protected function enrichPromptsWithSchemas(string $promptsDoc): string
    {
        $registry = app('mcp.registry.prompt');
        $enrichedDoc = $promptsDoc;

        foreach ($registry->all() as $name => $prompt) {
            $metadata = $registry->getMetadata($name);

            if (! empty($metadata['arguments'])) {
                $schemaDoc = $this->schemaDocumenter->documentPromptSchema($metadata);
                $enrichedDoc = str_replace(
                    "## {$name}",
                    "## {$name}\n\n{$schemaDoc}",
                    $enrichedDoc
                );
            }
        }

        return $enrichedDoc;
    }

    /**
     * Enrich documentation with examples.
     */
    protected function enrichWithExamples(array $docs): array
    {
        $this->status('Adding usage examples...');

        // Add examples section if not present
        if (! isset($docs['examples'])) {
            $docs['examples'] = $this->documentationGenerator->generateExamples();
        }

        return $docs;
    }

    /**
     * Enrich API documentation with schemas.
     */
    protected function enrichApiWithSchemas(string $apiDoc): string
    {
        // Add schema documentation to API methods
        $registry = app('mcp.registry');

        $schemas = [
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ];

        // Collect all schemas
        foreach ($registry->getTools() as $name => $tool) {
            $metadata = $registry->getToolRegistry()->getMetadata($name);
            if (! empty($metadata['input_schema'])) {
                $schemas['tools'][$name] = $metadata['input_schema'];
            }
        }

        foreach ($registry->getResources() as $name => $resource) {
            $metadata = $registry->getResourceRegistry()->getMetadata($name);
            if (! empty($metadata['schema'])) {
                $schemas['resources'][$name] = $metadata['schema'];
            }
        }

        foreach ($registry->getPrompts() as $name => $prompt) {
            $metadata = $registry->getPromptRegistry()->getMetadata($name);
            if (! empty($metadata['arguments'])) {
                $schemas['prompts'][$name] = $metadata['arguments'];
            }
        }

        // Add schemas section to API documentation
        $schemaSection = "\n\n## Component Schemas\n\n";

        if (! empty($schemas['tools'])) {
            $schemaSection .= "### Tool Schemas\n\n";
            foreach ($schemas['tools'] as $name => $schema) {
                $schemaSection .= "#### {$name}\n\n";
                $schemaSection .= $this->schemaDocumenter->documentSchema($schema);
                $schemaSection .= "\n\n";
            }
        }

        if (! empty($schemas['resources'])) {
            $schemaSection .= "### Resource Schemas\n\n";
            foreach ($schemas['resources'] as $name => $schema) {
                $schemaSection .= "#### {$name}\n\n";
                $schemaSection .= $this->schemaDocumenter->documentSchema($schema);
                $schemaSection .= "\n\n";
            }
        }

        if (! empty($schemas['prompts'])) {
            $schemaSection .= "### Prompt Schemas\n\n";
            foreach ($schemas['prompts'] as $name => $schema) {
                $schemaSection .= "#### {$name}\n\n";
                $schemaSection .= $this->schemaDocumenter->documentSchema($schema);
                $schemaSection .= "\n\n";
            }
        }

        return $apiDoc.$schemaSection;
    }

    /**
     * Enrich component documentation with schemas.
     */
    protected function enrichComponentWithSchemas(string $type, string $doc): string
    {
        return match ($type) {
            'tools' => $this->enrichToolsWithSchemas($doc),
            'resources' => $this->enrichResourcesWithSchemas($doc),
            'prompts' => $this->enrichPromptsWithSchemas($doc),
            default => $doc,
        };
    }

    /**
     * Save documentation to files.
     */
    protected function saveDocumentation(array $documentation, array $options): bool
    {
        $outputDir = $options['output'];

        // Create output directory if it doesn't exist
        if (! File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("Created output directory: $outputDir");
        }

        if ($options['single_file']) {
            // Generate single consolidated file
            return $this->saveSingleFile($documentation, $outputDir, $options);
        } else {
            // Save as multiple files
            return $this->saveMultipleFiles($documentation, $outputDir, $options);
        }
    }

    /**
     * Save documentation as a single file.
     */
    protected function saveSingleFile(array $documentation, string $outputDir, array $options): bool
    {
        $filename = $outputDir.'/mcp-documentation.'.$this->getFileExtension($options['format']);
        $content = $this->consolidateDocumentation($documentation, $options['format']);

        try {
            File::put($filename, $content);
            $this->info("Documentation saved to: $filename");

            return true;
        } catch (\Exception $e) {
            $this->displayError('Failed to save documentation', [
                'File' => $filename,
                'Error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Save documentation as multiple files.
     */
    protected function saveMultipleFiles(array $documentation, string $outputDir, array $options): bool
    {
        $success = $this->documentationGenerator->saveDocumentation($documentation, $outputDir);

        if (! $success) {
            $this->displayError('Failed to save documentation files');

            return false;
        }

        $this->info("Documentation saved to: $outputDir");

        return true;
    }

    /**
     * Consolidate documentation into a single string.
     */
    protected function consolidateDocumentation(array $documentation, string $format): string
    {
        $content = '';

        foreach ($documentation as $section => $sectionContent) {
            if (is_array($sectionContent)) {
                foreach ($sectionContent as $subsection => $subsectionContent) {
                    $content .= $subsectionContent."\n\n";
                }
            } else {
                $content .= $sectionContent."\n\n";
            }
        }

        return $content;
    }

    /**
     * Get file extension based on format.
     */
    protected function getFileExtension(string $format): string
    {
        return match ($format) {
            'markdown' => 'md',
            'html' => 'html',
            'json' => 'json',
            default => 'txt',
        };
    }

    /**
     * Display list of generated files.
     */
    protected function displayGeneratedFiles(array $documentation, array $options): void
    {
        $this->newLine();
        $this->sectionHeader('Generated Documentation Files');

        if ($options['single_file']) {
            $filename = 'mcp-documentation.'.$this->getFileExtension($options['format']);
            $this->line("- $filename");
        } else {
            foreach ($documentation as $section => $content) {
                if (is_array($content)) {
                    $this->line("- $section/");
                    foreach (array_keys($content) as $subsection) {
                        $this->line("  - $subsection.md");
                    }
                } else {
                    $this->line("- $section.md");
                }
            }
        }

        $this->newLine();
        $this->comment('View documentation at: '.$options['output']);
    }
}
