<?php

use Illuminate\Support\Facades\Route;
use App\Infrastructure\Http\Controllers\AuthController;

Route::post('/login', [AuthController::class, 'login']);

