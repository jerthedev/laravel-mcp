# Laravel MCP

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jerthedev/laravel-mcp.svg?style=flat-square)](https://packagist.org/packages/jerthedev/laravel-mcp)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/jerthedev/laravel-mcp/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jerthedev/laravel-mcp/actions?query=workflow%3Atests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/jerthedev/laravel-mcp/tests.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/jerthedev/laravel-mcp/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/jerthedev/laravel-mcp.svg?style=flat-square)](https://packagist.org/packages/jerthedev/laravel-mcp)

A comprehensive Laravel package that provides seamless integration with the **Model Context Protocol (MCP)**, enabling Laravel developers to easily create MCP servers that can expose Tools, Resources, and Prompts to AI applications like Claude Desktop, Claude Code, and ChatGPT Desktop.

## What is MCP?

The Model Context Protocol (MCP) is an open standard that enables secure, controlled interactions between AI applications and external systems. It allows you to:

- **Expose Tools**: Functions that AI can execute (e.g., database queries, API calls)
- **Provide Resources**: Data sources that AI can read (e.g., files, documents, configurations)  
- **Share Prompts**: Template systems for consistent AI interactions

## Features

- 🚀 **Laravel-Native**: Built with Laravel conventions and patterns
- 🔌 **Dual Transport**: Supports both HTTP and Stdio transports
- 🎯 **Auto-Discovery**: Automatically registers your MCP components
- 🛠️ **Artisan Commands**: Generate Tools, Resources, and Prompts quickly
- 🔒 **Security-First**: Built-in authentication and authorization
- 📚 **Well-Documented**: Comprehensive guides and examples
- ✅ **Fully Tested**: High test coverage with Laravel testing utilities
- 🪶 **Lightweight**: Custom MCP 1.0 implementation without external SDK dependencies

## Requirements

- **PHP**: 8.2 or higher
- **Laravel**: 11.0 or higher
- **Extensions**: `ext-json`, `ext-mbstring`

## Installation

Install the package via Composer:

```bash
composer require jerthedev/laravel-mcp
```

The package will automatically register itself via Laravel's package auto-discovery.

## Configuration

Publish the configuration files:

```bash
php artisan vendor:publish --tag="laravel-mcp"
```

This creates two configuration files:

- `config/laravel-mcp.php` - Main package configuration
- `config/mcp-transports.php` - Transport-specific settings

### Basic Configuration

```php
// config/laravel-mcp.php
return [
    'auto_discovery' => [
        'enabled' => true,
        'paths' => [
            'tools' => app_path('Mcp/Tools'),
            'resources' => app_path('Mcp/Resources'),
            'prompts' => app_path('Mcp/Prompts'),
        ],
    ],
    
    'transports' => [
        'http' => [
            'enabled' => true,
            'prefix' => 'mcp',
        ],
        'stdio' => [
            'enabled' => true,
        ],
    ],
];
```

## Quick Start

### 1. Create Your First MCP Tool

```bash
php artisan make:mcp-tool DatabaseQueryTool
```

This generates `app/Mcp/Tools/DatabaseQueryTool.php`:

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class DatabaseQueryTool extends McpTool
{
    public function getName(): string
    {
        return 'database_query';
    }
    
    public function getDescription(): string
    {
        return 'Execute database queries safely';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'SQL query to execute'
                ]
            ],
            'required' => ['query']
        ];
    }
    
    public function execute(array $params): array
    {
        // Your tool logic here
        return ['result' => 'Query executed successfully'];
    }
}
```

### 2. Create an MCP Resource

```bash
php artisan make:mcp-resource UserResource
```

### 3. Create an MCP Prompt

```bash
php artisan make:mcp-prompt EmailTemplate
```

### 4. Configure for Claude Desktop

Generate Claude Desktop configuration:

```bash
php artisan mcp:register --client=claude-desktop
```

This creates a configuration file for Claude Desktop to connect to your MCP server.

## Usage Examples

### HTTP Transport (Web Integration)

Your MCP server is automatically available via HTTP routes:

```bash
# List available tools
GET /mcp/tools

# Execute a tool
POST /mcp/tools/database_query
Content-Type: application/json

{
    "params": {
        "query": "SELECT * FROM users LIMIT 5"
    }
}
```

### Stdio Transport (Desktop AI Clients)

The Stdio transport is used by desktop AI clients like Claude Desktop:

```bash
# Start MCP server
php artisan mcp:serve
```

## Advanced Usage

### Custom Authentication

```php
// In your AppServiceProvider
use JTD\LaravelMCP\Facades\Mcp;

public function boot(): void
{
    Mcp::authenticateUsing(function ($request) {
        return $request->bearerToken() === config('app.mcp_token');
    });
}
```

### Dynamic Resource Content

```php
public function getContent(): string
{
    return Cache::remember('user-stats', 300, function () {
        return json_encode([
            'total_users' => User::count(),
            'active_sessions' => Session::where('last_activity', '>', now()->subMinutes(15))->count(),
        ]);
    });
}
```

### Validation and Error Handling

```php
public function execute(array $params): array
{
    $validator = Validator::make($params, [
        'email' => 'required|email',
        'message' => 'required|string|max:1000',
    ]);
    
    if ($validator->fails()) {
        throw new McpValidationException($validator->errors());
    }
    
    // Execute your logic...
}
```

## Available Commands

```bash
# Generate MCP components
php artisan make:mcp-tool CalculatorTool
php artisan make:mcp-resource UserResource  
php artisan make:mcp-prompt EmailTemplate

# Server management
php artisan mcp:serve                    # Start stdio server
php artisan mcp:list                     # List registered components

# Client configuration
php artisan mcp:register                 # Generate config for AI clients
```

## Transport Configuration

### HTTP Transport

The HTTP transport makes your MCP server available via Laravel routes:

```php
// config/mcp-transports.php
'http' => [
    'enabled' => true,
    'prefix' => 'mcp',
    'middleware' => ['auth:sanctum', 'throttle:60,1'],
    'cors' => [
        'enabled' => true,
        'origins' => ['https://claude.ai'],
    ],
],
```

### Stdio Transport

The Stdio transport is used for desktop AI clients:

```php
'stdio' => [
    'enabled' => true,
    'timeout' => 30,
    'buffer_size' => 4096,
],
```

## Development Status

This package is currently in active development. Here's our progress:

- [x] Package overview and architecture ✅
- [x] GitHub repository setup ✅  
- [ ] Core MCP protocol implementation 🚧
- [ ] HTTP and Stdio transport layers 🚧
- [ ] Tool, Resource, and Prompt abstractions 🚧
- [ ] Component auto-discovery 🚧
- [ ] Artisan commands for code generation 🚧
- [ ] Client configuration generation 🚧
- [ ] Comprehensive testing suite 🚧
- [ ] Production documentation 🚧

## Testing

Run the tests with:

```bash
composer test
```

Run tests with coverage:

```bash
composer test-coverage
```

Check code style:

```bash
composer pint
```

Run static analysis:

```bash
composer analyse
```

## Contributing

Please see [CONTRIBUTING](docs/CONTRIBUTING.md) for details on how to contribute to this project.

## Security

If you discover any security-related issues, please email jeremy@jerthedev.com instead of using the issue tracker.

## Credits

- [Jeremy Fall](https://github.com/jerthedev)
- [All Contributors](../../contributors)

## Related Projects

- [Model Context Protocol](https://modelcontextprotocol.io/) - Official MCP specification
- [MCP Specification](https://spec.modelcontextprotocol.io/) - Official MCP 1.0 specification
- [MCP Protocol Documentation](https://modelcontextprotocol.io/docs/concepts/architecture) - Protocol architecture and concepts

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.