<?php

/**
 * Simple test to validate MCP initialize response format
 * Tests the exact JSON format that Claude CLI expects vs Playwright
 */

echo "=== MCP Response Format Test ===" . PHP_EOL;
echo "Testing if our response matches Playwright exactly..." . PHP_EOL . PHP_EOL;

// What Playwright returns (WORKING)
$playwrightResponse = [
    'result' => [
        'protocolVersion' => '2025-06-18',
        'capabilities' => [
            'tools' => new stdClass()  // This should become {}
        ],
        'serverInfo' => [
            'name' => 'Playwright',
            'version' => '0.0.37'
        ]
    ],
    'jsonrpc' => '2.0',
    'id' => 0
];

// What our Laravel server should return
$laravelResponse = [
    'result' => [
        'protocolVersion' => '2025-06-18',
        'capabilities' => [
            'tools' => new stdClass()  // This should become {}
        ],
        'serverInfo' => [
            'name' => 'Laravel MCP Server',
            'version' => '1.0.0'
        ]
    ],
    'jsonrpc' => '2.0',
    'id' => 0
];

echo "🔍 Testing JSON encoding..." . PHP_EOL;

// Test Playwright format
$playwrightJson = json_encode($playwrightResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "✅ Playwright JSON:" . PHP_EOL;
echo "   " . $playwrightJson . PHP_EOL . PHP_EOL;

// Test our Laravel format
$laravelJson = json_encode($laravelResponse, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
echo "🔍 Laravel JSON:" . PHP_EOL;
echo "   " . $laravelJson . PHP_EOL . PHP_EOL;

// Parse back to check tools format
$playwrightParsed = json_decode($playwrightJson, true);
$laravelParsed = json_decode($laravelJson, true);

$playwrightTools = json_encode($playwrightParsed['result']['capabilities']['tools']);
$laravelTools = json_encode($laravelParsed['result']['capabilities']['tools']);

echo "🎯 CRITICAL TEST - Tools capability format:" . PHP_EOL;
echo "   Playwright tools: " . $playwrightTools . " (" . (($playwrightTools === '{}') ? "✅ CORRECT" : "❌ WRONG") . ")" . PHP_EOL;
echo "   Laravel tools:    " . $laravelTools . " (" . (($laravelTools === '{}') ? "✅ CORRECT" : "❌ WRONG") . ")" . PHP_EOL . PHP_EOL;

// Test field order
$playwrightKeys = array_keys($playwrightParsed);
$laravelKeys = array_keys($laravelParsed);

echo "📋 Field order test:" . PHP_EOL;
echo "   Playwright: [" . implode(', ', $playwrightKeys) . "]" . PHP_EOL;
echo "   Laravel:    [" . implode(', ', $laravelKeys) . "]" . PHP_EOL;
echo "   Match: " . (($playwrightKeys === $laravelKeys) ? "✅ YES" : "❌ NO") . PHP_EOL . PHP_EOL;

// Overall compatibility check
$toolsMatch = $playwrightTools === $laravelTools && $laravelTools === '{}';
$keysMatch = $playwrightKeys === $laravelKeys;
$compatible = $toolsMatch && $keysMatch;

echo "🏁 FINAL RESULT:" . PHP_EOL;
if ($compatible) {
    echo "   ✅ SUCCESS: Laravel response format matches Playwright exactly!" . PHP_EOL;
    echo "   ✅ Claude CLI should accept this response!" . PHP_EOL;
} else {
    echo "   ❌ FAILED: Laravel response format differs from Playwright" . PHP_EOL;
    echo "   ❌ Claude CLI will likely reject this response" . PHP_EOL;

    if (!$toolsMatch) {
        echo "   🔧 Fix needed: tools capability should be {} not " . $laravelTools . PHP_EOL;
    }
    if (!$keysMatch) {
        echo "   🔧 Fix needed: field order should be [result, jsonrpc, id]" . PHP_EOL;
    }
}

echo PHP_EOL;

// Additional debug: Test different ways to create empty object
echo "🧪 Testing different empty object approaches:" . PHP_EOL;
$approaches = [
    'new stdClass()' => new stdClass(),
    '(object)[]' => (object)[],
    'json_decode("{}")' => json_decode('{}'),
];

foreach ($approaches as $name => $value) {
    $json = json_encode($value);
    $correct = $json === '{}';
    echo "   $name: $json " . ($correct ? "✅" : "❌") . PHP_EOL;
}

echo PHP_EOL . "=== Test Complete ===" . PHP_EOL;