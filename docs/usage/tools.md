# Tools Documentation

MCP Tools are executable functions that AI clients can call to perform specific actions within your Laravel application. This guide covers everything you need to know about creating and managing Tools.

## What are MCP Tools?

MCP Tools are the "functions" that AI clients can execute. They're similar to API endpoints but specifically designed for AI interactions. Tools can:

- Execute business logic
- Interact with databases
- Call external APIs
- Perform calculations
- Trigger workflows
- Generate content

## Basic Tool Structure

All MCP Tools extend the `McpTool` abstract class:

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class MyTool extends McpTool
{
    protected string $name = 'my_tool';
    protected string $description = 'What this tool does';
    
    protected array $parameterSchema = [
        // Parameter definitions
    ];
    
    protected function handle(array $parameters): mixed
    {
        // Tool implementation
        return ['result' => 'success'];
    }
}
```

## Creating Your First Tool

### Step 1: Generate the Tool

```bash
php artisan make:mcp-tool WeatherTool
```

### Step 2: Define the Tool

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\Http;

class WeatherTool extends McpTool
{
    protected string $name = 'weather';
    protected string $description = 'Get current weather information for a city';
    
    protected array $parameterSchema = [
        'city' => [
            'type' => 'string',
            'description' => 'The city name',
            'required' => true,
        ],
        'units' => [
            'type' => 'string', 
            'description' => 'Temperature units',
            'enum' => ['celsius', 'fahrenheit'],
            'required' => false,
        ],
    ];

    protected function handle(array $parameters): mixed
    {
        $city = $parameters['city'];
        $units = $parameters['units'] ?? 'celsius';
        
        // Call weather API
        $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $city,
            'appid' => config('services.openweather.key'),
            'units' => $units === 'celsius' ? 'metric' : 'imperial',
        ]);
        
        if (!$response->successful()) {
            throw new \Exception('Weather data not available');
        }
        
        $data = $response->json();
        
        return [
            'city' => $data['name'],
            'country' => $data['sys']['country'],
            'temperature' => $data['main']['temp'],
            'description' => $data['weather'][0]['description'],
            'humidity' => $data['main']['humidity'],
            'units' => $units,
        ];
    }
}
```

## Parameter Schema Definition

The parameter schema defines what inputs your tool accepts and validates them:

### Basic Types

```php
protected array $parameterSchema = [
    'text_param' => [
        'type' => 'string',
        'description' => 'A text parameter',
        'required' => true,
        'minLength' => 1,
        'maxLength' => 100,
    ],
    'number_param' => [
        'type' => 'number',
        'description' => 'A numeric parameter',
        'required' => false,
        'minimum' => 0,
        'maximum' => 1000,
    ],
    'integer_param' => [
        'type' => 'integer',
        'description' => 'An integer parameter',
        'required' => true,
        'minimum' => 1,
    ],
    'boolean_param' => [
        'type' => 'boolean',
        'description' => 'A true/false parameter',
        'required' => false,
    ],
];
```

### Advanced Types

```php
protected array $parameterSchema = [
    'enum_param' => [
        'type' => 'string',
        'description' => 'Choose from predefined options',
        'enum' => ['option1', 'option2', 'option3'],
        'required' => true,
    ],
    'array_param' => [
        'type' => 'array',
        'description' => 'A list of items',
        'items' => [
            'type' => 'string',
        ],
        'minItems' => 1,
        'maxItems' => 10,
        'required' => false,
    ],
    'object_param' => [
        'type' => 'object',
        'description' => 'A complex object',
        'properties' => [
            'name' => ['type' => 'string', 'required' => true],
            'age' => ['type' => 'integer', 'required' => false],
        ],
        'required' => false,
    ],
];
```

## Advanced Tool Examples

### Database Interaction Tool

```php
<?php

namespace App\Mcp\Tools;

use App\Models\User;
use JTD\LaravelMCP\Abstracts\McpTool;

class UserManagementTool extends McpTool
{
    protected string $name = 'user_management';
    protected string $description = 'Manage user accounts';
    protected bool $requiresAuth = true;
    
    protected array $parameterSchema = [
        'action' => [
            'type' => 'string',
            'description' => 'The action to perform',
            'enum' => ['create', 'update', 'delete', 'find'],
            'required' => true,
        ],
        'user_data' => [
            'type' => 'object',
            'description' => 'User data for create/update operations',
            'properties' => [
                'name' => ['type' => 'string', 'required' => true],
                'email' => ['type' => 'string', 'required' => true],
                'password' => ['type' => 'string', 'required' => false],
            ],
            'required' => false,
        ],
        'user_id' => [
            'type' => 'integer',
            'description' => 'User ID for update/delete operations',
            'required' => false,
        ],
    ];

    protected function handle(array $parameters): mixed
    {
        $action = $parameters['action'];
        
        return match ($action) {
            'create' => $this->createUser($parameters['user_data']),
            'update' => $this->updateUser($parameters['user_id'], $parameters['user_data']),
            'delete' => $this->deleteUser($parameters['user_id']),
            'find' => $this->findUser($parameters),
        };
    }
    
    private function createUser(array $data): array
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password'] ?? 'password'),
        ]);
        
        return [
            'action' => 'create',
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'message' => 'User created successfully',
        ];
    }
    
    private function updateUser(int $id, array $data): array
    {
        $user = User::findOrFail($id);
        $user->update(array_filter($data));
        
        return [
            'action' => 'update',
            'user' => $user->only(['id', 'name', 'email', 'updated_at']),
            'message' => 'User updated successfully',
        ];
    }
    
    private function deleteUser(int $id): array
    {
        $user = User::findOrFail($id);
        $user->delete();
        
        return [
            'action' => 'delete',
            'user_id' => $id,
            'message' => 'User deleted successfully',
        ];
    }
    
    private function findUser(array $params): array
    {
        $query = User::query();
        
        if (isset($params['user_data']['email'])) {
            $query->where('email', $params['user_data']['email']);
        }
        
        if (isset($params['user_data']['name'])) {
            $query->where('name', 'like', '%' . $params['user_data']['name'] . '%');
        }
        
        $users = $query->get(['id', 'name', 'email', 'created_at']);
        
        return [
            'action' => 'find',
            'users' => $users->toArray(),
            'count' => $users->count(),
        ];
    }
}
```

### File Processing Tool

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;
use Illuminate\Support\Facades\Storage;

class FileProcessorTool extends McpTool
{
    protected string $name = 'file_processor';
    protected string $description = 'Process and analyze files';
    
    protected array $parameterSchema = [
        'action' => [
            'type' => 'string',
            'description' => 'Processing action',
            'enum' => ['analyze', 'convert', 'compress'],
            'required' => true,
        ],
        'file_path' => [
            'type' => 'string',
            'description' => 'Path to the file',
            'required' => true,
        ],
        'options' => [
            'type' => 'object',
            'description' => 'Additional options',
            'properties' => [
                'output_format' => ['type' => 'string'],
                'quality' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
            ],
            'required' => false,
        ],
    ];

    protected function handle(array $parameters): mixed
    {
        $action = $parameters['action'];
        $filePath = $parameters['file_path'];
        $options = $parameters['options'] ?? [];
        
        if (!Storage::exists($filePath)) {
            throw new \Exception('File not found: ' . $filePath);
        }
        
        return match ($action) {
            'analyze' => $this->analyzeFile($filePath),
            'convert' => $this->convertFile($filePath, $options),
            'compress' => $this->compressFile($filePath, $options),
        };
    }
    
    private function analyzeFile(string $filePath): array
    {
        $fullPath = Storage::path($filePath);
        $fileInfo = new \SplFileInfo($fullPath);
        
        return [
            'path' => $filePath,
            'size' => $fileInfo->getSize(),
            'extension' => $fileInfo->getExtension(),
            'mime_type' => mime_content_type($fullPath),
            'modified' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
            'is_readable' => $fileInfo->isReadable(),
            'is_writable' => $fileInfo->isWritable(),
        ];
    }
    
    private function convertFile(string $filePath, array $options): array
    {
        // Implementation depends on file type and target format
        // This is a simplified example
        
        $outputFormat = $options['output_format'] ?? 'json';
        $content = Storage::get($filePath);
        
        $convertedContent = match ($outputFormat) {
            'json' => json_encode(['content' => $content]),
            'base64' => base64_encode($content),
            default => $content,
        };
        
        $outputPath = pathinfo($filePath, PATHINFO_FILENAME) . '.' . $outputFormat;
        Storage::put($outputPath, $convertedContent);
        
        return [
            'original_file' => $filePath,
            'converted_file' => $outputPath,
            'format' => $outputFormat,
            'message' => 'File converted successfully',
        ];
    }
    
    private function compressFile(string $filePath, array $options): array
    {
        $content = Storage::get($filePath);
        $compressed = gzcompress($content, $options['quality'] ?? 9);
        
        $compressedPath = $filePath . '.gz';
        Storage::put($compressedPath, $compressed);
        
        return [
            'original_file' => $filePath,
            'compressed_file' => $compressedPath,
            'original_size' => strlen($content),
            'compressed_size' => strlen($compressed),
            'compression_ratio' => round((1 - strlen($compressed) / strlen($content)) * 100, 2) . '%',
        ];
    }
}
```

## Authorization and Security

### Basic Authorization

```php
class SecureTool extends McpTool
{
    protected bool $requiresAuth = true;
    
    protected function authorize(array $parameters): bool
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return false;
        }
        
        // Additional authorization logic
        return auth()->user()->hasPermission('use-secure-tool');
    }
}
```

### Parameter-Based Authorization

```php
protected function authorize(array $parameters): bool
{
    if (!parent::authorize($parameters)) {
        return false;
    }
    
    // Only allow users to access their own data
    if (isset($parameters['user_id'])) {
        return auth()->id() === (int) $parameters['user_id'];
    }
    
    return true;
}
```

## Middleware Integration

### Using Laravel Middleware

```php
class MyTool extends McpTool
{
    protected array $middleware = [
        'auth:api',
        'throttle:60,1',
        'custom.middleware',
    ];
    
    protected function applyMiddleware(string $middleware, array $parameters): array
    {
        // Custom middleware application logic
        // This is called for each middleware in the array
        
        return $parameters;
    }
}
```

### Custom Middleware Logic

```php
protected function boot(): void
{
    // Custom initialization
    $this->middleware[] = function ($parameters, $next) {
        // Log tool usage
        logger('MCP Tool executed', [
            'tool' => $this->getName(),
            'parameters' => $parameters,
            'user_id' => auth()->id(),
        ]);
        
        return $next($parameters);
    };
}
```

## Error Handling

### Proper Error Responses

```php
protected function handle(array $parameters): mixed
{
    try {
        // Tool logic here
        return ['result' => 'success'];
    } catch (\InvalidArgumentException $e) {
        return [
            'error' => true,
            'message' => $e->getMessage(),
            'type' => 'validation_error',
        ];
    } catch (\Exception $e) {
        logger()->error('MCP Tool error', [
            'tool' => $this->getName(),
            'error' => $e->getMessage(),
            'parameters' => $parameters,
        ]);
        
        return [
            'error' => true,
            'message' => 'An unexpected error occurred',
            'type' => 'system_error',
        ];
    }
}
```

## Testing Tools

### Unit Testing

```php
<?php

namespace Tests\Feature\Mcp\Tools;

use App\Mcp\Tools\CalculatorTool;
use Tests\TestCase;

class CalculatorToolTest extends TestCase
{
    public function test_calculator_addition()
    {
        $tool = new CalculatorTool();
        
        $result = $tool->execute([
            'operation' => 'add',
            'a' => 5,
            'b' => 3,
        ]);
        
        $this->assertEquals(8, $result['result']);
        $this->assertEquals('add', $result['operation']);
    }
    
    public function test_calculator_division_by_zero()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $tool = new CalculatorTool();
        $tool->execute([
            'operation' => 'divide',
            'a' => 10,
            'b' => 0,
        ]);
    }
}
```

### Integration Testing

```php
public function test_tool_registration()
{
    $registry = app(\JTD\LaravelMCP\Registry\McpRegistry::class);
    $tools = $registry->getTools();
    
    $this->assertArrayHasKey('calculator', $tools);
    $this->assertInstanceOf(CalculatorTool::class, $tools['calculator']);
}
```

## Performance Optimization

### Caching Results

```php
protected function handle(array $parameters): mixed
{
    $cacheKey = 'tool.' . $this->getName() . '.' . md5(serialize($parameters));
    
    return cache()->remember($cacheKey, 300, function () use ($parameters) {
        // Expensive operation here
        return $this->performExpensiveOperation($parameters);
    });
}
```

### Async Processing

```php
use Illuminate\Support\Facades\Bus;
use App\Jobs\ProcessToolRequest;

protected function handle(array $parameters): mixed
{
    // For long-running operations, dispatch a job
    if ($parameters['async'] ?? false) {
        $jobId = Bus::dispatch(new ProcessToolRequest($this->getName(), $parameters));
        
        return [
            'job_id' => $jobId,
            'message' => 'Processing started',
            'status' => 'pending',
        ];
    }
    
    // Synchronous processing
    return $this->processSync($parameters);
}
```

## Best Practices

### 1. Clear Naming and Descriptions

```php
protected string $name = 'user_profile_updater';
protected string $description = 'Updates user profile information including name, email, and preferences';
```

### 2. Comprehensive Parameter Validation

```php
protected array $parameterSchema = [
    'email' => [
        'type' => 'string',
        'description' => 'Valid email address',
        'format' => 'email',
        'required' => true,
    ],
    'age' => [
        'type' => 'integer',
        'description' => 'Age in years',
        'minimum' => 13,
        'maximum' => 120,
        'required' => false,
    ],
];
```

### 3. Consistent Return Format

```php
protected function handle(array $parameters): mixed
{
    // Always return consistent structure
    return [
        'success' => true,
        'data' => $result,
        'message' => 'Operation completed',
        'timestamp' => now()->toISOString(),
    ];
}
```

### 4. Proper Logging

```php
protected function handle(array $parameters): mixed
{
    logger()->info('MCP Tool executed', [
        'tool' => $this->getName(),
        'parameters_count' => count($parameters),
        'user_id' => auth()->id(),
    ]);
    
    // Tool logic...
}
```

## Common Patterns

### Service Integration Pattern

```php
class NotificationTool extends McpTool
{
    public function __construct(
        private NotificationService $notificationService
    ) {
        parent::__construct();
    }
    
    protected function handle(array $parameters): mixed
    {
        return $this->notificationService->send($parameters);
    }
}
```

### Repository Pattern

```php
class DataQueryTool extends McpTool
{
    protected function handle(array $parameters): mixed
    {
        $repository = $this->make(UserRepository::class);
        
        return $repository->findBy($parameters['criteria']);
    }
}
```

### Factory Pattern

```php
protected function handle(array $parameters): mixed
{
    $processorClass = match ($parameters['type']) {
        'image' => ImageProcessor::class,
        'video' => VideoProcessor::class,
        'document' => DocumentProcessor::class,
    };
    
    $processor = $this->make($processorClass);
    
    return $processor->process($parameters);
}
```

## Troubleshooting

### Common Issues

1. **Tool not registered**: Ensure it's in the correct directory and extends `McpTool`
2. **Parameter validation fails**: Check your parameter schema matches the input
3. **Authorization errors**: Verify your `authorize()` method logic
4. **Performance issues**: Consider caching and async processing

### Debugging

```php
protected function handle(array $parameters): mixed
{
    if (config('app.debug')) {
        logger()->debug('Tool debug info', [
            'tool' => $this->getName(),
            'parameters' => $parameters,
            'user' => auth()->user()?->toArray(),
        ]);
    }
    
    // Tool logic...
}
```

---

**Next Steps:**
- Learn about [Resources](resources.md) for data access
- Explore [Prompts](prompts.md) for AI interactions
- Check the [API Reference](../api-reference.md) for detailed method documentation