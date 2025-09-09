<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Email Template Prompt for testing email generation.
 *
 * This prompt generates email templates for various purposes,
 * testing complex parameter validation and message composition.
 */
class EmailTemplatePrompt extends McpPrompt
{
    protected string $name = 'email_template';

    protected string $description = 'Generates professional email templates for various purposes';

    protected array $argumentsSchema = [
        'type' => 'object',
        'properties' => [
            'purpose' => [
                'type' => 'string',
                'description' => 'Purpose of the email',
                'enum' => ['welcome', 'follow_up', 'reminder', 'thank_you', 'invitation', 'announcement'],
            ],
            'recipient_name' => [
                'type' => 'string',
                'description' => 'Name of the email recipient',
            ],
            'sender_name' => [
                'type' => 'string',
                'description' => 'Name of the email sender',
            ],
            'company' => [
                'type' => 'string',
                'description' => 'Company or organization name',
            ],
            'tone' => [
                'type' => 'string',
                'description' => 'Tone of the email',
                'enum' => ['professional', 'friendly', 'formal', 'casual'],
                'default' => 'professional',
            ],
            'additional_context' => [
                'type' => 'string',
                'description' => 'Additional context or specific details to include',
            ],
        ],
        'required' => ['purpose', 'recipient_name', 'sender_name'],
    ];

    /**
     * Generate email template messages.
     *
     * @param  array  $arguments  Prompt arguments
     * @return array Prompt messages response
     */
    public function getMessages(array $arguments): array
    {
        $purpose = $arguments['purpose'];
        $recipientName = $arguments['recipient_name'];
        $senderName = $arguments['sender_name'];
        $company = $arguments['company'] ?? '';
        $tone = $arguments['tone'] ?? 'professional';
        $additionalContext = $arguments['additional_context'] ?? '';

        $prompt = $this->generateEmailPrompt($purpose, $recipientName, $senderName, $company, $tone, $additionalContext);

        return [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        'type' => 'text',
                        'text' => $prompt,
                    ],
                ],
            ],
            'description' => "Email template for {$purpose} purpose",
        ];
    }

    /**
     * Generate the email template prompt.
     *
     * @param  string  $purpose  Email purpose
     * @param  string  $recipientName  Recipient name
     * @param  string  $senderName  Sender name
     * @param  string  $company  Company name
     * @param  string  $tone  Email tone
     * @param  string  $additionalContext  Additional context
     * @return string Generated prompt
     */
    protected function generateEmailPrompt(
        string $purpose,
        string $recipientName,
        string $senderName,
        string $company,
        string $tone,
        string $additionalContext
    ): string {
        $basePrompt = "Please create a {$tone} email template for the following scenario:\n\n";
        $basePrompt .= "Purpose: {$purpose}\n";
        $basePrompt .= "From: {$senderName}";

        if ($company) {
            $basePrompt .= " ({$company})";
        }

        $basePrompt .= "\nTo: {$recipientName}\n";
        $basePrompt .= "Tone: {$tone}\n\n";

        // Add purpose-specific instructions
        $basePrompt .= $this->getPurposeInstructions($purpose);

        if ($additionalContext) {
            $basePrompt .= "\n\nAdditional context: {$additionalContext}";
        }

        $basePrompt .= "\n\nPlease include:\n";
        $basePrompt .= "- Appropriate subject line\n";
        $basePrompt .= "- Professional greeting\n";
        $basePrompt .= "- Clear and concise body\n";
        $basePrompt .= "- Appropriate closing\n";
        $basePrompt .= "- Any relevant call-to-action if applicable\n";

        return $basePrompt;
    }

    /**
     * Get purpose-specific instructions.
     *
     * @param  string  $purpose  Email purpose
     * @return string Purpose instructions
     */
    protected function getPurposeInstructions(string $purpose): string
    {
        switch ($purpose) {
            case 'welcome':
                return 'This is a welcome email for a new customer, user, or team member. '.
                       'Make it warm and informative, explaining what they can expect next.';

            case 'follow_up':
                return 'This is a follow-up email after a meeting, call, or previous interaction. '.
                       'Reference the previous conversation and outline next steps.';

            case 'reminder':
                return 'This is a reminder email about an upcoming event, deadline, or action item. '.
                       'Be polite but clear about the importance of the reminder.';

            case 'thank_you':
                return 'This is a thank you email expressing gratitude for something specific. '.
                       "Be genuine and specific about what you're thanking them for.";

            case 'invitation':
                return 'This is an invitation email for an event, meeting, or opportunity. '.
                       'Include all relevant details (date, time, location, purpose) and clear RSVP instructions.';

            case 'announcement':
                return 'This is an announcement email sharing important news or updates. '.
                       'Be clear and informative, highlighting the key points and any required actions.';

            default:
                return 'Create an appropriate email for the specified purpose.';
        }
    }
}
