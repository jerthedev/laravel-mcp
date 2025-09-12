# Email Prompt Example

This example demonstrates how to create an MCP Prompt that generates email templates with dynamic content and proper Laravel integration.

## Features

- Multiple email template types (welcome, newsletter, notification)
- Dynamic variable substitution
- Laravel localization support
- Proper input validation
- Customizable tone and style

## Files

- `EmailTemplatePrompt.php` - The main prompt implementation
- `EmailTemplatePromptTest.php` - Unit tests for the prompt
- `templates/` - Email template files
- `README.md` - This documentation

## Usage

### Generate Welcome Email

```json
{
  "jsonrpc": "2.0",
  "id": 1,
  "method": "prompts/get",
  "params": {
    "name": "email_template",
    "arguments": {
      "type": "welcome",
      "recipient_name": "John Doe",
      "company_name": "Acme Corp",
      "tone": "friendly"
    }
  }
}
```

### Generate Newsletter

```json
{
  "jsonrpc": "2.0",
  "id": 2,
  "method": "prompts/get",
  "params": {
    "name": "email_template",
    "arguments": {
      "type": "newsletter",
      "subject": "Monthly Updates",
      "content_sections": ["news", "features", "events"],
      "tone": "professional"
    }
  }
}
```

## Installation

1. Copy `EmailTemplatePrompt.php` to your `app/Mcp/Prompts/` directory
2. Copy the `templates/` directory to your resources folder
3. The prompt will be auto-discovered by the Laravel MCP package

## Template Types

- `welcome` - User welcome emails
- `newsletter` - Marketing newsletters  
- `notification` - System notifications
- `custom` - Custom email templates

## Customization

Templates support variables like:
- `{{recipient_name}}`
- `{{company_name}}`
- `{{subject}}`
- `{{content}}`
- `{{date}}`

## Testing

Run the tests with:

```bash
./vendor/bin/phpunit tests/Unit/Prompts/EmailTemplatePromptTest.php
```