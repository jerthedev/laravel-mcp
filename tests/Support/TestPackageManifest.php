<?php

namespace JTD\LaravelMCP\Tests\Support;

use Orchestra\Testbench\Foundation\PackageManifest;

/**
 * Custom PackageManifest that bypasses the writable directory check.
 * This is necessary because the Orchestra Testbench cache directory
 * is owned by root and cannot be made writable in the test environment.
 */
class TestPackageManifest extends PackageManifest
{
    /**
     * Write the given manifest array to disk.
     * Overrides the parent method to bypass the writable directory check.
     *
     * @return void
     */
    protected function write(array $manifest)
    {
        // Try to write to the original path
        $dirname = dirname($this->manifestPath);

        // If the directory is not writable, use a temp directory
        if (! is_writable($dirname)) {
            // Use a temp directory instead
            $tempDir = '/tmp/laravel-mcp-test-cache';
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0777, true);
            }

            // Update the manifest path to use temp directory
            $this->manifestPath = $tempDir.'/'.basename($this->manifestPath);
        }

        // Write the manifest
        file_put_contents(
            $this->manifestPath,
            '<?php return '.var_export($manifest, true).';'
        );
    }

    /**
     * Ensure the manifest has been loaded into memory.
     * Overrides to handle the temp directory case.
     *
     * @return void
     */
    protected function ensureManifestIsLoaded()
    {
        if (! is_null($this->manifest)) {
            return;
        }

        // Check both original and temp paths
        $paths = [
            $this->manifestPath,
            '/tmp/laravel-mcp-test-cache/'.basename($this->manifestPath),
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $this->manifest = require $path;

                return;
            }
        }

        // If no manifest exists, build a new one
        $this->build();
    }
}
