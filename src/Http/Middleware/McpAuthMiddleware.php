<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * MCP Authentication Middleware
 *
 * Provides comprehensive authentication for MCP endpoints with support for:
 * - Laravel's authentication guards (session, token, sanctum, etc.)
 * - API key authentication
 * - Custom authentication drivers
 * - User context injection for MCP requests
 */
class McpAuthMiddleware
{
    /**
     * @var AuthFactory Laravel authentication factory
     */
    protected AuthFactory $auth;

    /**
     * Create a new middleware instance.
     */
    public function __construct(AuthFactory $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  string|null  $guard  Optional guard to use
     */
    public function handle(Request $request, Closure $next, ?string $guard = null): SymfonyResponse
    {
        // Check if authentication is required for MCP endpoints
        if (! $this->isAuthenticationRequired()) {
            return $next($request);
        }

        try {
            // Attempt authentication using configured methods
            $authenticated = $this->authenticate($request, $guard);

            if (! $authenticated) {
                return $this->unauthenticatedResponse('Authentication required');
            }

            // Add user context to request for downstream use
            $this->addUserContext($request);

            // Log successful authentication if configured
            $this->logAuthentication($request);

            return $next($request);
        } catch (AuthenticationException $e) {
            return $this->unauthenticatedResponse($e->getMessage());
        } catch (\Exception $e) {
            Log::error('MCP authentication error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('Authentication error occurred');
        }
    }

    /**
     * Check if authentication is required.
     */
    protected function isAuthenticationRequired(): bool
    {
        return config('laravel-mcp.auth.enabled', false);
    }

    /**
     * Attempt to authenticate the request.
     *
     * @throws AuthenticationException
     */
    protected function authenticate(Request $request, ?string $guard): bool
    {
        // Priority 1: Try Laravel guard authentication
        if ($this->authenticateWithGuard($request, $guard)) {
            return true;
        }

        // Priority 2: Try API key authentication
        if ($this->authenticateWithApiKey($request)) {
            return true;
        }

        // Priority 3: Try bearer token authentication
        if ($this->authenticateWithBearerToken($request)) {
            return true;
        }

        // Priority 4: Try custom authentication callback
        if ($this->authenticateWithCustomCallback($request)) {
            return true;
        }

        return false;
    }

    /**
     * Authenticate using Laravel guard.
     */
    protected function authenticateWithGuard(Request $request, ?string $guard): bool
    {
        $guardName = $guard ?? config('laravel-mcp.auth.guard');

        if (! $guardName) {
            return false;
        }

        try {
            $authGuard = $this->auth->guard($guardName);

            // Check if user is already authenticated
            if ($authGuard->check()) {
                return true;
            }

            // Attempt to authenticate from request
            if (method_exists($authGuard, 'attempt')) {
                $credentials = $this->extractCredentials($request);
                if (! empty($credentials) && $authGuard->attempt($credentials)) {
                    return true;
                }
            }

            // For token-based guards, check the token
            if ($request->bearerToken() && method_exists($authGuard, 'setToken')) {
                $authGuard->setToken($request->bearerToken());

                return $authGuard->check();
            }
        } catch (\Exception $e) {
            Log::debug('Guard authentication failed', [
                'guard' => $guardName,
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Authenticate using API key.
     */
    protected function authenticateWithApiKey(Request $request): bool
    {
        if (! config('laravel-mcp.auth.api_key_enabled', true)) {
            return false;
        }

        $apiKey = $this->extractApiKey($request);
        if (! $apiKey) {
            return false;
        }

        $configuredKey = config('laravel-mcp.auth.api_key');
        if (empty($configuredKey)) {
            return false;
        }

        // Support multiple API keys
        $validKeys = is_array($configuredKey) ? $configuredKey : [$configuredKey];

        foreach ($validKeys as $validKey) {
            if (hash_equals($validKey, $apiKey)) {
                // Optionally set a default user for API key authentication
                $this->setApiKeyUser($request, $apiKey);

                return true;
            }
        }

        return false;
    }

    /**
     * Authenticate using bearer token.
     */
    protected function authenticateWithBearerToken(Request $request): bool
    {
        $token = $request->bearerToken();
        if (! $token) {
            return false;
        }

        // Check if bearer token authentication is enabled
        if (! config('laravel-mcp.auth.bearer_token_enabled', true)) {
            return false;
        }

        // Use the configured token guard (e.g., sanctum, passport)
        $tokenGuard = config('laravel-mcp.auth.token_guard', 'sanctum');

        try {
            $guard = $this->auth->guard($tokenGuard);
            if (method_exists($guard, 'setToken')) {
                $guard->setToken($token);
            }

            return $guard->check();
        } catch (\Exception $e) {
            Log::debug('Bearer token authentication failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Authenticate using custom callback.
     */
    protected function authenticateWithCustomCallback(Request $request): bool
    {
        $callback = config('laravel-mcp.auth.custom_callback');

        if (! $callback || ! is_callable($callback)) {
            return false;
        }

        try {
            return $callback($request);
        } catch (\Exception $e) {
            Log::error('Custom authentication callback failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Extract API key from request.
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Check multiple locations for API key
        return $request->header('X-MCP-API-Key')
            ?? $request->header('X-API-Key')
            ?? $request->query('api_key')
            ?? $request->input('api_key');
    }

    /**
     * Extract credentials from request for guard authentication.
     */
    protected function extractCredentials(Request $request): array
    {
        $credentials = [];

        // Extract from JSON body for MCP requests
        if ($request->isJson()) {
            $params = $request->json('params', []);
            if (isset($params['auth'])) {
                $credentials = $params['auth'];
            }
        }

        // Fallback to standard request inputs
        if (empty($credentials)) {
            $credentials = $request->only(['email', 'username', 'password']);
        }

        return $credentials;
    }

    /**
     * Set user context for API key authentication.
     */
    protected function setApiKeyUser(Request $request, string $apiKey): void
    {
        // Check if there's a user mapping for this API key
        $userMapping = config('laravel-mcp.auth.api_key_users', []);

        if (isset($userMapping[$apiKey])) {
            $userId = $userMapping[$apiKey];
            $userModel = config('auth.providers.users.model', \App\Models\User::class);

            try {
                $user = $userModel::find($userId);
                if ($user) {
                    $this->auth->guard()->setUser($user);
                }
            } catch (\Exception $e) {
                Log::debug('Failed to set API key user', [
                    'api_key' => substr($apiKey, 0, 8).'...',
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Add user context to the request.
     */
    protected function addUserContext(Request $request): void
    {
        $user = $this->auth->user();

        if ($user) {
            // Add user to request attributes for downstream use
            $request->attributes->set('mcp_user', $user);
            $request->attributes->set('mcp_user_id', $user->getAuthIdentifier());

            // Add user context to request headers for logging
            $request->headers->set('X-MCP-User-ID', (string) $user->getAuthIdentifier());
        }
    }

    /**
     * Log successful authentication.
     */
    protected function logAuthentication(Request $request): void
    {
        if (! config('laravel-mcp.auth.log_authentications', false)) {
            return;
        }

        $user = $this->auth->user();

        Log::info('MCP authentication successful', [
            'user_id' => $user ? $user->getAuthIdentifier() : null,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * Return unauthenticated response.
     */
    protected function unauthenticatedResponse(string $message = 'Unauthenticated'): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32001,
                'message' => $message,
                'data' => [
                    'type' => 'authentication_required',
                ],
            ],
            'id' => null,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Return error response.
     */
    protected function errorResponse(string $message): JsonResponse
    {
        return response()->json([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => -32603,
                'message' => $message,
                'data' => [
                    'type' => 'internal_error',
                ],
            ],
            'id' => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
