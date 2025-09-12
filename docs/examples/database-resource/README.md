# Database Resource Example

This example demonstrates how to create an MCP Resource that provides access to database records with proper Laravel integration.

## Features

- Read individual user records
- List all users with pagination
- Proper error handling for non-existent records
- Laravel Eloquent integration
- Security considerations for data exposure

## Files

- `UserDatabaseResource.php` - The main resource implementation
- `UserDatabaseResourceTest.php` - Unit tests for the resource
- `README.md` - This documentation

## Usage

### Reading a Specific User

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "resources/read",
  "params": {
    "uri": "database://users/123"
  }
}
```

### Listing Users

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "resources/list",
  "params": {}
}
```

## Installation

1. Copy `UserDatabaseResource.php` to your `app/Mcp/Resources/` directory
2. Ensure you have a User model with appropriate fields
3. The resource will be auto-discovered by the Laravel MCP package

## Database Requirements

This example assumes you have a `users` table with at least these fields:
- `id` (primary key)
- `name`
- `email`
- `created_at`
- `updated_at`

## Security Considerations

This example includes:
- Field filtering to prevent sensitive data exposure
- Input validation
- Proper error messages without information leakage

## Testing

Run the tests with:

```bash
./vendor/bin/phpunit tests/Unit/Resources/UserDatabaseResourceTest.php
```