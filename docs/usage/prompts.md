# Prompts Documentation

MCP Prompts generate structured messages for AI interactions within your Laravel application. This guide covers everything you need to know about creating and managing Prompts.

## What are MCP Prompts?

MCP Prompts are template systems that generate structured messages for AI clients. They help standardize AI interactions and can:

- Generate consistent prompt templates
- Accept dynamic arguments
- Use Blade templates for complex content
- Provide structured message formats
- Support multiple message types
- Include context and instructions

## Basic Prompt Structure

All MCP Prompts extend the `McpPrompt` abstract class:

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class MyPrompt extends McpPrompt
{
    protected string $name = 'my_prompt';
    protected string $description = 'What this prompt generates';
    
    protected array $arguments = [
        // Argument definitions
    ];
    
    protected function customContent(array $arguments): string
    {
        // Prompt generation logic
        return 'Generated prompt content';
    }
}
```

## Creating Your First Prompt

### Step 1: Generate the Prompt

```bash
php artisan make:mcp-prompt EmailTemplatePrompt
```

### Step 2: Define the Prompt

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class EmailTemplatePrompt extends McpPrompt
{
    protected string $name = 'email_template';
    protected string $description = 'Generate professional email templates';
    
    protected array $arguments = [
        'type' => [
            'description' => 'Type of email template',
            'type' => 'string',
            'enum' => ['welcome', 'follow_up', 'thank_you', 'announcement'],
            'required' => true,
        ],
        'recipient_name' => [
            'description' => 'Name of the email recipient',
            'type' => 'string',
            'required' => true,
        ],
        'sender_name' => [
            'description' => 'Name of the email sender',
            'type' => 'string',
            'required' => true,
        ],
        'company' => [
            'description' => 'Company name',
            'type' => 'string',
            'required' => false,
        ],
        'context' => [
            'description' => 'Additional context for the email',
            'type' => 'string',
            'required' => false,
        ],
    ];

    protected function customContent(array $arguments): string
    {
        $type = $arguments['type'];
        $recipientName = $arguments['recipient_name'];
        $senderName = $arguments['sender_name'];
        $company = $arguments['company'] ?? '';
        $context = $arguments['context'] ?? '';
        
        return match ($type) {
            'welcome' => $this->generateWelcomeEmail($recipientName, $senderName, $company, $context),
            'follow_up' => $this->generateFollowUpEmail($recipientName, $senderName, $company, $context),
            'thank_you' => $this->generateThankYouEmail($recipientName, $senderName, $company, $context),
            'announcement' => $this->generateAnnouncementEmail($recipientName, $senderName, $company, $context),
        };
    }
    
    private function generateWelcomeEmail(string $recipient, string $sender, string $company, string $context): string
    {
        $companyText = $company ? " at {$company}" : "";
        $contextText = $context ? "\n\n{$context}" : "";
        
        return "Please write a professional welcome email with the following specifications:

**Email Type**: Welcome Email
**Recipient**: {$recipient}
**Sender**: {$sender}{$companyText}
**Context**: {$contextText}

The email should:
1. Warmly welcome the recipient
2. Set clear expectations
3. Provide helpful next steps
4. Maintain a professional yet friendly tone
5. Include relevant contact information

Please structure the email with:
- Subject line
- Greeting
- Welcome message
- Key information
- Next steps
- Professional closing";
    }
    
    private function generateFollowUpEmail(string $recipient, string $sender, string $company, string $context): string
    {
        return "Please write a professional follow-up email with these details:

**Email Type**: Follow-up Email
**Recipient**: {$recipient}
**Sender**: {$sender}" . ($company ? " from {$company}" : "") . "
**Context**: {$context}

The email should:
1. Reference the previous interaction
2. Provide value or new information
3. Include a clear call-to-action
4. Maintain professional tone
5. Show genuine interest in continuing the relationship

Structure the email with appropriate subject line, greeting, body, and closing.";
    }
    
    private function generateThankYouEmail(string $recipient, string $sender, string $company, string $context): string
    {
        return "Please write a sincere thank you email:

**Email Type**: Thank You Email
**Recipient**: {$recipient}
**Sender**: {$sender}" . ($company ? " from {$company}" : "") . "
**Context**: {$context}

The email should:
1. Express genuine gratitude
2. Be specific about what you're thanking them for
3. Mention the positive impact
4. Offer future assistance if appropriate
5. End on a warm, professional note

Include subject line and full email content.";
    }
    
    private function generateAnnouncementEmail(string $recipient, string $sender, string $company, string $context): string
    {
        return "Please write a professional announcement email:

**Email Type**: Announcement Email
**Recipient**: {$recipient}
**Sender**: {$sender}" . ($company ? " from {$company}" : "") . "
**Announcement Details**: {$context}

The email should:
1. Clearly communicate the announcement
2. Explain the importance or benefits
3. Provide relevant details
4. Include any required actions
5. Maintain excitement while being informative

Structure with compelling subject line, clear announcement, details, and appropriate closing.";
    }
}
```

## Using Templates

### Simple String Templates

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class SimpleTemplatePrompt extends McpPrompt
{
    protected string $name = 'simple_template';
    protected string $description = 'Simple template with placeholders';
    
    protected ?string $template = 'Hello {{name}}, welcome to {{company}}! Your role is {{role}}.';
    
    protected array $arguments = [
        'name' => [
            'description' => 'Person name',
            'type' => 'string',
            'required' => true,
        ],
        'company' => [
            'description' => 'Company name',
            'type' => 'string',
            'required' => true,
        ],
        'role' => [
            'description' => 'Person role',
            'type' => 'string',
            'required' => true,
        ],
    ];
}
```

### Blade Templates

Create a Blade template at `resources/views/mcp/prompts/code-review.blade.php`:

```blade
Please review the following {{ $language }} code:

```{{ $language }}
{{ $code }}
```

**Review Focus**: {{ $focus ?? 'General code quality and best practices' }}

Please provide:
@if(isset($include_performance) && $include_performance)
1. **Performance Analysis**
   - Identify potential bottlenecks
   - Suggest optimization opportunities
@endif

@if(isset($include_security) && $include_security)  
2. **Security Review**
   - Check for security vulnerabilities
   - Recommend security improvements
@endif

3. **Code Quality**
   - Assess code readability and maintainability
   - Check adherence to coding standards
   
4. **Best Practices**
   - Highlight any deviations from best practices
   - Suggest improvements

5. **Overall Assessment**
   - Provide an overall quality score (1-10)
   - Summarize main findings

@if(isset($suggestions) && $suggestions)
Please also provide specific code suggestions with improved versions where applicable.
@endif
```

Then use it in your prompt:

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class CodeReviewPrompt extends McpPrompt
{
    protected string $name = 'code_review';
    protected string $description = 'Generate comprehensive code review prompts';
    protected ?string $template = 'mcp.prompts.code-review';
    
    protected array $arguments = [
        'language' => [
            'description' => 'Programming language',
            'type' => 'string',
            'required' => true,
        ],
        'code' => [
            'description' => 'Code to review',
            'type' => 'string',
            'required' => true,
        ],
        'focus' => [
            'description' => 'Specific focus areas',
            'type' => 'string',
            'required' => false,
        ],
        'include_performance' => [
            'description' => 'Include performance analysis',
            'type' => 'boolean',
            'required' => false,
        ],
        'include_security' => [
            'description' => 'Include security review',
            'type' => 'boolean',
            'required' => false,
        ],
        'suggestions' => [
            'description' => 'Provide code suggestions',
            'type' => 'boolean',
            'required' => false,
        ],
    ];
}
```

## Advanced Prompt Examples

### Multi-Message Prompt

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

class ConversationPrompt extends McpPrompt
{
    protected string $name = 'conversation';
    protected string $description = 'Generate multi-message conversation prompts';
    
    protected array $arguments = [
        'scenario' => [
            'description' => 'Conversation scenario',
            'type' => 'string',
            'required' => true,
        ],
        'participants' => [
            'description' => 'List of participants',
            'type' => 'array',
            'items' => ['type' => 'string'],
            'required' => true,
        ],
        'context' => [
            'description' => 'Conversation context',
            'type' => 'string',
            'required' => false,
        ],
    ];

    protected function handleGet(array $arguments): array
    {
        $scenario = $arguments['scenario'];
        $participants = $arguments['participants'];
        $context = $arguments['context'] ?? '';
        
        $messages = [];
        
        // System message
        $messages[] = $this->createMessage('system', 
            "You are facilitating a conversation about: {$scenario}. " .
            "Participants: " . implode(', ', $participants) . ". " .
            ($context ? "Context: {$context}" : "")
        );
        
        // User message
        $messages[] = $this->createMessage('user',
            "Please simulate a realistic conversation between " . implode(' and ', $participants) . 
            " about {$scenario}. Make it natural and engaging."
        );
        
        return [
            'description' => $this->getDescription(),
            'messages' => $messages,
        ];
    }
}
```

### Dynamic Content Prompt

```php
<?php

namespace App\Mcp\Prompts;

use App\Models\User;
use App\Models\Product;
use JTD\LaravelMCP\Abstracts\McpPrompt;

class PersonalizedMarketingPrompt extends McpPrompt
{
    protected string $name = 'personalized_marketing';
    protected string $description = 'Generate personalized marketing content';
    
    protected array $arguments = [
        'user_id' => [
            'description' => 'Target user ID',
            'type' => 'integer',
            'required' => true,
        ],
        'product_id' => [
            'description' => 'Product to promote',
            'type' => 'integer',
            'required' => true,
        ],
        'campaign_type' => [
            'description' => 'Type of marketing campaign',
            'type' => 'string',
            'enum' => ['email', 'social', 'ad_copy', 'newsletter'],
            'required' => true,
        ],
        'tone' => [
            'description' => 'Communication tone',
            'type' => 'string',
            'enum' => ['professional', 'casual', 'friendly', 'urgent'],
            'required' => false,
        ],
    ];

    protected function customContent(array $arguments): string
    {
        $user = User::findOrFail($arguments['user_id']);
        $product = Product::with(['category', 'reviews'])->findOrFail($arguments['product_id']);
        $campaignType = $arguments['campaign_type'];
        $tone = $arguments['tone'] ?? 'friendly';
        
        $userProfile = $this->analyzeUserProfile($user);
        $productInfo = $this->analyzeProduct($product);
        
        return "Please create personalized {$campaignType} marketing content with these specifications:

**Target User Profile:**
- Name: {$user->name}
- Age Group: {$userProfile['age_group']}
- Purchase History: {$userProfile['purchase_summary']}
- Preferences: {$userProfile['preferences']}
- Communication Style: {$tone}

**Product Information:**
- Name: {$product->name}
- Category: {$product->category->name}
- Price: \${$product->price}
- Key Features: {$productInfo['features']}
- Customer Rating: {$productInfo['rating']}/5
- Best Selling Points: {$productInfo['selling_points']}

**Campaign Requirements:**
1. Personalize the message for this specific user
2. Highlight product benefits that align with user preferences
3. Use {$tone} tone throughout
4. Include a compelling call-to-action
5. Mention any relevant user benefits or discounts

**Content Type**: {$campaignType}
{$this->getCampaignSpecificInstructions($campaignType)}

Please generate the marketing content that will resonate with this user's profile and effectively promote the product.";
    }
    
    private function analyzeUserProfile(User $user): array
    {
        // In a real implementation, this would analyze user data
        return [
            'age_group' => $this->determineAgeGroup($user->date_of_birth ?? null),
            'purchase_summary' => $this->summarizePurchaseHistory($user),
            'preferences' => $this->getUserPreferences($user),
        ];
    }
    
    private function analyzeProduct(Product $product): array
    {
        return [
            'features' => $product->features ?? 'High-quality product features',
            'rating' => $product->reviews->avg('rating') ?? 4.0,
            'selling_points' => $this->getSellingPoints($product),
        ];
    }
    
    private function getCampaignSpecificInstructions(string $type): string
    {
        return match ($type) {
            'email' => 'Format as a complete email with subject line, greeting, body, and signature.',
            'social' => 'Keep it concise for social media (under 280 characters), include relevant hashtags.',
            'ad_copy' => 'Create attention-grabbing headlines and persuasive body copy for advertisements.',
            'newsletter' => 'Format as a newsletter section with engaging headline and informative content.',
            default => 'Create engaging marketing content appropriate for the specified medium.',
        };
    }
    
    private function determineAgeGroup($birthDate): string
    {
        if (!$birthDate) {
            return 'Unknown';
        }
        
        $age = now()->diffInYears($birthDate);
        
        return match (true) {
            $age < 25 => 'Gen Z (18-24)',
            $age < 40 => 'Millennial (25-39)',
            $age < 55 => 'Gen X (40-54)',
            default => 'Baby Boomer (55+)',
        };
    }
    
    private function summarizePurchaseHistory(User $user): string
    {
        $orderCount = $user->orders()->count();
        $totalSpent = $user->orders()->sum('total');
        
        return "Purchased {$orderCount} orders totaling \${$totalSpent}";
    }
    
    private function getUserPreferences(User $user): string
    {
        // This would analyze user preferences from various data sources
        return $user->preferences ?? 'Quality products, good value';
    }
    
    private function getSellingPoints(Product $product): string
    {
        // Extract selling points from product data
        return $product->selling_points ?? 'High quality, great value, customer favorite';
    }
}
```

### Documentation Generation Prompt

```php
<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;
use Illuminate\Support\Facades\File;

class DocumentationPrompt extends McpPrompt
{
    protected string $name = 'documentation';
    protected string $description = 'Generate code documentation';
    
    protected array $arguments = [
        'file_path' => [
            'description' => 'Path to the code file',
            'type' => 'string',
            'required' => true,
        ],
        'doc_type' => [
            'description' => 'Type of documentation',
            'type' => 'string',
            'enum' => ['api', 'readme', 'inline', 'changelog'],
            'required' => true,
        ],
        'include_examples' => [
            'description' => 'Include code examples',
            'type' => 'boolean',
            'required' => false,
        ],
    ];

    protected function customContent(array $arguments): string
    {
        $filePath = $arguments['file_path'];
        $docType = $arguments['doc_type'];
        $includeExamples = $arguments['include_examples'] ?? true;
        
        if (!File::exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }
        
        $code = File::get($filePath);
        $fileName = basename($filePath);
        $analysis = $this->analyzeCode($code, $filePath);
        
        return match ($docType) {
            'api' => $this->generateApiDocs($analysis, $includeExamples),
            'readme' => $this->generateReadmeDocs($analysis, $fileName),
            'inline' => $this->generateInlineDocs($analysis),
            'changelog' => $this->generateChangelogDocs($analysis),
        };
    }
    
    private function analyzeCode(string $code, string $filePath): array
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        
        return [
            'language' => $this->detectLanguage($extension),
            'line_count' => substr_count($code, "\n") + 1,
            'has_classes' => str_contains($code, 'class '),
            'has_functions' => str_contains($code, 'function ') || str_contains($code, 'def '),
            'has_comments' => str_contains($code, '//') || str_contains($code, '#') || str_contains($code, '/*'),
            'code_preview' => substr($code, 0, 500),
        ];
    }
    
    private function generateApiDocs(array $analysis, bool $includeExamples): string
    {
        $exampleText = $includeExamples ? "\n- Include practical usage examples" : "";
        
        return "Please generate comprehensive API documentation for this {$analysis['language']} code:

**Code Analysis:**
- Language: {$analysis['language']}
- Lines of code: {$analysis['line_count']}
- Contains classes: " . ($analysis['has_classes'] ? 'Yes' : 'No') . "
- Contains functions: " . ($analysis['has_functions'] ? 'Yes' : 'No') . "

**Code Preview:**
```{$analysis['language']}
{$analysis['code_preview']}
```

**Documentation Requirements:**
- Document all public classes and methods
- Include parameter descriptions and types
- Specify return values and types
- Note any exceptions that may be thrown
- Describe the purpose and functionality{$exampleText}
- Use proper markdown formatting
- Include version information if applicable

Please generate clear, professional API documentation that would help developers understand and use this code.";
    }
    
    private function generateReadmeDocs(array $analysis, string $fileName): string
    {
        return "Please generate a comprehensive README.md file for this project:

**File**: {$fileName}
**Language**: {$analysis['language']}
**Code Lines**: {$analysis['line_count']}

**Code Preview:**
```{$analysis['language']}
{$analysis['code_preview']}
```

**README Should Include:**
1. **Project Title and Description**
   - Clear, concise project description
   - Main purpose and functionality

2. **Installation Instructions**
   - Prerequisites and dependencies
   - Step-by-step installation guide

3. **Usage Examples**
   - Basic usage examples
   - Common use cases

4. **Configuration**
   - Configuration options
   - Environment setup

5. **API Documentation**
   - Key functions and classes
   - Parameter descriptions

6. **Contributing Guidelines**
   - How to contribute
   - Development setup

7. **License Information**
   - License type
   - Copyright information

Please generate a professional, well-structured README.md that would help users understand and use this project.";
    }
    
    private function generateInlineDocs(array $analysis): string
    {
        return "Please generate inline code documentation for this {$analysis['language']} code:

**Current Documentation Status:** " . ($analysis['has_comments'] ? 'Some comments exist' : 'No comments found') . "

**Code Preview:**
```{$analysis['language']}
{$analysis['code_preview']}
```

**Inline Documentation Requirements:**
- Add clear, concise comments for all public methods
- Include docblock comments with parameter and return type information
- Comment complex logic and algorithms
- Add class-level documentation describing purpose
- Include @param and @return annotations where applicable
- Use consistent commenting style
- Avoid obvious comments, focus on 'why' not 'what'

Please provide the improved version of the code with comprehensive inline documentation that follows best practices for {$analysis['language']}.";
    }
    
    private function generateChangelogDocs(array $analysis): string
    {
        return "Please analyze this code and suggest CHANGELOG entries:

**Code Details:**
- Language: {$analysis['language']}
- File lines: {$analysis['line_count']}

**Code Preview:**
```{$analysis['language']}
{$analysis['code_preview']}
```

**Changelog Requirements:**
Please suggest appropriate CHANGELOG entries in the standard format:

## [Version] - Date

### Added
- New features and functionality

### Changed  
- Changes to existing functionality

### Deprecated
- Features that will be removed

### Removed
- Features that have been removed

### Fixed
- Bug fixes

### Security
- Security improvements

Based on the code analysis, please suggest what types of changes this might represent and provide template CHANGELOG entries that a developer could use when releasing this code.";
    }
    
    private function detectLanguage(string $extension): string
    {
        return match ($extension) {
            'php' => 'PHP',
            'js' => 'JavaScript',
            'ts' => 'TypeScript',
            'py' => 'Python',
            'rb' => 'Ruby',
            'java' => 'Java',
            'cs' => 'C#',
            'go' => 'Go',
            'rs' => 'Rust',
            'cpp', 'cc', 'cxx' => 'C++',
            'c' => 'C',
            default => 'Unknown',
        };
    }
}
```

## Argument Validation

### Complex Argument Types

```php
protected array $arguments = [
    'user_data' => [
        'description' => 'User information object',
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'required' => true,
                'max_length' => 100,
            ],
            'email' => [
                'type' => 'string',
                'required' => true,
                'format' => 'email',
            ],
            'age' => [
                'type' => 'integer',
                'required' => false,
                'minimum' => 18,
                'maximum' => 100,
            ],
            'preferences' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'required' => false,
            ],
        ],
        'required' => true,
    ],
    'options' => [
        'description' => 'Processing options',
        'type' => 'object',
        'properties' => [
            'format' => [
                'type' => 'string',
                'enum' => ['json', 'xml', 'csv'],
            ],
            'include_meta' => [
                'type' => 'boolean',
            ],
        ],
        'required' => false,
    ],
];
```

### Custom Validation

```php
protected function validateArguments(array $arguments): array
{
    $validated = parent::validateArguments($arguments);
    
    // Custom validation logic
    if (isset($validated['user_id']) && isset($validated['admin_action'])) {
        // Don't allow admin actions on self
        if ($validated['user_id'] === auth()->id() && $validated['admin_action']) {
            throw new \InvalidArgumentException('Cannot perform admin actions on yourself');
        }
    }
    
    // Validate file paths
    if (isset($validated['file_path'])) {
        $path = $validated['file_path'];
        if (!str_starts_with($path, 'public/') && !str_starts_with($path, 'storage/')) {
            throw new \InvalidArgumentException('File path must be within allowed directories');
        }
    }
    
    return $validated;
}
```

## Authorization and Security

### Basic Authorization

```php
class SecurePrompt extends McpPrompt
{
    protected bool $requiresAuth = true;
    
    protected function authorize(array $arguments): bool
    {
        if (!auth()->check()) {
            return false;
        }
        
        // Role-based authorization
        if (isset($arguments['admin_feature'])) {
            return auth()->user()->hasRole('admin');
        }
        
        return true;
    }
}
```

### Content Filtering

```php
protected function customContent(array $arguments): string
{
    $content = $this->generateBaseContent($arguments);
    
    // Filter sensitive content
    $content = $this->filterSensitiveData($content);
    
    // Apply content policies
    $content = $this->applyContentPolicies($content, auth()->user());
    
    return $content;
}

private function filterSensitiveData(string $content): string
{
    // Remove or mask sensitive information
    $content = preg_replace('/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', '****-****-****-****', $content);
    $content = preg_replace('/\b\d{3}-\d{2}-\d{4}\b/', '***-**-****', $content);
    
    return $content;
}

private function applyContentPolicies(string $content, $user): string
{
    // Apply user-specific content policies
    if (!$user->hasPermission('view-sensitive-data')) {
        $content = str_replace('[SENSITIVE]', '[REDACTED]', $content);
    }
    
    return $content;
}
```

## Testing Prompts

### Unit Testing

```php
<?php

namespace Tests\Feature\Mcp\Prompts;

use App\Mcp\Prompts\EmailTemplatePrompt;
use Tests\TestCase;

class EmailTemplatePromptTest extends TestCase
{
    public function test_generates_welcome_email_prompt()
    {
        $prompt = new EmailTemplatePrompt();
        
        $result = $prompt->get([
            'type' => 'welcome',
            'recipient_name' => 'John Doe',
            'sender_name' => 'Jane Smith',
            'company' => 'Acme Corp',
        ]);
        
        $this->assertArrayHasKey('messages', $result);
        $this->assertStringContainsString('John Doe', $result['messages'][0]['content']['text']);
        $this->assertStringContainsString('Jane Smith', $result['messages'][0]['content']['text']);
        $this->assertStringContainsString('Acme Corp', $result['messages'][0]['content']['text']);
    }
    
    public function test_validates_required_arguments()
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        
        $prompt = new EmailTemplatePrompt();
        $prompt->get([
            'type' => 'welcome',
            // Missing required arguments
        ]);
    }
    
    public function test_authorization_prevents_unauthorized_access()
    {
        $prompt = new EmailTemplatePrompt();
        $prompt->requiresAuth = true;
        
        $this->expectException(\Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException::class);
        
        // No authenticated user
        $prompt->get([
            'type' => 'welcome',
            'recipient_name' => 'John Doe',
            'sender_name' => 'Jane Smith',
        ]);
    }
}
```

### Integration Testing

```php
public function test_blade_template_rendering()
{
    // Create a test Blade template
    $templateContent = 'Hello {{ $name }}, welcome to {{ $company }}!';
    $templatePath = resource_path('views/test-prompt.blade.php');
    
    File::put($templatePath, $templateContent);
    
    $prompt = new class extends McpPrompt {
        protected string $name = 'test';
        protected string $description = 'Test prompt';
        protected ?string $template = 'test-prompt';
        
        protected array $arguments = [
            'name' => ['type' => 'string', 'required' => true],
            'company' => ['type' => 'string', 'required' => true],
        ];
    };
    
    $result = $prompt->get([
        'name' => 'John',
        'company' => 'Acme',
    ]);
    
    $this->assertStringContainsString('Hello John, welcome to Acme!', $result['messages'][0]['content']['text']);
    
    File::delete($templatePath);
}
```

## Performance Optimization

### Caching Template Compilation

```php
protected function renderTemplate(array $arguments): string
{
    $cacheKey = 'prompt.template.' . $this->getName() . '.' . md5($this->template);
    
    if (view()->exists($this->template)) {
        // Blade templates are automatically cached by Laravel
        return view($this->template, $arguments)->render();
    }
    
    // Cache compiled string templates
    $compiledTemplate = Cache::remember($cacheKey, 3600, function () {
        return $this->compileStringTemplate($this->template);
    });
    
    return $this->renderCompiledTemplate($compiledTemplate, $arguments);
}
```

### Async Content Generation

```php
protected function customContent(array $arguments): string
{
    // For expensive operations, consider caching
    $cacheKey = 'prompt.content.' . $this->getName() . '.' . md5(serialize($arguments));
    
    return Cache::remember($cacheKey, 600, function () use ($arguments) {
        return $this->generateExpensiveContent($arguments);
    });
}
```

## Best Practices

### 1. Clear and Specific Prompts

```php
protected function customContent(array $arguments): string
{
    return "Please write a {$arguments['type']} with these specific requirements:

**Objective**: {$arguments['objective']}
**Target Audience**: {$arguments['audience']}
**Tone**: {$arguments['tone']}
**Length**: {$arguments['word_count']} words

**Structure Requirements:**
1. Clear introduction
2. Main content with examples
3. Strong conclusion

**Content Guidelines:**
- Use active voice
- Include specific examples
- Avoid jargon unless necessary
- Ensure proper formatting

Please provide content that meets all these specifications.";
}
```

### 2. Consistent Message Structure

```php
protected function handleGet(array $arguments): array
{
    return [
        'description' => $this->getDescription(),
        'messages' => [
            [
                'role' => 'system',
                'content' => [
                    'type' => 'text',
                    'text' => $this->getSystemContext($arguments),
                ],
            ],
            [
                'role' => 'user',
                'content' => [
                    'type' => 'text',
                    'text' => $this->generateContent($arguments),
                ],
            ],
        ],
    ];
}
```

### 3. Error Handling

```php
protected function customContent(array $arguments): string
{
    try {
        return $this->generateComplexContent($arguments);
    } catch (\Exception $e) {
        logger()->error('Prompt generation error', [
            'prompt' => $this->getName(),
            'arguments' => $arguments,
            'error' => $e->getMessage(),
        ]);
        
        return $this->getFallbackContent($arguments);
    }
}

private function getFallbackContent(array $arguments): string
{
    return "I apologize, but I encountered an error generating the specific prompt you requested. " .
           "Please try again with different parameters or contact support if the issue persists.";
}
```

## Common Patterns

### Template Factory Pattern

```php
class TemplateFactory
{
    public static function create(string $type, array $config): string
    {
        return match ($type) {
            'email' => self::createEmailTemplate($config),
            'article' => self::createArticleTemplate($config),
            'review' => self::createReviewTemplate($config),
            default => throw new \InvalidArgumentException("Unknown template type: {$type}"),
        };
    }
    
    private static function createEmailTemplate(array $config): string
    {
        return "Generate a {$config['style']} email about {$config['subject']}...";
    }
}
```

### Conditional Content Pattern

```php
protected function customContent(array $arguments): string
{
    $basePrompt = $this->getBasePrompt($arguments);
    
    if (isset($arguments['expert_mode']) && $arguments['expert_mode']) {
        $basePrompt .= $this->getExpertInstructions();
    }
    
    if (isset($arguments['include_examples']) && $arguments['include_examples']) {
        $basePrompt .= $this->getExampleInstructions();
    }
    
    return $basePrompt;
}
```

## Troubleshooting

### Common Issues

1. **Template not found**: Check file paths and Blade template locations
2. **Argument validation fails**: Verify argument schema matches input
3. **Authorization errors**: Check auth logic and user permissions
4. **Performance issues**: Implement caching for expensive operations

### Debugging

```php
protected function customContent(array $arguments): string
{
    if (config('app.debug')) {
        logger()->debug('Prompt generation', [
            'prompt' => $this->getName(),
            'arguments' => $arguments,
            'user_id' => auth()->id(),
        ]);
    }
    
    // Content generation logic...
}
```

---

**Next Steps:**
- Learn about [Tools](tools.md) for executable functions  
- Explore [Resources](resources.md) for data access
- Check the [API Reference](../api-reference.md) for detailed method documentation