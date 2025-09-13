<?php

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\ConfigValidator;
use PHPUnit\Framework\TestCase;

class ConfigValidatorTest extends TestCase
{
    protected ConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ConfigValidator;
    }

    public function test_validates_empty_configuration()
    {
        $errors = $this->validator->validate('claude-desktop', []);

        $this->assertContains('Configuration cannot be empty', $errors);
    }

    public function test_validates_unknown_client_type()
    {
        $errors = $this->validator->validate('unknown-client', ['test' => 'data']);

        $this->assertContains('Unknown client type: unknown-client', $errors);
    }

    public function test_validates_claude_desktop_missing_mcp_servers()
    {
        $config = ['other' => 'data'];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains('Configuration must contain mcpServers object', $errors);
    }

    public function test_validates_claude_desktop_stdio_configuration()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'command' => 'php',
                    'args' => ['artisan', 'mcp:serve'],
                    'cwd' => '/path/to/project',
                    'env' => ['APP_ENV' => 'local'],
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_claude_desktop_missing_command()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'command' => '',
                    'args' => ['artisan'],
                    'cwd' => '/path',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains("Command is required for server 'test-server'", $errors);
    }

    public function test_validates_claude_desktop_missing_cwd()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'command' => 'php',
                    'args' => ['artisan'],
                    'cwd' => '',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains("Valid working directory (cwd) is required for server 'test-server'", $errors);
    }

    public function test_validates_claude_desktop_http_configuration()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'url' => 'http://localhost:8000/mcp',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_claude_desktop_invalid_url()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'url' => 'not-a-valid-url',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains("Invalid URL format for server 'test-server'", $errors);
    }

    public function test_validates_claude_desktop_missing_transport_config()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'description' => 'Some server',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains("Server 'test-server' must have either 'command' (stdio) or 'url' (http) configuration", $errors);
    }

    public function test_validates_claude_code_missing_mcp_structure()
    {
        $config = ['other' => 'data'];

        $errors = $this->validator->validate('claude-code', $config);

        $this->assertContains('Configuration must contain mcp object', $errors);
    }

    public function test_validates_claude_code_missing_servers()
    {
        $config = ['mcp' => ['other' => 'data']];

        $errors = $this->validator->validate('claude-code', $config);

        $this->assertContains('Configuration must contain mcp.servers object', $errors);
    }

    public function test_validates_claude_code_stdio_configuration()
    {
        $config = [
            'mcp' => [
                'servers' => [
                    'test-server' => [
                        'command' => ['php', 'artisan', 'mcp:serve'],
                        'cwd' => '/path/to/project',
                        'env' => ['APP_ENV' => 'local'],
                    ],
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-code', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_claude_code_command_not_array()
    {
        $config = [
            'mcp' => [
                'servers' => [
                    'test-server' => [
                        'command' => 'php',
                        'cwd' => '/path',
                    ],
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-code', $config);

        $this->assertContains("Command must be a non-empty array for server 'test-server'", $errors);
    }

    public function test_validates_claude_code_http_configuration()
    {
        $config = [
            'mcp' => [
                'servers' => [
                    'test-server' => [
                        'url' => 'http://localhost:8000/mcp',
                        'headers' => ['Content-Type' => 'application/json'],
                    ],
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-code', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_chatgpt_desktop_missing_mcp_servers()
    {
        $config = ['other' => 'data'];

        $errors = $this->validator->validate('chatgpt-desktop', $config);

        $this->assertContains('Configuration must contain mcp_servers array', $errors);
    }

    public function test_validates_chatgpt_desktop_stdio_configuration()
    {
        $config = [
            'mcp_servers' => [
                [
                    'name' => 'Laravel MCP',
                    'executable' => 'php',
                    'args' => ['artisan', 'mcp:serve'],
                    'working_directory' => '/path/to/project',
                    'environment' => ['APP_ENV' => 'local'],
                ],
            ],
        ];

        $errors = $this->validator->validate('chatgpt-desktop', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_chatgpt_desktop_missing_name()
    {
        $config = [
            'mcp_servers' => [
                [
                    'executable' => 'php',
                    'args' => ['artisan'],
                    'working_directory' => '/path',
                ],
            ],
        ];

        $errors = $this->validator->validate('chatgpt-desktop', $config);

        $this->assertContains('Server at index 0 must have a name', $errors);
    }

    public function test_validates_chatgpt_desktop_http_configuration()
    {
        $config = [
            'mcp_servers' => [
                [
                    'name' => 'Laravel MCP',
                    'endpoint' => 'http://localhost:8000/mcp',
                    'method' => 'POST',
                    'headers' => ['Content-Type' => 'application/json'],
                ],
            ],
        ];

        $errors = $this->validator->validate('chatgpt-desktop', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_chatgpt_desktop_invalid_method()
    {
        $config = [
            'mcp_servers' => [
                [
                    'name' => 'Laravel MCP',
                    'endpoint' => 'http://localhost:8000/mcp',
                    'method' => 'INVALID',
                ],
            ],
        ];

        $errors = $this->validator->validate('chatgpt-desktop', $config);

        $this->assertContains('Invalid HTTP method for server at index 0', $errors);
    }

    public function test_validate_transport_stdio()
    {
        $config = [
            'command' => 'php',
            'args' => ['artisan', 'mcp:serve'],
            'cwd' => '/path/to/project',
        ];

        $errors = $this->validator->validateTransport('stdio', $config);

        $this->assertEmpty($errors);
    }

    public function test_validate_transport_stdio_missing_command()
    {
        $config = [
            'args' => ['artisan'],
            'cwd' => '/path',
        ];

        $errors = $this->validator->validateTransport('stdio', $config);

        $this->assertContains('Command is required for stdio transport', $errors);
    }

    public function test_validate_transport_http()
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 8000,
        ];

        $errors = $this->validator->validateTransport('http', $config);

        $this->assertEmpty($errors);
    }

    public function test_validate_transport_http_invalid_port()
    {
        $config = [
            'host' => '127.0.0.1',
            'port' => 70000,
        ];

        $errors = $this->validator->validateTransport('http', $config);

        $this->assertContains('Port must be between 1 and 65535', $errors);
    }

    public function test_validate_environment_variables()
    {
        $envVars = [
            'APP_ENV' => 'local',
            'MCP_DEBUG' => 'true',
            'LOG_LEVEL' => 'debug',
        ];

        $errors = $this->validator->validateEnvironmentVariables($envVars);

        $this->assertEmpty($errors);
    }

    public function test_validate_environment_variables_invalid_name()
    {
        $envVars = [
            'app-env' => 'local',  // Invalid: contains hyphen
            '123_VAR' => 'value',   // Invalid: starts with number
        ];

        $errors = $this->validator->validateEnvironmentVariables($envVars);

        $this->assertContains("Environment variable 'app-env' has invalid name format", $errors);
        $this->assertContains("Environment variable '123_VAR' has invalid name format", $errors);
    }

    public function test_is_valid_returns_true_for_valid_config()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'command' => 'php',
                    'args' => ['artisan', 'mcp:serve'],
                    'cwd' => '/path/to/project',
                ],
            ],
        ];

        $isValid = $this->validator->isValid('claude-desktop', $config);

        $this->assertTrue($isValid);
    }

    public function test_is_valid_returns_false_for_invalid_config()
    {
        $config = [];

        $isValid = $this->validator->isValid('claude-desktop', $config);

        $this->assertFalse($isValid);
    }

    public function test_validates_multiple_servers_in_claude_desktop()
    {
        $config = [
            'mcpServers' => [
                'server1' => [
                    'command' => 'php',
                    'args' => ['artisan', 'mcp:serve'],
                    'cwd' => '/path1',
                ],
                'server2' => [
                    'url' => 'http://localhost:8000/mcp',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertEmpty($errors);
    }

    public function test_validates_invalid_environment_variables_structure()
    {
        $config = [
            'mcpServers' => [
                'test-server' => [
                    'command' => 'php',
                    'args' => ['artisan'],
                    'cwd' => '/path',
                    'env' => 'not-an-array',
                ],
            ],
        ];

        $errors = $this->validator->validate('claude-desktop', $config);

        $this->assertContains("Environment variables must be an array for server 'test-server'", $errors);
    }
}
