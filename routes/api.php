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
use App\Http\Middleware\CadeteMiddleware;
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
    Route::get('/users/{id}', [UserController::class, 'show']);

    // Comisiones (excepto show que ya está pública)
    Route::post('/commissions', [CommissionController::class, 'store']);
    Route::get('/commissions/statuses', [CommissionController::class, 'getStatuses']);
    Route::get('/commissions/statuses/client', [CommissionController::class, 'getClientStatuses']);
    Route::get('/commissions',  [CommissionController::class, 'index']);
    Route::get('/commissions/{id}', [CommissionController::class, 'show']);
    Route::patch('/commissions/{id}/status', [CommissionController::class, 'updateStatus']);
    Route::delete('/commissions/{id}', [CommissionController::class, 'destroy']);

    // Gestión de cadetes en comisiones
    Route::post('/commissions/{commission}/assign-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'assignCadete']);
    Route::delete('/commissions/{commission}/unassign-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'unassignCadete']);
    Route::patch('/commissions/{commission}/change-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'changeCadete']);
    Route::get('/commissions/{commission}/assigned-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'getAssignedCadete']);
    Route::get('/cadetes/{cadete}/commissions', [App\Http\Controllers\CommissionCadeteController::class, 'getCommissionsByCadete']);

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
Route::prefix('cadete')->middleware(['auth:sanctum', CadeteMiddleware::class])->group(function () {
    Route::get('/home', [App\Http\Controllers\Cadete\DashboardController::class, 'home']);
    Route::get('/profile', [App\Http\Controllers\Cadete\CadeteController::class, 'profile']);
    Route::put('/profile', [App\Http\Controllers\Cadete\CadeteController::class, 'updateProfile']);
    Route::get('/deliveries', [App\Http\Controllers\Cadete\CadeteController::class, 'deliveries']);
    Route::get('/shipments', [App\Http\Controllers\Cadete\CadeteController::class, 'shipments']); // Alias para compatibilidad
    Route::put('/deliveries/{id}', [App\Http\Controllers\Cadete\CadeteController::class, 'updateShipmentStatus']);
    Route::put('/shipments/{id}', [App\Http\Controllers\Cadete\CadeteController::class, 'updateShipmentStatus']); // Alias para compatibilidad
    Route::post('/deliveries/{id}/location', [App\Http\Controllers\Cadete\CadeteController::class, 'sendLocation']);
    Route::post('/shipments/{id}/location', [App\Http\Controllers\Cadete\CadeteController::class, 'sendLocation']); // Alias para compatibilidad
    Route::get('/stats', [App\Http\Controllers\Cadete\CadeteController::class, 'stats']);
    Route::get('/earnings', [App\Http\Controllers\Cadete\CadeteController::class, 'earnings']);
    
    // Rutas para pagos del cadete
    Route::prefix('payments')->group(function () {
        Route::get('/', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'index']);
        Route::get('/summary', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getPaymentsSummary']);
        Route::get('/next-payment', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getNextPayment']);
        Route::get('/recent', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getRecentPayments']);
        Route::get('/{id}', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'show']);
    });
});

// Rutas para administradores - Gestión de pagos de cadetes
Route::middleware(['auth:sanctum', 'isAdmin'])->prefix('admin')->group(function () {
    Route::prefix('cadete-payments')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CadetePaymentController::class, 'index']);
        Route::post('/', [App\Http\Controllers\Admin\CadetePaymentController::class, 'store']);
        Route::get('/summary', [App\Http\Controllers\Admin\CadetePaymentController::class, 'getPaymentsSummary']);
        Route::post('/calculate', [App\Http\Controllers\Admin\CadetePaymentController::class, 'calculate']);
        Route::get('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'show']);
        Route::put('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'destroy']);
        Route::patch('/{id}/mark-as-paid', [App\Http\Controllers\Admin\CadetePaymentController::class, 'markAsPaid']);
        Route::patch('/{id}/mark-as-cancelled', [App\Http\Controllers\Admin\CadetePaymentController::class, 'markAsCancelled']);
        Route::get('/cadete/{cadeteId}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'getPaymentsByCadete']);
    });
});
