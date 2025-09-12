<?php

namespace JTD\LaravelMCP\Tests\Unit\Support;

use Illuminate\Support\Collection;
use JTD\LaravelMCP\Support\MessageSerializer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Simple MessageSerializer Tests (without Laravel bootstrapping)
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
class SimpleMessageSerializerTest extends TestCase
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

        // Invalid - missing jsonrpc
        $invalid1 = ['method' => 'test', 'id' => 1];
        $this->assertFalse($this->serializer->validateMessage($invalid1));
    }
}
