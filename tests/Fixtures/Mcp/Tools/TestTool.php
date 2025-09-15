<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Mcp\Tools;

use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Simple test tool for testing tool discovery and registration
 */
class TestTool extends McpTool
{
    protected string $name = 'test_tool';
    protected string $description = 'A simple test tool for verification';

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'A test message',
                ]
            ],
            'required' => ['message']
        ];
    }

    protected function handle(array $parameters): mixed
    {
        $message = $parameters['message'] ?? 'Hello World';

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => "Test tool executed with message: {$message}"
                ]
            ],
            'isError' => false
        ];
    }
}