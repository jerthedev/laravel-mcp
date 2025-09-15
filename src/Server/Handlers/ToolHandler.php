<?php

namespace JTD\LaravelMCP\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\ToolRegistry;

/**
 * Handler for MCP Tool operations.
 *
 * This class implements handlers for tool-related MCP operations:
 * - tools/list: List all available tools
 * - tools/call: Execute a specific tool with arguments
 *
 * Follows MCP 1.0 specification and JSON-RPC 2.0 protocol requirements.
 */
class ToolHandler extends BaseHandler
{
    /**
     * Tool registry for managing tools.
     */
    protected ToolRegistry $toolRegistry;

    /**
     * Create a new tool handler instance.
     */
    public function __construct(ToolRegistry $toolRegistry, bool $debug = false)
    {
        parent::__construct($debug);
        $this->toolRegistry = $toolRegistry;
        $this->handlerName = 'ToolHandler';
    }

    /**
     * Handle tool-related MCP requests.
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
        $this->logInfo("=== ToolHandler::handle STARTING for method: {$method} ===");
        $this->logDebug("Handling {$method}", [
            'params' => $this->sanitizeForLogging($params),
            'context' => $context,
        ]);

        try {
            $this->logInfo("About to enter match statement for method: {$method}");
            $result = match ($method) {
                'tools/list' => $this->handleToolsList($params, $context),
                'tools/call' => $this->handleToolsCall($params, $context),
                default => throw new ProtocolException("Unsupported method: {$method}", -32601),
            };
            error_log("[ToolHandler] === Match statement completed successfully for method: {$method} ===");

            // Debug the actual response being returned
            if ($method === 'tools/list') {
                // USING error_log instead of Laravel Log to avoid process termination
                error_log('[ToolHandler] FINAL RESPONSE BEING RETURNED - tools_count: ' . count($result['tools'] ?? []));
                error_log('[ToolHandler] Sample tool: ' . json_encode(isset($result['tools'][0]) ? array_keys($result['tools'][0]) : 'no tools'));
            }

            error_log('ToolHandler: CHECKPOINT - About to return result to MessageProcessor');
            return $result;
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
        return ['tools/list', 'tools/call'];
    }

    /**
     * Handle tools/list request.
     *
     * Returns a list of all available tools with their metadata according to MCP spec.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with tools list
     */
    protected function handleToolsList(array $params, array $context = []): array
    {
        error_log('=== CRITICAL: handleToolsList ENTRY - checking if this runs ===');
        error_log('handleToolsList: ENTRY POINT');
        $this->logInfo('=== ToolHandler::handleToolsList STARTING ===');
        error_log('handleToolsList: After first log');
        $this->logDebug('Processing tools/list request');
        error_log('handleToolsList: After debug log');

        // Validate cursor parameter if provided
        if (isset($params['cursor'])) {
            error_log('handleToolsList: Validating cursor');
            $this->logInfo('Validating cursor parameter');
            $this->validateRequest($params, [
                'cursor' => 'string',
            ]);
            error_log('handleToolsList: Cursor validated');
        }

        error_log('handleToolsList: About to enter try block');
        $this->logInfo('About to enter try block');
        try {
            error_log('handleToolsList: Inside try block');
            $this->logInfo('=== SKIPPING REGISTRY ACCESS IN FAST BYPASS MODE ===');
            error_log('handleToolsList: After bypass log');

            $this->logInfo('About to call getToolDefinitions');
            error_log('handleToolsList: About to call getToolDefinitions');
            try {
                $tools = $this->getToolDefinitions($params['cursor'] ?? null);
                error_log('handleToolsList: getToolDefinitions returned');
                $this->logInfo('Tool definitions retrieved successfully', [
                    'tool_count' => count($tools),
                ]);
                error_log('handleToolsList: After success log');
            } catch (\Throwable $e) {
                error_log('handleToolsList: Exception in getToolDefinitions: ' . $e->getMessage());
                $this->logError('Error in getToolDefinitions', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }

            $response = [
                'tools' => $tools,
            ];

            // Add next cursor if pagination is implemented
            if (isset($params['cursor']) && $this->hasMoreTools($params['cursor'], count($tools))) {
                $response['nextCursor'] = $this->getNextCursor($params['cursor'], $tools);
            }

            $this->logInfo('Tools list generated', [
                'tool_count' => count($tools),
                'has_cursor' => isset($params['cursor']),
            ]);

            error_log('handleToolsList: Final response tools count: ' . count($tools));
            error_log('handleToolsList: Sample tool names: ' . json_encode(array_slice(array_column($tools, 'name'), 0, 5)));

            $successResponse = $this->createSuccessResponse($response, $context);
            error_log('handleToolsList: createSuccessResponse returned: ' . json_encode(array_keys($successResponse)));

            return $successResponse;

        } catch (\Throwable $e) {
            $this->logError('Failed to list tools', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'error_class' => get_class($e),
            ]);
            // Include more detailed error in development/testing environments
            $errorMessage = 'Failed to retrieve tools list';
            if (config('app.debug', false)) {
                $errorMessage .= ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
            }
            throw new ProtocolException($errorMessage, -32603);
        }
    }

    /**
     * Handle tools/call request.
     *
     * Executes a tool with the provided arguments and returns the result.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with tool execution result
     *
     * @throws ProtocolException If tool is not found or execution fails
     */
    protected function handleToolsCall(array $params, array $context = []): array
    {
        $this->logDebug('Processing tools/call request', [
            'tool_name' => $params['name'] ?? 'unknown',
        ]);

        // Validate required parameters
        $this->validateRequest($params, [
            'name' => 'required|string',
            'arguments' => 'array',
        ]);

        $toolName = $params['name'];
        $arguments = $params['arguments'] ?? [];

        // Check if tool exists
        if (! $this->toolRegistry->has($toolName)) {
            $this->logWarning("Tool not found: {$toolName}");
            throw new ProtocolException("Tool not found: {$toolName}", -32601);
        }

        try {
            // Get the tool data from registry
            $toolData = $this->toolRegistry->get($toolName);

            // Extract the tool handler from the data array or use directly
            $tool = is_array($toolData) ? ($toolData['handler'] ?? $toolData) : $toolData;

            // Validate tool arguments if the tool supports it
            try {
                if (is_callable([$tool, 'validateArguments'])) {
                    if (! $tool->validateArguments($arguments)) {
                        $this->logError("Invalid arguments for tool: {$toolName}", [
                            'arguments' => $this->sanitizeForLogging($arguments),
                        ]);
                        throw new ProtocolException("Invalid arguments for tool: {$toolName}", -32602);
                    }
                }
            } catch (ProtocolException $e) {
                // Re-throw validation errors
                throw $e;
            } catch (\Throwable $e) {
                // Method doesn't exist or validation failed for other reasons, continue
            }

            $this->logInfo("Executing tool: {$toolName}", [
                'arguments_count' => count($arguments),
            ]);

            // Execute the tool
            $result = $this->executeTool($tool, $arguments);

            // Check if result is already properly formatted with content array
            if (is_array($result) && isset($result['content']) && is_array($result['content'])) {
                // Tool returned a properly formatted response
                $response = [
                    'content' => $result['content'],
                    'isError' => $result['isError'] ?? false,
                ];
            } else {
                // Format the response according to MCP spec
                $response = [
                    'content' => [
                        $this->formatContent($result, $this->getContentType($result)),
                    ],
                    'isError' => false,
                ];
            }

            $this->logInfo("Tool executed successfully: {$toolName}");

            return $this->createSuccessResponse($response, $context);

        } catch (ProtocolException $e) {
            // Handle specific tool execution failures as error responses
            if ($e->getCode() === -32603 && str_contains($e->getMessage(), 'Tool is not executable')) {
                $this->logError("Tool execution failed: {$toolName}", [
                    'error' => $e->getMessage(),
                    'arguments' => $this->sanitizeForLogging($arguments),
                ]);

                // Return error content for tool execution failures
                $response = [
                    'content' => [
                        $this->formatContent("Tool execution failed: {$e->getMessage()}", 'text'),
                    ],
                    'isError' => true,
                ];

                return $this->createSuccessResponse($response, $context);
            }

            // Re-throw other protocol exceptions (method not found, invalid params, etc.)
            throw $e;
        } catch (\Throwable $e) {
            $this->logError("Tool execution failed: {$toolName}", [
                'error' => $e->getMessage(),
                'arguments' => $this->sanitizeForLogging($arguments),
            ]);

            // Return error content for general tool execution failures
            $response = [
                'content' => [
                    $this->formatContent("Tool execution failed: {$e->getMessage()}", 'text'),
                ],
                'isError' => true,
            ];

            return $this->createSuccessResponse($response, $context);
        }
    }

    /**
     * Get tool definitions for MCP response.
     *
     * @param  string|null  $cursor  Pagination cursor
     * @return array Array of tool definitions
     */
    protected function getToolDefinitions(?string $cursor = null): array
    {
        $this->logDebug('Getting tool definitions');
        error_log('ToolHandler: getToolDefinitions called');

        $tools = $this->toolRegistry->all();
        error_log('ToolHandler: toolRegistry->all() returned ' . count($tools) . ' tools');
        error_log('ToolHandler: tool names: ' . json_encode(array_keys($tools)));

        $this->logDebug('Retrieved tools from registry', [
            'tool_count' => count($tools),
            'tool_names' => array_keys($tools),
        ]);
        $definitions = [];

        foreach ($tools as $name => $toolData) {
            try {
                // Extract the tool handler from the data array or use directly
                $tool = is_array($toolData) ? ($toolData['handler'] ?? $toolData) : $toolData;

                $definition = [
                    'name' => $name,
                    'description' => $this->getToolDescription($tool),
                    'inputSchema' => $this->getToolInputSchema($tool),
                ];

                // Add optional fields if available
                $title = $this->getToolTitle($tool);
                if ($title !== null) {
                    $definition['title'] = $title;
                }

                $outputSchema = $this->getToolOutputSchema($tool);
                if ($outputSchema !== null) {
                    $definition['outputSchema'] = $outputSchema;
                }

                $definitions[] = $definition;
            } catch (\Throwable $e) {
                $this->logWarning("Failed to get definition for tool: {$name}", [
                    'error' => $e->getMessage(),
                ]);
                // Skip tools that can't be properly defined
            }
        }

        if ($cursor !== null) {
            $paginated = $this->applyCursorPagination($definitions, $cursor);
            error_log('ToolHandler: Returning paginated definitions: ' . count($paginated) . ' tools');
            return $paginated;
        } else {
            // Return all definitions without artificial limits
            error_log('ToolHandler: Returning all ' . count($definitions) . ' tools');
            return $definitions;
        }
    }

    /**
     * Execute a tool with arguments.
     *
     * @param  mixed  $tool  Tool instance
     * @param  array  $arguments  Tool arguments
     * @return mixed Tool execution result
     */
    protected function executeTool($tool, array $arguments)
    {
        // Try execute method first (just try calling it, don't check if it exists)
        try {
            return $tool->execute($arguments);
        } catch (\BadMethodCallException $e) {
            // Method doesn't exist, try next approach
        } catch (\Error $e) {
            // Handle PHP 7+ errors for undefined methods
            if (str_contains($e->getMessage(), 'undefined method') || str_contains($e->getMessage(), 'Call to undefined method')) {
                // Method doesn't exist, try next approach
            } else {
                throw $e;
            }
        } catch (\Throwable $e) {
            // Method exists but execution failed, re-throw the original exception
            throw $e;
        }

        // Try __invoke method
        try {
            return $tool($arguments);
        } catch (\BadMethodCallException $e) {
            // Method doesn't exist, try next approach
        } catch (\Error $e) {
            // Handle PHP 7+ errors for non-callable objects
            if (str_contains($e->getMessage(), 'not callable') || str_contains($e->getMessage(), 'Function name must be')) {
                // Object is not callable, try next approach
            } else {
                throw $e;
            }
        } catch (\Throwable $e) {
            // Re-throw actual execution errors
            throw $e;
        }

        // Check if it's a direct callable (like closures) and try call_user_func
        if (is_callable($tool)) {
            return call_user_func($tool, $arguments);
        }

        throw new ProtocolException('Tool is not executable', -32603);
    }

    /**
     * Get tool description from tool instance.
     *
     * @param  mixed  $tool  Tool instance
     * @return string Tool description
     */
    protected function getToolDescription($tool): string
    {
        // Try getDescription method first
        try {
            if (is_callable([$tool, 'getDescription'])) {
                return $tool->getDescription();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try description method
        try {
            if (is_callable([$tool, 'description'])) {
                return $tool->description();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try description property
        if (is_object($tool) && property_exists($tool, 'description')) {
            return $tool->description;
        }

        return 'Tool: '.(is_object($tool) ? get_class($tool) : gettype($tool));
    }

    /**
     * Get tool input schema from tool instance.
     *
     * @param  mixed  $tool  Tool instance
     * @return array Tool input schema
     */
    protected function getToolInputSchema($tool): array
    {
        // Try getInputSchema method first
        try {
            if (is_callable([$tool, 'getInputSchema'])) {
                $schema = $tool->getInputSchema();
                if (is_array($schema)) {
                    return $schema;
                }
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try inputSchema method
        try {
            if (is_callable([$tool, 'inputSchema'])) {
                $schema = $tool->inputSchema();
                if (is_array($schema)) {
                    return $schema;
                }
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try inputSchema property
        if (is_object($tool) && property_exists($tool, 'inputSchema') && is_array($tool->inputSchema)) {
            return $tool->inputSchema;
        }

        // Default schema allows any parameters
        return [
            'type' => 'object',
            'properties' => [],
            'additionalProperties' => true,
        ];
    }

    /**
     * Get tool title (optional MCP field).
     *
     * @param  mixed  $tool  Tool instance
     * @return string|null Tool title or null if not available
     */
    protected function getToolTitle($tool): ?string
    {
        // Try getTitle method first
        try {
            if (is_callable([$tool, 'getTitle'])) {
                return $tool->getTitle();
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try title property
        try {
            if (property_exists($tool, 'title') && !empty($tool->title)) {
                return $tool->title;
            }
        } catch (\Throwable $e) {
            // Property doesn't exist, continue
        }

        return null;
    }

    /**
     * Get tool output schema (optional MCP field).
     *
     * @param  mixed  $tool  Tool instance
     * @return array|null Output schema or null if not available
     */
    protected function getToolOutputSchema($tool): ?array
    {
        // Try getOutputSchema method first
        try {
            if (is_callable([$tool, 'getOutputSchema'])) {
                $schema = $tool->getOutputSchema();
                return is_array($schema) ? $schema : null;
            }
        } catch (\Throwable $e) {
            // Method doesn't exist or failed, try next approach
        }

        // Try outputSchema property
        try {
            if (property_exists($tool, 'outputSchema') && is_array($tool->outputSchema)) {
                return $tool->outputSchema;
            }
        } catch (\Throwable $e) {
            // Property doesn't exist, continue
        }

        return null;
    }

    /**
     * Determine content type based on result.
     *
     * @param  mixed  $result  Tool execution result
     * @return string Content type
     */
    protected function getContentType($result): string
    {
        if (is_string($result)) {
            return 'text';
        }

        if (is_array($result) || is_object($result)) {
            return 'json';
        }

        return 'text';
    }

    /**
     * Apply cursor-based pagination to tools list.
     *
     * @param  array  $tools  Tools array
     * @param  string  $cursor  Pagination cursor
     * @return array Paginated tools array
     */
    protected function applyCursorPagination(array $tools, string $cursor): array
    {
        // Simple implementation - in production this might use more sophisticated pagination
        $decoded = base64_decode($cursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData || ! isset($cursorData['offset'])) {
            return $tools;
        }

        $offset = (int) $cursorData['offset'];
        $limit = $cursorData['limit'] ?? 50;

        return array_slice($tools, $offset, $limit);
    }

    /**
     * Check if there are more tools after current page.
     *
     * @param  string  $cursor  Current cursor
     * @param  int  $currentCount  Current page count
     * @return bool True if more tools available
     */
    protected function hasMoreTools(string $cursor, int $currentCount): bool
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
     * @param  array  $tools  Current tools array
     * @return string Next cursor
     */
    protected function getNextCursor(string $currentCursor, array $tools): string
    {
        $decoded = base64_decode($currentCursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData) {
            $cursorData = ['offset' => 0, 'limit' => 50];
        }

        $cursorData['offset'] = ($cursorData['offset'] ?? 0) + ($cursorData['limit'] ?? 50);

        return base64_encode(json_encode($cursorData));
    }

    /**
     * Ensure tools are discovered and registered.
     *
     * This method triggers component discovery if the tool registry is empty,
     * which can happen in JSON-RPC context where the service provider boot
     * process may not have run.
     */
    protected function ensureToolsDiscovered(): void
    {
        // FIXED: Re-enabled now that stdio communication issue is resolved
        $this->logInfo('ensureToolsDiscovered: Re-enabled after fixing stdio communication');

        try {
            $this->logInfo('ensureToolsDiscovered: Starting failsafe discovery');

            // Get the Laravel container to access discovery service
            $container = app();

            // Check if ComponentDiscovery is available
            if ($container->bound(\JTD\LaravelMCP\Registry\ComponentDiscovery::class)) {
                $this->logInfo('ensureToolsDiscovered: ComponentDiscovery service is bound');

                $discovery = $container->make(\JTD\LaravelMCP\Registry\ComponentDiscovery::class);

                // Get discovery paths from config
                $paths = config('laravel-mcp.discovery.paths', [
                    app_path('Mcp/Tools'),
                    app_path('Mcp/Resources'),
                    app_path('Mcp/Prompts'),
                ]);

                // Ensure paths is an array
                if (! is_array($paths)) {
                    $paths = [$paths];
                }

                // Filter to only existing directories to avoid hanging on non-existent paths
                $existingPaths = array_filter($paths, function($path) {
                    $exists = is_dir($path);
                    $this->logDebug('Path check', ['path' => $path, 'exists' => $exists]);
                    return $exists;
                });

                $this->logInfo('Triggering component discovery', [
                    'paths' => $existingPaths,
                    'discovery_class' => get_class($discovery),
                ]);

                if (empty($existingPaths)) {
                    $this->logInfo('No existing MCP component directories found, skipping discovery');
                    return;
                }

                // Discover and register components
                $this->logDebug('Calling discoverComponents...');
                $discovered = $discovery->discoverComponents($existingPaths);

                $this->logInfo('Discovery found components', [
                    'component_count' => count($discovered),
                    'components' => array_keys($discovered),
                ]);

                $this->logDebug('Calling registerDiscoveredComponents...');
                $discovery->registerDiscoveredComponents();

                $this->logInfo('Component discovery completed successfully');
            } else {
                $this->logWarning('ComponentDiscovery service not available in container');
            }
        } catch (\Throwable $e) {
            $this->logError('Failed to trigger tool discovery', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            // Don't throw - this is a failsafe mechanism
        }
    }
}
