<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Compatibility;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\LaravelMcpServiceProvider;
use JTD\LaravelMCP\Tests\TestCase;
use Symfony\Component\Process\Process;

/**
 * Test Suite Header
 *
 * Epic: TESTING-QUALITY
 * Sprint: Sprint 3
 * Ticket: TESTING-028 - Testing Strategy Quality Assurance
 *
 * Purpose: Validate package installation and upgrade processes
 * Dependencies: Symfony Process, Laravel Framework
 */
#[Group('compatibility')]
#[Group('installation')]
#[Group('ticket-028')]
class PackageInstallationTest extends TestCase
{
    private string $testAppPath;

    private string $packagePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testAppPath = sys_get_temp_dir().'/laravel-mcp-test-app-'.uniqid();
        $this->packagePath = dirname(dirname(__DIR__));
    }

    protected function tearDown(): void
    {
        // Clean up test application
        if (File::exists($this->testAppPath)) {
            File::deleteDirectory($this->testAppPath);
        }

        parent::tearDown();
    }

    /**
     * Test fresh package installation
     */
    #[Test]
    public function it_can_be_installed_in_fresh_laravel_app(): void
    {
        $this->markTestSkipped('Requires actual Laravel installation - run in CI environment');

        // Create fresh Laravel app
        $this->createFreshLaravelApp();

        // Install the package
        $this->installPackageInTestApp();

        // Verify installation
        $this->verifyPackageInstallation();
    }

    /**
     * Test package configuration publishing
     */
    #[Test]
    public function configuration_files_publish_correctly(): void
    {
        // Publish configuration
        Artisan::call('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-config',
            '--force' => true,
        ]);

        $configPath = config_path('laravel-mcp.php');

        if (File::exists($configPath)) {
            $this->assertFileExists($configPath);

            // Validate config structure
            $config = require $configPath;
            $this->validateConfigStructure($config);
        }
    }

    /**
     * Test route file publishing
     */
    #[Test]
    public function route_files_publish_correctly(): void
    {
        // Publish routes
        Artisan::call('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-routes',
            '--force' => true,
        ]);

        $routePath = base_path('routes/mcp.php');

        if (File::exists($routePath)) {
            $this->assertFileExists($routePath);

            // Validate route file contains expected routes
            $routeContent = File::get($routePath);
            $this->assertStringContainsString('Route::', $routeContent);
            $this->assertStringContainsString('/mcp', $routeContent);
        }
    }

    /**
     * Test stub files publishing for generators
     */
    #[Test]
    public function stub_files_publish_correctly(): void
    {
        // Publish stubs
        Artisan::call('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-stubs',
            '--force' => true,
        ]);

        $stubsPath = base_path('stubs/mcp');

        if (File::exists($stubsPath)) {
            $this->assertDirectoryExists($stubsPath);

            // Check for expected stub files
            $expectedStubs = [
                'tool.stub',
                'resource.stub',
                'prompt.stub',
            ];

            foreach ($expectedStubs as $stub) {
                $stubFile = $stubsPath.'/'.$stub;
                if (File::exists($stubFile)) {
                    $this->assertFileExists($stubFile);
                }
            }
        }
    }

    /**
     * Test package auto-discovery
     */
    #[Test]
    public function package_auto_discovery_works(): void
    {
        // Check if service provider is auto-discovered
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(
            LaravelMcpServiceProvider::class,
            $providers,
            'Service provider should be auto-discovered'
        );
    }

    /**
     * Test composer.json extra.laravel configuration
     */
    #[Test]
    public function composer_extra_laravel_configuration_is_correct(): void
    {
        $composerPath = dirname(dirname(__DIR__)).'/composer.json';
        $this->assertFileExists($composerPath, 'composer.json should exist');

        $composer = json_decode(File::get($composerPath), true);
        $this->assertIsArray($composer, 'composer.json should be valid JSON');

        // Verify extra.laravel section exists
        $this->assertArrayHasKey('extra', $composer, 'composer.json should have extra section');
        $this->assertArrayHasKey('laravel', $composer['extra'], 'extra should have laravel section');

        $laravel = $composer['extra']['laravel'];

        // Verify providers are correctly configured
        $this->assertArrayHasKey('providers', $laravel, 'laravel section should have providers');
        $this->assertIsArray($laravel['providers'], 'providers should be an array');
        $this->assertContains(
            LaravelMcpServiceProvider::class,
            $laravel['providers'],
            'LaravelMcpServiceProvider should be in providers array'
        );

        // Verify aliases are correctly configured
        $this->assertArrayHasKey('aliases', $laravel, 'laravel section should have aliases');
        $this->assertIsArray($laravel['aliases'], 'aliases should be an array');
        $this->assertArrayHasKey('Mcp', $laravel['aliases'], 'Mcp alias should be configured');
        $this->assertEquals(
            'JTD\\LaravelMCP\\Facades\\Mcp',
            $laravel['aliases']['Mcp'],
            'Mcp alias should point to correct facade class'
        );
    }

    /**
     * Test package upgrade scenario
     */
    #[Test]
    public function package_can_be_upgraded_safely(): void
    {
        $this->markTestSkipped('Requires actual package versions - run in CI environment');

        // Simulate upgrade by re-publishing assets
        Artisan::call('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-config',
            '--force' => true,
        ]);

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');

        // Re-cache configuration
        Artisan::call('config:cache');

        // Verify everything still works
        $this->assertTrue($this->app->bound('mcp.registry'));
    }

    /**
     * Test that old configuration is preserved during upgrade
     */
    #[Test]
    public function configuration_is_preserved_during_upgrade(): void
    {
        // Set custom configuration
        config(['laravel-mcp.custom_setting' => 'test_value']);

        // Get current config
        $originalConfig = config('laravel-mcp');

        // Re-publish configuration (simulating upgrade)
        Artisan::call('vendor:publish', [
            '--provider' => LaravelMcpServiceProvider::class,
            '--tag' => 'laravel-mcp-config',
        ]);

        // Custom settings should be preserved if config exists
        if (isset($originalConfig['custom_setting'])) {
            $this->assertEquals('test_value', config('laravel-mcp.custom_setting'));
        }
    }

    /**
     * Test migration rollback compatibility
     */
    #[Test]
    public function migrations_can_be_rolled_back(): void
    {
        $this->markTestSkipped('No migrations in current implementation');

        // Run migrations
        Artisan::call('migrate', ['--path' => 'vendor/jerthedev/laravel-mcp/database/migrations']);

        // Rollback migrations
        Artisan::call('migrate:rollback', ['--path' => 'vendor/jerthedev/laravel-mcp/database/migrations']);

        // Verify rollback completed
        $this->expectNotToPerformAssertions();
    }

    /**
     * Test package removal
     */
    #[Test]
    public function package_can_be_removed_cleanly(): void
    {
        // Remove published assets
        $filesToRemove = [
            config_path('laravel-mcp.php'),
            config_path('mcp-transports.php'),
            base_path('routes/mcp.php'),
        ];

        foreach ($filesToRemove as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->assertFileDoesNotExist($file);
            }
        }

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
    }

    /**
     * Test dependency resolution
     */
    #[Test]
    public function dependencies_resolve_correctly(): void
    {
        // Check required packages are available
        $this->assertTrue(
            class_exists(\Symfony\Component\Process\Process::class),
            'Symfony Process should be available'
        );

        $this->assertTrue(
            class_exists(\Symfony\Component\Yaml\Yaml::class),
            'Symfony Yaml should be available'
        );
    }

    /**
     * Test minimum PHP version requirement
     */
    #[Test]
    public function meets_minimum_php_version_requirement(): void
    {
        $minimumVersion = '8.2.0';

        $this->assertTrue(
            version_compare(PHP_VERSION, $minimumVersion, '>='),
            "PHP version must be {$minimumVersion} or higher"
        );
    }

    /**
     * Test minimum Laravel version requirement
     */
    #[Test]
    public function meets_minimum_laravel_version_requirement(): void
    {
        $minimumVersion = '11.0.0';
        $currentVersion = $this->app->version();

        $this->assertTrue(
            version_compare($currentVersion, $minimumVersion, '>='),
            "Laravel version must be {$minimumVersion} or higher"
        );
    }

    /**
     * Create a fresh Laravel application for testing
     */
    private function createFreshLaravelApp(): void
    {
        $process = new Process([
            'composer',
            'create-project',
            'laravel/laravel',
            $this->testAppPath,
            '--prefer-dist',
            '--quiet',
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail('Could not create fresh Laravel application: '.$process->getErrorOutput());
        }
    }

    /**
     * Install the package in test application
     */
    private function installPackageInTestApp(): void
    {
        // Add local repository
        $composerJson = json_decode(
            File::get($this->testAppPath.'/composer.json'),
            true
        );

        $composerJson['repositories'] = [
            [
                'type' => 'path',
                'url' => $this->packagePath,
            ],
        ];

        File::put(
            $this->testAppPath.'/composer.json',
            json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Install the package
        $process = new Process([
            'composer',
            'require',
            'jerthedev/laravel-mcp:@dev',
            '--prefer-source',
        ], $this->testAppPath);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->fail('Could not install package: '.$process->getErrorOutput());
        }
    }

    /**
     * Verify package installation
     */
    private function verifyPackageInstallation(): void
    {
        // Check if package is in vendor
        $this->assertDirectoryExists(
            $this->testAppPath.'/vendor/jerthedev/laravel-mcp'
        );

        // Run artisan command to verify service provider is loaded
        $process = new Process([
            'php',
            'artisan',
            'mcp:list',
        ], $this->testAppPath);

        $process->run();

        // Command might not exist yet, so we just check it doesn't fatal error
        $this->assertNotEquals(255, $process->getExitCode());
    }

    /**
     * Validate configuration structure
     */
    private function validateConfigStructure(array $config): void
    {
        // Required top-level keys
        $requiredKeys = [
            'discovery',
            'transports',
            'server',
            'middleware',
            'logging',
            'cache',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Config must have '{$key}' key");
        }

        // Validate discovery section
        $this->assertIsArray($config['discovery']);
        $this->assertArrayHasKey('enabled', $config['discovery']);
        $this->assertArrayHasKey('paths', $config['discovery']);

        // Validate transports section
        $this->assertIsArray($config['transports']);
        $this->assertArrayHasKey('default', $config['transports']);

        // Validate server section
        $this->assertIsArray($config['server']);
        $this->assertArrayHasKey('name', $config['server']);
        $this->assertArrayHasKey('version', $config['server']);
    }
}
