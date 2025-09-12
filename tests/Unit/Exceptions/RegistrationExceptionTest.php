<?php

/**
 * @file tests/Unit/Exceptions/RegistrationExceptionTest.php
 *
 * @description Unit tests for RegistrationException
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Exceptions\RegistrationException
 *
 * @epic TESTING-027 - Comprehensive Testing Implementation
 *
 * @ticket TESTING-027-Exceptions
 *
 * @traceability docs/Tickets/027-TestingComprehensive.md
 *
 * @testType Unit
 *
 * @testTarget Exception Handling
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

namespace JTD\LaravelMCP\Tests\Unit\Exceptions;

use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Exceptions\RegistrationException;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RegistrationException::class)]
#[Group('ticket-027')]
#[Group('exceptions')]
class RegistrationExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_extends_mcp_exception(): void
    {
        $exception = new RegistrationException('Test message');
        $this->assertInstanceOf(McpException::class, $exception);
    }

    #[Test]
    public function it_creates_duplicate_component_exception(): void
    {
        $type = 'tool';
        $name = 'calculator';

        $exception = RegistrationException::duplicateComponent($type, $name);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Component already registered: {$type}/{$name}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'component_name' => $name,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_component_not_found_exception(): void
    {
        $type = 'resource';
        $name = 'users';

        $exception = RegistrationException::componentNotFound($type, $name);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Component not found: {$type}/{$name}", $exception->getMessage());
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'component_name' => $name,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_component_type_exception(): void
    {
        $type = 'invalid';
        $validTypes = ['tool', 'resource', 'prompt'];

        $exception = RegistrationException::invalidComponentType($type, $validTypes);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Invalid component type: {$type}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'valid_types' => $validTypes,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_component_exception(): void
    {
        $type = 'tool';
        $reason = 'Missing required execute method';

        $exception = RegistrationException::invalidComponent($type, $reason);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Invalid {$type}: {$reason}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_registration_failed_exception(): void
    {
        $type = 'prompt';
        $name = 'greeting';
        $reason = 'Database connection failed';

        $exception = RegistrationException::registrationFailed($type, $name, $reason);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Failed to register {$type}/{$name}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'component_name' => $name,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_unregistration_failed_exception(): void
    {
        $type = 'resource';
        $name = 'files';
        $reason = 'Component is in use';

        $exception = RegistrationException::unregistrationFailed($type, $name, $reason);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Failed to unregister {$type}/{$name}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'component_name' => $name,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_discovery_failed_exception(): void
    {
        $path = '/app/Mcp/Tools';
        $reason = 'Directory not readable';

        $exception = RegistrationException::discoveryFailed($path, $reason);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Component discovery failed at {$path}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'discovery_path' => $path,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_handler_exception(): void
    {
        $type = 'tool';
        $name = 'calculator';
        $reason = 'Handler must be callable or class name';

        $exception = RegistrationException::invalidHandler($type, $name, $reason);

        $this->assertInstanceOf(RegistrationException::class, $exception);
        $this->assertSame("Invalid handler for {$type}/{$name}: {$reason}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame([
            'component_type' => $type,
            'component_name' => $name,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    #[DataProvider('exceptionDataProvider')]
    public function it_creates_exceptions_with_correct_data(
        string $method,
        array $params,
        string $expectedMessage,
        int $expectedCode,
        array $expectedData
    ): void {
        $exception = RegistrationException::$method(...$params);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedCode, $exception->getCode());
        $this->assertSame($expectedData, $exception->getData());
    }

    public static function exceptionDataProvider(): array
    {
        return [
            'duplicate with special chars' => [
                'duplicateComponent',
                ['tool', 'my-tool-123'],
                'Component already registered: tool/my-tool-123',
                -32602,
                ['component_type' => 'tool', 'component_name' => 'my-tool-123'],
            ],
            'not found with namespace' => [
                'componentNotFound',
                ['resource', 'api/users'],
                'Component not found: resource/api/users',
                -32601,
                ['component_type' => 'resource', 'component_name' => 'api/users'],
            ],
            'invalid type with empty valid list' => [
                'invalidComponentType',
                ['custom', []],
                'Invalid component type: custom',
                -32602,
                ['component_type' => 'custom', 'valid_types' => []],
            ],
            'discovery with nested path' => [
                'discoveryFailed',
                ['/app/Mcp/Custom/Tools', 'Class not found'],
                'Component discovery failed at /app/Mcp/Custom/Tools: Class not found',
                -32603,
                ['discovery_path' => '/app/Mcp/Custom/Tools', 'reason' => 'Class not found'],
            ],
        ];
    }

    #[Test]
    public function it_handles_component_types_consistently(): void
    {
        $types = ['tool', 'resource', 'prompt', 'custom-type'];

        foreach ($types as $type) {
            $exception = RegistrationException::duplicateComponent($type, 'test');
            $this->assertStringContainsString($type, $exception->getMessage());
            $this->assertSame($type, $exception->getData()['component_type']);
        }
    }

    #[Test]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(RegistrationException::class);
        $this->expectExceptionMessage('Test registration error');
        $this->expectExceptionCode(300);

        throw new RegistrationException('Test registration error', 300);
    }

    #[Test]
    public function it_preserves_data_through_exception_chain(): void
    {
        $data = [
            'component_type' => 'tool',
            'component_name' => 'test',
            'metadata' => ['version' => '1.0'],
        ];
        $exception = new RegistrationException('Test', 0, $data);

        $this->assertSame($data, $exception->getData());
    }

    #[Test]
    public function it_handles_empty_values_gracefully(): void
    {
        $exception = RegistrationException::duplicateComponent('', '');
        $this->assertSame('Component already registered: /', $exception->getMessage());

        $exception = RegistrationException::invalidComponent('', '');
        $this->assertSame('Invalid : ', $exception->getMessage());

        $exception = RegistrationException::discoveryFailed('', '');
        $this->assertSame('Component discovery failed at : ', $exception->getMessage());
    }
}
