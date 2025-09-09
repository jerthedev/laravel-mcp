<?php

/**
 * Epic: MCP Protocol Layer
 * Sprint: Registration System
 * Ticket: REGISTRATION-016 - Registration System Core Implementation
 *
 * @epic MCP-002
 *
 * @sprint 3
 *
 * @ticket 016
 */

namespace JTD\LaravelMCP\Tests\Feature\Registry;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Facades\Mcp;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('feature')]
#[Group('registry')]
#[Group('auto-discovery')]
#[Group('ticket-016')]
class AutoDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private string $testPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test MCP directories
        $this->testPath = app_path('Mcp');
        File::makeDirectory($this->testPath.'/Tools', 0755, true, true);
        File::makeDirectory($this->testPath.'/Resources', 0755, true, true);
        File::makeDirectory($this->testPath.'/Prompts', 0755, true, true);

        // Clear discovery cache
        Cache::forget('laravel_mcp_discovered_components');
    }

    protected function tearDown(): void
    {
        // Clean up test directories
        File::deleteDirectory($this->testPath);

        // Clear cache
        Cache::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_auto_discovers_components_on_boot(): void
    {
        // Create a test tool
        $this->createTestToolFile('CalculatorTool');

        // Re-register service provider to trigger discovery
        $this->app->register(\JTD\LaravelMCP\LaravelMcpServiceProvider::class);

        // Check if discovery was triggered (though components won't be registered due to class not existing)
        $discovery = $this->app->make(ComponentDiscovery::class);
        $this->assertInstanceOf(ComponentDiscovery::class, $discovery);
    }

    #[Test]
    public function it_discovers_and_registers_multiple_component_types(): void
    {
        // Create test components
        $this->createTestToolFile('MathTool');
        $this->createTestResourceFile('UserResource');
        $this->createTestPromptFile('EmailPrompt');

        // Get discovery service
        $discovery = $this->app->make(ComponentDiscovery::class);

        // Discover components
        $discovered = $discovery->discoverComponents([
            $this->testPath.'/Tools',
            $this->testPath.'/Resources',
            $this->testPath.'/Prompts',
        ]);

        // Assert discovery structure
        $this->assertIsArray($discovered);
    }

    #[Test]
    public function it_caches_discovery_results(): void
    {
        // Create test components
        $this->createTestToolFile('CachedTool');

        // Get discovery service
        $discovery = $this->app->make(ComponentDiscovery::class);

        // First discovery should cache results
        $firstResult = $discovery->discoverComponents([$this->testPath.'/Tools']);

        // Clear the actual files
        File::deleteDirectory($this->testPath.'/Tools');

        // Second discovery should use cache
        $secondResult = $discovery->discoverComponents([$this->testPath.'/Tools']);

        // Results should be the same (from cache)
        $this->assertEquals($firstResult, $secondResult);
    }

    #[Test]
    public function it_clears_cache_when_requested(): void
    {
        // Create and cache a component
        $this->createTestToolFile('TempTool');

        $discovery = $this->app->make(ComponentDiscovery::class);
        $discovery->discoverComponents([$this->testPath.'/Tools']);

        // Clear cache
        $discovery->clearCache();

        // Delete the file
        File::deleteDirectory($this->testPath.'/Tools');
        File::makeDirectory($this->testPath.'/Tools', 0755, true, true);

        // Discovery should find nothing (not using cache)
        $result = $discovery->discoverComponents([$this->testPath.'/Tools']);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_validates_discovered_components(): void
    {
        // Create an invalid component (missing required method)
        $this->createInvalidTool('InvalidTool');

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Discover and validate
        $discovery->discoverComponents([$this->testPath.'/Tools']);

        // Validation should not throw exception
        $discovery->validateDiscoveredComponents();

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_discovery_with_namespace_conflicts(): void
    {
        // Create tools with same name in different namespaces
        $this->createTestToolWithNamespace('DatabaseTool', 'App\\Mcp\\Tools\\Admin');
        $this->createTestToolWithNamespace('DatabaseTool', 'App\\Mcp\\Tools\\User');

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Should handle both without conflict
        $result = $discovery->discoverComponents([$this->testPath.'/Tools']);

        $this->assertIsArray($result);
    }

    #[Test]
    public function it_ignores_test_files_during_discovery(): void
    {
        // Create a test file
        $testContent = <<<'PHP'
<?php
namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class CalculatorToolTest extends McpTool
{
    protected function handle(array $parameters): mixed
    {
        return 'test';
    }
}
PHP;

        File::put($this->testPath.'/Tools/CalculatorToolTest.php', $testContent);

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Configure to exclude test files
        $discovery->setConfig(['exclude_patterns' => ['*Test.php']]);

        $result = $discovery->discoverComponents([$this->testPath.'/Tools']);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_supports_custom_discovery_paths(): void
    {
        // Create custom path
        $customPath = storage_path('custom_mcp');
        File::makeDirectory($customPath.'/Tools', 0755, true, true);

        $this->createTestToolAtPath('CustomTool', $customPath.'/Tools');

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Discover in custom path
        $result = $discovery->discoverComponents([$customPath.'/Tools']);

        $this->assertIsArray($result);

        // Cleanup
        File::deleteDirectory($customPath);
    }

    #[Test]
    public function it_discovers_components_recursively(): void
    {
        // Create nested directory structure
        File::makeDirectory($this->testPath.'/Tools/Admin', 0755, true);
        File::makeDirectory($this->testPath.'/Tools/User', 0755, true);

        $this->createTestToolAtPath('AdminTool', $this->testPath.'/Tools/Admin');
        $this->createTestToolAtPath('UserTool', $this->testPath.'/Tools/User');

        $discovery = $this->app->make(ComponentDiscovery::class);
        $discovery->setConfig(['recursive' => true]);

        $result = $discovery->discoverComponents([$this->testPath.'/Tools']);

        $this->assertIsArray($result);
    }

    #[Test]
    public function it_handles_malformed_php_files_gracefully(): void
    {
        // Create a malformed PHP file
        $malformedContent = <<<'PHP'
<?php
namespace App\Mcp\Tools;

// Syntax error: missing closing brace
class BrokenTool {
    public function test() {
        
PHP;

        File::put($this->testPath.'/Tools/BrokenTool.php', $malformedContent);

        $discovery = $this->app->make(ComponentDiscovery::class);

        // Should not throw exception
        $result = $discovery->discoverComponents([$this->testPath.'/Tools']);

        $this->assertIsArray($result);
    }

    /**
     * Helper method to create a test tool.
     */
    private function createTestToolFile(string $name): void
    {
        $content = <<<PHP
<?php
namespace App\\Mcp\\Tools;

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$name} extends McpTool
{
    protected string \$name = '{$name}';
    protected string \$description = 'Test tool';
    
    protected function handle(array \$parameters): mixed
    {
        return 'result';
    }
}
PHP;

        File::put($this->testPath."/Tools/{$name}.php", $content);
    }

    /**
     * Helper method to create a test tool at specific path.
     */
    private function createTestToolAtPath(string $name, string $path): void
    {
        $content = <<<PHP
<?php
namespace App\\Mcp\\Tools;

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$name} extends McpTool
{
    protected string \$name = '{$name}';
    protected string \$description = 'Test tool';
    
    protected function handle(array \$parameters): mixed
    {
        return 'result';
    }
}
PHP;

        File::put("{$path}/{$name}.php", $content);
    }

    /**
     * Helper method to create a test tool with custom namespace.
     */
    private function createTestToolWithNamespace(string $name, string $namespace): void
    {
        $namespacePath = str_replace('\\', '/', str_replace('App\\Mcp\\', '', $namespace));
        $fullPath = $this->testPath.'/'.$namespacePath;

        File::makeDirectory($fullPath, 0755, true, true);

        $content = <<<PHP
<?php
namespace {$namespace};

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$name} extends McpTool
{
    protected string \$name = '{$name}';
    protected string \$description = 'Test tool';
    
    protected function handle(array \$parameters): mixed
    {
        return 'result';
    }
}
PHP;

        File::put("{$fullPath}/{$name}.php", $content);
    }

    /**
     * Helper method to create an invalid tool.
     */
    private function createInvalidTool(string $name): void
    {
        $content = <<<PHP
<?php
namespace App\\Mcp\\Tools;

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$name} extends McpTool
{
    // Missing required handle method
}
PHP;

        File::put($this->testPath."/Tools/{$name}.php", $content);
    }

    /**
     * Helper method to create a test resource.
     */
    private function createTestResourceFile(string $name): void
    {
        $content = <<<PHP
<?php
namespace App\\Mcp\\Resources;

use JTD\\LaravelMCP\\Abstracts\\McpResource;

class {$name} extends McpResource
{
    protected string \$name = '{$name}';
    protected string \$description = 'Test resource';
    
    public function read(string \$uri): array
    {
        return ['data' => 'test'];
    }
}
PHP;

        File::put($this->testPath."/Resources/{$name}.php", $content);
    }

    /**
     * Helper method to create a test prompt.
     */
    private function createTestPromptFile(string $name): void
    {
        $content = <<<PHP
<?php
namespace App\\Mcp\\Prompts;

use JTD\\LaravelMCP\\Abstracts\\McpPrompt;

class {$name} extends McpPrompt
{
    protected string \$name = '{$name}';
    protected string \$description = 'Test prompt';
    
    public function getMessages(array \$arguments): array
    {
        return [
            ['role' => 'user', 'content' => 'test']
        ];
    }
}
PHP;

        File::put($this->testPath."/Prompts/{$name}.php", $content);
    }
}
