<?php

use Illuminate\Support\Facades\Route;

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
        // MCP HTTP transport endpoints will be registered here
        // Route::post('/message', [McpController::class, 'handleMessage']);
    });