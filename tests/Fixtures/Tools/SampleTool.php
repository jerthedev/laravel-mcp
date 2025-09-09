<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Sample MCP Tool for testing purposes.
 *
 * This tool provides basic functionality for testing MCP tool
 * registration, execution, and validation.
 */
class SampleTool extends McpTool
{
    protected string $name = 'sample_tool';

    protected string $description = 'A sample tool for testing MCP functionality';

    protected array $inputSchema = [
        'type' => 'object',
        'properties' => [
            'message' => [
                'type' => 'string',
                'description' => 'Message to process',
            ],
            'uppercase' => [
                'type' => 'boolean',
                'description' => 'Whether to convert to uppercase',
                'default' => false,
            ],
            'repeat' => [
                'type' => 'integer',
                'description' => 'Number of times to repeat the message',
                'default' => 1,
                'minimum' => 1,
                'maximum' => 10,
            ],
        ],
        'required' => ['message'],
    ];

    /**
     * Execute the sample tool.
     *
     * @param  array  $arguments  Tool arguments
     * @return array Tool execution result
     */
    public function execute(array $arguments): array
    {
        $message = $arguments['message'] ?? '';
        $uppercase = $arguments['uppercase'] ?? false;
        $repeat = $arguments['repeat'] ?? 1;

        // Process the message
        if ($uppercase) {
            $message = strtoupper($message);
        }

        // Repeat the message
        $result = str_repeat($message.' ', $repeat);
        $result = trim($result);

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => $result,
                ],
            ],
            'isError' => false,
        ];
    }
}
