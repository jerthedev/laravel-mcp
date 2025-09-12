# Middleware Usage Example

This example demonstrates how to create and use custom middleware in the Laravel MCP package for authentication, validation, and request processing.

## Features

- Custom authentication middleware
- Request validation middleware
- Rate limiting implementation
- Error handling middleware
- Middleware stack composition

## Files

- `CustomAuthMiddleware.php` - Authentication middleware
- `McpValidationMiddleware.php` - Request validation
- `RequestLoggingMiddleware.php` - Logging middleware
- `middleware-config.php` - Configuration example
- `README.md` - This documentation

## Usage

### Registering Middleware

In your `config/laravel-mcp.php`:

```php
'middleware' => [
    'global' => [
        \App\Http\Middleware\CustomAuthMiddleware::class,
        \JTD\LaravelMCP\Http\Middleware\McpValidationMiddleware::class,
    ],
    'groups' => [
        'authenticated' => [
            \App\Http\Middleware\CustomAuthMiddleware::class,
            \App\Http\Middleware\RequestLoggingMiddleware::class,
        ],
    ],
],
```

### Applying to Routes

```php
Route::post('/mcp')
    ->middleware(['mcp.validation', 'mcp.auth'])
    ->uses(McpController::class);
```

## Middleware Types

1. **Authentication** - Validates API keys, tokens, or certificates
2. **Validation** - Ensures request format and content validity
3. **Rate Limiting** - Controls request frequency per client
4. **Logging** - Records requests for debugging and monitoring
5. **CORS** - Handles cross-origin requests

## Installation

1. Copy middleware files to `app/Http/Middleware/`
2. Register in your service provider or middleware configuration
3. Apply to MCP routes as needed

## Testing

Run the tests with:

```bash
./vendor/bin/phpunit tests/Unit/Middleware/
```