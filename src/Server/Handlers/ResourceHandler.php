<?php

namespace JTD\LaravelMCP\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\ResourceRegistry;

/**
 * Handler for MCP Resource operations.
 *
 * This class implements handlers for resource-related MCP operations:
 * - resources/list: List all available resources
 * - resources/read: Read content from a specific resource
 *
 * Follows MCP 1.0 specification and JSON-RPC 2.0 protocol requirements.
 */
class ResourceHandler extends BaseHandler
{
    /**
     * Resource registry for managing resources.
     */
    protected ResourceRegistry $resourceRegistry;

    /**
     * Create a new resource handler instance.
     */
    public function __construct(ResourceRegistry $resourceRegistry, bool $debug = false)
    {
        parent::__construct($debug);
        $this->resourceRegistry = $resourceRegistry;
        $this->handlerName = 'ResourceHandler';
    }

    /**
     * Handle resource-related MCP requests.
     *
     * @param  string  $method  The MCP method being called
     * @param  array  $params  Request parameters
     * @param  array  $context  Additional context (request ID, etc.)
     * @return array Response data
     *
     * @throws ProtocolException If the request is invalid or processing fails
     */
    public function handle(string $method, array $params, array $context = []): array
    {
        $this->logDebug("Handling {$method}", [
            'params' => $this->sanitizeForLogging($params),
            'context' => $context,
        ]);

        try {
            return match ($method) {
                'resources/list' => $this->handleResourcesList($params, $context),
                'resources/read' => $this->handleResourcesRead($params, $context),
                default => throw new ProtocolException("Unsupported method: {$method}", -32601),
            };
        } catch (\Throwable $e) {
            return $this->handleException($e, $method, $context);
        }
    }

    /**
     * Get supported methods for this handler.
     *
     * @return array Array of supported method names
     */
    public function getSupportedMethods(): array
    {
        return ['resources/list', 'resources/read'];
    }

    /**
     * Handle resources/list request.
     *
     * Returns a list of all available resources with their metadata according to MCP spec.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with resources list
     */
    protected function handleResourcesList(array $params, array $context = []): array
    {
        $this->logDebug('Processing resources/list request');

        // Validate cursor parameter if provided
        if (isset($params['cursor'])) {
            $this->validateRequest($params, [
                'cursor' => 'string',
            ]);
        }

        try {
            $resources = $this->getResourceDefinitions($params['cursor'] ?? null);

            $response = [
                'resources' => $resources,
            ];

            // Add next cursor if pagination is implemented
            if (isset($params['cursor']) && $this->hasMoreResources($params['cursor'], count($resources))) {
                $response['nextCursor'] = $this->getNextCursor($params['cursor'], $resources);
            }

            $this->logInfo('Resources list generated', [
                'resource_count' => count($resources),
                'has_cursor' => isset($params['cursor']),
            ]);

            return $this->createSuccessResponse($response, $context);

        } catch (\Throwable $e) {
            $this->logError('Failed to list resources', [
                'error' => $e->getMessage(),
            ]);
            throw new ProtocolException('Failed to retrieve resources list: '.$e->getMessage(), -32603);
        }
    }

    /**
     * Handle resources/read request.
     *
     * Reads content from a resource identified by URI and returns it.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with resource content
     *
     * @throws ProtocolException If resource is not found or read fails
     */
    protected function handleResourcesRead(array $params, array $context = []): array
    {
        $this->logDebug('Processing resources/read request', [
            'uri' => $params['uri'] ?? 'unknown',
        ]);

        // Validate required parameters
        $this->validateRequest($params, [
            'uri' => 'required|string',
        ]);

        $uri = $params['uri'];

        try {
            // Find resource by URI
            $resource = $this->findResourceByUri($uri);

            if (! $resource) {
                $this->logWarning("Resource not found for URI: {$uri}");
                throw new ProtocolException("Resource not found: {$uri}", -32601);
            }

            $this->logInfo("Reading resource: {$uri}");

            // Read resource content
            $content = $this->readResourceContent($resource, $params);

            // Format the response according to MCP spec
            $response = [
                'contents' => $content,
            ];

            $this->logInfo("Resource read successfully: {$uri}", [
                'content_count' => count($content),
            ]);

            return $this->createSuccessResponse($response, $context);

        } catch (ProtocolException $e) {
            // Re-throw protocol exceptions
            throw $e;
        } catch (\Throwable $e) {
            $this->logError("Resource read failed: {$uri}", [
                'error' => $e->getMessage(),
            ]);
            throw new ProtocolException("Failed to read resource: {$e->getMessage()}", -32603);
        }
    }

    /**
     * Get resource definitions for MCP response.
     *
     * @param  string|null  $cursor  Pagination cursor
     * @return array Array of resource definitions
     */
    protected function getResourceDefinitions(?string $cursor = null): array
    {
        $resources = $this->resourceRegistry->all();
        $definitions = [];

        foreach ($resources as $name => $resource) {
            try {
                $definition = [
                    'uri' => $this->getResourceUri($name, $resource),
                    'name' => $name,
                    'description' => $this->getResourceDescription($resource),
                    'mimeType' => $this->getResourceMimeType($resource),
                ];

                // Add optional metadata
                $metadata = $this->getResourceMetadata($resource);
                if (! empty($metadata)) {
                    $definition = array_merge($definition, $metadata);
                }

                $definitions[] = $definition;
            } catch (\Throwable $e) {
                $this->logWarning("Failed to get definition for resource: {$name}", [
                    'error' => $e->getMessage(),
                ]);
                // Skip resources that can't be properly defined
            }
        }

        // Apply cursor-based pagination if needed
        if ($cursor !== null) {
            return $this->applyCursorPagination($definitions, $cursor);
        }

        return $definitions;
    }

    /**
     * Find resource by URI.
     *
     * @param  string  $uri  Resource URI
     * @return mixed|null Resource instance or null if not found
     */
    protected function findResourceByUri(string $uri)
    {
        $resources = $this->resourceRegistry->all();

        foreach ($resources as $name => $resource) {
            $resourceUri = $this->getResourceUri($name, $resource);
            if ($resourceUri === $uri) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * Read content from a resource.
     *
     * @param  mixed  $resource  Resource instance
     * @param  array  $params  Request parameters
     * @return array Array of content items
     */
    protected function readResourceContent($resource, array $params = []): array
    {
        $content = null;

        if (method_exists($resource, 'read')) {
            $content = $resource->read($params);
        } elseif (method_exists($resource, 'getContent')) {
            $content = $resource->getContent($params);
        } elseif (method_exists($resource, '__invoke')) {
            $content = $resource($params);
        } elseif (is_callable($resource)) {
            $content = call_user_func($resource, $params);
        } else {
            throw new ProtocolException('Resource is not readable', -32603);
        }

        // Ensure content is in array format
        if (! is_array($content)) {
            $content = [$this->formatResourceContent($content)];
        } else {
            // Format each content item
            $content = array_map([$this, 'formatResourceContent'], $content);
        }

        return $content;
    }

    /**
     * Format resource content for MCP response.
     *
     * @param  mixed  $content  Raw content
     * @return array Formatted content item
     */
    protected function formatResourceContent($content): array
    {
        if (is_array($content) && isset($content['type'])) {
            // Already formatted
            return $content;
        }

        if (is_string($content)) {
            return [
                'type' => 'text',
                'text' => $content,
            ];
        }

        if (is_array($content) || is_object($content)) {
            return [
                'type' => 'text',
                'text' => json_encode($content, JSON_PRETTY_PRINT),
            ];
        }

        return [
            'type' => 'text',
            'text' => (string) $content,
        ];
    }

    /**
     * Get resource URI from resource instance.
     *
     * @param  string  $name  Resource name
     * @param  mixed  $resource  Resource instance
     * @return string Resource URI
     */
    protected function getResourceUri(string $name, $resource): string
    {
        if (method_exists($resource, 'getUri')) {
            return $resource->getUri();
        }

        if (method_exists($resource, 'uri') && is_callable([$resource, 'uri'])) {
            return $resource->uri();
        }

        if (is_object($resource) && property_exists($resource, 'uri')) {
            return $resource->uri;
        }

        // Generate default URI
        return "resource://{$name}";
    }

    /**
     * Get resource description from resource instance.
     *
     * @param  mixed  $resource  Resource instance
     * @return string Resource description
     */
    protected function getResourceDescription($resource): string
    {
        if (method_exists($resource, 'getDescription')) {
            return $resource->getDescription();
        }

        if (method_exists($resource, 'description') && is_callable([$resource, 'description'])) {
            return $resource->description();
        }

        if (is_object($resource) && property_exists($resource, 'description')) {
            return $resource->description;
        }

        return 'Resource: '.(is_object($resource) ? get_class($resource) : gettype($resource));
    }

    /**
     * Get resource MIME type from resource instance.
     *
     * @param  mixed  $resource  Resource instance
     * @return string Resource MIME type
     */
    protected function getResourceMimeType($resource): string
    {
        if (method_exists($resource, 'getMimeType')) {
            return $resource->getMimeType();
        }

        if (method_exists($resource, 'mimeType') && is_callable([$resource, 'mimeType'])) {
            return $resource->mimeType();
        }

        if (is_object($resource) && property_exists($resource, 'mimeType')) {
            return $resource->mimeType;
        }

        // Default to text/plain
        return 'text/plain';
    }

    /**
     * Get additional resource metadata.
     *
     * @param  mixed  $resource  Resource instance
     * @return array Resource metadata
     */
    protected function getResourceMetadata($resource): array
    {
        $metadata = [];

        if (method_exists($resource, 'getMetadata')) {
            $metadata = $resource->getMetadata();
        } elseif (is_object($resource) && property_exists($resource, 'metadata')) {
            $metadata = $resource->metadata;
        }

        return is_array($metadata) ? $metadata : [];
    }

    /**
     * Apply cursor-based pagination to resources list.
     *
     * @param  array  $resources  Resources array
     * @param  string  $cursor  Pagination cursor
     * @return array Paginated resources array
     */
    protected function applyCursorPagination(array $resources, string $cursor): array
    {
        // Simple implementation - in production this might use more sophisticated pagination
        $decoded = base64_decode($cursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData || ! isset($cursorData['offset'])) {
            return $resources;
        }

        $offset = (int) $cursorData['offset'];
        $limit = $cursorData['limit'] ?? 50;

        return array_slice($resources, $offset, $limit);
    }

    /**
     * Check if there are more resources after current page.
     *
     * @param  string  $cursor  Current cursor
     * @param  int  $currentCount  Current page count
     * @return bool True if more resources available
     */
    protected function hasMoreResources(string $cursor, int $currentCount): bool
    {
        $decoded = base64_decode($cursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData) {
            return false;
        }

        $limit = $cursorData['limit'] ?? 50;

        return $currentCount >= $limit;
    }

    /**
     * Get next cursor for pagination.
     *
     * @param  string  $currentCursor  Current cursor
     * @param  array  $resources  Current resources array
     * @return string Next cursor
     */
    protected function getNextCursor(string $currentCursor, array $resources): string
    {
        $decoded = base64_decode($currentCursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData) {
            $cursorData = ['offset' => 0, 'limit' => 50];
        }

        $cursorData['offset'] = ($cursorData['offset'] ?? 0) + ($cursorData['limit'] ?? 50);

        return base64_encode(json_encode($cursorData));
    }
}
