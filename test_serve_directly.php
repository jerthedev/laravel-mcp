#!/usr/bin/env php
<?php

/**
 * Direct test of our ServeCommand to verify it works independently
 * This bypasses any Laravel application that might be overriding the command
 */

echo "=== DIRECT SERVECOMMAND TEST ===\n";

require_once __DIR__ . '/vendor/autoload.php';

use JTD\LaravelMCP\Commands\ServeCommand;
use JTD\LaravelMCP\Transport\TransportManager;
use JTD\LaravelMCP\Protocol\MessageProcessor;
use Illuminate\Container\Container;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\ConsoleOutput;

try {
    echo "Creating Laravel container...\n";
    $container = new Container();

    // Bind basic services
    $container->instance('config', new class {
        public function get($key, $default = null) {
            return match($key) {
                'app.name' => 'Direct Test',
                'laravel-mcp.version' => '1.0.0',
                'laravel-mcp.discovery.enabled' => false,
                default => $default
            };
        }
    });

    echo "Creating MessageProcessor...\n";
    $messageProcessor = new MessageProcessor($container);

    echo "Creating TransportManager...\n";
    $transportManager = new TransportManager($container);

    echo "Creating ServeCommand...\n";
    $serveCommand = new ServeCommand($transportManager, $messageProcessor);

    echo "Creating Console Application...\n";
    $application = new Application();
    $application->add($serveCommand);
    $application->setDefaultCommand('mcp:serve');

    echo "Running ServeCommand directly...\n";
    echo "This should show our debug messages if ServeCommand works...\n\n";

    // Run the command
    $application->run(new ArgvInput(['test_serve_directly.php', '--transport=stdio']), new ConsoleOutput());

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}