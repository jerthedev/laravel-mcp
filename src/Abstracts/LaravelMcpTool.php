<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;

abstract class LaravelMcpTool extends McpTool
{
    protected ValidationFactory $validator;

    protected Dispatcher $events;

    protected Cache $cache;

    protected Log $logger;

    public function __construct()
    {
        parent::__construct();

        // Inject Laravel services
        $this->validator = app()->make(ValidationFactory::class);
        $this->events = app()->make(Dispatcher::class);
        $this->cache = app()->make(Cache::class);
        $this->logger = app()->make(Log::class);
    }

    protected function dispatch($event): void
    {
        $this->events->dispatch($event);
    }

    protected function cache(string $key, \Closure $callback, ?int $ttl = null): mixed
    {
        return $this->cache->remember($key, $ttl, $callback);
    }

    protected function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
