#!/usr/bin/env php
<?php

// Simple test runner that bypasses cache directory permission issues

// Create writable cache directory
$cacheDir = '/tmp/laravel-mcp-test-cache';
if (! is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

// Create cache files
file_put_contents($cacheDir.'/packages.php', '<?php return [];');
file_put_contents($cacheDir.'/services.php', '<?php return [];');

// Set environment variables
putenv('TESTBENCH_BOOTSTRAP_CACHE_PATH='.$cacheDir);
$_ENV['TESTBENCH_BOOTSTRAP_CACHE_PATH'] = $cacheDir;
$_SERVER['TESTBENCH_BOOTSTRAP_CACHE_PATH'] = $cacheDir;

// Create symlink to bypass permission checks
$vendorCacheDir = __DIR__.'/vendor/orchestra/testbench-core/laravel/bootstrap/cache';
if (is_link($vendorCacheDir)) {
    unlink($vendorCacheDir);
} elseif (is_dir($vendorCacheDir)) {
    // Can't remove directory owned by root, but we can try to make it writable
    @chmod($vendorCacheDir, 0777);
}

// If we can't fix the vendor cache dir, at least create the files
if (is_dir($vendorCacheDir) && is_writable($vendorCacheDir)) {
    file_put_contents($vendorCacheDir.'/packages.php', '<?php return [];');
    file_put_contents($vendorCacheDir.'/services.php', '<?php return [];');
}

// Load composer autoloader
require __DIR__.'/vendor/autoload.php';

// Run PHPUnit with the appropriate configuration
$command = 'vendor/bin/phpunit';
if (isset($argv[1])) {
    $command .= ' '.implode(' ', array_slice($argv, 1));
}

echo "Running tests with custom cache configuration...\n";
passthru($command, $returnCode);
exit($returnCode);
