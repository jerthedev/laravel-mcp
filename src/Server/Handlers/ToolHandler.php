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
        $this->logDebug("Handling {$method}", [
            'params' => $this->sanitizeForLogging($params),
            'context' => $context,
        ]);

        try {
            return match ($method) {
                'tools/list' => $this->handleToolsList($params, $context),
                'tools/call' => $this->handleToolsCall($params, $context),
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
        $this->logDebug('Processing tools/list request');

        // Validate cursor parameter if provided
        if (isset($params['cursor'])) {
            $this->validateRequest($params, [
                'cursor' => 'string',
            ]);
        }

        try {
            $tools = $this->getToolDefinitions($params['cursor'] ?? null);

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

            return $this->createSuccessResponse($response, $context);

        } catch (\Throwable $e) {
            $this->logError('Failed to list tools', [
                'error' => $e->getMessage(),
            ]);
            throw new ProtocolException('Failed to retrieve tools list: '.$e->getMessage(), -32603);
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
            // Get the tool instance
            $tool = $this->toolRegistry->get($toolName);

            // Validate tool arguments if the tool supports it
            if (method_exists($tool, 'validateArguments')) {
                if (! $tool->validateArguments($arguments)) {
                    $this->logError("Invalid arguments for tool: {$toolName}", [
                        'arguments' => $this->sanitizeForLogging($arguments),
                    ]);
                    throw new ProtocolException("Invalid arguments for tool: {$toolName}", -32602);
                }
            }

            $this->logInfo("Executing tool: {$toolName}", [
                'arguments_count' => count($arguments),
            ]);

            // Execute the tool
            $result = $this->executeTool($tool, $arguments);

            // Format the response according to MCP spec
            $response = [
                'content' => [
                    $this->formatContent($result, $this->getContentType($result)),
                ],
                'isError' => false,
            ];

            $this->logInfo("Tool executed successfully: {$toolName}");

            return $this->createSuccessResponse($response, $context);

        } catch (ProtocolException $e) {
            // Re-throw protocol exceptions
            throw $e;
        } catch (\Throwable $e) {
            $this->logError("Tool execution failed: {$toolName}", [
                'error' => $e->getMessage(),
                'arguments' => $this->sanitizeForLogging($arguments),
            ]);

            // Return error content instead of throwing for tool execution failures
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
        $tools = $this->toolRegistry->all();
        $definitions = [];

        foreach ($tools as $name => $tool) {
            try {
                $definition = [
                    'name' => $name,
                    'description' => $this->getToolDescription($tool),
                    'inputSchema' => $this->getToolInputSchema($tool),
                ];

                $definitions[] = $definition;
            } catch (\Throwable $e) {
                $this->logWarning("Failed to get definition for tool: {$name}", [
                    'error' => $e->getMessage(),
                ]);
                // Skip tools that can't be properly defined
            }
        }

        // Apply cursor-based pagination if needed
        if ($cursor !== null) {
            return $this->applyCursorPagination($definitions, $cursor);
        }

        return $definitions;
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
        if (method_exists($tool, 'execute')) {
            return $tool->execute($arguments);
        }

        if (method_exists($tool, '__invoke')) {
            return $tool($arguments);
        }

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
        if (method_exists($tool, 'getDescription')) {
            return $tool->getDescription();
        }

        if (method_exists($tool, 'description') && is_callable([$tool, 'description'])) {
            return $tool->description();
        }

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
        if (method_exists($tool, 'getInputSchema')) {
            return $tool->getInputSchema();
        }

        if (method_exists($tool, 'inputSchema') && is_callable([$tool, 'inputSchema'])) {
            return $tool->inputSchema();
        }

        if (is_object($tool) && property_exists($tool, 'inputSchema')) {
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
}
