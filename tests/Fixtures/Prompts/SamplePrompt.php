<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Prompts;

use JTD\LaravelMCP\Abstracts\McpPrompt;

/**
 * Sample MCP Prompt for testing purposes.
 *
 * This prompt provides basic functionality for testing MCP prompt
 * registration, message generation, and argument validation.
 */
class SamplePrompt extends McpPrompt
{
    protected string $name = 'sample_prompt';

    protected string $description = 'A sample prompt for testing MCP functionality';

    protected array $argumentsSchema = [
        'type' => 'object',
        'properties' => [
            'topic' => [
                'type' => 'string',
                'description' => 'Topic for the prompt',
            ],
            'style' => [
                'type' => 'string',
                'description' => 'Writing style for the prompt',
                'enum' => ['formal', 'casual', 'technical'],
                'default' => 'formal',
            ],
            'length' => [
                'type' => 'string',
                'description' => 'Desired length of the response',
                'enum' => ['brief', 'medium', 'detailed'],
                'default' => 'medium',
            ],
        ],
        'required' => ['topic'],
    ];

    /**
     * Generate prompt messages.
     *
     * @param  array  $arguments  Prompt arguments
     * @return array Prompt messages response
     */
    public function getMessages(array $arguments): array
    {
        $topic = $arguments['topic'];
        $style = $arguments['style'] ?? 'formal';
        $length = $arguments['length'] ?? 'medium';

        $prompt = $this->generatePromptText($topic, $style, $length);

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
            'description' => $this->description,
        ];
    }

    /**
     * Generate the prompt text based on arguments.
     *
     * @param  string  $topic  Topic for the prompt
     * @param  string  $style  Writing style
     * @param  string  $length  Response length
     * @return string Generated prompt text
     */
    protected function generatePromptText(string $topic, string $style, string $length): string
    {
        $styleInstructions = $this->getStyleInstructions($style);
        $lengthInstructions = $this->getLengthInstructions($length);

        return "Please provide information about {$topic}. ".
               "Writing style: {$styleInstructions}. ".
               "Response length: {$lengthInstructions}.";
    }

    /**
     * Get style instructions.
     *
     * @param  string  $style  Writing style
     * @return string Style instructions
     */
    protected function getStyleInstructions(string $style): string
    {
        switch ($style) {
            case 'formal':
                return 'Use professional, academic language with proper terminology';
            case 'casual':
                return 'Use conversational, friendly language that is easy to understand';
            case 'technical':
                return 'Use precise technical language with specific details and terminology';
            default:
                return 'Use clear, informative language';
        }
    }

    /**
     * Get length instructions.
     *
     * @param  string  $length  Response length
     * @return string Length instructions
     */
    protected function getLengthInstructions(string $length): string
    {
        switch ($length) {
            case 'brief':
                return 'Provide a concise summary in 1-2 sentences';
            case 'medium':
                return 'Provide a balanced explanation in 1-2 paragraphs';
            case 'detailed':
                return 'Provide a comprehensive explanation with examples and context';
            default:
                return 'Provide an appropriate level of detail';
        }
    }
}
