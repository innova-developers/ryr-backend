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
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\CadeteMiddleware;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout']);

// Rutas de prueba para envío de notificaciones
Route::get('/test-email', [App\Http\Controllers\TestEmailController::class, 'testCommissionStatusEmail']);
Route::get('/test-whatsapp', [App\Http\Controllers\TestEmailController::class, 'testCommissionStatusWhatsApp']);

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

// Rutas compartidas para administradores, mostradores y cadetes
Route::middleware(['auth:sanctum', 'adminOrCadete'])->group(function () {
    // Nueva comisión
    Route::post('/commissions', [CommissionController::class, 'store']);
    
    // Listado de comisiones
    Route::get('/commissions', [CommissionController::class, 'index']);
    Route::get('/commissions/statuses', [CommissionController::class, 'getStatuses']);
    Route::get('/commissions/{id}', [CommissionController::class, 'show']);
    
    // Actualizar estado de comisión (cadetes y mostradores pueden actualizar)
    Route::patch('/commissions/{id}/status', [CommissionController::class, 'updateStatus']);
    
    // Actualizar sucursal de comisión (todos los roles pueden actualizar)
    Route::patch('/commissions/{id}/branch', [CommissionController::class, 'updateBranch']);
    
    // Actualizar comisión (cadetes y administradores)
    Route::put('/commissions/{id}', [CommissionController::class, 'update']);
    
    // Ver comisiones de un cadete (cadetes solo pueden ver las suyas)
    Route::get('/cadetes/{cadete}/commissions', [App\Http\Controllers\CommissionCadeteController::class, 'getCommissionsByCadete']);
    
    // Desasignar cadete de una comisión (cadetes solo pueden desasignarse a sí mismos)
    Route::delete('/commissions/{commission}/unassign-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'unassignCadete']);
    
    // Búsqueda de clientes (necesaria para crear comisiones)
    Route::get('customers/search', [CustomerController::class, 'search']);
    
    // Listado de usuarios (solo lectura para cadetes)
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    
    // Ingresos
    Route::apiResource('incomes', IncomesController::class);
    Route::get('users/{userId}/incomes', [IncomesController::class, 'indexByUser']);
    
    // Panel de cobradores (pagos de cadetes) - Solo lectura para cadetes
    Route::prefix('admin/cadete-payments')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CadetePaymentController::class, 'index']);
        Route::get('/summary', [App\Http\Controllers\Admin\CadetePaymentController::class, 'getPaymentsSummary']);
        Route::get('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'show']);
        Route::get('/cadete/{cadeteId}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'getPaymentsByCadete']);
    });
});

// Rutas protegidas solo para administradores y mostradores
Route::middleware(['auth:sanctum', 'isAdmin'])->group(function () {
    // Usuarios - Operaciones administrativas (crear, editar, eliminar)
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    // Comisiones - Operaciones administrativas
    Route::get('/commissions/statuses/client', [CommissionController::class, 'getClientStatuses']);
    Route::delete('/commissions/{id}', [CommissionController::class, 'destroy']);

    // Gestión de cadetes en comisiones (solo administradores)
    Route::post('/commissions/{commission}/assign-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'assignCadete']);
    Route::patch('/commissions/{commission}/change-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'changeCadete']);
    Route::get('/commissions/{commission}/assigned-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'getAssignedCadete']);
    Route::get('/commissions/available', [App\Http\Controllers\CommissionCadeteController::class, 'getAvailableCommissions']);

    // Clientes
    Route::apiResource('customers', CustomerController::class);

    // Cuenta corriente de clientes
    Route::prefix('customers/{customerId}/current-account')->group(function () {
        Route::get('/transactions', [CurrentAccountController::class, 'getCustomerTransactions']);
        Route::get('/balance', [CurrentAccountController::class, 'getCustomerBalance']);
    });
    
    // Panel de cobradores - Operaciones administrativas (crear, editar, eliminar)
    Route::prefix('admin/cadete-payments')->group(function () {
        Route::post('/', [App\Http\Controllers\Admin\CadetePaymentController::class, 'store']);
        Route::post('/calculate', [App\Http\Controllers\Admin\CadetePaymentController::class, 'calculate']);
        Route::put('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'update']);
        Route::delete('/{id}', [App\Http\Controllers\Admin\CadetePaymentController::class, 'destroy']);
        Route::patch('/{id}/mark-as-paid', [App\Http\Controllers\Admin\CadetePaymentController::class, 'markAsPaid']);
        Route::patch('/{id}/mark-as-cancelled', [App\Http\Controllers\Admin\CadetePaymentController::class, 'markAsCancelled']);
        Route::get('/{id}/download-proof', [App\Http\Controllers\Admin\CadetePaymentController::class, 'downloadProof']);
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
        Route::get('/next', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getNextPayment']);
        Route::get('/next-payment', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getNextPayment']);
        Route::get('/recent', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'getRecentPayments']);
        Route::get('/{id}', [App\Http\Controllers\Cadete\CadetePaymentController::class, 'show']);
    });

    // Rutas para notificaciones del cadete
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('/{id}/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::patch('/mark-all-as-read', [NotificationController::class, 'markAllAsRead']);
    });

    // Ruta para registrar token FCM
    Route::post('/fcm-token', [App\Http\Controllers\Cadete\FcmTokenController::class, 'store']);

    // Ruta de test para enviar notificaciones push FCM
    Route::get('/test-fcm-notification', [App\Http\Controllers\Cadete\TestFcmNotificationController::class, 'sendTestNotificationGet']);
    Route::post('/test-fcm-notification', [App\Http\Controllers\Cadete\TestFcmNotificationController::class, 'sendTestNotification']);

    // Pool de comisiones disponibles (sin cadete asignado)
    Route::get('/commissions/available', [App\Http\Controllers\CommissionCadeteController::class, 'getAvailableCommissions']);
    // Autoasignar comisión a sí mismo
    Route::post('/commissions/{commission}/self-assign', [App\Http\Controllers\CommissionCadeteController::class, 'selfAssignCommission']);
    // Desasignarse de una comisión
    Route::delete('/commissions/{commission}/unassign-cadete', [App\Http\Controllers\CommissionCadeteController::class, 'unassignCadete']);
});



// Rutas para métodos de pago de comisiones
Route::prefix('payment-methods')->group(function () {
    Route::get('/', [App\Http\Controllers\Admin\PaymentMethodController::class, 'index']);
    Route::post('/associate', [App\Http\Controllers\Admin\PaymentMethodController::class, 'associatePaymentMethod']);
    Route::get('/summary', [App\Http\Controllers\Admin\PaymentMethodController::class, 'getPaymentMethodsSummary']);
});

