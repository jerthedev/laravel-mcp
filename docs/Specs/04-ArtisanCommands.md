# Artisan Commands Specification

## Overview

The package provides a comprehensive set of Artisan commands following Laravel's conventions. These commands facilitate MCP server management, component generation, and client integration.

> **✅ Implementation Status**: All commands, traits, and base classes specified in this document have been fully implemented as of the latest version.

## Command Structure

### Command Organization
```
Commands/
├── BaseCommand.php                    # Abstract base for all MCP commands
├── BaseMcpGeneratorCommand.php        # Abstract base for generator commands
├── ServeCommand.php                   # mcp:serve - Start MCP server
├── MakeToolCommand.php                # make:mcp-tool - Generate tool class
├── MakeResourceCommand.php            # make:mcp-resource - Generate resource class
├── MakePromptCommand.php              # make:mcp-prompt - Generate prompt class
├── ListCommand.php                    # mcp:list - List registered components
├── RegisterCommand.php                # mcp:register - Register with AI clients
├── DocumentationCommand.php           # mcp:docs - Generate documentation
└── Traits/
    ├── FormatsOutput.php              # Output formatting functionality
    ├── HandlesConfiguration.php       # Configuration management
    └── HandlesCommandErrors.php       # Error handling functionality
```

## Server Commands

### MCP Serve Command (`mcp:serve`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Transport\StdioTransport;
use JTD\LaravelMCP\Registry\McpRegistry;

class ServeCommand extends Command
{
    protected $signature = 'mcp:serve
                           {--host=127.0.0.1 : The host to serve on}
                           {--port=8000 : The port to serve on}
                           {--transport=stdio : Transport type (stdio|http)}
                           {--timeout=30 : Request timeout in seconds}
                           {--debug : Enable debug mode}';

    protected $description = 'Start the MCP server';

    public function handle(): int
    {
        // Implementation details below
    }
}
```

#### Implementation Features
- **Transport Selection**: Stdio (default) or HTTP transport
- **Debug Mode**: Verbose logging and error reporting
- **Graceful Shutdown**: Handle SIGINT and SIGTERM signals
- **Error Handling**: Comprehensive error reporting and recovery
- **Performance Monitoring**: Optional performance metrics

#### Usage Examples
```bash
# Start stdio server (default)
php artisan mcp:serve

# Start HTTP server
php artisan mcp:serve --transport=http --port=8080

# Debug mode with verbose output
php artisan mcp:serve --debug

# Custom timeout
php artisan mcp:serve --timeout=60
```

### MCP List Command (`mcp:list`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Registry\McpRegistry;

class ListCommand extends Command
{
    protected $signature = 'mcp:list
                           {--type=all : Component type (all|tools|resources|prompts)}
                           {--format=table : Output format (table|json|yaml)}
                           {--detailed : Show detailed information}';

    protected $description = 'List all registered MCP components';
}
```

#### Output Features
- **Tabular Display**: Clean table format with component details
- **JSON Export**: Machine-readable JSON output
- **YAML Export**: Human-readable YAML format
- **Filtering**: Filter by component type
- **Detailed Mode**: Show parameters, descriptions, and metadata

#### Usage Examples
```bash
# List all components
php artisan mcp:list

# List only tools
php artisan mcp:list --type=tools

# JSON output
php artisan mcp:list --format=json

# Detailed information
php artisan mcp:list --detailed
```

## Generator Commands

### Make Tool Command (`make:mcp-tool`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeToolCommand extends GeneratorCommand
{
    protected $signature = 'make:mcp-tool
                           {name : The name of the tool class}
                           {--force : Overwrite existing files}
                           {--description= : Tool description}
                           {--parameters= : JSON string of tool parameters}';

    protected $description = 'Create a new MCP tool class';

    protected $type = 'MCP Tool';
}
```

#### Generation Features
- **Stub Template**: Pre-configured tool class template
- **Parameter Definition**: Auto-generate parameter validation
- **Description Integration**: Include description in generated class
- **Namespace Handling**: Proper namespace and use statements
- **Laravel Integration**: DI and Laravel service integration

#### Generated Tool Structure
```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class {{ class }} extends McpTool
{
    protected string $name = '{{ name }}';
    protected string $description = '{{ description }}';

    protected function getParameterSchema(): array
    {
        return [
            // Generated parameter definitions
        ];
    }

    public function execute(array $parameters): mixed
    {
        // Tool implementation
    }
}
```

#### Usage Examples
```bash
# Basic tool generation
php artisan make:mcp-tool CalculatorTool

# With description
php artisan make:mcp-tool DatabaseQueryTool --description="Query database tables"

# With parameters
php artisan make:mcp-tool UserSearchTool --parameters='{"query": {"type": "string", "description": "Search query"}}'

# Force overwrite
php artisan make:mcp-tool ExistingTool --force
```

### Make Resource Command (`make:mcp-resource`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeResourceCommand extends GeneratorCommand
{
    protected $signature = 'make:mcp-resource
                           {name : The name of the resource class}
                           {--model= : Associated Eloquent model}
                           {--force : Overwrite existing files}
                           {--uri-template= : URI template pattern}';

    protected $description = 'Create a new MCP resource class';

    protected $type = 'MCP Resource';
}
```

#### Generation Features
- **Model Integration**: Auto-detect and integrate Eloquent models
- **URI Template**: Generate URI patterns for resource access
- **CRUD Operations**: Pre-built create, read, update, delete methods
- **Collection Support**: Handle resource collections
- **Pagination**: Built-in pagination support

#### Usage Examples
```bash
# Basic resource
php artisan make:mcp-resource UserResource

# With model association
php artisan make:mcp-resource PostResource --model=Post

# With URI template
php artisan make:mcp-resource ArticleResource --uri-template="articles/{id}"
```

### Make Prompt Command (`make:mcp-prompt`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;

class MakePromptCommand extends GeneratorCommand
{
    protected $signature = 'make:mcp-prompt
                           {name : The name of the prompt class}
                           {--template= : Prompt template file}
                           {--variables= : JSON string of template variables}
                           {--force : Overwrite existing files}';

    protected $description = 'Create a new MCP prompt class';

    protected $type = 'MCP Prompt';
}
```

#### Generation Features
- **Template System**: Support for prompt templates
- **Variable Definition**: Define template variables with types
- **Validation**: Parameter validation for prompt variables
- **Blade Integration**: Optional Blade template support
- **Localization**: Multi-language prompt support

#### Usage Examples
```bash
# Basic prompt
php artisan make:mcp-prompt EmailTemplatePrompt

# With variables
php artisan make:mcp-prompt ReportPrompt --variables='{"title": "string", "date": "date"}'

# With template file
php artisan make:mcp-prompt NewsletterPrompt --template=newsletter.blade.php
```

## Registration Commands

### Register Command (`mcp:register`)

#### Command Definition
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;
use JTD\LaravelMCP\Support\ConfigGenerator;

class RegisterCommand extends Command
{
    protected $signature = 'mcp:register
                           {client : Client type (claude-desktop|claude-code|chatgpt)}
                           {--name= : Server name}
                           {--description= : Server description}
                           {--path= : Custom server path}
                           {--args=* : Additional arguments}
                           {--env=* : Environment variables}
                           {--output= : Output configuration file}
                           {--force : Overwrite existing configuration}';

    protected $description = 'Register MCP server with AI clients';
}
```

#### Client Support

##### Claude Desktop Registration
```json
{
  "mcpServers": {
    "my-laravel-app": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "cwd": "/path/to/laravel/app",
      "env": {
        "APP_ENV": "production"
      }
    }
  }
}
```

##### Claude Code Registration
```json
{
  "mcp": {
    "servers": {
      "my-laravel-app": {
        "command": ["php", "artisan", "mcp:serve"],
        "cwd": "/path/to/laravel/app"
      }
    }
  }
}
```

##### ChatGPT Desktop Registration
```json
{
  "mcp_servers": [
    {
      "name": "my-laravel-app",
      "executable": "php",
      "args": ["artisan", "mcp:serve"],
      "working_directory": "/path/to/laravel/app"
    }
  ]
}
```

#### Registration Features
- **Auto-Detection**: Detect client installations automatically
- **Path Resolution**: Resolve Laravel application paths
- **Environment Handling**: Set up required environment variables
- **Validation**: Validate configuration before writing
- **Backup**: Backup existing configurations

#### Usage Examples
```bash
# Register with Claude Desktop
php artisan mcp:register claude-desktop

# Custom server name
php artisan mcp:register claude-desktop --name="My Laravel MCP Server"

# With environment variables
php artisan mcp:register claude-desktop --env=APP_ENV=production --env=DB_CONNECTION=mysql

# Output to custom file
php artisan mcp:register claude-desktop --output=/custom/path/config.json

# Force overwrite
php artisan mcp:register claude-desktop --force
```

## Command Base Classes

### Base Generator Command
```php
<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\GeneratorCommand;

abstract class BaseMcpGeneratorCommand extends GeneratorCommand
{
    protected function getPath($name): string
    {
        $name = Str::replaceFirst($this->rootNamespace(), '', $name);
        return $this->laravel['path'].'/'.str_replace('\\', '/', $name).'.php';
    }

    protected function rootNamespace(): string
    {
        return $this->laravel->getNamespace().'Mcp\\';
    }

    protected function getStub(): string
    {
        $stub = $this->getStubName();
        
        if (file_exists($customStub = $this->laravel->basePath("stubs/mcp/$stub"))) {
            return $customStub;
        }

        return __DIR__."/../../resources/stubs/$stub";
    }

    abstract protected function getStubName(): string;
}
```

## Command Utilities

### Output Formatting
```php
trait FormatsOutput
{
    protected function formatTable(array $data, array $headers): void
    {
        $this->table($headers, $data);
    }

    protected function formatJson(array $data): void
    {
        $this->line(json_encode($data, JSON_PRETTY_PRINT));
    }

    protected function formatYaml(array $data): void
    {
        $this->line(Yaml::dump($data, 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }
}
```

### Configuration Helpers
```php
trait HandlesConfiguration
{
    protected function getClientConfigPath(string $client): ?string
    {
        $paths = [
            'claude-desktop' => [
                'darwin' => '~/Library/Application Support/Claude/claude_desktop_config.json',
                'linux' => '~/.config/claude/claude_desktop_config.json',
                'windows' => '%APPDATA%\\Claude\\claude_desktop_config.json',
            ],
            'claude-code' => [
                'darwin' => '~/Library/Application Support/Claude Code/config.json',
                'linux' => '~/.config/claude-code/config.json', 
                'windows' => '%APPDATA%\\Claude Code\\config.json',
            ],
        ];

        $os = $this->detectOS();
        return $paths[$client][$os] ?? null;
    }

    protected function detectOS(): string
    {
        return match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => 'linux',
        };
    }
}
```

## Testing Commands

### Command Testing Support
```php
abstract class CommandTestCase extends TestCase
{
    protected function artisan(string $command, array $parameters = []): int
    {
        return $this->app['artisan']->call($command, $parameters);
    }

    protected function artisanOutput(): string
    {
        return $this->app['artisan']->output();
    }

    protected function assertCommandSuccessful(): void
    {
        $this->assertEquals(0, $this->app['artisan']->getLastExitCode());
    }
}
```

## Error Handling

### Command Error Management
```php
trait HandlesCommandErrors
{
    protected function handleError(\Throwable $e): int
    {
        $this->error("Error: {$e->getMessage()}");
        
        if ($this->option('verbose') || $this->option('debug')) {
            $this->error($e->getTraceAsString());
        }
        
        return 1;
    }

    protected function validateInput(): bool
    {
        // Validation logic
        return true;
    }

    protected function confirmDestructiveAction(string $message): bool
    {
        return $this->confirm($message);
    }
}
```

## Performance Considerations

### Lazy Loading
- Commands are only loaded when needed
- Heavy dependencies are resolved lazily
- Cache command results where appropriate

### Resource Management
- Clean up resources after command execution
- Handle long-running processes efficiently
- Monitor memory usage in serve command

### Progress Indicators
- Show progress bars for long operations
- Provide real-time status updates
- Handle user interruption gracefully

## Implementation Status

### ✅ Fully Implemented Components

All components specified in this document have been successfully implemented:

#### Commands
- **ServeCommand** - MCP server management with HTTP/Stdio transports
- **ListCommand** - Component listing with multiple output formats (table/json/yaml)
- **MakeToolCommand** - Tool class generation with validation and security
- **MakeResourceCommand** - Resource class generation with model integration
- **MakePromptCommand** - Prompt class generation with template support
- **RegisterCommand** - Client registration for Claude Desktop, Claude Code, ChatGPT
- **DocumentationCommand** - MCP server documentation generation (additional feature)

#### Base Classes
- **BaseCommand** - Abstract base class with shared functionality
- **BaseMcpGeneratorCommand** - Abstract base for all generator commands
- **CommandTestCase** - Base class for command testing (in tests/Support/)

#### Traits
- **FormatsOutput** - Output formatting (table/json/yaml display methods)
- **HandlesConfiguration** - Configuration management (OS detection, client paths)
- **HandlesCommandErrors** - Error handling (validation, confirmation, error display)

### Architecture Benefits

The implemented architecture provides:

1. **Code Reusability** - Shared functionality through traits and base classes
2. **Consistent Error Handling** - Standardized error reporting across all commands
3. **Flexible Output Formats** - Support for table, JSON, and YAML output
4. **Secure Operations** - Input validation and path security for all operations
5. **Extensibility** - Easy to add new commands following established patterns
6. **Testing Support** - Comprehensive testing utilities for command validation

### Usage Examples

#### Using New Traits in Custom Commands

```php
<?php

namespace App\Console\Commands;

use JTD\LaravelMCP\Commands\BaseCommand;

class CustomMcpCommand extends BaseCommand
{
    // Inherits FormatsOutput, HandlesConfiguration, HandlesCommandErrors traits
    
    protected function executeCommand(): int
    {
        // Use trait methods
        $data = ['key' => 'value'];
        $this->formatJson($data);
        
        $enabled = $this->isMcpEnabled();
        
        if (!$this->validateInput()) {
            return self::EXIT_INVALID_INPUT;
        }
        
        return self::EXIT_SUCCESS;
    }
}
```

#### Creating Custom Generator Commands

```php
<?php

namespace App\Console\Commands;

use JTD\LaravelMCP\Commands\BaseMcpGeneratorCommand;

class MakeCustomComponentCommand extends BaseMcpGeneratorCommand
{
    protected $signature = 'make:mcp-custom {name}';
    protected $description = 'Create a custom MCP component';
    protected $type = 'Custom MCP Component';

    protected function getStubName(): string
    {
        return 'custom-component';
    }

    protected function getComponentType(): string
    {
        return 'custom';
    }
}
```

#### Using CommandTestCase for Testing

```php
<?php

namespace Tests\Unit\Commands;

use JTD\LaravelMCP\Tests\Support\CommandTestCase;

class CustomCommandTest extends CommandTestCase
{
    public function test_command_executes_successfully(): void
    {
        $this->executeAndAssertSuccess('custom:command');
        $this->assertOutputContains('Success message');
    }
}
```