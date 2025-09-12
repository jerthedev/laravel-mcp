<?php

declare(strict_types=1);

namespace JTD\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * MCP Error Handling Middleware
 *
 * Provides consistent error handling for MCP endpoints:
 * - Catches and formats exceptions as JSON-RPC errors
 * - Maps Laravel exceptions to appropriate MCP error codes
 * - Provides debug information in development mode
 * - Maintains JSON-RPC 2.0 error standards
 * - Logs errors appropriately based on severity
 */
class McpErrorHandlingMiddleware
{
    /**
     * @var array JSON-RPC 2.0 standard error codes
     */
    protected const JSON_RPC_ERRORS = [
        'PARSE_ERROR' => -32700,
        'INVALID_REQUEST' => -32600,
        'METHOD_NOT_FOUND' => -32601,
        'INVALID_PARAMS' => -32602,
        'INTERNAL_ERROR' => -32603,
    ];

    /**
     * @var array MCP-specific error codes (reserved range: -32000 to -32099)
     */
    protected const MCP_ERRORS = [
        'AUTHENTICATION_REQUIRED' => -32001,
        'PERMISSION_DENIED' => -32002,
        'RESOURCE_NOT_FOUND' => -32003,
        'RESOURCE_CONFLICT' => -32004,
        'RATE_LIMIT_EXCEEDED' => -32029,
        'CAPABILITY_NOT_SUPPORTED' => -32005,
        'TOOL_NOT_FOUND' => -32006,
        'TOOL_EXECUTION_FAILED' => -32007,
        'INVALID_URI' => -32008,
        'PROTOCOL_ERROR' => -32009,
        'SERVER_NOT_INITIALIZED' => -32010,
        'REQUEST_CANCELLED' => -32011,
        'REQUEST_TIMEOUT' => -32012,
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        try {
            return $next($request);
        } catch (\Throwable $exception) {
            return $this->handleException($request, $exception);
        }
    }

    /**
     * Handle an exception and return appropriate error response.
     */
    protected function handleException(Request $request, \Throwable $exception): JsonResponse
    {
        // Log the exception
        $this->logException($exception, $request);

        // Convert exception to error response
        $errorData = $this->convertExceptionToError($exception, $request);

        // Build JSON-RPC error response
        return $this->buildErrorResponse($errorData, $request);
    }

    /**
     * Convert exception to error data.
     */
    protected function convertExceptionToError(\Throwable $exception, Request $request): array
    {
        // Handle specific Laravel exceptions
        if ($exception instanceof ValidationException) {
            return $this->handleValidationException($exception);
        }

        if ($exception instanceof AuthenticationException) {
            return $this->handleAuthenticationException($exception);
        }

        if ($exception instanceof AuthorizationException) {
            return $this->handleAuthorizationException($exception);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->handleModelNotFoundException($exception);
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->handleNotFoundException($exception);
        }

        if ($exception instanceof HttpException) {
            return $this->handleHttpException($exception);
        }

        // Handle MCP-specific exceptions
        if ($this->isMcpException($exception)) {
            return $this->handleMcpException($exception);
        }

        // Handle generic exceptions
        return $this->handleGenericException($exception);
    }

    /**
     * Handle validation exception.
     */
    protected function handleValidationException(ValidationException $exception): array
    {
        $errors = $exception->errors();
        $firstError = reset($errors);
        $message = is_array($firstError) ? $firstError[0] : $firstError;

        return [
            'code' => self::JSON_RPC_ERRORS['INVALID_PARAMS'],
            'message' => 'Invalid params',
            'data' => [
                'type' => 'validation_error',
                'message' => $message,
                'errors' => $errors,
            ],
            'http_status' => Response::HTTP_BAD_REQUEST,
        ];
    }

    /**
     * Handle authentication exception.
     */
    protected function handleAuthenticationException(AuthenticationException $exception): array
    {
        return [
            'code' => self::MCP_ERRORS['AUTHENTICATION_REQUIRED'],
            'message' => 'Authentication required',
            'data' => [
                'type' => 'authentication_error',
                'message' => $exception->getMessage() ?: 'Unauthenticated',
                'guards' => $exception->guards(),
            ],
            'http_status' => Response::HTTP_UNAUTHORIZED,
        ];
    }

    /**
     * Handle authorization exception.
     */
    protected function handleAuthorizationException(AuthorizationException $exception): array
    {
        return [
            'code' => self::MCP_ERRORS['PERMISSION_DENIED'],
            'message' => 'Permission denied',
            'data' => [
                'type' => 'authorization_error',
                'message' => $exception->getMessage() ?: 'Unauthorized',
            ],
            'http_status' => Response::HTTP_FORBIDDEN,
        ];
    }

    /**
     * Handle model not found exception.
     */
    protected function handleModelNotFoundException(ModelNotFoundException $exception): array
    {
        $model = class_basename($exception->getModel());

        return [
            'code' => self::MCP_ERRORS['RESOURCE_NOT_FOUND'],
            'message' => 'Resource not found',
            'data' => [
                'type' => 'resource_not_found',
                'message' => "Resource {$model} not found",
                'model' => $model,
                'ids' => $exception->getIds(),
            ],
            'http_status' => Response::HTTP_NOT_FOUND,
        ];
    }

    /**
     * Handle not found exception.
     */
    protected function handleNotFoundException(NotFoundHttpException $exception): array
    {
        return [
            'code' => self::MCP_ERRORS['RESOURCE_NOT_FOUND'],
            'message' => 'Not found',
            'data' => [
                'type' => 'not_found',
                'message' => $exception->getMessage() ?: 'The requested resource was not found',
            ],
            'http_status' => Response::HTTP_NOT_FOUND,
        ];
    }

    /**
     * Handle HTTP exception.
     */
    protected function handleHttpException(HttpException $exception): array
    {
        $statusCode = $exception->getStatusCode();
        $errorCode = $this->mapHttpStatusToErrorCode($statusCode);

        return [
            'code' => $errorCode,
            'message' => $this->getErrorMessageForHttpStatus($statusCode),
            'data' => [
                'type' => 'http_error',
                'message' => $exception->getMessage(),
                'status_code' => $statusCode,
            ],
            'http_status' => $statusCode,
        ];
    }

    /**
     * Handle MCP-specific exception.
     */
    protected function handleMcpException(\Throwable $exception): array
    {
        $errorCode = self::JSON_RPC_ERRORS['INTERNAL_ERROR'];
        $message = 'MCP error';

        // Extract error code and message from MCP exception
        if (method_exists($exception, 'getErrorCode')) {
            $errorCode = $exception->getErrorCode();
        }

        if (method_exists($exception, 'getErrorMessage')) {
            $message = $exception->getErrorMessage();
        }

        $data = [
            'type' => 'mcp_error',
            'message' => $exception->getMessage(),
        ];

        // Add additional data if available
        if (method_exists($exception, 'getErrorData')) {
            $data = array_merge($data, $exception->getErrorData());
        }

        return [
            'code' => $errorCode,
            'message' => $message,
            'data' => $data,
            'http_status' => $this->getHttpStatusForMcpError($errorCode),
        ];
    }

    /**
     * Handle generic exception.
     */
    protected function handleGenericException(\Throwable $exception): array
    {
        $message = 'Internal error';
        $data = [
            'type' => 'internal_error',
        ];

        // Add debug information in development mode
        if ($this->shouldShowDebugInfo()) {
            $data['message'] = $exception->getMessage();
            $data['exception'] = get_class($exception);
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['trace'] = $this->formatStackTrace($exception);
        } else {
            $data['message'] = 'An internal error occurred';
        }

        return [
            'code' => self::JSON_RPC_ERRORS['INTERNAL_ERROR'],
            'message' => $message,
            'data' => $data,
            'http_status' => Response::HTTP_INTERNAL_SERVER_ERROR,
        ];
    }

    /**
     * Build JSON-RPC error response.
     */
    protected function buildErrorResponse(array $errorData, Request $request): JsonResponse
    {
        $httpStatus = $errorData['http_status'] ?? Response::HTTP_INTERNAL_SERVER_ERROR;
        unset($errorData['http_status']);

        $response = [
            'jsonrpc' => '2.0',
            'error' => $errorData,
            'id' => $this->extractRequestId($request),
        ];

        return response()->json($response, $httpStatus);
    }

    /**
     * Extract request ID from the request.
     */
    protected function extractRequestId(Request $request)
    {
        if ($request->isJson()) {
            return $request->json('id');
        }

        return null;
    }

    /**
     * Check if exception is MCP-specific.
     */
    protected function isMcpException(\Throwable $exception): bool
    {
        // Check if exception is in MCP namespace
        $namespace = (new \ReflectionClass($exception))->getNamespaceName();
        if (str_starts_with($namespace, 'JTD\\LaravelMCP\\')) {
            return true;
        }

        // Check if exception implements MCP error interface
        if ($exception instanceof \JTD\LaravelMCP\Contracts\McpException) {
            return true;
        }

        return false;
    }

    /**
     * Map HTTP status code to JSON-RPC error code.
     */
    protected function mapHttpStatusToErrorCode(int $statusCode): int
    {
        return match ($statusCode) {
            400 => self::JSON_RPC_ERRORS['INVALID_REQUEST'],
            401 => self::MCP_ERRORS['AUTHENTICATION_REQUIRED'],
            403 => self::MCP_ERRORS['PERMISSION_DENIED'],
            404 => self::MCP_ERRORS['RESOURCE_NOT_FOUND'],
            405 => self::JSON_RPC_ERRORS['METHOD_NOT_FOUND'],
            409 => self::MCP_ERRORS['RESOURCE_CONFLICT'],
            422 => self::JSON_RPC_ERRORS['INVALID_PARAMS'],
            429 => self::MCP_ERRORS['RATE_LIMIT_EXCEEDED'],
            500, 503 => self::JSON_RPC_ERRORS['INTERNAL_ERROR'],
            default => self::JSON_RPC_ERRORS['INTERNAL_ERROR'],
        };
    }

    /**
     * Get error message for HTTP status code.
     */
    protected function getErrorMessageForHttpStatus(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Permission denied',
            404 => 'Not found',
            405 => 'Method not allowed',
            409 => 'Conflict',
            422 => 'Unprocessable entity',
            429 => 'Too many requests',
            500 => 'Internal server error',
            503 => 'Service unavailable',
            default => 'Error',
        };
    }

    /**
     * Get HTTP status code for MCP error code.
     */
    protected function getHttpStatusForMcpError(int $errorCode): int
    {
        // Map MCP error codes to HTTP status codes
        if ($errorCode === self::MCP_ERRORS['AUTHENTICATION_REQUIRED']) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($errorCode === self::MCP_ERRORS['PERMISSION_DENIED']) {
            return Response::HTTP_FORBIDDEN;
        }

        if ($errorCode === self::MCP_ERRORS['RESOURCE_NOT_FOUND']) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($errorCode === self::MCP_ERRORS['RESOURCE_CONFLICT']) {
            return Response::HTTP_CONFLICT;
        }

        if ($errorCode === self::MCP_ERRORS['RATE_LIMIT_EXCEEDED']) {
            return Response::HTTP_TOO_MANY_REQUESTS;
        }

        if ($errorCode >= -32099 && $errorCode <= -32000) {
            // Server error range
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        if ($errorCode >= -32768 && $errorCode <= -32000) {
            // Reserved for implementation-defined server errors
            return Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        return Response::HTTP_BAD_REQUEST;
    }

    /**
     * Log exception based on severity.
     */
    protected function logException(\Throwable $exception, Request $request): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'request_id' => $request->header('X-Request-ID'),
            'method' => $request->method(),
            'uri' => $request->getRequestUri(),
            'ip' => $request->ip(),
        ];

        // Add user context if available
        if ($userId = $request->attributes->get('mcp_user_id')) {
            $context['user_id'] = $userId;
        }

        // Add MCP method if available
        if ($request->isJson() && $method = $request->json('method')) {
            $context['mcp_method'] = $method;
        }

        // Determine log level based on exception type
        $logLevel = $this->getLogLevel($exception);

        // Log with appropriate level
        Log::log($logLevel, 'MCP exception handled', $context);

        // Log full stack trace in debug mode
        if ($this->shouldLogStackTrace()) {
            Log::debug('MCP exception stack trace', [
                'trace' => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Determine log level for exception.
     */
    protected function getLogLevel(\Throwable $exception): string
    {
        // Client errors (4xx) should be logged as warning
        if ($exception instanceof ValidationException ||
            $exception instanceof AuthenticationException ||
            $exception instanceof AuthorizationException ||
            $exception instanceof NotFoundHttpException ||
            $exception instanceof ModelNotFoundException) {
            return 'warning';
        }

        // Rate limiting is info level
        if ($exception instanceof HttpException && $exception->getStatusCode() === 429) {
            return 'info';
        }

        // Server errors should be logged as error
        return 'error';
    }

    /**
     * Format stack trace for error response.
     */
    protected function formatStackTrace(\Throwable $exception): array
    {
        $trace = [];
        $frames = array_slice($exception->getTrace(), 0, 10); // Limit to 10 frames

        foreach ($frames as $i => $frame) {
            $trace[] = sprintf(
                '#%d %s%s%s() at %s:%s',
                $i,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0
            );
        }

        return $trace;
    }

    /**
     * Check if debug information should be shown.
     */
    protected function shouldShowDebugInfo(): bool
    {
        return config('app.debug') && config('laravel-mcp.error_handling.show_debug_info', true);
    }

    /**
     * Check if stack trace should be logged.
     */
    protected function shouldLogStackTrace(): bool
    {
        return config('app.debug') && config('laravel-mcp.error_handling.log_stack_trace', true);
    }
}
