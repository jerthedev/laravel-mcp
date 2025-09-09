<?php

namespace JTD\LaravelMCP\Tests\Feature;

use JTD\LaravelMCP\Tests\TestCase;

/**
 * Feature tests for directory structure validation.
 *
 * This test class ensures that the Laravel MCP package has the correct
 * directory structure as specified in the package structure specification.
 */
class DirectoryStructureValidationTest extends TestCase
{
    /**
     * Get the package root directory path.
     */
    protected function getPackagePath(string $path = ''): string
    {
        // Get the package root by going up from the tests directory
        $packageRoot = dirname(dirname(__DIR__));

        return $path ? $packageRoot.'/'.$path : $packageRoot;
    }

    /**
     * Test that all required source directories exist.
     *
     * @test
     */
    public function it_has_all_required_source_directories(): void
    {
        $requiredDirectories = [
            'src',
            'src/Commands',
            'src/Console',
            'src/Exceptions',
            'src/Facades',
            'src/Http',
            'src/Http/Controllers',
            'src/Http/Middleware',
            'src/Protocol',
            'src/Protocol/Contracts',
            'src/Registry',
            'src/Registry/Contracts',
            'src/Support',
            'src/Traits',
            'src/Transport',
            'src/Transport/Contracts',
            'src/Abstracts',
        ];

        foreach ($requiredDirectories as $directory) {
            $fullPath = $this->getPackagePath($directory);
            $this->assertDirectoryExists(
                $fullPath,
                "Required directory '{$directory}' does not exist"
            );
        }
    }

    /**
     * Test that all required configuration directories exist.
     *
     * @test
     */
    public function it_has_all_required_configuration_directories(): void
    {
        $requiredDirectories = [
            'config',
            'routes',
            'resources',
            'resources/stubs',
            'resources/views',
            'resources/views/debug',
        ];

        foreach ($requiredDirectories as $directory) {
            $fullPath = $this->getPackagePath($directory);
            $this->assertDirectoryExists(
                $fullPath,
                "Required configuration directory '{$directory}' does not exist"
            );
        }
    }

    /**
     * Test that all required test directories exist.
     *
     * @test
     */
    public function it_has_all_required_test_directories(): void
    {
        $requiredDirectories = [
            'tests',
            'tests/Unit',
            'tests/Feature',
            'tests/Fixtures',
            'tests/Fixtures/Tools',
            'tests/Fixtures/Resources',
            'tests/Fixtures/Prompts',
        ];

        foreach ($requiredDirectories as $directory) {
            $fullPath = $this->getPackagePath($directory);
            $this->assertDirectoryExists(
                $fullPath,
                "Required test directory '{$directory}' does not exist"
            );
        }
    }

    /**
     * Test that all contract interfaces exist.
     *
     * @test
     */
    public function it_has_all_required_contract_interfaces(): void
    {
        $requiredContracts = [
            'src/Transport/Contracts/TransportInterface.php',
            'src/Transport/Contracts/MessageHandlerInterface.php',
            'src/Protocol/Contracts/JsonRpcHandlerInterface.php',
            'src/Protocol/Contracts/ProtocolHandlerInterface.php',
            'src/Registry/Contracts/RegistryInterface.php',
            'src/Registry/Contracts/DiscoveryInterface.php',
        ];

        foreach ($requiredContracts as $contract) {
            $fullPath = $this->getPackagePath($contract);
            $this->assertFileExists(
                $fullPath,
                "Required contract interface '{$contract}' does not exist"
            );
        }
    }

    /**
     * Test that all foundational traits exist.
     *
     * @test
     */
    public function it_has_all_required_foundational_traits(): void
    {
        $requiredTraits = [
            'src/Traits/HandlesMcpRequests.php',
            'src/Traits/ValidatesParameters.php',
            'src/Traits/ManagesCapabilities.php',
        ];

        foreach ($requiredTraits as $trait) {
            $fullPath = $this->getPackagePath($trait);
            $this->assertFileExists(
                $fullPath,
                "Required trait '{$trait}' does not exist"
            );
        }
    }

    /**
     * Test that all exception classes exist.
     *
     * @test
     */
    public function it_has_all_required_exception_classes(): void
    {
        $requiredExceptions = [
            'src/Exceptions/McpException.php',
            'src/Exceptions/TransportException.php',
            'src/Exceptions/ProtocolException.php',
            'src/Exceptions/RegistrationException.php',
        ];

        foreach ($requiredExceptions as $exception) {
            $fullPath = $this->getPackagePath($exception);
            $this->assertFileExists(
                $fullPath,
                "Required exception class '{$exception}' does not exist"
            );
        }
    }

    /**
     * Test that the facade exists.
     *
     * @test
     */
    public function it_has_the_mcp_facade(): void
    {
        $facadePath = $this->getPackagePath('src/Facades/Mcp.php');
        $this->assertFileExists($facadePath, 'MCP Facade does not exist');
    }

    /**
     * Test that console utilities exist.
     *
     * @test
     */
    public function it_has_console_utilities(): void
    {
        $consoleFiles = [
            'src/Console/OutputFormatter.php',
        ];

        foreach ($consoleFiles as $file) {
            $fullPath = $this->getPackagePath($file);
            $this->assertFileExists(
                $fullPath,
                "Required console utility '{$file}' does not exist"
            );
        }
    }

    /**
     * Test that all code generation stubs exist.
     *
     * @test
     */
    public function it_has_all_code_generation_stubs(): void
    {
        $requiredStubs = [
            'resources/stubs/tool.stub',
            'resources/stubs/resource.stub',
            'resources/stubs/prompt.stub',
            'resources/stubs/mcp-routes.stub',
        ];

        foreach ($requiredStubs as $stub) {
            $fullPath = $this->getPackagePath($stub);
            $this->assertFileExists(
                $fullPath,
                "Required stub file '{$stub}' does not exist"
            );
        }
    }

    /**
     * Test that debug view template exists.
     *
     * @test
     */
    public function it_has_debug_view_template(): void
    {
        $viewPath = $this->getPackagePath('resources/views/debug/mcp-info.blade.php');
        $this->assertFileExists($viewPath, 'Debug view template does not exist');
    }

    /**
     * Test that base test case exists.
     *
     * @test
     */
    public function it_has_base_test_case(): void
    {
        $testCasePath = $this->getPackagePath('tests/TestCase.php');
        $this->assertFileExists($testCasePath, 'Base TestCase does not exist');
    }

    /**
     * Test that test fixtures exist.
     *
     * @test
     */
    public function it_has_test_fixtures(): void
    {
        $requiredFixtures = [
            'tests/Fixtures/Tools/SampleTool.php',
            'tests/Fixtures/Tools/CalculatorTool.php',
            'tests/Fixtures/Resources/SampleResource.php',
            'tests/Fixtures/Prompts/SamplePrompt.php',
            'tests/Fixtures/Prompts/EmailTemplatePrompt.php',
        ];

        foreach ($requiredFixtures as $fixture) {
            $fullPath = $this->getPackagePath($fixture);
            $this->assertFileExists(
                $fullPath,
                "Required fixture '{$fixture}' does not exist"
            );
        }
    }

    /**
     * Test that contract interfaces can be loaded and have expected methods.
     *
     * @test
     */
    public function it_can_load_all_contract_interfaces(): void
    {
        $contracts = [
            'JTD\LaravelMCP\Transport\Contracts\TransportInterface',
            'JTD\LaravelMCP\Transport\Contracts\MessageHandlerInterface',
            'JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface',
            'JTD\LaravelMCP\Protocol\Contracts\ProtocolHandlerInterface',
            'JTD\LaravelMCP\Registry\Contracts\RegistryInterface',
            'JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface',
        ];

        foreach ($contracts as $contractClass) {
            $this->assertTrue(
                interface_exists($contractClass),
                "Contract interface '{$contractClass}' cannot be loaded"
            );
        }
    }

    /**
     * Test that trait classes can be loaded.
     *
     * @test
     */
    public function it_can_load_all_trait_classes(): void
    {
        $traits = [
            'JTD\LaravelMCP\Traits\HandlesMcpRequests',
            'JTD\LaravelMCP\Traits\ValidatesParameters',
            'JTD\LaravelMCP\Traits\ManagesCapabilities',
        ];

        foreach ($traits as $traitClass) {
            $this->assertTrue(
                trait_exists($traitClass),
                "Trait '{$traitClass}' cannot be loaded"
            );
        }
    }

    /**
     * Test that exception classes can be loaded and extend the base class.
     *
     * @test
     */
    public function it_can_load_all_exception_classes(): void
    {
        $exceptions = [
            'JTD\LaravelMCP\Exceptions\McpException',
            'JTD\LaravelMCP\Exceptions\TransportException',
            'JTD\LaravelMCP\Exceptions\ProtocolException',
            'JTD\LaravelMCP\Exceptions\RegistrationException',
        ];

        foreach ($exceptions as $exceptionClass) {
            $this->assertTrue(
                class_exists($exceptionClass),
                "Exception class '{$exceptionClass}' cannot be loaded"
            );

            // Test that child exceptions extend McpException (except the base class)
            if ($exceptionClass !== 'JTD\LaravelMCP\Exceptions\McpException') {
                $this->assertTrue(
                    is_subclass_of($exceptionClass, 'JTD\LaravelMCP\Exceptions\McpException'),
                    "Exception class '{$exceptionClass}' does not extend McpException"
                );
            }
        }
    }

    /**
     * Test that facade can be loaded.
     *
     * @test
     */
    public function it_can_load_mcp_facade(): void
    {
        $facadeClass = 'JTD\LaravelMCP\Facades\Mcp';
        $this->assertTrue(
            class_exists($facadeClass),
            "Facade class '{$facadeClass}' cannot be loaded"
        );

        $this->assertTrue(
            is_subclass_of($facadeClass, 'Illuminate\Support\Facades\Facade'),
            "Facade class does not extend Laravel's Facade class"
        );
    }

    /**
     * Test that console utilities can be loaded.
     *
     * @test
     */
    public function it_can_load_console_utilities(): void
    {
        $utilityClass = 'JTD\LaravelMCP\Console\OutputFormatter';
        $this->assertTrue(
            class_exists($utilityClass),
            "Console utility class '{$utilityClass}' cannot be loaded"
        );
    }

    /**
     * Test that test fixtures can be loaded.
     *
     * @test
     */
    public function it_can_load_test_fixtures(): void
    {
        $fixtures = [
            'JTD\LaravelMCP\Tests\Fixtures\Tools\SampleTool',
            'JTD\LaravelMCP\Tests\Fixtures\Tools\CalculatorTool',
            'JTD\LaravelMCP\Tests\Fixtures\Resources\SampleResource',
            'JTD\LaravelMCP\Tests\Fixtures\Prompts\SamplePrompt',
            'JTD\LaravelMCP\Tests\Fixtures\Prompts\EmailTemplatePrompt',
        ];

        foreach ($fixtures as $fixtureClass) {
            $this->assertTrue(
                class_exists($fixtureClass),
                "Fixture class '{$fixtureClass}' cannot be loaded"
            );
        }
    }

    /**
     * Test namespace organization follows Laravel patterns.
     *
     * @test
     */
    public function it_follows_laravel_namespace_patterns(): void
    {
        // Test that files are in expected namespaces
        $this->assertTrue(interface_exists('JTD\LaravelMCP\Transport\Contracts\TransportInterface'));
        $this->assertTrue(interface_exists('JTD\LaravelMCP\Protocol\Contracts\JsonRpcHandlerInterface'));
        $this->assertTrue(interface_exists('JTD\LaravelMCP\Registry\Contracts\RegistryInterface'));
        $this->assertTrue(trait_exists('JTD\LaravelMCP\Traits\HandlesMcpRequests'));
        $this->assertTrue(class_exists('JTD\LaravelMCP\Facades\Mcp'));
        $this->assertTrue(class_exists('JTD\LaravelMCP\Exceptions\McpException'));
        $this->assertTrue(class_exists('JTD\LaravelMCP\Console\OutputFormatter'));
    }

    /**
     * Test that PSR-4 autoloading works correctly.
     *
     * @test
     */
    public function it_supports_psr4_autoloading(): void
    {
        // Test that classes can be autoloaded via PSR-4
        $testClasses = [
            'JTD\LaravelMCP\Transport\Contracts\TransportInterface',
            'JTD\LaravelMCP\Protocol\Contracts\ProtocolHandlerInterface',
            'JTD\LaravelMCP\Registry\Contracts\DiscoveryInterface',
            'JTD\LaravelMCP\Traits\ValidatesParameters',
            'JTD\LaravelMCP\Exceptions\TransportException',
            'JTD\LaravelMCP\Facades\Mcp',
        ];

        foreach ($testClasses as $class) {
            $this->assertTrue(
                interface_exists($class) || trait_exists($class) || class_exists($class),
                "Class '{$class}' cannot be autoloaded via PSR-4"
            );
        }
    }
}
