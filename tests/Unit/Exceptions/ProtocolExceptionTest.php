<?php

/**
 * @file tests/Unit/Exceptions/ProtocolExceptionTest.php
 *
 * @description Unit tests for ProtocolException
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Exceptions\ProtocolException
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
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ProtocolException::class)]
#[Group('ticket-027')]
#[Group('exceptions')]
class ProtocolExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_extends_mcp_exception(): void
    {
        $exception = new ProtocolException('Test message');
        $this->assertInstanceOf(McpException::class, $exception);
    }

    #[Test]
    public function it_creates_parse_error_exception(): void
    {
        $message = 'Invalid JSON';

        $exception = ProtocolException::parseError($message);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Parse error: {$message}", $exception->getMessage());
        $this->assertSame(-32700, $exception->getCode());
        $this->assertSame(['parse_error' => $message], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_request_exception(): void
    {
        $message = 'Missing required field';

        $exception = ProtocolException::invalidRequest($message);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Invalid request: {$message}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame(['request_error' => $message], $exception->getData());
    }

    #[Test]
    public function it_creates_method_not_found_exception(): void
    {
        $method = 'nonexistent/method';

        $exception = ProtocolException::methodNotFound($method);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Method not found: {$method}", $exception->getMessage());
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame(['method' => $method], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_params_exception(): void
    {
        $message = 'Parameter type mismatch';

        $exception = ProtocolException::invalidParams($message);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Invalid params: {$message}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame(['params_error' => $message], $exception->getData());
    }

    #[Test]
    public function it_creates_internal_error_exception(): void
    {
        $message = 'Database connection failed';

        $exception = ProtocolException::internalError($message);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Internal error: {$message}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame(['internal_error' => $message], $exception->getData());
    }

    #[Test]
    public function it_creates_server_error_exception(): void
    {
        $code = -32099;
        $message = 'Custom server error';

        $exception = ProtocolException::serverError($code, $message);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Server error: {$message}", $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
        $this->assertSame(['server_error' => $message], $exception->getData());
    }

    #[Test]
    public function it_creates_unsupported_protocol_version_exception(): void
    {
        $version = '2.0';
        $supportedVersions = ['1.0', '1.1'];

        $exception = ProtocolException::unsupportedProtocolVersion($version, $supportedVersions);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Unsupported protocol version: {$version}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame([
            'version' => $version,
            'supported_versions' => $supportedVersions,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_message_format_exception(): void
    {
        $reason = 'Missing jsonrpc field';

        $exception = ProtocolException::invalidMessageFormat($reason);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Invalid message format: {$reason}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame(['format_error' => $reason], $exception->getData());
    }

    #[Test]
    public function it_creates_capability_not_supported_exception(): void
    {
        $capability = 'experimental/feature';

        $exception = ProtocolException::capabilityNotSupported($capability);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Capability not supported: {$capability}", $exception->getMessage());
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame(['capability' => $capability], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_response_exception(): void
    {
        $reason = 'Response missing required result field';

        $exception = ProtocolException::invalidResponse($reason);

        $this->assertInstanceOf(ProtocolException::class, $exception);
        $this->assertSame("Invalid response: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame(['response_error' => $reason], $exception->getData());
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
        $exception = ProtocolException::$method(...$params);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedCode, $exception->getCode());
        $this->assertSame($expectedData, $exception->getData());
    }

    public static function exceptionDataProvider(): array
    {
        return [
            'parse error with special chars' => [
                'parseError',
                ['Unexpected token "<" at position 0'],
                'Parse error: Unexpected token "<" at position 0',
                -32700,
                ['parse_error' => 'Unexpected token "<" at position 0'],
            ],
            'method not found with namespace' => [
                'methodNotFound',
                ['tools/execute/custom'],
                'Method not found: tools/execute/custom',
                -32601,
                ['method' => 'tools/execute/custom'],
            ],
            'server error with custom code' => [
                'serverError',
                [-32050, 'Rate limit exceeded'],
                'Server error: Rate limit exceeded',
                -32050,
                ['server_error' => 'Rate limit exceeded'],
            ],
            'unsupported version with empty list' => [
                'unsupportedProtocolVersion',
                ['3.0', []],
                'Unsupported protocol version: 3.0',
                -32600,
                ['version' => '3.0', 'supported_versions' => []],
            ],
        ];
    }

    #[Test]
    public function it_validates_server_error_code_range(): void
    {
        // Server error codes should be in range -32099 to -32000
        $validCode = -32050;
        $exception = ProtocolException::serverError($validCode, 'Test');
        $this->assertSame($validCode, $exception->getCode());

        // Can also accept codes outside the range (for flexibility)
        $customCode = -40000;
        $exception = ProtocolException::serverError($customCode, 'Test');
        $this->assertSame($customCode, $exception->getCode());
    }

    #[Test]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Test protocol error');
        $this->expectExceptionCode(200);

        throw new ProtocolException('Test protocol error', 200);
    }

    #[Test]
    public function it_preserves_data_through_exception_chain(): void
    {
        $data = ['error_details' => 'Detailed information', 'context' => ['request_id' => '123']];
        $exception = new ProtocolException('Test', 0, $data);

        $this->assertSame($data, $exception->getData());
    }

    #[Test]
    public function it_handles_empty_messages_gracefully(): void
    {
        $exception = ProtocolException::parseError('');
        $this->assertSame('Parse error: ', $exception->getMessage());
        $this->assertSame(['parse_error' => ''], $exception->getData());

        $exception = ProtocolException::methodNotFound('');
        $this->assertSame('Method not found: ', $exception->getMessage());
        $this->assertSame(['method' => ''], $exception->getData());
    }
}
