# Troubleshooting Guide

This guide helps you diagnose and resolve common issues with the Laravel MCP package. Use this guide to quickly identify and fix problems in your MCP implementation.

## Table of Contents

- [Common Issues](#common-issues)
- [Installation Problems](#installation-problems)
- [Configuration Issues](#configuration-issues)
- [Component Registration Problems](#component-registration-problems)
- [Runtime Errors](#runtime-errors)
- [Performance Issues](#performance-issues)
- [Transport Problems](#transport-problems)
- [Authentication and Authorization](#authentication-and-authorization)
- [Debugging Tools](#debugging-tools)
- [Getting Help](#getting-help)

## Common Issues

### 1. Components Not Being Discovered

**Symptoms:**
- `php artisan mcp:list` shows no components
- Tools/Resources/Prompts not appearing in registry
- "Component not found" errors

**Causes & Solutions:**

**Wrong Directory Structure**
```bash
# ❌ Wrong
app/MCP/Tools/MyTool.php

# ✅ Correct
app/Mcp/Tools/MyTool.php
app/Mcp/Resources/MyResource.php
app/Mcp/Prompts/MyPrompt.php
```

**Namespace Issues**
```php
// ❌ Wrong namespace
namespace App\MCP\Tools;

// ✅ Correct namespace
namespace App\Mcp\Tools;
```

**Class Not Extending Base Class**
```php
// ❌ Wrong - not extending base class
class MyTool {}

// ✅ Correct - extending base class
class MyTool extends McpTool {}
```

**Discovery Disabled**
```bash
# Check if discovery is enabled
MCP_DISCOVERY_ENABLED=true
```

**Cache Issues**
```bash
# Clear all caches
php artisan optimize:clear
php artisan config:clear
php artisan mcp:clear
```

### 2. Parameter Validation Errors

**Symptoms:**
- "Validation failed" errors
- Parameters not being accepted
- Type mismatch errors

**Solutions:**

**Check Parameter Schema**
```php
// Ensure schema matches your parameters
protected array $parameterSchema = [
    'name' => [
        'type' => 'string',
        'required' => true,  // ← Make sure this is correct
    ],
];
```

**Verify Required Parameters**
```php
// Check what parameters are actually required
public function getRequiredParameters(): array
{
    return array_keys(array_filter($this->parameterSchema, function ($schema) {
        return $schema['required'] ?? false;
    }));
}
```

**Debug Incoming Parameters**
```php
protected function handle(array $parameters): mixed
{
    // Add debug logging
    if (config('app.debug')) {
        logger()->debug('Tool parameters received', $parameters);
    }
    
    // Your tool logic...
}
```

### 3. Authentication Problems

**Symptoms:**
- "Unauthorized" errors
- Auth required but not working
- Permission denied errors

**Solutions:**

**Check Auth Configuration**
```bash
# Verify auth settings
MCP_REQUIRE_AUTH=true
MCP_AUTH_GUARD=api
```

**Verify Auth Guard**
```php
// In config/auth.php, ensure guard is configured
'guards' => [
    'api' => [
        'driver' => 'token',
        'provider' => 'users',
        'hash' => false,
    ],
],
```

**Debug Authorization**
```php
protected function authorize(array $parameters): bool
{
    if (config('app.debug')) {
        logger()->debug('Auth check', [
            'user_id' => auth()->id(),
            'is_authenticated' => auth()->check(),
            'guard' => auth()->getDefaultDriver(),
        ]);
    }
    
    return parent::authorize($parameters);
}
```

## Installation Problems

### Composer Installation Issues

**Problem: Package not found**
```bash
# Error message
Package jerthedev/laravel-mcp not found
```

**Solutions:**
```bash
# Clear Composer cache
composer clear-cache

# Update Composer
composer self-update

# Try with specific version
composer require jerthedev/laravel-mcp:^1.0
```

**Problem: Version conflicts**
```bash
# Check PHP version
php -v  # Should be 8.2+

# Check Laravel version
php artisan --version  # Should be 11.0+

# Update dependencies
composer update
```

### Service Provider Issues

**Problem: Service provider not registered**

**Auto-discovery disabled:**
```php
// In composer.json
"extra": {
    "laravel": {
        "dont-discover": [
            "jerthedev/laravel-mcp"
        ]
    }
}
```

**Manual registration:**
```php
// In config/app.php
'providers' => [
    // Other providers...
    JTD\LaravelMCP\LaravelMcpServiceProvider::class,
],
```

### Configuration Publishing Issues

**Problem: Config files not published**
```bash
# Force publish all configs
php artisan vendor:publish --tag="laravel-mcp" --force

# Publish specific configs
php artisan vendor:publish --tag="laravel-mcp-config" --force
```

**Problem: Permission errors**
```bash
# Fix directory permissions
sudo chmod -R 755 config/
sudo chown -R $USER:$USER config/
```

## Configuration Issues

### Environment Variables Not Working

**Problem: Config values not being read from .env**

**Check config caching:**
```bash
# Clear config cache
php artisan config:clear

# Check current config values
php artisan config:show laravel-mcp
```

**Verify .env syntax:**
```bash
# ❌ Wrong - spaces around equals
MCP_ENABLED = true

# ✅ Correct - no spaces
MCP_ENABLED=true

# ❌ Wrong - missing quotes for strings with spaces
MCP_TOOLS_PATH=app/Mcp Tools

# ✅ Correct - quoted strings
MCP_TOOLS_PATH="app/Mcp Tools"
```

### Transport Configuration Issues

**Problem: HTTP transport not working**
```bash
# Check HTTP transport config
MCP_HTTP_ENABLED=true
MCP_DEFAULT_TRANSPORT=http
MCP_HTTP_PORT=8000
```

**Problem: Routes not registered**
```bash
# Check if routes are loaded
php artisan route:list | grep mcp

# Clear route cache
php artisan route:clear
```

## Component Registration Problems

### Tools Not Loading

**Diagnosis Steps:**

1. **Check File Location**
```bash
# Verify file exists in correct location
ls -la app/Mcp/Tools/
```

2. **Verify Class Structure**
```php
<?php
// File: app/Mcp/Tools/MyTool.php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class MyTool extends McpTool
{
    protected function handle(array $parameters): mixed
    {
        return ['result' => 'success'];
    }
}
```

3. **Check for Syntax Errors**
```bash
# Check for PHP syntax errors
php -l app/Mcp/Tools/MyTool.php
```

4. **Manual Registration Test**
```php
// In a service provider or AppServiceProvider
use App\Mcp\Tools\MyTool;
use JTD\LaravelMCP\Facades\Mcp;

public function boot()
{
    try {
        Mcp::registerTool('my_tool', new MyTool());
        logger()->info('Tool registered successfully');
    } catch (\Exception $e) {
        logger()->error('Tool registration failed: ' . $e->getMessage());
    }
}
```

### Resources Not Loading

**Common Issues:**

1. **Model Class Not Found**
```php
// Make sure model exists and is imported
use App\Models\User;  // ← Add this import

class UserResource extends McpResource
{
    protected ?string $modelClass = User::class;
}
```

2. **URI Template Issues**
```php
// ❌ Invalid URI template
protected string $uriTemplate = 'users{id}';  // Missing slash

// ✅ Valid URI template
protected string $uriTemplate = 'users/{id?}';
```

### Prompts Not Loading

**Common Issues:**

1. **Template File Not Found**
```php
// Check if Blade template exists
protected ?string $template = 'mcp.prompts.my-template';

// Template should be at:
// resources/views/mcp/prompts/my-template.blade.php
```

2. **Argument Schema Issues**
```php
// ❌ Invalid argument definition
protected array $arguments = [
    'name' => 'string',  // Missing array structure
];

// ✅ Valid argument definition
protected array $arguments = [
    'name' => [
        'type' => 'string',
        'required' => true,
    ],
];
```

## Runtime Errors

### Memory Errors

**Problem: Out of memory errors**
```bash
# Increase memory limit
ini_set('memory_limit', '256M');

# Or in php.ini
memory_limit = 256M
```

**Optimize queries:**
```php
// ❌ Loading all records
$users = User::all();

// ✅ Use pagination
$users = User::paginate(15);

// ✅ Select only needed fields
$users = User::select(['id', 'name', 'email'])->paginate(15);
```

### Database Connection Issues

**Problem: Database connection errors**

**Check database configuration:**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();
```

**Connection timeouts:**
```php
// In database config
'mysql' => [
    'options' => [
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ],
],
```

### Class Not Found Errors

**Problem: Class not found during runtime**

**Autoloader issues:**
```bash
# Regenerate autoloader
composer dump-autoload

# Clear class cache
php artisan clear-compiled
php artisan optimize:clear
```

**Namespace issues:**
```php
// Check that class namespace matches directory structure
// File: app/Mcp/Tools/Calculator/AdvancedTool.php
namespace App\Mcp\Tools\Calculator;  // ← Must match path
```

## Performance Issues

### Slow Component Discovery

**Problem: Discovery takes too long**

**Enable discovery caching:**
```bash
MCP_DISCOVERY_CACHE=true
```

**Limit discovery paths:**
```php
// In config/laravel-mcp.php
'discovery' => [
    'paths' => [
        'tools' => 'app/Mcp/Tools',
        // Remove unused paths
        // 'resources' => 'app/Mcp/Resources',
        // 'prompts' => 'app/Mcp/Prompts',
    ],
],
```

### Slow Tool Execution

**Problem: Tools take too long to execute**

**Add caching:**
```php
protected function handle(array $parameters): mixed
{
    $cacheKey = 'tool.' . $this->getName() . '.' . md5(serialize($parameters));
    
    return Cache::remember($cacheKey, 300, function () use ($parameters) {
        return $this->expensiveOperation($parameters);
    });
}
```

**Database query optimization:**
```php
// ❌ N+1 query problem
foreach ($users as $user) {
    echo $user->profile->name;  // Separate query for each user
}

// ✅ Eager loading
$users = User::with('profile')->get();
foreach ($users as $user) {
    echo $user->profile->name;  // No additional queries
}
```

### Memory Leaks

**Problem: Memory usage keeps growing**

**Use generators for large datasets:**
```php
protected function processLargeDataset(): \Generator
{
    foreach (User::lazy() as $user) {
        yield $this->processUser($user);
    }
}
```

**Clear object references:**
```php
protected function handle(array $parameters): mixed
{
    $result = $this->heavyProcessing($parameters);
    
    // Clear references to large objects
    unset($this->largeDataArray);
    
    return $result;
}
```

## Transport Problems

### HTTP Transport Issues

**Problem: HTTP requests failing**

**Check server configuration:**
```bash
# Test HTTP endpoint directly
curl -X POST http://localhost:8000/mcp/tools/list \
  -H "Content-Type: application/json"
```

**CORS issues:**
```php
// In config/mcp-transports.php
'http' => [
    'cors' => [
        'enabled' => true,
        'origins' => ['*'],  // Or specific domains
        'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'headers' => ['Content-Type', 'Authorization'],
    ],
],
```

**Middleware conflicts:**
```php
// Check middleware configuration
'http' => [
    'middleware' => ['api'],  // Make sure middleware exists
],
```

### Stdio Transport Issues

**Problem: Stdio communication failing**

**Buffer size issues:**
```bash
# Increase buffer size
MCP_STDIO_BUFFER_SIZE=16384
```

**Encoding problems:**
```bash
# Ensure correct encoding
MCP_STDIO_ENCODING=utf-8
```

**Timeout issues:**
```bash
# Increase timeout
MCP_STDIO_TIMEOUT=60
```

## Authentication and Authorization

### Token Authentication Issues

**Problem: API tokens not working**

**Check token middleware:**
```php
// In routes/api.php
Route::middleware(['auth:sanctum'])->group(function () {
    // MCP routes
});
```

**Token generation:**
```php
// Generate token for testing
$user = User::find(1);
$token = $user->createToken('mcp-test')->plainTextToken;
echo "Token: " . $token;
```

### Permission Issues

**Problem: Users can't access certain components**

**Check authorization logic:**
```php
protected function authorize(array $parameters): bool
{
    $user = auth()->user();
    
    // Debug authorization
    if (config('app.debug')) {
        logger()->debug('Authorization check', [
            'user_id' => $user?->id,
            'user_permissions' => $user?->permissions ?? [],
            'required_permission' => 'use-mcp-tools',
        ]);
    }
    
    return $user && $user->can('use-mcp-tools');
}
```

### Role-Based Access Issues

**Problem: Role-based access not working**

**Install and configure roles package:**
```bash
# If using Spatie Permission
composer require spatie/laravel-permission

php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
```

**Define permissions:**
```php
// Create permissions
Permission::create(['name' => 'use-mcp-tools']);
Permission::create(['name' => 'use-mcp-resources']);
Permission::create(['name' => 'use-mcp-prompts']);

// Assign to roles
$role = Role::create(['name' => 'mcp-user']);
$role->givePermissionTo(['use-mcp-tools', 'use-mcp-resources']);
```

## Debugging Tools

### Enable Debug Mode

**Laravel debug mode:**
```bash
APP_DEBUG=true
MCP_LOGGING_ENABLED=true
MCP_LOG_LEVEL=debug
```

### Logging MCP Operations

**Add comprehensive logging:**
```php
// In AppServiceProvider
use JTD\LaravelMCP\Events\McpToolExecuted;
use JTD\LaravelMCP\Events\McpResourceAccessed;

public function boot()
{
    Event::listen(McpToolExecuted::class, function ($event) {
        Log::info('MCP Tool executed', [
            'tool' => $event->toolName,
            'parameters' => $event->parameters,
            'execution_time' => $event->executionTime,
            'user_id' => $event->userId,
        ]);
    });
    
    Event::listen(McpResourceAccessed::class, function ($event) {
        Log::info('MCP Resource accessed', [
            'resource' => $event->resourceName,
            'action' => $event->action,
            'user_id' => $event->userId,
        ]);
    });
}
```

### Command Line Debugging

**Useful debugging commands:**
```bash
# Check MCP status
php artisan mcp:status

# List all components with details
php artisan mcp:list --verbose

# Test component registration
php artisan tinker
>>> app('mcp.registry')->getTools()
>>> app('mcp.registry')->getResources()
>>> app('mcp.registry')->getPrompts()

# Check configuration
php artisan config:show laravel-mcp
php artisan config:show mcp-transports

# Monitor logs in real-time
tail -f storage/logs/laravel.log | grep MCP
```

### Custom Debug Tool

**Create a debug tool:**
```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class DebugTool extends McpTool
{
    protected string $name = 'debug';
    protected string $description = 'Debug MCP package state';
    
    protected function handle(array $parameters): mixed
    {
        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'mcp_config' => config('laravel-mcp'),
            'registered_tools' => array_keys(app('mcp.registry')->getTools()),
            'registered_resources' => array_keys(app('mcp.registry')->getResources()),
            'registered_prompts' => array_keys(app('mcp.registry')->getPrompts()),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
```

## Error Messages Reference

### Common Error Messages and Solutions

**"Component not found"**
- Check component is in correct directory
- Verify namespace matches directory structure
- Clear caches: `php artisan optimize:clear`

**"Validation failed"**
- Check parameter schema matches input
- Verify required parameters are provided
- Check parameter types match schema

**"Unauthorized"**
- Verify authentication is working
- Check authorization logic
- Ensure user has required permissions

**"Class not found"**
- Run `composer dump-autoload`
- Check namespace declaration
- Verify file location matches namespace

**"Transport not available"**
- Check transport configuration
- Verify transport is enabled
- Check middleware configuration

## Performance Monitoring

### Monitor Component Performance

**Add timing to components:**
```php
protected function handle(array $parameters): mixed
{
    $startTime = microtime(true);
    
    try {
        $result = $this->performOperation($parameters);
        
        return $result;
    } finally {
        $executionTime = microtime(true) - $startTime;
        
        if ($executionTime > 1.0) {  // Log slow operations
            logger()->warning('Slow MCP operation', [
                'component' => $this->getName(),
                'execution_time' => $executionTime,
                'parameters' => $parameters,
            ]);
        }
    }
}
```

### Memory Usage Monitoring

**Monitor memory usage:**
```php
protected function handle(array $parameters): mixed
{
    $memoryStart = memory_get_usage();
    
    $result = $this->performOperation($parameters);
    
    $memoryEnd = memory_get_usage();
    $memoryUsed = $memoryEnd - $memoryStart;
    
    if ($memoryUsed > 10 * 1024 * 1024) {  // Log high memory usage (10MB+)
        logger()->warning('High memory usage', [
            'component' => $this->getName(),
            'memory_used' => $memoryUsed,
            'memory_peak' => memory_get_peak_usage(),
        ]);
    }
    
    return $result;
}
```

## Getting Help

### Before Asking for Help

1. **Check this troubleshooting guide** for your specific issue
2. **Search existing issues** on GitHub
3. **Enable debug mode** and check logs
4. **Try the minimal reproduction** case
5. **Gather system information** (PHP version, Laravel version, package version)

### GitHub Issues

When creating a GitHub issue, include:

**System Information:**
```bash
# Run these commands and include output
php -v
php artisan --version
composer show jerthedev/laravel-mcp
php artisan mcp:status
```

**Configuration:**
```bash
# Include relevant configuration
cat .env | grep MCP
php artisan config:show laravel-mcp
```

**Error Details:**
- Full error message and stack trace
- Steps to reproduce the issue
- Expected vs actual behavior
- Relevant log entries

### Community Support

- **GitHub Discussions**: For questions and community support
- **Stack Overflow**: Tag questions with `laravel-mcp`
- **Laravel Community**: Slack, Discord, forums

### Professional Support

For professional support and consulting:
- **Email**: jeremy@jerthedev.com
- **Custom Development**: Available for custom MCP integrations
- **Training**: Available for team training on MCP package usage

---

**Quick Fix Checklist:**

□ Clear all caches: `php artisan optimize:clear`  
□ Check file locations and namespaces  
□ Verify configuration values  
□ Enable debug mode and check logs  
□ Test with minimal example  
□ Check database connections  
□ Verify authentication setup  
□ Review middleware configuration  

If issues persist after trying these steps, please create a GitHub issue with detailed information.