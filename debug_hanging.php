#!/usr/bin/env php
<?php

/**
 * Debug script to isolate exactly where the hanging occurs
 * by manually stepping through the exact same code path
 * that Claude Code triggers.
 */

echo "=== DEBUGGING MCP HANGING ISSUE ===\n";

// Load composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Simulate the key classes that are involved in the hanging
use JTD\LaravelMCP\Abstracts\McpTool;
use JTD\LaravelMCP\Registry\ComponentFactory;
use JTD\LaravelMCP\Registry\ToolRegistry;
use JTD\LaravelMCP\Server\Handlers\ToolHandler;
use Illuminate\Container\Container;

// Create a minimal Laravel-like container
$container = new Container();

echo "1. Creating ComponentFactory...\n";
$factory = new ComponentFactory($container);
echo "   ✓ ComponentFactory created\n";

echo "2. Creating ToolRegistry...\n";
$toolRegistry = new ToolRegistry($factory);
echo "   ✓ ToolRegistry created\n";

echo "3. Creating ToolHandler...\n";
try {
    $toolHandler = new ToolHandler($toolRegistry);
    echo "   ✓ ToolHandler created\n";
} catch (\Throwable $e) {
    echo "   ✗ Failed to create ToolHandler: " . $e->getMessage() . "\n";
    exit(1);
}

echo "4. Calling ToolHandler->handle('tools/list', [])...\n";
try {
    $start = microtime(true);

    $result = $toolHandler->handle('tools/list', []);

    $duration = microtime(true) - $start;
    echo "   ✓ tools/list completed in " . round($duration * 1000) . "ms\n";
    echo "   ✓ Result: " . json_encode($result) . "\n";
} catch (\Throwable $e) {
    echo "   ✗ tools/list failed: " . $e->getMessage() . "\n";
    echo "   Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";
echo "The hanging is NOT in the ToolHandler logic itself.\n";
echo "The issue must be in the stdio transport or process communication.\n";