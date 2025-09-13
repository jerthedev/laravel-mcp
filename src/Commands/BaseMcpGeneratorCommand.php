<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Commands\Concerns\SecuresMakeCommands;

/**
 * Base generator command for all MCP component generators.
 *
 * This abstract class provides common functionality for generating MCP components
 * including proper namespace resolution, path handling, and stub processing.
 */
abstract class BaseMcpGeneratorCommand extends GeneratorCommand
{
    use SecuresMakeCommands;

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'MCP Component';

    /**
     * Get the destination class path.
     *
     * @param  string  $name  The fully qualified class name
     * @return string The destination file path
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
     * Get the root namespace for the class.
     *
     * @return string The root namespace
     */
    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace();
    }

    /**
     * Get the stub file for the generator.
     *
     * This method must be implemented by child classes to specify
     * which stub file to use for generation.
     *
     * @return string The stub file path
     */
    protected function getStub(): string
    {
        $stubName = $this->getStubName();

        return $this->getStubPath($stubName);
    }

    /**
     * Get the stub name for this generator.
     *
     * This method must be implemented by child classes.
     *
     * @return string The stub filename (without extension)
     */
    abstract protected function getStubName(): string;

    /**
     * Get the stub file path with fallback logic.
     *
     * @param  string  $stubName  The stub filename
     * @return string The full path to the stub file
     */
    protected function getStubPath(string $stubName): string
    {
        // Check for published stub first (this is where published stubs would be)
        $publishedStub = resource_path("stubs/mcp/{$stubName}.stub");
        if (file_exists($publishedStub)) {
            return $publishedStub;
        }

        // Use package stub (correct path based on current directory structure)
        $packageStub = __DIR__."/../../resources/stubs/{$stubName}.stub";

        // Check if the stub file exists and warn if it doesn't
        if (! file_exists($packageStub)) {
            $this->warn("Stub file {$stubName}.stub does not exist at expected locations:");
            $this->comment("- {$publishedStub}");
            $this->comment("- {$packageStub}");
            $this->comment('Run `php artisan vendor:publish --tag=laravel-mcp` to publish the stubs.');
        }

        return $packageStub;
    }

    /**
     * Get the default namespace for MCP components.
     *
     * @param  string  $rootNamespace  The application root namespace
     * @return string The MCP component namespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $componentType = $this->getComponentType();
        $componentNamespace = ucfirst(Str::plural(strtolower($componentType)));

        return $rootNamespace."\\Mcp\\{$componentNamespace}";
    }

    /**
     * Get the component type for this generator.
     *
     * This method should be implemented by child classes to specify
     * the component type (tool, resource, prompt).
     *
     * @return string The component type
     */
    abstract protected function getComponentType(): string;

    /**
     * Get the class name from the fully qualified name.
     *
     * @param  string  $name  The fully qualified class name
     * @return string The class name without namespace
     */
    protected function getClassName(string $name): string
    {
        return class_basename($name);
    }

    /**
     * Get the component name from the class name.
     *
     * This converts PascalCase to snake_case and removes common suffixes.
     *
     * @param  string  $className  The class name
     * @param  string  $suffix  Optional suffix to remove
     * @return string The component name
     */
    protected function getComponentName(string $className, string $suffix = ''): string
    {
        // Convert PascalCase to snake_case
        $name = Str::snake($className);

        // Remove component suffix if present
        if (! empty($suffix)) {
            $name = preg_replace('/_'.strtolower($suffix).'$/', '', $name);
        }

        return $name;
    }

    /**
     * Get the tool name from the class name.
     *
     * @param  string  $className  The class name
     * @return string The tool name
     */
    protected function getToolName(string $className): string
    {
        return $this->getComponentName($className, 'tool');
    }

    /**
     * Get the resource name from the class name.
     *
     * @param  string  $className  The class name
     * @return string The resource name
     */
    protected function getResourceName(string $className): string
    {
        return $this->getComponentName($className, 'resource');
    }

    /**
     * Get the prompt name from the class name.
     *
     * @param  string  $className  The class name
     * @return string The prompt name
     */
    protected function getPromptName(string $className): string
    {
        return $this->getComponentName($className, 'prompt');
    }

    /**
     * Display success message with component details.
     *
     * @param  string  $componentType  The component type
     * @param  array  $details  Additional details to display
     */
    protected function displaySuccessMessage(?string $componentType = null, array $details = []): void
    {
        $componentType = $componentType ?: $this->getComponentType();
        $className = $this->getClassName($this->qualifyClass($this->getNameInput()));
        $path = $this->getPath($this->qualifyClass($this->getNameInput()));

        $this->info(ucfirst($componentType).' created successfully!');
        $this->newLine();
        $this->line("<comment>Class:</comment> {$className}");

        // Display component-specific details
        foreach ($details as $key => $value) {
            if (! empty($value)) {
                $this->line("<comment>{$key}:</comment> {$value}");
            }
        }

        $this->line("<comment>File:</comment> {$path}");
        $this->newLine();

        // Display component-specific next steps
        $this->displayNextSteps($componentType);
    }

    /**
     * Display next steps based on component type.
     *
     * @param  string  $componentType  The component type
     */
    protected function displayNextSteps(string $componentType): void
    {
        $this->comment('Next steps:');

        switch (strtolower($componentType)) {
            case 'tool':
                $this->line('1. Define your tool parameters in the $inputSchema property');
                $this->line('2. Implement the execute() method with your tool logic');
                $this->line('3. The tool will be automatically discovered and registered');
                break;

            case 'resource':
                $this->line('1. Implement the read() method to return resource data');
                $this->line('2. Optionally implement list() for resource collections');
                $this->line('3. Set up subscription support if needed');
                $this->line('4. The resource will be automatically discovered and registered');
                break;

            case 'prompt':
                $this->line('1. Define your prompt arguments in the $argumentsSchema property');
                $this->line('2. Implement the getMessages() method with your prompt logic');
                $this->line('3. Add template variables and content generation logic');
                $this->line('4. The prompt will be automatically discovered and registered');
                break;

            default:
                $this->line('1. Implement the required methods for your component');
                $this->line('2. The component will be automatically discovered and registered');
                break;
        }
    }

    /**
     * Generate a human-readable description from component name.
     *
     * @param  string  $componentName  The component name
     * @param  string  $componentType  The component type
     * @return string The generated description
     */
    protected function generateDefaultDescription(string $componentName, string $componentType): string
    {
        $readableName = str_replace('_', ' ', $componentName);

        switch (strtolower($componentType)) {
            case 'tool':
                return "A tool for {$readableName} operations";
            case 'resource':
                return "A resource providing {$readableName} data";
            case 'prompt':
                return "A prompt for {$readableName} generation";
            default:
                return "A {$componentType} for {$readableName}";
        }
    }

    /**
     * Validate that all required stub replacements are provided.
     *
     * @param  array  $replacements  The replacement array
     *
     * @throws \InvalidArgumentException If required replacements are missing
     */
    protected function validateStubReplacements(array $replacements): void
    {
        $required = ['{{ namespace }}', '{{ class }}', '{{ name }}'];

        foreach ($required as $placeholder) {
            if (! array_key_exists($placeholder, $replacements)) {
                throw new \InvalidArgumentException("Missing required stub replacement: {$placeholder}");
            }

            if (empty($replacements[$placeholder])) {
                throw new \InvalidArgumentException("Empty value for stub replacement: {$placeholder}");
            }
        }
    }

    /**
     * Get qualified model class name with security validation.
     *
     * @param  string  $model  The model name
     * @return string The fully qualified model class name
     */
    protected function qualifyModel(string $model): string
    {
        $model = ltrim($model, '\\/');
        $model = str_replace('/', '\\', $model);

        // Get root namespace safely
        $rootNamespace = 'App\\';
        if (method_exists($this, 'rootNamespace')) {
            try {
                $rootNamespace = $this->rootNamespace();
            } catch (\Throwable $e) {
                // Fall back to default if Laravel app not available
                $rootNamespace = 'App\\';
            }
        }

        if (Str::startsWith($model, $rootNamespace)) {
            return $model;
        }

        return $rootNamespace.'Models\\'.$model;
    }

    /**
     * Check if a model class exists and warn if it doesn't.
     *
     * @param  string  $model  The model name
     */
    protected function checkModelExists(string $model): void
    {
        if (empty($model)) {
            return;
        }

        $modelClass = $this->qualifyModel($model);

        if (! class_exists($modelClass)) {
            $this->warn("Model class {$modelClass} does not exist.");
            $this->comment('The component will be created anyway, but you may need to create the model or adjust the class name.');
        }
    }

    /**
     * Check if a template file exists and warn if it doesn't.
     *
     * @param  string  $templatePath  The template file path
     */
    protected function checkTemplateExists(string $templatePath): void
    {
        if (empty($templatePath)) {
            return;
        }

        if (! file_exists($templatePath)) {
            $this->warn("Template file {$templatePath} does not exist.");
            $this->comment('The component will be created anyway, but you may need to create the template or adjust the path.');
        }
    }

    /**
     * Execute the console command with comprehensive validation.
     *
     * @return int Exit code
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
        } catch (\Throwable $e) {
            $this->error("Failed to create {$this->type}: {$e->getMessage()}");

            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }
}
