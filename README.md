# Laravel MCP

A comprehensive Laravel package that provides seamless integration with the Model Context Protocol (MCP), enabling Laravel developers to easily create MCP servers that can expose Tools, Resources, and Prompts to AI applications like Claude Desktop, Claude Code, and ChatGPT Desktop.

## Installation

You can install the package via composer:

```bash
composer require jerthedev/laravel-mcp
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-mcp-config"
```

This will create a `config/laravel-mcp.php` file where you can customize the package settings.

## Usage

This package is currently in development. Full documentation will be available once the implementation is complete.

## Roadmap

- [ ] Core MCP protocol implementation
- [ ] HTTP and Stdio transport layers  
- [ ] Tool, Resource, and Prompt abstractions
- [ ] Component auto-discovery
- [ ] Artisan commands for code generation
- [ ] Client configuration generation
- [ ] Comprehensive documentation

## Requirements

- PHP 8.2+
- Laravel 11.0+

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.