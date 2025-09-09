<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Resources;

use JTD\LaravelMCP\Abstracts\McpResource;

/**
 * Sample MCP Resource for testing purposes.
 *
 * This resource provides basic functionality for testing MCP resource
 * registration, reading, and subscription capabilities.
 */
class SampleResource extends McpResource
{
    protected string $uri = 'test://sample-resource';

    protected string $name = 'Sample Resource';

    protected string $description = 'A sample resource for testing MCP functionality';

    protected string $mimeType = 'application/json';

    protected bool $supportsSubscription = true;

    /**
     * Sample data for the resource.
     */
    protected array $data = [
        'title' => 'Sample Resource Data',
        'version' => '1.0.0',
        'items' => [
            ['id' => 1, 'name' => 'Item 1', 'value' => 100],
            ['id' => 2, 'name' => 'Item 2', 'value' => 200],
            ['id' => 3, 'name' => 'Item 3', 'value' => 300],
        ],
        'metadata' => [
            'created_at' => '2024-01-01T00:00:00Z',
            'updated_at' => '2024-01-15T12:00:00Z',
            'author' => 'Test Suite',
        ],
    ];

    /**
     * Read the resource content.
     *
     * @param  array  $options  Read options
     * @return array Resource content response
     */
    public function read(array $options = []): array
    {
        $data = $this->data;

        // Apply filtering if requested
        if (isset($options['filter'])) {
            $data = $this->applyFilter($data, $options['filter']);
        }

        // Apply pagination if requested
        if (isset($options['limit']) || isset($options['offset'])) {
            $data = $this->applyPagination($data, $options);
        }

        return [
            'contents' => [
                [
                    'uri' => $this->uri,
                    'mimeType' => $this->mimeType,
                    'text' => json_encode($data, JSON_PRETTY_PRINT),
                ],
            ],
        ];
    }

    /**
     * List available resources.
     *
     * @param  array  $options  List options
     * @return array List of resources
     */
    public function list(array $options = []): array
    {
        return [
            'resources' => [
                [
                    'uri' => $this->uri,
                    'name' => $this->name,
                    'description' => $this->description,
                    'mimeType' => $this->mimeType,
                ],
                [
                    'uri' => $this->uri.'/items',
                    'name' => 'Sample Resource Items',
                    'description' => 'Just the items from the sample resource',
                    'mimeType' => $this->mimeType,
                ],
                [
                    'uri' => $this->uri.'/metadata',
                    'name' => 'Sample Resource Metadata',
                    'description' => 'Metadata for the sample resource',
                    'mimeType' => $this->mimeType,
                ],
            ],
        ];
    }

    /**
     * Subscribe to resource changes.
     *
     * @param  array  $options  Subscription options
     * @return array Subscription response
     */
    public function subscribe(array $options = []): array
    {
        return [
            'subscription' => [
                'uri' => $this->uri,
                'subscribed' => true,
                'subscription_id' => 'sub_'.uniqid(),
            ],
        ];
    }

    /**
     * Unsubscribe from resource changes.
     *
     * @param  array  $options  Unsubscription options
     * @return array Unsubscription response
     */
    public function unsubscribe(array $options = []): array
    {
        return [
            'subscription' => [
                'uri' => $this->uri,
                'subscribed' => false,
                'subscription_id' => $options['subscription_id'] ?? null,
            ],
        ];
    }

    /**
     * Apply filter to data.
     *
     * @param  array  $data  Data to filter
     * @param  array  $filter  Filter criteria
     * @return array Filtered data
     */
    protected function applyFilter(array $data, array $filter): array
    {
        if (isset($filter['section'])) {
            switch ($filter['section']) {
                case 'items':
                    return ['items' => $data['items'] ?? []];
                case 'metadata':
                    return ['metadata' => $data['metadata'] ?? []];
                default:
                    return $data;
            }
        }

        return $data;
    }

    /**
     * Apply pagination to data.
     *
     * @param  array  $data  Data to paginate
     * @param  array  $options  Pagination options
     * @return array Paginated data
     */
    protected function applyPagination(array $data, array $options): array
    {
        if (isset($data['items'])) {
            $items = $data['items'];
            $offset = $options['offset'] ?? 0;
            $limit = $options['limit'] ?? count($items);

            $data['items'] = array_slice($items, $offset, $limit);
            $data['pagination'] = [
                'offset' => $offset,
                'limit' => $limit,
                'total' => count($items),
            ];
        }

        return $data;
    }
}
