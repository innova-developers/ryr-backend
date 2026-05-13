<?php

namespace App\Services;

use App\Shared\Models\FcmToken;
use App\Shared\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\Unregistered;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmNotificationService
{
    private $messaging;

    public function __construct()
    {
        try {
            $firebaseCredentialsPath = config('services.firebase.credentials_path');

            if (! $firebaseCredentialsPath || ! file_exists($firebaseCredentialsPath)) {
                Log::warning('Firebase credentials file not found', [
                    'path' => $firebaseCredentialsPath,
                ]);
                $this->messaging = null;

                return;
            }

            $factory = (new Factory())->withServiceAccount($firebaseCredentialsPath);
            $this->messaging = $factory->createMessaging();
        } catch (\Exception $e) {
            Log::error('Error inicializando Firebase Messaging', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->messaging = null;
        }
    }

    /**
     * Enviar notificación push a un usuario
     *
     * @param int $userId ID del usuario
     * @param array $payload Datos de la notificación
     *   - title: Título de la notificación
     *   - body: Cuerpo del mensaje
     *   - data: Datos adicionales (opcional)
     *   - imageUrl: URL de imagen (opcional)
     * @return array Resultado del envío
     */
    public function sendPushToUser(int $userId, array $payload): array
    {
        if (! $this->messaging) {
            Log::warning('Firebase Messaging no está configurado, no se puede enviar notificación', [
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'message' => 'Firebase no está configurado',
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
            ];
        }

        // Validar payload requerido
        if (empty($payload['title']) || empty($payload['body'])) {
            Log::warning('Payload de notificación incompleto', [
                'user_id' => $userId,
                'payload' => $payload,
            ]);

            return [
                'success' => false,
                'message' => 'Título y cuerpo son requeridos',
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
            ];
        }

        // Obtener tokens activos del usuario
        $tokens = FcmToken::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($tokens->isEmpty()) {
            Log::info('Usuario no tiene tokens FCM activos', [
                'user_id' => $userId,
            ]);

            return [
                'success' => false,
                'message' => 'Usuario no tiene tokens FCM registrados',
                'sent' => 0,
                'failed' => 0,
                'invalid_tokens' => [],
            ];
        }

        $results = [
            'success' => true,
            'sent' => 0,
            'failed' => 0,
            'invalid_tokens' => [],
            'errors' => [],
        ];

        // Enviar notificación a cada token
        foreach ($tokens as $token) {
            try {
                $result = $this->sendToToken($token->fcm_token, $payload, $token->platform);

                if ($result['success']) {
                    $results['sent']++;
                    $token->markAsUsed();
                } else {
                    $results['failed']++;
                    $results['errors'][] = [
                        'token_id' => $token->id,
                        'error' => $result['error'] ?? 'Error desconocido',
                    ];

                    // Si el token es inválido o no registrado, marcarlo como inactivo
                    if (isset($result['invalid_token']) && $result['invalid_token']) {
                        $results['invalid_tokens'][] = $token->id;
                        $token->deactivate();
                        Log::info('Token FCM marcado como inactivo por ser inválido', [
                            'token_id' => $token->id,
                            'user_id' => $userId,
                            'platform' => $token->platform,
                        ]);
                    }
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'token_id' => $token->id,
                    'error' => $e->getMessage(),
                ];
                Log::error('Error enviando notificación FCM a token específico', [
                    'token_id' => $token->id,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $results['message'] = "Enviadas: {$results['sent']}, Fallidas: {$results['failed']}";

        Log::info('Notificación FCM enviada a usuario', [
            'user_id' => $userId,
            'results' => $results,
        ]);

        return $results;
    }

    /**
     * Enviar notificación a un token específico
     *
     * @param string $fcmToken Token FCM
     * @param array $payload Datos de la notificación
     * @param string $platform Plataforma (android, ios, web)
     * @return array Resultado del envío
     */
    private function sendToToken(string $fcmToken, array $payload, string $platform): array
    {
        try {
            // Crear notificación
            $notification = Notification::create(
                $payload['title'],
                $payload['body']
            );

            // Preparar datos adicionales
            $data = $payload['data'] ?? [];
            $data['timestamp'] = now()->toISOString();
            $data['platform'] = $platform;

            // Crear mensaje
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification)
                ->withData($data);

            // Configuraciones específicas por plataforma
            if ($platform === 'android') {
                $message = $message->withAndroidConfig([
                    'priority' => 'high',
                    'notification' => [
                        'sound' => 'default',
                        'channel_id' => 'default',
                    ],
                ]);
            } elseif ($platform === 'ios') {
                $message = $message->withApnsConfig([
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ]);
            }

            // Enviar mensaje
            $this->messaging->send($message);

            return [
                'success' => true,
            ];

        } catch (Unregistered $e) {
            Log::warning('Token FCM no registrado', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'invalid_token' => true,
                'error' => 'Token no registrado',
            ];

        } catch (NotFound $e) {
            Log::warning('Token FCM no encontrado', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'invalid_token' => true,
                'error' => 'Token no encontrado',
            ];

        } catch (InvalidArgument $e) {
            Log::error('Argumento inválido al enviar notificación FCM', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'invalid_token' => false,
                'error' => 'Argumento inválido: ' . $e->getMessage(),
            ];

        } catch (\Exception $e) {
            Log::error('Error enviando notificación FCM', [
                'token' => substr($fcmToken, 0, 20) . '...',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'invalid_token' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Enviar notificación a múltiples usuarios
     *
     * @param array $userIds IDs de usuarios
     * @param array $payload Datos de la notificación
     * @return array Resultado del envío
     */
    public function sendPushToUsers(array $userIds, array $payload): array
    {
        $results = [
            'total_users' => count($userIds),
            'successful' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($userIds as $userId) {
            $result = $this->sendPushToUser($userId, $payload);
            $results['details'][$userId] = $result;

            if ($result['sent'] > 0) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Enviar notificación a todos los cadetes con tokens FCM activos
     *
     * @param array $payload Datos de la notificación
     * @param int|null $branchId Filtrar por branch_id (opcional)
     * @return array Resultado del envío
     */
    public function sendPushToAllCadetes(array $payload, ?int $branchId = null): array
    {
        try {
            // Obtener todos los cadetes con tokens FCM activos
            $query = User::whereIn('role', [\App\Shared\Enums\UserRole::CADETE, \App\Shared\Enums\UserRole::CADETE_EXTERNO])
                ->whereHas('fcmTokens', function ($q) {
                    $q->where('is_active', true);
                });

            // Filtrar por branch si se especifica
            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            $cadeteIds = $query->pluck('id')->toArray();

            if (empty($cadeteIds)) {
                Log::info('No hay cadetes con tokens FCM activos para notificar', [
                    'branch_id' => $branchId,
                ]);

                return [
                    'total_users' => 0,
                    'successful' => 0,
                    'failed' => 0,
                    'details' => [],
                ];
            }

            return $this->sendPushToUsers($cadeteIds, $payload);
        } catch (\Exception $e) {
            Log::error('Error al obtener cadetes para notificación', [
                'error' => $e->getMessage(),
                'branch_id' => $branchId,
            ]);

            return [
                'total_users' => 0,
                'successful' => 0,
                'failed' => 0,
                'details' => [],
            ];
        }
    }
}
