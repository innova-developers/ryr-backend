<?php

namespace App\Services\ScheduleNormalizer;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ejemplo de implementación concreta del cliente de IA
 *
 * Esta es una implementación de ejemplo que puede ser adaptada
 * para usar diferentes proveedores de IA (OpenAI, Anthropic, etc.)
 *
 * NOTA: Esta clase NO debe usarse en cronjobs.
 * Los horarios deben pre-procesarse antes de ejecutar cronjobs.
 */
class ExampleAIClient extends AbstractAIClient
{
    private ?string $apiKey;
    private ?string $apiUrl;

    public function __construct(?string $apiKey = null, ?string $apiUrl = null)
    {
        $this->apiKey = $apiKey ?? config('services.ai.api_key');
        $this->apiUrl = $apiUrl ?? config('services.ai.api_url');
    }

    /**
     * Parsea un horario comercial usando IA
     *
     * @param string $rawSchedule Horario en formato libre
     * @return array Array estructurado con rangos horarios
     */
    public function parseSchedule(string $rawSchedule): array
    {
        if (empty($this->apiKey) || empty($this->apiUrl)) {
            Log::warning('Cliente de IA no configurado correctamente');

            return ['ranges' => [], 'confidence' => 'low'];
        }

        try {
            $prompt = $this->buildPrompt($rawSchedule);

            // Ejemplo de llamada a API (adaptar según el proveedor)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl, [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Eres un asistente que parsea horarios comerciales. Responde SOLO con JSON válido.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
            ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';

                return $this->validateResponse($content);
            }

            Log::warning('Error en respuesta de IA', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['ranges' => [], 'confidence' => 'low'];
        } catch (\Exception $e) {
            Log::error('Error al llamar a IA para parsear horario', [
                'schedule' => $rawSchedule,
                'error' => $e->getMessage(),
            ]);

            return ['ranges' => [], 'confidence' => 'low'];
        }
    }
}
