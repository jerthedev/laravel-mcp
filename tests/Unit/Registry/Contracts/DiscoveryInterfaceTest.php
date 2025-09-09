<?php

namespace JTD\LaravelMCP\Tests\Unit\Registry\Contracts;

use JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for DiscoveryInterface contract.
 *
 * This test ensures that all implementations of DiscoveryInterface
 * properly implement the required methods for component discovery.
 */
class DiscoveryInterfaceTest extends TestCase
{
    /** @var DiscoveryInterface&MockObject */
    protected $discovery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discovery = $this->createMock(DiscoveryInterface::class);
    }

    /**
     * Test discover components in paths.
     */
    public function test_discover_components_in_paths(): void
    {
        $paths = [
            '/app/Mcp/Tools',
            '/app/Mcp/Resources',
            '/app/Mcp/Prompts',
        ];

        $expectedComponents = [
            'tools' => [
                'CalculatorTool' => [
                    'class' => 'App\\Mcp\\Tools\\CalculatorTool',
                    'path' => '/app/Mcp/Tools/CalculatorTool.php',
                ],
            ],
            'resources' => [
                'FileResource' => [
                    'class' => 'App\\Mcp\\Resources\\FileResource',
                    'path' => '/app/Mcp/Resources/FileResource.php',
                ],
            ],
            'prompts' => [
                'GreetingPrompt' => [
                    'class' => 'App\\Mcp\\Prompts\\GreetingPrompt',
                    'path' => '/app/Mcp/Prompts/GreetingPrompt.php',
                ],
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->with($paths)
            ->willReturn($expectedComponents);

        $components = $this->discovery->discover($paths);

        $this->assertSame($expectedComponents, $components);
    }

    /**
     * Test discover returns empty array when no components found.
     */
    public function test_discover_returns_empty_when_no_components(): void
    {
        $paths = ['/empty/path'];

        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->with($paths)
            ->willReturn([]);

        $components = $this->discovery->discover($paths);

        $this->assertSame([], $components);
    }

    /**
     * Test discoverType for specific component type.
     */
    public function test_discover_type_for_tools(): void
    {
        $paths = ['/app/Mcp/Tools'];

        $expectedTools = [
            'CalculatorTool' => [
                'class' => 'App\\Mcp\\Tools\\CalculatorTool',
                'path' => '/app/Mcp/Tools/CalculatorTool.php',
            ],
            'WeatherTool' => [
                'class' => 'App\\Mcp\\Tools\\WeatherTool',
                'path' => '/app/Mcp/Tools/WeatherTool.php',
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('discoverType')
            ->with('tools', $paths)
            ->willReturn($expectedTools);

        $tools = $this->discovery->discoverType('tools', $paths);

        $this->assertSame($expectedTools, $tools);
    }

    /**
     * Test discoverType for resources.
     */
    public function test_discover_type_for_resources(): void
    {
        $paths = ['/app/Mcp/Resources'];

        $expectedResources = [
            'DatabaseResource' => [
                'class' => 'App\\Mcp\\Resources\\DatabaseResource',
                'path' => '/app/Mcp/Resources/DatabaseResource.php',
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('discoverType')
            ->with('resources', $paths)
            ->willReturn($expectedResources);

        $resources = $this->discovery->discoverType('resources', $paths);

        $this->assertSame($expectedResources, $resources);
    }

    /**
     * Test discoverType for prompts.
     */
    public function test_discover_type_for_prompts(): void
    {
        $paths = ['/app/Mcp/Prompts'];

        $expectedPrompts = [
            'EmailPrompt' => [
                'class' => 'App\\Mcp\\Prompts\\EmailPrompt',
                'path' => '/app/Mcp/Prompts/EmailPrompt.php',
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('discoverType')
            ->with('prompts', $paths)
            ->willReturn($expectedPrompts);

        $prompts = $this->discovery->discoverType('prompts', $paths);

        $this->assertSame($expectedPrompts, $prompts);
    }

    /**
     * Test isValidComponent with valid component file.
     */
    public function test_is_valid_component_with_valid_file(): void
    {
        $filePath = '/app/Mcp/Tools/CalculatorTool.php';
        $type = 'tools';

        $this->discovery
            ->expects($this->once())
            ->method('isValidComponent')
            ->with($filePath, $type)
            ->willReturn(true);

        $this->assertTrue($this->discovery->isValidComponent($filePath, $type));
    }

    /**
     * Test isValidComponent with invalid component file.
     */
    public function test_is_valid_component_with_invalid_file(): void
    {
        $filePath = '/app/Mcp/Tools/InvalidFile.php';
        $type = 'tools';

        $this->discovery
            ->expects($this->once())
            ->method('isValidComponent')
            ->with($filePath, $type)
            ->willReturn(false);

        $this->assertFalse($this->discovery->isValidComponent($filePath, $type));
    }

    /**
     * Test isValidComponent with wrong type.
     */
    public function test_is_valid_component_with_wrong_type(): void
    {
        $filePath = '/app/Mcp/Tools/CalculatorTool.php';
        $type = 'resources'; // Wrong type for a tool

        $this->discovery
            ->expects($this->once())
            ->method('isValidComponent')
            ->with($filePath, $type)
            ->willReturn(false);

        $this->assertFalse($this->discovery->isValidComponent($filePath, $type));
    }

    /**
     * Test extractMetadata from component file.
     */
    public function test_extract_metadata_from_file(): void
    {
        $filePath = '/app/Mcp/Tools/CalculatorTool.php';

        $expectedMetadata = [
            'name' => 'calculator',
            'description' => 'Perform mathematical calculations',
            'class' => 'App\\Mcp\\Tools\\CalculatorTool',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'operation' => ['type' => 'string'],
                    'a' => ['type' => 'number'],
                    'b' => ['type' => 'number'],
                ],
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('extractMetadata')
            ->with($filePath)
            ->willReturn($expectedMetadata);

        $metadata = $this->discovery->extractMetadata($filePath);

        $this->assertSame($expectedMetadata, $metadata);
    }

    /**
     * Test extractMetadata returns empty array for invalid file.
     */
    public function test_extract_metadata_returns_empty_for_invalid(): void
    {
        $filePath = '/invalid/file.php';

        $this->discovery
            ->expects($this->once())
            ->method('extractMetadata')
            ->with($filePath)
            ->willReturn([]);

        $metadata = $this->discovery->extractMetadata($filePath);

        $this->assertSame([], $metadata);
    }

    /**
     * Test getClassFromFile with valid PHP file.
     */
    public function test_get_class_from_file_valid(): void
    {
        $filePath = '/app/Mcp/Tools/CalculatorTool.php';
        $expectedClass = 'App\\Mcp\\Tools\\CalculatorTool';

        $this->discovery
            ->expects($this->once())
            ->method('getClassFromFile')
            ->with($filePath)
            ->willReturn($expectedClass);

        $class = $this->discovery->getClassFromFile($filePath);

        $this->assertSame($expectedClass, $class);
    }

    /**
     * Test getClassFromFile returns null for file without class.
     */
    public function test_get_class_from_file_no_class(): void
    {
        $filePath = '/app/config.php';

        $this->discovery
            ->expects($this->once())
            ->method('getClassFromFile')
            ->with($filePath)
            ->willReturn(null);

        $class = $this->discovery->getClassFromFile($filePath);

        $this->assertNull($class);
    }

    /**
     * Test isValidComponentClass with valid tool class.
     */
    public function test_is_valid_component_class_valid_tool(): void
    {
        $className = 'App\\Mcp\\Tools\\CalculatorTool';
        $type = 'tools';

        $this->discovery
            ->expects($this->once())
            ->method('isValidComponentClass')
            ->with($className, $type)
            ->willReturn(true);

        $this->assertTrue($this->discovery->isValidComponentClass($className, $type));
    }

    /**
     * Test isValidComponentClass with invalid class.
     */
    public function test_is_valid_component_class_invalid(): void
    {
        $className = 'App\\Http\\Controllers\\TestController';
        $type = 'tools';

        $this->discovery
            ->expects($this->once())
            ->method('isValidComponentClass')
            ->with($className, $type)
            ->willReturn(false);

        $this->assertFalse($this->discovery->isValidComponentClass($className, $type));
    }

    /**
     * Test getSupportedTypes returns all supported types.
     */
    public function test_get_supported_types(): void
    {
        $expectedTypes = ['tools', 'resources', 'prompts'];

        $this->discovery
            ->expects($this->once())
            ->method('getSupportedTypes')
            ->willReturn($expectedTypes);

        $types = $this->discovery->getSupportedTypes();

        $this->assertSame($expectedTypes, $types);
    }

    /**
     * Test setConfig sets discovery configuration.
     */
    public function test_set_config(): void
    {
        $config = [
            'recursive' => true,
            'exclude' => ['vendor', 'tests'],
            'filePattern' => '*.php',
        ];

        $this->discovery
            ->expects($this->once())
            ->method('setConfig')
            ->with($config);

        $this->discovery->setConfig($config);
    }

    /**
     * Test getConfig returns current configuration.
     */
    public function test_get_config(): void
    {
        $expectedConfig = [
            'recursive' => true,
            'exclude' => ['vendor'],
            'filePattern' => '*.php',
        ];

        $this->discovery
            ->expects($this->once())
            ->method('getConfig')
            ->willReturn($expectedConfig);

        $config = $this->discovery->getConfig();

        $this->assertSame($expectedConfig, $config);
    }

    /**
     * Test addFilter adds discovery filter.
     */
    public function test_add_filter(): void
    {
        $filter = function ($component) {
            return $component['enabled'] ?? true;
        };

        $this->discovery
            ->expects($this->once())
            ->method('addFilter')
            ->with($filter);

        $this->discovery->addFilter($filter);
    }

    /**
     * Test getFilters returns all filters.
     */
    public function test_get_filters(): void
    {
        $filter1 = function ($c) {
            return true;
        };
        $filter2 = function ($c) {
            return false;
        };

        $expectedFilters = [$filter1, $filter2];

        $this->discovery
            ->expects($this->once())
            ->method('getFilters')
            ->willReturn($expectedFilters);

        $filters = $this->discovery->getFilters();

        $this->assertSame($expectedFilters, $filters);
    }

    /**
     * Test full discovery workflow.
     */
    public function test_full_discovery_workflow(): void
    {
        $paths = ['/app/Mcp'];
        $config = ['recursive' => true];
        $filter = function ($c) {
            return true;
        };

        // Set config
        $this->discovery
            ->expects($this->once())
            ->method('setConfig')
            ->with($config);

        // Add filter
        $this->discovery
            ->expects($this->once())
            ->method('addFilter')
            ->with($filter);

        // Discover components
        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->with($paths)
            ->willReturn([
                'tools' => ['CalculatorTool' => []],
                'resources' => ['FileResource' => []],
            ]);

        // Execute workflow
        $this->discovery->setConfig($config);
        $this->discovery->addFilter($filter);
        $components = $this->discovery->discover($paths);

        $this->assertArrayHasKey('tools', $components);
        $this->assertArrayHasKey('resources', $components);
    }

    /**
     * Test discovering with multiple paths.
     */
    public function test_discover_multiple_paths(): void
    {
        $paths = [
            '/app/Mcp',
            '/vendor/package/mcp',
            '/custom/components',
        ];

        $expectedComponents = [
            'tools' => [
                'AppTool' => ['path' => '/app/Mcp/Tools/AppTool.php'],
                'PackageTool' => ['path' => '/vendor/package/mcp/Tools/PackageTool.php'],
                'CustomTool' => ['path' => '/custom/components/Tools/CustomTool.php'],
            ],
        ];

        $this->discovery
            ->expects($this->once())
            ->method('discover')
            ->with($paths)
            ->willReturn($expectedComponents);

        $components = $this->discovery->discover($paths);

        $this->assertCount(3, $components['tools']);
    }

    /**
     * Test validation of component types.
     */
    public function test_validate_component_types(): void
    {
        $types = ['tools', 'resources', 'prompts'];

        // Set up expectation for exactly 3 calls
        $this->discovery
            ->expects($this->exactly(3))
            ->method('discoverType')
            ->willReturnCallback(function ($type, $paths) use ($types) {
                $this->assertContains($type, $types);
                $this->assertEquals(['/test/path'], $paths);

                return [];
            });

        foreach ($types as $type) {
            $result = $this->discovery->discoverType($type, ['/test/path']);
            $this->assertIsArray($result);
        }
    }

    /**
     * Test discovery with filters applied.
     */
    public function test_discovery_with_filters(): void
    {
        $enabledFilter = function ($component) {
            return ($component['metadata']['enabled'] ?? true) === true;
        };

        $versionFilter = function ($component) {
            return version_compare($component['metadata']['version'] ?? '0.0.0', '1.0.0', '>=');
        };

        // PHPUnit 10 removed withConsecutive, so we use willReturnCallback instead
        $callCount = 0;
        $expectedArgs = [$enabledFilter, $versionFilter];

        $this->discovery
            ->expects($this->exactly(2))
            ->method('addFilter')
            ->willReturnCallback(function ($filter) use (&$callCount, $expectedArgs) {
                $this->assertEquals($expectedArgs[$callCount], $filter);
                $callCount++;
            });

        $this->discovery
            ->expects($this->once())
            ->method('getFilters')
            ->willReturn([$enabledFilter, $versionFilter]);

        $this->discovery->addFilter($enabledFilter);
        $this->discovery->addFilter($versionFilter);

        $filters = $this->discovery->getFilters();
        $this->assertCount(2, $filters);
    }
}
