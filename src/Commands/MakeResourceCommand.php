<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Commands\Concerns\McpMakeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Artisan command for generating MCP resource classes.
 *
 * This command creates a new MCP resource class from a stub template,
 * handling namespace resolution, file placement, model integration,
 * and stub variable replacement.
 */
#[AsCommand(name: 'make:mcp-resource')]
class MakeResourceCommand extends GeneratorCommand
{
    use McpMakeCommand;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:mcp-resource 
                           {name : The name of the resource class}
                           {--model= : Associated Eloquent model}
                           {--force : Overwrite existing files}
                           {--uri-template= : URI template pattern}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new MCP resource class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'MCP Resource';

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
            $this->error("Failed to create resource: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->getStubPath('resource');
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Mcp\\Resources';
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
        $resourceName = $this->getResourceName($className);
        $description = $this->getResourceDescription();
        $uri = $this->getResourceUri($className);

        $replacements = [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $className,
            '{{ name }}' => $resourceName,
            '{{ description }}' => $description,
            '{{ uri }}' => $uri,
        ];

        return $this->secureStubReplacement($stub, $replacements);
    }

    /**
     * Get the class name from the fully qualified name.
     */
    protected function getClassName(string $name): string
    {
        return class_basename($name);
    }

    /**
     * Get the resource name from the class name.
     */
    protected function getResourceName(string $className): string
    {
        // Convert PascalCase to snake_case for resource name
        $name = Str::snake($className);

        // Remove _resource suffix if present
        $name = preg_replace('/_resource$/', '', $name);

        return $name;
    }

    /**
     * Get the resource description.
     */
    protected function getResourceDescription(): string
    {
        $className = $this->getClassName($this->getNameInput());
        $resourceName = str_replace('_', ' ', $this->getResourceName($className));

        $model = $this->validateModelName($this->option('model'));
        if ($model) {
            return "Resource providing access to {$model} model data via {$resourceName}";
        }

        return "A resource providing {$resourceName} data";
    }

    /**
     * Get the resource URI template.
     */
    protected function getResourceUri(string $className): string
    {
        $uriTemplate = $this->validateUriTemplate($this->option('uri-template'));

        if (! empty($uriTemplate)) {
            // Ensure URI starts with scheme or is relative
            if (! str_contains($uriTemplate, '://') && ! str_starts_with($uriTemplate, '/')) {
                $uriTemplate = '/'.$uriTemplate;
            }

            return $uriTemplate;
        }

        // Generate default URI from resource name
        $resourceName = $this->getResourceName($className);

        $model = $this->validateModelName($this->option('model'));
        if ($model) {
            $modelName = Str::snake(class_basename($model));

            return "/{$modelName}/{id}";
        }

        return "/{$resourceName}";
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

        // Validate model name and check if it exists
        $model = $this->validateModelName($this->option('model'));
        if ($model) {
            $this->checkModelExists($model);
        }

        // Validate URI template
        $this->validateUriTemplate($this->option('uri-template'));
    }

    /**
     * Qualify the given model class name.
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
     * Display success message with details.
     */
    protected function displaySuccessMessage(): void
    {
        $className = $this->getClassName($this->qualifyClass($this->getNameInput()));
        $resourceName = $this->getResourceName($className);
        $uri = $this->getResourceUri($className);

        $details = [
            'Resource Name' => $resourceName,
            'URI' => $uri,
        ];

        if ($model = $this->validateModelName($this->option('model'))) {
            $details['Model'] = $model;
        }

        $this->displayMcpSuccessMessage('resource', $details);
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the resource class'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['model', 'm', InputOption::VALUE_OPTIONAL, 'Associated Eloquent model'],
            ['force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files'],
            ['uri-template', 'u', InputOption::VALUE_OPTIONAL, 'URI template pattern'],
        ];
    }
}
