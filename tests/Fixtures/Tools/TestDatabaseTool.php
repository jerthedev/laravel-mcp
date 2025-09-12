<?php

namespace JTD\LaravelMCP\Tests\Fixtures\Tools;

use Illuminate\Support\Facades\DB;
use JTD\LaravelMCP\Abstracts\McpTool;

/**
 * Test Database Tool
 *
 * A database query tool for testing database-related MCP functionality.
 */
class TestDatabaseTool extends McpTool
{
    /**
     * Tool name.
     */
    protected string $name = 'test_database';

    /**
     * Tool description.
     */
    protected string $description = 'Executes test database queries';

    /**
     * Tool parameter schema.
     */
    protected array $parameterSchema = [
        'action' => [
            'type' => 'string',
            'description' => 'Database action to perform',
            'enum' => ['count', 'list', 'find', 'create'],
            'required' => true,
        ],
        'table' => [
            'type' => 'string',
            'description' => 'Database table name',
            'required' => true,
        ],
        'id' => [
            'type' => 'integer',
            'description' => 'Record ID for find action',
            'required' => false,
        ],
        'data' => [
            'type' => 'object',
            'description' => 'Data for create action',
            'required' => false,
        ],
    ];

    /**
     * Handle the tool execution.
     */
    protected function handle(array $parameters): mixed
    {
        $action = $parameters['action'];
        $table = $parameters['table'];

        // Validate table name to prevent SQL injection
        if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
            throw new \InvalidArgumentException('Invalid table name');
        }

        $result = match ($action) {
            'count' => $this->countRecords($table),
            'list' => $this->listRecords($table),
            'find' => $this->findRecord($table, $parameters['id'] ?? null),
            'create' => $this->createRecord($table, $parameters['data'] ?? []),
            default => throw new \InvalidArgumentException("Unknown action: {$action}"),
        };

        return [
            'content' => [
                [
                    'type' => 'text',
                    'text' => json_encode($result),
                ],
            ],
        ];
    }

    /**
     * Count records in a table.
     */
    private function countRecords(string $table): array
    {
        try {
            $count = DB::table($table)->count();

            return ['count' => $count];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * List records from a table.
     */
    private function listRecords(string $table): array
    {
        try {
            $records = DB::table($table)->limit(10)->get();

            return ['records' => $records->toArray()];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Find a specific record.
     */
    private function findRecord(string $table, ?int $id): array
    {
        if ($id === null) {
            return ['error' => 'ID is required for find action'];
        }

        try {
            $record = DB::table($table)->find($id);

            return ['record' => $record];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Create a new record.
     */
    private function createRecord(string $table, array $data): array
    {
        if (empty($data)) {
            return ['error' => 'Data is required for create action'];
        }

        try {
            $id = DB::table($table)->insertGetId($data);

            return ['id' => $id, 'created' => true];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
