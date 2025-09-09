<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JTD\LaravelMCP\Registry\McpRegistry;
use JTD\LaravelMCP\Registry\PromptRegistry;
use JTD\LaravelMCP\Registry\ResourceRegistry;
use JTD\LaravelMCP\Registry\ToolRegistry;

/**
 * Documentation generator for MCP server.
 *
 * This class generates comprehensive documentation for MCP servers,
 * including component documentation, API references, and usage guides.
 */
class DocumentationGenerator
{
    /**
     * Registry instances.
     */
    protected McpRegistry $registry;

    protected ToolRegistry $toolRegistry;

    protected ResourceRegistry $resourceRegistry;

    protected PromptRegistry $promptRegistry;

    /**
     * Documentation templates.
     */
    protected array $templates = [];

    /**
     * Create a new documentation generator instance.
     */
    public function __construct(
        McpRegistry $registry,
        ToolRegistry $toolRegistry,
        ResourceRegistry $resourceRegistry,
        PromptRegistry $promptRegistry
    ) {
        $this->registry = $registry;
        $this->toolRegistry = $toolRegistry;
        $this->resourceRegistry = $resourceRegistry;
        $this->promptRegistry = $promptRegistry;

        $this->initializeTemplates();
    }

    /**
     * Generate complete MCP server documentation.
     */
    public function generateCompleteDocumentation(array $options = []): array
    {
        return [
            'overview' => $this->generateOverview($options),
            'components' => $this->generateComponentDocumentation(),
            'api_reference' => $this->generateApiReference(),
            'usage_guide' => $this->generateUsageGuide(),
            'configuration' => $this->generateConfigurationGuide(),
            'examples' => $this->generateExamples(),
        ];
    }

    /**
     * Generate server overview documentation.
     */
    public function generateOverview(array $options = []): string
    {
        $serverName = $options['name'] ?? 'Laravel MCP Server';
        $description = $options['description'] ?? 'A Model Context Protocol server built with Laravel';
        $version = $options['version'] ?? '1.0.0';

        $stats = [
            'tools' => $this->toolRegistry->count(),
            'resources' => $this->resourceRegistry->count(),
            'prompts' => $this->promptRegistry->count(),
            'total' => $this->registry->count(),
        ];

        $markdown = [
            "# {$serverName}",
            '',
            $description,
            '',
            "**Version:** {$version}",
            '**Generated:** '.now()->format('Y-m-d H:i:s T'),
            '',
            '## Component Statistics',
            '',
            "- **Tools:** {$stats['tools']}",
            "- **Resources:** {$stats['resources']}",
            "- **Prompts:** {$stats['prompts']}",
            "- **Total Components:** {$stats['total']}",
            '',
            '## Features',
            '',
            '- JSON-RPC 2.0 protocol support',
            '- HTTP and Stdio transport layers',
            '- Auto-discovery of MCP components',
            '- Comprehensive capability negotiation',
            '- Laravel integration with service container',
            '',
            '## Quick Start',
            '',
            '```bash',
            '# Start the MCP server',
            'php artisan mcp:serve',
            '',
            '# List available components',
            'php artisan mcp:list',
            '```',
        ];

        return implode("\n", $markdown);
    }

    /**
     * Generate component documentation.
     */
    public function generateComponentDocumentation(): array
    {
        return [
            'tools' => $this->generateToolsDocumentation(),
            'resources' => $this->generateResourcesDocumentation(),
            'prompts' => $this->generatePromptsDocumentation(),
        ];
    }

    /**
     * Generate tools documentation.
     */
    public function generateToolsDocumentation(): string
    {
        $markdown = [
            '# Tools',
            '',
            'Available MCP tools for execution by AI clients.',
            '',
        ];

        if ($this->toolRegistry->count() === 0) {
            $markdown[] = '_No tools are currently registered._';

            return implode("\n", $markdown);
        }

        foreach ($this->toolRegistry->all() as $name => $tool) {
            $metadata = $this->toolRegistry->getMetadata($name);

            $markdown[] = "## {$name}";
            $markdown[] = '';

            if (! empty($metadata['description'])) {
                $markdown[] = $metadata['description'];
                $markdown[] = '';
            }

            // Parameters
            if (! empty($metadata['parameters'])) {
                $markdown[] = '### Parameters';
                $markdown[] = '';

                foreach ($metadata['parameters'] as $param => $info) {
                    $type = $info['type'] ?? 'mixed';
                    $required = $info['required'] ?? false;
                    $description = $info['description'] ?? '';
                    $requiredText = $required ? ' _(required)_' : ' _(optional)_';

                    $markdown[] = "- **{$param}** (`{$type}`){$requiredText}: {$description}";
                }
                $markdown[] = '';
            }

            // Input schema
            if (! empty($metadata['input_schema'])) {
                $markdown[] = '### Input Schema';
                $markdown[] = '';
                $markdown[] = '```json';
                $markdown[] = json_encode($metadata['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $markdown[] = '```';
                $markdown[] = '';
            }

            // Class information
            if (! empty($metadata['class'])) {
                $markdown[] = "**Class:** `{$metadata['class']}`";
                $markdown[] = '';
            }

            $markdown[] = '---';
            $markdown[] = '';
        }

        return implode("\n", $markdown);
    }

    /**
     * Generate resources documentation.
     */
    public function generateResourcesDocumentation(): string
    {
        $markdown = [
            '# Resources',
            '',
            'Available MCP resources for data access by AI clients.',
            '',
        ];

        if ($this->resourceRegistry->count() === 0) {
            $markdown[] = '_No resources are currently registered._';

            return implode("\n", $markdown);
        }

        foreach ($this->resourceRegistry->all() as $name => $resource) {
            $metadata = $this->resourceRegistry->getMetadata($name);

            $markdown[] = "## {$name}";
            $markdown[] = '';

            if (! empty($metadata['description'])) {
                $markdown[] = $metadata['description'];
                $markdown[] = '';
            }

            // URI
            if (! empty($metadata['uri'])) {
                $markdown[] = "**URI:** `{$metadata['uri']}`";
                $markdown[] = '';
            }

            // MIME Type
            if (! empty($metadata['mime_type'])) {
                $markdown[] = "**MIME Type:** `{$metadata['mime_type']}`";
                $markdown[] = '';
            }

            // Annotations
            if (! empty($metadata['annotations'])) {
                $markdown[] = '### Annotations';
                $markdown[] = '';

                foreach ($metadata['annotations'] as $annotation) {
                    $markdown[] = "- {$annotation}";
                }
                $markdown[] = '';
            }

            // Class information
            if (! empty($metadata['class'])) {
                $markdown[] = "**Class:** `{$metadata['class']}`";
                $markdown[] = '';
            }

            $markdown[] = '---';
            $markdown[] = '';
        }

        return implode("\n", $markdown);
    }

    /**
     * Generate prompts documentation.
     */
    public function generatePromptsDocumentation(): string
    {
        $markdown = [
            '# Prompts',
            '',
            'Available MCP prompts for template-based AI interactions.',
            '',
        ];

        if ($this->promptRegistry->count() === 0) {
            $markdown[] = '_No prompts are currently registered._';

            return implode("\n", $markdown);
        }

        foreach ($this->promptRegistry->all() as $name => $prompt) {
            $metadata = $this->promptRegistry->getMetadata($name);

            $markdown[] = "## {$name}";
            $markdown[] = '';

            if (! empty($metadata['description'])) {
                $markdown[] = $metadata['description'];
                $markdown[] = '';
            }

            // Arguments
            if (! empty($metadata['arguments'])) {
                $markdown[] = '### Arguments';
                $markdown[] = '';

                foreach ($metadata['arguments'] as $arg) {
                    $name = $arg['name'] ?? 'unknown';
                    $type = $arg['type'] ?? 'string';
                    $required = $arg['required'] ?? false;
                    $description = $arg['description'] ?? '';
                    $requiredText = $required ? ' _(required)_' : ' _(optional)_';

                    $markdown[] = "- **{$name}** (`{$type}`){$requiredText}: {$description}";
                }
                $markdown[] = '';
            }

            // Class information
            if (! empty($metadata['class'])) {
                $markdown[] = "**Class:** `{$metadata['class']}`";
                $markdown[] = '';
            }

            $markdown[] = '---';
            $markdown[] = '';
        }

        return implode("\n", $markdown);
    }

    /**
     * Generate API reference documentation.
     */
    public function generateApiReference(): string
    {
        $markdown = [
            '# API Reference',
            '',
            'JSON-RPC 2.0 API methods supported by this MCP server.',
            '',
            '## Core Methods',
            '',
            '### initialize',
            '',
            'Initialize the MCP connection and negotiate capabilities.',
            '',
            '**Request:**',
            '```json',
            '{',
            '  "jsonrpc": "2.0",',
            '  "id": 1,',
            '  "method": "initialize",',
            '  "params": {',
            '    "protocolVersion": "2024-11-05",',
            '    "capabilities": {},',
            '    "clientInfo": {',
            '      "name": "client-name",',
            '      "version": "1.0.0"',
            '    }',
            '  }',
            '}',
            '```',
            '',
            '**Response:**',
            '```json',
            '{',
            '  "jsonrpc": "2.0",',
            '  "id": 1,',
            '  "result": {',
            '    "protocolVersion": "2024-11-05",',
            '    "capabilities": {},',
            '    "serverInfo": {',
            '      "name": "Laravel MCP Server",',
            '      "version": "1.0.0"',
            '    }',
            '  }',
            '}',
            '```',
            '',
            '### ping',
            '',
            'Ping the server to check connectivity.',
            '',
            '**Request:**',
            '```json',
            '{',
            '  "jsonrpc": "2.0",',
            '  "id": 2,',
            '  "method": "ping"',
            '}',
            '```',
            '',
            '**Response:**',
            '```json',
            '{',
            '  "jsonrpc": "2.0",',
            '  "id": 2,',
            '  "result": {}',
            '}',
            '```',
            '',
        ];

        // Add tool methods if available
        if ($this->toolRegistry->count() > 0) {
            $markdown = array_merge($markdown, [
                '## Tool Methods',
                '',
                '### tools/list',
                '',
                'List all available tools.',
                '',
                '### tools/call',
                '',
                'Execute a tool with parameters.',
                '',
            ]);
        }

        // Add resource methods if available
        if ($this->resourceRegistry->count() > 0) {
            $markdown = array_merge($markdown, [
                '## Resource Methods',
                '',
                '### resources/list',
                '',
                'List all available resources.',
                '',
                '### resources/read',
                '',
                'Read a resource by URI.',
                '',
                '### resources/templates/list',
                '',
                'List resource templates.',
                '',
            ]);
        }

        // Add prompt methods if available
        if ($this->promptRegistry->count() > 0) {
            $markdown = array_merge($markdown, [
                '## Prompt Methods',
                '',
                '### prompts/list',
                '',
                'List all available prompts.',
                '',
                '### prompts/get',
                '',
                'Get a prompt with arguments.',
                '',
            ]);
        }

        return implode("\n", $markdown);
    }

    /**
     * Generate usage guide documentation.
     */
    public function generateUsageGuide(): string
    {
        return implode("\n", [
            '# Usage Guide',
            '',
            '## Installation',
            '',
            '1. Install the Laravel MCP package:',
            '   ```bash',
            '   composer require jtd/laravel-mcp',
            '   ```',
            '',
            '2. Publish the configuration:',
            '   ```bash',
            '   php artisan vendor:publish --tag="laravel-mcp"',
            '   ```',
            '',
            '3. Configure your MCP components in `config/laravel-mcp.php`',
            '',
            '## Running the Server',
            '',
            '### Stdio Transport (Recommended)',
            '',
            'Start the MCP server with stdio transport:',
            '',
            '```bash',
            'php artisan mcp:serve',
            '```',
            '',
            '### HTTP Transport',
            '',
            'Start the MCP server with HTTP transport:',
            '',
            '```bash',
            'php artisan mcp:serve --transport=http --port=8000',
            '```',
            '',
            '## Claude Desktop Integration',
            '',
            'Add this to your Claude Desktop configuration:',
            '',
            '```json',
            '{',
            '  "mcpServers": {',
            '    "laravel-mcp": {',
            '      "command": "php",',
            '      "args": ["artisan", "mcp:serve"],',
            '      "cwd": "/path/to/your/laravel/project"',
            '    }',
            '  }',
            '}',
            '```',
            '',
            '## Development',
            '',
            '### Creating Tools',
            '',
            '```bash',
            'php artisan make:mcp-tool CalculatorTool',
            '```',
            '',
            '### Creating Resources',
            '',
            '```bash',
            'php artisan make:mcp-resource DatabaseResource',
            '```',
            '',
            '### Creating Prompts',
            '',
            '```bash',
            'php artisan make:mcp-prompt EmailTemplate',
            '```',
        ]);
    }

    /**
     * Generate configuration guide documentation.
     */
    public function generateConfigurationGuide(): string
    {
        return implode("\n", [
            '# Configuration Guide',
            '',
            '## Main Configuration',
            '',
            'The main configuration file is `config/laravel-mcp.php`:',
            '',
            '```php',
            '<?php',
            '',
            'return [',
            '    // Server information',
            '    \'server\' => [',
            '        \'name\' => \'Laravel MCP Server\',',
            '        \'version\' => \'1.0.0\',',
            '    ],',
            '',
            '    // Component discovery',
            '    \'discovery\' => [',
            '        \'enabled\' => true,',
            '        \'paths\' => [',
            '            app_path(\'Mcp/Tools\'),',
            '            app_path(\'Mcp/Resources\'),',
            '            app_path(\'Mcp/Prompts\'),',
            '        ],',
            '    ],',
            '',
            '    // Routes configuration',
            '    \'routes\' => [',
            '        \'prefix\' => \'mcp\',',
            '        \'middleware\' => [\'api\'],',
            '    ],',
            '];',
            '```',
            '',
            '## Transport Configuration',
            '',
            'Transport settings are in `config/mcp-transports.php`:',
            '',
            '```php',
            '<?php',
            '',
            'return [',
            '    \'default\' => \'stdio\',',
            '',
            '    \'transports\' => [',
            '        \'stdio\' => [',
            '            \'enabled\' => true,',
            '            \'timeout\' => 30,',
            '        ],',
            '',
            '        \'http\' => [',
            '            \'enabled\' => true,',
            '            \'host\' => \'127.0.0.1\',',
            '            \'port\' => 8000,',
            '            \'path\' => \'/mcp\',',
            '        ],',
            '    ],',
            '];',
            '```',
            '',
            '## Environment Variables',
            '',
            'Key environment variables:',
            '',
            '```env',
            '# Transport Settings',
            'MCP_DEFAULT_TRANSPORT=stdio',
            'MCP_HTTP_ENABLED=true',
            'MCP_HTTP_PORT=8000',
            'MCP_STDIO_TIMEOUT=30',
            '',
            '# Discovery Settings',
            'MCP_AUTO_DISCOVERY=true',
            '',
            '# Debug Settings',
            'MCP_DEBUG=false',
            'MCP_LOG_REQUESTS=true',
            '```',
        ]);
    }

    /**
     * Generate examples documentation.
     */
    public function generateExamples(): string
    {
        return implode("\n", [
            '# Examples',
            '',
            '## Simple Tool Example',
            '',
            '```php',
            '<?php',
            '',
            'namespace App\\Mcp\\Tools;',
            '',
            'use JTD\\LaravelMCP\\Abstracts\\McpTool;',
            '',
            'class CalculatorTool extends McpTool',
            '{',
            '    public function getName(): string',
            '    {',
            '        return \'calculator\';',
            '    }',
            '',
            '    public function getDescription(): string',
            '    {',
            '        return \'Performs basic arithmetic calculations\';',
            '    }',
            '',
            '    public function execute(array $parameters): array',
            '    {',
            '        $a = $parameters[\'a\'] ?? 0;',
            '        $b = $parameters[\'b\'] ?? 0;',
            '        $operation = $parameters[\'operation\'] ?? \'add\';',
            '',
            '        $result = match($operation) {',
            '            \'add\' => $a + $b,',
            '            \'subtract\' => $a - $b,',
            '            \'multiply\' => $a * $b,',
            '            \'divide\' => $b !== 0 ? $a / $b : \'Error: Division by zero\',',
            '            default => \'Error: Unknown operation\'',
            '        };',
            '',
            '        return [\'result\' => $result];',
            '    }',
            '}',
            '```',
            '',
            '## Simple Resource Example',
            '',
            '```php',
            '<?php',
            '',
            'namespace App\\Mcp\\Resources;',
            '',
            'use JTD\\LaravelMCP\\Abstracts\\McpResource;',
            '',
            'class UserResource extends McpResource',
            '{',
            '    public function getName(): string',
            '    {',
            '        return \'users\';',
            '    }',
            '',
            '    public function getDescription(): string',
            '    {',
            '        return \'Access user data from the database\';',
            '    }',
            '',
            '    public function read(array $parameters): array',
            '    {',
            '        $userId = $parameters[\'id\'] ?? null;',
            '',
            '        if ($userId) {',
            '            $user = User::find($userId);',
            '            return $user ? $user->toArray() : [];',
            '        }',
            '',
            '        return User::all()->toArray();',
            '    }',
            '}',
            '```',
            '',
            '## Simple Prompt Example',
            '',
            '```php',
            '<?php',
            '',
            'namespace App\\Mcp\\Prompts;',
            '',
            'use JTD\\LaravelMCP\\Abstracts\\McpPrompt;',
            '',
            'class EmailTemplate extends McpPrompt',
            '{',
            '    public function getName(): string',
            '    {',
            '        return \'email_template\';',
            '    }',
            '',
            '    public function getDescription(): string',
            '    {',
            '        return \'Generate professional email templates\';',
            '    }',
            '',
            '    public function render(array $arguments): array',
            '    {',
            '        $subject = $arguments[\'subject\'] ?? \'No Subject\';',
            '        $recipient = $arguments[\'recipient\'] ?? \'Recipient\';',
            '        $tone = $arguments[\'tone\'] ?? \'professional\';',
            '',
            '        $content = "Write a {$tone} email to {$recipient} with the subject: {$subject}";',
            '',
            '        return [',
            '            \'messages\' => [',
            '                [',
            '                    \'role\' => \'user\',',
            '                    \'content\' => [',
            '                        \'type\' => \'text\',',
            '                        \'text\' => $content,',
            '                    ],',
            '                ],',
            '            ],',
            '        ];',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Save documentation to files.
     */
    public function saveDocumentation(array $documentation, string $basePath): bool
    {
        try {
            if (! File::exists($basePath)) {
                File::makeDirectory($basePath, 0755, true);
            }

            foreach ($documentation as $section => $content) {
                $filename = "{$basePath}/".Str::kebab($section).'.md';

                if (is_array($content)) {
                    // Handle nested documentation sections
                    $sectionPath = "{$basePath}/".Str::kebab($section);
                    if (! File::exists($sectionPath)) {
                        File::makeDirectory($sectionPath, 0755, true);
                    }

                    foreach ($content as $subsection => $subcontent) {
                        $subfilename = "{$sectionPath}/".Str::kebab($subsection).'.md';
                        File::put($subfilename, $subcontent);
                    }
                } else {
                    File::put($filename, $content);
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Generate README file.
     */
    public function generateReadme(array $options = []): string
    {
        $overview = $this->generateOverview($options);
        $usageGuide = $this->generateUsageGuide();

        return $overview."\n\n".$usageGuide;
    }

    /**
     * Initialize documentation templates.
     */
    protected function initializeTemplates(): void
    {
        $this->templates = [
            'overview' => '# {name}\n\n{description}',
            'component' => '## {name}\n\n{description}',
            'method' => '### {method}\n\n{description}',
        ];
    }

    /**
     * Get documentation templates.
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }

    /**
     * Set documentation template.
     */
    public function setTemplate(string $key, string $template): void
    {
        $this->templates[$key] = $template;
    }
}
