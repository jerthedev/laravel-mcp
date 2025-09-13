<?php

namespace JTD\LaravelMCP\Commands\Traits;

use JTD\LaravelMCP\Exceptions\ConfigurationException;

/**
 * Provides configuration management functionality for MCP commands.
 *
 * This trait includes methods for detecting operating systems, resolving
 * configuration paths, and managing AI client configurations.
 */
trait HandlesConfiguration
{
    /**
     * Get client configuration file path based on OS.
     *
     * @param  string  $client  Client type (claude-desktop|claude-code|chatgpt)
     * @return string|null Configuration file path or null if not supported
     */
    protected function getClientConfigPath(string $client): ?string
    {
        if (! $this->isClientSupported($client)) {
            return null;
        }

        try {
            $os = $this->detectOS();
            $home = $this->getHomeDirectory();

            return match ([$client, $os]) {
                ['claude-desktop', 'windows'] => $home.'/AppData/Roaming/Claude/claude_desktop_config.json',
                ['claude-desktop', 'darwin'] => $home.'/Library/Application Support/Claude/claude_desktop_config.json',
                ['claude-desktop', 'linux'] => $home.'/.config/claude/claude_desktop_config.json',

                ['claude-code', 'windows'] => $home.'/AppData/Roaming/Claude Code/config.json',
                ['claude-code', 'darwin'] => $home.'/Library/Application Support/Claude Code/config.json',
                ['claude-code', 'linux'] => $home.'/.config/claude-code/claude_config.json',

                ['chatgpt', 'windows'] => $home.'/AppData/Roaming/ChatGPT/chatgpt_config.json',
                ['chatgpt', 'darwin'] => $home.'/Library/Application Support/ChatGPT/chatgpt_config.json',
                ['chatgpt', 'linux'] => $home.'/.config/chatgpt/chatgpt_config.json',

                default => null,
            };
        } catch (ConfigurationException) {
            return null;
        }
    }

    /**
     * Detect the current operating system.
     *
     * @return string OS identifier (windows|darwin|linux)
     */
    protected function detectOS(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => 'linux',
        };
    }

    /**
     * Get the user's home directory.
     *
     * @return string Home directory path
     *
     * @throws ConfigurationException If home directory cannot be determined
     */
    protected function getHomeDirectory(): string
    {
        $home = match ($this->detectOS()) {
            'windows' => $this->getWindowsHomeDirectory(),
            'darwin', 'linux' => $this->getUnixHomeDirectory(),
        };

        if (! $home || ! is_dir($home)) {
            throw new ConfigurationException('Could not determine user home directory');
        }

        return rtrim($home, DIRECTORY_SEPARATOR);
    }

    /**
     * Get Windows home directory.
     *
     * @return string|null Windows home directory or null if not found
     */
    protected function getWindowsHomeDirectory(): ?string
    {
        $userProfile = $_SERVER['USERPROFILE'] ?? null;
        if ($userProfile && is_dir($userProfile)) {
            return $userProfile;
        }

        $homeDrive = $_SERVER['HOMEDRIVE'] ?? '';
        $homePath = $_SERVER['HOMEPATH'] ?? '';
        if ($homeDrive && $homePath) {
            $home = $homeDrive.$homePath;
            if (is_dir($home)) {
                return $home;
            }
        }

        return null;
    }

    /**
     * Get Unix-style home directory.
     *
     * @return string|null Unix home directory or null if not found
     */
    protected function getUnixHomeDirectory(): ?string
    {
        $home = $_SERVER['HOME'] ?? null;
        if ($home && is_dir($home)) {
            return $home;
        }

        // Fallback to /home/username
        $user = $_SERVER['USER'] ?? $_SERVER['USERNAME'] ?? null;
        if ($user) {
            $fallback = "/home/{$user}";
            if (is_dir($fallback)) {
                return $fallback;
            }
        }

        return null;
    }

    /**
     * Check if a client is supported.
     *
     * @param  string  $client  Client identifier
     * @return bool Whether the client is supported
     */
    protected function isClientSupported(string $client): bool
    {
        return in_array($client, ['claude-desktop', 'claude-code', 'chatgpt', 'chatgpt-desktop']);
    }

    /**
     * Validate that a configuration directory exists or can be created.
     *
     * @param  string  $configPath  Configuration file path
     * @return bool Whether the directory exists or was created
     */
    protected function ensureConfigDirectoryExists(string $configPath): bool
    {
        $directory = dirname($configPath);

        if (is_dir($directory)) {
            return true;
        }

        try {
            return mkdir($directory, 0755, true);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Backup an existing configuration file.
     *
     * @param  string  $configPath  Configuration file path
     * @return string|null Backup file path or null if backup failed
     */
    protected function backupConfigFile(string $configPath): ?string
    {
        if (! file_exists($configPath)) {
            return null;
        }

        $backupPath = $configPath.'.backup.'.date('Y-m-d-H-i-s');

        try {
            if (copy($configPath, $backupPath)) {
                return $backupPath;
            }
        } catch (\Throwable) {
            // Backup failed, but not critical
        }

        return null;
    }

    /**
     * Get MCP configuration with environment variable override support.
     *
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value
     * @return mixed Configuration value
     */
    protected function getMcpConfig(string $key, $default = null)
    {
        // Check for environment variable override first
        $envKey = 'MCP_'.strtoupper(str_replace(['.', '-'], '_', $key));
        $envValue = $_SERVER[$envKey] ?? $_ENV[$envKey] ?? null;

        if ($envValue !== null) {
            // Convert string boolean values
            if (in_array(strtolower($envValue), ['true', 'false'])) {
                return strtolower($envValue) === 'true';
            }

            // Convert numeric values
            if (is_numeric($envValue)) {
                return str_contains($envValue, '.') ? (float) $envValue : (int) $envValue;
            }

            return $envValue;
        }

        return config("laravel-mcp.{$key}", $default);
    }

    /**
     * Get transport configuration with validation.
     *
     * @param  string  $transport  Transport type
     * @param  string  $key  Configuration key
     * @param  mixed  $default  Default value
     * @return mixed Configuration value
     */
    protected function getTransportConfigValue(string $transport, string $key, $default = null)
    {
        $fullKey = "mcp-transports.{$transport}.{$key}";

        return config($fullKey, $default);
    }

    /**
     * Validate configuration values against constraints.
     *
     * @param  array  $config  Configuration array
     * @param  array  $constraints  Validation constraints
     * @return array Validation errors (empty if valid)
     */
    protected function validateConfigurationValues(array $config, array $constraints): array
    {
        $errors = [];

        foreach ($constraints as $key => $constraint) {
            $value = data_get($config, $key);

            if (isset($constraint['required']) && $constraint['required'] && $value === null) {
                $errors[] = "Required configuration '{$key}' is missing";

                continue;
            }

            if ($value !== null && isset($constraint['type'])) {
                $type = gettype($value);
                if ($type !== $constraint['type']) {
                    $errors[] = "Configuration '{$key}' must be of type {$constraint['type']}, {$type} given";
                }
            }

            if ($value !== null && isset($constraint['options'])) {
                if (! in_array($value, $constraint['options'])) {
                    $options = implode(', ', $constraint['options']);
                    $errors[] = "Configuration '{$key}' must be one of: {$options}";
                }
            }
        }

        return $errors;
    }
}
