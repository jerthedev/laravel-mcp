<?php

namespace JTD\LaravelMCP\Tests\Unit\Support;

use Illuminate\Support\Collection;
use JTD\LaravelMCP\Support\MessageSerializer;
use JTD\LaravelMCP\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * MessageSerializer Tests
 *
 * @group unit
 * @group support
 * @group ticket-023
 * @group epic-laravel-integration
 * @group sprint-3
 */
#[Group('unit')]
#[Group('support')]
#[Group('ticket-023')]
class MessageSerializerTest extends TestCase
{
    protected MessageSerializer $serializer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new MessageSerializer;
    }

    #[Test]
    public function it_serializes_simple_arrays(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['foo' => 'bar'],
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertEquals($data, $decoded);
    }

    #[Test]
    public function it_deserializes_json_messages(): void
    {
        $json = '{"jsonrpc":"2.0","method":"test","params":{"foo":"bar"},"id":1}';

        $result = $this->serializer->deserialize($json);

        $this->assertEquals('2.0', $result['jsonrpc']);
        $this->assertEquals('test', $result['method']);
        $this->assertEquals(['foo' => 'bar'], $result['params']);
        $this->assertEquals(1, $result['id']);
    }

    #[Test]
    public function it_handles_laravel_collections(): void
    {
        $collection = new Collection(['foo', 'bar', 'baz']);
        $data = [
            'jsonrpc' => '2.0',
            'result' => $collection,
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertEquals(['foo', 'bar', 'baz'], $decoded['result']);
    }

    #[Test]
    public function it_prevents_circular_references(): void
    {
        $obj1 = new \stdClass;
        $obj2 = new \stdClass;
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $data = [
            'jsonrpc' => '2.0',
            'result' => $obj1,
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertStringContainsString('[Circular Reference]', json_encode($decoded));
    }

    #[Test]
    public function it_respects_max_depth_limit(): void
    {
        $serializer = new MessageSerializer(2);

        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'too deep',
                    ],
                ],
            ],
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Maximum serialization depth');

        $serializer->serialize($data);
    }

    #[Test]
    public function it_serializes_batch_messages(): void
    {
        $messages = [
            ['jsonrpc' => '2.0', 'method' => 'test1', 'params' => [], 'id' => 1],
            ['jsonrpc' => '2.0', 'method' => 'test2', 'params' => [], 'id' => 2],
        ];

        $json = $this->serializer->serializeBatch($messages);
        $decoded = json_decode($json, true);

        $this->assertCount(2, $decoded);
        $this->assertEquals('test1', $decoded[0]['method']);
        $this->assertEquals('test2', $decoded[1]['method']);
    }

    #[Test]
    public function it_deserializes_batch_messages(): void
    {
        $json = '[{"jsonrpc":"2.0","method":"test1","id":1},{"jsonrpc":"2.0","method":"test2","id":2}]';

        $messages = $this->serializer->deserializeBatch($json);

        $this->assertCount(2, $messages);
        $this->assertEquals('test1', $messages[0]['method']);
        $this->assertEquals('test2', $messages[1]['method']);
    }

    #[Test]
    public function it_validates_jsonrpc_messages(): void
    {
        // Valid request
        $validRequest = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => [],
            'id' => 1,
        ];
        $this->assertTrue($this->serializer->validateMessage($validRequest));

        // Valid response with result
        $validResponse = [
            'jsonrpc' => '2.0',
            'result' => 'success',
            'id' => 1,
        ];
        $this->assertTrue($this->serializer->validateMessage($validResponse));

        // Valid error response
        $validError = [
            'jsonrpc' => '2.0',
            'error' => ['code' => -32600, 'message' => 'Invalid Request'],
            'id' => 1,
        ];
        $this->assertTrue($this->serializer->validateMessage($validError));

        // Invalid - missing jsonrpc
        $invalid1 = ['method' => 'test', 'id' => 1];
        $this->assertFalse($this->serializer->validateMessage($invalid1));

        // Invalid - wrong jsonrpc version
        $invalid2 = ['jsonrpc' => '1.0', 'method' => 'test', 'id' => 1];
        $this->assertFalse($this->serializer->validateMessage($invalid2));

        // Invalid - both result and error
        $invalid3 = [
            'jsonrpc' => '2.0',
            'result' => 'success',
            'error' => ['code' => -32600, 'message' => 'Error'],
            'id' => 1,
        ];
        $this->assertFalse($this->serializer->validateMessage($invalid3));
    }

    #[Test]
    public function it_handles_datetime_objects(): void
    {
        $date = new \DateTime('2024-01-15 10:30:00');
        $data = [
            'jsonrpc' => '2.0',
            'result' => ['date' => $date],
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertStringStartsWith('2024-01-15', $decoded['result']['date']);
    }

    #[Test]
    public function it_can_check_serializability(): void
    {
        $serializableData = ['foo' => 'bar'];
        $this->assertTrue($this->serializer->canSerialize($serializableData));

        $resource = fopen('php://memory', 'r');
        $dataWithResource = ['resource' => $resource];
        $this->assertTrue($this->serializer->canSerialize($dataWithResource));
        fclose($resource);
    }

    #[Test]
    public function it_measures_serialized_size(): void
    {
        $data = ['foo' => 'bar', 'baz' => 'qux'];
        $size = $this->serializer->getSerializedSize($data);

        $this->assertGreaterThan(0, $size);
        $this->assertLessThan(1000, $size);
    }

    #[Test]
    public function it_compresses_and_decompresses_messages(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'params' => ['data' => str_repeat('test', 1000)],
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $compressed = $this->serializer->compress($json);
        $decompressed = $this->serializer->decompress($compressed);

        $this->assertEquals($json, $decompressed);
        $this->assertLessThan(strlen($json), strlen($compressed));
    }

    #[Test]
    public function it_handles_custom_serializers(): void
    {
        $customClass = new class
        {
            public $value = 'custom';
        };

        $this->serializer->registerSerializer(get_class($customClass), function ($obj) {
            return 'CUSTOM:'.$obj->value;
        });

        $data = [
            'jsonrpc' => '2.0',
            'result' => $customClass,
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertEquals('CUSTOM:custom', $decoded['result']);
    }

    #[Test]
    public function it_handles_invalid_json_gracefully(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode message');

        $this->serializer->deserialize('invalid json');
    }

    #[Test]
    public function it_handles_non_array_deserialization(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decoded message is not an array');

        $this->serializer->deserialize('"string"');
    }

    #[Test]
    public function it_handles_empty_batch_messages(): void
    {
        $json = $this->serializer->serializeBatch([]);
        $this->assertEquals('[]', $json);

        $messages = $this->serializer->deserializeBatch('[]');
        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    #[Test]
    public function it_handles_deeply_nested_collections(): void
    {
        $nested = collect([
            'level1' => collect([
                'level2' => collect([
                    'level3' => collect(['deep' => 'value']),
                ]),
            ]),
        ]);

        $data = [
            'jsonrpc' => '2.0',
            'result' => $nested,
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertEquals('value', $decoded['result']['level1']['level2']['level3']['deep']);
    }

    #[Test]
    public function it_handles_null_values_in_messages(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'result' => null,
            'id' => null,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        $this->assertNull($decoded['result']);
        $this->assertNull($decoded['id']);
    }

    #[Test]
    public function it_validates_notification_messages(): void
    {
        // Valid notification (no id)
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'notification',
            'params' => [],
        ];
        $this->assertTrue($this->serializer->validateMessage($notification));

        // Invalid notification with both result and method
        $invalid = [
            'jsonrpc' => '2.0',
            'method' => 'test',
            'result' => 'data',
        ];
        $this->assertFalse($this->serializer->validateMessage($invalid));
    }

    #[Test]
    public function it_handles_unicode_and_special_characters(): void
    {
        $data = [
            'jsonrpc' => '2.0',
            'result' => [
                'emoji' => '🚀',
                'unicode' => 'こんにちは',
                'special' => "Line1\nLine2\tTab",
                'quotes' => 'He said "Hello"',
            ],
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = $this->serializer->deserialize($json);

        $this->assertEquals('🚀', $decoded['result']['emoji']);
        $this->assertEquals('こんにちは', $decoded['result']['unicode']);
        $this->assertStringContainsString("\n", $decoded['result']['special']);
    }

    #[Test]
    public function it_handles_extremely_large_messages(): void
    {
        // Create a large message
        $largeArray = array_fill(0, 1000, str_repeat('x', 100));
        $data = [
            'jsonrpc' => '2.0',
            'result' => $largeArray,
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $size = $this->serializer->getSerializedSize($data);

        $this->assertGreaterThan(100000, $size);

        // Compression should reduce size significantly
        $compressed = $this->serializer->compress($json);
        $this->assertLessThan($size, strlen($compressed));
    }

    #[Test]
    public function it_handles_invalid_batch_json(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode batch');

        $this->serializer->deserializeBatch('not json array');
    }

    #[Test]
    public function it_validates_error_response_structure(): void
    {
        // Valid error with complete structure
        $validError = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32700,
                'message' => 'Parse error',
                'data' => ['line' => 1, 'column' => 10],
            ],
            'id' => null,
        ];
        $this->assertTrue($this->serializer->validateMessage($validError));

        // Invalid error - missing message
        $invalidError = [
            'jsonrpc' => '2.0',
            'error' => ['code' => -32700],
            'id' => 1,
        ];
        $this->assertFalse($this->serializer->validateMessage($invalidError));
    }

    #[Test]
    public function it_handles_resources_and_closures(): void
    {
        $resource = fopen('php://memory', 'r');
        $closure = function () {
            return 'test';
        };

        $data = [
            'jsonrpc' => '2.0',
            'result' => [
                'resource' => $resource,
                'closure' => $closure,
            ],
            'id' => 1,
        ];

        $json = $this->serializer->serialize($data);
        $decoded = json_decode($json, true);

        // Resources and closures should be serialized as type indicators
        $this->assertStringContainsString('[Resource', json_encode($decoded['result']['resource']));
        $this->assertStringContainsString('[Closure]', json_encode($decoded['result']['closure']));

        fclose($resource);
    }
}
