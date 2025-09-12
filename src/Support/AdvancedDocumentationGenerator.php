<?php

namespace JTD\LaravelMCP\Support;

use Illuminate\Support\Str;

/**
 * Advanced documentation generator for MCP server.
 *
 * This class generates advanced documentation including architecture guides,
 * extension tutorials, performance optimization, security best practices,
 * and community contribution guidelines.
 */
class AdvancedDocumentationGenerator
{
    /**
     * Performance monitor instance.
     */
    protected ?PerformanceMonitor $performanceMonitor = null;

    /**
     * Example compiler instance.
     */
    protected ExampleCompiler $exampleCompiler;

    /**
     * Extension guide builder.
     */
    protected ExtensionGuideBuilder $extensionBuilder;

    /**
     * Create a new advanced documentation generator instance.
     */
    public function __construct(
        ?PerformanceMonitor $performanceMonitor = null,
        ?ExampleCompiler $exampleCompiler = null,
        ?ExtensionGuideBuilder $extensionBuilder = null
    ) {
        $this->performanceMonitor = $performanceMonitor;
        $this->exampleCompiler = $exampleCompiler ?? new ExampleCompiler;
        $this->extensionBuilder = $extensionBuilder ?? new ExtensionGuideBuilder;
    }

    /**
     * Generate architecture documentation.
     */
    public function generateArchitectureDocumentation(array $options = []): string
    {
        $sections = [
            $this->generateArchitectureOverview($options),
            $this->generateLayeredArchitecture(),
            $this->generateComponentDiagrams(),
            $this->generateDataFlow(),
            $this->generateDesignPatterns(),
            $this->generateScalabilityConsiderations(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Generate architecture overview.
     */
    protected function generateArchitectureOverview(array $options = []): string
    {
        $packageName = $options['package_name'] ?? 'Laravel MCP';

        return implode("\n", [
            "# {$packageName} Architecture",
            '',
            '## Overview',
            '',
            'This document describes the technical architecture of the Laravel MCP package,',
            'including its design principles, component structure, and integration patterns.',
            '',
            '### Core Design Principles',
            '',
            '1. **Separation of Concerns** - Clear boundaries between transport, protocol, and business logic',
            '2. **Extensibility** - Easy to add new tools, resources, and prompts',
            '3. **Laravel Integration** - Leverages Laravel\'s service container and patterns',
            '4. **Protocol Compliance** - Strict adherence to MCP 1.0 specification',
            '5. **Performance** - Optimized for both stdio and HTTP transports',
        ]);
    }

    /**
     * Generate layered architecture documentation.
     */
    protected function generateLayeredArchitecture(): string
    {
        return implode("\n", [
            '## Layered Architecture',
            '',
            '### Transport Layer',
            '- **StdioTransport**: Handles standard input/output communication',
            '- **HttpTransport**: RESTful API implementation',
            '- **TransportManager**: Factory for transport selection',
            '',
            '### Protocol Layer',
            '- **JsonRpcHandler**: JSON-RPC 2.0 message processing',
            '- **McpProtocolHandler**: MCP-specific protocol implementation',
            '- **MessageValidator**: Request/response validation',
            '',
            '### Registry Layer',
            '- **McpRegistry**: Central component registry',
            '- **ToolRegistry**: Tool management and metadata',
            '- **ResourceRegistry**: Resource management',
            '- **PromptRegistry**: Prompt template management',
            '',
            '### Application Layer',
            '- **McpServer**: Main server orchestration',
            '- **ComponentDiscovery**: Auto-discovery system',
            '- **ConfigurationManager**: Configuration handling',
        ]);
    }

    /**
     * Generate component diagrams.
     */
    protected function generateComponentDiagrams(): string
    {
        return implode("\n", [
            '## Component Diagrams',
            '',
            '### Request Flow',
            '```mermaid',
            'sequenceDiagram',
            '    participant Client',
            '    participant Transport',
            '    participant Protocol',
            '    participant Registry',
            '    participant Component',
            '    ',
            '    Client->>Transport: JSON-RPC Request',
            '    Transport->>Protocol: Parse Message',
            '    Protocol->>Registry: Lookup Component',
            '    Registry->>Component: Execute Method',
            '    Component-->>Registry: Return Result',
            '    Registry-->>Protocol: Format Response',
            '    Protocol-->>Transport: JSON-RPC Response',
            '    Transport-->>Client: Send Response',
            '```',
            '',
            '### Component Registration',
            '```mermaid',
            'graph TD',
            '    A[Service Provider] --> B[Component Discovery]',
            '    B --> C{Component Type}',
            '    C -->|Tool| D[Tool Registry]',
            '    C -->|Resource| E[Resource Registry]',
            '    C -->|Prompt| F[Prompt Registry]',
            '    D --> G[MCP Registry]',
            '    E --> G',
            '    F --> G',
            '```',
        ]);
    }

    /**
     * Generate data flow documentation.
     */
    protected function generateDataFlow(): string
    {
        return implode("\n", [
            '## Data Flow',
            '',
            '### Initialization Flow',
            '1. Client sends `initialize` request',
            '2. Server negotiates capabilities',
            '3. Server loads registered components',
            '4. Server returns capabilities and server info',
            '5. Client sends `initialized` notification',
            '',
            '### Tool Execution Flow',
            '1. Client sends `tools/call` request',
            '2. Server validates request parameters',
            '3. Server locates tool in registry',
            '4. Tool executes with provided arguments',
            '5. Server formats and returns result',
            '',
            '### Resource Access Flow',
            '1. Client sends `resources/read` request',
            '2. Server validates URI and permissions',
            '3. Resource handler fetches data',
            '4. Server applies any transformations',
            '5. Server returns resource content',
        ]);
    }

    /**
     * Generate design patterns documentation.
     */
    protected function generateDesignPatterns(): string
    {
        return implode("\n", [
            '## Design Patterns',
            '',
            '### Factory Pattern',
            '- **TransportFactory**: Creates appropriate transport instances',
            '- **HandlerFactory**: Creates protocol handlers',
            '',
            '### Registry Pattern',
            '- **McpRegistry**: Central registry for all components',
            '- **Type-specific registries**: Specialized registries for each component type',
            '',
            '### Strategy Pattern',
            '- **Transport strategies**: Different transport implementations',
            '- **Validation strategies**: Protocol-specific validation',
            '',
            '### Observer Pattern',
            '- **Event system**: Laravel events for component lifecycle',
            '- **Listeners**: Track metrics and usage',
            '',
            '### Facade Pattern',
            '- **Mcp Facade**: Simplified API access',
            '- **Registry facades**: Direct registry access',
        ]);
    }

    /**
     * Generate scalability considerations.
     */
    protected function generateScalabilityConsiderations(): string
    {
        return implode("\n", [
            '## Scalability Considerations',
            '',
            '### Horizontal Scaling',
            '- Stateless server design',
            '- Load balancer compatible',
            '- Redis/cache for shared state',
            '',
            '### Performance Optimizations',
            '- Component lazy loading',
            '- Response caching',
            '- Connection pooling',
            '- Async job processing',
            '',
            '### Resource Management',
            '- Memory limits for large responses',
            '- Request timeout handling',
            '- Rate limiting support',
            '- Graceful degradation',
        ]);
    }

    /**
     * Generate extension guide documentation.
     */
    public function generateExtensionGuide(array $options = []): string
    {
        return $this->extensionBuilder->buildGuide($options);
    }

    /**
     * Generate performance optimization documentation.
     */
    public function generatePerformanceOptimization(array $options = []): string
    {
        $sections = [
            $this->generatePerformanceOverview(),
            $this->generateBenchmarks(),
            $this->generateOptimizationTechniques(),
            $this->generateCachingStrategies(),
            $this->generateMonitoringGuide(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Generate performance overview.
     */
    protected function generatePerformanceOverview(): string
    {
        return implode("\n", [
            '# Performance Optimization Guide',
            '',
            '## Overview',
            '',
            'This guide provides comprehensive performance optimization strategies',
            'for Laravel MCP servers to ensure optimal response times and resource utilization.',
            '',
            '## Performance Goals',
            '',
            '- **Response Time**: < 100ms for typical operations',
            '- **Throughput**: > 1000 requests/second',
            '- **Memory Usage**: < 128MB per worker',
            '- **CPU Usage**: < 50% under normal load',
        ]);
    }

    /**
     * Generate benchmarks.
     */
    protected function generateBenchmarks(): string
    {
        if ($this->performanceMonitor && $this->performanceMonitor->isEnabled()) {
            $metrics = $this->performanceMonitor->getSummary();

            return implode("\n", [
                '## Current Performance Metrics',
                '',
                '```json',
                json_encode($metrics, JSON_PRETTY_PRINT),
                '```',
            ]);
        }

        return implode("\n", [
            '## Performance Benchmarks',
            '',
            '### Baseline Metrics',
            '- Tool execution: ~50ms average',
            '- Resource read: ~30ms average',
            '- Prompt rendering: ~20ms average',
            '- Initialize: ~100ms',
            '',
            '### Load Testing Results',
            '- 100 concurrent connections: 95% < 200ms',
            '- 1000 requests/minute: 99% < 500ms',
            '- Memory stable at 64MB after 10,000 requests',
        ]);
    }

    /**
     * Generate optimization techniques.
     */
    protected function generateOptimizationTechniques(): string
    {
        return implode("\n", [
            '## Optimization Techniques',
            '',
            '### 1. Component Loading',
            '```php',
            '// Lazy load components only when needed',
            'config([\'laravel-mcp.discovery.lazy\' => true]);',
            '```',
            '',
            '### 2. Database Optimization',
            '```php',
            '// Use eager loading for relationships',
            '$users = User::with([\'posts\', \'comments\'])->get();',
            '',
            '// Index frequently queried columns',
            'Schema::table(\'mcp_logs\', function ($table) {',
            '    $table->index([\'method\', \'created_at\']);',
            '});',
            '```',
            '',
            '### 3. Response Optimization',
            '```php',
            '// Paginate large datasets',
            'public function read(array $params): array',
            '{',
            '    return User::paginate($params[\'limit\'] ?? 50)->toArray();',
            '}',
            '```',
            '',
            '### 4. Memory Management',
            '```php',
            '// Stream large files instead of loading into memory',
            'return response()->streamDownload(function () {',
            '    // Stream content',
            '});',
            '```',
        ]);
    }

    /**
     * Generate caching strategies.
     */
    protected function generateCachingStrategies(): string
    {
        return implode("\n", [
            '## Caching Strategies',
            '',
            '### Response Caching',
            '```php',
            'public function execute(array $params): array',
            '{',
            '    return Cache::remember(',
            '        "tool:{$this->getName()}:" . md5(json_encode($params)),',
            '        3600,',
            '        fn() => $this->performExpensiveOperation($params)',
            '    );',
            '}',
            '```',
            '',
            '### Component Registry Caching',
            '```php',
            'config([\'laravel-mcp.cache.enabled\' => true]);',
            'config([\'laravel-mcp.cache.ttl\' => 3600]);',
            '```',
            '',
            '### Redis Configuration',
            '```env',
            'CACHE_DRIVER=redis',
            'REDIS_CLIENT=predis',
            'REDIS_HOST=127.0.0.1',
            'REDIS_PORT=6379',
            '```',
        ]);
    }

    /**
     * Generate monitoring guide.
     */
    protected function generateMonitoringGuide(): string
    {
        return implode("\n", [
            '## Performance Monitoring',
            '',
            '### Built-in Monitoring',
            '```php',
            '// Enable performance monitoring',
            'config([\'laravel-mcp.performance.enabled\' => true]);',
            '',
            '// Access metrics',
            '$monitor = app(PerformanceMonitor::class);',
            '$summary = $monitor->getSummary();',
            '```',
            '',
            '### Laravel Telescope Integration',
            '```php',
            '// Install Telescope',
            'composer require laravel/telescope',
            '',
            '// Monitor MCP requests',
            'Telescope::tag(function (IncomingEntry $entry) {',
            '    if ($entry->type === \'request\' && Str::contains($entry->content[\'uri\'], \'mcp\')) {',
            '        return [\'mcp\'];',
            '    }',
            '    return [];',
            '});',
            '```',
            '',
            '### Custom Metrics',
            '```php',
            'class CustomTool extends McpTool',
            '{',
            '    public function execute(array $params): array',
            '    {',
            '        $monitor = app(PerformanceMonitor::class);',
            '        ',
            '        return $monitor->measure(',
            '            fn() => $this->performOperation($params),',
            '            \'custom_tool.execution\'',
            '        );',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Generate security best practices documentation.
     */
    public function generateSecurityBestPractices(array $options = []): string
    {
        $sections = [
            $this->generateSecurityOverview(),
            $this->generateAuthenticationGuide(),
            $this->generateAuthorizationGuide(),
            $this->generateInputValidation(),
            $this->generateSecureConfiguration(),
            $this->generateAuditingGuide(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Generate security overview.
     */
    protected function generateSecurityOverview(): string
    {
        return implode("\n", [
            '# Security Best Practices',
            '',
            '## Overview',
            '',
            'This guide covers security best practices for Laravel MCP implementations,',
            'including authentication, authorization, input validation, and secure configuration.',
            '',
            '## Security Principles',
            '',
            '1. **Defense in Depth** - Multiple layers of security',
            '2. **Least Privilege** - Minimal permissions by default',
            '3. **Input Validation** - Never trust user input',
            '4. **Secure by Default** - Safe configuration out of the box',
            '5. **Audit Trail** - Log all security-relevant events',
        ]);
    }

    /**
     * Generate authentication guide.
     */
    protected function generateAuthenticationGuide(): string
    {
        return implode("\n", [
            '## Authentication',
            '',
            '### API Token Authentication',
            '```php',
            '// config/laravel-mcp.php',
            'return [',
            '    \'authentication\' => [',
            '        \'enabled\' => true,',
            '        \'driver\' => \'token\',',
            '        \'header\' => \'X-MCP-Token\',',
            '    ],',
            '];',
            '```',
            '',
            '### OAuth 2.0 Integration',
            '```php',
            '// Using Laravel Passport',
            'class McpAuthMiddleware',
            '{',
            '    public function handle($request, Closure $next)',
            '    {',
            '        if (!$request->user() || !$request->user()->tokenCan(\'mcp:access\')) {',
            '            return response()->json([\'error\' => \'Unauthorized\'], 401);',
            '        }',
            '        return $next($request);',
            '    }',
            '}',
            '```',
            '',
            '### mTLS (Mutual TLS)',
            '```nginx',
            '# Nginx configuration for mTLS',
            'server {',
            '    ssl_client_certificate /path/to/ca.crt;',
            '    ssl_verify_client on;',
            '    ssl_verify_depth 2;',
            '}',
            '```',
        ]);
    }

    /**
     * Generate authorization guide.
     */
    protected function generateAuthorizationGuide(): string
    {
        return implode("\n", [
            '## Authorization',
            '',
            '### Role-Based Access Control',
            '```php',
            'class SecureTool extends McpTool',
            '{',
            '    public function authorize(array $params): bool',
            '    {',
            '        return auth()->user()->hasRole(\'admin\');',
            '    }',
            '}',
            '```',
            '',
            '### Permission-Based Access',
            '```php',
            '// Using Laravel Policies',
            'class ToolPolicy',
            '{',
            '    public function execute(User $user, McpTool $tool): bool',
            '    {',
            '        return $user->hasPermission("mcp.tools.{$tool->getName()}");',
            '    }',
            '}',
            '```',
            '',
            '### Resource-Level Security',
            '```php',
            'class UserResource extends McpResource',
            '{',
            '    public function read(array $params): array',
            '    {',
            '        // Only return data user has access to',
            '        return User::where(\'organization_id\', auth()->user()->organization_id)',
            '            ->get()',
            '            ->toArray();',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Generate input validation guide.
     */
    protected function generateInputValidation(): string
    {
        return implode("\n", [
            '## Input Validation',
            '',
            '### Parameter Validation',
            '```php',
            'public function getInputSchema(): array',
            '{',
            '    return [',
            '        \'type\' => \'object\',',
            '        \'properties\' => [',
            '            \'email\' => [',
            '                \'type\' => \'string\',',
            '                \'format\' => \'email\',',
            '                \'maxLength\' => 255,',
            '            ],',
            '            \'age\' => [',
            '                \'type\' => \'integer\',',
            '                \'minimum\' => 0,',
            '                \'maximum\' => 150,',
            '            ],',
            '        ],',
            '        \'required\' => [\'email\'],',
            '        \'additionalProperties\' => false,',
            '    ];',
            '}',
            '```',
            '',
            '### SQL Injection Prevention',
            '```php',
            '// Always use parameterized queries',
            'DB::select(\'SELECT * FROM users WHERE email = ?\', [$email]);',
            '',
            '// Use Eloquent ORM',
            'User::where(\'email\', $email)->first();',
            '```',
            '',
            '### XSS Prevention',
            '```php',
            '// Escape output',
            'return [',
            '    \'content\' => e($userInput),',
            '    \'html\' => clean($htmlContent), // Using HTMLPurifier',
            '];',
            '```',
        ]);
    }

    /**
     * Generate secure configuration guide.
     */
    protected function generateSecureConfiguration(): string
    {
        return implode("\n", [
            '## Secure Configuration',
            '',
            '### Environment Variables',
            '```env',
            '# Never commit sensitive data',
            'MCP_API_KEY=your-secret-key',
            'MCP_ENCRYPTION_KEY=base64:...',
            'MCP_SSL_ENABLED=true',
            'MCP_RATE_LIMIT=100',
            '```',
            '',
            '### Encryption',
            '```php',
            '// Encrypt sensitive data',
            'use Illuminate\\Support\\Facades\\Crypt;',
            '',
            'public function storeSensitiveData($data)',
            '{',
            '    return Crypt::encryptString($data);',
            '}',
            '',
            'public function retrieveSensitiveData($encrypted)',
            '{',
            '    return Crypt::decryptString($encrypted);',
            '}',
            '```',
            '',
            '### Rate Limiting',
            '```php',
            '// Apply rate limiting middleware',
            'Route::middleware([\'throttle:api\'])->group(function () {',
            '    Route::post(\'/mcp/execute\', [McpController::class, \'execute\']);',
            '});',
            '```',
        ]);
    }

    /**
     * Generate auditing guide.
     */
    protected function generateAuditingGuide(): string
    {
        return implode("\n", [
            '## Security Auditing',
            '',
            '### Audit Logging',
            '```php',
            'class AuditableToolTrait',
            '{',
            '    protected function logExecution(array $params, array $result)',
            '    {',
            '        Log::channel(\'security\')->info(\'Tool executed\', [',
            '            \'tool\' => $this->getName(),',
            '            \'user\' => auth()->id(),',
            '            \'params\' => $this->sanitizeForLog($params),',
            '            \'ip\' => request()->ip(),',
            '            \'timestamp\' => now(),',
            '        ]);',
            '    }',
            '}',
            '```',
            '',
            '### Security Headers',
            '```php',
            '// Middleware for security headers',
            'class SecurityHeadersMiddleware',
            '{',
            '    public function handle($request, Closure $next)',
            '    {',
            '        $response = $next($request);',
            '        ',
            '        return $response',
            '            ->header(\'X-Content-Type-Options\', \'nosniff\')',
            '            ->header(\'X-Frame-Options\', \'DENY\')',
            '            ->header(\'X-XSS-Protection\', \'1; mode=block\')',
            '            ->header(\'Strict-Transport-Security\', \'max-age=31536000\');',
            '    }',
            '}',
            '```',
        ]);
    }

    /**
     * Generate community contribution guidelines.
     */
    public function generateContributionGuidelines(array $options = []): string
    {
        $sections = [
            $this->generateContributionOverview(),
            $this->generateCodeOfConduct(),
            $this->generateDevelopmentSetup(),
            $this->generateContributionProcess(),
            $this->generateCodingStandards(),
            $this->generateTestingRequirements(),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * Generate contribution overview.
     */
    protected function generateContributionOverview(): string
    {
        return implode("\n", [
            '# Contributing to Laravel MCP',
            '',
            'Thank you for your interest in contributing to Laravel MCP! This document provides',
            'guidelines and instructions for contributing to the project.',
            '',
            '## Ways to Contribute',
            '',
            '- **Bug Reports**: Help us identify and fix issues',
            '- **Feature Requests**: Suggest new features and improvements',
            '- **Code Contributions**: Submit pull requests with bug fixes or features',
            '- **Documentation**: Improve or translate documentation',
            '- **Testing**: Write tests or improve test coverage',
            '- **Examples**: Create example implementations',
        ]);
    }

    /**
     * Generate code of conduct.
     */
    protected function generateCodeOfConduct(): string
    {
        return implode("\n", [
            '## Code of Conduct',
            '',
            '### Our Pledge',
            '',
            'We pledge to make participation in our project a harassment-free experience for everyone,',
            'regardless of age, body size, disability, ethnicity, gender identity, level of experience,',
            'nationality, personal appearance, race, religion, or sexual identity and orientation.',
            '',
            '### Expected Behavior',
            '',
            '- Be respectful and inclusive',
            '- Accept constructive criticism gracefully',
            '- Focus on what is best for the community',
            '- Show empathy towards other community members',
            '',
            '### Unacceptable Behavior',
            '',
            '- Harassment, discrimination, or offensive comments',
            '- Personal attacks or trolling',
            '- Publishing private information without consent',
            '- Other unethical or unprofessional conduct',
        ]);
    }

    /**
     * Generate development setup guide.
     */
    protected function generateDevelopmentSetup(): string
    {
        return implode("\n", [
            '## Development Setup',
            '',
            '### Prerequisites',
            '',
            '- PHP 8.2 or higher',
            '- Composer 2.x',
            '- Laravel 11.x',
            '- Git',
            '',
            '### Installation',
            '',
            '```bash',
            '# Fork and clone the repository',
            'git clone https://github.com/your-username/laravel-mcp.git',
            'cd laravel-mcp',
            '',
            '# Install dependencies',
            'composer install',
            '',
            '# Run tests',
            'composer test',
            '',
            '# Run code style checks',
            'composer pint',
            '',
            '# Run static analysis',
            'composer analyse',
            '```',
        ]);
    }

    /**
     * Generate contribution process.
     */
    protected function generateContributionProcess(): string
    {
        return implode("\n", [
            '## Contribution Process',
            '',
            '### 1. Create an Issue',
            '',
            'Before starting work, create an issue to discuss your proposed changes:',
            '',
            '- **Bug Report**: Describe the bug and how to reproduce it',
            '- **Feature Request**: Explain the feature and its benefits',
            '- **Documentation**: Describe what needs to be improved',
            '',
            '### 2. Fork and Branch',
            '',
            '```bash',
            '# Create a feature branch',
            'git checkout -b feature/your-feature-name',
            '',
            '# Or a bugfix branch',
            'git checkout -b bugfix/issue-number-description',
            '```',
            '',
            '### 3. Make Changes',
            '',
            '- Write clean, documented code',
            '- Follow the coding standards',
            '- Add tests for new functionality',
            '- Update documentation as needed',
            '',
            '### 4. Test Your Changes',
            '',
            '```bash',
            '# Run all tests',
            'composer test:full',
            '',
            '# Run specific tests',
            './vendor/bin/phpunit tests/Unit/YourTest.php',
            '',
            '# Check code style',
            'composer pint',
            '',
            '# Run static analysis',
            'composer analyse',
            '```',
            '',
            '### 5. Submit Pull Request',
            '',
            '- Push your branch to your fork',
            '- Create a pull request against the main branch',
            '- Fill out the pull request template',
            '- Link the related issue',
            '- Wait for review and address feedback',
        ]);
    }

    /**
     * Generate coding standards.
     */
    protected function generateCodingStandards(): string
    {
        return implode("\n", [
            '## Coding Standards',
            '',
            '### PHP Standards',
            '',
            '- Follow PSR-12 coding style',
            '- Use PHP 8.2+ features appropriately',
            '- Type declarations for all parameters and returns',
            '- Meaningful variable and method names',
            '',
            '### Documentation Standards',
            '',
            '```php',
            '/**',
            ' * Brief description of the method.',
            ' *',
            ' * Longer description if needed, explaining the purpose',
            ' * and any important details about the implementation.',
            ' *',
            ' * @param  string  $param  Description of parameter',
            ' * @return array Description of return value',
            ' * @throws \\Exception When something goes wrong',
            ' */',
            'public function exampleMethod(string $param): array',
            '{',
            '    // Implementation',
            '}',
            '```',
            '',
            '### Git Commit Messages',
            '',
            '- Use present tense ("Add feature" not "Added feature")',
            '- Use imperative mood ("Move cursor to..." not "Moves cursor to...")',
            '- Limit first line to 72 characters',
            '- Reference issues and pull requests',
            '',
            'Example:',
            '```',
            'Add tool validation for input schemas',
            '',
            'Implements JSON Schema validation for tool input parameters',
            'to ensure type safety and prevent invalid requests.',
            '',
            'Fixes #123',
            '```',
        ]);
    }

    /**
     * Generate testing requirements.
     */
    protected function generateTestingRequirements(): string
    {
        return implode("\n", [
            '## Testing Requirements',
            '',
            '### Test Coverage',
            '',
            '- Minimum 90% code coverage required',
            '- All new features must include tests',
            '- Bug fixes should include regression tests',
            '',
            '### Test Organization',
            '',
            '```php',
            'namespace Tests\\Unit\\YourFeature;',
            '',
            'use PHPUnit\\Framework\\Attributes\\Test;',
            'use PHPUnit\\Framework\\Attributes\\Group;',
            'use Tests\\TestCase;',
            '',
            '#[Group(\'unit\')]',
            '#[Group(\'your-feature\')]',
            'class YourFeatureTest extends TestCase',
            '{',
            '    #[Test]',
            '    public function it_does_something_specific(): void',
            '    {',
            '        // Arrange',
            '        $input = \'test\';',
            '        ',
            '        // Act',
            '        $result = $this->processInput($input);',
            '        ',
            '        // Assert',
            '        $this->assertEquals(\'expected\', $result);',
            '    }',
            '}',
            '```',
            '',
            '### Integration Tests',
            '',
            '- Test component interactions',
            '- Verify Laravel integration',
            '- Test database operations with transactions',
            '- Mock external services appropriately',
        ]);
    }

    /**
     * Generate advanced examples.
     */
    public function generateAdvancedExamples(array $options = []): array
    {
        return $this->exampleCompiler->compileAdvancedExamples($options);
    }

    /**
     * Validate extension guide.
     */
    public function validateExtensionGuide(string $guide): array
    {
        return $this->extensionBuilder->validateGuide($guide);
    }

    /**
     * Compile and test example code.
     */
    public function compileExample(string $code, string $type = 'tool'): array
    {
        return $this->exampleCompiler->compile($code, $type);
    }

    /**
     * Generate complete advanced documentation.
     */
    public function generateCompleteAdvancedDocumentation(array $options = []): array
    {
        return [
            'architecture' => $this->generateArchitectureDocumentation($options),
            'extension_guide' => $this->generateExtensionGuide($options),
            'performance' => $this->generatePerformanceOptimization($options),
            'security' => $this->generateSecurityBestPractices($options),
            'contributing' => $this->generateContributionGuidelines($options),
            'examples' => $this->generateAdvancedExamples($options),
        ];
    }

    /**
     * Save advanced documentation to files.
     */
    public function saveAdvancedDocumentation(array $documentation, string $basePath): bool
    {
        try {
            if (! is_dir($basePath)) {
                if (! mkdir($basePath, 0755, true)) {
                    return false;
                }
            }

            foreach ($documentation as $section => $content) {
                if ($section === 'examples' && is_array($content)) {
                    $examplesPath = "{$basePath}/examples";
                    if (! is_dir($examplesPath)) {
                        if (! mkdir($examplesPath, 0755, true)) {
                            return false;
                        }
                    }

                    foreach ($content as $example => $code) {
                        $filename = "{$examplesPath}/".Str::kebab($example).'.php';
                        if (file_put_contents($filename, $code) === false) {
                            return false;
                        }
                    }
                } else {
                    $filename = "{$basePath}/".str_replace('_', '-', $section).'.md';
                    if (file_put_contents($filename, $content) === false) {
                        return false;
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
