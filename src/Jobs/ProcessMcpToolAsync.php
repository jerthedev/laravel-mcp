<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JTD\LaravelMCP\Events\AsyncRequestCompleted;
use JTD\LaravelMCP\Events\AsyncRequestFailed;
use JTD\LaravelMCP\Registry\McpRegistry;

class ProcessMcpToolAsync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $toolName;

    public array $parameters;

    public string $requestId;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [60, 120, 300];

    public function __construct(string $toolName, array $parameters, string $requestId)
    {
        $this->toolName = $toolName;
        $this->parameters = $parameters;
        $this->requestId = $requestId;
    }

    public function handle(McpRegistry $registry): void
    {
        $startTime = microtime(true);

        try {
            // Update status to processing
            $this->updateStatus('processing', [
                'started_at' => now()->toISOString(),
                'attempt' => $this->attempts(),
            ]);

            // Get the tool from the registry
            $tool = $registry->getTool($this->toolName);

            if (! $tool) {
                throw new \RuntimeException("Tool not found: {$this->toolName}");
            }

            // Execute the tool
            $result = $tool->execute($this->parameters);

            // Calculate execution time
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Store successful result
            $this->storeResult($result, 'completed', $executionTime);

            // Notify completion
            event(new AsyncRequestCompleted($this->requestId, $result));

        } catch (\Throwable $e) {
            $this->handleFailure($e, microtime(true) - $startTime);
            throw $e;
        }
    }

    private function updateStatus(string $status, array $data = []): void
    {
        cache()->put("mcp:async:status:{$this->requestId}", array_merge([
            'status' => $status,
            'tool' => $this->toolName,
            'updated_at' => now()->toISOString(),
        ], $data), 3600);
    }

    private function storeResult(mixed $result, string $status, float $executionTime): void
    {
        cache()->put("mcp:async_result:{$this->requestId}", [
            'status' => $status,
            'result' => $result,
            'execution_time' => $executionTime,
            'completed_at' => now()->toISOString(),
            'tool' => $this->toolName,
        ], 3600);
    }

    private function handleFailure(\Throwable $e, float $duration): void
    {
        $executionTime = $duration * 1000;

        // Store failure result
        cache()->put("mcp:async_result:{$this->requestId}", [
            'status' => 'failed',
            'error' => $e->getMessage(),
            'execution_time' => $executionTime,
            'failed_at' => now()->toISOString(),
            'attempt' => $this->attempts(),
        ], 3600);

        // Notify about failure
        event(new AsyncRequestFailed($this->requestId, $e->getMessage(), $this->attempts()));
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('Async MCP tool execution failed', [
            'tool' => $this->toolName,
            'parameters' => $this->parameters,
            'request_id' => $this->requestId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
