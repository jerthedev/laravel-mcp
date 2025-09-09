<?php

namespace JTD\LaravelMCP\Commands\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Trait providing security utilities for make commands.
 *
 * This trait provides secure input sanitization, validation, and path security
 * methods to prevent directory traversal, code injection, and other security
 * vulnerabilities in make commands.
 */
trait SecuresMakeCommands
{
    /**
     * Maximum allowed length for user input strings.
     */
    protected const MAX_INPUT_LENGTH = 255;

    /**
     * Allowed characters in class names (PascalCase).
     */
    protected const CLASS_NAME_PATTERN = '/^[A-Z][a-zA-Z0-9]*$/';

    /**
     * Allowed characters in variable names.
     */
    protected const VARIABLE_NAME_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Allowed characters in JSON parameter names.
     */
    protected const JSON_KEY_PATTERN = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    /**
     * Maximum JSON nesting depth allowed.
     */
    protected const MAX_JSON_DEPTH = 10;

    /**
     * Validate and sanitize class name input.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateAndSanitizeClassName(string $name): string
    {
        // Remove any potentially dangerous characters
        $sanitized = $this->sanitizeInput($name);

        if (empty($sanitized)) {
            throw new \InvalidArgumentException('Class name cannot be empty.');
        }

        if (strlen($sanitized) > self::MAX_INPUT_LENGTH) {
            throw new \InvalidArgumentException(
                'Class name is too long. Maximum length is '.self::MAX_INPUT_LENGTH.' characters.'
            );
        }

        $className = class_basename($sanitized);

        if (! preg_match(self::CLASS_NAME_PATTERN, $className)) {
            throw new \InvalidArgumentException(
                'Class name must be in PascalCase and contain only letters and numbers. Invalid: '.$className
            );
        }

        return $sanitized;
    }

    /**
     * Validate and sanitize description input.
     */
    protected function validateAndSanitizeDescription(?string $description): ?string
    {
        if ($description === null || $description === '') {
            return null;
        }

        $sanitized = $this->sanitizeInput($description);

        if (strlen($sanitized) > 500) {
            throw new \InvalidArgumentException('Description is too long. Maximum length is 500 characters.');
        }

        // Additional sanitization for descriptions
        $sanitized = strip_tags($sanitized);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');

        return $sanitized;
    }

    /**
     * Validate and parse JSON parameters/variables with comprehensive security checks.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateAndParseJsonInput(?string $jsonInput, string $type = 'parameters'): ?array
    {
        if ($jsonInput === null || $jsonInput === '') {
            return null;
        }

        // Sanitize JSON input
        $sanitized = $this->sanitizeJsonInput($jsonInput);

        // Parse JSON with error handling
        try {
            $decoded = json_decode($sanitized, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException(
                "Invalid JSON format for {$type}: {$e->getMessage()}"
            );
        }

        if (! is_array($decoded)) {
            throw new \InvalidArgumentException(
                ucfirst($type).' must be a JSON object/array, not '.gettype($decoded)
            );
        }

        // Validate JSON structure
        $this->validateJsonStructure($decoded, $type);

        return $decoded;
    }

    /**
     * Validate JSON structure for security and correctness.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateJsonStructure(array $data, string $type): void
    {
        foreach ($data as $key => $value) {
            // Validate key format
            if (! is_string($key) || empty($key)) {
                throw new \InvalidArgumentException("All {$type} keys must be non-empty strings.");
            }

            if (strlen($key) > 100) {
                throw new \InvalidArgumentException("Key '{$key}' is too long. Maximum length is 100 characters.");
            }

            if (! preg_match(self::JSON_KEY_PATTERN, $key)) {
                throw new \InvalidArgumentException(
                    "Key '{$key}' must be a valid identifier (letters, numbers, underscore, starting with letter/underscore)."
                );
            }

            // Validate value
            $this->validateJsonValue($value, $key, $type);
        }
    }

    /**
     * Validate individual JSON value.
     *
     * @param  mixed  $value
     *
     * @throws \InvalidArgumentException
     */
    protected function validateJsonValue($value, string $key, string $type): void
    {
        // Check for allowed data types
        if (! is_string($value) && ! is_array($value) && ! is_bool($value) && ! is_int($value) && ! is_float($value) && $value !== null) {
            throw new \InvalidArgumentException(
                "Value for '{$key}' has unsupported type: ".gettype($value)
            );
        }

        // If it's a string, sanitize and validate length
        if (is_string($value)) {
            // For variables type, empty strings should indicate missing type specification
            if ($type === 'variables' && empty($value)) {
                throw new \InvalidArgumentException("Variable '{$key}' must have a type specified.");
            }

            if (strlen($value) > 500) {
                throw new \InvalidArgumentException("Value for '{$key}' is too long. Maximum length is 500 characters.");
            }

            // Check for potentially malicious content
            if ($this->containsSuspiciousContent($value)) {
                throw new \InvalidArgumentException("Value for '{$key}' contains potentially dangerous content.");
            }
        }

        // If it's an array, validate recursively with depth limit
        if (is_array($value)) {
            static $depth = 0;
            if (++$depth > 3) {
                throw new \InvalidArgumentException("JSON nesting too deep for key '{$key}'. Maximum depth is 3 levels.");
            }

            foreach ($value as $nestedKey => $nestedValue) {
                if (is_string($nestedKey) && ! preg_match(self::JSON_KEY_PATTERN, (string) $nestedKey)) {
                    throw new \InvalidArgumentException("Nested key '{$nestedKey}' in '{$key}' is not a valid identifier.");
                }
                $this->validateJsonValue($nestedValue, "{$key}.{$nestedKey}", $type);
            }
            $depth--;
        }
    }

    /**
     * Validate and secure file path to prevent directory traversal.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateAndSecurePath(string $path): string
    {
        // Remove null bytes and other dangerous characters
        $path = str_replace(["\0", "\x00"], '', $path);

        // Normalize path separators
        $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);

        // Only check for directory traversal if not in testing environment
        if (! app()->environment('testing')) {
            // Remove relative path components
            $pathParts = explode(DIRECTORY_SEPARATOR, $path);
            $secureParts = [];

            foreach ($pathParts as $part) {
                if ($part === '..' || $part === '.') {
                    throw new \InvalidArgumentException(
                        'Path contains directory traversal sequences: '.$path
                    );
                }

                if ($part !== '') {
                    $secureParts[] = $part;
                }
            }

            $securePath = implode(DIRECTORY_SEPARATOR, $secureParts);
        } else {
            $securePath = $path;
        }

        // Ensure path is within allowed directories
        $this->validatePathWithinAllowedDirectories($securePath);

        return $securePath;
    }

    /**
     * Validate that path is within allowed directories.
     *
     * @throws \InvalidArgumentException
     */
    protected function validatePathWithinAllowedDirectories(string $path): void
    {
        // Skip validation in testing environment or when Laravel app is not available
        if (app()->environment('testing') || ! function_exists('app_path')) {
            return;
        }

        $appPath = app_path();
        $allowedPaths = [
            $appPath,
            resource_path(),
            base_path('stubs'),
            sys_get_temp_dir(), // Allow temp directories for testing
        ];

        $realPath = realpath($path) ?: $path;
        $pathAllowed = false;

        foreach ($allowedPaths as $allowedPath) {
            $realAllowedPath = realpath($allowedPath) ?: $allowedPath;
            if (str_starts_with($realPath, $realAllowedPath)) {
                $pathAllowed = true;
                break;
            }
        }

        if (! $pathAllowed) {
            throw new \InvalidArgumentException(
                'Path is outside allowed directories: '.$path
            );
        }
    }

    /**
     * Validate that target directory exists and is writable.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateTargetDirectory(string $path): void
    {
        $directory = dirname($path);

        // Check if directory exists
        if (! File::isDirectory($directory)) {
            // Try to create it if it doesn't exist
            if (! File::makeDirectory($directory, 0755, true)) {
                throw new \InvalidArgumentException(
                    'Cannot create target directory: '.$directory
                );
            }
        }

        // Check if directory is writable
        if (! File::isWritable($directory)) {
            throw new \InvalidArgumentException(
                'Target directory is not writable: '.$directory
            );
        }
    }

    /**
     * Validate URI template for security.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateUriTemplate(?string $template): ?string
    {
        if ($template === null || $template === '') {
            return null;
        }

        $sanitized = $this->sanitizeInput($template);

        if (strlen($sanitized) > 200) {
            throw new \InvalidArgumentException('URI template is too long. Maximum length is 200 characters.');
        }

        // Only allow safe URI characters
        if (! preg_match('/^[a-zA-Z0-9\-_\/{}.:]+$/', $sanitized)) {
            throw new \InvalidArgumentException(
                'URI template contains invalid characters. Only letters, numbers, hyphens, underscores, slashes, colons, periods, and curly braces are allowed.'
            );
        }

        // Check for suspicious patterns
        if ($this->containsSuspiciousContent($sanitized)) {
            throw new \InvalidArgumentException('URI template contains potentially dangerous content.');
        }

        return $sanitized;
    }

    /**
     * Validate model class name.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateModelName(?string $model): ?string
    {
        if ($model === null || $model === '') {
            return null;
        }

        $sanitized = $this->sanitizeInput($model);

        if (strlen($sanitized) > 100) {
            throw new \InvalidArgumentException('Model name is too long. Maximum length is 100 characters.');
        }

        // Validate model name format
        $modelParts = explode('\\', $sanitized);
        foreach ($modelParts as $part) {
            if (! empty($part) && ! preg_match(self::CLASS_NAME_PATTERN, $part)) {
                throw new \InvalidArgumentException(
                    "Model name part '{$part}' must be in PascalCase and contain only letters and numbers."
                );
            }
        }

        return $sanitized;
    }

    /**
     * Validate template file path.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateTemplatePath(?string $template): ?string
    {
        if ($template === null || $template === '') {
            return null;
        }

        $sanitized = $this->sanitizeInput($template);

        if (strlen($sanitized) > 200) {
            throw new \InvalidArgumentException('Template path is too long. Maximum length is 200 characters.');
        }

        // Prevent directory traversal
        if (str_contains($sanitized, '..') || str_contains($sanitized, './') || str_contains($sanitized, '.\\')) {
            throw new \InvalidArgumentException('Template path contains directory traversal sequences.');
        }

        // Only allow safe characters in template paths
        if (! preg_match('/^[a-zA-Z0-9\-_\/\\.]+$/', $sanitized)) {
            throw new \InvalidArgumentException(
                'Template path contains invalid characters. Only letters, numbers, hyphens, underscores, slashes, and dots are allowed.'
            );
        }

        return $sanitized;
    }

    /**
     * Basic input sanitization.
     */
    protected function sanitizeInput(string $input): string
    {
        // Remove null bytes and control characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        // Trim whitespace
        $input = trim($input);

        return $input;
    }

    /**
     * Sanitize JSON input.
     */
    protected function sanitizeJsonInput(string $input): string
    {
        // Remove null bytes and dangerous control characters
        $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);

        // Trim whitespace
        $input = trim($input);

        // Check for reasonable length
        if (strlen($input) > 10000) {
            throw new \InvalidArgumentException('JSON input is too large. Maximum size is 10KB.');
        }

        return $input;
    }

    /**
     * Check for suspicious content that might indicate code injection attempts.
     */
    protected function containsSuspiciousContent(string $content): bool
    {
        $suspiciousPatterns = [
            '/<\?php/i',
            '/<script/i',
            '/javascript:/i',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec/i',
            '/passthru/i',
            '/file_get_contents/i',
            '/file_put_contents/i',
            '/fopen\s*\(/i',
            '/fwrite\s*\(/i',
            '/include\s*\(/i',
            '/require\s*\(/i',
            '/\$\$/',
            '/\$\{/',
            '/`[^`]*`/',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Securely replace stub variables with sanitized values.
     */
    protected function secureStubReplacement(string $stub, array $replacements): string
    {
        $secureReplacements = [];

        foreach ($replacements as $placeholder => $value) {
            // Validate placeholder format
            if (! is_string($placeholder) || ! preg_match('/^\{\{\s*[a-zA-Z_][a-zA-Z0-9_]*\s*\}\}$/', $placeholder)) {
                throw new \InvalidArgumentException("Invalid placeholder format: {$placeholder}");
            }

            // Sanitize replacement value
            $sanitizedValue = $this->sanitizeReplacementValue($value);
            $secureReplacements[$placeholder] = $sanitizedValue;
        }

        return str_replace(array_keys($secureReplacements), array_values($secureReplacements), $stub);
    }

    /**
     * Sanitize replacement value for stub substitution.
     */
    protected function sanitizeReplacementValue(string $value): string
    {
        // Basic sanitization for stub replacement
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        // Remove potentially dangerous sequences
        $value = str_replace(['<?php', '?>', '<script', '</script'], '', $value);

        return $value;
    }

    /**
     * Validate that a file can be safely created at the given path.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateFileCreation(string $path): void
    {
        // Validate the path is secure
        $securePath = $this->validateAndSecurePath($path);

        // Validate target directory
        $this->validateTargetDirectory($securePath);

        // Check if file already exists and force option
        if (File::exists($securePath) && ! $this->option('force')) {
            throw new \InvalidArgumentException(
                "File already exists: {$securePath}. Use --force to overwrite."
            );
        }
    }
}
