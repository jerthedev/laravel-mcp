<?php

namespace JTD\LaravelMCP\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command for generating MCP tool classes.
 *
 * This command creates a new MCP tool class from a stub template,
 * handling namespace resolution, file placement, and stub variable replacement.
 */
#[AsCommand(name: 'make:mcp-tool')]
class MakeToolCommand extends BaseMcpGeneratorCommand
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:mcp-tool 
                           {name : The name of the tool class}
                           {--force : Overwrite existing files}
                           {--description= : Tool description}
                           {--parameters= : JSON string of tool parameters}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP tool class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'MCP Tool';

    /**
     * Get the stub name for this generator.
     *
     * @return string The stub filename
     */
    protected function getStubName(): string
    {
        return 'tool';
    }

    /**
     * Get the component type for this generator.
     *
     * @return string The component type
     */
    protected function getComponentType(): string
    {
        return 'tool';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceStubVariables($stub, $name);
    }

    /**
     * Replace stub variables with actual values.
     */
    protected function replaceStubVariables(string $stub, string $name): string
    {
        $className = $this->getClassName($name);
        $namespace = $this->getNamespace($name);
        $toolName = $this->getToolName($className);
        $description = $this->getToolDescription();

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ name }}' => $toolName,
            '{{ description }}' => $description,
        ];

        return $this->secureStubReplacement($stub, $replacements);
    }

    /**
     * Get the tool description.
     */
    protected function getToolDescription(): string
    {
        $description = $this->validateAndSanitizeDescription($this->option('description'));

        if (empty($description)) {
            $className = $this->getClassName($this->getNameInput());
            $toolName = str_replace('_', ' ', $this->getToolName($className));
            $description = "A tool for {$toolName} operations";
        }

        return $description;
    }

    /**
     * Validate and sanitize all command inputs.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateAndSanitizeInputs(): void
    {
        // Validate and sanitize class name
        $this->validateAndSanitizeClassName($this->getNameInput());

        // Validate and parse parameters if provided
        $this->validateAndParseJsonInput($this->option('parameters'), 'parameters');

        // Validate description
        $this->validateAndSanitizeDescription($this->option('description'));
    }

    /**
     * Display success message with details.
     */
    protected function displaySuccessMessage(?string $componentType = null, array $details = []): void
    {
        $className = $this->getClassName($this->qualifyClass($this->getNameInput()));
        $toolName = $this->getToolName($className);

        parent::displaySuccessMessage('tool', [
            'Tool Name' => $toolName,
        ]);
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the tool class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files'],
            ['description', 'd', InputOption::VALUE_OPTIONAL, 'Tool description'],
            ['parameters', 'p', InputOption::VALUE_OPTIONAL, 'JSON string of tool parameters'],
        ];
    }
}
