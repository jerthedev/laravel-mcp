<?php

namespace App\Mcp\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Email template generation prompt
 *
 * This prompt generates email templates with dynamic content
 * and proper Laravel integration including localization.
 */
class EmailTemplatePrompt extends McpPrompt
{
    /**
     * Get the prompt name
     */
    public function getName(): string
    {
        return 'email_template';
    }

    /**
     * Get the prompt description
     */
    public function getDescription(): string
    {
        return 'Generates email templates with dynamic content, supporting multiple types and tones.';
    }

    /**
     * Get the prompt arguments schema
     */
    public function getArguments(): array
    {
        return [
            'type' => [
                'description' => 'Type of email template to generate',
                'type' => 'string',
                'enum' => ['welcome', 'newsletter', 'notification', 'custom'],
                'required' => true,
            ],
            'recipient_name' => [
                'description' => 'Name of the email recipient',
                'type' => 'string',
                'required' => false,
            ],
            'company_name' => [
                'description' => 'Company or organization name',
                'type' => 'string',
                'required' => false,
            ],
            'subject' => [
                'description' => 'Email subject line',
                'type' => 'string',
                'required' => false,
            ],
            'content' => [
                'description' => 'Main email content',
                'type' => 'string',
                'required' => false,
            ],
            'tone' => [
                'description' => 'Tone of the email',
                'type' => 'string',
                'enum' => ['friendly', 'professional', 'casual', 'formal'],
                'required' => false,
                'default' => 'professional',
            ],
            'language' => [
                'description' => 'Language for localized templates',
                'type' => 'string',
                'required' => false,
                'default' => 'en',
            ],
        ];
    }

    /**
     * Generate the email template prompt
     */
    public function generate(array $arguments): array
    {
        $type = $arguments['type'];
        $tone = $arguments['tone'] ?? 'professional';
        $language = $arguments['language'] ?? 'en';

        // Set locale if different from default
        if ($language !== app()->getLocale()) {
            app()->setLocale($language);
        }

        $template = $this->getTemplate($type, $tone);
        $variables = $this->extractVariables($arguments);

        // Process template with variables
        $processedTemplate = $this->processTemplate($template, $variables);

        return [
            'description' => $this->getTemplateDescription($type, $tone),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => $this->getSystemPrompt($type, $tone),
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $processedTemplate,
                    ],
                ],
            ],
        ];
    }

    /**
     * Get email template based on type and tone
     */
    private function getTemplate(string $type, string $tone): string
    {
        $templates = [
            'welcome' => [
                'friendly' => "Hi {{recipient_name}}!\n\nWelcome to {{company_name}}! We're thrilled to have you join our community. You're about to embark on an amazing journey with us.\n\n{{content}}\n\nIf you have any questions, don't hesitate to reach out. We're here to help!\n\nWarm regards,\nThe {{company_name}} Team",
                'professional' => "Dear {{recipient_name}},\n\nWelcome to {{company_name}}. We appreciate your interest in our services and look forward to providing you with exceptional value.\n\n{{content}}\n\nShould you require assistance, please contact our support team.\n\nBest regards,\n{{company_name}}",
                'casual' => "Hey {{recipient_name}}!\n\nAwesome - you're now part of the {{company_name}} family! 🎉\n\n{{content}}\n\nCatch you later!\nThe {{company_name}} crew",
                'formal' => "Dear Mr./Ms. {{recipient_name}},\n\nWe extend our formal welcome to {{company_name}}. Your registration has been processed successfully.\n\n{{content}}\n\nWe remain at your disposal for any inquiries.\n\nRespectfully yours,\n{{company_name}} Administration",
            ],
            'newsletter' => [
                'friendly' => "Hi there!\n\nIt's that time again - your {{company_name}} newsletter is here! We've got some exciting updates to share with you.\n\n{{content}}\n\nThanks for being part of our community!\n\nCheers,\nThe {{company_name}} Team",
                'professional' => "Dear Subscriber,\n\nWe are pleased to present the latest {{company_name}} newsletter featuring important updates and announcements.\n\n{{content}}\n\nThank you for your continued interest in our services.\n\nBest regards,\n{{company_name}}",
                'casual' => "What's up!\n\nYour {{company_name}} update is here! Check out what we've been up to:\n\n{{content}}\n\nStay awesome!\nThe {{company_name}} team",
                'formal' => "Dear Valued Subscriber,\n\nWe hereby present the {{company_name}} periodic newsletter containing pertinent information and updates.\n\n{{content}}\n\nWe appreciate your attention to this communication.\n\nSincerely,\n{{company_name}} Editorial Board",
            ],
            'notification' => [
                'friendly' => "Hi {{recipient_name}}!\n\nWe wanted to let you know about something important:\n\n{{content}}\n\nThanks for staying connected with {{company_name}}!\n\nBest,\nThe {{company_name}} Team",
                'professional' => "Dear {{recipient_name}},\n\nThis message is to inform you of the following:\n\n{{content}}\n\nIf you have any concerns, please contact us immediately.\n\nRegards,\n{{company_name}}",
                'casual' => "Hey {{recipient_name}}!\n\nJust a heads up:\n\n{{content}}\n\nThanks!\n{{company_name}}",
                'formal' => "Dear {{recipient_name}},\n\nWe hereby notify you of the following matter:\n\n{{content}}\n\nPlease acknowledge receipt of this notification.\n\nFormal regards,\n{{company_name}} Administration",
            ],
            'custom' => [
                'friendly' => "Hi {{recipient_name}}!\n\n{{content}}\n\nBest wishes,\n{{company_name}}",
                'professional' => "Dear {{recipient_name}},\n\n{{content}}\n\nBest regards,\n{{company_name}}",
                'casual' => "Hey {{recipient_name}}!\n\n{{content}}\n\nCheers!\n{{company_name}}",
                'formal' => "Dear {{recipient_name}},\n\n{{content}}\n\nRespectfully,\n{{company_name}}",
            ],
        ];

        return $templates[$type][$tone] ?? $templates['custom']['professional'];
    }

    /**
     * Extract and prepare variables for template processing
     */
    private function extractVariables(array $arguments): array
    {
        return [
            'recipient_name' => $arguments['recipient_name'] ?? 'Valued Customer',
            'company_name' => $arguments['company_name'] ?? config('app.name', 'Your Company'),
            'subject' => $arguments['subject'] ?? 'Important Message',
            'content' => $arguments['content'] ?? 'Thank you for your interest in our services.',
            'date' => now()->format('F j, Y'),
            'time' => now()->format('g:i A'),
            'year' => now()->year,
        ];
    }

    /**
     * Process template by replacing variables
     */
    private function processTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }

    /**
     * Get template description
     */
    private function getTemplateDescription(string $type, string $tone): string
    {
        return "Email template generator for {$type} emails with a {$tone} tone";
    }

    /**
     * Get system prompt for the AI
     */
    private function getSystemPrompt(string $type, string $tone): string
    {
        return "You are an expert email copywriter. Generate a {$tone} {$type} email that is engaging, clear, and appropriate for the target audience. Ensure the email follows best practices for deliverability and user engagement.";
    }
}
