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

### Core Features
- 🚀 **Laravel-Native**: Built with Laravel conventions and patterns
- 🔌 **Dual Transport**: Supports both HTTP and Stdio transports
- 🎯 **Auto-Discovery**: Automatically registers your MCP components
- 🛠️ **Artisan Commands**: Generate Tools, Resources, and Prompts quickly
- 🔒 **Security-First**: Built-in authentication and authorization
- 📚 **Well-Documented**: Comprehensive guides and examples
- ✅ **Fully Tested**: High test coverage with Laravel testing utilities
- 🪶 **Lightweight**: Custom MCP 1.0 implementation without external SDK dependencies

### Enhanced Production Features
- ⚡ **Async Processing**: Queue-based MCP request processing with job monitoring
- 📊 **Event-Driven Architecture**: 10+ events with built-in and custom listeners
- 🚨 **Advanced Monitoring**: Performance monitoring and metrics collection
- 🛡️ **7-Layer Middleware Stack**: Production security and validation pipeline
- 📨 **Notification System**: Multi-channel delivery (Email, Slack, Database)
- 🏗️ **Service Provider**: 100% specification compliance with enhanced features

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

🎉 **Production Ready!** This package is now complete with comprehensive implementation:

### ✅ Completed Features
- [x] **Package Architecture**: Complete with enhanced service provider
- [x] **MCP Protocol Implementation**: Full JSON-RPC 2.0 and MCP 1.0 compliance
- [x] **Transport Layers**: HTTP and Stdio transports with production features
- [x] **Component System**: Tool, Resource, and Prompt abstractions with auto-discovery
- [x] **Artisan Commands**: Complete command system with trait-based architecture (100% spec compliance)
- [x] **Laravel Integration**: Event system, jobs, notifications, middleware
- [x] **Service Provider**: 100% specification compliance with enhanced features
- [x] **Testing Infrastructure**: 727 fast tests, 1,355 unit tests, CI/CD pipeline
- [x] **Documentation**: Comprehensive specs and usage examples

### 🏗️ Architecture Status
- ✅ **Core Services**: Registry, transport, protocol handling
- ✅ **Enhanced Services**: Performance monitoring, schema validation, debugging
- ✅ **Event System**: 10+ events with built-in and custom listeners
- ✅ **Async Processing**: Queue-based processing with job monitoring
- ✅ **Notification System**: Multi-channel delivery (Email, Slack, Database)
- ✅ **Security**: 7-layer middleware stack with comprehensive validation

## Testing

### Comprehensive Test Infrastructure
The package implements a **tiered testing strategy** for optimal development workflow:

```bash
# Fast tests for CI/CD (727 tests, ~9.4s)
composer test:fast

# Unit tests (1,355 tests, ~15s)  
composer test:unit

# Feature tests
composer test:feature

# Full comprehensive suite (1,624+ tests, ~20s)
composer test:comprehensive

# Run with coverage
composer test:coverage
```

### Test Status
- ✅ **727 Fast Tests**: Core functionality validation (CI-friendly)
- ✅ **1,355 Unit Tests**: Comprehensive unit test coverage
- ✅ **GitHub Actions CI**: Automated testing on push/PR
- ✅ **Production Ready**: All critical paths tested and validated

Check code style:

```bash
composer pint
```

Run static analysis:

```bash
composer analyse
```

## Documentation

Comprehensive documentation is available:

- **[Architecture Overview](docs/architecture-enhanced.md)** - Complete system architecture with all enhanced features
- **[Events System](docs/events-system.md)** - Event-driven architecture documentation  
- **[Async Processing](docs/async-processing.md)** - Queue-based asynchronous processing
- **[Middleware Stack](docs/middleware-stack.md)** - Production-ready middleware documentation
- **[McpManager API](docs/components/mcp-manager.md)** - Complete API reference for central manager
- **[Package Structure](docs/Specs/02-PackageStructure.md)** - Updated package organization
- **[Service Provider](docs/Specs/03-ServiceProvider.md)** - Enhanced Laravel integration
- **[Laravel Integration](docs/Specs/10-LaravelIntegration.md)** - Framework integration details

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