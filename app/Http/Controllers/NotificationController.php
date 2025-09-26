<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class NotificationController
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {
    }

    /**
     * Obtener notificaciones del usuario autenticado
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
            'unread_only' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Datos de entrada inválidos',
                'details' => $validator->errors(),
            ], 400);
        }

        $userId = Auth::id();
        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);
        
        // Manejar diferentes formatos de boolean que puede enviar Flutter
        $unreadOnlyParam = $request->get('unread_only', false);
        
        // Convertir a boolean de manera robusta
        if (is_bool($unreadOnlyParam)) {
            $unreadOnly = $unreadOnlyParam;
        } elseif (is_string($unreadOnlyParam)) {
            $unreadOnly = in_array(strtolower($unreadOnlyParam), ['true', '1', 'yes', 'on']);
        } elseif (is_numeric($unreadOnlyParam)) {
            $unreadOnly = (bool) $unreadOnlyParam;
        } else {
            $unreadOnly = false;
        }

        try {
            if ($unreadOnly) {
                $notifications = $this->notificationService->getUnreadNotifications($userId);
            } else {
                $notifications = $this->notificationService->getUserNotifications($userId, $limit, $offset);
            }

            return response()->json([
                'success' => true,
                'data' => $notifications,
                'count' => count($notifications),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener notificaciones',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead(int $id): JsonResponse
    {
        $userId = Auth::id();

        try {
            $success = $this->notificationService->markAsRead($id, $userId);

            if (!$success) {
                return response()->json([
                    'error' => 'Notificación no encontrada o no tienes permisos para acceder a ella',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notificación marcada como leída',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar notificación como leída',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead(): JsonResponse
    {
        $userId = Auth::id();

        try {
            $count = $this->notificationService->markAllAsRead($userId);

            return response()->json([
                'success' => true,
                'message' => "Se marcaron {$count} notificaciones como leídas",
                'count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al marcar notificaciones como leídas',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener contador de notificaciones no leídas
     */
    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();

        try {
            $unreadNotifications = $this->notificationService->getUnreadNotifications($userId);
            $count = count($unreadNotifications);

            return response()->json([
                'success' => true,
                'unread_count' => $count,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener contador de notificaciones',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
