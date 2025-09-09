<?php

use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Http\Controllers\McpController;

/*
|--------------------------------------------------------------------------
| MCP Package Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the MCP service provider and provide the
| HTTP transport endpoints for MCP protocol communication.
|
*/

// Main MCP message handling endpoint (JSON-RPC over HTTP)
Route::post('/', [McpController::class, 'handle'])
    ->name('mcp.handle');

// CORS preflight handling
Route::options('/', [McpController::class, 'options'])
    ->name('mcp.options');

// Server-Sent Events endpoint for real-time notifications
Route::get('/events', [McpController::class, 'events'])
    ->name('mcp.events');

// Health check endpoint
Route::get('/health', [McpController::class, 'health'])
    ->name('mcp.health');

// Server information endpoint
Route::get('/info', [McpController::class, 'info'])
    ->name('mcp.info');
