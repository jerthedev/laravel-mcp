<?php

namespace JTD\LaravelMCP\Server;

use Illuminate\Support\Facades\Config;

class ServerInfo
{
    private array $serverInfo;

    private array $runtimeInfo;

    private int $startTime;

    public function __construct()
    {
        $this->startTime = time();
        $this->initializeServerInfo();
        $this->initializeRuntimeInfo();
    }

    /**
     * Initialize server information from configuration.
     */
    private function initializeServerInfo(): void
    {
        $this->serverInfo = [
            'name' => Config::get('laravel-mcp.server.name', env('MCP_SERVER_NAME', 'Laravel MCP Server')),
            'version' => Config::get('laravel-mcp.server.version', '1.0.0'),
            'description' => Config::get('laravel-mcp.server.description', env('MCP_SERVER_DESCRIPTION', 'MCP Server built with Laravel')),
            'vendor' => Config::get('laravel-mcp.server.vendor', 'JTD/LaravelMCP'),
        ];
    }

    /**
     * Initialize runtime information.
     */
    private function initializeRuntimeInfo(): void
    {
        $this->runtimeInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * Get complete server information.
     */
    public function getServerInfo(): array
    {
        return array_merge($this->serverInfo, [
            'protocolVersion' => '2024-11-05',
            'implementation' => [
                'name' => 'Laravel MCP',
                'version' => $this->serverInfo['version'],
                'repository' => 'https://github.com/jerthedev/laravel-mcp',
            ],
            'runtime' => $this->runtimeInfo,
            'startTime' => $this->startTime,
            'uptime' => $this->getUptime(),
        ]);
    }

    /**
     * Get basic server information for MCP initialize response.
     */
    public function getBasicInfo(): array
    {
        return [
            'name' => $this->serverInfo['name'],
            'version' => $this->serverInfo['version'],
        ];
    }

    /**
     * Get server name.
     */
    public function getName(): string
    {
        return $this->serverInfo['name'];
    }

    /**
     * Get server version.
     */
    public function getVersion(): string
    {
        return $this->serverInfo['version'];
    }

    /**
     * Get server description.
     */
    public function getDescription(): string
    {
        return $this->serverInfo['description'];
    }

    /**
     * Get server vendor.
     */
    public function getVendor(): string
    {
        return $this->serverInfo['vendor'];
    }

    /**
     * Get protocol version.
     */
    public function getProtocolVersion(): string
    {
        return '2024-11-05';
    }

    /**
     * Get server uptime in seconds.
     */
    public function getUptime(): int
    {
        return time() - $this->startTime;
    }

    /**
     * Get server start time.
     */
    public function getStartTime(): int
    {
        return $this->startTime;
    }

    /**
     * Get runtime information.
     */
    public function getRuntimeInfo(): array
    {
        return $this->runtimeInfo;
    }

    /**
     * Update server information.
     */
    public function updateServerInfo(array $info): void
    {
        $this->serverInfo = array_merge($this->serverInfo, $info);
    }

    /**
     * Update runtime information.
     */
    public function updateRuntimeInfo(array $info): void
    {
        $this->runtimeInfo = array_merge($this->runtimeInfo, $info);
    }

    /**
     * Set server name.
     */
    public function setName(string $name): void
    {
        $this->serverInfo['name'] = $name;
    }

    /**
     * Set server version.
     */
    public function setVersion(string $version): void
    {
        $this->serverInfo['version'] = $version;
    }

    /**
     * Set server description.
     */
    public function setDescription(string $description): void
    {
        $this->serverInfo['description'] = $description;
    }

    /**
     * Set server vendor.
     */
    public function setVendor(string $vendor): void
    {
        $this->serverInfo['vendor'] = $vendor;
    }

    /**
     * Get server status information.
     */
    public function getStatus(): array
    {
        return [
            'server' => $this->getBasicInfo(),
            'uptime' => $this->getUptime(),
            'start_time' => $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'environment' => $this->runtimeInfo['environment'],
            'timezone' => $this->runtimeInfo['timezone'],
        ];
    }

    /**
     * Get detailed server information for debugging.
     */
    public function getDetailedInfo(): array
    {
        return [
            'server' => $this->serverInfo,
            'runtime' => $this->runtimeInfo,
            'system' => [
                'os' => PHP_OS,
                'architecture' => php_uname('m'),
                'hostname' => gethostname(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'performance' => [
                'uptime' => $this->getUptime(),
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'loaded_extensions' => get_loaded_extensions(),
            ],
        ];
    }

    /**
     * Format uptime as human-readable string.
     */
    public function getUptimeFormatted(): string
    {
        $uptime = $this->getUptime();
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $minutes = floor(($uptime % 3600) / 60);
        $seconds = $uptime % 60;

        if ($days > 0) {
            return sprintf('%dd %dh %dm %ds', $days, $hours, $minutes, $seconds);
        } elseif ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }

    /**
     * Reset start time (for restarts).
     */
    public function resetStartTime(): void
    {
        $this->startTime = time();
    }

    /**
     * Get server information as JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->getServerInfo(), JSON_PRETTY_PRINT);
    }

    /**
     * Get server information as array (magic method).
     */
    public function toArray(): array
    {
        return $this->getServerInfo();
    }
}
