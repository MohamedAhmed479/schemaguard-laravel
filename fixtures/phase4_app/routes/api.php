<?php

declare(strict_types=1);

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/api/users/{user}', [UserController::class, 'show']);
Route::post('/api/users', [UserController::class, 'store']);
Route::apiResource('/api/accounts', UserController::class);
Route::resource('/web/users', UserController::class);

$uri = '/api/dynamic';
Route::get($uri, [UserController::class, 'show']);
