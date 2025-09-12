<?php

/**
 * @file tests/Unit/Exceptions/ConfigurationExceptionTest.php
 *
 * @description Unit tests for ConfigurationException
 *
 * @category Testing
 *
 * @coverage \JTD\LaravelMCP\Exceptions\ConfigurationException
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

use JTD\LaravelMCP\Exceptions\ConfigurationException;
use JTD\LaravelMCP\Exceptions\McpException;
use JTD\LaravelMCP\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(ConfigurationException::class)]
#[Group('ticket-027')]
#[Group('exceptions')]
class ConfigurationExceptionTest extends UnitTestCase
{
    #[Test]
    public function it_extends_mcp_exception(): void
    {
        $exception = new ConfigurationException('Test message');
        $this->assertInstanceOf(McpException::class, $exception);
    }

    #[Test]
    public function it_creates_validation_failed_exception(): void
    {
        $errors = [
            'field1' => 'Field 1 is required',
            'field2' => 'Field 2 must be a string',
        ];

        $exception = ConfigurationException::validationFailed($errors);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame('Configuration validation failed', $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame(['validation_errors' => $errors], $exception->getData());
    }

    #[Test]
    public function it_creates_unsupported_client_exception(): void
    {
        $client = 'unsupported-client';
        $supportedClients = ['claude-desktop', 'vscode'];

        $exception = ConfigurationException::unsupportedClient($client, $supportedClients);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Unsupported client: {$client}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame([
            'client' => $client,
            'supported_clients' => $supportedClients,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_unsupported_os_exception(): void
    {
        $os = 'BeOS';

        $exception = ConfigurationException::unsupportedOS($os);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Unsupported operating system: {$os}", $exception->getMessage());
        $this->assertSame(-32600, $exception->getCode());
        $this->assertSame(['operating_system' => $os], $exception->getData());
    }

    #[Test]
    public function it_creates_config_file_error_exception(): void
    {
        $path = '/etc/mcp/config.json';
        $reason = 'Permission denied';

        $exception = ConfigurationException::configFileError($path, $reason);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Configuration file error at {$path}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'config_path' => $path,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_config_directory_error_exception(): void
    {
        $directory = '/etc/mcp';
        $reason = 'Directory does not exist';

        $exception = ConfigurationException::configDirectoryError($directory, $reason);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Configuration directory error at {$directory}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'config_directory' => $directory,
            'reason' => $reason,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_generator_not_found_exception(): void
    {
        $client = 'unknown-client';

        $exception = ConfigurationException::generatorNotFound($client);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Configuration generator not found for client: {$client}", $exception->getMessage());
        $this->assertSame(-32601, $exception->getCode());
        $this->assertSame(['client' => $client], $exception->getData());
    }

    #[Test]
    public function it_creates_invalid_structure_exception(): void
    {
        $client = 'claude-desktop';
        $expectedStructure = 'Expected array with "servers" key';

        $exception = ConfigurationException::invalidStructure($client, $expectedStructure);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Invalid configuration structure for {$client}: {$expectedStructure}", $exception->getMessage());
        $this->assertSame(-32602, $exception->getCode());
        $this->assertSame([
            'client' => $client,
            'expected_structure' => $expectedStructure,
        ], $exception->getData());
    }

    #[Test]
    public function it_creates_merge_error_exception(): void
    {
        $client = 'vscode';
        $reason = 'Conflicting configuration keys';

        $exception = ConfigurationException::mergeError($client, $reason);

        $this->assertInstanceOf(ConfigurationException::class, $exception);
        $this->assertSame("Configuration merge failed for {$client}: {$reason}", $exception->getMessage());
        $this->assertSame(-32603, $exception->getCode());
        $this->assertSame([
            'client' => $client,
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
        $exception = ConfigurationException::$method(...$params);

        $this->assertSame($expectedMessage, $exception->getMessage());
        $this->assertSame($expectedCode, $exception->getCode());
        $this->assertSame($expectedData, $exception->getData());
    }

    public static function exceptionDataProvider(): array
    {
        return [
            'validation with empty errors' => [
                'validationFailed',
                [[]],
                'Configuration validation failed',
                -32602,
                ['validation_errors' => []],
            ],
            'unsupported client without supported list' => [
                'unsupportedClient',
                ['test-client', []],
                'Unsupported client: test-client',
                -32600,
                ['client' => 'test-client', 'supported_clients' => []],
            ],
            'config file with special characters' => [
                'configFileError',
                ['/path/with spaces/config.json', 'Invalid JSON'],
                'Configuration file error at /path/with spaces/config.json: Invalid JSON',
                -32603,
                ['config_path' => '/path/with spaces/config.json', 'reason' => 'Invalid JSON'],
            ],
        ];
    }

    #[Test]
    public function it_can_be_thrown_and_caught(): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Test configuration error');
        $this->expectExceptionCode(100);

        throw new ConfigurationException('Test configuration error', 100);
    }

    #[Test]
    public function it_preserves_data_through_exception_chain(): void
    {
        $data = ['key' => 'value', 'nested' => ['item' => 'data']];
        $exception = new ConfigurationException('Test', 0, $data);

        $this->assertSame($data, $exception->getData());
    }
}
