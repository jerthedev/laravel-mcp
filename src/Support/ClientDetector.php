<?php

namespace JTD\LaravelMCP\Support;

use JTD\LaravelMCP\Exceptions\ConfigurationException;

/**
 * Client environment detection utility.
 *
 * This class provides utilities for detecting the operating system,
 * determining client configuration paths, and validating client environments.
 */
class ClientDetector
{
    /**
     * Supported operating systems.
     */
    protected const SUPPORTED_OS = ['windows', 'macos', 'linux'];

    /**
     * Supported AI clients.
     */
    protected const SUPPORTED_CLIENTS = ['claude-desktop', 'claude-code', 'chatgpt', 'chatgpt-desktop'];

    /**
     * Detect the current operating system.
     *
     * @return string Operating system identifier
     *
     * @throws ConfigurationException If OS is not supported
     */
    public function detectOperatingSystem(): string
    {
        $os = match (true) {
            stripos(PHP_OS, 'WIN') === 0 => 'windows',
            stripos(PHP_OS, 'DARWIN') === 0 => 'macos',
            default => 'linux',
        };

        if (! in_array($os, self::SUPPORTED_OS)) {
            throw new ConfigurationException("Unsupported operating system: {$os}");
        }

        return $os;
    }

    /**
     * Get the user's home directory.
     *
     * @return string Home directory path
     *
     * @throws ConfigurationException If home directory cannot be determined
     */
    public function getHomeDirectory(): string
    {
        $home = match ($this->detectOperatingSystem()) {
            'windows' => $this->getWindowsHomeDirectory(),
            'macos', 'linux' => $this->getUnixHomeDirectory(),
        };

        if (! $home || ! is_dir($home)) {
            throw new ConfigurationException('Could not determine user home directory');
        }

        return rtrim($home, DIRECTORY_SEPARATOR);
    }

    /**
     * Get default configuration path for a client.
     *
     * @param  string  $client  Client identifier
     * @return string|null Configuration file path or null if not found
     */
    public function getDefaultConfigPath(string $client): ?string
    {
        if (! $this->isClientSupported($client)) {
            return null;
        }

        try {
            $os = $this->detectOperatingSystem();
            $home = $this->getHomeDirectory();

            return match ([$client, $os]) {
                ['claude-desktop', 'windows'] => $home.'/AppData/Roaming/Claude/claude_desktop_config.json',
                ['claude-desktop', 'macos'] => $home.'/Library/Application Support/Claude/claude_desktop_config.json',
                ['claude-desktop', 'linux'] => $home.'/.config/claude/claude_desktop_config.json',

                ['claude-code', 'windows'] => $home.'/AppData/Roaming/Claude Code/config.json',
                ['claude-code', 'macos'] => $home.'/Library/Application Support/Claude Code/config.json',
                ['claude-code', 'linux'] => $home.'/.config/claude-code/claude_config.json',

                ['chatgpt', 'windows'] => $home.'/AppData/Roaming/ChatGPT/chatgpt_config.json',
                ['chatgpt', 'macos'] => $home.'/Library/Application Support/ChatGPT/chatgpt_config.json',
                ['chatgpt', 'linux'] => $home.'/.config/chatgpt/chatgpt_config.json',

                ['chatgpt-desktop', 'windows'] => $home.'/AppData/Roaming/ChatGPT/chatgpt_config.json',
                ['chatgpt-desktop', 'macos'] => $home.'/Library/Application Support/ChatGPT/chatgpt_config.json',
                ['chatgpt-desktop', 'linux'] => $home.'/.config/chatgpt/chatgpt_config.json',

                default => null,
            };
        } catch (ConfigurationException) {
            return null;
        }
    }

    /**
     * Check if a client is supported.
     *
     * @param  string  $client  Client identifier
     * @return bool True if supported, false otherwise
     */
    public function isClientSupported(string $client): bool
    {
        return in_array($client, self::SUPPORTED_CLIENTS);
    }

    /**
     * Get list of supported clients.
     *
     * @return array Array of supported client identifiers
     */
    public function getSupportedClients(): array
    {
        return self::SUPPORTED_CLIENTS;
    }

    /**
     * Get list of supported operating systems.
     *
     * @return array Array of supported OS identifiers
     */
    public function getSupportedOS(): array
    {
        return self::SUPPORTED_OS;
    }

    /**
     * Check if a configuration file exists for a client.
     *
     * @param  string  $client  Client identifier
     * @return bool True if configuration file exists
     */
    public function hasClientConfig(string $client): bool
    {
        $path = $this->getDefaultConfigPath($client);

        return $path && file_exists($path);
    }

    /**
     * Validate client environment.
     *
     * @param  string  $client  Client identifier
     * @return array Array of validation results
     */
    public function validateClientEnvironment(string $client): array
    {
        $validation = [
            'client_supported' => $this->isClientSupported($client),
            'os_supported' => true,
            'config_path_available' => false,
            'config_directory_writable' => false,
            'config_file_exists' => false,
            'config_file_readable' => false,
            'config_file_writable' => false,
        ];

        try {
            $this->detectOperatingSystem();
        } catch (ConfigurationException) {
            $validation['os_supported'] = false;

            return $validation;
        }

        $configPath = $this->getDefaultConfigPath($client);
        if ($configPath) {
            $validation['config_path_available'] = true;

            $configDir = dirname($configPath);
            $validation['config_directory_writable'] = is_dir($configDir) ? is_writable($configDir) : is_writable(dirname($configDir));

            if (file_exists($configPath)) {
                $validation['config_file_exists'] = true;
                $validation['config_file_readable'] = is_readable($configPath);
                $validation['config_file_writable'] = is_writable($configPath);
            }
        }

        return $validation;
    }

    /**
     * Detect if a client application is likely installed.
     *
     * @param  string  $client  Client identifier
     * @return bool True if client appears to be installed
     */
    public function isClientInstalled(string $client): bool
    {
        $configPath = $this->getDefaultConfigPath($client);
        if (! $configPath) {
            return false;
        }

        $appDir = dirname($configPath);

        return is_dir($appDir);
    }

    /**
     * Get Windows home directory.
     *
     * @return string|null Home directory path or null if not found
     */
    protected function getWindowsHomeDirectory(): ?string
    {
        $userProfile = getenv('USERPROFILE');
        if ($userProfile) {
            return $userProfile;
        }

        $homeDrive = getenv('HOMEDRIVE');
        $homePath = getenv('HOMEPATH');
        if ($homeDrive && $homePath) {
            return $homeDrive.$homePath;
        }

        return null;
    }

    /**
     * Get Unix-like system home directory.
     *
     * @return string|null Home directory path or null if not found
     */
    protected function getUnixHomeDirectory(): ?string
    {
        return getenv('HOME') ?: null;
    }

    /**
     * Create configuration directory if it doesn't exist.
     *
     * @param  string  $client  Client identifier
     * @return bool True if directory exists or was created successfully
     */
    public function ensureConfigDirectory(string $client): bool
    {
        $configPath = $this->getDefaultConfigPath($client);
        if (! $configPath) {
            return false;
        }

        $configDir = dirname($configPath);
        if (is_dir($configDir)) {
            return true;
        }

        return mkdir($configDir, 0755, true);
    }

    /**
     * Alias for detectOperatingSystem() for backward compatibility.
     */
    public function detectOS(): string
    {
        // Return the original PHP_OS values for backward compatibility with tests
        return match (true) {
            stripos(PHP_OS, 'WIN') === 0 => 'Windows',
            stripos(PHP_OS, 'DARWIN') === 0 => 'Darwin', 
            default => 'Linux',
        };
    }

    /**
     * Detect client environment.
     */
    public function detectClientEnvironment(): array
    {
        return [
            'os' => $this->detectOperatingSystem(),
            'home' => $this->getHomeDirectory(),
            'installed_clients' => $this->getInstalledClients(),
        ];
    }

    /**
     * Get list of installed clients.
     */
    protected function getInstalledClients(): array
    {
        $installed = [];
        foreach (self::SUPPORTED_CLIENTS as $client) {
            if ($this->isClientInstalled($client)) {
                $installed[] = $client;
            }
        }

        return $installed;
    }

    /**
     * Get config directory for a specific OS.
     */
    public function getConfigDirectory(string $os): string
    {
        $home = $this->getHomeDirectory();

        return match ($os) {
            'windows', 'Windows' => $home.'/AppData/Roaming',
            'macos', 'Darwin' => $home.'/Library/Application Support',
            'linux', 'Linux' => $home.'/.config',
            default => $home.'/.config',
        };
    }

    /**
     * Get AppData directory (Windows-specific).
     */
    public function getAppDataDirectory(): ?string
    {
        try {
            $os = $this->detectOperatingSystem();
            if ($os === 'windows') {
                return getenv('APPDATA') ?: null;
            }
        } catch (ConfigurationException) {
            // Ignore
        }

        return null;
    }

    /**
     * Get config filename for a specific client.
     */
    public function getConfigFilename(string $client): ?string
    {
        return match ($client) {
            'claude-desktop' => 'claude_desktop_config.json',
            'claude-code', 'chatgpt-desktop' => 'config.json',
            'chatgpt' => 'chatgpt_config.json',
            default => null,
        };
    }
}
