<?php

namespace JTD\LaravelMCP\Middleware;

use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RateLimitMiddleware extends McpMiddleware
{
    private int $maxAttempts;

    private int $decayMinutes;

    public function __construct(int $maxAttempts = 60, int $decayMinutes = 1)
    {
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
    }

    protected function before(array $parameters): array
    {
        $key = $this->getRateLimitKey();

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            throw new HttpException(429, 'Too many requests');
        }

        RateLimiter::hit($key, $this->decayMinutes * 60);

        return $parameters;
    }

    private function getRateLimitKey(): string
    {
        return 'mcp:rate_limit:'.(auth()->id() ?? request()->ip());
    }
}
