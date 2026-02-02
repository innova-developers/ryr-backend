<?php

namespace App\Http\Controllers\Admin;

use App\Mail\GeneralNotificationMail;
use App\Services\FcmNotificationService;
use App\Shared\Enums\UserRole;
use App\Shared\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class NotificationController
{
    protected $fcmService;

    public function __construct(FcmNotificationService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Obtener lista de cadetes para mostrar en el formulario
     */
    public function getCadetes(Request $request): JsonResponse
    {
        try {
            $cadetes = User::whereIn('role', [UserRole::CADETE, UserRole::CADETE_EXTERNO])
                ->select('id', 'name', 'email', 'role')
                ->orderBy('name', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'cadetes' => $cadetes,
                    'total' => $cadetes->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error obteniendo cadetes para notificaciones', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la lista de cadetes',
            ], 500);
        }
    }

    /**
     * Enviar notificación general a todos los cadetes
     */
    public function sendNotificationToAllCadetes(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
            'send_email' => 'boolean',
            'send_push' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $title = $request->title;
            $message = $request->message;
            $sendEmail = $request->boolean('send_email', false);
            $sendPush = $request->boolean('send_push', true);

            // Obtener todos los cadetes de la franquicia actual
            $cadetes = User::whereIn('role', [UserRole::CADETE, UserRole::CADETE_EXTERNO])
                ->get();

            if ($cadetes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay cadetes registrados en esta franquicia',
                ], 404);
            }

            $results = [
                'total_cadetes' => $cadetes->count(),
                'emails_sent' => 0,
                'emails_failed' => 0,
                'push_sent' => 0,
                'push_failed' => 0,
                'details' => [],
            ];

            // Enviar notificaciones a cada cadete
            foreach ($cadetes as $cadete) {
                $cadeteResult = [
                    'cadete_id' => $cadete->id,
                    'cadete_name' => $cadete->name,
                    'email_sent' => false,
                    'push_sent' => false,
                    'errors' => [],
                ];

                // Enviar email si está habilitado y el cadete tiene email
                if ($sendEmail && $cadete->email) {
                    try {
                        $this->sendEmailToCadete($cadete, $title, $message);
                        $cadeteResult['email_sent'] = true;
                        $results['emails_sent']++;
                    } catch (\Exception $e) {
                        $cadeteResult['errors'][] = 'Email: ' . $e->getMessage();
                        $results['emails_failed']++;
                        Log::error('Error enviando email a cadete', [
                            'cadete_id' => $cadete->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Enviar push notification si está habilitado
                if ($sendPush) {
                    try {
                        $pushResult = $this->fcmService->sendPushToUser($cadete->id, [
                            'title' => $title,
                            'body' => $message,
                            'data' => [
                                'type' => 'general_notification',
                                'timestamp' => now()->toISOString(),
                            ],
                        ]);

                        if ($pushResult['sent'] > 0) {
                            $cadeteResult['push_sent'] = true;
                            $results['push_sent']++;
                        } else {
                            $cadeteResult['errors'][] = 'Push: ' . ($pushResult['message'] ?? 'No se pudo enviar');
                            $results['push_failed']++;
                        }
                    } catch (\Exception $e) {
                        $cadeteResult['errors'][] = 'Push: ' . $e->getMessage();
                        $results['push_failed']++;
                        Log::error('Error enviando push a cadete', [
                            'cadete_id' => $cadete->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $results['details'][] = $cadeteResult;
            }

            Log::info('Notificación general enviada a cadetes', [
                'total_cadetes' => $results['total_cadetes'],
                'emails_sent' => $results['emails_sent'],
                'push_sent' => $results['push_sent'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notificaciones enviadas',
                'data' => $results,
            ]);

        } catch (\Exception $e) {
            Log::error('Error enviando notificaciones a cadetes', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al enviar las notificaciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enviar email a un cadete
     */
    private function sendEmailToCadete(User $cadete, string $title, string $message): void
    {
        Mail::to($cadete->email)->send(
            new GeneralNotificationMail($title, $message, $cadete->name)
        );
    }
}
