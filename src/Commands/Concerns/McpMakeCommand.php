<?php

namespace JTD\LaravelMCP\Commands\Concerns;

use Illuminate\Support\Str;

/**
 * Base functionality for MCP make commands.
 *
 * This trait provides shared methods for generating MCP component classes
 * with proper naming conventions, path resolution, and stub processing.
 */
trait McpMakeCommand
{
    use SecuresMakeCommands;

    /**
     * Get the class name from the fully qualified name.
     */
    protected function getClassName(string $name): string
    {
        return class_basename($name);
    }

    /**
     * Get the component name from the class name.
     *
     * This converts PascalCase to snake_case and removes common suffixes.
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
     */
    protected function getToolName(string $className): string
    {
        return $this->getComponentName($className, 'tool');
    }

    /**
     * Get the resource name from the class name.
     */
    protected function getResourceName(string $className): string
    {
        return $this->getComponentName($className, 'resource');
    }

    /**
     * Get the prompt name from the class name.
     */
    protected function getPromptName(string $className): string
    {
        return $this->getComponentName($className, 'prompt');
    }

    /**
     * Display success message with component details.
     */
    protected function displayMcpSuccessMessage(string $componentType, array $details = []): void
    {
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
        }
    }

    /**
     * Get the stub file path with fallback logic.
     */
    protected function getStubPath(string $stubName): string
    {
        // Check for published stub first (this is where published stubs would be)
        $publishedStub = resource_path("stubs/mcp/{$stubName}.stub");
        if (file_exists($publishedStub)) {
            return $publishedStub;
        }

        // Use package stub (correct path based on current directory structure)
        $packageStub = __DIR__."/../../../resources/stubs/{$stubName}.stub";

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
     */
    protected function getMcpNamespace(string $rootNamespace, string $componentType): string
    {
        $componentNamespace = ucfirst(Str::plural(strtolower($componentType)));

        return $rootNamespace."\\Mcp\\{$componentNamespace}";
    }

    /**
     * Generate a human-readable description from component name.
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
}
