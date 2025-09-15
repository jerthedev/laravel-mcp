#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JTD\LaravelMCP\Server\Handlers\ToolHandler;
use JTD\LaravelMCP\Registry\ToolRegistry;

// Simple test to verify pagination works
echo "Testing pagination implementation...\n";

try {
    // Create mock tool registry
    $toolRegistry = new ToolRegistry();

    // Add some test tools
    for ($i = 1; $i <= 10; $i++) {
        $toolRegistry->register("test_tool_$i", new class {
            public function getDescription() {
                return "Test tool description";
            }
            public function getInputSchema() {
                return ['type' => 'object', 'properties' => []];
            }
        });
    }

    echo "Registry has " . count($toolRegistry->all()) . " tools\n";

    // Create handler with debug enabled
    $handler = new ToolHandler($toolRegistry, true);

    // Test tools/list without cursor (should return only 3 tools due to our limit)
    echo "\nTesting tools/list without cursor...\n";
    $result = $handler->handle('tools/list', [], ['request_id' => 1]);

    echo "Response keys: " . json_encode(array_keys($result)) . "\n";
    echo "Tools count: " . count($result['tools'] ?? []) . "\n";

    if (isset($result['tools'])) {
        echo "Tool names: " . json_encode(array_column($result['tools'], 'name')) . "\n";
    }

    echo "\nTest completed successfully!\n";

} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}