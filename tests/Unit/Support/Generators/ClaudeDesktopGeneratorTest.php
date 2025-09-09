<?php

/**
 * Test file header for traceability:
 * EPIC: N/A
 * SPEC: docs/Specs/09-ClientRegistration.md
 * SPRINT: N/A
 * TICKET: 019-ClientConfigGeneration
 */

namespace Tests\Unit\Support\Generators;

use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Support\Generators\ClaudeDesktopGenerator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit')]
#[Group('support')]
#[Group('generators')]
#[Group('ticket-019')]
class ClaudeDesktopGeneratorTest extends TestCase
{
    private ClaudeDesktopGenerator $generator;

    private McpRegistry $mockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = $this->createMock(McpRegistry::class);
        $this->generator = new ClaudeDesktopGenerator($this->mockRegistry);
    }

    #[Test]
    public function it_generates_stdio_configuration(): void
    {
        $this->mockRegistry->method('getTools')->willReturn([]);
        $this->mockRegistry->method('getResources')->willReturn([]);
        $this->mockRegistry->method('getPrompts')->willReturn([]);

        $options = [
            'name' => 'test-server',
            'description' => 'Test MCP Server',
            'transport' => 'stdio',
            'cwd' => '/test/path',
            'command' => 'php',
            'args' => ['artisan', 'mcp:serve'],
            'env' => ['APP_ENV' => 'testing'],
        ];

        $config = $this->generator->generate($options);

        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('test-server', $config['mcpServers']);

        $serverConfig = $config['mcpServers']['test-server'];
        $this->assertEquals('Test MCP Server', $serverConfig['description']);
        $this->assertEquals('php', $serverConfig['command']);
        $this->assertEquals(['artisan', 'mcp:serve'], $serverConfig['args']);
        $this->assertEquals('/test/path', $serverConfig['cwd']);
        $this->assertArrayHasKey('APP_ENV', $serverConfig['env']);
        $this->assertEquals('testing', $serverConfig['env']['APP_ENV']);
    }

    #[Test]
    public function it_generates_http_configuration(): void
    {
        $this->mockRegistry->method('getTools')->willReturn([]);
        $this->mockRegistry->method('getResources')->willReturn([]);
        $this->mockRegistry->method('getPrompts')->willReturn([]);

        $options = [
            'name' => 'test-server',
            'description' => 'Test MCP Server',
            'transport' => 'http',
            'host' => 'localhost',
            'port' => 8080,
        ];

        $config = $this->generator->generate($options);

        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('test-server', $config['mcpServers']);

        $serverConfig = $config['mcpServers']['test-server'];
        $this->assertEquals('Test MCP Server', $serverConfig['description']);
        $this->assertEquals('curl', $serverConfig['command']);
        $this->assertContains('-X', $serverConfig['args']);
        $this->assertContains('POST', $serverConfig['args']);
        $this->assertContains('http://localhost:8080/mcp', $serverConfig['args']);
    }

    #[Test]
    public function it_uses_default_values_when_not_provided(): void
    {
        $this->mockRegistry->method('getTools')->willReturn(['tool1', 'tool2']);
        $this->mockRegistry->method('getResources')->willReturn(['resource1']);
        $this->mockRegistry->method('getPrompts')->willReturn(['prompt1', 'prompt2', 'prompt3']);

        $config = $this->generator->generate();

        $this->assertArrayHasKey('mcpServers', $config);
        $serverName = array_key_first($config['mcpServers']);
        $this->assertNotNull($serverName);

        $serverConfig = $config['mcpServers'][$serverName];
        $this->assertStringContainsString('2 tools', $serverConfig['description']);
        $this->assertStringContainsString('1 resource', $serverConfig['description']);
        $this->assertStringContainsString('3 prompts', $serverConfig['description']);
    }

    #[Test]
    public function it_validates_configuration(): void
    {
        $validConfig = [
            'mcpServers' => [
                'test-server' => [
                    'command' => 'php',
                    'args' => ['artisan', 'mcp:serve'],
                    'description' => 'Test Server',
                ],
            ],
        ];

        $errors = $this->generator->validateConfig($validConfig);
        $this->assertEmpty($errors);

        $invalidConfig = [
            'mcpServers' => [
                'test-server' => [
                    // Missing required command field
                    'args' => ['artisan', 'mcp:serve'],
                ],
            ],
        ];

        $errors = $this->generator->validateConfig($invalidConfig);
        $this->assertNotEmpty($errors);
        $this->assertContains('Command is required for server test-server', $errors);
    }

    #[Test]
    public function it_merges_configurations_properly(): void
    {
        $newConfig = [
            'mcpServers' => [
                'new-server' => [
                    'command' => 'php',
                    'args' => ['new.php'],
                ],
            ],
        ];

        $existingConfig = [
            'mcpServers' => [
                'existing-server' => [
                    'command' => 'node',
                    'args' => ['existing.js'],
                ],
            ],
            'otherSettings' => ['key' => 'value'],
        ];

        $merged = $this->generator->mergeConfig($newConfig, $existingConfig);

        $this->assertArrayHasKey('mcpServers', $merged);
        $this->assertArrayHasKey('existing-server', $merged['mcpServers']);
        $this->assertArrayHasKey('new-server', $merged['mcpServers']);
        $this->assertArrayHasKey('otherSettings', $merged);
        $this->assertEquals('value', $merged['otherSettings']['key']);
    }

    #[Test]
    public function it_loads_existing_configuration(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'claude_config');
        $existingConfig = [
            'mcpServers' => [
                'existing' => ['command' => 'test'],
            ],
        ];
        file_put_contents($tempFile, json_encode($existingConfig));

        $options = [
            'name' => 'new-server',
            'config_path' => $tempFile,
        ];

        $config = $this->generator->generate($options);

        $this->assertArrayHasKey('mcpServers', $config);
        $this->assertArrayHasKey('existing', $config['mcpServers']);
        $this->assertArrayHasKey('new-server', $config['mcpServers']);

        unlink($tempFile);
    }

    #[Test]
    public function it_handles_invalid_transport_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported transport: invalid');

        $options = [
            'name' => 'test-server',
            'transport' => 'invalid',
        ];

        $this->generator->generate($options);
    }

    #[Test]
    public function it_gets_default_server_name(): void
    {
        config(['app.name' => 'TestApp']);

        $name = $this->generator->getDefaultServerName();

        $this->assertEquals('TestApp MCP Server', $name);
    }

    #[Test]
    public function it_gets_default_description_with_component_counts(): void
    {
        $this->mockRegistry->method('getTools')->willReturn(['tool1', 'tool2']);
        $this->mockRegistry->method('getResources')->willReturn(['resource1']);
        $this->mockRegistry->method('getPrompts')->willReturn([]);

        $description = $this->generator->getDefaultDescription();

        $this->assertStringContainsString('2 tools', $description);
        $this->assertStringContainsString('1 resource', $description);
        $this->assertStringContainsString('0 prompts', $description);
    }
}
