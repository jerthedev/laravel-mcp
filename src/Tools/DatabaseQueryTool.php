<?php

namespace JTD\LaravelMCP\Tools;

use Illuminate\Support\Facades\DB;
use JTD\LaravelMCP\Abstracts\LaravelMcpTool;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class DatabaseQueryTool extends LaravelMcpTool
{
    protected string $name = 'db_query';

    protected string $description = 'Execute safe database queries';

    protected array $parameterSchema = [
        'table' => [
            'type' => 'string',
            'description' => 'Table name to query',
            'required' => true,
        ],
        'columns' => [
            'type' => 'array',
            'description' => 'Columns to select',
            'required' => false,
        ],
        'where' => [
            'type' => 'array',
            'description' => 'Where conditions',
            'required' => false,
        ],
        'limit' => [
            'type' => 'integer',
            'description' => 'Maximum number of records',
            'minimum' => 1,
            'maximum' => 100,
            'required' => false,
        ],
    ];

    protected bool $requiresAuth = true;

    protected function handle(array $parameters): mixed
    {
        $this->validateTableAccess($parameters['table']);

        $query = DB::table($parameters['table']);

        if (isset($parameters['columns'])) {
            $query->select($parameters['columns']);
        }

        if (isset($parameters['where'])) {
            foreach ($parameters['where'] as $condition) {
                $query->where($condition['column'], $condition['operator'] ?? '=', $condition['value']);
            }
        }

        $limit = $parameters['limit'] ?? 50;

        return $query->limit($limit)->get()->toArray();
    }

    private function validateTableAccess(string $table): void
    {
        $allowedTables = config('laravel-mcp.database.allowed_tables', []);

        if (! empty($allowedTables) && ! in_array($table, $allowedTables)) {
            throw new UnauthorizedHttpException('Bearer', "Access to table '{$table}' is not allowed");
        }

        $forbiddenTables = config('laravel-mcp.database.forbidden_tables', [
            'password_resets', 'sessions', 'personal_access_tokens',
        ]);

        if (in_array($table, $forbiddenTables)) {
            throw new UnauthorizedHttpException('Bearer', "Access to table '{$table}' is forbidden");
        }
    }
}
