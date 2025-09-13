<?php

namespace JTD\LaravelMCP\Registry;

/**
 * Enhanced URI pattern matcher for MCP resources.
 *
 * This class provides advanced URI pattern matching capabilities
 * for MCP resources, supporting complex patterns with parameters,
 * wildcards, and regular expressions.
 */
class UriMatcher
{
    /**
     * Pattern cache for performance.
     */
    private array $compiledPatterns = [];

    /**
     * Match a URI against a pattern.
     *
     * @param  string  $uri  The URI to match
     * @param  string  $pattern  The pattern to match against
     * @return array|false Extracted parameters or false if no match
     */
    public function match(string $uri, string $pattern): array|false
    {
        // Get or compile the pattern
        $regex = $this->compilePattern($pattern);

        // Perform the match
        if (preg_match($regex, $uri, $matches)) {
            // Extract named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return $params ?: [];
        }

        return false;
    }

    /**
     * Match a URI against multiple patterns.
     *
     * @param  string  $uri  The URI to match
     * @param  array  $patterns  Array of patterns to try
     * @return array|false First matching pattern's parameters or false
     */
    public function matchAny(string $uri, array $patterns): array|false
    {
        foreach ($patterns as $pattern) {
            $result = $this->match($uri, $pattern);
            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    /**
     * Generate a URI from a pattern and parameters.
     *
     * @param  string  $pattern  The pattern template
     * @param  array  $params  Parameters to inject
     * @return string Generated URI
     */
    public function generate(string $pattern, array $params = []): string
    {
        $uri = $pattern;

        // Replace named parameters
        foreach ($params as $key => $value) {
            // Support multiple parameter formats
            $uri = str_replace([
                "{{$key}}",           // {param}
                "{{{$key}}}",         // {{param}}
                ":{$key}",            // :param
                "*{$key}",            // *param
            ], $value, $uri);
        }

        // Remove any remaining optional parameters
        $uri = preg_replace('/\{[^}]*\?\}/', '', $uri);

        // Remove any remaining required parameters (they become literals)
        $uri = preg_replace('/\{([^}]*)\}/', '$1', $uri);

        return $uri;
    }

    /**
     * Compile a pattern into a regular expression.
     *
     * @param  string  $pattern  The pattern to compile
     * @return string Compiled regex pattern
     */
    private function compilePattern(string $pattern): string
    {
        // Check cache
        if (isset($this->compiledPatterns[$pattern])) {
            return $this->compiledPatterns[$pattern];
        }

        // Escape special regex characters except our pattern syntax
        $regex = preg_quote($pattern, '#');

        // Convert pattern syntax to regex
        $replacements = [
            // Named parameters: {name}, {id}
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\}/' => '(?P<$1>[^/]+)',

            // Optional parameters: {name?}
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*)\\\\\?\\\\\}/' => '(?P<$1>[^/]*)?',

            // Typed parameters: {id:int}, {slug:alpha}
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*):int\\\\\}/' => '(?P<$1>\d+)',
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*):alpha\\\\\}/' => '(?P<$1>[a-zA-Z]+)',
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*):alphanum\\\\\}/' => '(?P<$1>[a-zA-Z0-9]+)',
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*):slug\\\\\}/' => '(?P<$1>[a-zA-Z0-9\-_]+)',
            '/\\\\\{([a-zA-Z_][a-zA-Z0-9_]*):uuid\\\\\}/' => '(?P<$1>[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})',

            // Wildcards: * and **
            '/\\\\\*\\\\\*/' => '.*',      // ** matches any characters including /
            '/\\\\\*/' => '[^/]*',         // * matches any characters except /

            // Path parameters (Laravel style): :param
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/' => '(?P<$1>[^/]+)',
        ];

        foreach ($replacements as $search => $replace) {
            $regex = preg_replace($search, $replace, $regex);
        }

        // Wrap in delimiters and anchors
        $regex = '#^'.$regex.'$#';

        // Cache the compiled pattern
        $this->compiledPatterns[$pattern] = $regex;

        return $regex;
    }

    /**
     * Extract all parameter names from a pattern.
     *
     * @param  string  $pattern  The pattern to analyze
     * @return array Parameter names
     */
    public function extractParameterNames(string $pattern): array
    {
        $params = [];

        // Match all parameter patterns
        $patterns = [
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(:[a-zA-Z]+)?\??}/',  // {param}, {param:type}, {param?}
            '/:([a-zA-Z_][a-zA-Z0-9_]*)/',                      // :param
        ];

        foreach ($patterns as $regex) {
            if (preg_match_all($regex, $pattern, $matches)) {
                $params = array_merge($params, $matches[1]);
            }
        }

        return array_unique($params);
    }

    /**
     * Validate that a URI matches the expected pattern constraints.
     *
     * @param  string  $uri  The URI to validate
     * @param  string  $pattern  The pattern with constraints
     * @param  array  $constraints  Additional constraints
     * @return bool True if valid
     */
    public function validate(string $uri, string $pattern, array $constraints = []): bool
    {
        $params = $this->match($uri, $pattern);

        if ($params === false) {
            return false;
        }

        // Apply additional constraints
        foreach ($constraints as $param => $constraint) {
            if (! isset($params[$param])) {
                continue;
            }

            $value = $params[$param];

            // Handle different constraint types
            if (is_callable($constraint)) {
                if (! $constraint($value)) {
                    return false;
                }
            } elseif (is_string($constraint)) {
                // Assume it's a regex pattern
                if (! preg_match($constraint, $value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Score how well a URI matches a pattern.
     *
     * Higher scores indicate better matches. Useful for finding
     * the best matching pattern from multiple candidates.
     *
     * @param  string  $uri  The URI to score
     * @param  string  $pattern  The pattern to match against
     * @return int Match score (0 = no match, higher = better match)
     */
    public function score(string $uri, string $pattern): int
    {
        $params = $this->match($uri, $pattern);

        if ($params === false) {
            return 0;
        }

        $score = 100; // Base score for matching

        // Exact match gets highest score
        if ($uri === $pattern) {
            return 1000;
        }

        // More specific patterns get higher scores
        $wildcardCount = substr_count($pattern, '*');
        $paramCount = count($params);

        $score -= ($wildcardCount * 10);  // Penalize wildcards
        $score -= ($paramCount * 5);      // Penalize parameters

        // Bonus for matching more of the literal parts
        $literalParts = preg_split('/[\{\}\*:]/', $pattern);
        $literalMatches = 0;

        foreach ($literalParts as $part) {
            if (! empty($part) && str_contains($uri, $part)) {
                $literalMatches++;
            }
        }

        $score += ($literalMatches * 15);

        return max(1, $score); // Ensure score is at least 1 for any match
    }

    /**
     * Find the best matching pattern for a URI.
     *
     * @param  string  $uri  The URI to match
     * @param  array  $patterns  Array of patterns to try
     * @return string|null Best matching pattern or null
     */
    public function findBestMatch(string $uri, array $patterns): ?string
    {
        $bestPattern = null;
        $bestScore = 0;

        foreach ($patterns as $pattern) {
            $score = $this->score($uri, $pattern);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestPattern = $pattern;
            }
        }

        return $bestPattern;
    }

    /**
     * Convert a Laravel route pattern to MCP pattern.
     *
     * @param  string  $laravelPattern  Laravel route pattern
     * @return string MCP pattern
     */
    public function fromLaravelPattern(string $laravelPattern): string
    {
        // Convert Laravel {param} to MCP {param}
        // Convert Laravel {param?} to MCP {param?}
        // Laravel patterns are already compatible
        return $laravelPattern;
    }

    /**
     * Convert an MCP pattern to Laravel route pattern.
     *
     * @param  string  $mcpPattern  MCP pattern
     * @return string Laravel pattern
     */
    public function toLaravelPattern(string $mcpPattern): string
    {
        // Convert typed parameters to plain parameters
        $pattern = preg_replace('/\{([^:}]+):[^}]+\}/', '{$1}', $mcpPattern);

        // Convert :param to {param}
        $pattern = preg_replace('/:([a-zA-Z_][a-zA-Z0-9_]*)/', '{$1}', $pattern);

        // Convert ** to {any} where
        $pattern = str_replace('**', '{any}', $pattern);

        // Convert * to {segment}
        $pattern = str_replace('*', '{segment}', $pattern);

        return $pattern;
    }

    /**
     * Clear the pattern cache.
     */
    public function clearCache(): void
    {
        $this->compiledPatterns = [];
    }
}
