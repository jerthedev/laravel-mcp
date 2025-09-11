<?php

namespace JTD\LaravelMCP\Tests\Unit\Server\Handlers;

use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Exceptions\ProtocolException;
use JTD\LaravelMCP\Server\Handlers\BaseHandler;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for BaseHandler abstract class.
 *
 * This test suite ensures the BaseHandler provides proper request validation,
 * error handling, response formatting, and logging functionality that serves
 * as the foundation for all MCP message handlers.
 *
 * @epic 009-McpServerHandlers
 *
 * @spec docs/Specs/009-McpServerHandlers.md
 *
 * @ticket 009-McpServerHandlers.md
 *
 * @sprint Sprint-2
 */
#[CoversClass(BaseHandler::class)]
class BaseHandlerTest extends TestCase
{
    private TestableBaseHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new TestableBaseHandler;
    }

    #[Test]
    public function constructor_sets_handler_name_and_debug_mode(): void
    {
        $handler = new TestableBaseHandler(true);

        $this->assertSame(TestableBaseHandler::class, $handler->getHandlerName());
        $this->assertTrue($handler->isDebug());
    }

    #[Test]
    public function constructor_defaults_to_non_debug_mode(): void
    {
        $handler = new TestableBaseHandler;

        $this->assertFalse($handler->isDebug());
    }

    #[Test]
    public function set_debug_updates_debug_mode(): void
    {
        $this->assertFalse($this->handler->isDebug());

        $this->handler->setDebug(true);
        $this->assertTrue($this->handler->isDebug());

        $this->handler->setDebug(false);
        $this->assertFalse($this->handler->isDebug());
    }

    #[Test]
    public function validate_request_passes_with_valid_parameters(): void
    {
        $params = ['name' => 'test', 'value' => 123];
        $rules = ['name' => 'required|string', 'value' => 'integer'];

        // Should not throw exception
        $this->handler->testValidateRequest($params, $rules);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_passes_with_empty_rules(): void
    {
        $params = ['name' => 'test'];
        $rules = [];

        // Should not throw exception
        $this->handler->testValidateRequest($params, $rules);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_request_throws_protocol_exception_with_invalid_parameters(): void
    {
        $params = ['name' => 123]; // Should be string
        $rules = ['name' => 'required|string'];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);
        $this->expectExceptionMessage('Invalid parameters:');

        $this->handler->testValidateRequest($params, $rules);
    }

    #[Test]
    public function validate_request_throws_protocol_exception_with_missing_required_parameters(): void
    {
        $params = [];
        $rules = ['name' => 'required|string'];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);

        $this->handler->testValidateRequest($params, $rules);
    }

    #[Test]
    public function validate_request_uses_custom_error_messages(): void
    {
        $params = ['name' => 123];
        $rules = ['name' => 'required|string'];
        $messages = ['name.string' => 'Custom error message'];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid parameters: Custom error message');

        $this->handler->testValidateRequest($params, $rules, $messages);
    }

    #[Test]
    public function validate_required_params_passes_with_all_required_present(): void
    {
        $params = ['name' => 'test', 'value' => 123, 'extra' => 'optional'];
        $required = ['name', 'value'];

        // Should not throw exception
        $this->handler->testValidateRequiredParams($params, $required);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_required_params_passes_with_empty_required_list(): void
    {
        $params = ['name' => 'test'];
        $required = [];

        // Should not throw exception
        $this->handler->testValidateRequiredParams($params, $required);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_required_params_throws_protocol_exception_with_missing_parameters(): void
    {
        $params = ['name' => 'test'];
        $required = ['name', 'missing', 'also_missing'];

        $this->expectException(ProtocolException::class);
        $this->expectExceptionCode(-32602);
        $this->expectExceptionMessage('Missing required parameters: missing, also_missing');

        $this->handler->testValidateRequiredParams($params, $required);
    }

    #[Test]
    public function create_success_response_returns_result_without_metadata(): void
    {
        $result = ['data' => 'test', 'count' => 5];

        $response = $this->handler->testCreateSuccessResponse($result);

        $this->assertSame($result, $response);
        $this->assertArrayNotHasKey('_meta', $response);
    }

    #[Test]
    public function create_success_response_adds_metadata_when_requested(): void
    {
        $result = ['data' => 'test'];
        $context = ['add_metadata' => true, 'request_id' => 'test-123'];

        $response = $this->handler->testCreateSuccessResponse($result, $context);

        $this->assertSame('test', $response['data']);
        $this->assertArrayHasKey('_meta', $response);
        $this->assertSame(TestableBaseHandler::class, $response['_meta']['handler']);
        $this->assertSame('test-123', $response['_meta']['request_id']);
        $this->assertArrayHasKey('timestamp', $response['_meta']);
    }

    #[Test]
    public function create_error_response_returns_proper_structure(): void
    {
        $message = 'Test error';
        $code = -32603;

        $response = $this->handler->testCreateErrorResponse($message, $code);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame($code, $response['error']['code']);
        $this->assertSame($message, $response['error']['message']);
        $this->assertArrayNotHasKey('data', $response['error']);
    }

    #[Test]
    public function create_error_response_includes_data_when_provided(): void
    {
        $message = 'Test error';
        $code = -32603;
        $data = ['extra' => 'info'];

        $response = $this->handler->testCreateErrorResponse($message, $code, $data);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame($code, $response['error']['code']);
        $this->assertSame($message, $response['error']['message']);
        $this->assertSame($data, $response['error']['data']);
    }

    #[Test]
    public function supports_method_returns_true_for_supported_methods(): void
    {
        $this->assertTrue($this->handler->supportsMethod('test/method'));
        $this->assertTrue($this->handler->supportsMethod('another/method'));
    }

    #[Test]
    public function supports_method_returns_false_for_unsupported_methods(): void
    {
        $this->assertFalse($this->handler->supportsMethod('unsupported/method'));
        $this->assertFalse($this->handler->supportsMethod('test'));
    }

    #[Test]
    public function get_supported_methods_returns_expected_methods(): void
    {
        $expected = ['test/method', 'another/method'];

        $this->assertSame($expected, $this->handler->getSupportedMethods());
    }

    #[Test]
    public function sanitize_for_logging_removes_sensitive_keys(): void
    {
        $params = [
            'username' => 'test_user',
            'password' => 'secret123',
            'token' => 'Bearer xyz',
            'secret' => 'classified',
            'key' => 'api_key',
            'auth' => 'auth_data',
            'credential' => 'creds',
            'normal_data' => 'visible',
        ];

        $sanitized = $this->handler->testSanitizeForLogging($params);

        $this->assertSame('test_user', $sanitized['username']);
        $this->assertSame('visible', $sanitized['normal_data']);
        $this->assertSame('[REDACTED]', $sanitized['password']);
        $this->assertSame('[REDACTED]', $sanitized['token']);
        $this->assertSame('[REDACTED]', $sanitized['secret']);
        $this->assertSame('[REDACTED]', $sanitized['key']);
        $this->assertSame('[REDACTED]', $sanitized['auth']);
        $this->assertSame('[REDACTED]', $sanitized['credential']);
    }

    #[Test]
    public function sanitize_for_logging_handles_empty_array(): void
    {
        $sanitized = $this->handler->testSanitizeForLogging([]);

        $this->assertSame([], $sanitized);
    }

    #[Test]
    public function handle_exception_preserves_protocol_exception_details(): void
    {
        $originalException = new ProtocolException('Custom error', -32602, 'test/method', null, ['detail' => 'info']);

        $response = $this->handler->testHandleException($originalException, 'test/method');

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32602, $response['error']['code']);
        $this->assertSame('Custom error', $response['error']['message']);
        $this->assertSame(['detail' => 'info'], $response['error']['data']);
    }

    #[Test]
    public function handle_exception_converts_generic_exception_to_internal_error(): void
    {
        $originalException = new \RuntimeException('Unexpected error');

        $response = $this->handler->testHandleException($originalException, 'test/method');

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32603, $response['error']['code']);
        $this->assertSame('Internal server error', $response['error']['message']);
        $this->assertNull($response['error']['data'] ?? null);
    }

    #[Test]
    public function handle_exception_includes_debug_data_when_debug_enabled(): void
    {
        $this->handler->setDebug(true);
        $originalException = new \RuntimeException('Unexpected error');

        $response = $this->handler->testHandleException($originalException, 'test/method');

        $this->assertArrayHasKey('error', $response);
        $this->assertArrayHasKey('data', $response['error']);
        $this->assertArrayHasKey('exception_type', $response['error']['data']);
        $this->assertArrayHasKey('file', $response['error']['data']);
        $this->assertArrayHasKey('line', $response['error']['data']);
        $this->assertArrayHasKey('trace', $response['error']['data']);
        $this->assertSame(\RuntimeException::class, $response['error']['data']['exception_type']);
    }

    #[Test]
    #[DataProvider('formatContentProvider')]
    public function format_content_handles_different_types_correctly($content, string $type, array $expected): void
    {
        $result = $this->handler->testFormatContent($content, $type);

        $this->assertSame($expected, $result);
    }

    public static function formatContentProvider(): array
    {
        return [
            'string as text' => [
                'Simple text',
                'text',
                ['type' => 'text', 'text' => 'Simple text'],
            ],
            'array as text' => [
                ['key' => 'value'],
                'text',
                ['type' => 'text', 'text' => '{"key":"value"}'],
            ],
            'string as json' => [
                'Simple text',
                'json',
                ['type' => 'text', 'text' => '"Simple text"'],
            ],
            'array as json' => [
                ['key' => 'value'],
                'json',
                ['type' => 'text', 'text' => "{\n    \"key\": \"value\"\n}"],
            ],
            'resource type' => [
                'resource-content',
                'resource',
                ['type' => 'resource', 'resource' => 'resource-content'],
            ],
            'unknown type defaults to text' => [
                'content',
                'unknown',
                ['type' => 'text', 'text' => 'content'],
            ],
            'number as text' => [
                42,
                'text',
                ['type' => 'text', 'text' => '42'],
            ],
        ];
    }

    #[Test]
    public function logging_methods_use_handler_name_prefix(): void
    {
        Log::spy();

        $this->handler->testLogInfo('Test info message', ['key' => 'value']);
        $this->handler->testLogDebug('Test debug message'); // Should not log when debug disabled
        $this->handler->testLogWarning('Test warning message');
        $this->handler->testLogError('Test error message');

        Log::shouldHaveReceived('info')
            ->once()
            ->with('['.TestableBaseHandler::class.'] Test info message', ['key' => 'value']);

        Log::shouldNotHaveReceived('debug'); // Debug disabled

        Log::shouldHaveReceived('warning')
            ->once()
            ->with('['.TestableBaseHandler::class.'] Test warning message', []);

        Log::shouldHaveReceived('error')
            ->once()
            ->with('['.TestableBaseHandler::class.'] Test error message', []);
    }

    #[Test]
    public function debug_logging_works_when_debug_enabled(): void
    {
        Log::spy();
        $this->handler->setDebug(true);

        $this->handler->testLogDebug('Test debug message', ['debug' => 'data']);

        Log::shouldHaveReceived('debug')
            ->once()
            ->with('['.TestableBaseHandler::class.'] Test debug message', ['debug' => 'data']);
    }

    #[Test]
    public function constructor_logs_initialization_when_debug_enabled(): void
    {
        Log::spy();

        new TestableBaseHandler(true);

        Log::shouldHaveReceived('debug')
            ->once()
            ->with('Initializing '.TestableBaseHandler::class);
    }

    #[Test]
    public function constructor_does_not_log_when_debug_disabled(): void
    {
        Log::spy();

        new TestableBaseHandler(false);

        Log::shouldNotHaveReceived('debug');
    }
}

/**
 * Testable implementation of BaseHandler for testing abstract functionality.
 */
class TestableBaseHandler extends BaseHandler
{
    public function handle(string $method, array $params, array $context = []): array
    {
        return ['handled' => true, 'method' => $method];
    }

    public function getSupportedMethods(): array
    {
        return ['test/method', 'another/method'];
    }

    // Expose protected methods for testing
    public function testValidateRequest(array $params, array $rules, array $messages = []): void
    {
        $this->validateRequest($params, $rules, $messages);
    }

    public function testValidateRequiredParams(array $params, array $required): void
    {
        $this->validateRequiredParams($params, $required);
    }

    public function testCreateSuccessResponse(array $result, array $context = []): array
    {
        return $this->createSuccessResponse($result, $context);
    }

    public function testCreateErrorResponse(string $message, int $code = -32603, $data = null): array
    {
        return $this->createErrorResponse($message, $code, $data);
    }

    public function testSanitizeForLogging(array $params): array
    {
        return $this->sanitizeForLogging($params);
    }

    public function testHandleException(\Throwable $e, string $method, array $context = []): array
    {
        return $this->handleException($e, $method, $context);
    }

    public function testFormatContent($content, string $type = 'text'): array
    {
        return $this->formatContent($content, $type);
    }

    public function testLogInfo(string $message, array $context = []): void
    {
        $this->logInfo($message, $context);
    }

    public function testLogDebug(string $message, array $context = []): void
    {
        $this->logDebug($message, $context);
    }

    public function testLogWarning(string $message, array $context = []): void
    {
        $this->logWarning($message, $context);
    }

    public function testLogError(string $message, array $context = []): void
    {
        $this->logError($message, $context);
    }
}
