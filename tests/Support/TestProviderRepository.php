<?php

namespace JTD\LaravelMCP\Tests\Support;

use Illuminate\Foundation\ProviderRepository;

/**
 * Custom ProviderRepository that handles non-writable cache directories gracefully.
 */
class TestProviderRepository extends ProviderRepository
{
    /**
     * Write the service manifest file.
     * Override to handle non-writable directories gracefully.
     *
     * @param  array  $manifest
     * @return array
     */
    public function writeManifest($manifest)
    {
        $dirname = dirname($this->manifestPath);

        // Try to create the directory if it doesn't exist
        if (! is_dir($dirname)) {
            @mkdir($dirname, 0777, true);
        }

        // Only attempt to write if the directory is writable
        if (is_writable($dirname)) {
            return parent::writeManifest($manifest);
        }

        // If not writable, just return the manifest without caching
        return $manifest;
    }

    /**
     * Determine if the manifest should be compiled.
     * Override to disable caching in non-writable environments.
     *
     * @param  array  $manifest
     * @param  array  $providers
     * @return bool
     */
    protected function shouldRecompile($manifest, $providers)
    {
        $dirname = dirname($this->manifestPath);

        // If directory is not writable, always recompile (no cache)
        if (! is_writable($dirname)) {
            return true;
        }

        return parent::shouldRecompile($manifest, $providers);
    }
}
