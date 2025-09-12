<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;

/**
 * Test User Resource
 *
 * A user data resource for testing MCP resource functionality.
 */
class TestUserResource extends McpResource
{
    /**
     * Resource URI.
     */
    protected string $uri = 'test://users';

    /**
     * Resource name.
     */
    protected string $name = 'test_users';

    /**
     * Resource description.
     */
    protected string $description = 'Test user data resource';

    /**
     * Resource MIME type.
     */
    protected string $mimeType = 'application/json';

    /**
     * Test user data.
     */
    private array $testUsers = [
        [
            'id' => 1,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'admin',
        ],
        [
            'id' => 2,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'role' => 'user',
        ],
        [
            'id' => 3,
            'name' => 'Bob Johnson',
            'email' => 'bob@example.com',
            'role' => 'user',
        ],
    ];

    /**
     * Get the resource URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get the resource MIME type.
     */
    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get the resource description.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Read the resource content.
     */
    public function read(array $options = []): array
    {
        $filter = $options['filter'] ?? null;
        $users = $this->testUsers;

        // Apply filter if provided
        if ($filter) {
            if (isset($filter['role'])) {
                $users = array_filter($users, fn ($user) => $user['role'] === $filter['role']);
            }
            if (isset($filter['id'])) {
                $users = array_filter($users, fn ($user) => $user['id'] === $filter['id']);
            }
        }

        return [
            'contents' => [
                [
                    'uri' => $this->uri,
                    'mimeType' => $this->mimeType,
                    'text' => json_encode([
                        'users' => array_values($users),
                        'total' => count($users),
                        'timestamp' => now()->toIso8601String(),
                    ]),
                ],
            ],
        ];
    }

    /**
     * List available sub-resources.
     */
    public function list(): array
    {
        return [
            'resources' => array_map(function ($user) {
                return [
                    'uri' => $this->uri.'/'.$user['id'],
                    'name' => 'user_'.$user['id'],
                    'description' => 'User: '.$user['name'],
                    'mimeType' => $this->mimeType,
                ];
            }, $this->testUsers),
        ];
    }

    /**
     * Check if resource can be updated.
     */
    public function canUpdate(): bool
    {
        return false; // Read-only for testing
    }

    /**
     * Check if resource can be watched for changes.
     */
    public function canWatch(): bool
    {
        return true;
    }
}
