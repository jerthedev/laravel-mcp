#!/bin/bash
echo "=== Minimal MCP Test $(date) ===" >> /tmp/minimal-debug.log

# Read input and respond exactly like Playwright
while IFS= read -r line; do
    echo "[$(date '+%H:%M:%S')] IN: $line" >> /tmp/minimal-debug.log

    if echo "$line" | grep -q '"method":"initialize"'; then
        response='{"result":{"protocolVersion":"2025-06-18","capabilities":{"tools":{}},"serverInfo":{"name":"Minimal Test","version":"1.0.0"}},"jsonrpc":"2.0","id":0}'
        echo "[$(date '+%H:%M:%S')] OUT: $response" >> /tmp/minimal-debug.log
        echo "$response"
    elif echo "$line" | grep -q '"method":"notifications/initialized"'; then
        echo "[$(date '+%H:%M:%S')] Received notifications/initialized" >> /tmp/minimal-debug.log
        request='{"method":"roots/list","jsonrpc":"2.0","id":0}'
        echo "[$(date '+%H:%M:%S')] OUT: $request" >> /tmp/minimal-debug.log
        echo "$request"
    elif echo "$line" | grep -q '"result".*"roots"'; then
        echo "[$(date '+%H:%M:%S')] Received roots response, exiting" >> /tmp/minimal-debug.log
        exit 0
    fi
done

echo "[$(date '+%H:%M:%S')] Script ended" >> /tmp/minimal-debug.log