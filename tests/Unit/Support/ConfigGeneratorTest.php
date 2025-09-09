<?php

/**
 * Laravel MCP Package - ConfigGenerator Unit Tests
 *
 * This file contains unit tests for the ConfigGenerator class,
 * testing configuration generation methods for various AI clients.
 *
 * @category    Support
 *
 * @version     1.0.0
 *
 * @since       2024-01-15
 *
 * @author      JTD Development Team
 * @copyright   2024 JTD Development
 * @license     MIT
 *
 * Test Organization:
 * - Epic: Laravel MCP Package Development
 * - Sprint: Command Implementation (Sprint 3)
 * - Ticket: ARTISANCOMMANDS-007 - RegisterCommand Implementation
 *
 * Coverage Areas:
 * - Client-specific configuration generation methods
 * - Configuration validation functionality
 * - File path resolution and OS detection
 * - Configuration merging capabilities
 * - Error handling and edge cases
 *
 * Dependencies:
 * - ConfigGenerator class
 * - McpRegistry for component information
 * - File system operations
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('support')]
#[Group('config-generator')]
#[Group('ticket-ARTISANCOMMANDS-007')]
class ConfigGeneratorTest extends TestCase
{
    protected ConfigGenerator $configGenerator;

    protected McpRegistry $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = $this->createMock(McpRegistry::class);
        $this->configGenerator = new ConfigGenerator($this->mockRegistry);
    }

    #[Test]
    public function it_generates_claude_desktop_configuration(): void
    {
        $options = [
            'server_name' => 'test-server',
            'command' => ['php', 'artisan', 'mcp:serve'],
            'args' => ['--debug'],
            'env' => ['APP_ENV' => 'testing'],
        ];

        $config = $this->configGenerator->generateClaudeDesktopConfig($options);

        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('test-server', $config['mcpServers']);

        $server = $config['mcpServers']['test-server'];
        $this->assertEquals('php', $server['command']);
        $this->assertContains('artisan', $server['args']);
        $this->assertContains('mcp:serve', $server['args']);
        $this->assertContains('--debug', $server['args']);
        $this->assertEquals(['APP_ENV' => 'testing'], $server['env']);
    }

    #[Test]
    public function it_generates_claude_desktop_configuration_with_defaults(): void
    {
        $config = $this->configGenerator->generateClaudeDesktopConfig();

        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('laravel-mcp', $config['mcpServers']);

        $server = $config['mcpServers']['laravel-mcp'];
        $this->assertEquals('php', $server['command']);
        $this->assertEquals(['artisan', 'mcp:serve'], $server['args']);
        $this->assertEquals([], $server['env']);
    }

    #[Test]
    public function it_generates_claude_code_configuration(): void
    {
        $options = [
            'server_name' => 'test-server',
            'command' => ['php', 'artisan', 'mcp:serve'],
            'args' => ['--debug'],
            'env' => ['APP_ENV' => 'testing'],
            'description' => 'Test MCP Server',
        ];

        $config = $this->configGenerator->generateClaudeCodeConfig($options);

        $this->assertArrayHasKey('mcp', $config);
        $this->assertArrayHasKey('servers', $config['mcp']);
        $this->assertArrayHasKey('test-server', $config['mcp']['servers']);

        $server = $config['mcp']['servers']['test-server'];
        $this->assertEquals('php', $server['command']);
        $this->assertContains('artisan', $server['args']);
        $this->assertContains('mcp:serve', $server['args']);
        $this->assertContains('--debug', $server['args']);
        $this->assertEquals(['APP_ENV' => 'testing'], $server['env']);
        $this->assertEquals('Test MCP Server', $server['description']);
    }

    #[Test]
    public function it_generates_claude_code_configuration_with_defaults(): void
    {
        $config = $this->configGenerator->generateClaudeCodeConfig();

        $this->assertArrayHasKey('mcp', $config);
        $this->assertArrayHasKey('servers', $config['mcp']);
        $this->assertArrayHasKey('laravel-mcp', $config['mcp']['servers']);

        $server = $config['mcp']['servers']['laravel-mcp'];
        $this->assertEquals('php', $server['command']);
        $this->assertEquals(['artisan', 'mcp:serve'], $server['args']);
        $this->assertEquals([], $server['env']);
        $this->assertEquals('Laravel MCP Server', $server['description']);
    }

    #[Test]
    public function it_generates_chatgpt_desktop_configuration(): void
    {
        $options = [
            'server_name' => 'test-server',
            'command' => ['php', 'artisan', 'mcp:serve'],
            'args' => ['--debug'],
            'env' => ['APP_ENV' => 'testing'],
            'description' => 'Test MCP Server',
        ];

        $config = $this->configGenerator->generateChatGptDesktopConfig($options);

        $this->assertArrayHasKey('mcp_servers', $config);
        $this->assertIsArray($config['mcp_servers']);
        $this->assertCount(1, $config['mcp_servers']);

        $server = $config['mcp_servers'][0];
        $this->assertEquals('test-server', $server['name']);
        $this->assertEquals(['php', 'artisan', 'mcp:serve', '--debug'], $server['command']);
        $this->assertEquals(['APP_ENV' => 'testing'], $server['env']);
        $this->assertEquals('Test MCP Server', $server['description']);
    }

    #[Test]
    public function it_generates_chatgpt_desktop_configuration_with_defaults(): void
    {
        $config = $this->configGenerator->generateChatGptDesktopConfig();

        $this->assertArrayHasKey('mcp_servers', $config);
        $this->assertIsArray($config['mcp_servers']);
        $this->assertCount(1, $config['mcp_servers']);

        $server = $config['mcp_servers'][0];
        $this->assertEquals('laravel-mcp', $server['name']);
        $this->assertEquals(['php', 'artisan', 'mcp:serve'], $server['command']);
        $this->assertEquals([], $server['env']);
        $this->assertEquals('Laravel MCP Server', $server['description']);
    }

    #[Test]
    public function it_validates_claude_desktop_configuration(): void
    {
        $validConfig = ['mcpServers' => ['test' => []]];
        $errors = $this->configGenerator->validateClientConfig('claude-desktop', $validConfig);
        $this->assertEmpty($errors);

        $invalidConfig = ['invalid' => 'config'];
        $errors = $this->configGenerator->validateClientConfig('claude-desktop', $invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration must contain mcpServers object', $errors);
    }

    #[Test]
    public function it_validates_claude_code_configuration(): void
    {
        $validConfig = ['mcp' => ['servers' => ['test' => []]]];
        $errors = $this->configGenerator->validateClientConfig('claude-code', $validConfig);
        $this->assertEmpty($errors);

        $invalidConfig = ['invalid' => 'config'];
        $errors = $this->configGenerator->validateClientConfig('claude-code', $invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration must contain mcp.servers object', $errors);
    }

    #[Test]
    public function it_validates_chatgpt_configuration(): void
    {
        $validConfig = ['mcp_servers' => []];
        $errors = $this->configGenerator->validateClientConfig('chatgpt', $validConfig);
        $this->assertEmpty($errors);

        $invalidConfig = ['invalid' => 'config'];
        $errors = $this->configGenerator->validateClientConfig('chatgpt', $invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('Configuration must contain mcp_servers array', $errors);
    }

    #[Test]
    public function it_validates_unknown_client_type(): void
    {
        $config = ['test' => 'config'];
        $errors = $this->configGenerator->validateClientConfig('unknown-client', $config);

        $this->assertNotEmpty($errors);
        $this->assertContains('Unknown client type: unknown-client', $errors);
    }

    #[Test]
    public function it_merges_claude_desktop_configurations(): void
    {
        $existing = [
            'mcpServers' => [
                'server1' => ['command' => 'node', 'args' => ['server1.js']],
            ],
        ];

        $new = [
            'mcpServers' => [
                'server2' => ['command' => 'php', 'args' => ['artisan', 'mcp:serve']],
            ],
        ];

        $merged = $this->configGenerator->mergeClientConfig('claude-desktop', $new, $existing);

        $this->assertArrayHasKey('mcpServers', $merged);
        $this->assertArrayHasKey('server1', $merged['mcpServers']);
        $this->assertArrayHasKey('server2', $merged['mcpServers']);
    }

    #[Test]
    public function it_merges_claude_code_configurations(): void
    {
        $existing = [
            'mcp' => [
                'servers' => [
                    'server1' => ['command' => 'node', 'args' => ['server1.js']],
                ],
            ],
        ];

        $new = [
            'mcp' => [
                'servers' => [
                    'server2' => ['command' => 'php', 'args' => ['artisan', 'mcp:serve']],
                ],
            ],
        ];

        $merged = $this->configGenerator->mergeClientConfig('claude-code', $new, $existing);

        $this->assertArrayHasKey('mcp', $merged);
        $this->assertArrayHasKey('servers', $merged['mcp']);
        $this->assertArrayHasKey('server1', $merged['mcp']['servers']);
        $this->assertArrayHasKey('server2', $merged['mcp']['servers']);
    }

    #[Test]
    public function it_merges_chatgpt_configurations(): void
    {
        $existing = [
            'mcp_servers' => [
                ['name' => 'server1', 'command' => ['node', 'server1.js']],
            ],
        ];

        $new = [
            'mcp_servers' => [
                ['name' => 'server2', 'command' => ['php', 'artisan', 'mcp:serve']],
            ],
        ];

        $merged = $this->configGenerator->mergeClientConfig('chatgpt', $new, $existing);

        $this->assertArrayHasKey('mcp_servers', $merged);
        $this->assertCount(2, $merged['mcp_servers']);
        $this->assertEquals('server1', $merged['mcp_servers'][0]['name']);
        $this->assertEquals('server2', $merged['mcp_servers'][1]['name']);
    }

    #[Test]
    public function it_returns_new_config_when_existing_is_empty(): void
    {
        $new = ['mcpServers' => ['test' => []]];
        $merged = $this->configGenerator->mergeClientConfig('claude-desktop', $new, []);

        $this->assertEquals($new, $merged);
    }

    #[Test]
    public function it_detects_operating_system(): void
    {
        // Since we can't easily mock PHP_OS, we'll just test that the method returns a valid OS
        $reflection = new \ReflectionClass($this->configGenerator);
        $method = $reflection->getMethod('detectOperatingSystem');
        $method->setAccessible(true);

        $os = $method->invoke($this->configGenerator);
        $this->assertContains($os, ['windows', 'macos', 'linux']);
    }

    #[Test]
    public function it_gets_home_directory(): void
    {
        $reflection = new \ReflectionClass($this->configGenerator);
        $method = $reflection->getMethod('getHomeDirectory');
        $method->setAccessible(true);

        $home = $method->invoke($this->configGenerator);
        $this->assertIsString($home);
        $this->assertNotEmpty($home);
    }

    #[Test]
    public function it_gets_client_config_path(): void
    {
        $path = $this->configGenerator->getClientConfigPath('claude-desktop');
        $this->assertIsString($path);
        $this->assertStringContainsString('claude_desktop_config.json', $path);

        $path = $this->configGenerator->getClientConfigPath('claude-code');
        $this->assertIsString($path);
        $this->assertStringContainsString('claude_config.json', $path);

        $path = $this->configGenerator->getClientConfigPath('chatgpt');
        $this->assertIsString($path);
        $this->assertStringContainsString('chatgpt_config.json', $path);

        $path = $this->configGenerator->getClientConfigPath('unknown');
        $this->assertNull($path);
    }

    #[Test]
    public function it_saves_client_configuration(): void
    {
        $config = ['test' => 'config'];
        $path = sys_get_temp_dir().'/test_config_'.uniqid().'.json';

        $result = $this->configGenerator->saveClientConfig($config, $path, true);
        $this->assertTrue($result);

        $this->assertFileExists($path);

        $savedConfig = json_decode(file_get_contents($path), true);
        $this->assertEquals($config, $savedConfig);

        // Cleanup
        unlink($path);
    }

    #[Test]
    public function it_prevents_overwrite_without_force(): void
    {
        $config = ['test' => 'config'];
        $path = sys_get_temp_dir().'/test_config_'.uniqid().'.json';

        // Create existing file
        file_put_contents($path, '{"existing": "config"}');

        $result = $this->configGenerator->saveClientConfig($config, $path, false);
        $this->assertFalse($result);

        // File should remain unchanged
        $existingConfig = json_decode(file_get_contents($path), true);
        $this->assertEquals(['existing' => 'config'], $existingConfig);

        // Cleanup
        unlink($path);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        $config = ['test' => 'config'];
        $dir = sys_get_temp_dir().'/test_dir_'.uniqid();
        $path = $dir.'/config.json';

        $result = $this->configGenerator->saveClientConfig($config, $path, true);
        $this->assertTrue($result);

        $this->assertFileExists($path);

        // Cleanup
        unlink($path);
        rmdir($dir);
    }

    #[Test]
    public function it_handles_save_errors_gracefully(): void
    {
        $config = ['test' => 'config'];
        $path = '/root/invalid/path/config.json'; // Should fail on most systems

        $result = $this->configGenerator->saveClientConfig($config, $path, true);
        $this->assertFalse($result);
    }
}
