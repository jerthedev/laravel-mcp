<?php

namespace JTD\LaravelMCP\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use JTD\LaravelMCP\Events\McpRequestProcessed;
use JTD\LaravelMCP\Registry\McpRegistry;

/**
 * Job for processing MCP requests asynchronously.
 *
 * This job handles MCP tool executions, resource accesses, and prompt generations
 * in the background queue. It provides retry logic, timeout handling, and result
 * caching for async operations.
 */
class ProcessMcpRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The MCP method to execute.
     */
    public string $method;

    /**
     * The request parameters.
     */
    public array $parameters;

    /**
     * The unique request ID for tracking.
     */
    public string $requestId;

    /**
     * Additional context for the request.
     */
    public array $context;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * Indicate if the job should be encrypted.
     *
     * @var bool
     */
    public $shouldBeEncrypted = false;

    /**
     * Create a new job instance.
     *
     * @param  string  $method  The MCP method to execute
     * @param  array  $parameters  The request parameters
     * @param  string|null  $requestId  Optional request ID (will be generated if not provided)
     * @param  array  $context  Additional context
     */
    public function __construct(
        string $method,
        array $parameters = [],
        ?string $requestId = null,
        array $context = []
    ) {
        $this->method = $method;
        $this->parameters = $parameters;
        $this->requestId = $requestId ?? $this->generateRequestId();
        $this->context = array_merge($context, [
            'async' => true,
            'queued_at' => $this->getCurrentTimestamp(),
        ]);
    }

    /**
     * Get the current timestamp.
     */
    protected function getCurrentTimestamp(): string
    {
        // Use now() if available (Laravel is bootstrapped)
        if (function_exists('now')) {
            return now()->toISOString();
        }

        // Fallback to native PHP
        return (new \DateTime)->format('c');
    }

    /**
     * Execute the job.
     *
     * @param  McpRegistry  $registry  The MCP registry
     *
     * @throws \Exception
     */
    public function handle(McpRegistry $registry): void
    {
        $startTime = microtime(true);

        try {
            // Update job status to processing
            $this->updateJobStatus('processing');

            // Parse the method to determine component type and name
            [$componentType, $componentName] = $this->parseMethod();

            // Execute the appropriate component
            $result = match ($componentType) {
                'tools' => $this->executeTool($registry, $componentName),
                'resources' => $this->executeResource($registry, $componentName),
                'prompts' => $this->executePrompt($registry, $componentName),
                default => throw new \InvalidArgumentException("Unknown method: {$this->method}"),
            };

            // Calculate execution time
            $executionTime = (microtime(true) - $startTime) * 1000;

            // Store the result for retrieval
            $this->storeResult($result, $executionTime);

            // Dispatch request processed event
            event(new McpRequestProcessed(
                $this->requestId,
                $this->method,
                $this->parameters,
                $result,
                $executionTime,
                'queue',
                $this->context
            ));

            // Log successful execution
            Log::info('MCP request processed successfully', [
                'request_id' => $this->requestId,
                'method' => $this->method,
                'execution_time_ms' => $executionTime,
            ]);

        } catch (\Throwable $e) {
            $this->handleFailure($e);
            throw $e;
        }
    }

    /**
     * Parse the method to extract component type and name.
     *
     * @return array [componentType, componentName]
     */
    protected function parseMethod(): array
    {
        $parts = explode('/', $this->method);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Invalid method format: {$this->method}");
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * Execute a tool.
     */
    protected function executeTool(McpRegistry $registry, string $toolName): mixed
    {
        $tool = $registry->getTool($toolName);

        if (! $tool) {
            throw new \RuntimeException("Tool not found: {$toolName}");
        }

        return $tool->execute($this->parameters);
    }

    /**
     * Execute a resource operation.
     */
    protected function executeResource(McpRegistry $registry, string $resourceName): mixed
    {
        $resource = $registry->getResource($resourceName);

        if (! $resource) {
            throw new \RuntimeException("Resource not found: {$resourceName}");
        }

        $action = $this->parameters['action'] ?? 'read';

        return match ($action) {
            'read' => $resource->read($this->parameters),
            'list' => $resource->list($this->parameters),
            default => throw new \InvalidArgumentException("Invalid resource action: {$action}"),
        };
    }

    /**
     * Execute a prompt generation.
     */
    protected function executePrompt(McpRegistry $registry, string $promptName): mixed
    {
        $prompt = $registry->getPrompt($promptName);

        if (! $prompt) {
            throw new \RuntimeException("Prompt not found: {$promptName}");
        }

        return $prompt->generate($this->parameters);
    }

    /**
     * Store the job result for retrieval.
     */
    protected function storeResult(mixed $result, float $executionTime): void
    {
        $data = [
            'status' => 'completed',
            'result' => $result,
            'execution_time_ms' => $executionTime,
            'completed_at' => now()->toISOString(),
            'attempts' => $this->attempts(),
        ];

        // Store for 1 hour by default
        $ttl = $this->context['result_ttl'] ?? 3600;
        Cache::put($this->getResultCacheKey(), $data, $ttl);
    }

    /**
     * Update the job status in cache.
     */
    protected function updateJobStatus(string $status): void
    {
        $data = [
            'status' => $status,
            'updated_at' => $this->getCurrentTimestamp(),
            'attempts' => $this->attempts(),
        ];

        Cache::put($this->getStatusCacheKey(), $data, 300); // 5 minutes
    }

    /**
     * Handle job failure.
     */
    protected function handleFailure(\Throwable $exception): void
    {
        $data = [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'error_class' => get_class($exception),
            'failed_at' => $this->getCurrentTimestamp(),
            'attempts' => $this->attempts(),
        ];

        // Store failure for 1 hour
        Cache::put($this->getResultCacheKey(), $data, 3600);

        Log::error('MCP request processing failed', [
            'request_id' => $this->requestId,
            'method' => $this->method,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->handleFailure($exception);

        // Additional cleanup or notifications can be added here
        Log::critical('MCP job permanently failed after retries', [
            'request_id' => $this->requestId,
            'method' => $this->method,
            'parameters' => $this->parameters,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the cache key for storing results.
     */
    public function getResultCacheKey(): string
    {
        return "mcp:async:result:{$this->requestId}";
    }

    /**
     * Get the cache key for status updates.
     */
    public function getStatusCacheKey(): string
    {
        return "mcp:async:status:{$this->requestId}";
    }

    /**
     * Generate a unique request ID.
     */
    protected function generateRequestId(): string
    {
        return 'mcp-req-'.bin2hex(random_bytes(8));
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'mcp',
            'mcp:'.$this->method,
            'request:'.$this->requestId,
        ];
    }

    /**
     * Determine the time at which the job should timeout.
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(15);
    }

    /**
     * Get the job's display name.
     */
    public function displayName(): string
    {
        return "MCP Request: {$this->method}";
    }
}
