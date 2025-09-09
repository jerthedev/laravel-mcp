<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Container\Container;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Traits\HandlesMcpRequests;
use JTD\LaravelMCP\Traits\ValidatesParameters;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * Abstract base class for MCP Resources.
 *
 * This class provides the foundation for creating MCP resources that can be
 * read by AI clients. Resources represent data sources that AI can access
 * within the Laravel application.
 */
abstract class McpResource
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
     * The name of the resource.
     */
    protected string $name;

    /**
     * A description of what the resource provides.
     */
    protected string $description;

    /**
     * The URI template for this resource.
     */
    protected string $uriTemplate;

    /**
     * The Eloquent model class for this resource (optional).
     */
    protected ?string $modelClass = null;

    /**
     * Middleware to apply to this resource.
     */
    protected array $middleware = [];

    /**
     * Whether this resource requires authentication.
     */
    protected bool $requiresAuth = false;

    /**
     * Create a new resource instance.
     */
    public function __construct()
    {
        $this->container = Container::getInstance();
        $this->validator = $this->container->make(\Illuminate\Contracts\Validation\Factory::class);
        $this->boot();
    }

    /**
     * Boot the resource. Override in child classes for initialization.
     */
    protected function boot(): void
    {
        // Override in child classes
    }

    /**
     * Get the resource name.
     */
    public function getName(): string
    {
        return $this->name ?? $this->generateNameFromClass();
    }

    /**
     * Get the resource description.
     */
    public function getDescription(): string
    {
        return $this->description ?? 'MCP Resource';
    }

    /**
     * Get the resource URI template.
     */
    public function getUriTemplate(): string
    {
        return $this->uriTemplate ?? $this->generateUriTemplate();
    }

    /**
     * Read the resource with optional parameters.
     *
     * @param  array  $params  Read parameters
     * @return mixed The resource content
     */
    public function read(array $params): mixed
    {
        if (! $this->authorize('read', $params)) {
            throw new UnauthorizedHttpException('', 'Unauthorized resource access');
        }

        $validatedParams = $this->validateParameters($params, 'read');

        return $this->handleRead($validatedParams);
    }

    /**
     * List available resources.
     *
     * @param  array  $params  List parameters
     * @return array List of resources
     */
    public function list(array $params = []): array
    {
        if (! $this->authorize('list', $params)) {
            throw new UnauthorizedHttpException('', 'Unauthorized resource listing');
        }

        $validatedParams = $this->validateParameters($params, 'list');

        return $this->handleList($validatedParams);
    }

    /**
     * Subscribe to resource changes (if supported).
     *
     * @param  array  $params  Subscription parameters
     * @return mixed Subscription response
     */
    public function subscribe(array $params): mixed
    {
        if (! $this->supportsSubscription()) {
            throw new \BadMethodCallException('Resource does not support subscriptions');
        }

        if (! $this->authorize('subscribe', $params)) {
            throw new UnauthorizedHttpException('', 'Unauthorized subscription');
        }

        return $this->handleSubscribe($params);
    }

    /**
     * Handle read operation. Override in child classes or use model-based implementation.
     */
    protected function handleRead(array $params): mixed
    {
        if ($this->modelClass) {
            return $this->readFromModel($params);
        }

        return $this->customRead($params);
    }

    /**
     * Handle list operation. Override in child classes or use model-based implementation.
     */
    protected function handleList(array $params): array
    {
        if ($this->modelClass) {
            return $this->listFromModel($params);
        }

        return $this->customList($params);
    }

    /**
     * Read from Eloquent model.
     */
    protected function readFromModel(array $params): mixed
    {
        $model = $this->make($this->modelClass);

        if (isset($params['id'])) {
            return $model->findOrFail($params['id'])->toArray();
        }

        return $model->first()?->toArray();
    }

    /**
     * List from Eloquent model with pagination and filtering.
     */
    protected function listFromModel(array $params): array
    {
        $model = $this->make($this->modelClass);
        $query = $model->newQuery();

        // Apply filters
        if (isset($params['filters'])) {
            foreach ($params['filters'] as $field => $value) {
                $query->where($field, $value);
            }
        }

        // Apply pagination
        $perPage = $params['per_page'] ?? 15;
        $page = $params['page'] ?? 1;

        return $query->paginate($perPage, ['*'], 'page', $page)->toArray();
    }

    /**
     * Custom read implementation. Override in child classes.
     */
    protected function customRead(array $params): mixed
    {
        throw new \BadMethodCallException('Custom read method not implemented');
    }

    /**
     * Custom list implementation. Override in child classes.
     */
    protected function customList(array $params): array
    {
        throw new \BadMethodCallException('Custom list method not implemented');
    }

    /**
     * Handle subscription. Override in child classes.
     */
    protected function handleSubscribe(array $params): mixed
    {
        // Default subscription handling
        return ['subscribed' => true, 'resource' => $this->getName()];
    }

    /**
     * Check if resource supports subscriptions. Override in child classes.
     */
    protected function supportsSubscription(): bool
    {
        return false;
    }

    /**
     * Authorize resource access.
     */
    protected function authorize(string $action, array $params): bool
    {
        if (! $this->requiresAuth) {
            return true;
        }

        // Default authorization logic - can be overridden in child classes
        return true;
    }

    /**
     * Generate resource name from class name.
     */
    private function generateNameFromClass(): string
    {
        $className = class_basename($this);

        return Str::snake(str_replace('Resource', '', $className));
    }

    /**
     * Generate URI template from resource name.
     */
    private function generateUriTemplate(): string
    {
        $name = $this->getName();

        return "{$name}/*";
    }

    /**
     * Resolve a dependency from the Laravel container.
     */
    protected function make(string $abstract, array $parameters = [])
    {
        return $this->container->make($abstract, $parameters);
    }

    /**
     * Get the resource definition for MCP.
     */
    public function toArray(): array
    {
        return [
            'uri' => $this->getUriTemplate(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'mimeType' => 'application/json',
        ];
    }

    /**
     * Format content for MCP response.
     */
    protected function formatContent(mixed $content): array
    {
        return [
            'contents' => [
                [
                    'uri' => $this->getUriTemplate(),
                    'mimeType' => 'application/json',
                    'text' => is_string($content) ? $content : json_encode($content),
                ],
            ],
        ];
    }
}
