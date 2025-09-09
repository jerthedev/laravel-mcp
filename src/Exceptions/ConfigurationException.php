<?php

namespace JTD\LaravelMCP\Exceptions;

/**
 * Exception thrown when configuration errors occur.
 *
 * This exception is thrown when there are issues with MCP server
 * configuration, client configuration, or environment setup.
 */
class ConfigurationException extends McpException
{
    /**
     * Create a configuration validation error.
     *
     * @param  array  $errors  Array of validation errors
     * @return static
     */
    public static function validationFailed(array $errors): self
    {
        return new static(
            'Configuration validation failed',
            -32602,
            ['validation_errors' => $errors]
        );
    }

    /**
     * Create an unsupported client error.
     *
     * @param  string  $client  Client identifier
     * @param  array  $supportedClients  List of supported clients
     * @return static
     */
    public static function unsupportedClient(string $client, array $supportedClients = []): self
    {
        return new static(
            "Unsupported client: {$client}",
            -32600,
            [
                'client' => $client,
                'supported_clients' => $supportedClients,
            ]
        );
    }

    /**
     * Create an unsupported operating system error.
     *
     * @param  string  $os  Operating system identifier
     * @return static
     */
    public static function unsupportedOS(string $os): self
    {
        return new static(
            "Unsupported operating system: {$os}",
            -32600,
            ['operating_system' => $os]
        );
    }

    /**
     * Create a configuration file error.
     *
     * @param  string  $path  Configuration file path
     * @param  string  $reason  Error reason
     * @return static
     */
    public static function configFileError(string $path, string $reason): self
    {
        return new static(
            "Configuration file error at {$path}: {$reason}",
            -32603,
            [
                'config_path' => $path,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Create a configuration directory error.
     *
     * @param  string  $directory  Directory path
     * @param  string  $reason  Error reason
     * @return static
     */
    public static function configDirectoryError(string $directory, string $reason): self
    {
        return new static(
            "Configuration directory error at {$directory}: {$reason}",
            -32603,
            [
                'config_directory' => $directory,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Create a generator not found error.
     *
     * @param  string  $client  Client identifier
     * @return static
     */
    public static function generatorNotFound(string $client): self
    {
        return new static(
            "Configuration generator not found for client: {$client}",
            -32601,
            ['client' => $client]
        );
    }

    /**
     * Create an invalid configuration structure error.
     *
     * @param  string  $client  Client identifier
     * @param  string  $expectedStructure  Description of expected structure
     * @return static
     */
    public static function invalidStructure(string $client, string $expectedStructure): self
    {
        return new static(
            "Invalid configuration structure for {$client}: {$expectedStructure}",
            -32602,
            [
                'client' => $client,
                'expected_structure' => $expectedStructure,
            ]
        );
    }

    /**
     * Create a configuration merge error.
     *
     * @param  string  $client  Client identifier
     * @param  string  $reason  Error reason
     * @return static
     */
    public static function mergeError(string $client, string $reason): self
    {
        return new static(
            "Configuration merge failed for {$client}: {$reason}",
            -32603,
            [
                'client' => $client,
                'reason' => $reason,
            ]
        );
    }
}
