<?php

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Exceptions\ConfigurationException;
use JTD\LaravelMCP\Support\ConfigGenerator;
use JTD\LaravelMCP\Tests\TestCase;

class ConfigGeneratorWrapperTest extends TestCase
{
    protected ConfigGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the ConfigGenerator instance from the container
        $this->generator = app(ConfigGenerator::class);
    }

    public function test_generate_config_wrapper_method_works_for_claude_desktop()
    {
        $options = [
            'name' => 'Test Server',
            'transport' => 'stdio',
        ];

        $config = $this->generator->generateConfig('claude-desktop', $options);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('mcpServers', $config);
    }

    public function test_generate_config_wrapper_method_works_for_claude_code()
    {
        $options = [
            'name' => 'Test Server',
            'transport' => 'stdio',
        ];

        $config = $this->generator->generateConfig('claude-code', $options);

        $this->assertIsArray($config);
        $this->assertArrayHasKey('mcp', $config);
        $this->assertArrayHasKey('servers', $config['mcp']);
    }

    public function test_generate_config_wrapper_method_works_for_chatgpt_desktop()
    {
        $options = [
            'name' => 'Test Server',
            'transport' => 'stdio',
        ];

        // Test both 'chatgpt' and 'chatgpt-desktop' work
        $config1 = $this->generator->generateConfig('chatgpt', $options);
        $config2 = $this->generator->generateConfig('chatgpt-desktop', $options);

        $this->assertIsArray($config1);
        $this->assertIsArray($config2);
        $this->assertArrayHasKey('mcp_servers', $config1);
        $this->assertArrayHasKey('mcp_servers', $config2);
    }

    public function test_generate_config_throws_exception_for_unsupported_client()
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Unsupported client: invalid-client');

        $this->generator->generateConfig('invalid-client', []);
    }

    public function test_write_config_wrapper_method()
    {
        $tempPath = sys_get_temp_dir().'/test_config_'.uniqid().'.json';

        try {
            $result = $this->generator->writeConfig('claude-desktop', $tempPath, [
                'name' => 'Test Server',
                'force' => true,
            ]);

            $this->assertTrue($result);
            $this->assertFileExists($tempPath);

            $content = json_decode(file_get_contents($tempPath), true);
            $this->assertIsArray($content);
            $this->assertArrayHasKey('mcpServers', $content);
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }

    public function test_get_default_config_path_wrapper_method()
    {
        // Test for claude-desktop
        $path = $this->generator->getDefaultConfigPath('claude-desktop');
        $this->assertIsString($path);
        $this->assertStringContainsString('claude', strtolower($path));

        // Test for chatgpt-desktop (should map to chatgpt internally)
        $path = $this->generator->getDefaultConfigPath('chatgpt-desktop');
        $this->assertIsString($path);
        $this->assertStringContainsString('chatgpt', strtolower($path));
    }
}
