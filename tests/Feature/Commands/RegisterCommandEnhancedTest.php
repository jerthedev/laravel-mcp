<?php

namespace JTD\LaravelMCP\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\ClientDetector;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('commands')]
#[Group('register-command-enhanced')]
class RegisterCommandEnhancedTest extends TestCase
{
    private string $tempConfigPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a temporary directory for test configs
        $this->tempConfigPath = sys_get_temp_dir().'/mcp_test_'.uniqid();
        mkdir($this->tempConfigPath);

        // First, forget any existing instances
        $this->app->forgetInstance(ClientDetector::class);
        $this->app->forgetInstance(ConfigGenerator::class);

        // Create a test-specific ClientDetector that returns our temp path
        $this->app->singleton(ClientDetector::class, function () {
            $detector = $this->createPartialMock(ClientDetector::class, ['getDefaultConfigPath']);
            $detector->method('getDefaultConfigPath')
                ->with($this->anything())
                ->willReturn($this->tempConfigPath.'/test_config.json');

            return $detector;
        });
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempConfigPath)) {
            $this->recursiveDelete($this->tempConfigPath);
        }

        parent::tearDown();
    }

    private function recursiveDelete($dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (is_dir($dir.'/'.$object)) {
                        $this->recursiveDelete($dir.'/'.$object);
                    } else {
                        unlink($dir.'/'.$object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    #[Test]
    public function test_register_command_with_http_transport(): void
    {
        $outputPath = $this->tempConfigPath.'/claude_desktop_http.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'Test HTTP Server',
            '--description' => 'Test server with HTTP transport',
            '--transport' => 'http',
            '--host' => '192.168.1.100',
            '--port' => '9000',
            '--output' => $outputPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $config = json_decode(file_get_contents($outputPath), true);
        $this->assertIsArray($config);
        $this->assertArrayHasKey('mcpServers', $config);

        // Verify HTTP transport configuration - Claude Desktop uses curl for HTTP
        $serverConfig = reset($config['mcpServers']);
        // HTTP transport uses curl command
        $this->assertEquals('curl', $serverConfig['command']);
        $this->assertContains('-X', $serverConfig['args']);
        $this->assertContains('POST', $serverConfig['args']);
    }

    #[Test]
    public function test_register_command_with_custom_cwd(): void
    {
        $customCwd = '/custom/working/directory';

        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'Test CWD Server',
            '--cwd' => $customCwd,
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully registered');

        // For Claude Code, we use CLI registration instead of config files
        // The test verifies the command completes successfully with custom cwd
    }

    #[Test]
    public function test_register_command_with_custom_command(): void
    {
        $outputPath = $this->tempConfigPath.'/chatgpt_custom_command.json';

        $this->artisan('mcp:register', [
            'client' => 'chatgpt-desktop',
            '--name' => 'Test Custom Command',
            '--command' => 'php8.2',
            '--output' => $outputPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $config = json_decode(file_get_contents($outputPath), true);
        $serverConfig = $config['mcp_servers'][0];
        // ChatGPT generator stores command as array in 'command' field
        $this->assertIsArray($serverConfig['command']);
        $this->assertEquals('php8.2', $serverConfig['command'][0]);
    }

    #[Test]
    public function test_register_command_dry_run_mode(): void
    {
        $outputPath = $this->tempConfigPath.'/dry_run_test.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'Dry Run Test',
            '--output' => $outputPath,
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Client: claude-desktop')
            ->expectsOutputToContain('Output path: '.$outputPath)
            ->expectsOutputToContain('No files were modified (dry-run mode)')
            ->assertExitCode(0);

        // Verify file was NOT created in dry-run mode
        $this->assertFileDoesNotExist($outputPath);
    }

    #[Test]
    public function test_register_command_with_all_transport_options(): void
    {
        $outputPath = $this->tempConfigPath.'/full_transport_config.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'Full Transport Test',
            '--transport' => 'http',
            '--host' => 'api.example.com',
            '--port' => '3000',
            '--cwd' => '/app/project',
            '--command' => 'node',
            '--output' => $outputPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $config = json_decode(file_get_contents($outputPath), true);
        $this->assertIsArray($config);

        // Verify transport options were applied
        $serverConfig = reset($config['mcpServers']);
        // HTTP transport always uses curl, ignoring custom command option
        $this->assertEquals('curl', $serverConfig['command']);
        // HTTP config should have the correct host/port in args
        $this->assertContains('http://api.example.com:3000/mcp', $serverConfig['args']);
    }

    #[Test]
    public function test_register_command_validates_invalid_client(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'invalid-client',
        ])
            ->expectsOutputToContain('Invalid client type: invalid-client')
            ->assertExitCode(2);
    }

    #[Test]
    public function test_register_command_supports_chatgpt_desktop_alias(): void
    {
        $outputPath = $this->tempConfigPath.'/chatgpt_desktop.json';

        // Test that chatgpt-desktop is now the correct name
        $this->artisan('mcp:register', [
            'client' => 'chatgpt-desktop',
            '--name' => 'ChatGPT Desktop Test',
            '--output' => $outputPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $config = json_decode(file_get_contents($outputPath), true);
        $this->assertArrayHasKey('mcp_servers', $config);
        $this->assertEquals('ChatGPT Desktop Test', $config['mcp_servers'][0]['name']);
    }

    #[Test]
    public function test_register_command_with_stdio_transport_options(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'Stdio Transport Test',
            '--transport' => 'stdio',
            '--cwd' => '/project/path',
            '--command' => 'php',
            '--args' => ['artisan', 'mcp:custom'],
        ])
            ->assertExitCode(0)
            ->expectsOutputToContain('Successfully registered');

        // For Claude Code, we use CLI registration instead of config files
        // The test verifies the command completes successfully with stdio transport options
    }

    #[Test]
    public function test_register_command_preserves_backward_compatibility(): void
    {
        $outputPath = $this->tempConfigPath.'/backward_compat.json';

        // Test that the command still works without new options
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'Backward Compatibility Test',
            '--output' => $outputPath,
        ])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);

        $config = json_decode(file_get_contents($outputPath), true);
        $serverConfig = reset($config['mcpServers']);

        // Default transport should be stdio (indicated by presence of command field)
        $this->assertArrayHasKey('command', $serverConfig);
        // Default command should be php
        $this->assertEquals('php', $serverConfig['command']);
    }
}
