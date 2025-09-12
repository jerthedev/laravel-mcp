<?php

/**
 * Bootstrap file for PHPUnit tests.
 * Sets up the testing environment with proper cache directories.
 */

// Create a writable test environment by copying Orchestra skeleton
$orchestraBase = __DIR__.'/../vendor/orchestra/testbench-core/laravel';
$testBase = '/tmp/orchestra-testbench';

// Ensure test base exists
if (! is_dir($testBase)) {
    @mkdir($testBase, 0777, true);
}

// Copy or link necessary directories from Orchestra skeleton
// Note: 'app' is excluded as we need it to be writable
$directories = ['config', 'database', 'lang', 'migrations', 'public', 'resources', 'routes', 'tests'];
foreach ($directories as $dir) {
    $source = $orchestraBase.'/'.$dir;
    $target = $testBase.'/'.$dir;

    if (! file_exists($target) && file_exists($source)) {
        // Create symlink to original directory
        @symlink($source, $target);
    }
}

// Remove app symlink if it exists and create writable directory
$appDir = $testBase.'/app';
if (is_link($appDir)) {
    @unlink($appDir);
}
if (! is_dir($appDir)) {
    @mkdir($appDir, 0777, true);
    // Copy basic app structure from Orchestra
    $appSource = $orchestraBase.'/app';
    if (is_dir($appSource)) {
        // Copy subdirectories
        foreach (['Console', 'Exceptions', 'Http', 'Models', 'Providers'] as $subdir) {
            $src = $appSource.'/'.$subdir;
            $dst = $appDir.'/'.$subdir;
            if (is_dir($src) && ! is_dir($dst)) {
                @mkdir($dst, 0777, true);
            }
        }
    }
}

// Copy files
$files = ['.env.example', 'artisan', 'composer.json', 'server.php'];
foreach ($files as $file) {
    $source = $orchestraBase.'/'.$file;
    $target = $testBase.'/'.$file;

    if (! file_exists($target) && file_exists($source)) {
        @copy($source, $target);
    }
}

// Create writable directories
$writableDirs = [
    'bootstrap',
    'bootstrap/cache',
    'storage',
    'storage/app',
    'storage/app/public',
    'storage/framework',
    'storage/framework/cache',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/testing',
    'storage/framework/views',
    'storage/logs',
    'vendor',  // Create vendor directory for config discovery
    'app/Mcp',
    'app/Mcp/Tools',
    'app/Mcp/Resources',
    'app/Mcp/Prompts',
];

foreach ($writableDirs as $dir) {
    $path = $testBase.'/'.$dir;
    if (! is_dir($path)) {
        @mkdir($path, 0777, true);
    }
}

// Create symlink to Laravel framework config for Orchestra discovery
$frameworkConfigSource = __DIR__.'/../vendor/laravel/framework/config';
$frameworkConfigTarget = $testBase.'/vendor/laravel/framework/config';
if (! file_exists($frameworkConfigTarget) && file_exists($frameworkConfigSource)) {
    @mkdir($testBase.'/vendor/laravel', 0777, true);
    @mkdir($testBase.'/vendor/laravel/framework', 0777, true);
    @symlink($frameworkConfigSource, $frameworkConfigTarget);
}

// Create required cache files
$cacheFiles = [
    $testBase.'/bootstrap/cache/packages.php' => '<?php return [];',
    $testBase.'/bootstrap/cache/services.php' => '<?php return [];',
    $testBase.'/bootstrap/app.php' => file_get_contents($orchestraBase.'/bootstrap/app.php'),
    $testBase.'/bootstrap/autoload.php' => file_get_contents($orchestraBase.'/bootstrap/autoload.php'),
    $testBase.'/bootstrap/providers.php' => file_get_contents($orchestraBase.'/bootstrap/providers.php'),
];

foreach ($cacheFiles as $file => $content) {
    if (! file_exists($file)) {
        @file_put_contents($file, $content);
    }
}

// Set environment to use our test directory
putenv('TESTBENCH_WORKING_PATH='.$testBase);
$_ENV['TESTBENCH_WORKING_PATH'] = $testBase;

// Set MCP_ENABLED for all tests
putenv('MCP_ENABLED=true');
$_ENV['MCP_ENABLED'] = 'true';

// Load composer autoloader
require __DIR__.'/../vendor/autoload.php';

// Manually load package helpers if not already loaded
if (! function_exists('mcp')) {
    require __DIR__.'/../src/Support/helpers.php';
}
