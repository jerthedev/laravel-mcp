<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Str;

/**
 * Builder for MCP extension guides.
 *
 * This class generates comprehensive guides for extending the Laravel MCP package.
 */
class ExtensionGuideBuilder
{
    /**
     * Build extension guide.
     */
    public function buildGuide(array $options = []): string
    {
        $sections = [
            $this->generateOverview(),
            $this->generateCustomToolGuide(),
            $this->generateCustomResourceGuide(),
            $this->generateCustomPromptGuide(),
            $this->generateTransportExtension(),
            $this->generateMiddlewareIntegration(),
            $this->generateEventListeners(),
            $this->generatePackageExtension(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Generate overview section.
     */
    protected function generateOverview(): string
    {
        return implode("\n", [
            '# Extending Laravel MCP',
            '',
            '## Overview',
            '',
            'This guide demonstrates how to extend the Laravel MCP package with custom',
            'components, transports, and integrations.',
            '',
            '## Extension Points',
            '',
            '1. **Custom Tools** - Add new executable functions',
            '2. **Custom Resources** - Create new data sources',
            '3. **Custom Prompts** - Build template systems',
            '4. **Transport Layers** - Implement new communication protocols',
            '5. **Middleware** - Add request/response processing',
            '6. **Event Listeners** - React to MCP events',
            '7. **Package Extensions** - Create reusable MCP packages',
        ]);
    }

    /**
     * Generate custom tool guide.
     */
    protected function generateCustomToolGuide(): string
    {
        return implode("\n", [
            '## Creating Custom Tools',
            '',
            '### Step 1: Generate Tool Class',
            '',
            '```bash',
            'php artisan make:mcp-tool MyCustomTool',
            '```',
            '',
            '### Step 2: Implement Tool Logic',
            '',
            '```php',
            '<?php',
            '',
            'namespace App\\Mcp\\Tools;',
            '',
            'use JTD\\LaravelMCP\\Abstracts\\McpTool;',
            'use JTD\\LaravelMCP\\Contracts\\McpToolInterface;',
            '',
            'class MyCustomTool extends McpTool implements McpToolInterface',
            '{',
            '    public function getName(): string',
            '    {',
            '        return \'my_custom_tool\';',
            '    }',
            '',
            '    public function getDescription(): string',
            '    {',
            '        return \'Performs custom operations\';',
            '    }',
            '',
            '    public function getInputSchema(): array',
            '    {',
            '        return [',
            '            \'type\' => \'object\',',
            '            \'properties\' => [',
            '                \'action\' => [',
            '                    \'type\' => \'string\',',
            '                    \'description\' => \'Action to perform\',',
            '                ],',
            '            ],',
            '            \'required\' => [\'action\'],',
            '        ];',
            '    }',
            '',
            '    public function execute(array $parameters): array',
            '    {',
            '        // Your custom logic here',
            '        return [\'result\' => \'success\'];',
            '    }',
            '}',
            '```',
            '',
            '### Step 3: Register Tool (Optional)',
            '',
            'Tools are auto-discovered by default. For manual registration:',
            '',
            '```php',
            '// In a service provider',
            'use JTD\\LaravelMCP\\Facades\\Mcp;',
            '',
            'public function boot()',
            '{',
            '    Mcp::registerTool(MyCustomTool::class);',
            '}',
            '```',
        ]);
    }

    /**
     * Generate custom resource guide.
     */
    protected function generateCustomResourceGuide(): string
    {
        return implode("\n", [
            '## Creating Custom Resources',
            '',
            '### Resource with Dynamic URIs',
            '',
            '```php',
            'class DynamicResource extends McpResource',
            '{',
            '    public function getUriTemplates(): array',
            '    {',
            '        return [',
            '            [',
            '                \'uri\' => \'db://table/{table_name}\',',
            '                \'name\' => \'Database Table\',',
            '                \'description\' => \'Access database tables\',',
            '            ],',
            '            [',
            '                \'uri\' => \'api://endpoint/{endpoint}\',',
            '                \'name\' => \'API Endpoint\',',
            '                \'description\' => \'Access API endpoints\',',
            '            ],',
            '        ];',
            '    }',
            '',
            '    public function read(array $parameters): array',
            '    {',
            '        $uri = $parameters[\'uri\'] ?? \'\';',
            '        ',
            '        if (str_starts_with($uri, \'db://\')) {',
            '            return $this->readDatabase($uri);',
            '        }',
            '        ',
            '        if (str_starts_with($uri, \'api://\')) {',
            '            return $this->readApi($uri);',
            '        }',
            '        ',
            '        return [\'error\' => \'Unknown URI scheme\'];',
            '    }',
            '}',
            '```',
            '',
            '### Streaming Resource',
            '',
            '```php',
            'class StreamingResource extends McpResource',
            '{',
            '    public function supportsStreaming(): bool',
            '    {',
            '        return true;',
            '    }',
            '',
            '    public function stream(array $parameters): \\Generator',
            '    {',
            '        $file = $parameters[\'file\'] ?? \'\';',
            '        ',
            '        $handle = fopen($file, \'r\');',
            '        while (!feof($handle)) {',
            '            yield fread($handle, 8192);',
            '        }',
            '        fclose($handle);',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Generate custom prompt guide.
     */
    protected function generateCustomPromptGuide(): string
    {
        return implode("\n", [
            '## Creating Custom Prompts',
            '',
            '### Dynamic Prompt with Context',
            '',
            '```php',
            'class ContextualPrompt extends McpPrompt',
            '{',
            '    public function render(array $arguments): array',
            '    {',
            '        $context = $this->gatherContext($arguments);',
            '        ',
            '        return [',
            '            \'messages\' => [',
            '                $this->buildSystemMessage($context),',
            '                $this->buildUserMessage($arguments),',
            '            ],',
            '        ];',
            '    }',
            '',
            '    protected function gatherContext(array $arguments): array',
            '    {',
            '        // Gather context from database, cache, etc.',
            '        return [',
            '            \'user_preferences\' => $this->getUserPreferences(),',
            '            \'recent_history\' => $this->getRecentHistory(),',
            '            \'domain_knowledge\' => $this->getDomainKnowledge($arguments),',
            '        ];',
            '    }',
            '}',
            '```',
            '',
            '### Multi-Modal Prompt',
            '',
            '```php',
            'class MultiModalPrompt extends McpPrompt',
            '{',
            '    public function render(array $arguments): array',
            '    {',
            '        $messages = [];',
            '        ',
            '        // Text content',
            '        $messages[] = [',
            '            \'role\' => \'user\',',
            '            \'content\' => [',
            '                \'type\' => \'text\',',
            '                \'text\' => $arguments[\'text\'] ?? \'\',',
            '            ],',
            '        ];',
            '        ',
            '        // Image content',
            '        if (isset($arguments[\'image\'])) {',
            '            $messages[] = [',
            '                \'role\' => \'user\',',
            '                \'content\' => [',
            '                    \'type\' => \'image\',',
            '                    \'data\' => base64_encode(file_get_contents($arguments[\'image\'])),',
            '                    \'mime_type\' => mime_content_type($arguments[\'image\']),',
            '                ],',
            '            ];',
            '        }',
            '        ',
            '        return [\'messages\' => $messages];',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Generate transport extension guide.
     */
    protected function generateTransportExtension(): string
    {
        return implode("\n", [
            '## Creating Custom Transports',
            '',
            '### Implementing Transport Interface',
            '',
            '```php',
            'namespace App\\Mcp\\Transports;',
            '',
            'use JTD\\LaravelMCP\\Transport\\TransportInterface;',
            '',
            'class CustomTransport implements TransportInterface',
            '{',
            '    protected array $config;',
            '    protected $connection;',
            '',
            '    public function initialize(array $config = []): void',
            '    {',
            '        $this->config = $config;',
            '        // Initialize connection',
            '    }',
            '',
            '    public function start(): void',
            '    {',
            '        // Start listening for messages',
            '    }',
            '',
            '    public function stop(): void',
            '    {',
            '        // Stop transport and cleanup',
            '    }',
            '',
            '    public function send(array $message): void',
            '    {',
            '        // Send message through transport',
            '    }',
            '',
            '    public function receive(): ?array',
            '    {',
            '        // Receive and return message',
            '        return null;',
            '    }',
            '',
            '    public function isRunning(): bool',
            '    {',
            '        // Check if transport is active',
            '        return false;',
            '    }',
            '}',
            '```',
            '',
            '### Registering Custom Transport',
            '',
            '```php',
            '// In AppServiceProvider',
            'use JTD\\LaravelMCP\\Transport\\TransportManager;',
            '',
            'public function boot()',
            '{',
            '    $manager = app(TransportManager::class);',
            '    ',
            '    $manager->extend(\'custom\', function ($config) {',
            '        return new CustomTransport($config);',
            '    });',
            '}',
            '```',
        ]);
    }

    /**
     * Generate middleware integration guide.
     */
    protected function generateMiddlewareIntegration(): string
    {
        return implode("\n", [
            '## Adding Custom Middleware',
            '',
            '### Request Middleware',
            '',
            '```php',
            'namespace App\\Mcp\\Middleware;',
            '',
            'use Closure;',
            '',
            'class ValidateApiKeyMiddleware',
            '{',
            '    public function handle($request, Closure $next)',
            '    {',
            '        $apiKey = $request->header(\'X-API-Key\');',
            '        ',
            '        if (!$this->isValidApiKey($apiKey)) {',
            '            return response()->json([',
            '                \'jsonrpc\' => \'2.0\',',
            '                \'error\' => [',
            '                    \'code\' => -32000,',
            '                    \'message\' => \'Invalid API key\',',
            '                ],',
            '                \'id\' => $request->input(\'id\'),',
            '            ], 401);',
            '        }',
            '        ',
            '        return $next($request);',
            '    }',
            '',
            '    protected function isValidApiKey(?string $key): bool',
            '    {',
            '        // Validate API key',
            '        return $key === config(\'mcp.api_key\');',
            '    }',
            '}',
            '```',
            '',
            '### Registering Middleware',
            '',
            '```php',
            '// In config/laravel-mcp.php',
            '\'routes\' => [',
            '    \'middleware\' => [',
            '        \'api\',',
            '        \\App\\Mcp\\Middleware\\ValidateApiKeyMiddleware::class,',
            '    ],',
            '],',
            '```',
        ]);
    }

    /**
     * Generate event listeners guide.
     */
    protected function generateEventListeners(): string
    {
        return implode("\n", [
            '## Creating Event Listeners',
            '',
            '### Available Events',
            '',
            '- `McpInitialized` - Server initialized',
            '- `McpRequestReceived` - Request received',
            '- `McpRequestProcessed` - Request completed',
            '- `McpToolExecuted` - Tool executed',
            '- `McpResourceRead` - Resource accessed',
            '- `McpPromptRendered` - Prompt rendered',
            '',
            '### Creating a Listener',
            '',
            '```php',
            'namespace App\\Listeners;',
            '',
            'use JTD\\LaravelMCP\\Events\\McpToolExecuted;',
            'use Illuminate\\Support\\Facades\\Log;',
            '',
            'class LogToolExecution',
            '{',
            '    public function handle(McpToolExecuted $event)',
            '    {',
            '        Log::info(\'MCP Tool Executed\', [',
            '            \'tool\' => $event->tool,',
            '            \'parameters\' => $event->parameters,',
            '            \'result\' => $event->result,',
            '            \'duration\' => $event->duration,',
            '        ]);',
            '        ',
            '        // Send metrics to monitoring service',
            '        $this->sendMetrics($event);',
            '    }',
            '',
            '    protected function sendMetrics(McpToolExecuted $event)',
            '    {',
            '        // Send to Datadog, New Relic, etc.',
            '    }',
            '}',
            '```',
            '',
            '### Registering Listeners',
            '',
            '```php',
            '// In EventServiceProvider',
            'protected $listen = [',
            '    \\JTD\\LaravelMCP\\Events\\McpToolExecuted::class => [',
            '        \\App\\Listeners\\LogToolExecution::class,',
            '    ],',
            '];',
            '```',
        ]);
    }

    /**
     * Generate package extension guide.
     */
    protected function generatePackageExtension(): string
    {
        return implode("\n", [
            '## Creating MCP Extension Packages',
            '',
            '### Package Structure',
            '',
            '```',
            'my-mcp-extension/',
            '├── src/',
            '│   ├── Tools/',
            '│   ├── Resources/',
            '│   ├── Prompts/',
            '│   └── MyMcpExtensionServiceProvider.php',
            '├── config/',
            '│   └── my-mcp-extension.php',
            '├── tests/',
            '└── composer.json',
            '```',
            '',
            '### Service Provider',
            '',
            '```php',
            'namespace MyVendor\\MyMcpExtension;',
            '',
            'use Illuminate\\Support\\ServiceProvider;',
            'use JTD\\LaravelMCP\\Facades\\Mcp;',
            '',
            'class MyMcpExtensionServiceProvider extends ServiceProvider',
            '{',
            '    public function register()',
            '    {',
            '        $this->mergeConfigFrom(',
            '            __DIR__.\'/../config/my-mcp-extension.php\',',
            '            \'my-mcp-extension\'',
            '        );',
            '    }',
            '',
            '    public function boot()',
            '    {',
            '        // Register tools',
            '        Mcp::registerTool(\\MyVendor\\MyMcpExtension\\Tools\\CustomTool::class);',
            '        ',
            '        // Register resources',
            '        Mcp::registerResource(\\MyVendor\\MyMcpExtension\\Resources\\CustomResource::class);',
            '        ',
            '        // Register prompts',
            '        Mcp::registerPrompt(\\MyVendor\\MyMcpExtension\\Prompts\\CustomPrompt::class);',
            '        ',
            '        // Publish config',
            '        if ($this->app->runningInConsole()) {',
            '            $this->publishes([',
            '                __DIR__.\'/../config/my-mcp-extension.php\' => config_path(\'my-mcp-extension.php\'),',
            '            ], \'config\');',
            '        }',
            '    }',
            '}',
            '```',
            '',
            '### Composer Configuration',
            '',
            '```json',
            '{',
            '    "name": "my-vendor/my-mcp-extension",',
            '    "description": "MCP extension for Laravel",',
            '    "require": {',
            '        "php": "^8.2",',
            '        "jtd/laravel-mcp": "^1.0"',
            '    },',
            '    "autoload": {',
            '        "psr-4": {',
            '            "MyVendor\\\\MyMcpExtension\\\\": "src/"',
            '        }',
            '    },',
            '    "extra": {',
            '        "laravel": {',
            '            "providers": [',
            '                "MyVendor\\\\MyMcpExtension\\\\MyMcpExtensionServiceProvider"',
            '            ]',
            '        }',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Validate extension guide.
     */
    public function validateGuide(string $guide): array
    {
        $validation = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check for required sections
        $requiredSections = [
            '## Creating Custom Tools',
            '## Creating Custom Resources',
            '## Creating Custom Prompts',
        ];

        foreach ($requiredSections as $section) {
            if (strpos($guide, $section) === false) {
                $validation['warnings'][] = "Missing section: {$section}";
            }
        }

        // Check for code examples
        if (substr_count($guide, '```php') < 5) {
            $validation['warnings'][] = 'Guide should contain more PHP code examples';
        }

        // Check for bash examples
        if (substr_count($guide, '```bash') < 1) {
            $validation['warnings'][] = 'Guide should contain command-line examples';
        }

        // Validate code blocks are properly closed
        $openBlocks = substr_count($guide, '```');
        if ($openBlocks % 2 !== 0) {
            $validation['valid'] = false;
            $validation['errors'][] = 'Unclosed code block detected';
        }

        return $validation;
    }

    /**
     * Generate extension template.
     */
    public function generateExtensionTemplate(string $type, string $name): string
    {
        $className = Str::studly($name);
        $namespace = 'App\\Mcp\\'.Str::plural(ucfirst($type));

        return match ($type) {
            'tool' => $this->generateToolTemplate($className, $namespace),
            'resource' => $this->generateResourceTemplate($className, $namespace),
            'prompt' => $this->generatePromptTemplate($className, $namespace),
            default => throw new \InvalidArgumentException("Unknown type: {$type}"),
        };
    }

    /**
     * Generate tool template.
     */
    protected function generateToolTemplate(string $className, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use JTD\\LaravelMCP\\Abstracts\\McpTool;

class {$className} extends McpTool
{
    /**
     * Get tool name.
     */
    public function getName(): string
    {
        return Str::snake(class_basename(\$this));
    }

    /**
     * Get tool description.
     */
    public function getDescription(): string
    {
        return 'Description of what this tool does';
    }

    /**
     * Get input schema.
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                // Define your parameters here
            ],
            'required' => [],
        ];
    }

    /**
     * Execute the tool.
     */
    public function execute(array \$parameters): array
    {
        // Implement your tool logic here
        
        return [
            'success' => true,
            'result' => 'Tool executed successfully',
        ];
    }
}
PHP;
    }

    /**
     * Generate resource template.
     */
    protected function generateResourceTemplate(string $className, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use JTD\\LaravelMCP\\Abstracts\\McpResource;

class {$className} extends McpResource
{
    /**
     * Get resource name.
     */
    public function getName(): string
    {
        return Str::snake(class_basename(\$this));
    }

    /**
     * Get resource description.
     */
    public function getDescription(): string
    {
        return 'Description of this resource';
    }

    /**
     * Get resource URI.
     */
    public function getUri(): string
    {
        return 'custom://' . \$this->getName();
    }

    /**
     * Read resource.
     */
    public function read(array \$parameters): array
    {
        // Implement resource reading logic
        
        return [
            'content' => 'Resource content',
            'metadata' => [],
        ];
    }
}
PHP;
    }

    /**
     * Generate prompt template.
     */
    protected function generatePromptTemplate(string $className, string $namespace): string
    {
        return <<<PHP
<?php

namespace {$namespace};

use JTD\\LaravelMCP\\Abstracts\\McpPrompt;

class {$className} extends McpPrompt
{
    /**
     * Get prompt name.
     */
    public function getName(): string
    {
        return Str::snake(class_basename(\$this));
    }

    /**
     * Get prompt description.
     */
    public function getDescription(): string
    {
        return 'Description of this prompt template';
    }

    /**
     * Get prompt arguments.
     */
    public function getArguments(): array
    {
        return [
            [
                'name' => 'example_arg',
                'type' => 'string',
                'description' => 'An example argument',
                'required' => false,
            ],
        ];
    }

    /**
     * Render the prompt.
     */
    public function render(array \$arguments): array
    {
        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => 'Your prompt content here',
                    ],
                ],
            ],
        ];
    }
}
PHP;
    }
}
