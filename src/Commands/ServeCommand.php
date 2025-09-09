<?php

namespace JTD\LaravelMCP\Commands;

use Illuminate\Console\Command;

/**
 * MCP Server command to start the MCP server.
 */
class ServeCommand extends Command
{
    protected $signature = 'mcp:serve
                           {--host=127.0.0.1 : The host to serve on}
                           {--port=8000 : The port to serve on}
                           {--transport=stdio : Transport type (stdio|http)}
                           {--timeout=30 : Request timeout in seconds}
                           {--debug : Enable debug mode}';

    protected $description = 'Start the MCP server';

    public function handle(): int
    {
        // Placeholder implementation - will be fully implemented in future tickets
        $this->info('MCP Server would start here');
        
        return 0;
    }
}