<?php

/**
 * @file tests/Unit/Protocol/CapabilityNegotiatorTest.php
 *
 * @description Unit tests for CapabilityNegotiator
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Protocol\CapabilityNegotiator
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-CapabilityNegotiator
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Protocol Negotiation
 *
 * @testPriority Critical
 *
 * @quality Production-ready
 *
 * @coverage 95%+
 *
 * @standards PSR-12, PHPUnit 10.x
 */

declare(strict_types=1);

namespace JTD\LaravelMCP\Tests\Unit\Protocol;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Protocol\CapabilityNegotiator;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(CapabilityNegotiator::class)]
#[Group('ticket-027')]
#[Group('protocol')]
#[Group('negotiation')]
class CapabilityNegotiatorTest extends UnitTestCase
{
    private CapabilityNegotiator $negotiator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->negotiator = new CapabilityNegotiator;
    }

    #[Test]
    public function it_constructs_with_default_capabilities(): void
    {
        $defaults = $this->negotiator->getDefaultServerCapabilities();

        $this->assertArrayHasKey('tools', $defaults);
        $this->assertArrayHasKey('resources', $defaults);
        $this->assertArrayHasKey('prompts', $defaults);
        $this->assertArrayHasKey('logging', $defaults);
    }

    #[Test]
    public function it_negotiates_basic_capabilities(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => false],
        ];

        $serverCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => true],
        ];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        $this->assertArrayHasKey('tools', $negotiated);
        $this->assertArrayHasKey('resources', $negotiated);
        $this->assertTrue($negotiated['tools']['listChanged']);
        $this->assertTrue($negotiated['resources']['subscribe']);
        $this->assertFalse($negotiated['resources']['listChanged']);
    }

    #[Test]
    public function it_uses_server_defaults_when_client_provides_no_capabilities(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [];
        $serverCapabilities = [
            'tools' => ['listChanged' => true],
        ];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        $this->assertArrayHasKey('tools', $negotiated);
        $this->assertTrue($negotiated['tools']['listChanged']);
    }

    #[Test]
    public function it_handles_client_specific_capabilities(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [
            'experimental' => ['feature1' => true],
        ];

        $serverCapabilities = [];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        // Default capabilities should still be present
        $this->assertArrayHasKey('tools', $negotiated);
        $this->assertArrayHasKey('resources', $negotiated);
    }

    #[Test]
    public function it_negotiates_complex_feature_arrays(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [
            'logging' => ['level' => 'debug', 'targets' => ['file']],
        ];

        $serverCapabilities = [
            'logging' => ['level' => 'info', 'targets' => ['database']],
        ];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        $this->assertArrayHasKey('logging', $negotiated);
        $this->assertIsArray($negotiated['logging']);
    }

    #[Test]
    public function it_validates_capabilities(): void
    {
        $validCapabilities = [
            'tools' => [],
            'resources' => [],
        ];

        $this->assertTrue($this->negotiator->validateCapabilities($validCapabilities));

        // Test with missing required capability
        $this->negotiator->addCapabilityConstraint('required_capability', ['required' => true]);
        $this->assertFalse($this->negotiator->validateCapabilities($validCapabilities));
    }

    #[Test]
    public function it_generates_capability_summary(): void
    {
        $capabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true, 'listChanged' => false],
            'prompts' => ['listChanged' => true],
        ];

        $summary = $this->negotiator->getCapabilitySummary($capabilities);

        $this->assertArrayHasKey('supported_capabilities', $summary);
        $this->assertArrayHasKey('feature_count', $summary);
        $this->assertArrayHasKey('enabled_features', $summary);
        $this->assertArrayHasKey('disabled_features', $summary);

        $this->assertSame(['tools', 'resources', 'prompts'], $summary['supported_capabilities']);
        $this->assertSame(4, $summary['feature_count']);
        $this->assertContains('tools.listChanged', $summary['enabled_features']);
        $this->assertContains('resources.listChanged', $summary['disabled_features']);
    }

    #[Test]
    public function it_checks_capability_existence(): void
    {
        $capabilities = [
            'tools' => ['listChanged' => true],
            'resources' => [],
        ];

        $this->assertTrue($this->negotiator->hasCapability($capabilities, 'tools'));
        $this->assertTrue($this->negotiator->hasCapability($capabilities, 'resources'));
        $this->assertFalse($this->negotiator->hasCapability($capabilities, 'prompts'));
    }

    #[Test]
    public function it_checks_feature_existence(): void
    {
        $capabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => false, 'listChanged' => true],
        ];

        $this->assertTrue($this->negotiator->hasFeature($capabilities, 'tools', 'listChanged'));
        $this->assertFalse($this->negotiator->hasFeature($capabilities, 'resources', 'subscribe'));
        $this->assertTrue($this->negotiator->hasFeature($capabilities, 'resources', 'listChanged'));
        $this->assertFalse($this->negotiator->hasFeature($capabilities, 'prompts', 'listChanged'));
    }

    #[Test]
    public function it_gets_specific_capability_defaults(): void
    {
        $tools = $this->negotiator->getToolsCapabilities();
        $this->assertArrayHasKey('listChanged', $tools);
        $this->assertFalse($tools['listChanged']);

        $resources = $this->negotiator->getResourcesCapabilities();
        $this->assertArrayHasKey('subscribe', $resources);
        $this->assertArrayHasKey('listChanged', $resources);
        $this->assertFalse($resources['subscribe']);
        $this->assertFalse($resources['listChanged']);

        $prompts = $this->negotiator->getPromptsCapabilities();
        $this->assertArrayHasKey('listChanged', $prompts);
        $this->assertFalse($prompts['listChanged']);

        $logging = $this->negotiator->getLoggingCapabilities();
        $this->assertIsArray($logging);
        $this->assertEmpty($logging);
    }

    #[Test]
    public function it_sets_and_gets_default_server_capabilities(): void
    {
        $newDefaults = [
            'experimental' => ['feature1' => true],
        ];

        $this->negotiator->setDefaultServerCapabilities($newDefaults);
        $defaults = $this->negotiator->getDefaultServerCapabilities();

        $this->assertArrayHasKey('experimental', $defaults);
        $this->assertTrue($defaults['experimental']['feature1']);
        // Original defaults should still be present
        $this->assertArrayHasKey('tools', $defaults);
    }

    #[Test]
    public function it_manages_capability_constraints(): void
    {
        $constraint = [
            'required' => true,
            'features' => ['feature1', 'feature2'],
        ];

        $this->negotiator->addCapabilityConstraint('custom', $constraint);
        $constraints = $this->negotiator->getCapabilityConstraints();

        $this->assertArrayHasKey('custom', $constraints);
        $this->assertTrue($constraints['custom']['required']);
        $this->assertContains('feature1', $constraints['custom']['features']);

        $this->negotiator->removeCapabilityConstraint('custom');
        $constraints = $this->negotiator->getCapabilityConstraints();
        $this->assertArrayNotHasKey('custom', $constraints);
    }

    #[Test]
    public function it_creates_minimal_capabilities(): void
    {
        $minimal = $this->negotiator->createMinimalCapabilities();

        $this->assertArrayHasKey('tools', $minimal);
        $this->assertArrayHasKey('resources', $minimal);
        $this->assertArrayHasKey('prompts', $minimal);
        $this->assertEmpty($minimal['tools']);
        $this->assertEmpty($minimal['resources']);
        $this->assertEmpty($minimal['prompts']);
    }

    #[Test]
    public function it_creates_full_capabilities(): void
    {
        $full = $this->negotiator->createFullCapabilities();

        $this->assertArrayHasKey('tools', $full);
        $this->assertArrayHasKey('resources', $full);
        $this->assertArrayHasKey('prompts', $full);
        $this->assertArrayHasKey('logging', $full);

        $this->assertTrue($full['tools']['listChanged']);
        $this->assertTrue($full['resources']['subscribe']);
        $this->assertTrue($full['resources']['listChanged']);
        $this->assertTrue($full['prompts']['listChanged']);
    }

    #[Test]
    public function it_handles_invalid_client_values_with_safe_defaults(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [
            'tools' => ['listChanged' => 'invalid'], // Invalid boolean value
        ];

        $serverCapabilities = [
            'tools' => ['listChanged' => true],
        ];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        // Should use safe default (false) instead of server value when client value is invalid
        $this->assertFalse($negotiated['tools']['listChanged']);
    }

    #[Test]
    public function it_handles_non_array_capability_values(): void
    {
        Log::shouldReceive('debug')->once();

        $clientCapabilities = [
            'tools' => true, // Non-array value
            'resources' => false,
        ];

        $serverCapabilities = [
            'tools' => ['listChanged' => true],
            'resources' => ['subscribe' => true],
        ];

        $negotiated = $this->negotiator->negotiate($clientCapabilities, $serverCapabilities);

        $this->assertArrayHasKey('tools', $negotiated);
        $this->assertArrayHasKey('resources', $negotiated);
    }

    #[Test]
    #[DataProvider('negotiationScenarioProvider')]
    public function it_negotiates_various_scenarios(
        array $clientCaps,
        array $serverCaps,
        string $capability,
        string $feature,
        $expectedValue
    ): void {
        Log::shouldReceive('debug')->once();

        $negotiated = $this->negotiator->negotiate($clientCaps, $serverCaps);

        if ($expectedValue === null) {
            $this->assertArrayNotHasKey($feature, $negotiated[$capability] ?? []);
        } else {
            $this->assertSame($expectedValue, $negotiated[$capability][$feature]);
        }
    }

    public static function negotiationScenarioProvider(): array
    {
        return [
            'both support and enable' => [
                ['tools' => ['listChanged' => true]],
                ['tools' => ['listChanged' => true]],
                'tools',
                'listChanged',
                true,
            ],
            'client disables server feature' => [
                ['tools' => ['listChanged' => false]],
                ['tools' => ['listChanged' => true]],
                'tools',
                'listChanged',
                false,
            ],
            'server disables client feature' => [
                ['tools' => ['listChanged' => true]],
                ['tools' => ['listChanged' => false]],
                'tools',
                'listChanged',
                false,
            ],
            'client doesnt specify feature' => [
                ['tools' => []],
                ['tools' => ['listChanged' => true]],
                'tools',
                'listChanged',
                true,
            ],
        ];
    }

    #[Test]
    public function it_merges_array_features(): void
    {
        Log::shouldReceive('debug')->once();

        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->negotiator);
        $method = $reflection->getMethod('negotiateFeature');
        $method->setAccessible(true);

        $serverValue = ['option1' => 'value1'];
        $clientValue = ['option2' => 'value2'];

        $result = $method->invoke($this->negotiator, 'logging', 'config', $serverValue, $clientValue);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('option1', $result);
        $this->assertArrayHasKey('option2', $result);
    }

    #[Test]
    public function it_checks_capability_support(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->negotiator);
        $method = $reflection->getMethod('canSupportCapability');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->negotiator, 'tools'));
        $this->assertTrue($method->invoke($this->negotiator, 'resources'));
        $this->assertTrue($method->invoke($this->negotiator, 'prompts'));
        $this->assertTrue($method->invoke($this->negotiator, 'logging'));
        $this->assertFalse($method->invoke($this->negotiator, 'unknown'));
    }

    #[Test]
    public function it_checks_feature_support(): void
    {
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($this->negotiator);
        $method = $reflection->getMethod('canSupportFeature');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($this->negotiator, 'tools', 'listChanged'));
        $this->assertTrue($method->invoke($this->negotiator, 'resources', 'subscribe'));
        $this->assertTrue($method->invoke($this->negotiator, 'resources', 'listChanged'));
        $this->assertFalse($method->invoke($this->negotiator, 'tools', 'unknown'));
        $this->assertFalse($method->invoke($this->negotiator, 'unknown', 'feature'));
    }
}
