<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Commands\Concerns\McpMakeCommand;
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
class MakeToolCommand extends GeneratorCommand
{
    use McpMakeCommand;

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
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            // Validate and sanitize all inputs
            $this->validateAndSanitizeInputs();

            // Validate file creation is safe
            $this->validateFileCreation($this->getPath($this->qualifyClass($this->getNameInput())));

            // Call parent handle to generate the file
            $result = parent::handle();

            if ($result === false) {
                return self::FAILURE;
            }

            $this->displaySuccessMessage();

            return self::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $this->error("Validation error: {$e->getMessage()}");

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error("Failed to create tool: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->getStubPath('tool');
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->getMcpNamespace($rootNamespace, 'tool');
    }

    /**
     * Get the destination class path.
     */
    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        $relativePath = str_replace('\\', '/', $name).'.php';
        $fullPath = $this->laravel['path'].'/'.$relativePath;

        // Validate path security
        return $this->validateAndSecurePath($fullPath);
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
    protected function displaySuccessMessage(): void
    {
        $className = $this->getClassName($this->qualifyClass($this->getNameInput()));
        $toolName = $this->getToolName($className);

        $this->displayMcpSuccessMessage('tool', [
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
