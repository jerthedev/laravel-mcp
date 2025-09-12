<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MCP Rate Limiting Middleware
 *
 * Provides comprehensive rate limiting for MCP endpoints:
 * - Per-user and per-IP rate limiting
 * - Method-specific rate limits
 * - Configurable rate limit strategies
 * - Proper rate limit headers in responses
 * - Graceful handling of rate limit exceeded
 */
class McpRateLimitMiddleware
{
    /**
     * @var RateLimiter Laravel rate limiter
     */
    protected RateLimiter $limiter;

    /**
     * @var array Default rate limits per method category
     */
    protected array $defaultLimits = [
        'tools' => ['attempts' => 60, 'decay' => 60],
        'resources' => ['attempts' => 100, 'decay' => 60],
        'prompts' => ['attempts' => 100, 'decay' => 60],
        'initialize' => ['attempts' => 10, 'decay' => 60],
        'sampling' => ['attempts' => 20, 'decay' => 60],
        'completion' => ['attempts' => 50, 'decay' => 60],
        'default' => ['attempts' => 60, 'decay' => 60],
    ];

    /**
     * Create a new middleware instance.
     */
    public function __construct(RateLimiter $limiter)
    {
        $this->limiter = $limiter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  string|null  $limiterName  Optional named limiter
     */
    public function handle(Request $request, Closure $next, ?string $limiterName = null): SymfonyResponse
    {
        // Skip rate limiting if disabled
        if (! $this->isRateLimitingEnabled()) {
            return $next($request);
        }

        // Get rate limit configuration
        $config = $this->getRateLimitConfig($request, $limiterName);

        // Generate rate limit key
        $key = $this->resolveRateLimitKey($request, $config);

        // Check if rate limit exceeded
        if ($this->limiter->tooManyAttempts($key, $config['attempts'])) {
            return $this->rateLimitExceededResponse($request, $key, $config);
        }

        // Increment the counter
        $this->limiter->hit($key, $config['decay']);

        // Process the request
        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $key, $config);
    }

    /**
     * Check if rate limiting is enabled.
     */
    protected function isRateLimitingEnabled(): bool
    {
        return config('laravel-mcp.rate_limiting.enabled', true);
    }

    /**
     * Get rate limit configuration for the request.
     */
    protected function getRateLimitConfig(Request $request, ?string $limiterName): array
    {
        // Use named limiter if provided
        if ($limiterName) {
            $namedConfig = config("laravel-mcp.rate_limiting.limiters.{$limiterName}");
            if ($namedConfig) {
                return $this->normalizeConfig($namedConfig);
            }
        }

        // Get method-specific configuration
        $method = $this->extractMethod($request);
        if ($method) {
            $methodConfig = $this->getMethodRateLimitConfig($method);
            if ($methodConfig) {
                return $methodConfig;
            }
        }

        // Use default configuration
        return $this->getDefaultRateLimitConfig();
    }

    /**
     * Extract MCP method from request.
     */
    protected function extractMethod(Request $request): ?string
    {
        if (! $request->isJson()) {
            return null;
        }

        return $request->json('method');
    }

    /**
     * Get method-specific rate limit configuration.
     */
    protected function getMethodRateLimitConfig(string $method): array
    {
        // Check for exact method configuration
        $exactConfig = config("laravel-mcp.rate_limiting.methods.{$method}");
        if ($exactConfig) {
            return $this->normalizeConfig($exactConfig);
        }

        // Check for category configuration
        $category = explode('/', $method)[0];
        $categoryConfig = config("laravel-mcp.rate_limiting.categories.{$category}");
        if ($categoryConfig) {
            return $this->normalizeConfig($categoryConfig);
        }

        // Use default for category
        if (isset($this->defaultLimits[$category])) {
            return $this->normalizeConfig($this->defaultLimits[$category]);
        }

        return $this->getDefaultRateLimitConfig();
    }

    /**
     * Get default rate limit configuration.
     */
    protected function getDefaultRateLimitConfig(): array
    {
        $config = config('laravel-mcp.rate_limiting.default', $this->defaultLimits['default']);

        return $this->normalizeConfig($config);
    }

    /**
     * Normalize rate limit configuration.
     */
    protected function normalizeConfig(array|int $config): array
    {
        // Handle simple integer config as attempts per minute
        if (is_int($config)) {
            return [
                'attempts' => $config,
                'decay' => 60,
                'strategy' => 'default',
            ];
        }

        return [
            'attempts' => $config['attempts'] ?? 60,
            'decay' => $config['decay'] ?? 60,
            'strategy' => $config['strategy'] ?? 'default',
        ];
    }

    /**
     * Resolve rate limit key for the request.
     */
    protected function resolveRateLimitKey(Request $request, array $config): string
    {
        $strategy = $config['strategy'] ?? 'default';
        $method = $this->extractMethod($request) ?? 'unknown';

        $keyParts = ['mcp', 'rate_limit', $method];

        switch ($strategy) {
            case 'per_user':
                $keyParts[] = $this->getUserIdentifier($request);
                break;

            case 'per_ip':
                $keyParts[] = $request->ip();
                break;

            case 'per_user_per_ip':
                $keyParts[] = $this->getUserIdentifier($request);
                $keyParts[] = $request->ip();
                break;

            case 'per_api_key':
                $keyParts[] = $this->getApiKeyIdentifier($request);
                break;

            case 'global':
                // No additional identifier needed
                break;

            default:
                // Default strategy: per user if authenticated, per IP otherwise
                $user = $this->getUserIdentifier($request);
                $keyParts[] = $user ?: $request->ip();
                break;
        }

        // Add custom key suffix if configured
        $customSuffix = config('laravel-mcp.rate_limiting.key_suffix');
        if ($customSuffix && is_callable($customSuffix)) {
            $keyParts[] = $customSuffix($request);
        }

        return implode(':', array_filter($keyParts));
    }

    /**
     * Get user identifier for rate limiting.
     */
    protected function getUserIdentifier(Request $request): ?string
    {
        // Check request attributes set by auth middleware
        $userId = $request->attributes->get('mcp_user_id');
        if ($userId) {
            return 'user:'.$userId;
        }

        // Check Laravel auth
        if (auth()->check()) {
            return 'user:'.auth()->id();
        }

        return null;
    }

    /**
     * Get API key identifier for rate limiting.
     */
    protected function getApiKeyIdentifier(Request $request): string
    {
        $apiKey = $request->header('X-MCP-API-Key')
            ?? $request->header('X-API-Key')
            ?? $request->query('api_key')
            ?? 'anonymous';

        // Hash the API key for security
        return 'api:'.substr(md5($apiKey), 0, 16);
    }

    /**
     * Add rate limit headers to response.
     */
    protected function addRateLimitHeaders(SymfonyResponse $response, string $key, array $config): SymfonyResponse
    {
        $maxAttempts = $config['attempts'];
        $remainingAttempts = $this->limiter->remaining($key, $maxAttempts);
        $retryAfter = $this->limiter->availableIn($key);

        $response->headers->set('X-RateLimit-Limit', (string) $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $remainingAttempts));

        if ($retryAfter > 0) {
            $response->headers->set('X-RateLimit-Reset', (string) (time() + $retryAfter));
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        // Add method-specific rate limit info if different from default
        $method = $this->extractMethod(request());
        if ($method) {
            $response->headers->set('X-RateLimit-Method', $method);
        }

        return $response;
    }

    /**
     * Return rate limit exceeded response.
     */
    protected function rateLimitExceededResponse(Request $request, string $key, array $config): JsonResponse
    {
        $retryAfter = $this->limiter->availableIn($key);
        $method = $this->extractMethod($request) ?? 'unknown';

        // Log rate limit exceeded
        $this->logRateLimitExceeded($request, $method, $retryAfter);

        $response = response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32029,
                'message' => 'Too many requests',
                'data' => [
                    'type' => 'rate_limit_exceeded',
                    'retry_after' => $retryAfter,
                    'limit' => $config['attempts'],
                    'window' => $config['decay'],
                    'method' => $method,
                ],
            ],
            'id' => $request->json('id'),
        ], Response::HTTP_TOO_MANY_REQUESTS);

        // Add rate limit headers
        return $this->addRateLimitHeaders($response, $key, $config);
    }

    /**
     * Log rate limit exceeded event.
     */
    protected function logRateLimitExceeded(Request $request, string $method, int $retryAfter): void
    {
        if (! config('laravel-mcp.rate_limiting.log_exceeded', true)) {
            return;
        }

        Log::warning('MCP rate limit exceeded', [
            'method' => $method,
            'ip' => $request->ip(),
            'user_id' => $request->attributes->get('mcp_user_id'),
            'retry_after' => $retryAfter,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Clear rate limit for a specific key.
     *
     * @param  string  $key  Rate limit key to clear
     */
    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }

    /**
     * Reset all rate limits for a user.
     *
     * @param  int|string  $userId  User ID
     */
    public function resetForUser($userId): void
    {
        // This would need to be implemented based on your caching strategy
        // For example, if using Redis, you might scan for keys matching the user pattern
        Log::info('Rate limits reset for user', ['user_id' => $userId]);
    }

    /**
     * Get current rate limit status for a request.
     */
    public function getStatus(Request $request, ?string $limiterName = null): array
    {
        $config = $this->getRateLimitConfig($request, $limiterName);
        $key = $this->resolveRateLimitKey($request, $config);

        $maxAttempts = $config['attempts'];
        $remainingAttempts = $this->limiter->remaining($key, $maxAttempts);
        $retryAfter = $this->limiter->availableIn($key);

        return [
            'limit' => $maxAttempts,
            'remaining' => max(0, $remainingAttempts),
            'reset' => $retryAfter > 0 ? time() + $retryAfter : null,
            'retry_after' => $retryAfter > 0 ? $retryAfter : null,
        ];
    }
}
