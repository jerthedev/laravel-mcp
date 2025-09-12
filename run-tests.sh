#!/bin/bash

# Script to run tests with proper cache directory permissions
# This is a workaround for containerized environments where the vendor cache directory is not writable

echo "Setting up test environment..."

# Create writable cache directory
TEST_CACHE_DIR="/tmp/laravel-mcp-test-cache"
mkdir -p "$TEST_CACHE_DIR"
chmod 777 "$TEST_CACHE_DIR"

# Create cache files
echo "<?php return [];" > "$TEST_CACHE_DIR/packages.php"
echo "<?php return [];" > "$TEST_CACHE_DIR/services.php"

# Export environment variables
export TESTBENCH_BOOTSTRAP_CACHE_PATH="$TEST_CACHE_DIR"
export APP_ENV=testing
export CACHE_DRIVER=array

# Check if we're running as root (Docker environment)
if [ "$EUID" -eq 0 ]; then
    echo "Running in Docker/root environment - adjusting permissions..."
    
    # Try to make the vendor cache directory writable
    VENDOR_CACHE="/var/www/html/vendor/orchestra/testbench-core/laravel/bootstrap/cache"
    if [ -d "$VENDOR_CACHE" ]; then
        chown -R $(whoami):$(whoami) "$VENDOR_CACHE" 2>/dev/null || true
        chmod -R 777 "$VENDOR_CACHE" 2>/dev/null || true
    fi
fi

# Determine which test suite to run
TEST_SUITE="${1:-fast}"

echo "Running $TEST_SUITE test suite..."

case "$TEST_SUITE" in
    fast)
        vendor/bin/phpunit --testsuite=Fast
        ;;
    unit)
        vendor/bin/phpunit --testsuite=Unit
        ;;
    feature)
        vendor/bin/phpunit --testsuite=Feature
        ;;
    comprehensive)
        vendor/bin/phpunit --testsuite=Comprehensive
        ;;
    coverage)
        vendor/bin/phpunit --coverage-text --coverage-html=coverage
        ;;
    *)
        vendor/bin/phpunit "$@"
        ;;
esac