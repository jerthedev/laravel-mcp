# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

### Package Development
```bash
# Install dependencies
composer install

# Run code style fixes
composer pint

# Run tests (when implemented)
./vendor/bin/phpunit

# Run static analysis (when implemented)  
./vendor/bin/phpstan analyse

# Run specific test file
./vendor/bin/phpunit tests/Unit/SomeTest.php

# Publish package assets during development
php artisan vendor:publish --tag="laravel-mcp"
```

### MCP-Specific Commands (Future Implementation)
```bash
# Start MCP server
php artisan mcp:serve

# List registered MCP components
php artisan mcp:list

# Generate MCP components
php artisan make:mcp-tool CalculatorTool
php artisan make:mcp-resource UserResource
php artisan make:mcp-prompt EmailTemplate

# Register with AI clients
php artisan mcp:register
```

## Package Architecture

### Core Architecture
This Laravel package implements the Model Context Protocol (MCP) 1.0 specification, providing a bridge between Laravel applications and AI clients (Claude Desktop, Claude Code, ChatGPT Desktop). The architecture follows Laravel conventions while implementing MCP protocol requirements.

**Key Architectural Layers:**
1. **Transport Layer**: Handles communication via HTTP and Stdio transports
2. **Protocol Layer**: Implements JSON-RPC 2.0 and MCP 1.0 message handling
3. **Registry System**: Auto-discovers and manages Tools, Resources, and Prompts
4. **Base Classes**: Abstract classes for creating MCP components
5. **Laravel Integration**: Service provider, facades, middleware, and Artisan commands

### Service Provider Structure
`LaravelMcpServiceProvider` is the main integration point:
- **Register Phase**: Binds core services, interfaces, and singletons to Laravel's container
- **Boot Phase**: Sets up routes, middleware, component discovery, and console commands
- **Publishing**: Handles configuration, routes, and stub publishing for end-users

### Component Discovery System
The package auto-discovers MCP components in Laravel applications:
- **Default Paths**: `app/Mcp/Tools/`, `app/Mcp/Resources/`, `app/Mcp/Prompts/`
- **Registry Pattern**: Central `McpRegistry` with type-specific registries
- **Auto-Registration**: Components extending base classes are automatically registered
- **Route Generation**: Automatic Laravel route registration for HTTP transport

### Transport Architecture
**Dual Transport Support:**
- **Stdio Transport**: Standard input/output for desktop AI clients (primary)
- **HTTP Transport**: RESTful API for web-based integrations (secondary)
- **Transport Manager**: Factory pattern for transport selection and instantiation
- **Message Framing**: JSON-RPC 2.0 compliant message handling

### Configuration Structure
Two-tier configuration system:
- **`laravel-mcp.php`**: Main package configuration (discovery, routes, middleware)
- **`mcp-transports.php`**: Transport-specific settings (HTTP/Stdio parameters)

### Development Workflow
The package uses a ticket-based development approach:
- **28 Sequential Tickets**: From `001-PackageOverview` to `028-TestingQuality`
- **Dependency Chain**: Each ticket builds on previous implementations
- **1-1.5 Day Tasks**: Manageable development increments
- **Specification-Driven**: Each ticket implements specific requirements from `docs/Specs/`

### MCP Component Types
Three core MCP component types with Laravel integration:
- **Tools**: Executable functions that AI can call (extend `McpTool`)
- **Resources**: Data sources AI can read (extend `McpResource`)  
- **Prompts**: Template systems for AI interactions (extend `McpPrompt`)

### Laravel-Specific Integrations
- **Middleware Stack**: Authentication, CORS, rate limiting, validation
- **Facade Pattern**: `Mcp` facade for fluent API access
- **Event System**: Component registration and request processing events
- **Job Integration**: Async MCP request processing
- **Validation**: Laravel validation for MCP request parameters

## Namespace Structure

**Root Namespace**: `JTD\LaravelMCP\`

Key namespaces:
- `Commands\`: Artisan command implementations
- `Transport\`: HTTP and Stdio transport layers
- `Protocol\`: JSON-RPC and MCP protocol handling
- `Registry\`: Component discovery and registration
- `Abstracts\`: Base classes for MCP components
- `Http\`: Controllers and middleware
- `Support\`: Utilities for config generation and documentation

## Development Status

**Current State**: Foundational skeleton with basic service provider and configuration
**Next Steps**: Follow sequential ticket implementation starting with `003-ServiceProviderCore`
**Target**: Production-ready Laravel package for MCP server implementation