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
        } catch (ProtocolException $e) {
            // Re-throw validation and method not found errors
            if (in_array($e->getCode(), [-32600, -32601, -32602])) {
                throw $e;
            }
            
            return $this->handleException($e, $method, $context);
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

            // Read resource content (exclude URI from params)
            $readParams = $params;
            unset($readParams['uri']);
            $content = $this->readResourceContent($resource, $readParams);

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

        // Try each method in order, catching exceptions for methods that don't exist
        try {
            if (is_callable([$resource, 'read'])) {
                $content = $resource->read($params);
            } else {
                throw new \BadMethodCallException('read method not available');
            }
        } catch (\BadMethodCallException|\Error|\Mockery\Exception\BadMethodCallException $e) {
            try {
                if (is_callable([$resource, 'getContent'])) {
                    $content = $resource->getContent($params);
                } else {
                    throw new \BadMethodCallException('getContent method not available');
                }
            } catch (\BadMethodCallException|\Error|\Mockery\Exception\BadMethodCallException $e) {
                try {
                    if (is_callable([$resource, '__invoke'])) {
                        $content = $resource($params);
                    } else {
                        throw new \BadMethodCallException('__invoke method not available');
                    }
                } catch (\BadMethodCallException|\Error|\Mockery\Exception\BadMethodCallException $e) {
                    try {
                        if (is_callable($resource)) {
                            $content = call_user_func($resource, $params);
                        } else {
                            throw new ProtocolException('Resource is not readable', -32603);
                        }
                    } catch (\BadMethodCallException|\Error|\Mockery\Exception\BadMethodCallException $e) {
                        throw new ProtocolException('Resource is not readable', -32603);
                    }
                }
            }
        }

        // Check if content is already properly formatted with 'contents' key
        if (is_array($content) && isset($content['contents']) && is_array($content['contents'])) {
            // Validate and format each content item if needed
            $formattedContents = [];
            foreach ($content['contents'] as $item) {
                if (is_array($item) && isset($item['type'])) {
                    // Already properly formatted
                    $formattedContents[] = $item;
                } else {
                    // Needs formatting
                    $formattedContents[] = $this->formatResourceContent($item);
                }
            }
            return $formattedContents;
        }

        // Ensure content is in array format
        if (! is_array($content)) {
            $content = [$this->formatResourceContent($content)];
        } else {
            // Check if this is an indexed array of content items
            $isIndexed = array_keys($content) === range(0, count($content) - 1);
            
            if ($isIndexed) {
                // This is an array of content items - check if they're formatted
                $allFormatted = true;
                foreach ($content as $item) {
                    if (!is_array($item) || !isset($item['type'])) {
                        $allFormatted = false;
                        break;
                    }
                }
                
                if ($allFormatted) {
                    return $content;
                }
                
                // Format each content item
                $content = array_map([$this, 'formatResourceContent'], $content);
            } else {
                // This is an associative array (single data object) - format as single item
                $content = [$this->formatResourceContent($content)];
            }
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

        if (is_array($content)) {
            // Check if this is a partial content item with text but no type
            if (isset($content['text']) || isset($content['uri']) || isset($content['mimeType'])) {
                // This looks like a content item that just needs the type field
                $formatted = [
                    'type' => 'text',
                ];
                
                // Preserve existing text content
                if (isset($content['text'])) {
                    $formatted['text'] = $content['text'];
                }
                
                // Preserve other properties like uri, mimeType
                foreach (['uri', 'mimeType'] as $prop) {
                    if (isset($content[$prop])) {
                        $formatted[$prop] = $content[$prop];
                    }
                }
                
                return $formatted;
            }
            
            // Otherwise serialize as JSON
            return [
                'type' => 'text',
                'text' => json_encode($content, JSON_PRETTY_PRINT),
            ];
        }

        if (is_object($content)) {
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
        // Try getUri method first
        try {
            if (is_callable([$resource, 'getUri'])) {
                return $resource->getUri();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try uri method
        try {
            if (is_callable([$resource, 'uri'])) {
                return $resource->uri();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try uri property
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
        // Try getDescription method first
        try {
            if (method_exists($resource, 'getDescription') || is_callable([$resource, 'getDescription'])) {
                return $resource->getDescription();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try description method
        try {
            if (method_exists($resource, 'description') || is_callable([$resource, 'description'])) {
                return $resource->description();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try description property
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
        // Try getMimeType method first
        try {
            if (method_exists($resource, 'getMimeType') || is_callable([$resource, 'getMimeType'])) {
                return $resource->getMimeType();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try mimeType method
        try {
            if (method_exists($resource, 'mimeType') || is_callable([$resource, 'mimeType'])) {
                return $resource->mimeType();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try mimeType property
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

        // Try getMetadata method first
        try {
            if (method_exists($resource, 'getMetadata') || is_callable([$resource, 'getMetadata'])) {
                $metadata = $resource->getMetadata();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try metadata property
        if (empty($metadata) && is_object($resource) && property_exists($resource, 'metadata')) {
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
