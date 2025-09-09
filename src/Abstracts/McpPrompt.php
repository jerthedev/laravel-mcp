<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Str;
use Illuminate\View\Factory as ViewFactory;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Abstract base class for MCP Prompts.
 *
 * This class provides the foundation for creating MCP prompts that can be
 * used by AI clients. Prompts are templates that generate structured messages
 * for AI interactions within the Laravel application.
 */
abstract class McpPrompt
{
    use HandlesMcpRequests, ValidatesParameters;

    /**
     * Laravel container instance.
     */
    protected Container $container;

    /**
     * Laravel validation factory.
     */
    protected ValidationFactory $validator;

    /**
     * Laravel view factory.
     */
    protected ViewFactory $view;

    /**
     * The name of the prompt.
     */
    protected string $name;

    /**
     * A description of what the prompt generates.
     */
    protected string $description;

    /**
     * The argument definitions for this prompt.
     */
    protected array $arguments = [];

    /**
     * Optional template for this prompt.
     */
    protected ?string $template = null;

    /**
     * Middleware to apply to this prompt.
     */
    protected array $middleware = [];

    /**
     * Whether this prompt requires authentication.
     */
    protected bool $requiresAuth = false;

    /**
     * Create a new prompt instance.
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(ValidationFactory::class);
        $this->view = $this->container->make(ViewFactory::class);
        $this->boot();
    }

    /**
     * Boot the prompt. Override in child classes for initialization.
     */
    protected function boot(): void
    {
        // Override in child classes
    }

    /**
     * Get the prompt name.
     */
    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    /**
     * Get the prompt description.
     */
    public function getDescription(): string
    {
        return $this->description ?? 'MCP Prompt';
    }

    /**
     * Get the prompt arguments definition.
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get the messages for this prompt with the given arguments.
     *
     * @param  array  $arguments  The prompt arguments
     * @return array The generated messages
     */
    public function get(array $arguments = []): array
    {
        if (! $this->authorize($arguments)) {
            throw new UnauthorizedHttpException('', 'Unauthorized prompt access');
        }

        $validatedArgs = $this->validateArguments($arguments);

        return $this->handleGet($validatedArgs);
    }

    /**
     * Handle prompt generation.
     */
    protected function handleGet(array $arguments): array
    {
        $content = $this->generateContent($arguments);

        return [
            'description' => $this->getDescription(),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $content,
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate content for the prompt.
     */
    protected function generateContent(array $arguments): string
    {
        if ($this->template) {
            return $this->renderTemplate($arguments);
        }

        return $this->customContent($arguments);
    }

    /**
     * Render template with arguments.
     */
    protected function renderTemplate(array $arguments): string
    {
        if (str_contains($this->template, '.blade.php') || $this->view->exists($this->template)) {
            return $this->view->make($this->template, $arguments)->render();
        }

        // Simple string template - support both {{key}} and {key} formats
        $content = $this->template;
        foreach ($arguments as $key => $value) {
            $content = str_replace("{{{$key}}}", $value, $content);
            $content = str_replace("{{$key}}", $value, $content);
        }

        return $content;
    }

    /**
     * Generate custom content. Override in child classes.
     */
    protected function customContent(array $arguments): string
    {
        throw new \BadMethodCallException('Custom content method not implemented');
    }

    /**
     * Validate prompt arguments.
     */
    protected function validateArguments(array $arguments): array
    {
        if (empty($this->arguments)) {
            return $arguments;
        }

        $rules = $this->buildValidationRules();

        return $this->validator->make($arguments, $rules)->validated();
    }

    /**
     * Build Laravel validation rules from argument definitions.
     */
    private function buildValidationRules(): array
    {
        $rules = [];

        foreach ($this->arguments as $name => $config) {
            $rules[$name] = $this->buildArgumentRule($config);
        }

        return $rules;
    }

    /**
     * Build validation rule for a single argument.
     */
    private function buildArgumentRule(array $config): string
    {
        $rules = [];

        if ($config['required'] ?? false) {
            $rules[] = 'required';
        } else {
            $rules[] = 'nullable';
        }

        switch ($config['type'] ?? 'string') {
            case 'string':
                $rules[] = 'string';
                if (isset($config['max_length'])) {
                    $rules[] = "max:{$config['max_length']}";
                }
                break;
            case 'integer':
                $rules[] = 'integer';
                break;
            case 'number':
                $rules[] = 'numeric';
                break;
            case 'boolean':
                $rules[] = 'boolean';
                break;
            case 'array':
                $rules[] = 'array';
                break;
        }

        return implode('|', $rules);
    }

    /**
     * Authorize prompt access.
     */
    protected function authorize(array $arguments): bool
    {
        if (! $this->requiresAuth) {
            return true;
        }

        // Default authorization logic - can be overridden in child classes
        return true;
    }

    /**
     * Generate prompt name from class name.
     */
    private function generateNameFromClass(): string
    {
        $className = class_basename($this);

        return Str::snake(str_replace('Prompt', '', $className));
    }

    /**
     * Resolve a dependency from the Laravel container.
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Get the prompt definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'arguments' => $this->getArguments(),
        ];
    }

    /**
     * Create a message structure for MCP.
     */
    protected function createMessage(string $role, string $content): array
    {
        return [
            'role' => $role,
            'content' => [
                'type' => 'text',
                'text' => $content,
            ],
        ];
    }

    /**
     * Format messages for MCP response.
     */
    protected function formatMessages(array $messages): array
    {
        return [
            'messages' => $messages,
        ];
    }

    /**
     * Apply template variables to a string.
     */
    protected function applyTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}
