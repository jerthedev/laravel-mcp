<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Compiler for advanced MCP examples.
 *
 * This class compiles, validates, and tests example code for MCP components.
 */
class ExampleCompiler
{
    /**
     * Temporary directory for compiled examples.
     */
    protected ?string $tempDir = null;

    /**
     * Validation results.
     */
    protected array $validationResults = [];

    /**
     * Create a new example compiler instance.
     */
    public function __construct()
    {
        $this->tempDir = sys_get_temp_dir().'/mcp-examples-'.Str::random(8);
    }

    /**
     * Compile an example.
     */
    public function compile(string $code, string $type = 'tool'): array
    {
        $className = $this->extractClassName($code);
        $namespace = $this->extractNamespace($code);

        // Validate syntax
        $syntaxErrors = $this->validateSyntax($code);
        if (! empty($syntaxErrors)) {
            return [
                'success' => false,
                'errors' => $syntaxErrors,
                'class' => $className,
                'type' => $type,
            ];
        }

        // Check if class extends correct base class
        $baseClassValid = $this->validateBaseClass($code, $type);
        if (! $baseClassValid) {
            return [
                'success' => false,
                'errors' => ['Class must extend Mcp'.ucfirst($type)],
                'class' => $className,
                'type' => $type,
            ];
        }

        // Validate required methods
        $methodErrors = $this->validateRequiredMethods($code, $type);
        if (! empty($methodErrors)) {
            return [
                'success' => false,
                'errors' => $methodErrors,
                'class' => $className,
                'type' => $type,
            ];
        }

        return [
            'success' => true,
            'class' => $className,
            'namespace' => $namespace,
            'type' => $type,
            'validated' => true,
        ];
    }

    /**
     * Compile advanced examples.
     */
    public function compileAdvancedExamples(array $options = []): array
    {
        $examples = [];

        // Database Tool Example
        $examples['database-tool'] = $this->generateDatabaseToolExample();

        // API Integration Tool
        $examples['api-integration'] = $this->generateApiIntegrationExample();

        // File Processing Resource
        $examples['file-processor'] = $this->generateFileProcessorExample();

        // Caching Resource
        $examples['cache-resource'] = $this->generateCacheResourceExample();

        // Complex Prompt Template
        $examples['complex-prompt'] = $this->generateComplexPromptExample();

        // Custom Transport Implementation
        $examples['custom-transport'] = $this->generateCustomTransportExample();

        // Middleware Integration
        $examples['middleware-integration'] = $this->generateMiddlewareExample();

        // Event-Driven Tool
        $examples['event-driven'] = $this->generateEventDrivenExample();

        // Validate all examples
        foreach ($examples as $name => $code) {
            $type = $this->detectExampleType($code);
            $result = $this->compile($code, $type);
            $this->validationResults[$name] = $result;
        }

        return $examples;
    }

    /**
     * Generate database tool example.
     */
    protected function generateDatabaseToolExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DatabaseQueryTool extends McpTool
{
    /**
     * Get tool name.
     */
    public function getName(): string
    {
        return 'database_query';
    }

    /**
     * Get tool description.
     */
    public function getDescription(): string
    {
        return 'Execute safe database queries with parameter binding';
    }

    /**
     * Get input schema.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'table' => [
                    'type' => 'string',
                    'description' => 'Database table name',
                    'pattern' => '^[a-zA-Z_][a-zA-Z0-9_]*$',
                ],
                'operation' => [
                    'type' => 'string',
                    'enum' => ['select', 'count', 'exists'],
                    'description' => 'Query operation',
                ],
                'conditions' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'column' => ['type' => 'string'],
                            'operator' => ['type' => 'string', 'enum' => ['=', '!=', '>', '<', '>=', '<=', 'like']],
                            'value' => ['type' => ['string', 'number', 'boolean']],
                        ],
                        'required' => ['column', 'operator', 'value'],
                    ],
                ],
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 100,
                    'default' => 10,
                ],
            ],
            'required' => ['table', 'operation'],
        ];
    }

    /**
     * Execute the tool.
     */
    public function execute(array $parameters): array
    {
        // Validate parameters
        $validator = Validator::make($parameters, [
            'table' => 'required|string|regex:/^[a-zA-Z_][a-zA-Z0-9_]*$/',
            'operation' => 'required|in:select,count,exists',
            'conditions' => 'array',
            'conditions.*.column' => 'required|string',
            'conditions.*.operator' => 'required|in:=,!=,>,<,>=,<=,like',
            'conditions.*.value' => 'required',
            'limit' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return [
                'error' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        try {
            $query = DB::table($parameters['table']);

            // Apply conditions
            if (isset($parameters['conditions'])) {
                foreach ($parameters['conditions'] as $condition) {
                    $query->where(
                        $condition['column'],
                        $condition['operator'],
                        $condition['value']
                    );
                }
            }

            // Execute operation
            switch ($parameters['operation']) {
                case 'select':
                    $limit = $parameters['limit'] ?? 10;
                    $results = $query->limit($limit)->get();
                    return [
                        'success' => true,
                        'data' => $results->toArray(),
                        'count' => $results->count(),
                    ];

                case 'count':
                    $count = $query->count();
                    return [
                        'success' => true,
                        'count' => $count,
                    ];

                case 'exists':
                    $exists = $query->exists();
                    return [
                        'success' => true,
                        'exists' => $exists,
                    ];

                default:
                    return [
                        'error' => 'Invalid operation',
                    ];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'Database error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
PHP;
    }

    /**
     * Generate API integration example.
     */
    protected function generateApiIntegrationExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ApiIntegrationTool extends McpTool
{
    /**
     * Get tool name.
     */
    public function getName(): string
    {
        return 'api_integration';
    }

    /**
     * Get tool description.
     */
    public function getDescription(): string
    {
        return 'Integrate with external APIs with caching and retry logic';
    }

    /**
     * Get input schema.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'endpoint' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'API endpoint URL',
                ],
                'method' => [
                    'type' => 'string',
                    'enum' => ['GET', 'POST', 'PUT', 'DELETE'],
                    'default' => 'GET',
                ],
                'headers' => [
                    'type' => 'object',
                    'description' => 'Request headers',
                ],
                'body' => [
                    'type' => 'object',
                    'description' => 'Request body for POST/PUT',
                ],
                'cache_ttl' => [
                    'type' => 'integer',
                    'description' => 'Cache TTL in seconds',
                    'minimum' => 0,
                    'default' => 300,
                ],
                'retry_times' => [
                    'type' => 'integer',
                    'description' => 'Number of retry attempts',
                    'minimum' => 0,
                    'maximum' => 5,
                    'default' => 3,
                ],
            ],
            'required' => ['endpoint'],
        ];
    }

    /**
     * Execute the tool.
     */
    public function execute(array $parameters): array
    {
        $endpoint = $parameters['endpoint'];
        $method = strtoupper($parameters['method'] ?? 'GET');
        $headers = $parameters['headers'] ?? [];
        $body = $parameters['body'] ?? null;
        $cacheTtl = $parameters['cache_ttl'] ?? 300;
        $retryTimes = $parameters['retry_times'] ?? 3;

        // Generate cache key
        $cacheKey = 'api:' . md5($endpoint . $method . json_encode($body));

        // Check cache for GET requests
        if ($method === 'GET' && $cacheTtl > 0) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return [
                    'success' => true,
                    'cached' => true,
                    'data' => $cached,
                ];
            }
        }

        try {
            // Build request
            $request = Http::withHeaders($headers)
                ->retry($retryTimes, 100)
                ->timeout(30);

            // Execute request
            $response = match($method) {
                'GET' => $request->get($endpoint),
                'POST' => $request->post($endpoint, $body),
                'PUT' => $request->put($endpoint, $body),
                'DELETE' => $request->delete($endpoint),
                default => throw new \InvalidArgumentException("Invalid method: $method"),
            };

            if ($response->successful()) {
                $data = $response->json() ?? $response->body();

                // Cache successful GET responses
                if ($method === 'GET' && $cacheTtl > 0) {
                    Cache::put($cacheKey, $data, $cacheTtl);
                }

                return [
                    'success' => true,
                    'status' => $response->status(),
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->body(),
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Request failed',
                'message' => $e->getMessage(),
            ];
        }
    }
}
PHP;
    }

    /**
     * Generate file processor example.
     */
    protected function generateFileProcessorExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class FileProcessorResource extends McpResource
{
    /**
     * Get resource name.
     */
    public function getName(): string
    {
        return 'file_processor';
    }

    /**
     * Get resource description.
     */
    public function getDescription(): string
    {
        return 'Process and analyze files with various operations';
    }

    /**
     * Get resource URI.
     */
    public function getUri(): string
    {
        return 'file://processor';
    }

    /**
     * Read resource.
     */
    public function read(array $parameters): array
    {
        $operation = $parameters['operation'] ?? 'list';
        $path = $parameters['path'] ?? '';
        $disk = $parameters['disk'] ?? 'local';

        try {
            $storage = Storage::disk($disk);

            switch ($operation) {
                case 'list':
                    return $this->listFiles($storage, $path);

                case 'read':
                    return $this->readFile($storage, $path);

                case 'metadata':
                    return $this->getFileMetadata($storage, $path);

                case 'analyze':
                    return $this->analyzeFile($storage, $path);

                default:
                    return ['error' => 'Invalid operation'];
            }
        } catch (\Exception $e) {
            return [
                'error' => 'File processing error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List files in directory.
     */
    protected function listFiles($storage, string $path): array
    {
        $files = $storage->files($path);
        $directories = $storage->directories($path);

        return [
            'path' => $path,
            'files' => array_map(function ($file) use ($storage) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $storage->size($file),
                    'modified' => $storage->lastModified($file),
                ];
            }, $files),
            'directories' => array_map(function ($dir) {
                return [
                    'name' => basename($dir),
                    'path' => $dir,
                ];
            }, $directories),
        ];
    }

    /**
     * Read file contents.
     */
    protected function readFile($storage, string $path): array
    {
        if (!$storage->exists($path)) {
            return ['error' => 'File not found'];
        }

        $content = $storage->get($path);
        $mimeType = $storage->mimeType($path);

        return [
            'path' => $path,
            'content' => base64_encode($content),
            'mime_type' => $mimeType,
            'size' => strlen($content),
        ];
    }

    /**
     * Get file metadata.
     */
    protected function getFileMetadata($storage, string $path): array
    {
        if (!$storage->exists($path)) {
            return ['error' => 'File not found'];
        }

        return [
            'path' => $path,
            'name' => basename($path),
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
            'size' => $storage->size($path),
            'mime_type' => $storage->mimeType($path),
            'last_modified' => $storage->lastModified($path),
            'visibility' => $storage->getVisibility($path),
        ];
    }

    /**
     * Analyze file contents.
     */
    protected function analyzeFile($storage, string $path): array
    {
        if (!$storage->exists($path)) {
            return ['error' => 'File not found'];
        }

        $content = $storage->get($path);
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $analysis = [
            'path' => $path,
            'size' => strlen($content),
            'lines' => substr_count($content, "\n") + 1,
            'words' => str_word_count($content),
            'characters' => strlen($content),
        ];

        // Add specific analysis based on file type
        if (in_array($extension, ['json'])) {
            $analysis['valid_json'] = json_decode($content) !== null;
        } elseif (in_array($extension, ['xml'])) {
            $analysis['valid_xml'] = @simplexml_load_string($content) !== false;
        } elseif (in_array($extension, ['php'])) {
            $analysis['php_tokens'] = count(token_get_all($content));
        }

        return $analysis;
    }
}
PHP;
    }

    /**
     * Generate cache resource example.
     */
    protected function generateCacheResourceExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheManagementResource extends McpResource
{
    /**
     * Get resource name.
     */
    public function getName(): string
    {
        return 'cache_management';
    }

    /**
     * Get resource description.
     */
    public function getDescription(): string
    {
        return 'Manage and monitor application cache';
    }

    /**
     * Get resource URI.
     */
    public function getUri(): string
    {
        return 'cache://management';
    }

    /**
     * Read resource.
     */
    public function read(array $parameters): array
    {
        $action = $parameters['action'] ?? 'stats';

        return match($action) {
            'stats' => $this->getCacheStats(),
            'keys' => $this->getCacheKeys($parameters),
            'get' => $this->getCacheValue($parameters),
            'tags' => $this->getCacheTags(),
            'flush' => $this->flushCache($parameters),
            default => ['error' => 'Invalid action'],
        };
    }

    /**
     * Get cache statistics.
     */
    protected function getCacheStats(): array
    {
        $driver = config('cache.default');
        $stats = [
            'driver' => $driver,
            'stores' => array_keys(config('cache.stores')),
        ];

        if ($driver === 'redis') {
            try {
                $info = Redis::info();
                $stats['redis'] = [
                    'used_memory' => $info['used_memory_human'] ?? 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_commands' => $info['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                ];
            } catch (\Exception $e) {
                $stats['redis'] = ['error' => $e->getMessage()];
            }
        }

        return $stats;
    }

    /**
     * Get cache keys.
     */
    protected function getCacheKeys(array $parameters): array
    {
        $pattern = $parameters['pattern'] ?? '*';
        $limit = $parameters['limit'] ?? 100;

        if (config('cache.default') !== 'redis') {
            return ['error' => 'Key listing only available for Redis cache'];
        }

        try {
            $keys = Redis::keys($pattern);
            $keys = array_slice($keys, 0, $limit);

            return [
                'pattern' => $pattern,
                'count' => count($keys),
                'keys' => array_map(function ($key) {
                    return [
                        'key' => $key,
                        'ttl' => Redis::ttl($key),
                        'type' => Redis::type($key),
                    ];
                }, $keys),
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache value.
     */
    protected function getCacheValue(array $parameters): array
    {
        $key = $parameters['key'] ?? null;

        if (!$key) {
            return ['error' => 'Key parameter required'];
        }

        $value = Cache::get($key);

        return [
            'key' => $key,
            'exists' => $value !== null,
            'value' => $value,
            'ttl' => config('cache.default') === 'redis' ? Redis::ttl($key) : null,
        ];
    }

    /**
     * Get cache tags.
     */
    protected function getCacheTags(): array
    {
        if (!Cache::supportsTags()) {
            return ['error' => 'Cache driver does not support tags'];
        }

        // This is a simplified example
        return [
            'supports_tags' => true,
            'info' => 'Tag information would be listed here',
        ];
    }

    /**
     * Flush cache.
     */
    protected function flushCache(array $parameters): array
    {
        $tags = $parameters['tags'] ?? null;
        $store = $parameters['store'] ?? null;

        try {
            if ($tags && Cache::supportsTags()) {
                Cache::tags($tags)->flush();
                return ['success' => true, 'flushed' => 'tags', 'tags' => $tags];
            } elseif ($store) {
                Cache::store($store)->flush();
                return ['success' => true, 'flushed' => 'store', 'store' => $store];
            } else {
                Cache::flush();
                return ['success' => true, 'flushed' => 'all'];
            }
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
PHP;
    }

    /**
     * Generate complex prompt example.
     */
    protected function generateComplexPromptExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use Illuminate\Support\Facades\View;

class ComplexAnalysisPrompt extends McpPrompt
{
    /**
     * Get prompt name.
     */
    public function getName(): string
    {
        return 'complex_analysis';
    }

    /**
     * Get prompt description.
     */
    public function getDescription(): string
    {
        return 'Generate complex multi-step analysis prompts with context';
    }

    /**
     * Get prompt arguments.
     */
    public function getArguments(): array
    {
        return [
            [
                'name' => 'data_type',
                'type' => 'string',
                'description' => 'Type of data to analyze',
                'required' => true,
            ],
            [
                'name' => 'context',
                'type' => 'object',
                'description' => 'Additional context for analysis',
                'required' => false,
            ],
            [
                'name' => 'depth',
                'type' => 'string',
                'description' => 'Analysis depth',
                'enum' => ['basic', 'detailed', 'comprehensive'],
                'default' => 'detailed',
            ],
            [
                'name' => 'format',
                'type' => 'string',
                'description' => 'Output format preference',
                'enum' => ['narrative', 'bullet_points', 'structured'],
                'default' => 'structured',
            ],
        ];
    }

    /**
     * Render the prompt.
     */
    public function render(array $arguments): array
    {
        $dataType = $arguments['data_type'];
        $context = $arguments['context'] ?? [];
        $depth = $arguments['depth'] ?? 'detailed';
        $format = $arguments['format'] ?? 'structured';

        $messages = [];

        // System message with role and constraints
        $messages[] = [
            'role' => 'system',
            'content' => [
                'type' => 'text',
                'text' => $this->buildSystemPrompt($dataType, $depth, $format),
            ],
        ];

        // Add context if provided
        if (!empty($context)) {
            $messages[] = [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => "Context for analysis:\n" . json_encode($context, JSON_PRETTY_PRINT),
                ],
            ];
        }

        // Main analysis request
        $messages[] = [
            'role' => 'user',
            'content' => [
                'type' => 'text',
                'text' => $this->buildAnalysisRequest($dataType, $depth),
            ],
        ];

        // Add examples for few-shot learning
        $messages = array_merge($messages, $this->getExampleMessages($dataType));

        return ['messages' => $messages];
    }

    /**
     * Build system prompt.
     */
    protected function buildSystemPrompt(string $dataType, string $depth, string $format): string
    {
        $prompt = "You are an expert data analyst specializing in {$dataType} analysis.\n\n";
        
        $prompt .= "Analysis Parameters:\n";
        $prompt .= "- Depth Level: {$depth}\n";
        $prompt .= "- Output Format: {$format}\n\n";
        
        $prompt .= "Guidelines:\n";
        
        switch ($depth) {
            case 'basic':
                $prompt .= "- Provide high-level insights\n";
                $prompt .= "- Focus on key findings only\n";
                $prompt .= "- Keep explanations concise\n";
                break;
            case 'detailed':
                $prompt .= "- Include supporting evidence\n";
                $prompt .= "- Explain methodologies used\n";
                $prompt .= "- Provide actionable recommendations\n";
                break;
            case 'comprehensive':
                $prompt .= "- Perform exhaustive analysis\n";
                $prompt .= "- Include statistical validation\n";
                $prompt .= "- Consider edge cases and limitations\n";
                $prompt .= "- Provide detailed methodology\n";
                break;
        }

        switch ($format) {
            case 'narrative':
                $prompt .= "\nPresent findings in a flowing narrative style.";
                break;
            case 'bullet_points':
                $prompt .= "\nUse bullet points for all key findings.";
                break;
            case 'structured':
                $prompt .= "\nOrganize output with clear sections and subsections.";
                break;
        }

        return $prompt;
    }

    /**
     * Build analysis request.
     */
    protected function buildAnalysisRequest(string $dataType, string $depth): string
    {
        $steps = [
            'basic' => [
                '1. Identify main patterns',
                '2. Summarize key findings',
                '3. Provide brief recommendations',
            ],
            'detailed' => [
                '1. Data overview and quality assessment',
                '2. Pattern identification and analysis',
                '3. Statistical significance testing',
                '4. Correlation analysis',
                '5. Actionable recommendations',
                '6. Limitations and considerations',
            ],
            'comprehensive' => [
                '1. Comprehensive data profiling',
                '2. Multi-dimensional pattern analysis',
                '3. Advanced statistical modeling',
                '4. Predictive insights',
                '5. Risk assessment',
                '6. Strategic recommendations',
                '7. Implementation roadmap',
                '8. Success metrics definition',
            ],
        ];

        $request = "Please analyze the {$dataType} data following these steps:\n\n";
        foreach ($steps[$depth] as $step) {
            $request .= "{$step}\n";
        }

        return $request;
    }

    /**
     * Get example messages for few-shot learning.
     */
    protected function getExampleMessages(string $dataType): array
    {
        // In a real implementation, these would be tailored to the data type
        return [
            [
                'role' => 'assistant',
                'content' => [
                    'type' => 'text',
                    'text' => "I'll analyze the {$dataType} data according to the specified parameters...",
                ],
            ],
        ];
    }
}
PHP;
    }

    /**
     * Generate custom transport example.
     */
    protected function generateCustomTransportExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Transports;

use JTD\LaravelMCP\Transport\TransportInterface;
use Illuminate\Support\Facades\Log;

class WebSocketTransport implements TransportInterface
{
    protected $connection;
    protected array $config;
    protected bool $connected = false;

    /**
     * Initialize transport.
     */
    public function initialize(array $config = []): void
    {
        $this->config = array_merge([
            'host' => '127.0.0.1',
            'port' => 8080,
            'timeout' => 30,
            'ssl' => false,
        ], $config);
    }

    /**
     * Start transport.
     */
    public function start(): void
    {
        $protocol = $this->config['ssl'] ? 'wss' : 'ws';
        $url = "{$protocol}://{$this->config['host']}:{$this->config['port']}/mcp";

        // Initialize WebSocket connection
        // This is a simplified example - real implementation would use a WebSocket library
        $this->connected = true;
        
        Log::info('WebSocket transport started', ['url' => $url]);
    }

    /**
     * Stop transport.
     */
    public function stop(): void
    {
        if ($this->connection) {
            // Close WebSocket connection
            $this->connected = false;
        }
        
        Log::info('WebSocket transport stopped');
    }

    /**
     * Send message.
     */
    public function send(array $message): void
    {
        if (!$this->connected) {
            throw new \RuntimeException('WebSocket not connected');
        }

        $json = json_encode($message);
        // Send via WebSocket
        Log::debug('WebSocket send', ['message' => $json]);
    }

    /**
     * Receive message.
     */
    public function receive(): ?array
    {
        if (!$this->connected) {
            return null;
        }

        // Receive from WebSocket
        // This is a simplified example
        return null;
    }

    /**
     * Check if transport is running.
     */
    public function isRunning(): bool
    {
        return $this->connected;
    }

    /**
     * Handle incoming WebSocket message.
     */
    protected function handleMessage(string $data): void
    {
        try {
            $message = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid JSON');
            }

            // Process message through MCP handler
            $response = app('mcp.server')->handleRequest($message);
            
            if ($response) {
                $this->send($response);
            }
        } catch (\Exception $e) {
            Log::error('WebSocket message handling error', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
        }
    }
}
PHP;
    }

    /**
     * Generate middleware example.
     */
    protected function generateMiddlewareExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class McpRateLimitMiddleware
{
    /**
     * Handle an incoming MCP request.
     */
    public function handle(Request $request, Closure $next)
    {
        $key = $this->resolveRequestKey($request);
        $maxAttempts = $this->getMaxAttempts($request);
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->buildRateLimitResponse($key);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        $response = $next($request);

        return $this->addRateLimitHeaders(
            $response,
            $maxAttempts,
            RateLimiter::remaining($key, $maxAttempts)
        );
    }

    /**
     * Resolve request signature key.
     */
    protected function resolveRequestKey(Request $request): string
    {
        $method = $request->input('method', 'unknown');
        $clientId = $request->header('X-Client-Id', $request->ip());
        
        return "mcp:{$clientId}:{$method}";
    }

    /**
     * Get max attempts based on method.
     */
    protected function getMaxAttempts(Request $request): int
    {
        $method = $request->input('method', 'unknown');
        
        // Different limits for different methods
        $limits = [
            'tools/call' => 60,
            'resources/read' => 100,
            'prompts/get' => 200,
            'initialize' => 10,
        ];

        return $limits[$method] ?? 100;
    }

    /**
     * Build rate limit response.
     */
    protected function buildRateLimitResponse(string $key): \Illuminate\Http\JsonResponse
    {
        $retryAfter = RateLimiter::availableIn($key);
        
        Log::warning('MCP rate limit exceeded', [
            'key' => $key,
            'retry_after' => $retryAfter,
        ]);

        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32000,
                'message' => 'Rate limit exceeded',
                'data' => [
                    'retry_after' => $retryAfter,
                ],
            ],
            'id' => request()->input('id'),
        ], 429);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addRateLimitHeaders($response, int $maxAttempts, int $remainingAttempts)
    {
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => $remainingAttempts,
        ]);
    }
}
PHP;
    }

    /**
     * Generate event-driven example.
     */
    protected function generateEventDrivenExample(): string
    {
        return <<<'PHP'
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use App\Events\McpToolExecuted;
use App\Jobs\ProcessMcpResult;

class EventDrivenTool extends McpTool
{
    /**
     * Get tool name.
     */
    public function getName(): string
    {
        return 'event_driven_processor';
    }

    /**
     * Get tool description.
     */
    public function getDescription(): string
    {
        return 'Process tasks asynchronously with event-driven architecture';
    }

    /**
     * Get input schema.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'task_type' => [
                    'type' => 'string',
                    'enum' => ['analyze', 'transform', 'aggregate'],
                    'description' => 'Type of processing task',
                ],
                'data' => [
                    'type' => 'array',
                    'description' => 'Data to process',
                ],
                'async' => [
                    'type' => 'boolean',
                    'description' => 'Process asynchronously',
                    'default' => false,
                ],
                'webhook_url' => [
                    'type' => 'string',
                    'format' => 'uri',
                    'description' => 'Webhook URL for async results',
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => ['low', 'normal', 'high'],
                    'default' => 'normal',
                ],
            ],
            'required' => ['task_type', 'data'],
        ];
    }

    /**
     * Execute the tool.
     */
    public function execute(array $parameters): array
    {
        $taskType = $parameters['task_type'];
        $data = $parameters['data'];
        $async = $parameters['async'] ?? false;
        $webhookUrl = $parameters['webhook_url'] ?? null;
        $priority = $parameters['priority'] ?? 'normal';

        // Fire pre-execution event
        Event::dispatch(new McpToolExecuted($this->getName(), $parameters, 'started'));

        if ($async) {
            // Process asynchronously
            $jobId = $this->dispatchAsyncJob($taskType, $data, $webhookUrl, $priority);
            
            // Fire queued event
            Event::dispatch(new McpToolExecuted($this->getName(), $parameters, 'queued'));
            
            return [
                'success' => true,
                'async' => true,
                'job_id' => $jobId,
                'status' => 'queued',
                'webhook_url' => $webhookUrl,
            ];
        }

        // Process synchronously
        try {
            $result = $this->processTask($taskType, $data);
            
            // Fire completion event
            Event::dispatch(new McpToolExecuted($this->getName(), $parameters, 'completed', $result));
            
            return [
                'success' => true,
                'async' => false,
                'result' => $result,
            ];
        } catch (\Exception $e) {
            // Fire error event
            Event::dispatch(new McpToolExecuted($this->getName(), $parameters, 'failed', null, $e->getMessage()));
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Dispatch async job.
     */
    protected function dispatchAsyncJob(string $taskType, array $data, ?string $webhookUrl, string $priority): string
    {
        $jobId = uniqid('mcp_job_');
        
        $job = new ProcessMcpResult([
            'job_id' => $jobId,
            'tool' => $this->getName(),
            'task_type' => $taskType,
            'data' => $data,
            'webhook_url' => $webhookUrl,
        ]);

        // Dispatch to appropriate queue based on priority
        $queue = match($priority) {
            'high' => 'high-priority',
            'low' => 'low-priority',
            default => 'default',
        };

        Queue::pushOn($queue, $job);

        return $jobId;
    }

    /**
     * Process task synchronously.
     */
    protected function processTask(string $taskType, array $data): array
    {
        return match($taskType) {
            'analyze' => $this->analyzeData($data),
            'transform' => $this->transformData($data),
            'aggregate' => $this->aggregateData($data),
            default => throw new \InvalidArgumentException("Unknown task type: {$taskType}"),
        };
    }

    /**
     * Analyze data.
     */
    protected function analyzeData(array $data): array
    {
        return [
            'count' => count($data),
            'types' => array_map('gettype', $data),
            'summary' => 'Analysis complete',
        ];
    }

    /**
     * Transform data.
     */
    protected function transformData(array $data): array
    {
        return array_map(function ($item) {
            if (is_string($item)) {
                return strtoupper($item);
            }
            if (is_numeric($item)) {
                return $item * 2;
            }
            return $item;
        }, $data);
    }

    /**
     * Aggregate data.
     */
    protected function aggregateData(array $data): array
    {
        $numbers = array_filter($data, 'is_numeric');
        
        return [
            'sum' => array_sum($numbers),
            'average' => count($numbers) > 0 ? array_sum($numbers) / count($numbers) : 0,
            'min' => count($numbers) > 0 ? min($numbers) : null,
            'max' => count($numbers) > 0 ? max($numbers) : null,
            'count' => count($numbers),
        ];
    }
}
PHP;
    }

    /**
     * Extract class name from code.
     */
    protected function extractClassName(string $code): ?string
    {
        if (preg_match('/class\s+(\w+)/', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract namespace from code.
     */
    protected function extractNamespace(string $code): ?string
    {
        if (preg_match('/namespace\s+([^;]+);/', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate PHP syntax.
     */
    protected function validateSyntax(string $code): array
    {
        $errors = [];

        // Create temp file
        $tempFile = tempnam(sys_get_temp_dir(), 'mcp_syntax_').'.php';
        file_put_contents($tempFile, $code);

        // Check syntax
        $output = shell_exec("php -l {$tempFile} 2>&1");

        if (strpos($output, 'No syntax errors') === false) {
            $errors[] = trim($output);
        }

        // Clean up
        unlink($tempFile);

        return $errors;
    }

    /**
     * Validate base class.
     */
    protected function validateBaseClass(string $code, string $type): bool
    {
        $expectedBase = 'Mcp'.ucfirst($type);

        return strpos($code, "extends {$expectedBase}") !== false ||
               strpos($code, "extends \\JTD\\LaravelMCP\\Abstracts\\{$expectedBase}") !== false;
    }

    /**
     * Validate required methods.
     */
    protected function validateRequiredMethods(string $code, string $type): array
    {
        $errors = [];

        $requiredMethods = match ($type) {
            'tool' => ['getName', 'getDescription', 'execute'],
            'resource' => ['getName', 'getDescription', 'getUri', 'read'],
            'prompt' => ['getName', 'getDescription', 'render'],
            default => [],
        };

        foreach ($requiredMethods as $method) {
            if (! preg_match("/public\s+function\s+{$method}\s*\(/", $code)) {
                $errors[] = "Missing required method: {$method}()";
            }
        }

        return $errors;
    }

    /**
     * Detect example type from code.
     */
    protected function detectExampleType(string $code): string
    {
        if (strpos($code, 'extends McpTool') !== false) {
            return 'tool';
        } elseif (strpos($code, 'extends McpResource') !== false) {
            return 'resource';
        } elseif (strpos($code, 'extends McpPrompt') !== false) {
            return 'prompt';
        } elseif (strpos($code, 'implements TransportInterface') !== false) {
            return 'transport';
        } else {
            return 'unknown';
        }
    }

    /**
     * Get validation results.
     */
    public function getValidationResults(): array
    {
        return $this->validationResults;
    }

    /**
     * Clean up temporary directory.
     */
    public function __destruct()
    {
        if ($this->tempDir && is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
    }
}
