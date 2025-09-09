<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry;

use Illuminate\Support\Facades\File;
use JTD\LaravelMCP\Registry\ComponentDiscovery;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test suite for ComponentDiscovery functionality.
 *
 * Tests the component discovery service that automatically finds and analyzes
 * MCP components in Laravel applications.
 */
class ComponentDiscoveryTest extends TestCase
{
    private ComponentDiscovery $discovery;

    private MockObject|McpRegistry $mockRegistry;

    private MockObject|ToolRegistry $mockToolRegistry;

    private MockObject|ResourceRegistry $mockResourceRegistry;

    private MockObject|PromptRegistry $mockPromptRegistry;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockRegistry = $this->createMock(McpRegistry::class);
        $this->mockToolRegistry = $this->createMock(ToolRegistry::class);
        $this->mockResourceRegistry = $this->createMock(ResourceRegistry::class);
        $this->mockPromptRegistry = $this->createMock(PromptRegistry::class);

        $this->discovery = new ComponentDiscovery(
            $this->mockRegistry,
            $this->mockToolRegistry,
            $this->mockResourceRegistry,
            $this->mockPromptRegistry
        );

        // Create temporary directory for test files
        $this->tempDir = sys_get_temp_dir().'/mcp_discovery_test_'.uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    /**
     * Test discovering components in directories.
     */
    public function test_discover_components_in_directories(): void
    {
        // Create test component files
        $toolsDir = $this->tempDir.'/Tools';
        $resourcesDir = $this->tempDir.'/Resources';
        $promptsDir = $this->tempDir.'/Prompts';

        mkdir($toolsDir, 0755, true);
        mkdir($resourcesDir, 0755, true);
        mkdir($promptsDir, 0755, true);

        // Define test classes dynamically using eval to ensure they exist
        if (!class_exists('App\Mcp\Tools\CalculatorTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Calculator tool for mathematical operations.
 */
class CalculatorTool extends McpTool
{
    protected string $name = "calculator";
    protected string $description = "Performs calculations";

    public function execute(array $arguments): array
    {
        return ["result" => 0];
    }
}
');
        }
        
        // Create tool file
        $toolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Calculator tool for mathematical operations.
 */
class CalculatorTool extends McpTool
{
    protected string $name = "calculator";
    protected string $description = "Performs calculations";

    public function execute(array $arguments): array
    {
        return ["result" => 0];
    }
}';
        file_put_contents($toolsDir.'/CalculatorTool.php', $toolContent);

        // Define resource class dynamically
        if (!class_exists('App\Mcp\Resources\UserProfileResource')) {
            eval('
namespace App\Mcp\Resources;
use JTD\LaravelMCP\Abstracts\McpResource;

/**
 * User profile resource.
 */
class UserProfileResource extends McpResource
{
    protected string $uri = "user://profile/{id}";

    public function read(array $options = []): array
    {
        return ["contents" => []];
    }
}
');
        }
        
        // Create resource file
        $resourceContent = '<?php
namespace App\Mcp\Resources;
use JTD\LaravelMCP\Abstracts\McpResource;

/**
 * User profile resource.
 */
class UserProfileResource extends McpResource
{
    protected string $uri = "user://profile/{id}";

    public function read(array $options = []): array
    {
        return ["contents" => []];
    }
}';
        file_put_contents($resourcesDir.'/UserProfileResource.php', $resourceContent);

        // Define prompt class dynamically
        if (!class_exists('App\Mcp\Prompts\EmailTemplatePrompt')) {
            eval('
namespace App\Mcp\Prompts;
use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Email template prompt.
 */
class EmailTemplatePrompt extends McpPrompt
{
    protected string $name = "email_template";

    public function getMessages(array $arguments): array
    {
        return ["messages" => []];
    }
}
');
        }
        
        // Create prompt file
        $promptContent = '<?php
namespace App\Mcp\Prompts;
use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Email template prompt.
 */
class EmailTemplatePrompt extends McpPrompt
{
    protected string $name = "email_template";

    public function getMessages(array $arguments): array
    {
        return ["messages" => []];
    }
}';
        file_put_contents($promptsDir.'/EmailTemplatePrompt.php', $promptContent);

        $discovered = $this->discovery->discover([$this->tempDir]);

        $this->assertArrayHasKey('tools', $discovered);
        $this->assertArrayHasKey('resources', $discovered);
        $this->assertArrayHasKey('prompts', $discovered);

        // Check that components were discovered
        $this->assertCount(1, $discovered['tools'], 'Should have discovered 1 tool');
        $this->assertCount(1, $discovered['resources'], 'Should have discovered 1 resource');
        $this->assertCount(1, $discovered['prompts'], 'Should have discovered 1 prompt');

        // Check component metadata
        $toolClass = array_keys($discovered['tools'])[0];
        $this->assertStringContainsString('CalculatorTool', $toolClass);
        $this->assertEquals('tool', $discovered['tools'][$toolClass]['type']);
        $this->assertStringContainsString('Calculator tool for mathematical operations', $discovered['tools'][$toolClass]['description']);

        $resourceClass = array_keys($discovered['resources'])[0];
        $this->assertStringContainsString('UserProfileResource', $resourceClass);
        $this->assertEquals('resource', $discovered['resources'][$resourceClass]['type']);

        $promptClass = array_keys($discovered['prompts'])[0];
        $this->assertStringContainsString('EmailTemplatePrompt', $promptClass);
        $this->assertEquals('prompt', $discovered['prompts'][$promptClass]['type']);
    }

    /**
     * Test discovering components of specific type.
     */
    public function test_discover_components_of_specific_type(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        // Define test class dynamically
        if (!class_exists('App\Mcp\Tools\TestTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}
');
        }

        $toolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}';
        file_put_contents($toolsDir.'/TestTool.php', $toolContent);

        $discovered = $this->discovery->discoverType('tools', [$this->tempDir]);

        $this->assertCount(1, $discovered);
        $toolClass = array_keys($discovered)[0];
        $this->assertStringContainsString('TestTool', $toolClass);
    }

    /**
     * Test discovering components with invalid type returns empty.
     */
    public function test_discover_invalid_type_returns_empty(): void
    {
        $discovered = $this->discovery->discoverType('invalid_type', [$this->tempDir]);

        $this->assertEmpty($discovered);
    }

    /**
     * Test discovering in non-existent directory.
     */
    public function test_discover_in_non_existent_directory(): void
    {
        $discovered = $this->discovery->discover(['/nonexistent/path']);

        $this->assertEquals([
            'tools' => [],
            'resources' => [],
            'prompts' => [],
        ], $discovered);
    }

    /**
     * Test valid component detection.
     */
    public function test_valid_component_detection(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $validToolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class ValidTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}';
        // Define valid tool class dynamically
        if (!class_exists('App\Mcp\Tools\ValidTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class ValidTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}
');
        }
        
        $validToolFile = $toolsDir.'/ValidTool.php';
        file_put_contents($validToolFile, $validToolContent);

        $invalidToolContent = '<?php
namespace App\Mcp\Tools;

class InvalidTool
{
    // Does not extend McpTool
}';
        // Define invalid tool class dynamically
        if (!class_exists('App\Mcp\Tools\InvalidTool')) {
            eval('
namespace App\Mcp\Tools;

class InvalidTool
{
    // Does not extend McpTool
}
');
        }
        
        $invalidToolFile = $toolsDir.'/InvalidTool.php';
        file_put_contents($invalidToolFile, $invalidToolContent);

        $this->assertTrue($this->discovery->isValidComponent($validToolFile, 'tools'));
        $this->assertFalse($this->discovery->isValidComponent($invalidToolFile, 'tools'));
    }

    /**
     * Test metadata extraction from components.
     */
    public function test_metadata_extraction(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $toolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Advanced calculator tool.
 * 
 * This tool performs complex mathematical operations
 * including basic arithmetic and advanced functions.
 * 
 * @author Test Author
 * @version 1.0
 */
class AdvancedCalculatorTool extends McpTool
{
    protected string $name = "advanced_calculator";
    protected string $description = "Performs advanced calculations";

    public function execute(array $arguments): array
    {
        return ["result" => 0];
    }

    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    private function internalMethod(): void
    {
        // Private method
    }
}';
        // Define class dynamically
        if (!class_exists('App\Mcp\Tools\AdvancedCalculatorTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Advanced calculator tool.
 * 
 * This tool performs complex mathematical operations
 * including basic arithmetic and advanced functions.
 * 
 * @author Test Author
 * @version 1.0
 */
class AdvancedCalculatorTool extends McpTool
{
    protected string $name = "advanced_calculator";
    protected string $description = "Performs advanced calculations";

    public function execute(array $arguments): array
    {
        return ["result" => 0];
    }

    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    private function internalMethod(): void
    {
        // Private method
    }
}
');
        }
        
        $toolFile = $toolsDir.'/AdvancedCalculatorTool.php';
        file_put_contents($toolFile, $toolContent);

        $metadata = $this->discovery->extractMetadata($toolFile);

        $this->assertArrayHasKey('class', $metadata);
        $this->assertArrayHasKey('file', $metadata);
        $this->assertArrayHasKey('namespace', $metadata);
        $this->assertArrayHasKey('name', $metadata);
        $this->assertArrayHasKey('description', $metadata);
        $this->assertArrayHasKey('methods', $metadata);
        $this->assertArrayHasKey('properties', $metadata);
        $this->assertArrayHasKey('type', $metadata);

        $this->assertStringContainsString('AdvancedCalculatorTool', $metadata['class']);
        $this->assertEquals('App\Mcp\Tools', $metadata['namespace']);
        $this->assertEquals('AdvancedCalculatorTool', $metadata['name']);
        $this->assertEquals('tool', $metadata['type']);
        $this->assertStringContainsString('Advanced calculator tool', $metadata['description']);
        $this->assertContains('execute', $metadata['methods']);
        $this->assertContains('add', $metadata['methods']);
        $this->assertNotContains('internalMethod', $metadata['methods']);
    }

    /**
     * Test getting class name from file.
     */
    public function test_get_class_name_from_file(): void
    {
        $testFile = $this->tempDir.'/TestClass.php';
        $content = '<?php
namespace App\Test;

class TestClass
{
    // Class content
}';
        file_put_contents($testFile, $content);

        $className = $this->discovery->getClassFromFile($testFile);

        $this->assertEquals('App\Test\TestClass', $className);
    }

    /**
     * Test getting class name from file without namespace.
     */
    public function test_get_class_name_from_file_without_namespace(): void
    {
        $testFile = $this->tempDir.'/NoNamespaceClass.php';
        $content = '<?php
class NoNamespaceClass
{
    // Class content
}';
        file_put_contents($testFile, $content);

        $className = $this->discovery->getClassFromFile($testFile);

        $this->assertEquals('NoNamespaceClass', $className);
    }

    /**
     * Test getting class name from non-existent file returns null.
     */
    public function test_get_class_name_from_non_existent_file(): void
    {
        $className = $this->discovery->getClassFromFile('/nonexistent/file.php');

        $this->assertNull($className);
    }

    /**
     * Test valid component class detection.
     */
    public function test_valid_component_class_detection(): void
    {
        // Create actual test classes in temp files for proper testing
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $validToolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class ValidTestTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}';
        // Define valid test tool class dynamically
        if (!class_exists('App\Mcp\Tools\ValidTestTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class ValidTestTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}
');
        }
        
        file_put_contents($toolsDir.'/ValidTestTool.php', $validToolContent);

        $this->assertTrue($this->discovery->isValidComponentClass('App\Mcp\Tools\ValidTestTool', 'tools'));
        $this->assertFalse($this->discovery->isValidComponentClass('NonExistentClass', 'tools'));
        $this->assertFalse($this->discovery->isValidComponentClass('App\Mcp\Tools\ValidTestTool', 'invalid_type'));
    }

    /**
     * Test getting supported types.
     */
    public function test_get_supported_types(): void
    {
        $types = $this->discovery->getSupportedTypes();

        $this->assertEquals(['tools', 'resources', 'prompts'], $types);
    }

    /**
     * Test setting and getting configuration.
     */
    public function test_configuration_methods(): void
    {
        $newConfig = [
            'recursive' => false,
            'file_patterns' => ['*.class.php'],
            'exclude_patterns' => ['*Test*.php'],
        ];

        $this->discovery->setConfig($newConfig);
        $config = $this->discovery->getConfig();

        $this->assertEquals(false, $config['recursive']);
        $this->assertEquals(['*.class.php'], $config['file_patterns']);
        $this->assertEquals(['*Test*.php'], $config['exclude_patterns']);
    }

    /**
     * Test adding and getting filters.
     */
    public function test_filter_methods(): void
    {
        $filter1 = function ($filePath) {
            return ! str_contains($filePath, 'excluded');
        };

        $filter2 = function ($filePath) {
            return str_ends_with($filePath, '.php');
        };

        $this->discovery->addFilter($filter1);
        $this->discovery->addFilter($filter2);

        $filters = $this->discovery->getFilters();

        $this->assertCount(2, $filters);
        $this->assertSame($filter1, $filters[0]);
        $this->assertSame($filter2, $filters[1]);
    }

    /**
     * Test component discovery and registration.
     */
    public function test_discover_components(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $toolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestDiscoveryTool extends McpTool
{
    protected string $name = "test_discovery";

    public function execute(array $arguments): array
    {
        return [];
    }
}';
        // Define class dynamically
        if (!class_exists('App\Mcp\Tools\TestDiscoveryTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

class TestDiscoveryTool extends McpTool
{
    protected string $name = "test_discovery";

    public function execute(array $arguments): array
    {
        return [];
    }
}
');
        }
        
        file_put_contents($toolsDir.'/TestDiscoveryTool.php', $toolContent);

        // Set up expectations for registry registrations
        $this->mockToolRegistry->expects($this->once())
            ->method('register')
            ->with(
                $this->stringContains('TestDiscoveryTool'),
                $this->stringContains('TestDiscoveryTool'),
                $this->arrayHasKey('type')
            );

        $this->discovery->discoverComponents([$this->tempDir]);
    }

    /**
     * Test file filtering with exclude patterns.
     */
    public function test_file_filtering_with_exclude_patterns(): void
    {
        $testDir = $this->tempDir.'/filtering';
        mkdir($testDir, 0755, true);

        // Create files that should be excluded
        file_put_contents($testDir.'/SomeTest.php', '<?php class SomeTest {}');
        file_put_contents($testDir.'/ValidTool.php', '<?php class ValidTool {}');
        file_put_contents($testDir.'/another_test.php', '<?php class AnotherTest {}');

        // Set config with exclude patterns
        $this->discovery->setConfig(['exclude_patterns' => ['*Test.php', '*test.php']]);

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->discovery);
        $passesFiltersMethod = $reflection->getMethod('passesFilters');
        $passesFiltersMethod->setAccessible(true);

        $this->assertFalse($passesFiltersMethod->invokeArgs($this->discovery, [$testDir.'/SomeTest.php']));
        $this->assertTrue($passesFiltersMethod->invokeArgs($this->discovery, [$testDir.'/ValidTool.php']));
        $this->assertFalse($passesFiltersMethod->invokeArgs($this->discovery, [$testDir.'/another_test.php']));
    }

    /**
     * Test file filtering with custom filters.
     */
    public function test_file_filtering_with_custom_filters(): void
    {
        $testFile = $this->tempDir.'/CustomTest.php';
        file_put_contents($testFile, '<?php class CustomTest {}');

        // Add custom filter
        $this->discovery->addFilter(function ($filePath) {
            return ! str_contains(basename($filePath), 'Custom');
        });

        // Use reflection to access protected method
        $reflection = new \ReflectionClass($this->discovery);
        $passesFiltersMethod = $reflection->getMethod('passesFilters');
        $passesFiltersMethod->setAccessible(true);

        $this->assertFalse($passesFiltersMethod->invokeArgs($this->discovery, [$testFile]));
    }

    /**
     * Test abstract class handling.
     */
    public function test_abstract_class_handling(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $abstractToolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

abstract class AbstractTool extends McpTool
{
    // Abstract class should be ignored
}';
        $abstractFile = $toolsDir.'/AbstractTool.php';
        file_put_contents($abstractFile, $abstractToolContent);

        $this->assertFalse($this->discovery->isValidComponent($abstractFile, 'tools'));
    }

    /**
     * Test interface handling.
     */
    public function test_interface_handling(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $interfaceContent = '<?php
namespace App\Mcp\Tools;

interface ToolInterface
{
    // Interface should be ignored
}';
        $interfaceFile = $toolsDir.'/ToolInterface.php';
        file_put_contents($interfaceFile, $interfaceContent);

        $className = $this->discovery->getClassFromFile($interfaceFile);
        if ($className) {
            // The getClassFromFile might still return the interface name,
            // but isValidComponentClass should return false
            $this->assertFalse($this->discovery->isValidComponentClass($className, 'tools'));
        } else {
            // If no class name is returned, that's also valid for an interface
            $this->assertNull($className, 'Interface should not be detected as a class');
        }
    }

    /**
     * Test description parsing from doc comments.
     */
    public function test_description_parsing_from_doc_comments(): void
    {
        $toolsDir = $this->tempDir.'/Tools';
        mkdir($toolsDir, 0755, true);

        $toolContent = '<?php
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Multi-line description tool.
 * 
 * This tool has a longer description that spans
 * multiple lines and includes details about its functionality.
 * 
 * @param string $arg1 First argument
 * @return array Result array
 */
class MultilineDescriptionTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}';
        // Define class dynamically
        if (!class_exists('App\Mcp\Tools\MultilineDescriptionTool')) {
            eval('
namespace App\Mcp\Tools;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Multi-line description tool.
 * 
 * This tool has a longer description that spans
 * multiple lines and includes details about its functionality.
 * 
 * @param string $arg1 First argument
 * @return array Result array
 */
class MultilineDescriptionTool extends McpTool
{
    public function execute(array $arguments): array
    {
        return [];
    }
}
');
        }
        
        $toolFile = $toolsDir.'/MultilineDescriptionTool.php';
        file_put_contents($toolFile, $toolContent);

        $metadata = $this->discovery->extractMetadata($toolFile);
        
        // Ensure metadata was extracted
        $this->assertNotEmpty($metadata, 'Metadata should not be empty');
        $this->assertArrayHasKey('description', $metadata, 'Metadata should have description key');

        $this->assertStringContainsString('Multi-line description tool', $metadata['description']);
        $this->assertStringContainsString('This tool has a longer description', $metadata['description']);
        $this->assertStringNotContainsString('@param', $metadata['description']);
        $this->assertStringNotContainsString('@return', $metadata['description']);
    }

    /**
     * Test handling malformed PHP files.
     */
    public function test_malformed_php_file_handling(): void
    {
        $malformedFile = $this->tempDir.'/MalformedFile.php';
        file_put_contents($malformedFile, '<?php this is not valid PHP syntax');

        $className = $this->discovery->getClassFromFile($malformedFile);
        $this->assertNull($className);

        $metadata = $this->discovery->extractMetadata($malformedFile);
        $this->assertEquals([], $metadata);
    }

    /**
     * Helper method to remove directory recursively.
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
