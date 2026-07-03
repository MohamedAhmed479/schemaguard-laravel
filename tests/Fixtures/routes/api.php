<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SchemaGuard\Tests\Fixtures\Http\Controllers\UserController;

Route::get('/api/users/{user}', [UserController::class, 'show']);
Route::post('/api/users', [UserController::class, 'store']);
