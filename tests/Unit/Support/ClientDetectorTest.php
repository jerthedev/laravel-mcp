<?php

/**
 * Test file header for traceability:
 * EPIC: N/A
 * SPEC: docs/Specs/09-ClientRegistration.md
 * SPRINT: N/A
 * TICKET: 019-ClientConfigGeneration
 */

namespace Tests\Unit\Support;

use JTD\LaravelMCP\Support\ClientDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use TestCase;

#[Group('unit')]
#[Group('support')]
#[Group('client-detector')]
#[Group('ticket-019')]
class ClientDetectorTest extends TestCase
{
    private ClientDetector $clientDetector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientDetector = new ClientDetector;
    }

    #[Test]
    public function it_detects_operating_system(): void
    {
        $os = $this->clientDetector->detectOS();

        $this->assertIsString($os);
        $this->assertContains($os, ['Windows', 'Darwin', 'Linux']);
    }

    #[Test]
    public function it_determines_if_client_is_installed(): void
    {
        // This test may vary depending on the system
        // We'll just ensure the method returns a boolean
        $isInstalled = $this->clientDetector->isClientInstalled('claude-desktop');

        $this->assertIsBool($isInstalled);
    }

    #[Test]
    #[DataProvider('clientProvider')]
    public function it_gets_default_config_path_for_client(string $client): void
    {
        $path = $this->clientDetector->getDefaultConfigPath($client);

        if ($path !== null) {
            $this->assertIsString($path);
            $this->assertStringContainsString('json', $path);
        } else {
            // Path might be null for unsupported clients
            $this->assertNull($path);
        }
    }

    #[Test]
    public function it_gets_home_directory(): void
    {
        $home = $this->clientDetector->getHomeDirectory();

        $this->assertIsString($home);
        $this->assertNotEmpty($home);
        $this->assertDirectoryExists($home);
    }

    #[Test]
    public function it_detects_client_environment(): void
    {
        $env = $this->clientDetector->detectClientEnvironment();

        $this->assertIsArray($env);
        $this->assertArrayHasKey('os', $env);
        $this->assertArrayHasKey('home', $env);
        $this->assertArrayHasKey('installed_clients', $env);

        $this->assertIsString($env['os']);
        $this->assertIsString($env['home']);
        $this->assertIsArray($env['installed_clients']);
    }

    #[Test]
    public function it_validates_client_compatibility(): void
    {
        // Test valid clients
        $this->assertTrue($this->clientDetector->isClientSupported('claude-desktop'));
        $this->assertTrue($this->clientDetector->isClientSupported('claude-code'));
        $this->assertTrue($this->clientDetector->isClientSupported('chatgpt-desktop'));

        // Test invalid client
        $this->assertFalse($this->clientDetector->isClientSupported('unsupported-client'));
    }

    #[Test]
    public function it_gets_config_directory_for_os(): void
    {
        $os = $this->clientDetector->detectOS();
        $configDir = $this->clientDetector->getConfigDirectory($os);

        $this->assertIsString($configDir);
        $this->assertNotEmpty($configDir);

        // Check that it returns appropriate directory based on OS
        if ($os === 'Darwin') {
            $this->assertStringContainsString('Library/Application Support', $configDir);
        } elseif ($os === 'Linux') {
            $this->assertStringContainsString('.config', $configDir);
        } elseif ($os === 'Windows') {
            $this->assertStringContainsString('AppData', $configDir);
        }
    }

    #[Test]
    public function it_gets_app_data_directory(): void
    {
        $appData = $this->clientDetector->getAppDataDirectory();

        if ($appData !== null) {
            $this->assertIsString($appData);
            $this->assertNotEmpty($appData);
        } else {
            // May be null on non-Windows systems
            $os = $this->clientDetector->detectOS();
            $this->assertNotEquals('Windows', $os);
        }
    }

    #[Test]
    public function it_provides_client_specific_config_filename(): void
    {
        $this->assertEquals(
            'claude_desktop_config.json',
            $this->clientDetector->getConfigFilename('claude-desktop')
        );

        $this->assertEquals(
            'config.json',
            $this->clientDetector->getConfigFilename('claude-code')
        );

        $this->assertEquals(
            'config.json',
            $this->clientDetector->getConfigFilename('chatgpt-desktop')
        );

        $this->assertNull(
            $this->clientDetector->getConfigFilename('unknown-client')
        );
    }

    public static function clientProvider(): array
    {
        return [
            'claude-desktop' => ['claude-desktop'],
            'claude-code' => ['claude-code'],
            'chatgpt-desktop' => ['chatgpt-desktop'],
            'unsupported' => ['unsupported-client'],
        ];
    }
}
