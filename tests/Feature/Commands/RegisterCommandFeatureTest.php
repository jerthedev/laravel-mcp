<?php

/**
 * Laravel MCP Package - RegisterCommand Feature Tests
 *
 * This file contains feature tests for the RegisterCommand functionality,
 * testing client registration workflows and configuration generation.
 *
 * @category    Commands
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
 * - Command signature and options validation
 * - Client-specific configuration generation
 * - Interactive prompts and user input handling
 * - File creation and overwrite scenarios
 * - Configuration validation and error handling
 * - Integration with ConfigGenerator
 *
 * Dependencies:
 * - ConfigGenerator class for configuration generation
 * - File system operations for config file handling
 * - Interactive console features
 */

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('commands')]
#[Group('register-command')]
#[Group('ticket-ARTISANCOMMANDS-007')]
class RegisterCommandFeatureTest extends TestCase
{
    protected ConfigGenerator $configGenerator;

    protected string $tempConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configGenerator = app(ConfigGenerator::class);
        $this->tempConfigPath = sys_get_temp_dir().'/mcp_test_config_'.uniqid().'.json';
    }

    protected function tearDown(): void
    {
        // Cleanup test files
        if (File::exists($this->tempConfigPath)) {
            File::delete($this->tempConfigPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_can_register_claude_desktop_configuration(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($this->tempConfigPath);

        $config = json_decode(File::get($this->tempConfigPath), true);
        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('test-server', $config['mcpServers']);
        $this->assertEquals('php', $config['mcpServers']['test-server']['command']);
        $this->assertContains('artisan', $config['mcpServers']['test-server']['args']);
        $this->assertContains('mcp:serve', $config['mcpServers']['test-server']['args']);
    }

    #[Test]
    public function it_can_register_claude_code_configuration(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('✓ MCP server registered successfully!');

        $this->assertFileExists($this->tempConfigPath);

        $config = json_decode(File::get($this->tempConfigPath), true);
        $this->assertArrayHasKey('mcp', $config);
        $this->assertArrayHasKey('servers', $config['mcp']);
        $this->assertArrayHasKey('test-server', $config['mcp']['servers']);
        $this->assertEquals('Test MCP Server', $config['mcp']['servers']['test-server']['description']);
    }

    #[Test]
    public function it_can_register_chatgpt_configuration(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'chatgpt',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('✓ MCP server registered successfully!');

        $this->assertFileExists($this->tempConfigPath);

        $config = json_decode(File::get($this->tempConfigPath), true);
        $this->assertArrayHasKey('mcp_servers', $config);
        $this->assertIsArray($config['mcp_servers']);
        $this->assertCount(1, $config['mcp_servers']);
        $this->assertEquals('test-server', $config['mcp_servers'][0]['name']);
        $this->assertEquals('Test MCP Server', $config['mcp_servers'][0]['description']);
    }

    #[Test]
    public function it_validates_client_type(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'invalid-client',
        ])
            ->assertFailed()
            ->expectsOutput('✗ Invalid client type: invalid-client');
    }

    #[Test]
    public function it_accepts_additional_arguments(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--args' => ['--debug', '--verbose'],
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful();

        $config = json_decode(File::get($this->tempConfigPath), true);
        $args = $config['mcpServers']['test-server']['args'];
        $this->assertContains('--debug', $args);
        $this->assertContains('--verbose', $args);
    }

    #[Test]
    public function it_accepts_environment_variables(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--env-var' => ['APP_ENV=testing', 'MCP_DEBUG=true'],
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful();

        $config = json_decode(File::get($this->tempConfigPath), true);
        $env = $config['mcpServers']['test-server']['env'];
        $this->assertEquals('testing', $env['APP_ENV']);
        $this->assertEquals('true', $env['MCP_DEBUG']);
    }

    #[Test]
    public function it_uses_custom_server_path(): void
    {
        $customPath = '/custom/path/to/artisan';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--path' => $customPath,
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful();

        $config = json_decode(File::get($this->tempConfigPath), true);
        $args = $config['mcpServers']['test-server']['args'];
        $this->assertContains($customPath, $args);
    }

    #[Test]
    public function it_prevents_overwrite_without_force_flag(): void
    {
        // Create existing file
        File::put($this->tempConfigPath, '{"existing": "config"}');

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--output' => $this->tempConfigPath,
        ])
            ->assertFailed()
            ->expectsOutput('⚠ Configuration not saved - file exists');

        // File should remain unchanged
        $content = File::get($this->tempConfigPath);
        $this->assertStringContainsString('existing', $content);
    }

    #[Test]
    public function it_overwrites_with_force_flag(): void
    {
        // Create existing file
        File::put($this->tempConfigPath, '{"existing": "config"}');

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful();

        // File should be overwritten
        $config = json_decode(File::get($this->tempConfigPath), true);
        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayNotHasKey('existing', $config);
    }

    #[Test]
    public function it_merges_with_existing_configuration(): void
    {
        // Create existing Claude Desktop config
        $existingConfig = [
            'mcpServers' => [
                'existing-server' => [
                    'command' => 'node',
                    'args' => ['server.js'],
                    'env' => [],
                ],
            ],
        ];
        File::put($this->tempConfigPath, json_encode($existingConfig));

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'new-server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('Merged with existing configuration');

        $config = json_decode(File::get($this->tempConfigPath), true);
        $this->assertArrayHasKey('existing-server', $config['mcpServers']);
        $this->assertArrayHasKey('new-server', $config['mcpServers']);
    }

    #[Test]
    public function it_creates_directory_if_not_exists(): void
    {
        $nestedPath = sys_get_temp_dir().'/mcp_test_'.uniqid().'/nested/config.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $nestedPath,
            '--force' => true,
        ])
            ->assertSuccessful();

        $this->assertFileExists($nestedPath);

        // Cleanup
        File::deleteDirectory(dirname(dirname($nestedPath)));
    }

    #[Test]
    public function it_provides_helpful_next_steps(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('Next Steps')
            ->expectsOutput('Restart Claude Desktop application')
            ->expectsOutput('Run "php artisan mcp:serve" to start the MCP server');
    }

    #[Test]
    public function it_shows_different_next_steps_for_different_clients(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'test-server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
        ])
            ->expectsOutput('Restart VS Code or reload the Claude extension');

        $this->artisan('mcp:register', [
            'client' => 'chatgpt',
            '--name' => 'test-server',
            '--output' => sys_get_temp_dir().'/chatgpt_test_'.uniqid().'.json',
            '--force' => true,
        ])
            ->expectsOutput('Restart ChatGPT Desktop application');
    }

    #[Test]
    public function it_handles_configuration_generation_errors(): void
    {
        // Mock ConfigGenerator to throw exception
        $mockGenerator = $this->createMock(ConfigGenerator::class);
        $mockGenerator->method('generateClaudeDesktopConfig')
            ->willThrowException(new \Exception('Configuration generation failed'));

        $this->app->instance(ConfigGenerator::class, $mockGenerator);

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $this->tempConfigPath,
        ])
            ->assertFailed();
    }

    #[Test]
    public function it_validates_configuration_before_saving(): void
    {
        // Mock ConfigGenerator to return invalid config
        $mockGenerator = $this->createMock(ConfigGenerator::class);
        $mockGenerator->method('generateClaudeDesktopConfig')
            ->willReturn(['invalid' => 'config']);
        $mockGenerator->method('validateClientConfig')
            ->willReturn(['Configuration is invalid']);

        $this->app->instance(ConfigGenerator::class, $mockGenerator);

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $this->tempConfigPath,
        ])
            ->assertFailed()
            ->expectsOutput('✗ Configuration validation failed:');
    }

    #[Test]
    public function it_handles_file_save_errors(): void
    {
        // Use an invalid path to trigger save error
        $invalidPath = '/root/invalid/path/config.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $invalidPath,
        ])
            ->assertFailed()
            ->expectsOutput('✗ Failed to save configuration');
    }

    #[Test]
    public function it_falls_back_to_current_directory_when_default_path_unavailable(): void
    {
        // Mock ConfigGenerator to return null for default path
        $mockGenerator = $this->createPartialMock(ConfigGenerator::class, ['getClientConfigPath']);
        $mockGenerator->method('getClientConfigPath')
            ->willReturn(null);

        $this->app->instance(ConfigGenerator::class, $mockGenerator);

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
        ])
            ->expectsOutput('Could not determine default config path');
    }

    #[Test]
    public function it_shows_debug_information_in_verbose_mode(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--output' => $this->tempConfigPath,
            '--force' => true,
            '-v' => true,
        ])
            ->assertSuccessful()
            ->expectsOutput('[DEBUG]');
    }
}
