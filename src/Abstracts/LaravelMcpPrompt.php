<?php

namespace JTD\LaravelMCP\Abstracts;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Logging\Log;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Support\Facades\View;

abstract class LaravelMcpPrompt extends McpPrompt
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

    protected function renderView(string $view, array $data = []): string
    {
        if (View::exists($view)) {
            return View::make($view, $data)->render();
        }

        return parent::generate($data);
    }

    protected function validateArguments(array $arguments, array $rules): array
    {
        $validator = $this->validator->make($arguments, $rules);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Validation failed: '.implode(', ', $validator->errors()->all())
            );
        }

        return $validator->validated();
    }
}
