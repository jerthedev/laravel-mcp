<?php

use Illuminate\Support\Facades\Route;
use JTD\LaravelMCP\Http\Controllers\McpController;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Here is where you can register MCP routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your MCP server!
|
*/

Route::prefix(config('laravel-mcp.routes.prefix', 'mcp'))
    ->group(function () {
        // Main MCP message handling endpoint (JSON-RPC over HTTP)
        Route::post('/', [McpController::class, 'handle'])
            ->name('mcp.handle')
            ->middleware(config('laravel-mcp.transports.http.middleware', ['mcp.cors', 'mcp.auth']));

        // CORS preflight handling
        Route::options('/', [McpController::class, 'options'])
            ->name('mcp.options');

        // Server-Sent Events endpoint for real-time notifications
        Route::get('/events', [McpController::class, 'events'])
            ->name('mcp.events')
            ->middleware(config('laravel-mcp.transports.http.middleware', ['mcp.cors', 'mcp.auth']));

        // Health check endpoint
        Route::get('/health', [McpController::class, 'health'])
            ->name('mcp.health');

        // Server information endpoint
        Route::get('/info', [McpController::class, 'info'])
            ->name('mcp.info');
    });
