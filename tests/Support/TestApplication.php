<?php

namespace JTD\LaravelMCP\Tests\Support;

use Illuminate\Foundation\Application;

/**
 * Custom Application class that handles test-specific paths.
 */
class TestApplication extends Application
{
    /**
     * Create a new application instance with test-specific paths.
     */
    public function __construct(?string $basePath = null)
    {
        // Use /tmp for the base path if not provided
        $basePath = $basePath ?? '/tmp/orchestra-testbench';

        // Ensure directory exists
        if (! is_dir($basePath)) {
            @mkdir($basePath, 0777, true);
        }

        parent::__construct($basePath);

        // Override paths to use writable directories
        $this->useBootstrapPath($basePath.'/bootstrap');
        $this->useStoragePath($basePath.'/storage');

        // Ensure bootstrap directories exist
        $this->ensureDirectoriesExist();
    }

    /**
     * Ensure required directories exist.
     */
    protected function ensureDirectoriesExist(): void
    {
        $directories = [
            $this->bootstrapPath(),
            $this->bootstrapPath('cache'),
            $this->storagePath(),
            $this->storagePath('framework'),
            $this->storagePath('framework/cache'),
            $this->storagePath('framework/sessions'),
            $this->storagePath('framework/views'),
            $this->storagePath('framework/testing'),
            $this->storagePath('logs'),
        ];

        foreach ($directories as $dir) {
            if (! is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }
        }

        // Create cache files to prevent errors
        $cacheFiles = [
            $this->bootstrapPath('cache/packages.php') => '<?php return [];',
            $this->bootstrapPath('cache/services.php') => '<?php return [];',
        ];

        foreach ($cacheFiles as $file => $content) {
            if (! file_exists($file)) {
                @file_put_contents($file, $content);
            }
        }
    }
}
