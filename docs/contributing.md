# Contributing to Laravel MCP

Thank you for your interest in contributing to the Laravel MCP package! This document provides guidelines and instructions for contributing to the project. We welcome contributions from the community and are grateful for any help you can provide.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Setup](#development-setup)
4. [How to Contribute](#how-to-contribute)
5. [Coding Standards](#coding-standards)
6. [Testing Guidelines](#testing-guidelines)
7. [Documentation](#documentation)
8. [Pull Request Process](#pull-request-process)
9. [Community](#community)

## Code of Conduct

Please note that this project is released with a [Contributor Code of Conduct](../CODE_OF_CONDUCT.md). By participating in this project, you agree to abide by its terms.

## Getting Started

### Prerequisites

Before contributing, ensure you have:

- PHP 8.2 or higher
- Composer 2.0 or higher
- Git
- A GitHub account
- Basic knowledge of Laravel and the Model Context Protocol

### Understanding the Project

1. **Read the Documentation**: Familiarize yourself with the [architecture](architecture.md) and [extending guide](extending.md)
2. **Explore the Codebase**: Review the source code structure and existing implementations
3. **Check Issues**: Look at open issues to understand current priorities and problems
4. **Review Pull Requests**: See what others are working on and learn from their contributions

## Development Setup

### Fork and Clone

1. Fork the repository on GitHub
2. Clone your fork locally:
```bash
git clone https://github.com/your-username/laravel-mcp.git
cd laravel-mcp
```

3. Add the upstream repository:
```bash
git remote add upstream https://github.com/JTD-Dev/laravel-mcp.git
```

### Install Dependencies

```bash
composer install
```

### Create a Test Application

```bash
# Create a new Laravel application for testing
composer create-project laravel/laravel test-app
cd test-app

# Link your local package
composer config repositories.laravel-mcp path ../laravel-mcp
composer require "jtd/laravel-mcp:@dev"
```

### Configure Your Environment

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Publish package configuration
php artisan vendor:publish --tag="laravel-mcp"
```

## How to Contribute

### Types of Contributions

We welcome various types of contributions:

#### 1. Bug Fixes
- Fix existing bugs reported in issues
- Improve error handling and edge cases
- Fix documentation errors or typos

#### 2. New Features
- Implement new MCP components (tools, resources, prompts)
- Add new transport layers
- Enhance existing functionality

#### 3. Performance Improvements
- Optimize existing code
- Improve caching strategies
- Reduce memory usage

#### 4. Documentation
- Improve existing documentation
- Add examples and tutorials
- Translate documentation

#### 5. Testing
- Add missing tests
- Improve test coverage
- Add integration tests

### Finding Issues to Work On

Look for issues labeled:
- `good-first-issue` - Great for newcomers
- `help-wanted` - Community help needed
- `enhancement` - New features or improvements
- `bug` - Something needs fixing
- `documentation` - Documentation improvements

### Creating an Issue

Before creating an issue:

1. **Search existing issues** to avoid duplicates
2. **Use issue templates** when available
3. **Provide detailed information**:
   - Clear title and description
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - Environment details (PHP version, Laravel version, etc.)

### Working on an Issue

1. **Comment on the issue** to let others know you're working on it
2. **Ask questions** if you need clarification
3. **Provide updates** on your progress
4. **Reference the issue** in your pull request

## Coding Standards

### PHP Standards

We follow PSR-12 coding standards. Use Laravel Pint to format your code:

```bash
composer pint
```

### Code Style Guidelines

```php
<?php

namespace JTD\LaravelMCP\Example;

use Illuminate\Support\Facades\Log;

/**
 * Example class demonstrating coding standards
 * 
 * @package JTD\LaravelMCP
 */
class ExampleClass
{
    /**
     * @var string
     */
    protected string $property;
    
    /**
     * Constructor
     * 
     * @param string $property
     */
    public function __construct(string $property)
    {
        $this->property = $property;
    }
    
    /**
     * Example method with proper documentation
     * 
     * @param array $data Input data
     * @return array Processed data
     * @throws \InvalidArgumentException When data is invalid
     */
    public function processData(array $data): array
    {
        // Validate input
        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }
        
        // Process data with clear variable names
        $processedData = array_map(function ($item) {
            return $this->transformItem($item);
        }, $data);
        
        // Log the operation
        Log::info('Data processed', [
            'count' => count($processedData),
            'property' => $this->property,
        ]);
        
        return $processedData;
    }
    
    /**
     * Transform a single item
     * 
     * @param mixed $item
     * @return mixed
     */
    protected function transformItem($item)
    {
        // Implementation
        return $item;
    }
}
```

### Best Practices

1. **Use Type Hints**: Always use parameter and return type hints
2. **Write Descriptive Names**: Use clear, descriptive variable and method names
3. **Keep Methods Small**: Methods should do one thing well
4. **Avoid Magic Numbers**: Use constants or configuration values
5. **Handle Errors Gracefully**: Use proper exception handling
6. **Document Complex Logic**: Add comments for non-obvious code
7. **Use Laravel Features**: Leverage Laravel's built-in functionality

## Testing Guidelines

### Writing Tests

All contributions must include tests. Follow these guidelines:

#### Unit Tests

```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use JTD\LaravelMCP\Example\ExampleClass;

class ExampleClassTest extends TestCase
{
    /**
     * @test
     */
    public function it_processes_data_correctly()
    {
        // Arrange
        $example = new ExampleClass('test');
        $input = ['item1', 'item2'];
        
        // Act
        $result = $example->processData($input);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }
    
    /**
     * @test
     */
    public function it_throws_exception_for_empty_data()
    {
        // Arrange
        $example = new ExampleClass('test');
        
        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Data cannot be empty');
        
        // Act
        $example->processData([]);
    }
}
```

#### Feature Tests

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class McpEndpointTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * @test
     */
    public function it_handles_tool_execution_request()
    {
        // Arrange
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'calculator',
                'arguments' => [
                    'operation' => 'add',
                    'a' => 5,
                    'b' => 3,
                ],
            ],
            'id' => 1,
        ];
        
        // Act
        $response = $this->postJson('/mcp', $request);
        
        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'jsonrpc' => '2.0',
                'result' => [
                    'content' => [
                        ['type' => 'text', 'text' => '8'],
                    ],
                ],
                'id' => 1,
            ]);
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
composer test:unit
composer test:feature

# Run with coverage
composer test:coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/ExampleClassTest.php
```

### Test Coverage

- Aim for at least 80% code coverage
- Critical paths should have 100% coverage
- Use `@codeCoverageIgnore` sparingly and with justification

## Documentation

### Documentation Standards

1. **PHPDoc Blocks**: All public methods must have PHPDoc blocks
2. **README Updates**: Update README.md for user-facing changes
3. **Changelog**: Add entries to CHANGELOG.md for notable changes
4. **Examples**: Provide examples for new features
5. **API Documentation**: Update API documentation for new endpoints

### Documentation Structure

```markdown
# Feature Name

## Overview
Brief description of the feature

## Installation/Setup
How to install or configure the feature

## Usage
### Basic Usage
Simple example

### Advanced Usage
Complex example with options

## API Reference
### Methods
- `methodName(parameters): returnType` - Description

### Parameters
- `parameterName` (type) - Description

## Examples
Practical examples with code

## Troubleshooting
Common issues and solutions
```

## Pull Request Process

### Before Submitting

1. **Update your fork**:
```bash
git fetch upstream
git checkout main
git merge upstream/main
```

2. **Create a feature branch**:
```bash
git checkout -b feature/your-feature-name
```

3. **Make your changes**:
- Write clean, documented code
- Add tests for your changes
- Update documentation as needed

4. **Run quality checks**:
```bash
# Format code
composer pint

# Run static analysis
composer analyse

# Run tests
composer test
```

5. **Commit your changes**:
```bash
git add .
git commit -m "feat: add new feature

- Detailed description of changes
- Reference issues: Fixes #123"
```

### Commit Message Format

Follow conventional commits specification:

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation changes
- `style:` Code style changes
- `refactor:` Code refactoring
- `test:` Test additions or changes
- `chore:` Maintenance tasks

### Submitting the Pull Request

1. **Push to your fork**:
```bash
git push origin feature/your-feature-name
```

2. **Create Pull Request**:
- Go to GitHub and create a PR from your branch
- Fill out the PR template completely
- Link related issues
- Add appropriate labels

3. **PR Description Template**:
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests
- [ ] Updated existing tests

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-reviewed code
- [ ] Updated documentation
- [ ] No breaking changes
- [ ] Requested review from maintainers

## Related Issues
Fixes #123
Relates to #456
```

### Code Review Process

1. **Automated Checks**: GitHub Actions will run tests and checks
2. **Peer Review**: At least one maintainer will review your code
3. **Address Feedback**: Make requested changes promptly
4. **Approval**: Once approved, your PR will be merged

### After Merge

- Delete your feature branch
- Update your local repository
- Celebrate your contribution!

## Community

### Communication Channels

- **GitHub Issues**: Bug reports and feature requests
- **GitHub Discussions**: General discussions and questions
- **Discord**: Real-time chat with contributors
- **Twitter**: Follow @LaravelMCP for updates

### Getting Help

If you need help:

1. **Check Documentation**: Review existing documentation
2. **Search Issues**: Look for similar problems
3. **Ask in Discussions**: Post your question with details
4. **Discord Community**: Get real-time help

### Recognition

We value all contributions and recognize contributors in:

- CONTRIBUTORS.md file
- Release notes
- Project README
- Annual contributor report

## Advanced Contributing

### Becoming a Maintainer

Active contributors may be invited to become maintainers. Maintainers:

- Review and merge pull requests
- Triage issues
- Guide project direction
- Mentor new contributors

### Release Process

1. **Version Tagging**: Follow semantic versioning
2. **Changelog Update**: Document all changes
3. **Testing**: Comprehensive testing before release
4. **Documentation**: Update all documentation
5. **Announcement**: Notify community of new release

### Security Vulnerabilities

If you discover a security vulnerability:

1. **Do NOT** create a public issue
2. Email security@laravel-mcp.dev
3. Include detailed information
4. Wait for response before disclosure

## Thank You!

Your contributions make Laravel MCP better for everyone. We appreciate your time, effort, and expertise. Together, we're building something amazing!

## Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Model Context Protocol Specification](https://github.com/anthropics/model-context-protocol)
- [PHP Standards Recommendations](https://www.php-fig.org/psr/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Keep a Changelog](https://keepachangelog.com/)
- [Semantic Versioning](https://semver.org/)