# Installation Guide

This guide walks you through installing and setting up the Laravel MCP package in your Laravel application.

## System Requirements

Before installing the Laravel MCP package, ensure your system meets the following requirements:

### Required
- **PHP**: 8.2 or higher
- **Laravel**: 11.0 or higher  
- **Composer**: Latest stable version
- **Extensions**: JSON, OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, BCMath

### Optional
- **Redis**: For enhanced caching and session features
- **Pusher**: For real-time notification features

## Installation Steps

### Step 1: Install via Composer

Install the package using Composer:

```bash
composer require jerthedev/laravel-mcp
```

### Step 2: Service Provider Registration

The package uses Laravel's auto-discovery feature, so the service provider will be automatically registered. If you have disabled auto-discovery, manually register the service provider in your `config/app.php`:

```php
'providers' => [
    // Other service providers...
    JTD\LaravelMCP\LaravelMcpServiceProvider::class,
],
```

### Step 3: Publish Configuration Files

Publish the package configuration files:

```bash
# Publish all MCP configuration files
php artisan vendor:publish --tag="laravel-mcp"

# Or publish specific configurations
php artisan vendor:publish --tag="laravel-mcp-config"
php artisan vendor:publish --tag="laravel-mcp-routes"
```

This will create the following configuration files:
- `config/laravel-mcp.php` - Main package configuration
- `config/mcp-transports.php` - Transport-specific settings
- `routes/mcp.php` - MCP-specific routes (if using HTTP transport)

### Step 4: Environment Configuration

Add the following environment variables to your `.env` file:

```env
# MCP Configuration
MCP_ENABLED=true
MCP_DEFAULT_TRANSPORT=stdio
MCP_HTTP_ENABLED=false
MCP_STDIO_ENABLED=true

# MCP Discovery Paths (optional - defaults are used if not set)
MCP_DISCOVERY_ENABLED=true
MCP_TOOLS_PATH=app/Mcp/Tools
MCP_RESOURCES_PATH=app/Mcp/Resources
MCP_PROMPTS_PATH=app/Mcp/Prompts

# MCP HTTP Transport (if enabled)
MCP_HTTP_MIDDLEWARE=api
MCP_HTTP_PREFIX=mcp
MCP_HTTP_RATE_LIMIT=60

# MCP Authentication (optional)
MCP_REQUIRE_AUTH=false
MCP_AUTH_GUARD=api
```

### Step 5: Create Directory Structure

Create the default directory structure for MCP components:

```bash
# Create MCP directories
mkdir -p app/Mcp/Tools
mkdir -p app/Mcp/Resources  
mkdir -p app/Mcp/Prompts

# Create example directories (optional)
mkdir -p app/Mcp/Tools/Examples
mkdir -p app/Mcp/Resources/Examples
mkdir -p app/Mcp/Prompts/Examples
```

### Step 6: Clear Application Cache

Clear Laravel's configuration and route cache:

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### Step 7: Verify Installation

Verify the installation by running:

```bash
# Check if MCP commands are available
php artisan list mcp

# Check MCP status (when implemented)
php artisan mcp:status
```

## Configuration Options

### Main Configuration (`config/laravel-mcp.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | MCP Package Enabled
    |--------------------------------------------------------------------------
    | 
    | This option controls whether the MCP package is enabled. When disabled,
    | no MCP functionality will be available.
    |
    */
    'enabled' => env('MCP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Default Transport
    |--------------------------------------------------------------------------
    |
    | The default transport method for MCP communications. Available options:
    | 'stdio', 'http'
    |
    */
    'default_transport' => env('MCP_DEFAULT_TRANSPORT', 'stdio'),

    /*
    |--------------------------------------------------------------------------
    | Component Discovery
    |--------------------------------------------------------------------------
    |
    | Configuration for automatic component discovery.
    |
    */
    'discovery' => [
        'enabled' => env('MCP_DISCOVERY_ENABLED', true),
        'paths' => [
            'tools' => env('MCP_TOOLS_PATH', 'app/Mcp/Tools'),
            'resources' => env('MCP_RESOURCES_PATH', 'app/Mcp/Resources'),
            'prompts' => env('MCP_PROMPTS_PATH', 'app/Mcp/Prompts'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | MCP authentication configuration.
    |
    */
    'auth' => [
        'required' => env('MCP_REQUIRE_AUTH', false),
        'guard' => env('MCP_AUTH_GUARD', 'api'),
    ],
];
```

### Transport Configuration (`config/mcp-transports.php`)

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | HTTP Transport Configuration
    |--------------------------------------------------------------------------
    */
    'http' => [
        'enabled' => env('MCP_HTTP_ENABLED', false),
        'middleware' => env('MCP_HTTP_MIDDLEWARE', 'api'),
        'prefix' => env('MCP_HTTP_PREFIX', 'mcp'),
        'rate_limit' => env('MCP_HTTP_RATE_LIMIT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stdio Transport Configuration  
    |--------------------------------------------------------------------------
    */
    'stdio' => [
        'enabled' => env('MCP_STDIO_ENABLED', true),
        'timeout' => env('MCP_STDIO_TIMEOUT', 30),
        'buffer_size' => env('MCP_STDIO_BUFFER_SIZE', 8192),
    ],
];
```

## Optional Dependencies

### Redis Setup (Optional)

If you want to use Redis features:

```bash
# Install Redis PHP extension (if not installed)
# Ubuntu/Debian:
sudo apt-get install php-redis

# Install Predis (alternative Redis client)
composer require predis/predis
```

Update your `.env` file:
```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Pusher Setup (Optional)

For real-time features:

```bash
composer require pusher/pusher-php-server
```

Update your `.env` file:
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your-app-id
PUSHER_APP_KEY=your-app-key
PUSHER_APP_SECRET=your-app-secret
PUSHER_APP_CLUSTER=your-app-cluster
```

## Troubleshooting Installation

### Common Issues

**1. Composer Installation Fails**
```bash
# Clear Composer cache and try again
composer clear-cache
composer install --no-cache
```

**2. Service Provider Not Found**
```bash
# Manually register in config/app.php
'providers' => [
    JTD\LaravelMCP\LaravelMcpServiceProvider::class,
],
```

**3. Configuration Publishing Fails**
```bash
# Force publish configuration
php artisan vendor:publish --tag="laravel-mcp" --force
```

**4. Permission Issues**
```bash
# Fix directory permissions
chmod -R 755 app/Mcp/
chown -R www-data:www-data app/Mcp/
```

### Verification Steps

After installation, verify everything is working:

```bash
# 1. Check configuration is loaded
php artisan config:show laravel-mcp

# 2. Check if MCP commands are available  
php artisan list mcp

# 3. Test component discovery
php artisan mcp:list

# 4. Clear all caches
php artisan optimize:clear
```

## Next Steps

Once installation is complete:

1. **Create Your First Components**: Follow the [Quick Start Guide](quick-start.md)
2. **Learn About Tools**: Read the [Tools Documentation](usage/tools.md)  
3. **Explore Resources**: Check out [Resources Documentation](usage/resources.md)
4. **Understand Prompts**: Review [Prompts Documentation](usage/prompts.md)

## Getting Help

If you encounter any issues during installation:

1. Check the [Troubleshooting Guide](troubleshooting.md)
2. Review [GitHub Issues](https://github.com/jerthedev/laravel-mcp/issues)
3. Join [GitHub Discussions](https://github.com/jerthedev/laravel-mcp/discussions)
4. Contact: jeremy@jerthedev.com

---

**Ready to continue?** Head to the [Quick Start Guide](quick-start.md) to create your first MCP components!