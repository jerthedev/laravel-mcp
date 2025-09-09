# Quick Start Guide

Get up and running with the Laravel MCP package in just a few minutes. This guide will walk you through creating your first MCP Tool, Resource, and Prompt.

## Prerequisites

Before starting, ensure you have:

1. Completed the [Installation Guide](installation.md)
2. Laravel application running
3. Basic understanding of Laravel concepts

## Step 1: Verify Installation

First, let's verify everything is installed correctly:

```bash
# Check if MCP is enabled
php artisan mcp:status

# List available MCP commands
php artisan list mcp
```

## Step 2: Create Your First MCP Tool

Tools are executable functions that AI clients can call. Let's create a simple calculator tool.

### Generate the Tool

```bash
php artisan make:mcp-tool CalculatorTool
```

This creates `app/Mcp/Tools/CalculatorTool.php`. Let's implement it:

```php
<?php

namespace App\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

class CalculatorTool extends McpTool
{
    protected string $name = 'calculator';
    protected string $description = 'Performs basic mathematical calculations';
    
    protected array $parameterSchema = [
        'operation' => [
            'type' => 'string',
            'description' => 'The operation to perform',
            'enum' => ['add', 'subtract', 'multiply', 'divide'],
            'required' => true,
        ],
        'a' => [
            'type' => 'number',
            'description' => 'First number',
            'required' => true,
        ],
        'b' => [
            'type' => 'number', 
            'description' => 'Second number',
            'required' => true,
        ],
    ];

    protected function handle(array $parameters): mixed
    {
        $a = $parameters['a'];
        $b = $parameters['b'];
        $operation = $parameters['operation'];
        
        $result = match ($operation) {
            'add' => $a + $b,
            'subtract' => $a - $b,
            'multiply' => $a * $b,
            'divide' => $b != 0 ? $a / $b : throw new \InvalidArgumentException('Division by zero'),
        };
        
        return [
            'operation' => $operation,
            'inputs' => ['a' => $a, 'b' => $b],
            'result' => $result,
            'message' => "Calculated {$a} {$operation} {$b} = {$result}",
        ];
    }
}
```

### Test Your Tool

```bash
# List registered tools
php artisan mcp:list --type=tools

# Test the tool (when testing commands are available)
php artisan mcp:test-tool calculator --operation=add --a=5 --b=3
```

## Step 3: Create Your First MCP Resource

Resources provide data that AI clients can read. Let's create a user resource.

### Generate the Resource

```bash
php artisan make:mcp-resource UserResource
```

Edit `app/Mcp/Resources/UserResource.php`:

```php
<?php

namespace App\Mcp\Resources;

use App\Models\User;
use JTD\LaravelMCP\Abstracts\McpResource;

class UserResource extends McpResource
{
    protected string $name = 'users';
    protected string $description = 'Access user information and profiles';
    protected string $uriTemplate = 'users/{id?}';
    protected ?string $modelClass = User::class;

    protected function customRead(array $params): mixed
    {
        // If ID provided, get specific user
        if (isset($params['id'])) {
            $user = User::findOrFail($params['id']);
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ];
        }
        
        // Otherwise, return recent users
        return User::latest()
            ->take(10)
            ->get(['id', 'name', 'email', 'created_at'])
            ->map(fn($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at->toISOString(),
            ])
            ->toArray();
    }

    protected function customList(array $params): array
    {
        $query = User::query();
        
        // Apply search filter
        if (isset($params['search'])) {
            $query->where('name', 'like', '%' . $params['search'] . '%')
                  ->orWhere('email', 'like', '%' . $params['search'] . '%');
        }
        
        // Apply pagination
        $perPage = min($params['per_page'] ?? 15, 100);
        $page = $params['page'] ?? 1;
        
        return $query->paginate($perPage, ['id', 'name', 'email', 'created_at'], 'page', $page)
            ->toArray();
    }
}
```

### Test Your Resource

```bash
# List registered resources
php artisan mcp:list --type=resources

# Test the resource (when testing commands are available)
php artisan mcp:test-resource users
php artisan mcp:test-resource users/1
```

## Step 4: Create Your First MCP Prompt

Prompts generate structured messages for AI interactions. Let's create a code review prompt.

### Generate the Prompt

```bash
php artisan make:mcp-prompt CodeReviewPrompt
```

Edit `app/Mcp/Prompts/CodeReviewPrompt.php`:

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class CodeReviewPrompt extends McpPrompt
{
    protected string $name = 'code_review';
    protected string $description = 'Generate code review prompts for different programming languages';
    
    protected array $arguments = [
        'language' => [
            'description' => 'Programming language of the code',
            'type' => 'string',
            'required' => true,
        ],
        'code' => [
            'description' => 'The code to review',
            'type' => 'string',
            'required' => true,
        ],
        'focus' => [
            'description' => 'Specific focus areas for the review',
            'type' => 'string',
            'required' => false,
        ],
    ];

    protected function customContent(array $arguments): string
    {
        $language = $arguments['language'];
        $code = $arguments['code'];
        $focus = $arguments['focus'] ?? 'best practices, performance, and security';
        
        return "Please review the following {$language} code with a focus on {$focus}:\n\n" .
               "```{$language}\n{$code}\n```\n\n" .
               "Please provide:\n" .
               "1. Overall assessment\n" .
               "2. Specific issues or improvements\n" .
               "3. Best practice recommendations\n" .
               "4. Performance considerations\n" .
               "5. Security concerns (if any)";
    }
}
```

### Test Your Prompt

```bash
# List registered prompts
php artisan mcp:list --type=prompts

# Test the prompt (when testing commands are available)
php artisan mcp:test-prompt code_review --language=php --code="echo 'Hello World';"
```

## Step 5: Verify Everything Works

Let's test our complete setup:

```bash
# List all registered MCP components
php artisan mcp:list

# Clear caches to ensure everything is registered
php artisan optimize:clear

# Check MCP server status
php artisan mcp:status
```

You should see output similar to:
```
MCP Components Registered:

Tools:
  - calculator: Performs basic mathematical calculations

Resources: 
  - users: Access user information and profiles

Prompts:
  - code_review: Generate code review prompts for different programming languages
```

## Step 6: Connect to AI Client (Optional)

If you want to test with an actual AI client like Claude Desktop:

### For Stdio Transport

Create or update your Claude Desktop configuration (`~/Library/Application Support/Claude/claude_desktop_config.json` on macOS):

```json
{
  "mcpServers": {
    "laravel-app": {
      "command": "php",
      "args": ["artisan", "mcp:serve"],
      "cwd": "/path/to/your/laravel/app"
    }
  }
}
```

### For HTTP Transport

If using HTTP transport, update your `.env`:

```env
MCP_HTTP_ENABLED=true
MCP_DEFAULT_TRANSPORT=http
```

Then your AI client can connect to: `http://your-app.test/mcp`

## Next Steps

Congratulations! You've successfully created your first MCP components. Here's what to explore next:

### Learn More About Each Component Type
- **Tools**: [Advanced Tools Guide](usage/tools.md) - Parameter validation, authentication, middleware
- **Resources**: [Advanced Resources Guide](usage/resources.md) - Complex queries, subscriptions, caching  
- **Prompts**: [Advanced Prompts Guide](usage/prompts.md) - Blade templates, dynamic content, argument validation

### Advanced Features
- **Authentication**: Secure your MCP endpoints
- **Middleware**: Add custom request processing
- **Events**: Hook into MCP lifecycle events
- **Testing**: Write tests for your components

### Production Considerations
- **Performance**: Optimize for production use
- **Monitoring**: Track MCP usage and errors
- **Security**: Implement proper authorization
- **Scaling**: Handle high-volume requests

## Common Patterns

### Simple Tool Pattern
```php
class MyTool extends McpTool
{
    protected function handle(array $parameters): mixed
    {
        // Your logic here
        return ['result' => 'success'];
    }
}
```

### Model-Based Resource Pattern
```php
class MyResource extends McpResource
{
    protected ?string $modelClass = MyModel::class;
    // Automatic CRUD operations provided
}
```

### Template-Based Prompt Pattern  
```php
class MyPrompt extends McpPrompt
{
    protected ?string $template = 'mcp.prompts.my-template';
    // Uses Blade template with arguments
}
```

## Troubleshooting

If something isn't working:

1. **Check Logs**: `tail -f storage/logs/laravel.log`
2. **Clear Caches**: `php artisan optimize:clear`
3. **Verify Directory Structure**: Ensure files are in correct `app/Mcp/` subdirectories
4. **Check Configuration**: Verify `.env` and config files
5. **Review Documentation**: Check the [Troubleshooting Guide](troubleshooting.md)

## Getting Help

- [API Reference](api-reference.md) - Complete method documentation
- [Troubleshooting Guide](troubleshooting.md) - Common issues and solutions  
- [GitHub Issues](https://github.com/jerthedev/laravel-mcp/issues) - Bug reports and feature requests
- [GitHub Discussions](https://github.com/jerthedev/laravel-mcp/discussions) - Community support

---

**Ready for advanced usage?** Explore the detailed component guides:
- [Tools Documentation](usage/tools.md)
- [Resources Documentation](usage/resources.md)  
- [Prompts Documentation](usage/prompts.md)