<?php

namespace App\Mcp\Resources;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JTD\LaravelMCP\Abstracts\McpResource;

/**
 * Database resource for accessing user records
 *
 * This resource demonstrates secure database access patterns
 * with proper data filtering and error handling.
 */
class UserDatabaseResource extends McpResource
{
    /**
     * Get the resource name
     */
    public function getName(): string
    {
        return 'user_database';
    }

    /**
     * Get the resource description
     */
    public function getDescription(): string
    {
        return 'Provides read access to user database records with proper security filtering.';
    }

    /**
     * Get the URI template for this resource
     */
    public function getUriTemplate(): string
    {
        return 'database://users/{id}';
    }

    /**
     * Read a specific user record
     */
    public function read(string $uri): array
    {
        // Parse the URI to extract the user ID
        $parsedUri = parse_url($uri);
        $path = trim($parsedUri['path'] ?? '', '/');
        $pathParts = explode('/', $path);

        if (count($pathParts) !== 2 || $pathParts[0] !== 'users') {
            throw new \InvalidArgumentException('Invalid URI format. Expected: database://users/{id}');
        }

        $userId = $pathParts[1];

        if (! is_numeric($userId)) {
            throw new \InvalidArgumentException('User ID must be numeric');
        }

        try {
            $user = User::findOrFail($userId);

            return [
                'contents' => [
                    [
                        'uri' => $uri,
                        'mimeType' => 'application/json',
                        'text' => json_encode($this->filterUserData($user->toArray()), JSON_PRETTY_PRINT),
                    ],
                ],
            ];
        } catch (ModelNotFoundException $e) {
            throw new \RuntimeException("User with ID {$userId} not found");
        }
    }

    /**
     * List available user resources
     */
    public function list(?string $cursor = null): array
    {
        $perPage = 10;
        $page = $cursor ? (int) $cursor : 1;

        $users = User::select(['id', 'name', 'email', 'created_at'])
            ->orderBy('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $resources = [];

        foreach ($users->items() as $user) {
            $resources[] = [
                'uri' => "database://users/{$user->id}",
                'name' => "User: {$user->name}",
                'description' => "Database record for user {$user->name} ({$user->email})",
                'mimeType' => 'application/json',
            ];
        }

        $response = [
            'resources' => $resources,
        ];

        // Add pagination cursor if there are more pages
        if ($users->hasMorePages()) {
            $response['nextCursor'] = (string) ($page + 1);
        }

        return $response;
    }

    /**
     * Filter user data to exclude sensitive information
     */
    private function filterUserData(array $userData): array
    {
        // Remove sensitive fields
        $allowedFields = [
            'id',
            'name',
            'email',
            'created_at',
            'updated_at',
        ];

        return array_intersect_key($userData, array_flip($allowedFields));
    }

    /**
     * Check if this resource supports subscription
     */
    public function supportsSubscription(): bool
    {
        return false; // Database resources typically don't support real-time subscriptions
    }

    /**
     * Get supported operations for this resource
     */
    public function getSupportedOperations(): array
    {
        return ['read', 'list'];
    }
}
