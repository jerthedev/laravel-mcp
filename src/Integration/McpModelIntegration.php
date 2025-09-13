<?php

namespace JTD\LaravelMCP\Integration;

use Illuminate\Database\Eloquent\Builder;

trait McpModelIntegration
{
    public function toMcpResource(): array
    {
        $data = $this->toArray();

        // Apply MCP-specific transformations
        return $this->transformForMcp($data);
    }

    public static function fromMcpParameters(array $parameters): static
    {
        $fillable = (new static)->getFillable();
        $attributes = array_intersect_key($parameters, array_flip($fillable));

        return new static($attributes);
    }

    public function scopeForMcp(Builder $query): Builder
    {
        // Apply MCP-specific scopes
        return $query->where('mcp_accessible', true);
    }

    protected function transformForMcp(array $data): array
    {
        // Remove sensitive attributes
        $hidden = $this->getHidden();
        foreach ($hidden as $attribute) {
            unset($data[$attribute]);
        }

        // Add computed attributes for MCP
        if (method_exists($this, 'getMcpAttributes')) {
            $data = array_merge($data, $this->getMcpAttributes());
        }

        return $data;
    }
}
