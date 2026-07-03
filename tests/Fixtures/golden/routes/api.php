<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SchemaGuard\Tests\Fixtures\golden\app\Http\Controllers\UserController;

Route::get('/api/users/{user}', [UserController::class, 'show']);
