<?php

namespace App\Http\Controllers\Cadete;

use App\Services\FcmNotificationService;
use App\Shared\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TestFcmNotificationController
{
    /**
     * GET /api/cadete/test-fcm-notification - Ruta de test para enviar notificación push (GET)
     */
    public function sendTestNotificationGet(Request $request): JsonResponse
    {
        try {
            // Obtener parámetros de query string
            $userId = $request->query('user_id') ? (int) $request->query('user_id') : Auth::id();
            $title = $request->query('title', 'Notificación de prueba');
            $body = $request->query('body');

            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debes proporcionar un user_id como parámetro de consulta o estar autenticado',
                    'usage' => 'GET /api/cadete/test-fcm-notification?user_id=1&title=Título&body=Cuerpo del mensaje'
                ], 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar que el usuario tenga tokens FCM registrados
            $tokensCount = $user->fcmTokens()->where('is_active', true)->count();

            if ($tokensCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El usuario no tiene tokens FCM activos registrados',
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'hint' => 'Primero registra un token FCM usando POST /api/cadete/fcm-token'
                ], 400);
            }

            // Preparar payload de notificación
            if (empty($body)) {
                $body = "Esta es una notificación de prueba para {$user->name}";
            }

            $fcmService = app(FcmNotificationService::class);

            $result = $fcmService->sendPushToUser($userId, [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'test_notification',
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'timestamp' => now()->toISOString(),
                    'test' => true,
                ],
            ]);

            Log::info('Notificación de prueba FCM enviada (GET)', [
                'user_id' => $userId,
                'user_name' => $user->name,
                'result' => $result,
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Notificación de prueba enviada',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tokens_count' => $tokensCount,
                ],
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'result' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error enviando notificación de prueba FCM (GET)', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar notificación de prueba: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /api/cadete/test-fcm-notification - Ruta de test para enviar notificación push
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        try {
            // Validar datos opcionales
            $validated = $request->validate([
                'user_id' => 'nullable|integer|exists:users,id',
                'title' => 'nullable|string|max:255',
                'body' => 'nullable|string|max:1000',
            ]);

            // Obtener usuario (del request o autenticado)
            $userId = $validated['user_id'] ?? Auth::id();

            if (!$userId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debes proporcionar un user_id o estar autenticado'
                ], 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            // Verificar que el usuario tenga tokens FCM registrados
            $tokensCount = $user->fcmTokens()->where('is_active', true)->count();

            if ($tokensCount === 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'El usuario no tiene tokens FCM activos registrados',
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'hint' => 'Primero registra un token FCM usando POST /api/cadete/fcm-token'
                ], 400);
            }

            // Preparar payload de notificación
            $title = $validated['title'] ?? 'Notificación de prueba';
            $body = $validated['body'] ?? "Esta es una notificación de prueba para {$user->name}";

            $fcmService = app(FcmNotificationService::class);

            $result = $fcmService->sendPushToUser($userId, [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'test_notification',
                    'user_id' => $userId,
                    'user_name' => $user->name,
                    'timestamp' => now()->toISOString(),
                    'test' => true,
                ],
            ]);

            Log::info('Notificación de prueba FCM enviada', [
                'user_id' => $userId,
                'user_name' => $user->name,
                'result' => $result,
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'Notificación de prueba enviada',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tokens_count' => $tokensCount,
                ],
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'result' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error enviando notificación de prueba FCM', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error al enviar notificación de prueba: ' . $e->getMessage()
            ], 500);
        }
    }
}

