# Documentation Specification

## Overview

The Documentation specification outlines the comprehensive documentation strategy for the Laravel MCP package, including developer guides, API references, tutorials, and examples specifically tailored for Laravel developers new to the Model Context Protocol.

## Documentation Structure

### Documentation Hierarchy
```
docs/
├── README.md                           # Package overview and quick start
├── INSTALLATION.md                     # Installation and setup guide
├── CONFIGURATION.md                    # Configuration reference
├── QUICK_START.md                      # Getting started tutorial
├── CHANGELOG.md                        # Version history
├── CONTRIBUTING.md                     # Contribution guidelines
├── CODE_OF_CONDUCT.md                  # Community guidelines
├── LICENSE.md                          # License information
├── guides/                             # Developer guides
│   ├── introduction-to-mcp.md          # MCP concepts for Laravel devs
│   ├── creating-tools.md               # Tool development guide
│   ├── creating-resources.md           # Resource development guide
│   ├── creating-prompts.md             # Prompt development guide
│   ├── middleware.md                   # Middleware usage guide
│   ├── validation.md                   # Validation guide
│   ├── authentication.md               # Authentication guide
│   ├── performance.md                  # Performance optimization
│   ├── debugging.md                    # Debugging and troubleshooting
│   └── deployment.md                   # Production deployment
├── examples/                           # Code examples
│   ├── basic-calculator-tool/          # Simple tool example
│   ├── database-resource/              # Database resource example
│   ├── email-prompt/                   # Prompt example
│   ├── middleware-usage/               # Middleware examples
│   ├── real-world-examples/            # Complex use cases
│   └── testing-examples/               # Testing examples
├── api/                                # API reference documentation
│   ├── tools.md                        # Tool API reference
│   ├── resources.md                    # Resource API reference
│   ├── prompts.md                      # Prompt API reference
│   ├── registry.md                     # Registry API reference
│   ├── transport.md                    # Transport API reference
│   └── commands.md                     # Artisan commands reference
└── troubleshooting/                    # Troubleshooting guides
    ├── common-issues.md                # Common problems and solutions
    ├── error-reference.md              # Error code reference
    └── faq.md                          # Frequently asked questions
```

## Auto-Generated Documentation

### Documentation Generator Implementation
```php
<?php

namespace JTD\LaravelMCP\Support;

use JTD\LaravelMCP\Registry\McpRegistry;
use Illuminate\Filesystem\Filesystem;
use ReflectionClass;

class DocumentationGenerator
{
    private McpRegistry $registry;
    private Filesystem $filesystem;
    private string $outputPath;

    public function __construct(McpRegistry $registry, Filesystem $filesystem)
    {
        $this->registry = $registry;
        $this->filesystem = $filesystem;
        $this->outputPath = base_path('docs/generated');
    }

    public function generateAll(): void
    {
        $this->ensureOutputDirectory();
        
        $this->generateToolsDocumentation();
        $this->generateResourcesDocumentation();
        $this->generatePromptsDocumentation();
        $this->generateServerInfo();
        $this->generateApiReference();
    }

    public function generateToolsDocumentation(): void
    {
        $tools = $this->registry->getTools();
        $content = $this->generateToolsMarkdown($tools);
        
        $this->filesystem->put(
            $this->outputPath . '/tools.md',
            $content
        );
    }

    public function generateResourcesDocumentation(): void
    {
        $resources = $this->registry->getResources();
        $content = $this->generateResourcesMarkdown($resources);
        
        $this->filesystem->put(
            $this->outputPath . '/resources.md',
            $content
        );
    }

    public function generatePromptsDocumentation(): void
    {
        $prompts = $this->registry->getPrompts();
        $content = $this->generatePromptsMarkdown($prompts);
        
        $this->filesystem->put(
            $this->outputPath . '/prompts.md',
            $content
        );
    }

    private function generateToolsMarkdown(array $tools): string
    {
        $content = "# Available Tools\n\n";
        $content .= "This document lists all available MCP tools in this Laravel application.\n\n";
        
        foreach ($tools as $name => $tool) {
            $content .= $this->generateToolMarkdown($name, $tool);
        }
        
        return $content;
    }

    private function generateToolMarkdown(string $name, $tool): string
    {
        $reflection = new ReflectionClass($tool);
        $docComment = $reflection->getDocComment();
        
        $markdown = "## {$name}\n\n";
        $markdown .= "**Class:** `{$reflection->getName()}`\n\n";
        $markdown .= "**Description:** {$tool->getDescription()}\n\n";
        
        // Extract documentation from docblock
        if ($docComment) {
            $docs = $this->parseDocComment($docComment);
            if (isset($docs['description'])) {
                $markdown .= $docs['description'] . "\n\n";
            }
        }
        
        // Parameters
        $inputSchema = $tool->getInputSchema();
        if (isset($inputSchema['properties']) && !empty($inputSchema['properties'])) {
            $markdown .= "### Parameters\n\n";
            
            foreach ($inputSchema['properties'] as $param => $schema) {
                $required = in_array($param, $inputSchema['required'] ?? []) ? '**Required**' : 'Optional';
                $type = $schema['type'] ?? 'mixed';
                $description = $schema['description'] ?? '';
                
                $markdown .= "- `{$param}` ({$type}) - {$required}\n";
                if ($description) {
                    $markdown .= "  {$description}\n";
                }
                
                if (isset($schema['enum'])) {
                    $values = implode(', ', array_map(fn($v) => "`$v`", $schema['enum']));
                    $markdown .= "  Allowed values: {$values}\n";
                }
                
                $markdown .= "\n";
            }
        }
        
        // Usage example
        $markdown .= "### Usage Example\n\n";
        $markdown .= "```json\n";
        $markdown .= json_encode([
            'tool' => $name,
            'parameters' => $this->generateExampleParameters($inputSchema),
        ], JSON_PRETTY_PRINT);
        $markdown .= "\n```\n\n";
        
        return $markdown;
    }

    private function generateResourcesMarkdown(array $resources): string
    {
        $content = "# Available Resources\n\n";
        $content .= "This document lists all available MCP resources in this Laravel application.\n\n";
        
        foreach ($resources as $name => $resource) {
            $content .= $this->generateResourceMarkdown($name, $resource);
        }
        
        return $content;
    }

    private function generateResourceMarkdown(string $name, $resource): string
    {
        $reflection = new ReflectionClass($resource);
        
        $markdown = "## {$name}\n\n";
        $markdown .= "**Class:** `{$reflection->getName()}`\n\n";
        $markdown .= "**Description:** {$resource->getDescription()}\n\n";
        $markdown .= "**URI Template:** `{$resource->getUriTemplate()}`\n\n";
        
        // Supported operations
        $operations = [];
        if (method_exists($resource, 'read')) $operations[] = 'read';
        if (method_exists($resource, 'list')) $operations[] = 'list';
        if (method_exists($resource, 'subscribe')) $operations[] = 'subscribe';
        
        if (!empty($operations)) {
            $markdown .= "**Supported Operations:** " . implode(', ', $operations) . "\n\n";
        }
        
        // Usage examples
        $markdown .= "### Usage Examples\n\n";
        
        if (in_array('read', $operations)) {
            $markdown .= "#### Read Resource\n";
            $markdown .= "```json\n";
            $markdown .= json_encode([
                'method' => 'resources/read',
                'params' => [
                    'uri' => str_replace(['*', '{id}'], ['123', '123'], $resource->getUriTemplate())
                ]
            ], JSON_PRETTY_PRINT);
            $markdown .= "\n```\n\n";
        }
        
        if (in_array('list', $operations)) {
            $markdown .= "#### List Resources\n";
            $markdown .= "```json\n";
            $markdown .= json_encode([
                'method' => 'resources/list'
            ], JSON_PRETTY_PRINT);
            $markdown .= "\n```\n\n";
        }
        
        return $markdown;
    }

    private function generatePromptsMarkdown(array $prompts): string
    {
        $content = "# Available Prompts\n\n";
        $content .= "This document lists all available MCP prompts in this Laravel application.\n\n";
        
        foreach ($prompts as $name => $prompt) {
            $content .= $this->generatePromptMarkdown($name, $prompt);
        }
        
        return $content;
    }

    private function generatePromptMarkdown(string $name, $prompt): string
    {
        $reflection = new ReflectionClass($prompt);
        
        $markdown = "## {$name}\n\n";
        $markdown .= "**Class:** `{$reflection->getName()}`\n\n";
        $markdown .= "**Description:** {$prompt->getDescription()}\n\n";
        
        // Arguments
        $arguments = $prompt->getArguments();
        if (!empty($arguments)) {
            $markdown .= "### Arguments\n\n";
            
            foreach ($arguments as $arg => $config) {
                $required = ($config['required'] ?? false) ? '**Required**' : 'Optional';
                $type = $config['type'] ?? 'string';
                $description = $config['description'] ?? '';
                
                $markdown .= "- `{$arg}` ({$type}) - {$required}\n";
                if ($description) {
                    $markdown .= "  {$description}\n";
                }
                $markdown .= "\n";
            }
        }
        
        // Usage example
        $markdown .= "### Usage Example\n\n";
        $markdown .= "```json\n";
        $markdown .= json_encode([
            'method' => 'prompts/get',
            'params' => [
                'name' => $name,
                'arguments' => $this->generateExampleArguments($arguments),
            ]
        ], JSON_PRETTY_PRINT);
        $markdown .= "\n```\n\n";
        
        return $markdown;
    }

    private function generateServerInfo(): void
    {
        $content = "# Server Information\n\n";
        
        $content .= "## Statistics\n\n";
        $content .= "- **Tools:** " . count($this->registry->getTools()) . "\n";
        $content .= "- **Resources:** " . count($this->registry->getResources()) . "\n";
        $content .= "- **Prompts:** " . count($this->registry->getPrompts()) . "\n\n";
        
        $content .= "## Configuration\n\n";
        $content .= "```php\n";
        $content .= "<?php\nreturn " . var_export(config('laravel-mcp'), true) . ";\n";
        $content .= "```\n\n";
        
        $content .= "*Generated at: " . now()->toISOString() . "*\n";
        
        $this->filesystem->put(
            $this->outputPath . '/server-info.md',
            $content
        );
    }

    private function generateApiReference(): void
    {
        $content = "# API Reference\n\n";
        $content .= "This is the complete API reference for this MCP server.\n\n";
        
        // Include all tools, resources, and prompts in one comprehensive reference
        $tools = $this->registry->getTools();
        $resources = $this->registry->getResources();
        $prompts = $this->registry->getPrompts();
        
        if (!empty($tools)) {
            $content .= "## Tools\n\n";
            foreach ($tools as $name => $tool) {
                $content .= $this->generateToolMarkdown($name, $tool);
            }
        }
        
        if (!empty($resources)) {
            $content .= "## Resources\n\n";
            foreach ($resources as $name => $resource) {
                $content .= $this->generateResourceMarkdown($name, $resource);
            }
        }
        
        if (!empty($prompts)) {
            $content .= "## Prompts\n\n";
            foreach ($prompts as $name => $prompt) {
                $content .= $this->generatePromptMarkdown($name, $prompt);
            }
        }
        
        $this->filesystem->put(
            $this->outputPath . '/api-reference.md',
            $content
        );
    }

    private function generateExampleParameters(array $inputSchema): array
    {
        $example = [];
        
        if (isset($inputSchema['properties'])) {
            foreach ($inputSchema['properties'] as $param => $schema) {
                $example[$param] = $this->generateExampleValue($schema);
            }
        }
        
        return $example;
    }

    private function generateExampleArguments(array $arguments): array
    {
        $example = [];
        
        foreach ($arguments as $arg => $config) {
            $example[$arg] = $this->generateExampleValue($config);
        }
        
        return $example;
    }

    private function generateExampleValue(array $schema): mixed
    {
        if (isset($schema['enum'])) {
            return $schema['enum'][0];
        }
        
        return match ($schema['type'] ?? 'string') {
            'string' => 'example_value',
            'integer' => 42,
            'number' => 3.14,
            'boolean' => true,
            'array' => ['item1', 'item2'],
            'object' => ['key' => 'value'],
            default => 'example',
        };
    }

    private function parseDocComment(string $docComment): array
    {
        $lines = explode("\n", $docComment);
        $description = [];
        $inDescription = true;
        
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B/*");
            
            if (empty($line)) {
                if ($inDescription && !empty($description)) {
                    $inDescription = false;
                }
                continue;
            }
            
            if (str_starts_with($line, '@')) {
                $inDescription = false;
                continue;
            }
            
            if ($inDescription) {
                $description[] = $line;
            }
        }
        
        return [
            'description' => implode("\n", $description),
        ];
    }

    private function ensureOutputDirectory(): void
    {
        if (!$this->filesystem->exists($this->outputPath)) {
            $this->filesystem->makeDirectory($this->outputPath, 0755, true);
        }
    }
}
```

## Documentation Content Templates

### Tool Documentation Template
```markdown
# {{ tool_name }} Tool

## Overview
{{ description }}

## Parameters
{{ parameters_table }}

## Usage Example
```json
{{ usage_example }}
```

## Response Format
```json
{{ response_example }}
```

## Error Handling
{{ error_information }}

## Implementation Notes
{{ implementation_notes }}
```

### Resource Documentation Template
```markdown
# {{ resource_name }} Resource

## Overview
{{ description }}

**URI Template:** `{{ uri_template }}`

## Supported Operations
{{ operations_list }}

## Usage Examples

### Read Operation
```json
{{ read_example }}
```

### List Operation
```json
{{ list_example }}
```

## Response Format
{{ response_format }}

## Implementation Notes
{{ implementation_notes }}
```

## Interactive Documentation

### Web-Based Documentation Interface
```php
<?php

namespace JTD\LaravelMCP\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use JTD\LaravelMCP\Support\DocumentationGenerator;
use JTD\LaravelMCP\Registry\McpRegistry;

class DocumentationController extends Controller
{
    private DocumentationGenerator $docGenerator;
    private McpRegistry $registry;

    public function __construct(DocumentationGenerator $docGenerator, McpRegistry $registry)
    {
        $this->docGenerator = $docGenerator;
        $this->registry = $registry;
    }

    public function index()
    {
        $stats = [
            'tools' => count($this->registry->getTools()),
            'resources' => count($this->registry->getResources()),
            'prompts' => count($this->registry->getPrompts()),
        ];

        return view('laravel-mcp::docs.index', compact('stats'));
    }

    public function tools()
    {
        $tools = $this->registry->getTools();
        return view('laravel-mcp::docs.tools', compact('tools'));
    }

    public function tool(string $name)
    {
        $tool = $this->registry->getTool($name);
        
        if (!$tool) {
            abort(404);
        }

        return view('laravel-mcp::docs.tool', compact('tool', 'name'));
    }

    public function resources()
    {
        $resources = $this->registry->getResources();
        return view('laravel-mcp::docs.resources', compact('resources'));
    }

    public function resource(string $name)
    {
        $resource = $this->registry->getResource($name);
        
        if (!$resource) {
            abort(404);
        }

        return view('laravel-mcp::docs.resource', compact('resource', 'name'));
    }

    public function prompts()
    {
        $prompts = $this->registry->getPrompts();
        return view('laravel-mcp::docs.prompts', compact('prompts'));
    }

    public function prompt(string $name)
    {
        $prompt = $this->registry->getPrompt($name);
        
        if (!$prompt) {
            abort(404);
        }

        return view('laravel-mcp::docs.prompt', compact('prompt', 'name'));
    }

    public function apiReference()
    {
        return view('laravel-mcp::docs.api-reference');
    }

    public function playground()
    {
        return view('laravel-mcp::docs.playground');
    }
}
```

## Documentation Artisan Command

### Generate Documentation Command
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Support\DocumentationGenerator;

class GenerateDocsCommand extends Command
{
    protected $signature = 'mcp:docs
                           {--output= : Output directory}
                           {--format=markdown : Output format (markdown|html|json)}
                           {--include=all : What to include (all|tools|resources|prompts)}';

    protected $description = 'Generate MCP server documentation';

    private DocumentationGenerator $docGenerator;

    public function __construct(DocumentationGenerator $docGenerator)
    {
        parent::__construct();
        $this->docGenerator = $docGenerator;
    }

    public function handle(): int
    {
        $this->info('Generating MCP documentation...');
        
        $include = $this->option('include');
        
        try {
            match ($include) {
                'all' => $this->docGenerator->generateAll(),
                'tools' => $this->docGenerator->generateToolsDocumentation(),
                'resources' => $this->docGenerator->generateResourcesDocumentation(),
                'prompts' => $this->docGenerator->generatePromptsDocumentation(),
                default => $this->docGenerator->generateAll(),
            };
            
            $this->info('Documentation generated successfully!');
            $this->info('Location: ' . base_path('docs/generated'));
            
            return 0;
        } catch (\Throwable $e) {
            $this->error("Failed to generate documentation: {$e->getMessage()}");
            return 1;
        }
    }
}
```

## Integration with Laravel Documentation Tools

### PHPDoc Integration
```php
/**
 * Calculate mathematical operations
 * 
 * This tool performs basic mathematical calculations including
 * addition, subtraction, multiplication, and division.
 * 
 * @mcp-tool calculator
 * @mcp-parameter operation string required The operation to perform (add|subtract|multiply|divide)
 * @mcp-parameter a number required First operand
 * @mcp-parameter b number required Second operand
 * @mcp-return number The result of the calculation
 * @mcp-throws InvalidArgumentException When division by zero is attempted
 * 
 * @example
 * {
 *   "operation": "add",
 *   "a": 5,
 *   "b": 3
 * }
 * // Returns: 8
 */
class CalculatorTool extends McpTool
{
    // Implementation
}
```

### Postman/OpenAPI Integration
```php
<?php

namespace JTD\LaravelMCP\Support;

class OpenApiGenerator
{
    public function generateOpenApiSpec(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Laravel MCP Server API',
                'version' => '1.0.0',
                'description' => 'Model Context Protocol server built with Laravel',
            ],
            'paths' => $this->generatePaths(),
            'components' => $this->generateComponents(),
        ];
    }

    private function generatePaths(): array
    {
        return [
            '/mcp' => [
                'post' => [
                    'summary' => 'Execute MCP request',
                    'requestBody' => [
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/JsonRpcRequest']
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/JsonRpcResponse']
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }
}
```