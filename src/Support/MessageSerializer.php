<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Illuminate\Support\Collection;

/**
 * Message serializer for Laravel-optimized JSON-RPC serialization.
 *
 * This class provides efficient serialization and deserialization of MCP messages,
 * with special handling for Laravel collections, models, and other framework-specific
 * data types. It optimizes performance while ensuring proper JSON-RPC compliance.
 */
class MessageSerializer
{
    /**
     * Maximum depth for nested object serialization.
     */
    protected int $maxDepth = 10;

    /**
     * Current serialization depth.
     */
    protected int $currentDepth = 0;

    /**
     * Object reference tracker to prevent circular references.
     */
    protected array $objectReferences = [];

    /**
     * Custom serializers for specific types.
     */
    protected array $customSerializers = [];

    /**
     * Options for JSON encoding.
     */
    protected int $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    /**
     * Create a new message serializer instance.
     *
     * @param  int  $maxDepth  Maximum serialization depth
     * @param  int  $jsonOptions  JSON encoding options
     */
    public function __construct(int $maxDepth = 10, int $jsonOptions = 0)
    {
        $this->maxDepth = $maxDepth;

        if ($jsonOptions > 0) {
            $this->jsonOptions = $jsonOptions;
        }

        $this->registerDefaultSerializers();
    }

    /**
     * Serialize an MCP message to JSON.
     *
     * @param  array  $message  The message to serialize
     * @return string The JSON-encoded message
     *
     * @throws \RuntimeException If serialization fails
     */
    public function serialize(array $message): string
    {
        $this->reset();

        try {
            $prepared = $this->prepareForSerialization($message);
            $json = json_encode($prepared, $this->jsonOptions);

            if ($json === false) {
                throw new \RuntimeException('Failed to encode message: '.json_last_error_msg());
            }

            return $json;
        } finally {
            $this->reset();
        }
    }

    /**
     * Deserialize a JSON message to array.
     *
     * @param  string  $json  The JSON string to deserialize
     * @return array The decoded message
     *
     * @throws \RuntimeException If deserialization fails
     */
    public function deserialize(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode message: '.json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('Decoded message is not an array');
        }

        return $this->restoreFromDeserialization($decoded);
    }

    /**
     * Serialize a batch of messages.
     *
     * @param  array  $messages  Array of messages to serialize
     * @return string The JSON-encoded batch
     *
     * @throws \RuntimeException If serialization fails
     */
    public function serializeBatch(array $messages): string
    {
        $batch = [];

        foreach ($messages as $message) {
            $this->reset();
            $batch[] = $this->prepareForSerialization($message);
        }

        $json = json_encode($batch, $this->jsonOptions);

        if ($json === false) {
            throw new \RuntimeException('Failed to encode batch: '.json_last_error_msg());
        }

        return $json;
    }

    /**
     * Deserialize a batch of JSON messages.
     *
     * @param  string  $json  The JSON batch string
     * @return array The decoded messages
     *
     * @throws \RuntimeException If deserialization fails
     */
    public function deserializeBatch(string $json): array
    {
        $decoded = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to decode batch: '.json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new \RuntimeException('Decoded batch is not an array');
        }

        $messages = [];
        foreach ($decoded as $message) {
            $messages[] = $this->restoreFromDeserialization($message);
        }

        return $messages;
    }

    /**
     * Prepare data for serialization.
     *
     * @param  mixed  $data  The data to prepare
     * @return mixed The prepared data
     *
     * @throws \RuntimeException If maximum depth is exceeded
     */
    protected function prepareForSerialization($data)
    {
        $this->currentDepth++;

        if ($this->currentDepth > $this->maxDepth) {
            throw new \RuntimeException("Maximum serialization depth ({$this->maxDepth}) exceeded");
        }

        try {
            // Handle null
            if ($data === null) {
                return null;
            }

            // Handle scalars
            if (is_scalar($data)) {
                return $data;
            }

            // Handle arrays
            if (is_array($data)) {
                return $this->prepareArray($data);
            }

            // Handle objects
            if (is_object($data)) {
                return $this->prepareObject($data);
            }

            // Handle resources (convert to string representation)
            if (is_resource($data)) {
                return '[Resource: '.get_resource_type($data).']';
            }

            // Default to string representation
            return (string) $data;
        } finally {
            $this->currentDepth--;
        }
    }

    /**
     * Prepare an array for serialization.
     *
     * @param  array  $data  The array to prepare
     * @return array The prepared array
     */
    protected function prepareArray(array $data): array
    {
        $prepared = [];

        foreach ($data as $key => $value) {
            $prepared[$key] = $this->prepareForSerialization($value);
        }

        return $prepared;
    }

    /**
     * Prepare an object for serialization.
     *
     * @param  object  $data  The object to prepare
     * @return mixed The prepared object data
     */
    protected function prepareObject(object $data)
    {
        // Check for circular reference
        $objectHash = spl_object_hash($data);
        if (isset($this->objectReferences[$objectHash])) {
            return '[Circular Reference]';
        }

        $this->objectReferences[$objectHash] = true;

        try {
            // Check custom serializers
            foreach ($this->customSerializers as $class => $serializer) {
                if ($data instanceof $class) {
                    return $serializer($data, $this);
                }
            }

            // Handle Laravel Collections
            if ($data instanceof Collection) {
                return $this->prepareCollection($data);
            }

            // Handle Jsonable objects
            if ($data instanceof Jsonable) {
                return json_decode($data->toJson(), true);
            }

            // Handle Arrayable objects
            if ($data instanceof Arrayable) {
                return $this->prepareForSerialization($data->toArray());
            }

            // Handle objects with __toString
            if (method_exists($data, '__toString')) {
                return (string) $data;
            }

            // Handle JsonSerializable objects
            if ($data instanceof \JsonSerializable) {
                return $this->prepareForSerialization($data->jsonSerialize());
            }

            // Handle DateTime objects
            if ($data instanceof \DateTimeInterface) {
                return $data->format(\DateTimeInterface::RFC3339);
            }

            // Handle Closures
            if ($data instanceof \Closure) {
                return '[Closure]';
            }

            // Handle standard objects - convert public properties
            return $this->prepareStandardObject($data);
        } finally {
            unset($this->objectReferences[$objectHash]);
        }
    }

    /**
     * Prepare a Laravel Collection for serialization.
     *
     * @param  Collection  $collection  The collection to prepare
     * @return array The prepared collection data
     */
    protected function prepareCollection(Collection $collection): array
    {
        return $this->prepareForSerialization($collection->toArray());
    }

    /**
     * Prepare a standard object for serialization.
     *
     * @param  object  $object  The object to prepare
     * @return array The prepared object data
     */
    protected function prepareStandardObject(object $object): array
    {
        $data = [];
        $reflection = new \ReflectionObject($object);

        // Add class information for deserialization
        $data['__class'] = get_class($object);

        // Get public properties
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $value = $property->getValue($object);
            $data[$name] = $this->prepareForSerialization($value);
        }

        return $data;
    }

    /**
     * Restore data from deserialization.
     *
     * @param  mixed  $data  The data to restore
     * @return mixed The restored data
     */
    protected function restoreFromDeserialization($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        // Check if this is a serialized object
        if (isset($data['__class'])) {
            return $this->restoreObject($data);
        }

        // Recursively restore array elements
        $restored = [];
        foreach ($data as $key => $value) {
            $restored[$key] = $this->restoreFromDeserialization($value);
        }

        return $restored;
    }

    /**
     * Restore an object from deserialized data.
     *
     * @param  array  $data  The object data
     * @return mixed The restored object or data array
     */
    protected function restoreObject(array $data)
    {
        $className = $data['__class'];
        unset($data['__class']);

        // For security, only restore certain safe classes
        $safeClasses = [
            Collection::class,
            \DateTime::class,
            \DateTimeImmutable::class,
        ];

        if (! in_array($className, $safeClasses)) {
            // Return as array with class information
            $data['__original_class'] = $className;

            return $data;
        }

        // Restore Laravel Collections
        if ($className === Collection::class) {
            return new Collection($data);
        }

        // Restore DateTime objects
        if ($className === \DateTime::class || $className === \DateTimeImmutable::class) {
            if (isset($data['date'])) {
                return new $className($data['date']);
            }
        }

        return $data;
    }

    /**
     * Register a custom serializer for a specific class.
     *
     * @param  string  $class  The class name
     * @param  callable  $serializer  The serializer callback
     */
    public function registerSerializer(string $class, callable $serializer): void
    {
        $this->customSerializers[$class] = $serializer;
    }

    /**
     * Register default serializers for common Laravel types.
     */
    protected function registerDefaultSerializers(): void
    {
        // Eloquent Model serializer
        $this->registerSerializer(\Illuminate\Database\Eloquent\Model::class, function ($model) {
            return $this->prepareForSerialization($model->toArray());
        });

        // Carbon date serializer
        if (class_exists(\Carbon\Carbon::class)) {
            $this->registerSerializer(\Carbon\Carbon::class, function ($carbon) {
                return $carbon->toISOString();
            });
        }

        // Stringable serializer
        $this->registerSerializer(\Illuminate\Support\Stringable::class, function ($stringable) {
            return (string) $stringable;
        });
    }

    /**
     * Reset the serializer state.
     */
    protected function reset(): void
    {
        $this->currentDepth = 0;
        $this->objectReferences = [];
    }

    /**
     * Set the maximum serialization depth.
     *
     * @param  int  $depth  The maximum depth
     */
    public function setMaxDepth(int $depth): void
    {
        $this->maxDepth = $depth;
    }

    /**
     * Set JSON encoding options.
     *
     * @param  int  $options  The JSON options
     */
    public function setJsonOptions(int $options): void
    {
        $this->jsonOptions = $options;
    }

    /**
     * Check if the serializer can handle the given data.
     *
     * @param  mixed  $data  The data to check
     * @return bool True if the data can be serialized
     */
    public function canSerialize($data): bool
    {
        try {
            $this->reset();
            $this->prepareForSerialization($data);

            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            $this->reset();
        }
    }

    /**
     * Get the size of serialized data in bytes.
     *
     * @param  mixed  $data  The data to measure
     * @return int The size in bytes
     */
    public function getSerializedSize($data): int
    {
        try {
            $json = $this->serialize(is_array($data) ? $data : ['data' => $data]);

            return strlen($json);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Validate a JSON-RPC message structure.
     *
     * @param  array  $message  The message to validate
     * @return bool True if valid
     */
    public function validateMessage(array $message): bool
    {
        // Check for required JSON-RPC fields
        if (! isset($message['jsonrpc']) || $message['jsonrpc'] !== '2.0') {
            return false;
        }

        // Check for method or result/error
        $hasMethod = isset($message['method']) && is_string($message['method']);
        $hasResult = array_key_exists('result', $message);
        $hasError = isset($message['error']) && is_array($message['error']);

        // Request must have method, response must have result or error
        if (! $hasMethod && ! $hasResult && ! $hasError) {
            return false;
        }

        // Response cannot have both result and error
        if ($hasResult && $hasError) {
            return false;
        }

        // Message cannot have both method and result/error (can't be both request and response)
        if ($hasMethod && ($hasResult || $hasError)) {
            return false;
        }

        // Validate error structure if present
        if ($hasError) {
            if (! isset($message['error']['code']) || ! is_int($message['error']['code'])) {
                return false;
            }
            if (! isset($message['error']['message']) || ! is_string($message['error']['message'])) {
                return false;
            }
        }

        // Check id field if present
        if (isset($message['id']) && ! is_string($message['id']) && ! is_numeric($message['id']) && ! is_null($message['id'])) {
            return false;
        }

        return true;
    }

    /**
     * Compress a serialized message using gzip.
     *
     * @param  string  $json  The JSON string to compress
     * @return string The compressed data
     */
    public function compress(string $json): string
    {
        $compressed = gzencode($json, 9);

        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress message');
        }

        return $compressed;
    }

    /**
     * Decompress a gzipped message.
     *
     * @param  string  $compressed  The compressed data
     * @return string The decompressed JSON
     */
    public function decompress(string $compressed): string
    {
        $json = gzdecode($compressed);

        if ($json === false) {
            throw new \RuntimeException('Failed to decompress message');
        }

        return $json;
    }
}
