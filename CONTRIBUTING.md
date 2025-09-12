# Contributing to Laravel MCP

First off, thank you for considering contributing to Laravel MCP! It's people like you that make Laravel MCP such a great tool for the Laravel and AI community.

## Quick Links

- [Detailed Contributing Guide](docs/contributing.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Development Setup](#development-setup)
- [Submitting Changes](#submitting-changes)

## Code of Conduct

This project and everyone participating in it is governed by the [Laravel MCP Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## Development Setup

```bash
# Fork and clone the repository
git clone https://github.com/your-username/laravel-mcp.git
cd laravel-mcp

# Install dependencies
composer install

# Run tests
composer test

# Run code style fixes
composer pint
```

## Submitting Changes

### Quick Process

1. **Fork** the repository
2. **Create** a feature branch (`git checkout -b feature/amazing-feature`)
3. **Commit** your changes (`git commit -m 'feat: add amazing feature'`)
4. **Push** to the branch (`git push origin feature/amazing-feature`)
5. **Open** a Pull Request

### Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

- `feat:` - New feature
- `fix:` - Bug fix
- `docs:` - Documentation only changes
- `style:` - Code style changes (formatting, etc)
- `refactor:` - Code change that neither fixes a bug nor adds a feature
- `test:` - Adding missing tests or correcting existing tests
- `chore:` - Changes to the build process or auxiliary tools

### Before Submitting

Please ensure:

- [ ] Tests pass (`composer test`)
- [ ] Code is formatted (`composer pint`)
- [ ] Static analysis passes (`composer analyse`)
- [ ] Documentation is updated if needed
- [ ] Changelog entry is added for notable changes

## What Can I Contribute?

### Good First Issues

Look for issues labeled [`good-first-issue`](https://github.com/JTD-Dev/laravel-mcp/labels/good-first-issue) - these are great for newcomers!

### Types of Contributions

- **Bug Fixes**: Help us squash bugs
- **Features**: Add new MCP tools, resources, or prompts
- **Documentation**: Improve guides, add examples, fix typos
- **Tests**: Increase test coverage
- **Performance**: Optimize existing code
- **Translations**: Help translate documentation

### Feature Requests

Have an idea? [Open an issue](https://github.com/JTD-Dev/laravel-mcp/issues/new) and tag it as `enhancement`.

## Need Help?

- Check our [detailed contributing guide](docs/contributing.md)
- Review the [documentation](docs/)
- Ask in [GitHub Discussions](https://github.com/JTD-Dev/laravel-mcp/discussions)
- Join our [Discord community](#)

## Recognition

Contributors are recognized in:
- [CONTRIBUTORS.md](CONTRIBUTORS.md)
- Release notes
- Project README

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
composer test                # Run all tests
composer test:unit           # Run unit tests only
composer test:feature        # Run feature tests only
composer test:coverage       # Run tests with coverage

# Code quality
composer pint               # Fix code style
composer analyse            # Run static analysis

# Documentation
php artisan mcp:docs        # Generate documentation
```

## Pull Request Guidelines

1. **Update tests** - Ensure all tests pass
2. **Update docs** - Keep documentation current
3. **One feature per PR** - Makes review easier
4. **Follow code style** - Run `composer pint`
5. **Write clear commits** - Use conventional commits
6. **Update changelog** - Note breaking changes

## Questions?

Feel free to [open an issue](https://github.com/JTD-Dev/laravel-mcp/issues/new) or ask in [discussions](https://github.com/JTD-Dev/laravel-mcp/discussions).

Thank you for contributing! 🎉