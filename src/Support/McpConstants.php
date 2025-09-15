<?php

namespace JTD\LaravelMCP\Support;

/**
 * MCP Protocol and Package Constants
 *
 * Defines version constants and protocol specifications for the Laravel MCP package.
 * Centralizes version management to prevent hardcoded values throughout the codebase.
 */
class McpConstants
{
    /**
     * Model Context Protocol (MCP) version.
     *
     * @see https://spec.modelcontextprotocol.io/specification/
     */
    public const MCP_PROTOCOL_VERSION = '2024-11-05';

    /**
     * JSON-RPC protocol version used for transport layer.
     */
    public const JSON_RPC_VERSION = '2.0';

    /**
     * Laravel MCP package version.
     */
    public const PACKAGE_VERSION = '1.0.0';

    /**
     * Package specification version.
     *
     * Tracks the specification version this implementation follows.
     * Format: YYYY-MM-DD to indicate spec compliance date.
     */
    public const SPECIFICATION_VERSION = '2024-12-01';

    /**
     * Default server version for MCP server implementations.
     */
    public const DEFAULT_SERVER_VERSION = '1.0.0';

    /**
     * Supported MCP protocol versions.
     *
     * Array of protocol versions this package can handle.
     */
    public const SUPPORTED_MCP_VERSIONS = ['2024-11-05', '2025-06-18'];

    /**
     * Supported JSON-RPC protocol versions.
     */
    public const SUPPORTED_JSON_RPC_VERSIONS = ['2.0'];

    /**
     * Legacy support for older validation configurations.
     *
     * @deprecated Use SUPPORTED_MCP_VERSIONS instead
     */
    public const LEGACY_SUPPORTED_VERSIONS = ['1.0'];

    // Performance Requirements Constants

    /**
     * Maximum response time for typical operations (milliseconds).
     */
    public const MAX_RESPONSE_TIME_MS = 100;

    /**
     * Maximum memory usage per request (bytes).
     */
    public const MAX_MEMORY_USAGE_BYTES = 50 * 1024 * 1024; // 50MB

    /**
     * Performance thresholds for different operation types.
     */
    public const PERFORMANCE_THRESHOLDS = [
        'small_message_serialize' => 1.0,   // < 1ms
        'medium_message_serialize' => 5.0,  // < 5ms
        'large_message_serialize' => 20.0,  // < 20ms
        'batch_10_process' => 2.0,          // < 2ms
        'batch_100_process' => 10.0,        // < 10ms
        'batch_1000_process' => 50.0,       // < 50ms
        'validation_check' => 0.1,          // < 0.1ms
        'component_discovery' => 50.0,      // < 50ms
        'registry_lookup' => 1.0,           // < 1ms
    ];

    /**
     * Memory usage limits for different scenarios.
     */
    public const MEMORY_LIMITS = [
        'component_discovery' => 10 * 1024 * 1024,  // 10MB
        'message_processing' => 20 * 1024 * 1024,   // 20MB
        'batch_processing' => 50 * 1024 * 1024,     // 50MB
        'total_application' => 128 * 1024 * 1024,   // 128MB
    ];

    /**
     * Get the current MCP protocol version.
     */
    public static function getMcpVersion(): string
    {
        return self::MCP_PROTOCOL_VERSION;
    }

    /**
     * Get the JSON-RPC protocol version.
     */
    public static function getJsonRpcVersion(): string
    {
        return self::JSON_RPC_VERSION;
    }

    /**
     * Get the package version.
     */
    public static function getPackageVersion(): string
    {
        return self::PACKAGE_VERSION;
    }

    /**
     * Check if a MCP protocol version is supported.
     */
    public static function isSupportedMcpVersion(string $version): bool
    {
        return in_array($version, self::SUPPORTED_MCP_VERSIONS);
    }

    /**
     * Check if a JSON-RPC protocol version is supported.
     */
    public static function isSupportedJsonRpcVersion(string $version): bool
    {
        return in_array($version, self::SUPPORTED_JSON_RPC_VERSIONS);
    }

    /**
     * Get all supported protocol versions for validation.
     *
     * @return array Array of supported versions
     */
    public static function getSupportedVersionsForValidation(): array
    {
        // Return legacy format for backward compatibility
        // TODO: Update validation middleware to use proper MCP versions
        return self::LEGACY_SUPPORTED_VERSIONS;
    }

    /**
     * Get specification version information.
     *
     * @return array Specification compliance information
     */
    public static function getSpecificationInfo(): array
    {
        return [
            'specification_version' => self::SPECIFICATION_VERSION,
            'package_version' => self::PACKAGE_VERSION,
            'mcp_protocol_version' => self::MCP_PROTOCOL_VERSION,
            'json_rpc_version' => self::JSON_RPC_VERSION,
            'compliance_date' => self::SPECIFICATION_VERSION,
            'supported_mcp_versions' => self::SUPPORTED_MCP_VERSIONS,
            'supported_jsonrpc_versions' => self::SUPPORTED_JSON_RPC_VERSIONS,
        ];
    }

    /**
     * Get performance requirements information.
     *
     * @return array Performance requirements and thresholds
     */
    public static function getPerformanceRequirements(): array
    {
        return [
            'max_response_time_ms' => self::MAX_RESPONSE_TIME_MS,
            'max_memory_usage_bytes' => self::MAX_MEMORY_USAGE_BYTES,
            'thresholds' => self::PERFORMANCE_THRESHOLDS,
            'memory_limits' => self::MEMORY_LIMITS,
        ];
    }

    /**
     * Check if response time meets performance requirement.
     *
     * @param  float  $responseTimeMs  Response time in milliseconds
     * @param  string|null  $operation  Operation type for specific threshold
     * @return bool True if within acceptable limits
     */
    public static function isPerformanceAcceptable(float $responseTimeMs, ?string $operation = null): bool
    {
        $threshold = $operation && isset(self::PERFORMANCE_THRESHOLDS[$operation])
            ? self::PERFORMANCE_THRESHOLDS[$operation]
            : self::MAX_RESPONSE_TIME_MS;

        return $responseTimeMs <= $threshold;
    }

    /**
     * Check if memory usage is within acceptable limits.
     *
     * @param  int  $memoryBytes  Memory usage in bytes
     * @param  string|null  $scenario  Memory scenario for specific limit
     * @return bool True if within acceptable limits
     */
    public static function isMemoryUsageAcceptable(int $memoryBytes, ?string $scenario = null): bool
    {
        $limit = $scenario && isset(self::MEMORY_LIMITS[$scenario])
            ? self::MEMORY_LIMITS[$scenario]
            : self::MAX_MEMORY_USAGE_BYTES;

        return $memoryBytes <= $limit;
    }
}
