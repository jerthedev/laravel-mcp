<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Test Email Prompt
 *
 * An email generation prompt for testing MCP prompt functionality.
 */
class TestEmailPrompt extends McpPrompt
{
    /**
     * Prompt name.
     */
    protected string $name = 'test_email';

    /**
     * Prompt description.
     */
    protected string $description = 'Generates test email templates';

    /**
     * Prompt arguments schema.
     */
    protected array $argumentsSchema = [
        'type' => 'object',
        'properties' => [
            'recipient' => [
                'type' => 'string',
                'description' => 'Email recipient name',
                'required' => true,
            ],
            'subject' => [
                'type' => 'string',
                'description' => 'Email subject',
                'required' => true,
            ],
            'tone' => [
                'type' => 'string',
                'description' => 'Email tone',
                'enum' => ['formal', 'casual', 'friendly', 'professional'],
                'default' => 'professional',
            ],
            'purpose' => [
                'type' => 'string',
                'description' => 'Purpose of the email',
                'required' => true,
            ],
            'include_signature' => [
                'type' => 'boolean',
                'description' => 'Include email signature',
                'default' => true,
            ],
        ],
        'required' => ['recipient', 'subject', 'purpose'],
    ];

    /**
     * Get the messages for the prompt.
     */
    public function getMessages(array $arguments): array
    {
        $recipient = $arguments['recipient'];
        $subject = $arguments['subject'];
        $tone = $arguments['tone'] ?? 'professional';
        $purpose = $arguments['purpose'];
        $includeSignature = $arguments['include_signature'] ?? true;

        $systemPrompt = $this->buildSystemPrompt($tone);
        $userPrompt = $this->buildUserPrompt($recipient, $subject, $purpose, $includeSignature);

        return [
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        'type' => 'text',
                        'text' => $systemPrompt,
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $userPrompt,
                    ],
                ],
            ],
            'metadata' => [
                'template' => 'email',
                'tone' => $tone,
                'recipient' => $recipient,
            ],
        ];
    }

    /**
     * Build the system prompt based on tone.
     */
    private function buildSystemPrompt(string $tone): string
    {
        $toneDescriptions = [
            'formal' => 'You are a professional email writer who creates formal, business-appropriate emails.',
            'casual' => 'You are a friendly email writer who creates relaxed, conversational emails.',
            'friendly' => 'You are a warm and approachable email writer who creates personable, friendly emails.',
            'professional' => 'You are a skilled email writer who creates clear, professional emails.',
        ];

        $description = $toneDescriptions[$tone] ?? $toneDescriptions['professional'];

        return $description.' Your emails are well-structured, clear, and appropriate for the context. '.
               'You always maintain the requested tone while ensuring the message is effective.';
    }

    /**
     * Build the user prompt with email details.
     */
    private function buildUserPrompt(string $recipient, string $subject, string $purpose, bool $includeSignature): string
    {
        $prompt = "Please write an email with the following details:\n\n";
        $prompt .= "Recipient: {$recipient}\n";
        $prompt .= "Subject: {$subject}\n";
        $prompt .= "Purpose: {$purpose}\n\n";
        $prompt .= "Please structure the email properly with:\n";
        $prompt .= "- An appropriate greeting\n";
        $prompt .= "- Clear and concise body paragraphs\n";
        $prompt .= "- A professional closing\n";

        if ($includeSignature) {
            $prompt .= "- Include a professional signature\n";
        }

        return $prompt;
    }

    /**
     * Get example arguments for testing.
     */
    public function getExampleArguments(): array
    {
        return [
            'recipient' => 'John Smith',
            'subject' => 'Project Update',
            'tone' => 'professional',
            'purpose' => 'Provide a status update on the current project and request feedback',
            'include_signature' => true,
        ];
    }

    /**
     * Validate arguments before processing.
     */
    public function validateArguments(array $arguments): bool
    {
        // Check required fields
        if (! isset($arguments['recipient']) || empty($arguments['recipient'])) {
            return false;
        }

        if (! isset($arguments['subject']) || empty($arguments['subject'])) {
            return false;
        }

        if (! isset($arguments['purpose']) || empty($arguments['purpose'])) {
            return false;
        }

        // Validate tone if provided
        if (isset($arguments['tone'])) {
            $validTones = ['formal', 'casual', 'friendly', 'professional'];
            if (! in_array($arguments['tone'], $validTones)) {
                return false;
            }
        }

        return true;
    }
}
