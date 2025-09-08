# Package Structure Specification

## Directory Structure

### Package Root Structure
```
jerthedev/laravel-mcp/
├── src/                           # Package source code
├── config/                        # Package configuration files
├── routes/                        # Default MCP routes
├── resources/                     # Package resources
│   ├── stubs/                     # Code generation stubs
│   └── views/                     # Blade views (if needed)
├── tests/                         # Package tests
├── docs/                          # Documentation
│   ├── Specs/                     # Technical specifications
│   └── Examples/                  # Usage examples
├── .github/                       # GitHub workflows and templates
├── composer.json                  # Composer configuration
├── README.md                      # Package overview
└── CHANGELOG.md                   # Version history
```

### Source Code Structure (`src/`)
```
src/
├── LaravelMcpServiceProvider.php          # Main service provider
├── Commands/                              # Artisan commands
│   ├── ServeCommand.php                   # mcp:serve command
│   ├── MakeToolCommand.php                # make:mcp-tool command
│   ├── MakeResourceCommand.php            # make:mcp-resource command
│   ├── MakePromptCommand.php              # make:mcp-prompt command
│   ├── ListCommand.php                    # mcp:list command
│   └── RegisterCommand.php               # mcp:register command
├── Http/                                  # HTTP transport components
│   ├── Controllers/
│   │   └── McpController.php              # Main MCP HTTP controller
│   └── Middleware/
│       ├── McpAuthMiddleware.php          # Authentication middleware
│       └── McpCorsMiddleware.php          # CORS middleware
├── Transport/                             # Transport implementations
│   ├── Contracts/
│   │   ├── TransportInterface.php         # Transport contract
│   │   └── MessageHandlerInterface.php    # Message handler contract
│   ├── HttpTransport.php                 # HTTP transport implementation
│   ├── StdioTransport.php                # Stdio transport implementation
│   └── TransportManager.php              # Transport factory/manager
├── Protocol/                              # MCP protocol implementation
│   ├── Contracts/
│   │   ├── JsonRpcHandlerInterface.php    # JSON-RPC handler contract
│   │   └── ProtocolHandlerInterface.php   # Protocol handler contract
│   ├── JsonRpcHandler.php                # JSON-RPC 2.0 implementation
│   ├── MessageProcessor.php              # MCP message processing
│   ├── CapabilityNegotiator.php          # Capability negotiation
│   └── NotificationHandler.php           # MCP notifications
├── Registry/                              # Component registration system
│   ├── Contracts/
│   │   ├── RegistryInterface.php          # Registry contract
│   │   └── DiscoveryInterface.php         # Discovery contract
│   ├── McpRegistry.php                    # Central registry
│   ├── ToolRegistry.php                   # Tool registration
│   ├── ResourceRegistry.php              # Resource registration
│   ├── PromptRegistry.php                # Prompt registration
│   ├── ComponentDiscovery.php            # Auto-discovery service
│   └── RouteRegistrar.php                # Route registration
├── Abstracts/                             # Base abstract classes
│   ├── McpTool.php                        # Base tool class
│   ├── McpResource.php                    # Base resource class
│   └── McpPrompt.php                      # Base prompt class
├── Traits/                                # Reusable traits
│   ├── HandlesMcpRequests.php             # Request handling trait
│   ├── ValidatesParameters.php           # Parameter validation trait
│   └── ManagesCapabilities.php           # Capability management trait
├── Support/                               # Support utilities
│   ├── ConfigGenerator.php               # Client config generation
│   ├── DocumentationGenerator.php        # Auto-documentation
│   ├── SchemaValidator.php               # JSON schema validation
│   └── MessageSerializer.php             # Message serialization
├── Facades/                               # Laravel facades
│   └── Mcp.php                            # Main MCP facade
├── Exceptions/                            # Package exceptions
│   ├── McpException.php                   # Base MCP exception
│   ├── TransportException.php            # Transport-related exceptions
│   ├── ProtocolException.php             # Protocol-related exceptions
│   └── RegistrationException.php         # Registration exceptions
└── Console/                               # Console utilities
    └── OutputFormatter.php               # Console output formatting
```

### Configuration Structure (`config/`)
```
config/
├── laravel-mcp.php                        # Main package configuration
└── mcp-transports.php                     # Transport-specific configuration
```

### Routes Structure (`routes/`)
```
routes/
├── mcp.php                                # Default MCP routes template
└── web.php                                # Web routes for HTTP transport (optional)
```

### Resources Structure (`resources/`)
```
resources/
├── stubs/                                 # Code generation templates
│   ├── tool.stub                          # Tool class template
│   ├── resource.stub                      # Resource class template
│   ├── prompt.stub                        # Prompt class template
│   └── mcp-routes.stub                    # MCP routes template
└── views/                                 # Blade views (if needed)
    └── debug/                             # Debug views
        └── mcp-info.blade.php             # MCP server info view
```

### Tests Structure (`tests/`)
```
tests/
├── TestCase.php                           # Base test case
├── Unit/                                  # Unit tests
│   ├── Commands/                          # Command tests
│   ├── Transport/                         # Transport tests
│   ├── Protocol/                          # Protocol tests
│   ├── Registry/                          # Registry tests
│   └── Support/                           # Support class tests
├── Feature/                               # Feature tests
│   ├── HttpTransportTest.php              # HTTP transport integration
│   ├── StdioTransportTest.php             # Stdio transport integration
│   ├── ToolRegistrationTest.php          # Tool registration features
│   └── ClientIntegrationTest.php         # Client integration tests
├── Fixtures/                              # Test fixtures
│   ├── Tools/                             # Sample tools
│   ├── Resources/                         # Sample resources
│   └── Prompts/                           # Sample prompts
└── stubs/                                 # Test-specific stubs
    └── laravel-app/                       # Mock Laravel app
```

## Application Integration Structure

### Laravel Application Structure (after package installation)
```
app/
├── Mcp/                                   # MCP components directory
│   ├── Tools/                             # Application MCP tools
│   │   ├── CalculatorTool.php             # Example tool
│   │   └── DatabaseQueryTool.php          # Example database tool
│   ├── Resources/                         # Application MCP resources
│   │   ├── UserResource.php               # Example user resource
│   │   └── PostResource.php               # Example post resource
│   └── Prompts/                           # Application MCP prompts
│       ├── EmailTemplatePrompt.php        # Example email prompt
│       └── ReportPrompt.php               # Example report prompt
├── Http/                                  # Laravel HTTP layer
│   ├── Controllers/                       # Standard Laravel controllers
│   └── Middleware/                        # Standard Laravel middleware
└── ...                                   # Other Laravel app directories
```

### Configuration Files (after publishing)
```
config/
├── laravel-mcp.php                        # Published package config
└── app.php                                # Laravel app config (providers added)
```

### Route Files (after publishing)
```
routes/
├── mcp.php                                # Published MCP routes
├── web.php                                # Standard Laravel web routes
└── api.php                                # Standard Laravel API routes
```

## File Organization Principles

### Namespace Organization
```php
JTD\LaravelMCP\                            # Root namespace
├── Commands\                              # Artisan commands namespace
├── Http\                                  # HTTP layer namespace
│   ├── Controllers\
│   └── Middleware\
├── Transport\                             # Transport layer namespace
├── Protocol\                              # Protocol layer namespace
├── Registry\                              # Registry system namespace
├── Abstracts\                             # Abstract classes namespace
├── Traits\                                # Traits namespace
├── Support\                               # Support utilities namespace
├── Facades\                               # Facades namespace
├── Exceptions\                            # Exceptions namespace
└── Console\                               # Console utilities namespace
```

### Class Naming Conventions
- **Commands**: Suffix with `Command` (e.g., `MakeToolCommand`)
- **Controllers**: Suffix with `Controller` (e.g., `McpController`)
- **Middleware**: Suffix with `Middleware` (e.g., `McpAuthMiddleware`)
- **Transport**: Suffix with `Transport` (e.g., `HttpTransport`)
- **Abstracts**: Prefix with `Mcp` (e.g., `McpTool`)
- **Exceptions**: Suffix with `Exception` (e.g., `McpException`)
- **Interfaces**: Suffix with `Interface` (e.g., `TransportInterface`)

### File Naming Conventions
- **PHP Files**: PascalCase matching class names
- **Configuration**: kebab-case with `.php` extension
- **Routes**: kebab-case with `.php` extension
- **Stubs**: lowercase with `.stub` extension
- **Views**: kebab-case with `.blade.php` extension

## Auto-loading Configuration

### Composer Auto-loading
```json
{
    "autoload": {
        "psr-4": {
            "JTD\\LaravelMCP\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "JTD\\LaravelMCP\\Tests\\": "tests/"
        }
    }
}
```

### Laravel Service Discovery
```json
{
    "extra": {
        "laravel": {
            "providers": [
                "JTD\\LaravelMCP\\LaravelMcpServiceProvider"
            ],
            "aliases": {
                "Mcp": "JTD\\LaravelMCP\\Facades\\Mcp"
            }
        }
    }
}
```

## Security Considerations

### File Permissions
- **Configuration Files**: Read-only in production
- **Route Files**: Secured against unauthorized modification
- **Generated Files**: Appropriate permissions for web server access

### Directory Security
- **Vendor Directory**: Protected from direct web access
- **Config Directory**: Sensitive configuration protection
- **MCP Components**: Proper namespace isolation

### Code Organization Security
- **Input Validation**: Centralized in base classes
- **Authorization**: Middleware-based access control
- **Error Handling**: Secure error message exposure