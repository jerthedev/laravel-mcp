<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Commands\Concerns\McpMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command for generating MCP prompt classes.
 *
 * This command creates a new MCP prompt class from a stub template,
 * handling namespace resolution, file placement, template integration,
 * and stub variable replacement.
 */
#[AsCommand(name: 'make:mcp-prompt')]
class MakePromptCommand extends GeneratorCommand
{
    use McpMakeCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:mcp-prompt 
                           {name : The name of the prompt class}
                           {--template= : Prompt template file}
                           {--variables= : JSON string of template variables}
                           {--force : Overwrite existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP prompt class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'MCP Prompt';

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
            $this->error("Failed to create prompt: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->getStubPath('prompt');
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $this->getMcpNamespace($rootNamespace, 'prompt');
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
        $promptName = $this->getPromptName($className);
        $description = $this->getPromptDescription();

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ name }}' => $promptName,
            '{{ description }}' => $description,
        ];

        return $this->secureStubReplacement($stub, $replacements);
    }

    /**
     * Get the prompt description.
     */
    protected function getPromptDescription(): string
    {
        $className = $this->getClassName($this->getNameInput());
        $promptName = str_replace('_', ' ', $this->getPromptName($className));

        $template = $this->validateTemplatePath($this->option('template'));
        if ($template) {
            return "A prompt for {$promptName} using template {$template}";
        }

        return "A prompt for {$promptName} generation";
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

        // Validate template path and check if it exists
        $templatePath = $this->validateTemplatePath($this->option('template'));
        if ($templatePath) {
            $this->checkTemplateExists($this->resolveTemplatePath($templatePath));
        }

        // Validate and parse variables
        $this->validateAndParseJsonInput($this->option('variables'), 'variables');
    }

    /**
     * Resolve the template file path.
     */
    protected function resolveTemplatePath(string $template): string
    {
        // Validate template path first
        $validatedTemplate = $this->validateTemplatePath($template);

        // If absolute path, validate it's in allowed directories
        if (str_starts_with($validatedTemplate, '/')) {
            return $this->validateAndSecurePath($validatedTemplate);
        }

        // Try resources/views first
        $viewPath = resource_path("views/{$validatedTemplate}");
        if (file_exists($viewPath)) {
            return $this->validateAndSecurePath($viewPath);
        }

        // Try resources/templates
        $templatePath = resource_path("templates/{$validatedTemplate}");
        if (file_exists($templatePath)) {
            return $this->validateAndSecurePath($templatePath);
        }

        // Default to resources/views (will be validated when file is created)
        return $this->validateAndSecurePath($viewPath);
    }

    /**
     * Display success message with details.
     */
    protected function displaySuccessMessage(): void
    {
        $className = $this->getClassName($this->qualifyClass($this->getNameInput()));
        $promptName = $this->getPromptName($className);

        $details = [
            'Prompt Name' => $promptName,
        ];

        if ($template = $this->validateTemplatePath($this->option('template'))) {
            $details['Template'] = $template;
        }

        if ($variables = $this->validateAndParseJsonInput($this->option('variables'), 'variables')) {
            $variableList = implode(', ', array_keys($variables));
            $details['Variables'] = $variableList;
        }

        $this->displayMcpSuccessMessage('prompt', $details);
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the prompt class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['template', 't', InputOption::VALUE_OPTIONAL, 'Prompt template file'],
            ['variables', 'v', InputOption::VALUE_OPTIONAL, 'JSON string of template variables'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files'],
        ];
    }
}
