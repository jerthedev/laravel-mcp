<?php

namespace JTD\LaravelMCP\Server\Handlers;

use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Registry\PromptRegistry;

/**
 * Handler for MCP Prompt operations.
 *
 * This class implements handlers for prompt-related MCP operations:
 * - prompts/list: List all available prompts
 * - prompts/get: Get a specific prompt with arguments
 *
 * Follows MCP 1.0 specification and JSON-RPC 2.0 protocol requirements.
 */
class PromptHandler extends BaseHandler
{
    /**
     * Prompt registry for managing prompts.
     */
    protected PromptRegistry $promptRegistry;

    /**
     * Create a new prompt handler instance.
     */
    public function __construct(PromptRegistry $promptRegistry, bool $debug = false)
    {
        parent::__construct($debug);
        $this->promptRegistry = $promptRegistry;
        $this->handlerName = 'PromptHandler';
    }

    /**
     * Handle prompt-related MCP requests.
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
                'prompts/list' => $this->handlePromptsList($params, $context),
                'prompts/get' => $this->handlePromptsGet($params, $context),
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
        return ['prompts/list', 'prompts/get'];
    }

    /**
     * Handle prompts/list request.
     *
     * Returns a list of all available prompts with their metadata according to MCP spec.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with prompts list
     */
    protected function handlePromptsList(array $params, array $context = []): array
    {
        $this->logDebug('Processing prompts/list request');

        // Validate cursor parameter if provided
        if (isset($params['cursor'])) {
            $this->validateRequest($params, [
                'cursor' => 'string',
            ]);
        }

        try {
            $prompts = $this->getPromptDefinitions($params['cursor'] ?? null);

            $response = [
                'prompts' => $prompts,
            ];

            // Add next cursor if pagination is implemented
            if (isset($params['cursor']) && $this->hasMorePrompts($params['cursor'], count($prompts))) {
                $response['nextCursor'] = $this->getNextCursor($params['cursor'], $prompts);
            }

            $this->logInfo('Prompts list generated', [
                'prompt_count' => count($prompts),
                'has_cursor' => isset($params['cursor']),
            ]);

            return $this->createSuccessResponse($response, $context);

        } catch (\Throwable $e) {
            $this->logError('Failed to list prompts', [
                'error' => $e->getMessage(),
            ]);
            throw new ProtocolException('Failed to retrieve prompts list: '.$e->getMessage(), -32603);
        }
    }

    /**
     * Handle prompts/get request.
     *
     * Gets a prompt with the provided arguments and returns the resolved content.
     *
     * @param  array  $params  Request parameters
     * @param  array  $context  Request context
     * @return array Response with prompt content
     *
     * @throws ProtocolException If prompt is not found or processing fails
     */
    protected function handlePromptsGet(array $params, array $context = []): array
    {
        $this->logDebug('Processing prompts/get request', [
            'prompt_name' => $params['name'] ?? 'unknown',
        ]);

        // Validate required parameters
        $this->validateRequest($params, [
            'name' => 'required|string',
            'arguments' => 'array',
        ]);

        $promptName = $params['name'];
        $arguments = $params['arguments'] ?? [];

        // Check if prompt exists
        if (! $this->promptRegistry->has($promptName)) {
            $this->logWarning("Prompt not found: {$promptName}");
            throw new ProtocolException("Prompt not found: {$promptName}", -32601);
        }

        try {
            // Get the prompt instance
            $prompt = $this->promptRegistry->get($promptName);

            // Validate prompt arguments if the prompt supports it
            if (method_exists($prompt, 'validateArguments')) {
                if (! $prompt->validateArguments($arguments)) {
                    $this->logError("Invalid arguments for prompt: {$promptName}", [
                        'arguments' => $this->sanitizeForLogging($arguments),
                    ]);
                    throw new ProtocolException("Invalid arguments for prompt: {$promptName}", -32602);
                }
            }

            $this->logInfo("Processing prompt: {$promptName}", [
                'arguments_count' => count($arguments),
            ]);

            // Process the prompt
            $result = $this->processPrompt($prompt, $arguments);

            // Format the response according to MCP spec
            $response = [
                'description' => $this->getPromptDescription($prompt),
                'messages' => $this->formatPromptMessages($result),
            ];

            $this->logInfo("Prompt processed successfully: {$promptName}");

            return $this->createSuccessResponse($response, $context);

        } catch (ProtocolException $e) {
            // Re-throw protocol exceptions
            throw $e;
        } catch (\Throwable $e) {
            $this->logError("Prompt processing failed: {$promptName}", [
                'error' => $e->getMessage(),
                'arguments' => $this->sanitizeForLogging($arguments),
            ]);
            throw new ProtocolException("Failed to process prompt: {$e->getMessage()}", -32603);
        }
    }

    /**
     * Get prompt definitions for MCP response.
     *
     * @param  string|null  $cursor  Pagination cursor
     * @return array Array of prompt definitions
     */
    protected function getPromptDefinitions(?string $cursor = null): array
    {
        $prompts = $this->promptRegistry->all();
        $definitions = [];

        foreach ($prompts as $name => $prompt) {
            try {
                $definition = [
                    'name' => $name,
                    'description' => $this->getPromptDescription($prompt),
                    'arguments' => $this->getPromptArguments($prompt),
                ];

                $definitions[] = $definition;
            } catch (\Throwable $e) {
                $this->logWarning("Failed to get definition for prompt: {$name}", [
                    'error' => $e->getMessage(),
                ]);
                // Skip prompts that can't be properly defined
            }
        }

        // Apply cursor-based pagination if needed
        if ($cursor !== null) {
            return $this->applyCursorPagination($definitions, $cursor);
        }

        return $definitions;
    }

    /**
     * Process a prompt with arguments.
     *
     * @param  mixed  $prompt  Prompt instance
     * @param  array  $arguments  Prompt arguments
     * @return mixed Prompt processing result
     */
    protected function processPrompt($prompt, array $arguments)
    {
        if (method_exists($prompt, 'process')) {
            return $prompt->process($arguments);
        }

        if (method_exists($prompt, 'get')) {
            return $prompt->get($arguments);
        }

        if (method_exists($prompt, '__invoke')) {
            return $prompt($arguments);
        }

        if (is_callable($prompt)) {
            return call_user_func($prompt, $arguments);
        }

        throw new ProtocolException('Prompt is not processable', -32603);
    }

    /**
     * Get prompt description from prompt instance.
     *
     * @param  mixed  $prompt  Prompt instance
     * @return string Prompt description
     */
    protected function getPromptDescription($prompt): string
    {
        if (method_exists($prompt, 'getDescription')) {
            return $prompt->getDescription();
        }

        if (method_exists($prompt, 'description') && is_callable([$prompt, 'description'])) {
            return $prompt->description();
        }

        if (is_object($prompt) && property_exists($prompt, 'description')) {
            return $prompt->description;
        }

        return 'Prompt: '.(is_object($prompt) ? get_class($prompt) : gettype($prompt));
    }

    /**
     * Get prompt arguments schema from prompt instance.
     *
     * @param  mixed  $prompt  Prompt instance
     * @return array Prompt arguments schema
     */
    protected function getPromptArguments($prompt): array
    {
        if (method_exists($prompt, 'getArguments')) {
            return $prompt->getArguments();
        }

        if (method_exists($prompt, 'arguments') && is_callable([$prompt, 'arguments'])) {
            return $prompt->arguments();
        }

        if (is_object($prompt) && property_exists($prompt, 'arguments')) {
            return $prompt->arguments;
        }

        // Return empty arguments array if not defined
        return [];
    }

    /**
     * Format prompt result as MCP messages.
     *
     * @param  mixed  $result  Prompt processing result
     * @return array Array of formatted messages
     */
    protected function formatPromptMessages($result): array
    {
        // If result is already an array of messages, return it
        if (is_array($result) && $this->isMessageArray($result)) {
            return $result;
        }

        // If result is a single message object, wrap it
        if (is_array($result) && isset($result['role'], $result['content'])) {
            return [$result];
        }

        // Convert other types to text message
        $content = [];
        if (is_string($result)) {
            $content[] = [
                'type' => 'text',
                'text' => $result,
            ];
        } elseif (is_array($result) || is_object($result)) {
            $content[] = [
                'type' => 'text',
                'text' => json_encode($result, JSON_PRETTY_PRINT),
            ];
        } else {
            $content[] = [
                'type' => 'text',
                'text' => (string) $result,
            ];
        }

        return [
            [
                'role' => 'user',
                'content' => $content,
            ],
        ];
    }

    /**
     * Check if an array contains message objects.
     *
     * @param  array  $array  Array to check
     * @return bool True if it's a message array
     */
    protected function isMessageArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        // Check if first element looks like a message
        $first = reset($array);

        return is_array($first) &&
               (isset($first['role']) || isset($first['content']));
    }

    /**
     * Apply cursor-based pagination to prompts list.
     *
     * @param  array  $prompts  Prompts array
     * @param  string  $cursor  Pagination cursor
     * @return array Paginated prompts array
     */
    protected function applyCursorPagination(array $prompts, string $cursor): array
    {
        // Simple implementation - in production this might use more sophisticated pagination
        $decoded = base64_decode($cursor);
        $cursorData = json_decode($decoded, true);

        if (! $cursorData || ! isset($cursorData['offset'])) {
            return $prompts;
        }

        $offset = (int) $cursorData['offset'];
        $limit = $cursorData['limit'] ?? 50;

        return array_slice($prompts, $offset, $limit);
    }

    /**
     * Check if there are more prompts after current page.
     *
     * @param  string  $cursor  Current cursor
     * @param  int  $currentCount  Current page count
     * @return bool True if more prompts available
     */
    protected function hasMorePrompts(string $cursor, int $currentCount): bool
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
     * @param  array  $prompts  Current prompts array
     * @return string Next cursor
     */
    protected function getNextCursor(string $currentCursor, array $prompts): string
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
