<?php

use App\Contexts\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Contexts\Branchs\Infrastructure\Http\Controllers\BranchController;
use App\Contexts\Customers\Infrastructure\Http\Controllers\CustomerController;
use App\Contexts\Destinations\Infrastructure\Http\Controllers\DestinationController;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Http\Controllers\ExtraordinaryCommissionController;
use App\Contexts\Users\Infrastructure\Http\Controllers\UserController;
use App\Contexts\Commissions\Infrastructure\Http\Controllers\CommissionController;
use App\Contexts\Orders\Infrastructure\Http\Controllers\ListOrdersController;
use App\Contexts\Commissions\Infrastructure\Http\Controllers\GetCommissionController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas con auth:sanctum
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Rutas de clientes
    Route::get('customers/search', [CustomerController::class, 'search']);
    Route::apiResource('customers', CustomerController::class);

    // Rutas de destinos
    Route::get('destinations', [DestinationController::class, 'index']);
    Route::post('destinations', [DestinationController::class, 'store']);
    Route::put('destinations/{id}', [DestinationController::class, 'update']);
    Route::delete('destinations/{id}', [DestinationController::class, 'destroy']);
    Route::get('destinations/rates/{origin}/{destination}', [DestinationController::class, 'rates']);
    Route::get('/origins', [DestinationController::class, 'origins']);
    Route::get('/destinations/{origin}', [DestinationController::class, 'destinations']);

    // Rutas de comisiones extraordinarias
    Route::apiResource('extraordinary-commissions', ExtraordinaryCommissionController::class);
    Route::get('extraordinary-commissions/{origin}/{destination}', [ExtraordinaryCommissionController::class, 'getByOriginAndDestination']);

    // Rutas de comisiones
    Route::post('/commissions', [CommissionController::class, 'store']);
    Route::get('/commissions/statuses', [CommissionController::class, 'getStatuses']);

    // Rutas de órdenes
    Route::get('/commissions',  [CommissionController::class, 'index']);
    Route::get('/commissions/{id}', [CommissionController::class, 'show']);
});

// Rutas protegidas con auth:sanctum y isAdmin
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    // Rutas de usuarios
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Rutas de sucursales
    Route::apiResource('branches', BranchController::class);
});



