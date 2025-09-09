# Laravel MCP Package Documentation

Welcome to the comprehensive documentation for the Laravel MCP package - a powerful Laravel package that provides seamless integration with the Model Context Protocol (MCP), enabling Laravel developers to easily create MCP servers that can expose Tools, Resources, and Prompts to AI applications like Claude Desktop, Claude Code, and ChatGPT Desktop.

## Overview

The Laravel MCP package implements the Model Context Protocol (MCP) 1.0 specification, providing a bridge between Laravel applications and AI clients. This package follows Laravel conventions while implementing MCP protocol requirements, offering developers a familiar and powerful way to create AI-integrated applications.

## Key Features

- **MCP 1.0 Compliance**: Full implementation of the Model Context Protocol specification
- **Laravel Integration**: Seamless integration with Laravel's ecosystem including container, validation, middleware, and more
- **Auto-Discovery**: Automatic component discovery and registration
- **Dual Transport**: Support for both HTTP and Stdio transports
- **Base Classes**: Abstract classes for creating Tools, Resources, and Prompts
- **Type Safety**: Full PHP 8.2+ type declarations and validation
- **Extensible**: Clean architecture for extending functionality
- **Testing**: Comprehensive test coverage and testing utilities

## Architecture Highlights

### Core Components
- **Tools**: Executable functions that AI can call to perform actions
- **Resources**: Data sources that AI can read and access
- **Prompts**: Template systems for structured AI interactions

### Transport Layer
- **HTTP Transport**: RESTful API for web-based integrations
- **Stdio Transport**: Standard input/output for desktop AI clients

### Registry System
- **Auto-Discovery**: Automatic component detection in `app/Mcp/` directories
- **Registration**: Centralized component registry with type-specific handling
- **Route Generation**: Automatic Laravel route creation

## Documentation Structure

### Getting Started
- [Installation Guide](installation.md) - System requirements, installation, and setup
- [Quick Start Guide](quick-start.md) - Get up and running quickly

### Component Usage
- [Tools Documentation](usage/tools.md) - Creating and managing MCP Tools
- [Resources Documentation](usage/resources.md) - Creating and managing MCP Resources
- [Prompts Documentation](usage/prompts.md) - Creating and managing MCP Prompts

### Reference & Support
- [API Reference](api-reference.md) - Complete API documentation
- [Troubleshooting](troubleshooting.md) - Common issues and solutions

## Quick Links

### Essential Reading
- [Installation Guide](installation.md) - Start here for setup
- [Quick Start Guide](quick-start.md) - Your first MCP components
- [API Reference](api-reference.md) - Complete reference

### Component Guides
- [Creating Tools](usage/tools.md) - Build executable AI functions
- [Creating Resources](usage/resources.md) - Expose data to AI
- [Creating Prompts](usage/prompts.md) - Template AI interactions

### Help & Support
- [Troubleshooting](troubleshooting.md) - Solutions to common issues
- [GitHub Issues](https://github.com/jerthedev/laravel-mcp/issues) - Report bugs or request features
- [GitHub Discussions](https://github.com/jerthedev/laravel-mcp/discussions) - Community support

## Package Information

- **Author**: Jeremy Fall
- **Email**: jeremy@jerthedev.com  
- **License**: MIT
- **PHP Version**: ^8.2
- **Laravel Version**: ^11.0
- **Package Name**: jerthedev/laravel-mcp

## System Requirements

- PHP 8.2 or higher
- Laravel 11.0 or higher
- Composer package manager
- Optional: Redis for enhanced features
- Optional: Pusher for real-time notifications

## Contributing

We welcome contributions to the Laravel MCP package! Please see our [Contributing Guide](CONTRIBUTING.md) for details on how to contribute to this project.

## License

This package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

Ready to get started? Head over to the [Installation Guide](installation.md) to begin integrating MCP into your Laravel application.