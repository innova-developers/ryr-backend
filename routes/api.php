<?php

use App\Contexts\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Contexts\Users\Infrastructure\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);

Route::middleware(['auth:sanctum', 'isAdmin'])->post('/users', [UserController::class, 'store']);
