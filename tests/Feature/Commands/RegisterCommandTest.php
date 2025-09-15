<?php

/**
 * Test file header for traceability:
 * EPIC: N/A
 * SPEC: docs/Specs/09-ClientRegistration.md
 * SPRINT: N/A
 * TICKET: 019-ClientConfigGeneration
 */

namespace JTD\LaravelMCP\Tests\Feature\Commands;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Support\ClientDetector;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('commands')]
#[Group('register-command')]
#[Group('ticket-019')]
class RegisterCommandTest extends TestCase
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
                ->with($this->anything())  // Accept any client parameter
                ->willReturn($this->tempConfigPath.'/test_config.json');

            return $detector;
        });
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempConfigPath)) {
            array_map('unlink', glob($this->tempConfigPath.'/*'));
            rmdir($this->tempConfigPath);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_registers_claude_desktop_configuration(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
            '--description' => 'Test MCP Server',
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $this->assertFileExists($configFile);

        $config = json_decode(file_get_contents($configFile), true);
        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('test-server', $config['mcpServers']);
    }

    #[Test]
    public function it_registers_claude_code_configuration(): void
    {
        // Test the command execution in test mode
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'test-mcp',
            '--description' => 'Test MCP Server',
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->expectsOutputToContain('Claude CLI')
            ->expectsOutputToContain('test mode')
            ->assertExitCode(0);

        // For Claude Code, we use CLI registration instead of config files
        // In test environment, the command is simulated for verification
    }

    #[Test]
    public function it_shows_claude_code_dry_run_correctly(): void
    {
        // Test dry-run mode shows the command that would be executed
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'test-mcp',
            '--description' => 'Test MCP Server',
            '--dry-run' => true,
        ])
            ->expectsOutputToContain('Command that would be executed:')
            ->assertExitCode(0);

        // Note: The actual command content may vary based on environment
        // but the key is that dry-run mode works without errors
    }

    #[Test]
    public function it_registers_chatgpt_desktop_configuration(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'chatgpt-desktop',
            '--name' => 'test-chatgpt',
            '--description' => 'Test ChatGPT MCP Server',
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $this->assertFileExists($configFile);

        $config = json_decode(file_get_contents($configFile), true);
        $this->assertArrayHasKey('mcp_servers', $config);
        $this->assertCount(1, $config['mcp_servers']);
        $this->assertEquals('test-chatgpt', $config['mcp_servers'][0]['name']);
    }

    #[Test]
    public function it_validates_invalid_client_type(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'invalid-client',
        ])
            ->expectsOutput('✗ Invalid client type: invalid-client')
            ->assertExitCode(2);
    }

    #[Test]
    public function it_prompts_for_server_name_when_not_provided(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--force' => true,
        ])
            ->expectsQuestion('What should we name this MCP server?', 'interactive-server')
            ->expectsQuestion('Enter a description for this MCP server', 'Interactive Test Server')
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $config = json_decode(file_get_contents($configFile), true);
        $this->assertArrayHasKey('interactive-server', $config['mcpServers']);
    }

    #[Test]
    public function it_handles_environment_variables(): void
    {
        // Use expectsOutputToContain or doesntExpectOutput to capture errors
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'env-test',
            '--env-var' => ['APP_ENV=testing', 'MCP_DEBUG=true'],
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        // Only check the file if the command succeeded

        $configFile = $this->tempConfigPath.'/test_config.json';
        $config = json_decode(file_get_contents($configFile), true);

        $serverConfig = $config['mcpServers']['env-test'];
        $this->assertEquals('testing', $serverConfig['env']['APP_ENV']);
        $this->assertEquals('true', $serverConfig['env']['MCP_DEBUG']);
    }

    #[Test]
    public function it_handles_additional_arguments(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'args-test',
            '--args' => ['--debug', '--verbose'],
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $config = json_decode(file_get_contents($configFile), true);

        $serverConfig = $config['mcpServers']['args-test'];
        $this->assertContains('--debug', $serverConfig['args']);
        $this->assertContains('--verbose', $serverConfig['args']);
    }

    #[Test]
    public function it_prevents_overwrite_without_force_flag(): void
    {
        // Create existing config file
        $configFile = $this->tempConfigPath.'/test_config.json';
        file_put_contents($configFile, '{"existing": "config"}');

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test-server',
        ])
            ->expectsConfirmation('Configuration file already exists at '.$configFile.'. Overwrite?', 'no')
            ->expectsOutput('⚠ Configuration not saved - file exists')
            ->assertExitCode(0);

        // Verify original config is unchanged
        $config = json_decode(file_get_contents($configFile), true);
        $this->assertEquals(['existing' => 'config'], $config);
    }

    #[Test]
    public function it_merges_with_existing_configuration(): void
    {
        // Create existing config file
        $configFile = $this->tempConfigPath.'/test_config.json';
        $existingConfig = [
            'mcpServers' => [
                'existing-server' => [
                    'command' => 'node',
                    'args' => ['server.js'],
                ],
            ],
        ];
        file_put_contents($configFile, json_encode($existingConfig));

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'new-server',
        ])
            ->expectsConfirmation('Configuration file already exists at '.$configFile.'. Overwrite?', 'yes')
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $config = json_decode(file_get_contents($configFile), true);
        $this->assertArrayHasKey('existing-server', $config['mcpServers']);
        $this->assertArrayHasKey('new-server', $config['mcpServers']);
    }

    #[Test]
    public function it_uses_custom_output_path(): void
    {
        $customPath = $this->tempConfigPath.'/custom_config.json';

        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'custom-path-test',
            '--output' => $customPath,
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $this->assertFileExists($customPath);

        $config = json_decode(file_get_contents($customPath), true);
        $this->assertArrayHasKey('custom-path-test', $config['mcpServers']);
    }

    #[Test]
    public function it_shows_next_steps_for_claude_desktop(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'test',
            '--force' => true,
        ])
            ->expectsOutputToContain('Next Steps')
            ->expectsOutputToContain('Restart Claude Desktop application')
            ->expectsOutputToContain('The MCP server will be available in Claude Desktop')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_next_steps_for_claude_code(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-code',
            '--name' => 'test',
            '--force' => true,
        ])
            ->expectsOutputToContain('Next Steps')
            ->expectsOutputToContain('Restart VS Code or reload the Claude extension')
            ->expectsOutputToContain('The MCP server will be available in Claude Code')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_next_steps_for_chatgpt(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'chatgpt-desktop',
            '--name' => 'test',
            '--force' => true,
        ])
            ->expectsOutputToContain('Next Steps')
            ->expectsOutputToContain('Restart ChatGPT Desktop application')
            ->expectsOutputToContain('The MCP server will be available in ChatGPT')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_handles_custom_server_path(): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'custom-server',
            '--path' => '/custom/artisan',
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $config = json_decode(file_get_contents($configFile), true);

        $serverConfig = $config['mcpServers']['custom-server'];
        $this->assertContains('/custom/artisan', $serverConfig['args']);
    }

    #[Test]
    #[DataProvider('invalidEnvVarProvider')]
    public function it_handles_invalid_environment_variables(array $envVars): void
    {
        $this->artisan('mcp:register', [
            'client' => 'claude-desktop',
            '--name' => 'env-test',
            '--env-var' => $envVars,
            '--force' => true,
        ])
            ->expectsOutput('✓ MCP server registered successfully!')
            ->assertExitCode(0);

        $configFile = $this->tempConfigPath.'/test_config.json';
        $config = json_decode(file_get_contents($configFile), true);

        // Invalid env vars should be ignored, not cause failure
        $serverConfig = $config['mcpServers']['env-test'];
        $this->assertIsArray($serverConfig['env']);
    }

    public static function invalidEnvVarProvider(): array
    {
        return [
            'no equals sign' => [['INVALID_VAR']],
            'empty value' => [['EMPTY_VAR=']],
            'only equals' => [['=']],
        ];
    }
}
