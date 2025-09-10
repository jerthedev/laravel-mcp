<?php

namespace JTD\LaravelMCP\Tests\Unit\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use JTD\LaravelMCP\Traits\FormatsResponses;
use JTD\LaravelMCP\Traits\ManagesCapabilities;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use JTD\LaravelMCP\Tests\TestCase;

/**
 * Test file header as per standards.
 *
 * Epic: Base Classes
 * Sprint: Sprint 2
 * Ticket: BASECLASSES-015
 * URL: docs/Tickets/015-BaseClassesTraits.md
 * Dependencies: 014-BASECLASSESCORE
 *
 * @covers \JTD\LaravelMCP\Traits\FormatsResponses
 */
#[Group('unit')]
#[Group('traits')]
#[Group('ticket-015')]
#[Group('epic-baseclasses')]
class FormatsResponsesTest extends TestCase
{
    protected $formatter;

    protected $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a mock class that uses the trait
        $this->formatter = new class
        {
            use FormatsResponses, ManagesCapabilities;

            public function getName(): string
            {
                return 'test_component';
            }

            public function getDescription(): string
            {
                return 'Test component description';
            }

            public function getComponentType(): string
            {
                return 'test';
            }

            public function getCapabilities(): array
            {
                return ['execute', 'read'];
            }

            public function getSupportedOperations(): array
            {
                return ['execute', 'read'];
            }

            public function getCapabilityMetadata(): array
            {
                return [
                    'type' => 'test',
                    'capabilities' => ['execute', 'read'],
                ];
            }
        };

        $this->reflection = new \ReflectionClass($this->formatter);
    }

    /**
     * Call a protected method on the formatter.
     */
    protected function callMethod(string $methodName, array $arguments = [])
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->formatter, $arguments);
    }

    #[Test]
    public function it_formats_success_response(): void
    {
        $data = ['key' => 'value'];
        $response = $this->callMethod('formatSuccess', [$data]);

        $this->assertTrue($response['success']);
        $this->assertEquals($data, $response['data']);
        $this->assertArrayHasKey('timestamp', $response);
    }

    #[Test]
    public function it_formats_success_response_with_meta(): void
    {
        $data = ['key' => 'value'];
        $meta = ['version' => '1.0'];
        $response = $this->callMethod('formatSuccess', [$data, $meta]);

        $this->assertTrue($response['success']);
        $this->assertEquals($data, $response['data']);
        $this->assertEquals($meta, $response['meta']);
    }

    #[Test]
    public function it_formats_error_response(): void
    {
        $response = $this->callMethod('formatError', ['Test error', -32603, ['detail' => 'info']]);

        $this->assertFalse($response['success']);
        $this->assertEquals(-32603, $response['error']['code']);
        $this->assertEquals('Test error', $response['error']['message']);
        $this->assertEquals(['detail' => 'info'], $response['error']['data']);
    }

    #[Test]
    public function it_formats_tool_response(): void
    {
        $result = ['calculation' => 42];
        $response = $this->callMethod('formatToolResponse', [$result]);

        $this->assertArrayHasKey('content', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertEquals('test_component', $response['meta']['tool']);
        $this->assertArrayHasKey('executed_at', $response['meta']);
    }

    #[Test]
    public function it_formats_resource_read_response(): void
    {
        $data = ['id' => 1, 'name' => 'Test'];
        $uri = 'resource/1';
        $response = $this->callMethod('formatResourceReadResponse', [$data, $uri]);

        $this->assertArrayHasKey('contents', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertEquals($uri, $response['contents'][0]['uri']);
        $this->assertEquals('application/json', $response['contents'][0]['mimeType']);
    }

    #[Test]
    public function it_formats_resource_list_response(): void
    {
        $items = [
            ['id' => 1, 'name' => 'Item 1'],
            ['id' => 2, 'name' => 'Item 2'],
        ];
        $response = $this->callMethod('formatResourceListResponse', [$items]);

        $this->assertArrayHasKey('resources', $response);
        $this->assertArrayHasKey('meta', $response);
        $this->assertCount(2, $response['resources']);
        $this->assertEquals(2, $response['meta']['count']);
    }

    #[Test]
    public function it_formats_prompt_response(): void
    {
        $content = 'Generated prompt content';
        $response = $this->callMethod('formatPromptResponse', [$content]);

        $this->assertArrayHasKey('description', $response);
        $this->assertArrayHasKey('messages', $response);
        $this->assertEquals('Test component description', $response['description']);
        $this->assertEquals($content, $response['messages'][0]['content']['text']);
    }

    #[Test]
    public function it_formats_paginated_response(): void
    {
        $items = [['id' => 1], ['id' => 2]];
        $response = $this->callMethod('formatPaginatedResponse', [$items, 10, 5, 2]);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('pagination', $response);
        $this->assertEquals(10, $response['pagination']['total']);
        $this->assertEquals(5, $response['pagination']['per_page']);
        $this->assertEquals(2, $response['pagination']['current_page']);
        $this->assertEquals(2, $response['pagination']['last_page']);
    }

    #[Test]
    public function it_formats_json_rpc_response(): void
    {
        $result = ['data' => 'test'];
        $response = $this->callMethod('formatJsonRpcResponse', [$result, 123]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals($result, $response['result']);
        $this->assertEquals(123, $response['id']);
    }

    #[Test]
    public function it_formats_json_rpc_error_response(): void
    {
        $error = new \Exception('Test error', 500);
        $response = $this->callMethod('formatJsonRpcResponse', [$error, 123]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals(500, $response['error']['code']);
        $this->assertEquals('Test error', $response['error']['message']);
    }

    #[Test]
    public function it_converts_to_json_response(): void
    {
        $data = ['key' => 'value'];
        $response = $this->callMethod('toJsonResponse', [$data]);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($data, $response->getData(true));
    }

    #[Test]
    public function it_detects_mime_type_for_json(): void
    {
        $data = '{"key": "value"}';
        $mimeType = $this->callMethod('getMimeType', [$data]);

        $this->assertEquals('application/json', $mimeType);
    }

    #[Test]
    public function it_detects_mime_type_for_html(): void
    {
        $data = '<html><body>Test</body></html>';
        $mimeType = $this->callMethod('getMimeType', [$data]);

        $this->assertEquals('text/html', $mimeType);
    }

    #[Test]
    public function it_detects_mime_type_for_xml(): void
    {
        $data = '<?xml version="1.0"?><root>Test</root>';
        $mimeType = $this->callMethod('getMimeType', [$data]);

        $this->assertEquals('application/xml', $mimeType);
    }

    #[Test]
    public function it_detects_mime_type_for_plain_text(): void
    {
        $data = 'Plain text content';
        $mimeType = $this->callMethod('getMimeType', [$data]);

        $this->assertEquals('text/plain', $mimeType);
    }

    #[Test]
    public function it_formats_capabilities_response(): void
    {
        $response = $this->callMethod('formatCapabilitiesResponse', []);

        $this->assertArrayHasKey('capabilities', $response);
        $this->assertArrayHasKey('supported_operations', $response);
        $this->assertArrayHasKey('metadata', $response);
        $this->assertEquals(['execute', 'read'], $response['capabilities']);
    }

    #[Test]
    public function it_formats_validation_errors(): void
    {
        $errors = [
            'field1' => ['Field1 is required'],
            'field2' => ['Field2 must be a string'],
        ];
        $response = $this->callMethod('formatValidationErrors', [$errors]);

        $this->assertFalse($response['success']);
        $this->assertEquals(-32602, $response['error']['code']);
        $this->assertEquals('Validation failed', $response['error']['message']);
        $this->assertEquals($errors, $response['error']['data']['validation_errors']);
    }

    #[Test]
    public function it_strips_sensitive_data(): void
    {
        $data = [
            'username' => 'john',
            'password' => 'secret123',
            'api_key' => 'key123',
            'data' => [
                'token' => 'token123',
                'info' => 'public',
            ],
        ];

        $stripped = $this->callMethod('stripSensitiveData', [$data]);

        $this->assertEquals('john', $stripped['username']);
        $this->assertEquals('[REDACTED]', $stripped['password']);
        $this->assertEquals('[REDACTED]', $stripped['api_key']);
        $this->assertEquals('[REDACTED]', $stripped['data']['token']);
        $this->assertEquals('public', $stripped['data']['info']);
    }

    #[Test]
    public function it_adds_cors_headers_when_enabled(): void
    {
        Config::set('laravel-mcp.cors.enabled', true);
        Config::set('laravel-mcp.cors.allowed_origins', 'http://localhost');
        Config::set('laravel-mcp.cors.allowed_methods', ['POST', 'GET']);

        $response = ['data' => 'test'];
        $withCors = $this->callMethod('addCorsHeaders', [$response]);

        $this->assertArrayHasKey('_cors', $withCors);
        $this->assertEquals('http://localhost', $withCors['_cors']['origin']);
        $this->assertEquals(['POST', 'GET'], $withCors['_cors']['methods']);
    }

    #[Test]
    public function it_does_not_add_cors_headers_when_disabled(): void
    {
        Config::set('laravel-mcp.cors.enabled', false);

        $response = ['data' => 'test'];
        $withoutCors = $this->callMethod('addCorsHeaders', [$response]);

        $this->assertArrayNotHasKey('_cors', $withoutCors);
    }

    #[Test]
    public function it_includes_timestamp_when_configured(): void
    {
        Config::set('laravel-mcp.response.include_timestamp', true);

        $response = $this->callMethod('formatSuccess', [['test' => 'data']]);

        $this->assertArrayHasKey('timestamp', $response);
    }

    #[Test]
    public function it_excludes_timestamp_when_configured(): void
    {
        Config::set('laravel-mcp.response.include_timestamp', false);

        $response = $this->callMethod('formatSuccess', [['test' => 'data']]);

        $this->assertArrayNotHasKey('timestamp', $response);
    }

    #[Test]
    public function it_includes_debug_info_when_enabled(): void
    {
        Config::set('app.debug', true);
        Config::set('laravel-mcp.debug', true);

        $response = $this->callMethod('formatSuccess', [['test' => 'data']]);

        $this->assertArrayHasKey('_debug', $response);
        $this->assertEquals('test_component', $response['_debug']['name']);
        $this->assertEquals('test', $response['_debug']['type']);
    }

    #[Test]
    public function it_formats_batch_response(): void
    {
        $responses = [
            ['result' => 'success1'],
            new \Exception('Error'),
            ['result' => 'success2'],
        ];

        $batch = $this->callMethod('formatBatchResponse', [$responses]);

        $this->assertCount(3, $batch);
        $this->assertEquals('success1', $batch[0]['result']);
        $this->assertArrayHasKey('error', $batch[1]);
        $this->assertEquals('success2', $batch[2]['result']);
    }

    #[Test]
    public function it_formats_data_with_laravel_models(): void
    {
        $model = new class
        {
            public function toArray(): array
            {
                return ['id' => 1, 'name' => 'Test Model'];
            }
        };

        $formatted = $this->callMethod('formatData', [$model]);

        $this->assertEquals(['id' => 1, 'name' => 'Test Model'], $formatted);
    }

    #[Test]
    public function it_formats_data_with_json_serializable(): void
    {
        $serializable = new class implements \JsonSerializable
        {
            public function jsonSerialize(): array
            {
                return ['serialized' => true];
            }
        };

        $formatted = $this->callMethod('formatData', [$serializable]);

        $this->assertEquals(['serialized' => true], $formatted);
    }

    #[Test]
    public function it_formats_resource_list_item_from_array(): void
    {
        $item = [
            'uri' => 'resource/1',
            'name' => 'Resource Name',
            'description' => 'Resource Description',
            'mimeType' => 'text/plain',
        ];

        $formatted = $this->callMethod('formatResourceListItem', [$item]);

        $this->assertEquals('resource/1', $formatted['uri']);
        $this->assertEquals('Resource Name', $formatted['name']);
        $this->assertEquals('Resource Description', $formatted['description']);
        $this->assertEquals('text/plain', $formatted['mimeType']);
    }

    #[Test]
    public function it_formats_resource_list_item_from_object(): void
    {
        $item = new class
        {
            public function toArray(): array
            {
                return [
                    'id' => 123,
                    'title' => 'Item Title',
                ];
            }
        };

        $formatted = $this->callMethod('formatResourceListItem', [$item]);

        $this->assertEquals('123', $formatted['uri']);
        $this->assertEquals('Item Title', $formatted['name']);
    }
}
