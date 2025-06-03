<?php

use App\Contexts\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Contexts\Users\Infrastructure\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
Route::middleware(['auth:sanctum', 'isAdmin'])->get('/users', [UserController::class, 'index']);
Route::middleware(['auth:sanctum', 'isAdmin'])->post('/users', [UserController::class, 'store']);
Route::middleware(['auth:sanctum', 'isAdmin'])->delete('/users/{id}', [UserController::class, 'destroy']);
Route::middleware(['auth:sanctum', 'isAdmin'])->put('/users/{id}', [UserController::class, 'update']);
Route::middleware(['auth:sanctum', 'isAdmin'])->apiResource('branches', \App\Contexts\Branchs\Infrastructure\Http\Controllers\BranchController::class);
