# Contributing to Laravel MCP

Thank you for your interest in contributing to Laravel MCP! We welcome contributions from the community and are pleased to have you join us.

## Code of Conduct

This project and everyone participating in it is governed by the [Laravel MCP Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## How Can I Contribute?

### Reporting Bugs

Before creating bug reports, please check the [existing issues](https://github.com/jerthedev/laravel-mcp/issues) to avoid duplicates. When creating a bug report, please use our [bug report template](.github/ISSUE_TEMPLATE/bug_report.yml) and include as many details as possible.

### Suggesting Features

Feature suggestions are welcome! Please use our [feature request template](.github/ISSUE_TEMPLATE/feature_request.yml) and provide:

- A clear description of the problem you're trying to solve
- Your proposed solution
- Use cases where this feature would be beneficial
- Any implementation ideas you might have

### Improving Documentation

Documentation improvements are always appreciated. This includes:

- Fixing typos or clarifying existing content
- Adding missing documentation
- Creating examples and tutorials
- Improving code comments and docblocks

## Development Setup

### Prerequisites

- PHP 8.2 or higher
- Composer
- Laravel 11.0 or higher (for testing)
- Git

### Setting Up Your Development Environment

1. Fork the repository on GitHub
2. Clone your fork locally:
   ```bash
   git clone https://github.com/YOUR-USERNAME/laravel-mcp.git
   cd laravel-mcp
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Create a new branch for your feature/fix:
   ```bash
   git checkout -b feature/your-feature-name
   ```

### Testing Your Changes

1. Run the test suite:
   ```bash
   ./vendor/bin/phpunit
   ```

2. Check code style:
   ```bash
   composer pint
   ```

3. Run static analysis (when implemented):
   ```bash
   ./vendor/bin/phpstan analyse
   ```

4. Test with a real Laravel application:
   ```bash
   # Create a fresh Laravel app
   composer create-project laravel/laravel test-app
   cd test-app
   
   # Add your local package
   composer config repositories.laravel-mcp path ../laravel-mcp
   composer require jerthedev/laravel-mcp:@dev
   
   # Test the package functionality
   php artisan vendor:publish --tag="laravel-mcp"
   ```

## Coding Standards

### PHP Standards

- Follow PSR-12 coding standards
- Use Laravel's conventions and patterns
- Write descriptive variable and method names
- Add docblocks to all public methods and classes

### Code Style

We use Laravel Pint for code styling. Run it before committing:

```bash
composer pint
```

### Architecture Guidelines

- Follow Laravel package development best practices
- Use Laravel's service container for dependency injection
- Follow the existing namespace structure: `JTD\LaravelMCP\`
- Maintain compatibility with MCP 1.0 specification
- Ensure both HTTP and Stdio transports remain functional

### Writing Tests

- Write tests for all new functionality
- Use Orchestra Testbench for Laravel package testing
- Follow Laravel's testing conventions
- Aim for high test coverage
- Include both unit and feature tests

### Example Test Structure:

```php
<?php

namespace JTD\LaravelMCP\Tests\Unit;

use JTD\LaravelMCP\Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_example_functionality(): void
    {
        // Arrange
        
        // Act
        
        // Assert
    }
}
```

## Submitting Changes

### Pull Request Process

1. Update your branch with the latest changes from main:
   ```bash
   git checkout main
   git pull upstream main
   git checkout your-feature-branch
   git rebase main
   ```

2. Run all tests and ensure they pass:
   ```bash
   composer test  # or ./vendor/bin/phpunit
   composer pint
   ```

3. Commit your changes with a descriptive message:
   ```bash
   git add .
   git commit -m "Add feature: descriptive commit message"
   ```

4. Push to your fork:
   ```bash
   git push origin your-feature-branch
   ```

5. Create a pull request using our [PR template](.github/PULL_REQUEST_TEMPLATE.md)

### Pull Request Guidelines

- Fill out the PR template completely
- Include tests for new functionality
- Update documentation as needed
- Update CHANGELOG.md under the "Unreleased" section
- Ensure all CI checks pass
- Be responsive to code review feedback

### Commit Message Guidelines

- Use the present tense ("Add feature" not "Added feature")
- Use the imperative mood ("Move cursor to..." not "Moves cursor to...")
- Limit the first line to 72 characters or less
- Reference issues and pull requests liberally after the first line

## Package Architecture

### Core Components

Understanding the package architecture will help you contribute effectively:

1. **Service Provider**: Main Laravel integration point
2. **Transport Layer**: HTTP and Stdio communication handlers
3. **Protocol Handler**: JSON-RPC 2.0 and MCP protocol implementation
4. **Base Classes**: Abstract classes for Tools, Resources, and Prompts
5. **Registry System**: Component discovery and registration
6. **Artisan Commands**: CLI tools for development and deployment

### Development Workflow

The package follows a ticket-based development approach:

- Each feature/improvement has a corresponding ticket in `docs/Tickets/`
- Tickets are implemented sequentially to maintain dependencies
- Each ticket includes detailed specifications and acceptance criteria

## Community

### Getting Help

- Create a [discussion](https://github.com/jerthedev/laravel-mcp/discussions) for questions
- Check the [Laravel community resources](https://laravel.com/community)
- Review the [MCP specification](https://spec.modelcontextprotocol.io/)

### Staying Updated

- Watch the repository for updates
- Follow the changelog for new releases
- Join discussions about upcoming features

## Recognition

Contributors will be recognized in:

- CHANGELOG.md for significant contributions
- GitHub contributors list
- Special thanks in release notes for major contributions

## Questions?

Don't hesitate to ask questions! Create a [discussion](https://github.com/jerthedev/laravel-mcp/discussions) or reach out through the [issue tracker](https://github.com/jerthedev/laravel-mcp/issues).

Thank you for contributing to Laravel MCP! 🚀