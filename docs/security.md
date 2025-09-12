# Security Best Practices

This guide provides comprehensive security guidelines for implementing and deploying Laravel MCP servers. Follow these practices to ensure your MCP implementation is secure and protected against common vulnerabilities.

## Table of Contents

1. [Authentication and Authorization](#authentication-and-authorization)
2. [Input Validation and Sanitization](#input-validation-and-sanitization)
3. [API Security](#api-security)
4. [Data Protection](#data-protection)
5. [Secure Communication](#secure-communication)
6. [Rate Limiting and DDoS Protection](#rate-limiting-and-ddos-protection)
7. [Audit Logging](#audit-logging)
8. [Security Headers](#security-headers)
9. [Dependency Management](#dependency-management)
10. [Security Testing](#security-testing)

## Authentication and Authorization

### Multi-Layer Authentication

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class McpAuthenticator
{
    protected array $authMethods = ['api_key', 'jwt', 'oauth2'];
    
    public function authenticate(Request $request): ?array
    {
        // Try each authentication method
        foreach ($this->authMethods as $method) {
            $result = $this->tryAuthentication($method, $request);
            if ($result !== null) {
                return $result;
            }
        }
        
        throw new \Exception('Authentication failed');
    }
    
    protected function tryAuthentication(string $method, Request $request): ?array
    {
        return match($method) {
            'api_key' => $this->authenticateApiKey($request),
            'jwt' => $this->authenticateJwt($request),
            'oauth2' => $this->authenticateOAuth2($request),
            default => null,
        };
    }
    
    protected function authenticateApiKey(Request $request): ?array
    {
        $apiKey = $request->header('X-API-Key') ?? $request->input('api_key');
        
        if (!$apiKey) {
            return null;
        }
        
        // Constant-time comparison to prevent timing attacks
        $user = \App\Models\ApiKey::where('key_hash', hash('sha256', $apiKey))
            ->where('active', true)
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$user) {
            return null;
        }
        
        // Update last used timestamp
        $user->update(['last_used_at' => now()]);
        
        return [
            'user_id' => $user->user_id,
            'scopes' => $user->scopes,
            'method' => 'api_key',
        ];
    }
    
    protected function authenticateJwt(Request $request): ?array
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        try {
            $decoded = JWT::decode(
                $token,
                new Key(config('mcp.jwt_secret'), 'HS256')
            );
            
            // Verify token claims
            if ($decoded->exp < time()) {
                return null; // Token expired
            }
            
            if ($decoded->iss !== config('app.url')) {
                return null; // Invalid issuer
            }
            
            return [
                'user_id' => $decoded->sub,
                'scopes' => $decoded->scopes ?? [],
                'method' => 'jwt',
            ];
        } catch (\Exception $e) {
            \Log::warning('JWT authentication failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    protected function authenticateOAuth2(Request $request): ?array
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return null;
        }
        
        // Verify with OAuth2 provider
        $user = \Laravel\Passport\Token::find($token);
        
        if (!$user || $user->revoked) {
            return null;
        }
        
        return [
            'user_id' => $user->user_id,
            'scopes' => $user->scopes,
            'method' => 'oauth2',
        ];
    }
}
```

### Role-Based Access Control (RBAC)

```php
<?php

namespace App\Mcp\Security;

class McpAuthorization
{
    protected array $permissions = [
        'admin' => ['*'],
        'developer' => ['tools.*', 'resources.read', 'prompts.*'],
        'user' => ['tools.execute', 'resources.read'],
        'readonly' => ['resources.read'],
    ];
    
    public function authorize(array $auth, string $action, $resource = null): bool
    {
        $userRole = $this->getUserRole($auth['user_id']);
        $permissions = $this->permissions[$userRole] ?? [];
        
        // Check for wildcard permission
        if (in_array('*', $permissions)) {
            return true;
        }
        
        // Check specific permission
        if (in_array($action, $permissions)) {
            return true;
        }
        
        // Check pattern matching (e.g., 'tools.*')
        foreach ($permissions as $permission) {
            if (fnmatch($permission, $action)) {
                return true;
            }
        }
        
        // Check resource-specific permissions
        if ($resource !== null) {
            return $this->checkResourcePermission($auth, $action, $resource);
        }
        
        return false;
    }
    
    protected function getUserRole(int $userId): string
    {
        return \App\Models\User::find($userId)->role ?? 'user';
    }
    
    protected function checkResourcePermission(array $auth, string $action, $resource): bool
    {
        // Check if user owns the resource
        if (method_exists($resource, 'getUserId') && $resource->getUserId() === $auth['user_id']) {
            return true;
        }
        
        // Check team permissions
        if (method_exists($resource, 'getTeamId')) {
            $userTeams = \App\Models\TeamMember::where('user_id', $auth['user_id'])
                ->pluck('team_id')
                ->toArray();
            
            if (in_array($resource->getTeamId(), $userTeams)) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Secure Session Management

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Support\Facades\Cache;

class SecureSessionManager
{
    protected int $sessionTimeout = 3600; // 1 hour
    protected int $maxConcurrentSessions = 3;
    
    public function createSession(int $userId, array $metadata = []): string
    {
        // Generate secure session ID
        $sessionId = bin2hex(random_bytes(32));
        
        // Check concurrent sessions
        $this->enforceSessionLimit($userId);
        
        // Store session data
        $sessionData = [
            'user_id' => $userId,
            'created_at' => time(),
            'last_activity' => time(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ];
        
        Cache::put(
            "session:{$sessionId}",
            $sessionData,
            $this->sessionTimeout
        );
        
        // Track user sessions
        $this->trackUserSession($userId, $sessionId);
        
        return $sessionId;
    }
    
    public function validateSession(string $sessionId): ?array
    {
        $session = Cache::get("session:{$sessionId}");
        
        if (!$session) {
            return null;
        }
        
        // Check session timeout
        if (time() - $session['last_activity'] > $this->sessionTimeout) {
            $this->destroySession($sessionId);
            return null;
        }
        
        // Validate IP address (optional, may cause issues with mobile users)
        if (config('mcp.security.validate_ip') && $session['ip_address'] !== request()->ip()) {
            \Log::warning('Session IP mismatch', [
                'session_id' => $sessionId,
                'expected_ip' => $session['ip_address'],
                'actual_ip' => request()->ip(),
            ]);
            $this->destroySession($sessionId);
            return null;
        }
        
        // Update last activity
        $session['last_activity'] = time();
        Cache::put("session:{$sessionId}", $session, $this->sessionTimeout);
        
        return $session;
    }
    
    protected function enforceSessionLimit(int $userId): void
    {
        $sessions = Cache::get("user_sessions:{$userId}", []);
        
        if (count($sessions) >= $this->maxConcurrentSessions) {
            // Remove oldest session
            $oldestSession = array_shift($sessions);
            $this->destroySession($oldestSession);
        }
    }
    
    protected function trackUserSession(int $userId, string $sessionId): void
    {
        $sessions = Cache::get("user_sessions:{$userId}", []);
        $sessions[] = $sessionId;
        Cache::put("user_sessions:{$userId}", $sessions, 86400);
    }
    
    public function destroySession(string $sessionId): void
    {
        $session = Cache::get("session:{$sessionId}");
        
        if ($session) {
            // Remove from user sessions
            $userSessions = Cache::get("user_sessions:{$session['user_id']}", []);
            $userSessions = array_diff($userSessions, [$sessionId]);
            Cache::put("user_sessions:{$session['user_id']}", $userSessions, 86400);
        }
        
        Cache::forget("session:{$sessionId}");
    }
}
```

## Input Validation and Sanitization

### Comprehensive Input Validation

```php
<?php

namespace App\Mcp\Validation;

use Illuminate\Support\Facades\Validator;

class InputValidator
{
    protected array $rules = [
        'tools/call' => [
            'name' => 'required|string|max:255|regex:/^[a-z0-9_]+$/',
            'arguments' => 'required|array',
        ],
        'resources/read' => [
            'uri' => 'required|string|max:1000',
        ],
        'prompts/get' => [
            'name' => 'required|string|max:255',
            'arguments' => 'array',
        ],
    ];
    
    public function validate(string $method, array $params): array
    {
        $rules = $this->rules[$method] ?? [];
        
        $validator = Validator::make($params, $rules);
        
        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                'Validation failed: ' . $validator->errors()->first()
            );
        }
        
        // Additional security validation
        $this->validateSecurity($params);
        
        // Sanitize input
        return $this->sanitize($params);
    }
    
    protected function validateSecurity(array $params): void
    {
        foreach ($params as $key => $value) {
            // Check for SQL injection patterns
            if (is_string($value)) {
                $this->checkSqlInjection($value);
                $this->checkXss($value);
                $this->checkPathTraversal($value);
            }
            
            // Recursive validation for arrays
            if (is_array($value)) {
                $this->validateSecurity($value);
            }
        }
    }
    
    protected function checkSqlInjection(string $value): void
    {
        $patterns = [
            '/\b(UNION|SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|EXECUTE)\b/i',
            '/(\-\-|\/\*|\*\/|;|\||\'|\")/',
            '/\b(OR|AND)\s+\d+\s*=\s*\d+/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new \InvalidArgumentException('Potential SQL injection detected');
            }
        }
    }
    
    protected function checkXss(string $value): void
    {
        $patterns = [
            '/<script[^>]*>.*?<\/script>/is',
            '/<iframe[^>]*>.*?<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new \InvalidArgumentException('Potential XSS attack detected');
            }
        }
    }
    
    protected function checkPathTraversal(string $value): void
    {
        $patterns = [
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%252e%252e%252f/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                throw new \InvalidArgumentException('Path traversal attempt detected');
            }
        }
    }
    
    protected function sanitize(array $params): array
    {
        array_walk_recursive($params, function (&$value) {
            if (is_string($value)) {
                // Remove null bytes
                $value = str_replace(chr(0), '', $value);
                
                // Trim whitespace
                $value = trim($value);
                
                // HTML encode special characters
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
        });
        
        return $params;
    }
}
```

### File Upload Security

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Http\UploadedFile;

class FileUploadValidator
{
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/pdf',
        'text/plain',
        'application/json',
    ];
    
    protected array $blockedExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7',
        'exe', 'bat', 'cmd', 'sh', 'cgi',
        'htaccess', 'htpasswd',
    ];
    
    protected int $maxFileSize = 10485760; // 10MB
    
    public function validate(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > $this->maxFileSize) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed');
        }
        
        // Validate MIME type
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException('File type not allowed');
        }
        
        // Check file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, $this->blockedExtensions)) {
            throw new \InvalidArgumentException('File extension not allowed');
        }
        
        // Verify actual file content matches MIME type
        $this->verifyFileContent($file);
        
        // Scan for malware (if configured)
        if (config('mcp.security.malware_scanning')) {
            $this->scanForMalware($file);
        }
    }
    
    protected function verifyFileContent(UploadedFile $file): void
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $file->getRealPath());
        finfo_close($finfo);
        
        if ($detectedMime !== $file->getMimeType()) {
            throw new \InvalidArgumentException('File content does not match declared type');
        }
        
        // Additional content verification for images
        if (str_starts_with($detectedMime, 'image/')) {
            $this->verifyImageContent($file);
        }
    }
    
    protected function verifyImageContent(UploadedFile $file): void
    {
        $imageInfo = @getimagesize($file->getRealPath());
        
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Invalid image file');
        }
        
        // Check for embedded PHP in EXIF data
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($file->getRealPath());
            if ($exif) {
                foreach ($exif as $key => $value) {
                    if (is_string($value) && preg_match('/<\?php/i', $value)) {
                        throw new \InvalidArgumentException('Malicious content detected in image metadata');
                    }
                }
            }
        }
    }
    
    protected function scanForMalware(UploadedFile $file): void
    {
        // Integration with ClamAV or other antivirus
        $scanner = new \Xenolope\Quahog\Client(
            config('mcp.security.clamav_socket', '/var/run/clamav/clamd.ctl')
        );
        
        $result = $scanner->scanFile($file->getRealPath());
        
        if ($result->isInfected()) {
            throw new \InvalidArgumentException('Malware detected in uploaded file');
        }
    }
}
```

## API Security

### API Rate Limiting

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class ApiRateLimiter
{
    protected array $limits = [
        'global' => ['attempts' => 1000, 'decay' => 60],
        'tools/call' => ['attempts' => 100, 'decay' => 60],
        'resources/read' => ['attempts' => 500, 'decay' => 60],
        'prompts/get' => ['attempts' => 200, 'decay' => 60],
    ];
    
    public function check(Request $request, string $method): bool
    {
        $key = $this->resolveKey($request, $method);
        $limits = $this->getLimits($method);
        
        if (RateLimiter::tooManyAttempts($key, $limits['attempts'])) {
            $this->logRateLimitExceeded($request, $method);
            return false;
        }
        
        RateLimiter::hit($key, $limits['decay']);
        
        return true;
    }
    
    protected function resolveKey(Request $request, string $method): string
    {
        $identifier = $this->getIdentifier($request);
        return "mcp:{$identifier}:{$method}";
    }
    
    protected function getIdentifier(Request $request): string
    {
        // Use authenticated user ID if available
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }
        
        // Use API key if present
        if ($apiKey = $request->header('X-API-Key')) {
            return 'api:' . substr($apiKey, 0, 8);
        }
        
        // Fall back to IP address
        return 'ip:' . $request->ip();
    }
    
    protected function getLimits(string $method): array
    {
        return $this->limits[$method] ?? $this->limits['global'];
    }
    
    protected function logRateLimitExceeded(Request $request, string $method): void
    {
        \Log::warning('Rate limit exceeded', [
            'method' => $method,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
        ]);
        
        // Trigger alert for suspicious activity
        if ($this->isSuspicious($request)) {
            $this->alertSecurity($request, $method);
        }
    }
    
    protected function isSuspicious(Request $request): bool
    {
        $key = 'suspicious:' . $request->ip();
        $attempts = Cache::increment($key);
        Cache::expire($key, 3600);
        
        return $attempts > 10; // More than 10 rate limit violations in an hour
    }
    
    protected function alertSecurity(Request $request, string $method): void
    {
        // Send notification to security team
        \Notification::route('mail', config('mcp.security.alert_email'))
            ->notify(new \App\Notifications\SecurityAlert([
                'type' => 'rate_limit_abuse',
                'ip' => $request->ip(),
                'method' => $method,
                'timestamp' => now(),
            ]));
    }
}
```

### CORS Configuration

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class McpCorsMiddleware
{
    protected array $allowedOrigins = [];
    protected array $allowedMethods = ['POST', 'OPTIONS'];
    protected array $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'X-API-Key',
        'X-Request-ID',
    ];
    
    public function __construct()
    {
        $this->allowedOrigins = config('mcp.security.allowed_origins', []);
    }
    
    public function handle(Request $request, Closure $next)
    {
        $origin = $request->header('Origin');
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return response()->json(['error' => 'Origin not allowed'], 403);
        }
        
        // Handle preflight requests
        if ($request->isMethod('OPTIONS')) {
            return $this->handlePreflight($request);
        }
        
        $response = $next($request);
        
        // Add CORS headers
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        return $response;
    }
    
    protected function isOriginAllowed(?string $origin): bool
    {
        if (!$origin) {
            return false;
        }
        
        // Check exact match
        if (in_array($origin, $this->allowedOrigins)) {
            return true;
        }
        
        // Check wildcard patterns
        foreach ($this->allowedOrigins as $allowed) {
            if (fnmatch($allowed, $origin)) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function handlePreflight(Request $request)
    {
        return response('', 204)
            ->header('Access-Control-Allow-Origin', $request->header('Origin'))
            ->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->header('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->header('Access-Control-Max-Age', '86400');
    }
}
```

## Data Protection

### Encryption at Rest

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Support\Facades\Crypt;

class DataEncryption
{
    protected array $encryptedFields = [
        'api_keys' => ['key'],
        'user_credentials' => ['password', 'secret'],
        'mcp_audit_logs' => ['params', 'response'],
    ];
    
    public function encryptData(string $table, array $data): array
    {
        if (!isset($this->encryptedFields[$table])) {
            return $data;
        }
        
        foreach ($this->encryptedFields[$table] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->encrypt($data[$field]);
            }
        }
        
        return $data;
    }
    
    public function decryptData(string $table, array $data): array
    {
        if (!isset($this->encryptedFields[$table])) {
            return $data;
        }
        
        foreach ($this->encryptedFields[$table] as $field) {
            if (isset($data[$field])) {
                $data[$field] = $this->decrypt($data[$field]);
            }
        }
        
        return $data;
    }
    
    protected function encrypt($value): string
    {
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        return Crypt::encryptString($value);
    }
    
    protected function decrypt(string $value)
    {
        $decrypted = Crypt::decryptString($value);
        
        // Try to decode JSON if applicable
        $decoded = json_decode($decrypted, true);
        return $decoded ?? $decrypted;
    }
    
    public function rotateEncryptionKey(): void
    {
        // This should be done during maintenance window
        $tables = array_keys($this->encryptedFields);
        
        foreach ($tables as $table) {
            DB::table($table)->chunkById(100, function ($records) use ($table) {
                foreach ($records as $record) {
                    $data = (array) $record;
                    
                    // Decrypt with old key
                    $decrypted = $this->decryptWithOldKey($table, $data);
                    
                    // Encrypt with new key
                    $encrypted = $this->encryptData($table, $decrypted);
                    
                    // Update record
                    DB::table($table)
                        ->where('id', $record->id)
                        ->update($encrypted);
                }
            });
        }
    }
}
```

### Sensitive Data Masking

```php
<?php

namespace App\Mcp\Security;

class DataMasking
{
    protected array $maskingRules = [
        'email' => '/^(.{2}).*@(.*)$/',
        'phone' => '/^(\d{3}).*(\d{2})$/',
        'credit_card' => '/^(\d{4}).*(\d{4})$/',
        'api_key' => '/^(.{8}).*$/',
        'password' => '/.*/','
    ];
    
    public function mask(array $data, array $fields): array
    {
        foreach ($fields as $field => $type) {
            if (isset($data[$field])) {
                $data[$field] = $this->maskValue($data[$field], $type);
            }
        }
        
        return $data;
    }
    
    protected function maskValue($value, string $type): string
    {
        if (!isset($this->maskingRules[$type])) {
            return '***MASKED***';
        }
        
        return match($type) {
            'email' => preg_replace($this->maskingRules[$type], '$1***@$2', $value),
            'phone' => preg_replace($this->maskingRules[$type], '$1-***-$2', $value),
            'credit_card' => preg_replace($this->maskingRules[$type], '$1-****-****-$2', $value),
            'api_key' => preg_replace($this->maskingRules[$type], '$1...', $value),
            'password' => '********',
            default => '***MASKED***',
        };
    }
    
    public function maskLogEntry(array $logEntry): array
    {
        $sensitiveFields = [
            'password' => 'password',
            'api_key' => 'api_key',
            'token' => 'api_key',
            'secret' => 'password',
            'credit_card' => 'credit_card',
        ];
        
        array_walk_recursive($logEntry, function (&$value, $key) use ($sensitiveFields) {
            if (isset($sensitiveFields[$key])) {
                $value = $this->maskValue($value, $sensitiveFields[$key]);
            }
        });
        
        return $logEntry;
    }
}
```

## Secure Communication

### TLS/SSL Configuration

```php
<?php

namespace App\Mcp\Security;

class TlsConfiguration
{
    public function enforceHttps(): void
    {
        if (!request()->secure() && app()->environment('production')) {
            abort(403, 'HTTPS required');
        }
    }
    
    public function validateCertificate(string $url): bool
    {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => config('mcp.security.ca_bundle'),
                'disable_compression' => true,
                'SNI_enabled' => true,
                'ciphers' => 'HIGH:!SSLv2:!SSLv3',
            ],
        ]);
        
        $headers = @get_headers($url, 1, $context);
        
        return $headers !== false;
    }
    
    public function createSecureHttpClient(): \GuzzleHttp\Client
    {
        return new \GuzzleHttp\Client([
            'verify' => config('mcp.security.ca_bundle'),
            'protocols' => ['https'],
            'timeout' => 30,
            'connect_timeout' => 10,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'Laravel-MCP/1.0',
            ],
            'curl' => [
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            ],
        ]);
    }
}
```

### Message Signing

```php
<?php

namespace App\Mcp\Security;

class MessageSigner
{
    protected string $algorithm = 'sha256';
    
    public function sign(array $message): array
    {
        $payload = json_encode($message);
        $signature = $this->generateSignature($payload);
        
        return [
            'payload' => $message,
            'signature' => $signature,
            'algorithm' => $this->algorithm,
            'timestamp' => time(),
        ];
    }
    
    public function verify(array $signedMessage): bool
    {
        // Check timestamp to prevent replay attacks
        if (!$this->verifyTimestamp($signedMessage['timestamp'] ?? 0)) {
            return false;
        }
        
        $payload = json_encode($signedMessage['payload']);
        $expectedSignature = $this->generateSignature($payload);
        
        // Constant-time comparison
        return hash_equals($expectedSignature, $signedMessage['signature']);
    }
    
    protected function generateSignature(string $payload): string
    {
        $secret = config('mcp.security.signing_key');
        return hash_hmac($this->algorithm, $payload, $secret);
    }
    
    protected function verifyTimestamp(int $timestamp): bool
    {
        $maxAge = config('mcp.security.signature_max_age', 300); // 5 minutes
        return abs(time() - $timestamp) <= $maxAge;
    }
}
```

## Rate Limiting and DDoS Protection

### Advanced Rate Limiting

```php
<?php

namespace App\Mcp\Security;

use Illuminate\Support\Facades\Redis;

class AdvancedRateLimiter
{
    protected array $buckets = [
        'burst' => ['capacity' => 10, 'refill_rate' => 1, 'refill_interval' => 1],
        'sustained' => ['capacity' => 100, 'refill_rate' => 10, 'refill_interval' => 60],
        'daily' => ['capacity' => 10000, 'refill_rate' => 10000, 'refill_interval' => 86400],
    ];
    
    public function allowRequest(string $identifier): bool
    {
        foreach ($this->buckets as $name => $config) {
            if (!$this->checkBucket($identifier, $name, $config)) {
                return false;
            }
        }
        
        return true;
    }
    
    protected function checkBucket(string $identifier, string $name, array $config): bool
    {
        $key = "rate_limit:{$identifier}:{$name}";
        
        return Redis::eval($this->getTokenBucketScript(), 1, $key, 
            $config['capacity'],
            $config['refill_rate'],
            $config['refill_interval'],
            time()
        ) > 0;
    }
    
    protected function getTokenBucketScript(): string
    {
        return <<<'LUA'
            local key = KEYS[1]
            local capacity = tonumber(ARGV[1])
            local refill_rate = tonumber(ARGV[2])
            local refill_interval = tonumber(ARGV[3])
            local now = tonumber(ARGV[4])
            
            local bucket = redis.call('HMGET', key, 'tokens', 'last_refill')
            local tokens = tonumber(bucket[1]) or capacity
            local last_refill = tonumber(bucket[2]) or now
            
            -- Calculate tokens to add
            local elapsed = now - last_refill
            local tokens_to_add = math.floor(elapsed / refill_interval) * refill_rate
            tokens = math.min(capacity, tokens + tokens_to_add)
            
            if tokens > 0 then
                tokens = tokens - 1
                redis.call('HMSET', key, 'tokens', tokens, 'last_refill', now)
                redis.call('EXPIRE', key, 86400)
                return tokens
            else
                return 0
            end
        LUA;
    }
}
```

### DDoS Protection

```php
<?php

namespace App\Mcp\Security;

class DdosProtection
{
    protected array $blacklist = [];
    protected array $whitelist = [];
    
    public function checkRequest(Request $request): bool
    {
        $ip = $request->ip();
        
        // Check whitelist
        if ($this->isWhitelisted($ip)) {
            return true;
        }
        
        // Check blacklist
        if ($this->isBlacklisted($ip)) {
            $this->logBlockedRequest($request);
            return false;
        }
        
        // Check for attack patterns
        if ($this->detectAttackPattern($request)) {
            $this->addToBlacklist($ip);
            return false;
        }
        
        return true;
    }
    
    protected function detectAttackPattern(Request $request): bool
    {
        $ip = $request->ip();
        
        // Check request rate
        $requestCount = Redis::incr("ddos:requests:{$ip}");
        Redis::expire("ddos:requests:{$ip}", 60);
        
        if ($requestCount > 100) { // More than 100 requests per minute
            return true;
        }
        
        // Check for suspicious patterns
        if ($this->hasSuspiciousHeaders($request)) {
            return true;
        }
        
        if ($this->hasSuspiciousPayload($request)) {
            return true;
        }
        
        return false;
    }
    
    protected function hasSuspiciousHeaders(Request $request): bool
    {
        // Check for missing or suspicious headers
        if (!$request->hasHeader('User-Agent')) {
            return true;
        }
        
        $userAgent = $request->header('User-Agent');
        
        // Check for known bad user agents
        $badAgents = ['scanner', 'bot', 'crawler', 'spider'];
        foreach ($badAgents as $agent) {
            if (stripos($userAgent, $agent) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    protected function hasSuspiciousPayload(Request $request): bool
    {
        $content = $request->getContent();
        
        // Check payload size
        if (strlen($content) > 1048576) { // > 1MB
            return true;
        }
        
        // Check for repeated patterns (possible flooding)
        if (preg_match('/(.)\1{100,}/', $content)) {
            return true;
        }
        
        return false;
    }
    
    protected function addToBlacklist(string $ip): void
    {
        Redis::setex("ddos:blacklist:{$ip}", 3600, 1); // Block for 1 hour
        
        \Log::warning('IP added to DDoS blacklist', ['ip' => $ip]);
    }
    
    protected function isBlacklisted(string $ip): bool
    {
        return Redis::exists("ddos:blacklist:{$ip}");
    }
    
    protected function isWhitelisted(string $ip): bool
    {
        return in_array($ip, config('mcp.security.whitelist', []));
    }
}
```

## Audit Logging

### Comprehensive Audit System

```php
<?php

namespace App\Mcp\Security;

use App\Models\AuditLog;

class AuditLogger
{
    protected array $sensitiveFields = ['password', 'token', 'secret', 'api_key'];
    
    public function log(string $event, array $data = []): void
    {
        $auditData = [
            'event' => $event,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'method' => request()->method(),
            'url' => request()->fullUrl(),
            'data' => $this->sanitizeData($data),
            'session_id' => session()->getId(),
            'timestamp' => now(),
        ];
        
        // Store in database
        AuditLog::create($auditData);
        
        // Send to SIEM if configured
        if (config('mcp.security.siem_enabled')) {
            $this->sendToSiem($auditData);
        }
        
        // Check for security events
        $this->checkSecurityEvent($event, $data);
    }
    
    protected function sanitizeData(array $data): array
    {
        $sanitized = $data;
        
        array_walk_recursive($sanitized, function (&$value, $key) {
            if (in_array($key, $this->sensitiveFields)) {
                $value = '***REDACTED***';
            }
        });
        
        return $sanitized;
    }
    
    protected function sendToSiem(array $auditData): void
    {
        $siem = app(SiemConnector::class);
        $siem->send($auditData);
    }
    
    protected function checkSecurityEvent(string $event, array $data): void
    {
        $securityEvents = [
            'authentication_failed',
            'authorization_failed',
            'rate_limit_exceeded',
            'suspicious_activity',
            'malware_detected',
        ];
        
        if (in_array($event, $securityEvents)) {
            $this->handleSecurityEvent($event, $data);
        }
    }
    
    protected function handleSecurityEvent(string $event, array $data): void
    {
        // Increment security event counter
        $count = Redis::incr("security:events:{$event}:" . date('Y-m-d'));
        Redis::expire("security:events:{$event}:" . date('Y-m-d'), 86400);
        
        // Alert if threshold exceeded
        $threshold = config("mcp.security.event_thresholds.{$event}", 10);
        if ($count > $threshold) {
            $this->alertSecurityTeam($event, $count, $data);
        }
    }
}
```

## Security Headers

### Security Headers Middleware

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Enable XSS protection
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Content Security Policy
        $response->headers->set('Content-Security-Policy', $this->getCsp());
        
        // Strict Transport Security
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }
        
        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Permissions Policy
        $response->headers->set('Permissions-Policy', $this->getPermissionsPolicy());
        
        return $response;
    }
    
    protected function getCsp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
    }
    
    protected function getPermissionsPolicy(): string
    {
        return implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'accelerometer=()',
        ]);
    }
}
```

## Dependency Management

### Dependency Security Scanner

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SecurityAudit extends Command
{
    protected $signature = 'mcp:security-audit';
    protected $description = 'Run security audit on dependencies and code';
    
    public function handle(): int
    {
        $this->info('Running security audit...');
        
        $results = [
            'composer' => $this->auditComposerPackages(),
            'npm' => $this->auditNpmPackages(),
            'code' => $this->auditCode(),
            'configuration' => $this->auditConfiguration(),
        ];
        
        $this->displayResults($results);
        
        return $this->hasVulnerabilities($results) ? 1 : 0;
    }
    
    protected function auditComposerPackages(): array
    {
        exec('composer audit --format=json 2>&1', $output, $exitCode);
        
        if ($exitCode !== 0) {
            $audit = json_decode(implode('', $output), true);
            return [
                'status' => 'vulnerable',
                'vulnerabilities' => $audit['advisories'] ?? [],
            ];
        }
        
        return ['status' => 'secure'];
    }
    
    protected function auditNpmPackages(): array
    {
        exec('npm audit --json 2>&1', $output, $exitCode);
        
        $audit = json_decode(implode('', $output), true);
        
        if (($audit['metadata']['vulnerabilities']['total'] ?? 0) > 0) {
            return [
                'status' => 'vulnerable',
                'vulnerabilities' => $audit['vulnerabilities'] ?? [],
            ];
        }
        
        return ['status' => 'secure'];
    }
    
    protected function auditCode(): array
    {
        $issues = [];
        
        // Check for hardcoded secrets
        $patterns = [
            '/["\']password["\']\s*=>\s*["\'][^"\']+["\']/',
            '/["\']api_key["\']\s*=>\s*["\'][^"\']+["\']/',
            '/["\']secret["\']\s*=>\s*["\'][^"\']+["\']/',
        ];
        
        $files = glob(base_path('app/**/*.php'));
        
        foreach ($files as $file) {
            $content = file_get_contents($file);
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content)) {
                    $issues[] = "Potential hardcoded secret in {$file}";
                }
            }
        }
        
        return empty($issues) ? ['status' => 'secure'] : [
            'status' => 'issues',
            'issues' => $issues,
        ];
    }
    
    protected function auditConfiguration(): array
    {
        $issues = [];
        
        // Check debug mode
        if (config('app.debug') === true && app()->environment('production')) {
            $issues[] = 'Debug mode enabled in production';
        }
        
        // Check encryption key
        if (empty(config('app.key'))) {
            $issues[] = 'Application encryption key not set';
        }
        
        // Check session configuration
        if (config('session.secure') !== true && app()->environment('production')) {
            $issues[] = 'Session cookies not set to secure-only';
        }
        
        return empty($issues) ? ['status' => 'secure'] : [
            'status' => 'issues',
            'issues' => $issues,
        ];
    }
}
```

## Security Testing

### Security Test Suite

```php
<?php

namespace Tests\Security;

use Tests\TestCase;

class McpSecurityTest extends TestCase
{
    public function test_sql_injection_prevention()
    {
        $payloads = [
            "'; DROP TABLE users; --",
            "1' OR '1'='1",
            "admin' --",
            "' UNION SELECT * FROM users --",
        ];
        
        foreach ($payloads as $payload) {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => [
                    'name' => 'search',
                    'arguments' => ['query' => $payload],
                ],
                'id' => 1,
            ]);
            
            $response->assertStatus(400);
            $this->assertStringContainsString('SQL injection', $response->json('error.message'));
        }
    }
    
    public function test_xss_prevention()
    {
        $payloads = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert("XSS")>',
            'javascript:alert("XSS")',
            '<iframe src="javascript:alert(\'XSS\')"></iframe>',
        ];
        
        foreach ($payloads as $payload) {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => [
                    'name' => 'comment',
                    'arguments' => ['text' => $payload],
                ],
                'id' => 1,
            ]);
            
            $response->assertStatus(400);
            $this->assertStringContainsString('XSS', $response->json('error.message'));
        }
    }
    
    public function test_rate_limiting()
    {
        for ($i = 0; $i < 101; $i++) {
            $response = $this->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => ['name' => 'test'],
                'id' => $i,
            ]);
            
            if ($i < 100) {
                $response->assertStatus(200);
            } else {
                $response->assertStatus(429);
            }
        }
    }
    
    public function test_authentication_required()
    {
        $response = $this->postJson('/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => ['name' => 'admin_tool'],
            'id' => 1,
        ]);
        
        $response->assertStatus(401);
    }
    
    public function test_authorization_enforcement()
    {
        $user = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($user)
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'method' => 'tools/call',
                'params' => ['name' => 'admin_tool'],
                'id' => 1,
            ]);
        
        $response->assertStatus(403);
    }
}
```

## Conclusion

Security is paramount when implementing MCP servers. By following these best practices and implementing the security measures outlined in this guide, you can ensure your Laravel MCP implementation is protected against common vulnerabilities and attacks. Regular security audits, dependency updates, and monitoring are essential for maintaining a secure system.