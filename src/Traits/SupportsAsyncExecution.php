<?php

namespace JTD\LaravelMCP\Traits;

use Illuminate\Support\Str;
use JTD\LaravelMCP\Jobs\ProcessMcpToolAsync;

trait SupportsAsyncExecution
{
    protected bool $runAsync = false;

    protected ?string $queue = null;

    public function executeAsync(array $parameters): string
    {
        $requestId = Str::uuid()->toString();

        ProcessMcpToolAsync::dispatch(
            $this->getName(),
            $parameters,
            $requestId
        )->onQueue($this->queue ?? 'mcp-tools');

        return $requestId;
    }

    public function getAsyncResult(string $requestId): ?array
    {
        return cache()->get("mcp:async_result:{$requestId}");
    }

    public function setAsyncMode(bool $async = true): self
    {
        $this->runAsync = $async;

        return $this;
    }

    public function setQueue(string $queue): self
    {
        $this->queue = $queue;

        return $this;
    }

    public function isAsync(): bool
    {
        return $this->runAsync;
    }
}
