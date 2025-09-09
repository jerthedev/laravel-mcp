<?php

namespace JTD\LaravelMCP\Protocol\Contracts;

/**
 * Interface for MCP protocol handling.
 *
 * This interface defines the contract for handling Model Context Protocol
 * messages and operations. It manages MCP-specific functionality like
 * capability negotiation, tool/resource/prompt operations, and lifecycle events.
 */
interface ProtocolHandlerInterface
{
    /**
     * Initialize the protocol handler with server capabilities.
     *
     * @param  array  $capabilities  Server capabilities configuration
     */
    public function initialize(array $capabilities = []): void;

    /**
     * Handle MCP initialization request.
     *
     * @param  array  $params  Initialization parameters from client
     * @return array Server capabilities and information
     */
    public function handleInitialize(array $params): array;

    /**
     * Handle MCP initialized notification.
     */
    public function handleInitialized(): void;

    /**
     * Handle ping request.
     *
     * @return array Pong response
     */
    public function handlePing(): array;

    /**
     * Handle tools/list request.
     *
     * @param  array  $params  List parameters (filters, pagination)
     * @return array Available tools list
     */
    public function handleToolsList(array $params = []): array;

    /**
     * Handle tools/call request.
     *
     * @param  array  $params  Tool call parameters (name, arguments)
     * @return array Tool execution result
     */
    public function handleToolsCall(array $params): array;

    /**
     * Handle resources/list request.
     *
     * @param  array  $params  List parameters (filters, pagination)
     * @return array Available resources list
     */
    public function handleResourcesList(array $params = []): array;

    /**
     * Handle resources/read request.
     *
     * @param  array  $params  Resource read parameters (uri, options)
     * @return array Resource content
     */
    public function handleResourcesRead(array $params): array;

    /**
     * Handle resources/subscribe request.
     *
     * @param  array  $params  Subscription parameters (uri, options)
     * @return array Subscription confirmation
     */
    public function handleResourcesSubscribe(array $params): array;

    /**
     * Handle resources/unsubscribe request.
     *
     * @param  array  $params  Unsubscribe parameters (uri)
     * @return array Unsubscribe confirmation
     */
    public function handleResourcesUnsubscribe(array $params): array;

    /**
     * Handle prompts/list request.
     *
     * @param  array  $params  List parameters (filters, pagination)
     * @return array Available prompts list
     */
    public function handlePromptsList(array $params = []): array;

    /**
     * Handle prompts/get request.
     *
     * @param  array  $params  Prompt parameters (name, arguments)
     * @return array Prompt messages
     */
    public function handlePromptsGet(array $params): array;

    /**
     * Handle logging/setLevel request.
     *
     * @param  array  $params  Log level parameters
     * @return array Set level confirmation
     */
    public function handleLoggingSetLevel(array $params): array;

    /**
     * Get server capabilities.
     *
     * @return array Current server capabilities
     */
    public function getCapabilities(): array;

    /**
     * Get server information.
     *
     * @return array Server name, version, and other info
     */
    public function getServerInfo(): array;

    /**
     * Check if the protocol handler can handle a specific method.
     *
     * @param  string  $method  The MCP method name
     */
    public function canHandleMethod(string $method): bool;

    /**
     * Get all supported MCP methods.
     *
     * @return array Array of supported method names
     */
    public function getSupportedMethods(): array;
}
