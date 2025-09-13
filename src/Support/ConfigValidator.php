<?php

namespace JTD\LaravelMCP\Support;

/**
 * Configuration validator for MCP client configurations.
 *
 * This class provides comprehensive validation for client-specific
 * MCP configurations, ensuring they meet the requirements for each
 * supported AI client (Claude Desktop, Claude Code, ChatGPT Desktop).
 */
class ConfigValidator
{
    /**
     * Validate configuration for a specific client.
     *
     * @param  string  $client  Client type (claude-desktop, claude-code, chatgpt-desktop)
     * @param  array  $config  Configuration array to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(string $client, array $config): array
    {
        $errors = [];

        // Perform common validation
        $errors = array_merge($errors, $this->validateCommon($config));

        // Perform client-specific validation
        $errors = array_merge($errors, $this->validateClient($client, $config));

        return $errors;
    }

    /**
     * Validate common configuration requirements.
     *
     * @param  array  $config  Configuration array
     * @return array Array of validation errors
     */
    private function validateCommon(array $config): array
    {
        $errors = [];

        // Check for empty configuration
        if (empty($config)) {
            $errors[] = 'Configuration cannot be empty';
        }

        // Check if configuration is a valid array structure
        if (! is_array($config)) {
            $errors[] = 'Configuration must be an array';
        }

        return $errors;
    }

    /**
     * Validate client-specific configuration.
     *
     * @param  string  $client  Client type
     * @param  array  $config  Configuration array
     * @return array Array of validation errors
     */
    private function validateClient(string $client, array $config): array
    {
        return match ($client) {
            'claude-desktop' => $this->validateClaudeDesktop($config),
            'claude-code' => $this->validateClaudeCode($config),
            'chatgpt-desktop' => $this->validateChatGpt($config),
            default => ['Unknown client type: '.$client],
        };
    }

    /**
     * Validate Claude Desktop configuration.
     *
     * @param  array  $config  Configuration array
     * @return array Array of validation errors
     */
    private function validateClaudeDesktop(array $config): array
    {
        $errors = [];

        // Check for required mcpServers structure
        if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
            $errors[] = 'Configuration must contain mcpServers object';

            return $errors; // Skip further validation if structure is missing
        }

        // Validate each server configuration
        foreach ($config['mcpServers'] as $serverName => $serverConfig) {
            if (! is_array($serverConfig)) {
                $errors[] = "Server configuration for '$serverName' must be an array";

                continue;
            }

            // Check for required fields based on transport type
            if (isset($serverConfig['command'])) {
                // Stdio transport validation
                if (empty($serverConfig['command'])) {
                    $errors[] = "Command is required for server '$serverName'";
                }

                if (! isset($serverConfig['args']) || ! is_array($serverConfig['args'])) {
                    $errors[] = "Args must be an array for server '$serverName'";
                }

                if (empty($serverConfig['cwd']) || ! is_string($serverConfig['cwd'])) {
                    $errors[] = "Valid working directory (cwd) is required for server '$serverName'";
                }
            } elseif (isset($serverConfig['url'])) {
                // HTTP transport validation
                if (empty($serverConfig['url'])) {
                    $errors[] = "URL is required for HTTP transport in server '$serverName'";
                }

                if (! filter_var($serverConfig['url'], FILTER_VALIDATE_URL)) {
                    $errors[] = "Invalid URL format for server '$serverName'";
                }
            } else {
                $errors[] = "Server '$serverName' must have either 'command' (stdio) or 'url' (http) configuration";
            }

            // Validate environment variables if present
            if (isset($serverConfig['env']) && ! is_array($serverConfig['env'])) {
                $errors[] = "Environment variables must be an array for server '$serverName'";
            }
        }

        return $errors;
    }

    /**
     * Validate Claude Code configuration.
     *
     * @param  array  $config  Configuration array
     * @return array Array of validation errors
     */
    private function validateClaudeCode(array $config): array
    {
        $errors = [];

        // Check for required mcp.servers structure
        if (! isset($config['mcp']) || ! is_array($config['mcp'])) {
            $errors[] = 'Configuration must contain mcp object';

            return $errors;
        }

        if (! isset($config['mcp']['servers']) || ! is_array($config['mcp']['servers'])) {
            $errors[] = 'Configuration must contain mcp.servers object';

            return $errors;
        }

        // Validate each server configuration
        foreach ($config['mcp']['servers'] as $serverName => $serverConfig) {
            if (! is_array($serverConfig)) {
                $errors[] = "Server configuration for '$serverName' must be an array";

                continue;
            }

            // Check for required fields based on transport type
            if (isset($serverConfig['command'])) {
                // Stdio transport validation
                if (! is_array($serverConfig['command']) || empty($serverConfig['command'])) {
                    $errors[] = "Command must be a non-empty array for server '$serverName'";
                }

                if (empty($serverConfig['cwd']) || ! is_string($serverConfig['cwd'])) {
                    $errors[] = "Valid working directory (cwd) is required for server '$serverName'";
                }
            } elseif (isset($serverConfig['url'])) {
                // HTTP transport validation
                if (empty($serverConfig['url'])) {
                    $errors[] = "URL is required for HTTP transport in server '$serverName'";
                }

                if (! filter_var($serverConfig['url'], FILTER_VALIDATE_URL)) {
                    $errors[] = "Invalid URL format for server '$serverName'";
                }

                // Validate headers if present
                if (isset($serverConfig['headers']) && ! is_array($serverConfig['headers'])) {
                    $errors[] = "Headers must be an array for server '$serverName'";
                }
            } else {
                $errors[] = "Server '$serverName' must have either 'command' (stdio) or 'url' (http) configuration";
            }

            // Validate environment variables if present
            if (isset($serverConfig['env']) && ! is_array($serverConfig['env'])) {
                $errors[] = "Environment variables must be an array for server '$serverName'";
            }
        }

        return $errors;
    }

    /**
     * Validate ChatGPT Desktop configuration.
     *
     * @param  array  $config  Configuration array
     * @return array Array of validation errors
     */
    private function validateChatGpt(array $config): array
    {
        $errors = [];

        // Check for required mcp_servers structure
        if (! isset($config['mcp_servers']) || ! is_array($config['mcp_servers'])) {
            $errors[] = 'Configuration must contain mcp_servers array';

            return $errors;
        }

        // Validate each server configuration
        foreach ($config['mcp_servers'] as $index => $serverConfig) {
            if (! is_array($serverConfig)) {
                $errors[] = "Server configuration at index $index must be an array";

                continue;
            }

            // Check for required name field
            if (empty($serverConfig['name'])) {
                $errors[] = "Server at index $index must have a name";
            }

            // Check for required fields based on transport type
            if (isset($serverConfig['executable'])) {
                // Stdio transport validation
                if (empty($serverConfig['executable'])) {
                    $errors[] = "Executable is required for server at index $index";
                }

                if (! isset($serverConfig['args']) || ! is_array($serverConfig['args'])) {
                    $errors[] = "Args must be an array for server at index $index";
                }

                if (empty($serverConfig['working_directory']) || ! is_string($serverConfig['working_directory'])) {
                    $errors[] = "Valid working_directory is required for server at index $index";
                }
            } elseif (isset($serverConfig['endpoint'])) {
                // HTTP transport validation
                if (empty($serverConfig['endpoint'])) {
                    $errors[] = "Endpoint is required for HTTP transport in server at index $index";
                }

                if (! filter_var($serverConfig['endpoint'], FILTER_VALIDATE_URL)) {
                    $errors[] = "Invalid endpoint URL format for server at index $index";
                }

                // Validate method if present
                if (isset($serverConfig['method']) && ! in_array($serverConfig['method'], ['GET', 'POST', 'PUT', 'DELETE'])) {
                    $errors[] = "Invalid HTTP method for server at index $index";
                }

                // Validate headers if present
                if (isset($serverConfig['headers']) && ! is_array($serverConfig['headers'])) {
                    $errors[] = "Headers must be an array for server at index $index";
                }
            } else {
                $errors[] = "Server at index $index must have either 'executable' (stdio) or 'endpoint' (http) configuration";
            }

            // Validate environment variables if present
            if (isset($serverConfig['environment']) && ! is_array($serverConfig['environment'])) {
                $errors[] = "Environment variables must be an array for server at index $index";
            }
        }

        return $errors;
    }

    /**
     * Validate transport-specific configuration.
     *
     * @param  string  $transport  Transport type (stdio or http)
     * @param  array  $config  Configuration for the transport
     * @return array Array of validation errors
     */
    public function validateTransport(string $transport, array $config): array
    {
        $errors = [];

        switch ($transport) {
            case 'stdio':
                if (empty($config['command'])) {
                    $errors[] = 'Command is required for stdio transport';
                }
                if (! isset($config['args']) || ! is_array($config['args'])) {
                    $errors[] = 'Args must be an array for stdio transport';
                }
                if (empty($config['cwd'])) {
                    $errors[] = 'Working directory (cwd) is required for stdio transport';
                }
                break;

            case 'http':
                if (empty($config['host'])) {
                    $errors[] = 'Host is required for HTTP transport';
                }
                if (! isset($config['port']) || ! is_numeric($config['port'])) {
                    $errors[] = 'Valid port number is required for HTTP transport';
                }
                if ($config['port'] < 1 || $config['port'] > 65535) {
                    $errors[] = 'Port must be between 1 and 65535';
                }
                break;

            default:
                $errors[] = "Unknown transport type: $transport";
        }

        return $errors;
    }

    /**
     * Validate environment variables format.
     *
     * @param  array  $envVars  Environment variables array
     * @return array Array of validation errors
     */
    public function validateEnvironmentVariables(array $envVars): array
    {
        $errors = [];

        foreach ($envVars as $key => $value) {
            if (! is_string($key) || empty($key)) {
                $errors[] = 'Environment variable keys must be non-empty strings';
            }

            if (! is_string($value) && ! is_numeric($value) && ! is_bool($value)) {
                $errors[] = "Environment variable '$key' has invalid value type";
            }

            // Check for valid environment variable name format
            if (! preg_match('/^[A-Z_][A-Z0-9_]*$/i', $key)) {
                $errors[] = "Environment variable '$key' has invalid name format";
            }
        }

        return $errors;
    }

    /**
     * Check if a configuration is valid for a specific client.
     *
     * @param  string  $client  Client type
     * @param  array  $config  Configuration array
     * @return bool True if configuration is valid
     */
    public function isValid(string $client, array $config): bool
    {
        return empty($this->validate($client, $config));
    }
}
