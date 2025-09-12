<?php

/**
 * @file tests/Unit/Exceptions/TransportExceptionTest.php
 *
 * @description Unit tests for TransportException
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Exceptions\TransportException
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
use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(TransportException::class)]
#[Group('ticket-027')]
#[Group('exceptions')]
class TransportExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_extends_mcp_exception(): void
    {
        $exception = new TransportException('Test message');
        $this->assertInstanceOf(McpException::class, $exception);
    }

    #[Test]
    public function it_creates_connection_failed_exception(): void
    {
        $transport = 'http';
        $reason = 'Connection timeout';

        $exception = TransportException::connectionFailed($transport, $reason);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Transport connection failed ({$transport}): {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_send_failed_exception(): void
    {
        $transport = 'stdio';
        $reason = 'Broken pipe';

        $exception = TransportException::sendFailed($transport, $reason);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Failed to send message ({$transport}): {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_receive_failed_exception(): void
    {
        $transport = 'websocket';
        $reason = 'Invalid frame format';

        $exception = TransportException::receiveFailed($transport, $reason);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Failed to receive message ({$transport}): {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_unsupported_transport_exception(): void
    {
        $transport = 'grpc';
        $supportedTransports = ['http', 'stdio', 'websocket'];

        $exception = TransportException::unsupportedTransport($transport, $supportedTransports);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Unsupported transport: {$transport}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'supported_transports' => $supportedTransports,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_transport_not_configured_exception(): void
    {
        $transport = 'redis';

        $exception = TransportException::transportNotConfigured($transport);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Transport not configured: {$transport}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame(['transport' => $transport], $exception->getData());
    }

    #[Test]
    public function it_creates_authentication_failed_exception(): void
    {
        $transport = 'http';
        $reason = 'Invalid API key';

        $exception = TransportException::authenticationFailed($transport, $reason);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Authentication failed ({$transport}): {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_timeout_exception(): void
    {
        $transport = 'http';
        $timeout = 30;

        $exception = TransportException::timeout($transport, $timeout);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Transport timeout ({$transport}): {$timeout} seconds", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'timeout' => $timeout,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_rate_limit_exceeded_exception(): void
    {
        $transport = 'http';
        $limit = 100;
        $window = 60;

        $exception = TransportException::rateLimitExceeded($transport, $limit, $window);

        $this->assertInstanceOf(TransportException::class, $exception);
        $this->assertSame("Rate limit exceeded ({$transport}): {$limit} requests per {$window} seconds", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'transport' => $transport,
            'limit' => $limit,
            'window' => $window,
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
        $exception = TransportException::$method(...$params);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedCode, $exception->getCode());
        $this->assertSame($expectedData, $exception->getData());
    }

    public static function exceptionDataProvider(): array
    {
        return [
            'connection with SSL error' => [
                'connectionFailed',
                ['https', 'SSL certificate verification failed'],
                'Transport connection failed (https): SSL certificate verification failed',
                -32603,
                ['transport' => 'https', 'reason' => 'SSL certificate verification failed'],
            ],
            'send with encoding error' => [
                'sendFailed',
                ['stdio', 'Invalid UTF-8 encoding'],
                'Failed to send message (stdio): Invalid UTF-8 encoding',
                -32603,
                ['transport' => 'stdio', 'reason' => 'Invalid UTF-8 encoding'],
            ],
            'unsupported with empty list' => [
                'unsupportedTransport',
                ['custom', []],
                'Unsupported transport: custom',
                -32600,
                ['transport' => 'custom', 'supported_transports' => []],
            ],
            'timeout with large value' => [
                'timeout',
                ['websocket', 3600],
                'Transport timeout (websocket): 3600 seconds',
                -32603,
                ['transport' => 'websocket', 'timeout' => 3600],
            ],
            'rate limit with high values' => [
                'rateLimitExceeded',
                ['api', 10000, 3600],
                'Rate limit exceeded (api): 10000 requests per 3600 seconds',
                -32603,
                ['transport' => 'api', 'limit' => 10000, 'window' => 3600],
            ],
        ];
    }

    #[Test]
    public function it_handles_different_transport_types(): void
    {
        $transports = ['http', 'https', 'stdio', 'websocket', 'tcp', 'unix'];

        foreach ($transports as $transport) {
            $exception = TransportException::connectionFailed($transport, 'test');
            $this->assertStringContainsString($transport, $exception->getMessage());
            $this->assertSame($transport, $exception->getData()['transport']);
        }
    }

    #[Test]
    public function it_formats_timeout_messages_correctly(): void
    {
        $exception = TransportException::timeout('http', 0);
        $this->assertSame('Transport timeout (http): 0 seconds', $exception->getMessage());

        $exception = TransportException::timeout('stdio', 1);
        $this->assertSame('Transport timeout (stdio): 1 seconds', $exception->getMessage());

        $exception = TransportException::timeout('websocket', 30);
        $this->assertSame('Transport timeout (websocket): 30 seconds', $exception->getMessage());
    }

    #[Test]
    public function it_formats_rate_limit_messages_correctly(): void
    {
        $exception = TransportException::rateLimitExceeded('http', 1, 1);
        $this->assertSame('Rate limit exceeded (http): 1 requests per 1 seconds', $exception->getMessage());

        $exception = TransportException::rateLimitExceeded('api', 60, 60);
        $this->assertSame('Rate limit exceeded (api): 60 requests per 60 seconds', $exception->getMessage());
    }

    #[Test]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Test transport error');
        $this->expectExceptionCode(400);

        throw new TransportException('Test transport error', 400);
    }

    #[Test]
    public function it_preserves_data_through_exception_chain(): void
    {
        $data = [
            'transport' => 'http',
            'endpoint' => 'https://api.example.com',
            'headers' => ['Authorization' => 'Bearer token'],
        ];
        $exception = new TransportException('Test', 0, $data);

        $this->assertSame($data, $exception->getData());
    }

    #[Test]
    public function it_handles_empty_values_gracefully(): void
    {
        $exception = TransportException::connectionFailed('', '');
        $this->assertSame('Transport connection failed (): ', $exception->getMessage());

        $exception = TransportException::transportNotConfigured('');
        $this->assertSame('Transport not configured: ', $exception->getMessage());

        $exception = TransportException::timeout('', 0);
        $this->assertSame('Transport timeout (): 0 seconds', $exception->getMessage());
    }

    #[Test]
    public function it_handles_negative_values_in_rate_limit(): void
    {
        // Should handle negative values gracefully (though they don't make logical sense)
        $exception = TransportException::rateLimitExceeded('http', -1, -60);
        $this->assertSame('Rate limit exceeded (http): -1 requests per -60 seconds', $exception->getMessage());
        $this->assertSame([
            'transport' => 'http',
            'limit' => -1,
            'window' => -60,
        ], $exception->getData());
    }
}
