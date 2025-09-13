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
│   ├── Unit/                      # Unit tests (1,355 tests)
│   ├── Feature/                   # Feature tests
│   └── Compatibility/             # Package compatibility tests
├── docs/                          # Documentation
│   ├── Specs/                     # Technical specifications
│   ├── examples/                  # Usage examples
│   ├── architecture-enhanced.md   # Enhanced system architecture
│   ├── events-system.md           # Event-driven architecture
│   ├── async-processing.md        # Async processing documentation
│   ├── middleware-stack.md        # Production middleware stack
│   └── components/                # Component documentation
│       └── mcp-manager.md         # McpManager API reference
├── .github/                       # GitHub workflows and templates
│   └── workflows/                 # CI/CD pipelines
│       └── tests.yml              # Automated testing (727 fast tests)
├── composer.json                  # Composer configuration
├── phpunit.xml                    # PHPUnit configuration
├── README.md                      # Package overview
├── CHANGELOG.md                   # Version history
└── CLAUDE.md                      # Development instructions
```

### Source Code Structure (`src/`)
```
src/
├── LaravelMcpServiceProvider.php          # Main service provider
├── McpManager.php                         # Central MCP manager
├── Commands/                              # Artisan commands
│   ├── BaseCommand.php                    # Base command class
│   ├── Concerns/                          # Command concerns/traits
│   │   ├── McpMakeCommand.php             # Make command trait
│   │   └── SecuresMakeCommands.php        # Security trait
│   ├── DocumentationCommand.php          # Documentation generation
│   ├── ServeCommand.php                   # mcp:serve command
│   ├── MakeToolCommand.php                # make:mcp-tool command
│   ├── MakeResourceCommand.php            # make:mcp-resource command
│   ├── MakePromptCommand.php              # make:mcp-prompt command
│   ├── ListCommand.php                    # mcp:list command
│   └── RegisterCommand.php               # mcp:register command
├── Http/                                  # HTTP transport components
│   ├── Controllers/
│   │   ├── McpController.php              # Main MCP HTTP controller
│   │   └── NotificationController.php     # Notification controller
│   ├── Exceptions/
│   │   └── McpValidationException.php     # HTTP validation exception
│   └── Middleware/
│       ├── HandleSseRequest.php           # Server-sent events support
│       ├── McpAuthMiddleware.php          # Authentication middleware
│       ├── McpCorsMiddleware.php          # CORS middleware
│       ├── McpErrorHandlingMiddleware.php # Error handling middleware
│       ├── McpLoggingMiddleware.php       # Request/response logging
│       ├── McpRateLimitMiddleware.php     # Rate limiting middleware
│       └── McpValidationMiddleware.php    # Request validation middleware
├── Transport/                             # Transport implementations
│   ├── Contracts/
│   │   ├── TransportInterface.php         # Transport contract
│   │   └── MessageHandlerInterface.php    # Message handler contract
│   ├── BaseTransport.php                 # Base transport class
│   ├── HttpTransport.php                 # HTTP transport implementation
│   ├── MessageFramer.php                 # Message framing utilities
│   ├── StdioTransport.php                # Stdio transport implementation
│   ├── StreamHandler.php                 # Stream handling utilities
│   └── TransportManager.php              # Transport factory/manager
├── Protocol/                              # MCP protocol implementation
│   ├── Contracts/
│   │   ├── JsonRpcHandlerInterface.php    # JSON-RPC handler contract
│   │   ├── NotificationHandlerInterface.php # Notification handler contract
│   │   └── ProtocolHandlerInterface.php   # Protocol handler contract
│   ├── CapabilityNegotiator.php          # Capability negotiation
│   ├── JsonRpcHandler.php                # JSON-RPC 2.0 implementation
│   ├── MessageProcessor.php              # MCP message processing
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
│   ├── BaseComponent.php                  # Common base component
│   ├── McpPrompt.php                      # Base prompt class
│   ├── McpResource.php                    # Base resource class
│   └── McpTool.php                        # Base tool class
├── Traits/                                # Reusable traits
│   ├── FormatsResponses.php               # Response formatting trait
│   ├── HandlesMcpRequests.php             # Request handling trait
│   ├── LogsOperations.php                 # Operation logging trait
│   ├── ManagesCapabilities.php           # Capability management trait
│   └── ValidatesParameters.php           # Parameter validation trait
├── Support/                               # Support utilities
│   ├── Contracts/
│   │   └── ClientGeneratorInterface.php   # Client generator contract
│   ├── Generators/                        # Client configuration generators
│   │   ├── ChatGptGenerator.php           # ChatGPT client generator
│   │   ├── ClaudeCodeGenerator.php        # Claude Code generator
│   │   └── ClaudeDesktopGenerator.php     # Claude Desktop generator
│   ├── AdvancedDocumentationGenerator.php # Advanced documentation
│   ├── ClientDetector.php                 # Client detection utilities
│   ├── ConfigGenerator.php               # Client config generation
│   ├── Debugger.php                       # Debug utilities
│   ├── DocumentationGenerator.php        # Auto-documentation
│   ├── ExampleCompiler.php               # Example compilation
│   ├── ExtensionGuideBuilder.php         # Extension guide builder
│   ├── helpers.php                        # Helper functions
│   ├── MessageSerializer.php             # Message serialization
│   ├── PerformanceMonitor.php            # Performance monitoring
│   ├── SchemaDocumenter.php              # Schema documentation
│   └── SchemaValidator.php               # JSON schema validation
├── Facades/                               # Laravel facades
│   └── Mcp.php                            # Main MCP facade
├── Events/                                # Event system (10+ events)
│   ├── McpComponentRegistered.php         # Component registration event
│   ├── McpPromptGenerated.php            # Prompt generation event
│   ├── McpRequestProcessed.php           # Request processing event
│   ├── McpResourceAccessed.php           # Resource access event
│   ├── McpToolExecuted.php               # Tool execution event
│   ├── NotificationBroadcast.php         # Notification broadcast event
│   ├── NotificationDelivered.php         # Notification delivery event
│   ├── NotificationFailed.php            # Notification failure event
│   ├── NotificationQueued.php            # Notification queued event
│   └── NotificationSent.php              # Notification sent event
├── Listeners/                             # Event listeners
│   ├── LogMcpActivity.php                # Activity logging listener
│   ├── LogMcpComponentRegistration.php   # Component registration logger
│   ├── TrackMcpRequestMetrics.php        # Request metrics tracker
│   └── TrackMcpUsage.php                 # Usage tracking listener
├── Jobs/                                  # Async job processing
│   ├── ProcessMcpRequest.php             # Async MCP request processing
│   └── ProcessNotificationDelivery.php   # Async notification delivery
├── Notifications/                         # Notification system
│   ├── Channels/                          # Custom notification channels
│   │   └── SlackChannel.php              # Slack notification channel
│   ├── McpComponentFailure.php           # Component failure notification
│   ├── McpErrorNotification.php          # Error notifications
│   └── McpStatusUpdate.php               # Status update notifications
├── Server/                                # MCP server implementation
│   ├── Contracts/
│   │   └── ServerInterface.php           # Server contract
│   ├── CapabilityManager.php             # Server capability management
│   ├── McpServer.php                     # Main MCP server
│   └── ServerInfo.php                    # Server information provider
├── Exceptions/                            # Exception handling
│   ├── ConfigurationException.php        # Configuration errors
│   ├── McpException.php                   # Base MCP exception
│   ├── ProtocolException.php             # Protocol errors
│   ├── RegistrationException.php         # Registration errors
│   └── TransportException.php            # Transport errors
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
├── mcp-transports.php                     # Transport configuration
└── app.php                                # Laravel app config (providers added)
```

### Route Files (after publishing)
```
routes/
├── mcp.php                                # Published MCP routes
├── web.php                                # Standard Laravel web routes (with MCP routes)
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
- **Documentation Files**: kebab-case with `.md` extension
- **Event Files**: PascalCase with descriptive names
- **Job Files**: PascalCase with `Process` prefix where applicable
- **Listener Files**: PascalCase with descriptive action names
- **Notification Files**: PascalCase with `Notification` suffix

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
- **Input Validation**: Centralized in base classes and validation middleware
- **Authorization**: Middleware-based access control with rate limiting
- **Error Handling**: Secure error message exposure with centralized error handling
- **Event Security**: Sensitive data filtering in event dispatching
- **Async Processing**: Secure job queuing with parameter sanitization
- **Logging**: Structured logging with security context
- **Notification Security**: Secure notification channels and filtering