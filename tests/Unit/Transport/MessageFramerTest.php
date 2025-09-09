<?php

/**
 * MessageFramer Unit Tests
 *
 * @epic Transport Layer
 *
 * @ticket 011-TransportStdio
 *
 * @module Transport/Utilities
 *
 * @coverage src/Transport/MessageFramer.php
 *
 * @test-type Unit
 *
 * Test requirements:
 * - Message framing and parsing
 * - Invalid message handling
 * - Large message support
 * - Protocol compliance
 * - Content-Length header support
 */

namespace JTD\LaravelMCP\Tests\Unit\Transport;

use JTD\LaravelMCP\Exceptions\TransportException;
use JTD\LaravelMCP\Tests\TestCase;
use JTD\LaravelMCP\Transport\MessageFramer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(MessageFramer::class)]
#[Group('transport')]
#[Group('stdio')]
#[Group('ticket-011')]
class MessageFramerTest extends TestCase
{
    private MessageFramer $framer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->framer = new MessageFramer;
    }

    #[Test]
    public function it_frames_json_rpc_request(): void
    {
        $message = [
            'method' => 'test.method',
            'params' => ['param1' => 'value1'],
            'id' => 1,
        ];

        $framed = $this->framer->frame($message);

        $this->assertStringContainsString('"jsonrpc":"2.0"', $framed);
        $this->assertStringContainsString('"method":"test.method"', $framed);
        $this->assertStringContainsString('"id":1', $framed);
        $this->assertStringEndsWith("\n", $framed);
    }

    #[Test]
    public function it_frames_json_rpc_response(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'result' => ['data' => 'test'],
            'id' => 1,
        ];

        $framed = $this->framer->frame($message);

        $this->assertStringContainsString('"result":{"data":"test"}', $framed);
        $this->assertStringContainsString('"id":1', $framed);
    }

    #[Test]
    public function it_frames_json_rpc_error_response(): void
    {
        $message = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32600,
                'message' => 'Invalid Request',
            ],
            'id' => null,
        ];

        $framed = $this->framer->frame($message);

        $this->assertStringContainsString('"error":{', $framed);
        $this->assertStringContainsString('"code":-32600', $framed);
        $this->assertStringContainsString('"message":"Invalid Request"', $framed);
    }

    #[Test]
    public function it_frames_with_content_length_header(): void
    {
        $framer = new MessageFramer(['use_content_length' => true]);

        $message = [
            'method' => 'test',
            'id' => 1,
        ];

        $framed = $framer->frame($message);

        $this->assertStringContainsString('Content-Length:', $framed);
        $this->assertStringContainsString('Content-Type: application/json', $framed);
        $this->assertStringContainsString("\r\n\r\n", $framed);
    }

    #[Test]
    public function it_parses_line_delimited_messages(): void
    {
        $message1 = '{"jsonrpc":"2.0","method":"test1","id":1}';
        $message2 = '{"jsonrpc":"2.0","method":"test2","id":2}';
        $data = $message1."\n".$message2."\n";

        $messages = $this->framer->parse($data);

        $this->assertCount(2, $messages);
        $this->assertEquals('test1', $messages[0]['method']);
        $this->assertEquals('test2', $messages[1]['method']);
    }

    #[Test]
    public function it_parses_partial_messages(): void
    {
        $message = '{"jsonrpc":"2.0","method":"test","id":1}';

        // Send first part
        $messages = $this->framer->parse(substr($message, 0, 20));
        $this->assertCount(0, $messages);

        // Send rest with newline
        $messages = $this->framer->parse(substr($message, 20)."\n");
        $this->assertCount(1, $messages);
        $this->assertEquals('test', $messages[0]['method']);
    }

    #[Test]
    public function it_parses_messages_with_content_length(): void
    {
        $framer = new MessageFramer(['use_content_length' => true]);

        $json = '{"jsonrpc":"2.0","method":"test","id":1}';
        $contentLength = strlen($json);
        $data = "Content-Length: {$contentLength}\r\n";
        $data .= "Content-Type: application/json\r\n";
        $data .= "\r\n";
        $data .= $json;

        $messages = $framer->parse($data);

        $this->assertCount(1, $messages);
        $this->assertEquals('test', $messages[0]['method']);
    }

    #[Test]
    public function it_handles_multiple_content_length_messages(): void
    {
        $framer = new MessageFramer(['use_content_length' => true]);

        $json1 = '{"jsonrpc":"2.0","method":"test1","id":1}';
        $json2 = '{"jsonrpc":"2.0","method":"test2","id":2}';

        $data = 'Content-Length: '.strlen($json1)."\r\n\r\n".$json1;
        $data .= 'Content-Length: '.strlen($json2)."\r\n\r\n".$json2;

        $messages = $framer->parse($data);

        $this->assertCount(2, $messages);
        $this->assertEquals('test1', $messages[0]['method']);
        $this->assertEquals('test2', $messages[1]['method']);
    }

    #[Test]
    public function it_throws_exception_for_invalid_json(): void
    {
        // Parse will catch the error but not throw it
        // Instead it logs and skips the invalid message
        $messages = $this->framer->parse("invalid json\n");

        // Should return empty array for invalid JSON
        $this->assertCount(0, $messages);

        // Check that parse error was tracked
        $stats = $this->framer->getStats();
        $this->assertEquals(1, $stats['parse_errors']);
    }

    #[Test]
    public function it_throws_exception_for_missing_jsonrpc_version(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Invalid or missing JSON-RPC version');

        $message = ['method' => 'test'];
        $this->framer->frame($message);

        // Now test with wrong version
        $message = ['jsonrpc' => '1.0', 'method' => 'test'];
        $this->framer->frame($message);
    }

    #[Test]
    public function it_throws_exception_for_invalid_message_structure(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Message must be either a request or response');

        $message = ['jsonrpc' => '2.0'];
        $this->framer->frame($message);
    }

    #[Test]
    public function it_throws_exception_for_buffer_overflow(): void
    {
        $framer = new MessageFramer(['max_message_size' => 100]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessageMatches('/buffer overflow/i');

        $largeMessage = str_repeat('x', 200);
        $framer->parse($largeMessage);
    }

    #[Test]
    public function it_creates_request_messages(): void
    {
        $request = $this->framer->createRequest('test.method', ['param' => 'value'], 1);

        $this->assertEquals('2.0', $request['jsonrpc']);
        $this->assertEquals('test.method', $request['method']);
        $this->assertEquals(['param' => 'value'], $request['params']);
        $this->assertEquals(1, $request['id']);
    }

    #[Test]
    public function it_creates_notification_messages(): void
    {
        $notification = $this->framer->createRequest('notify', ['data' => 'test']);

        $this->assertEquals('2.0', $notification['jsonrpc']);
        $this->assertEquals('notify', $notification['method']);
        $this->assertArrayNotHasKey('id', $notification);
    }

    #[Test]
    public function it_creates_response_messages(): void
    {
        $response = $this->framer->createResponse(['result' => 'data'], 1);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(['result' => 'data'], $response['result']);
        $this->assertEquals(1, $response['id']);
    }

    #[Test]
    public function it_creates_error_response_messages(): void
    {
        $error = $this->framer->createErrorResponse(
            -32600,
            'Invalid Request',
            ['details' => 'Missing method'],
            1
        );

        $this->assertEquals('2.0', $error['jsonrpc']);
        $this->assertEquals(-32600, $error['error']['code']);
        $this->assertEquals('Invalid Request', $error['error']['message']);
        $this->assertEquals(['details' => 'Missing method'], $error['error']['data']);
        $this->assertEquals(1, $error['id']);
    }

    #[Test]
    public function it_identifies_request_messages(): void
    {
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];
        $response = ['jsonrpc' => '2.0', 'result' => 'data', 'id' => 1];

        $this->assertTrue($this->framer->isRequest($request));
        $this->assertFalse($this->framer->isRequest($response));
    }

    #[Test]
    public function it_identifies_response_messages(): void
    {
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];
        $response = ['jsonrpc' => '2.0', 'result' => 'data', 'id' => 1];
        $error = ['jsonrpc' => '2.0', 'error' => ['code' => -32600, 'message' => 'Error'], 'id' => 1];

        $this->assertFalse($this->framer->isResponse($request));
        $this->assertTrue($this->framer->isResponse($response));
        $this->assertTrue($this->framer->isResponse($error));
    }

    #[Test]
    public function it_identifies_notification_messages(): void
    {
        $request = ['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1];
        $notification = ['jsonrpc' => '2.0', 'method' => 'notify'];

        $this->assertFalse($this->framer->isNotification($request));
        $this->assertTrue($this->framer->isNotification($notification));
    }

    #[Test]
    public function it_clears_buffer(): void
    {
        $this->framer->parse('partial message');
        $this->assertGreaterThan(0, $this->framer->getBufferSize());

        $this->framer->clearBuffer();
        $this->assertEquals(0, $this->framer->getBufferSize());
    }

    #[Test]
    public function it_checks_for_buffered_data(): void
    {
        $this->assertFalse($this->framer->hasBufferedData());

        $this->framer->parse('partial');
        $this->assertTrue($this->framer->hasBufferedData());
    }

    #[Test]
    public function it_tracks_statistics(): void
    {
        $stats = $this->framer->getStats();
        $this->assertEquals(0, $stats['messages_framed']);
        $this->assertEquals(0, $stats['messages_parsed']);

        $this->framer->frame(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]);
        $this->framer->parse('{"jsonrpc":"2.0","result":"ok","id":1}'."\n");

        $stats = $this->framer->getStats();
        $this->assertEquals(1, $stats['messages_framed']);
        $this->assertEquals(1, $stats['messages_parsed']);
    }

    #[Test]
    public function it_resets_statistics(): void
    {
        $this->framer->frame(['jsonrpc' => '2.0', 'method' => 'test', 'id' => 1]);
        $stats = $this->framer->getStats();
        $this->assertGreaterThan(0, $stats['messages_framed']);

        $this->framer->resetStats();

        $stats = $this->framer->getStats();
        $this->assertEquals(0, $stats['messages_framed']);
        $this->assertEquals(0, $stats['messages_parsed']);
    }

    #[Test]
    #[DataProvider('errorCodeProvider')]
    public function it_provides_standard_error_codes(string $name, int $expectedCode): void
    {
        $codes = MessageFramer::getErrorCodes();

        $this->assertArrayHasKey($name, $codes);
        $this->assertEquals($expectedCode, $codes[$name]);
    }

    public static function errorCodeProvider(): array
    {
        return [
            ['PARSE_ERROR', -32700],
            ['INVALID_REQUEST', -32600],
            ['METHOD_NOT_FOUND', -32601],
            ['INVALID_PARAMS', -32602],
            ['INTERNAL_ERROR', -32603],
            ['SERVER_ERROR', -32000],
        ];
    }

    #[Test]
    public function it_validates_error_object_structure(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Error code must be an integer');

        $message = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => 'invalid',
                'message' => 'Error',
            ],
            'id' => 1,
        ];

        $this->framer->frame($message);
    }

    #[Test]
    public function it_validates_method_type(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Method must be a string');

        $message = [
            'jsonrpc' => '2.0',
            'method' => 123,
            'id' => 1,
        ];

        $this->framer->frame($message);
    }

    #[Test]
    public function it_validates_params_type(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Params must be an array');

        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => 'invalid',
            'id' => 1,
        ];

        $this->framer->frame($message);
    }

    #[Test]
    public function it_validates_id_type(): void
    {
        // Valid ID types
        $validIds = [1, 'string-id', null];

        foreach ($validIds as $id) {
            $message = [
                'jsonrpc' => '2.0',
                'method' => 'test',
                'id' => $id,
            ];

            $framed = $this->framer->frame($message);
            $this->assertNotEmpty($framed);
        }

        // Invalid ID type
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Invalid id type');

        $message = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'id' => ['invalid'],
        ];

        $this->framer->frame($message);
    }
}
