<?php

namespace JTD\LaravelMCP\Commands;

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
class MakePromptCommand extends BaseMcpGeneratorCommand
{
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
     * Get the stub name for this generator.
     *
     * @return string The stub filename
     */
    protected function getStubName(): string
    {
        return 'prompt';
    }

    /**
     * Get the component type for this generator.
     *
     * @return string The component type
     */
    protected function getComponentType(): string
    {
        return 'prompt';
    }

    /**
     * Execute the console command.
     */

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
    protected function displaySuccessMessage(?string $componentType = null, array $details = []): void
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
