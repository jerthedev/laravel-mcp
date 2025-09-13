<?php

namespace JTD\LaravelMCP\Exceptions;

/**
 * Error codes for registration-related exceptions.
 *
 * This class provides standardized error codes for all registration
 * operations, making it easier to handle and debug registration failures.
 */
class RegistrationErrorCodes
{
    // Component registration errors (REG0xx)
    public const COMPONENT_ALREADY_EXISTS = 'REG001';

    public const INVALID_HANDLER = 'REG002';

    public const HANDLER_CLASS_NOT_FOUND = 'REG003';

    public const HANDLER_INTERFACE_MISMATCH = 'REG004';

    public const COMPONENT_NAME_EMPTY = 'REG005';

    public const INVALID_COMPONENT_TYPE = 'REG006';

    public const REGISTRATION_LOCKED = 'REG007';

    public const DUPLICATE_ALIAS = 'REG008';

    public const CIRCULAR_ALIAS = 'REG009';

    // Discovery errors (DISC0xx)
    public const DISCOVERY_FAILED = 'DISC001';

    public const DISCOVERY_PATH_NOT_FOUND = 'DISC002';

    public const DISCOVERY_CLASS_EXTRACTION_FAILED = 'DISC003';

    public const DISCOVERY_INVALID_COMPONENT = 'DISC004';

    public const DISCOVERY_CACHE_FAILED = 'DISC005';

    // Validation errors (VAL0xx)
    public const VALIDATION_FAILED = 'VAL001';

    public const STRICT_MODE_VIOLATION = 'VAL002';

    public const INVALID_METADATA = 'VAL003';

    public const MISSING_REQUIRED_FIELD = 'VAL004';

    public const INVALID_OPTIONS = 'VAL005';

    // Resource routing errors (ROUTE0xx)
    public const ROUTE_PATTERN_INVALID = 'ROUTE001';

    public const ROUTE_CONFLICT = 'ROUTE002';

    public const ROUTE_NOT_FOUND = 'ROUTE003';

    public const ROUTE_PARAMETER_MISSING = 'ROUTE004';

    // Middleware errors (MW0xx)
    public const MIDDLEWARE_NOT_FOUND = 'MW001';

    public const MIDDLEWARE_EXECUTION_FAILED = 'MW002';

    public const MIDDLEWARE_INVALID_RESPONSE = 'MW003';

    // Cache errors (CACHE0xx)
    public const CACHE_WRITE_FAILED = 'CACHE001';

    public const CACHE_READ_FAILED = 'CACHE002';

    public const CACHE_INVALIDATION_FAILED = 'CACHE003';

    // Lock errors (LOCK0xx)
    public const LOCK_ACQUISITION_FAILED = 'LOCK001';

    public const LOCK_RELEASE_FAILED = 'LOCK002';

    public const LOCK_TIMEOUT = 'LOCK003';

    // Hook errors (HOOK0xx)
    public const HOOK_EXECUTION_FAILED = 'HOOK001';

    public const HOOK_NOT_FOUND = 'HOOK002';

    public const HOOK_INVALID_RESPONSE = 'HOOK003';

    /**
     * Get the error message for a given error code.
     */
    public static function getMessage(string $code): string
    {
        return match ($code) {
            self::COMPONENT_ALREADY_EXISTS => 'Component with this name already exists',
            self::INVALID_HANDLER => 'Invalid handler provided for component',
            self::HANDLER_CLASS_NOT_FOUND => 'Handler class does not exist',
            self::HANDLER_INTERFACE_MISMATCH => 'Handler does not implement required interface',
            self::COMPONENT_NAME_EMPTY => 'Component name cannot be empty',
            self::INVALID_COMPONENT_TYPE => 'Invalid component type specified',
            self::REGISTRATION_LOCKED => 'Registration is locked and cannot be modified',
            self::DUPLICATE_ALIAS => 'Alias already exists for another component',
            self::CIRCULAR_ALIAS => 'Circular alias reference detected',

            self::DISCOVERY_FAILED => 'Component discovery failed',
            self::DISCOVERY_PATH_NOT_FOUND => 'Discovery path does not exist',
            self::DISCOVERY_CLASS_EXTRACTION_FAILED => 'Failed to extract class from file',
            self::DISCOVERY_INVALID_COMPONENT => 'Discovered component is invalid',
            self::DISCOVERY_CACHE_FAILED => 'Failed to cache discovery results',

            self::VALIDATION_FAILED => 'Component validation failed',
            self::STRICT_MODE_VIOLATION => 'Strict mode validation failed',
            self::INVALID_METADATA => 'Invalid metadata provided',
            self::MISSING_REQUIRED_FIELD => 'Required field is missing',
            self::INVALID_OPTIONS => 'Invalid options provided',

            self::ROUTE_PATTERN_INVALID => 'Invalid route pattern',
            self::ROUTE_CONFLICT => 'Route conflicts with existing route',
            self::ROUTE_NOT_FOUND => 'Route not found',
            self::ROUTE_PARAMETER_MISSING => 'Required route parameter is missing',

            self::MIDDLEWARE_NOT_FOUND => 'Middleware not found',
            self::MIDDLEWARE_EXECUTION_FAILED => 'Middleware execution failed',
            self::MIDDLEWARE_INVALID_RESPONSE => 'Middleware returned invalid response',

            self::CACHE_WRITE_FAILED => 'Failed to write to cache',
            self::CACHE_READ_FAILED => 'Failed to read from cache',
            self::CACHE_INVALIDATION_FAILED => 'Failed to invalidate cache',

            self::LOCK_ACQUISITION_FAILED => 'Failed to acquire lock',
            self::LOCK_RELEASE_FAILED => 'Failed to release lock',
            self::LOCK_TIMEOUT => 'Lock acquisition timed out',

            self::HOOK_EXECUTION_FAILED => 'Hook execution failed',
            self::HOOK_NOT_FOUND => 'Hook not found',
            self::HOOK_INVALID_RESPONSE => 'Hook returned invalid response',

            default => 'Unknown error occurred',
        };
    }

    /**
     * Get the severity level for a given error code.
     */
    public static function getSeverity(string $code): string
    {
        return match (substr($code, 0, -3)) {
            'REG', 'DISC' => 'error',
            'VAL', 'ROUTE' => 'warning',
            'MW', 'HOOK' => 'warning',
            'CACHE' => 'notice',
            'LOCK' => 'critical',
            default => 'info',
        };
    }

    /**
     * Check if an error is recoverable.
     */
    public static function isRecoverable(string $code): bool
    {
        return ! in_array($code, [
            self::HANDLER_CLASS_NOT_FOUND,
            self::HANDLER_INTERFACE_MISMATCH,
            self::CIRCULAR_ALIAS,
            self::LOCK_TIMEOUT,
        ]);
    }
}
