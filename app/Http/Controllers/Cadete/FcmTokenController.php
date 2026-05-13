<?php

namespace App\Http\Controllers\Cadete;

use App\Shared\Models\FcmToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FcmTokenController
{
    /**
     * POST /api/cadete/fcm-token - Registrar token FCM
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validar los datos de entrada
            $validated = $request->validate([
                'fcm_token' => 'required|string|min:10|max:500',
                'platform' => 'required|string|in:android,ios,web',
            ]);

            $user = Auth::user();

            if (! $user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Usuario no autenticado',
                ], 401);
            }

            // Buscar si ya existe un token para este usuario y plataforma
            $existingToken = FcmToken::where('user_id', $user->id)
                ->where('platform', $validated['platform'])
                ->first();

            // Si el mismo token ya existe para otro usuario, invalidarlo
            $duplicateToken = FcmToken::where('fcm_token', $validated['fcm_token'])
                ->where('user_id', '!=', $user->id)
                ->first();

            if ($duplicateToken) {
                Log::info('Token FCM duplicado encontrado, invalidando token anterior', [
                    'old_user_id' => $duplicateToken->user_id,
                    'new_user_id' => $user->id,
                    'platform' => $validated['platform'],
                ]);
                $duplicateToken->deactivate();
            }

            // Upsert: actualizar si existe, crear si no existe
            if ($existingToken) {
                // Actualizar token existente
                $existingToken->update([
                    'fcm_token' => $validated['fcm_token'],
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                $fcmToken = $existingToken->fresh();
                $isNew = false;
            } else {
                // Crear nuevo token
                $fcmToken = FcmToken::create([
                    'user_id' => $user->id,
                    'fcm_token' => $validated['fcm_token'],
                    'platform' => $validated['platform'],
                    'is_active' => true,
                    'last_used_at' => now(),
                ]);
                $isNew = true;
            }

            Log::info('Token FCM registrado exitosamente', [
                'user_id' => $user->id,
                'platform' => $validated['platform'],
                'is_new' => $isNew,
                'token_id' => $fcmToken->id,
            ]);

            return response()->json([
                'status' => 'ok',
                'data' => [
                    'id' => $fcmToken->id,
                    'user_id' => $fcmToken->user_id,
                    'fcm_token' => $fcmToken->fcm_token,
                    'platform' => $fcmToken->platform,
                    'is_active' => $fcmToken->is_active,
                    'created_at' => $fcmToken->created_at->toISOString(),
                    'updated_at' => $fcmToken->updated_at->toISOString(),
                ],
            ], $isNew ? 201 : 200);

        } catch (ValidationException $e) {
            Log::warning('Error de validación al registrar token FCM', [
                'user_id' => Auth::id(),
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Datos inválidos',
                'errors' => $e->errors(),
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error al registrar token FCM', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor al registrar el token',
            ], 500);
        }
    }
}
