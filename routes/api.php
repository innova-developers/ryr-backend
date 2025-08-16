<?php

use App\Contexts\Auth\Infrastructure\Http\Controllers\AuthController;
use App\Contexts\Branchs\Infrastructure\Http\Controllers\BranchController;
use App\Contexts\Commissions\Infrastructure\Http\Controllers\CommissionController;
use App\Contexts\Customers\Infrastructure\Http\Controllers\CustomerController;
use App\Contexts\Destinations\Infrastructure\Http\Controllers\DestinationController;
use App\Contexts\ExpenseCategories\Infrastructure\Http\Controllers\ExpenseCategoryController;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Http\Controllers\ExtraordinaryCommissionController;
use App\Contexts\Locations\Infrastructure\Http\Controllers\LocationsController;
use App\Contexts\Users\Infrastructure\Http\Controllers\UserController;
use App\Contexts\Transports\Infrastructure\Http\Controllers\TransportController;
use App\Contexts\Expenses\Infrastructure\Http\Controllers\ExpensesController;
use App\Contexts\Incomes\Infrastructure\Http\Controllers\IncomesController;
use App\Contexts\IncomeCategories\Infrastructure\Http\Controllers\IncomeCategoryController;
use App\Contexts\CurrentAccount\Infrastructure\Http\Controllers\CurrentAccountController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Rutas públicas para validación de clientes
Route::post('/validate-identifier', [App\Http\Controllers\Auth\ValidateIdentifierController::class, 'validateIdentifier']);
Route::post('/verify-code', [App\Http\Controllers\Auth\ValidateIdentifierController::class, 'verifyCode']);

// Rutas de destinos
Route::get('destinations', [DestinationController::class, 'index']);
Route::post('destinations', [DestinationController::class, 'store']);
Route::get('destinations/rates/{origin}/{destination}', [DestinationController::class, 'rates']);
Route::get('/origins', [DestinationController::class, 'origins']);
Route::get('/destinations/origin/{origin}', [DestinationController::class, 'destinations']);
Route::get('destinations/{id}', [DestinationController::class, 'show']);
Route::put('destinations/{id}', [DestinationController::class, 'update']);
Route::delete('destinations/{id}', [DestinationController::class, 'destroy']);

// Rutas de comisiones extraordinarias
Route::apiResource('extraordinary-commissions', ExtraordinaryCommissionController::class);
Route::get('extraordinary-commissions/{origin}/{destination}', [ExtraordinaryCommissionController::class, 'getByOriginAndDestination']);

// Rutas de categorías de gastos
Route::apiResource('expense-categories', ExpenseCategoryController::class);

// Rutas de gastos generales
Route::apiResource('expenses', ExpensesController::class);
Route::get('users/{userId}/expenses', [ExpensesController::class, 'index']);

// Rutas de ingresos
Route::apiResource('incomes', IncomesController::class);
Route::get('users/{userId}/incomes', [IncomesController::class, 'indexByUser']);

// Rutas de categorías de ingresos
Route::apiResource('income-categories', IncomeCategoryController::class);

// Rutas de cuenta corriente
Route::prefix('current-accounts')->group(function () {
    Route::post('/', [CurrentAccountController::class, 'store']);
    Route::get('/{id}', [CurrentAccountController::class, 'show']);
    Route::put('/{id}', [CurrentAccountController::class, 'update']);
    Route::delete('/{id}', [CurrentAccountController::class, 'destroy']);
});

// Rutas públicas para comisiones
Route::get('/commissions/{id}/tracking', [CommissionController::class, 'showPublic']);
Route::post('/commissions/public', [App\Http\Controllers\Public\PublicCommissionController::class, 'store']);

// Rutas públicas para clientes
Route::post('/customers/public', [App\Http\Controllers\Public\PublicCustomerController::class, 'store']);

// Rutas protegidas para usuarios, comisiones y clientes
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    // Usuarios
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Comisiones (excepto show que ya está pública)
    Route::post('/commissions', [CommissionController::class, 'store']);
    Route::get('/commissions/statuses', [CommissionController::class, 'getStatuses']);
    Route::get('/commissions/{id}', [CommissionController::class, 'show']);
    Route::patch('/commissions/{id}/status', [CommissionController::class, 'updateStatus']);
    Route::get('/commissions',  [CommissionController::class, 'index']);
    Route::delete('/commissions/{id}', [CommissionController::class, 'destroy']);

    // Clientes
    Route::get('customers/search', [CustomerController::class, 'search']);
    Route::apiResource('customers', CustomerController::class);

    // Cuenta corriente de clientes
    Route::prefix('customers/{customerId}/current-account')->group(function () {
        Route::get('/transactions', [CurrentAccountController::class, 'getCustomerTransactions']);
        Route::get('/balance', [CurrentAccountController::class, 'getCustomerBalance']);
    });
});

Route::get('/users/{userId}/salary', [UserController::class, 'calculateSalary']);

// Rutas de sucursales
Route::apiResource('branches', BranchController::class);

Route::prefix('locations')->group(function () {
    Route::get('/', [LocationsController::class, 'index']);
    Route::post('/', [LocationsController::class, 'store']);
    Route::get('/origin/{origin}', [LocationsController::class, 'getByOrigin']);
    Route::put('/{id}', [LocationsController::class, 'update']);
    Route::delete('/{id}', [LocationsController::class, 'destroy']);
});

Route::prefix('transports')->group(function () {
    Route::get('/', [TransportController::class, 'index']);
    Route::post('/', [TransportController::class, 'store']);
    Route::put('/{id}', [TransportController::class, 'update']);
    Route::delete('/{id}', [TransportController::class, 'destroy']);

    // Rutas de gastos de transportes (mantener compatibilidad)
    Route::get('/{transportId}/expenses', [ExpensesController::class, 'indexByTransport']);
    Route::post('/{transportId}/expenses', [ExpensesController::class, 'storeForTransport']);
    Route::put('/{transportId}/expenses/{expenseId}', [ExpensesController::class, 'updateForTransport']);
    Route::delete('/{transportId}/expenses/{expenseId}', [ExpensesController::class, 'destroyForTransport']);
});

// Rutas del Dashboard de Clientes (protegidas con autenticación de cliente)
Route::middleware(['auth:sanctum'])->prefix('client')->group(function () {
    Route::get('/profile', [App\Http\Controllers\Client\ClientDashboardController::class, 'getProfile']);
    Route::put('/profile', [App\Http\Controllers\Client\ClientDashboardController::class, 'updateProfile']);
    Route::get('/shipments', [App\Http\Controllers\Client\ClientDashboardController::class, 'getShipments']);
    Route::get('/account-balance', [App\Http\Controllers\Client\ClientDashboardController::class, 'getAccountBalance']);

    // Rutas de cuenta corriente del cliente
    Route::get('/current-account/transactions', [App\Http\Controllers\Client\ClientDashboardController::class, 'getCurrentAccountTransactions']);
    Route::get('/current-account/balance', [App\Http\Controllers\Client\ClientDashboardController::class, 'getCurrentAccountBalance']);
});

// Rutas para cadetes y cadetes externos
Route::prefix('cadete')->middleware(['auth:sanctum', \App\Http\Middleware\CadeteMiddleware::class])->group(function () {
    Route::get('/profile', [App\Http\Controllers\Cadete\CadeteController::class, 'profile']);
    Route::get('/shipments', [App\Http\Controllers\Cadete\CadeteController::class, 'shipments']);
    Route::put('/shipments/{id}', [App\Http\Controllers\Cadete\CadeteController::class, 'updateShipmentStatus']);
    Route::post('/shipments/{id}/location', [App\Http\Controllers\Cadete\CadeteController::class, 'sendLocation']);
    Route::get('/stats', [App\Http\Controllers\Cadete\CadeteController::class, 'stats']);
});
