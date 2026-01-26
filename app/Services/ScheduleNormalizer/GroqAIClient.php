<?php

namespace App\Services\ScheduleNormalizer;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de IA usando Groq API (gratis con límites generosos)
 *
 * Para obtener una API key:
 * 1. Registrarse en https://console.groq.com/
 * 2. Crear una API key
 * 3. Agregar a .env: GROQ_API_KEY=tu_api_key
 *
 * Ventajas de Groq:
 * - Gratis con límites generosos
 * - Muy rápido (inferencia acelerada)
 * - Modelos Llama 3.1 disponibles
 */
class GroqAIClient extends AbstractAIClient
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const DEFAULT_MODEL = 'llama-3.1-8b-instant';

    private ?string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.groq.api_key');
    }

    /**
     * Parsea un horario comercial usando Groq API
     *
     * @param string $rawSchedule Horario en formato libre
     * @return array Array estructurado con rangos horarios
     */
    public function parseSchedule(string $rawSchedule): array
    {
        if (empty($this->apiKey)) {
            Log::warning('Groq API key no configurada. Agregar GROQ_API_KEY a .env');

            return ['ranges' => [], 'confidence' => 'low'];
        }

        try {
            $prompt = $this->buildPrompt($rawSchedule);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => self::DEFAULT_MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Eres un asistente especializado en parsear horarios comerciales. ' .
                                       'Responde SOLO con JSON válido, sin texto adicional. ' .
                                       'El JSON debe tener exactamente esta estructura: ' .
                                       '{"ranges": [{"start": "HH:MM", "end": "HH:MM"}, ...], "confidence": "medium|low"}. ' .
                                       'Usa formato 24 horas (00:00 a 23:59).',
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.1, // Baja temperatura para respuestas más determinísticas
                    'max_tokens' => 500,
                ]);

            if ($response->successful()) {
                $content = $response->json()['choices'][0]['message']['content'] ?? '';

                if (empty($content)) {
                    Log::warning('Respuesta vacía de Groq', [
                        'schedule' => $rawSchedule,
                        'response' => $response->json(),
                    ]);

                    return ['ranges' => [], 'confidence' => 'low'];
                }

                return $this->validateResponse($content);
            }

            Log::warning('Error en respuesta de Groq', [
                'status' => $response->status(),
                'body' => $response->body(),
                'schedule' => $rawSchedule,
            ]);

            return ['ranges' => [], 'confidence' => 'low'];
        } catch (\Exception $e) {
            Log::error('Error al llamar a Groq para parsear horario', [
                'schedule' => $rawSchedule,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ['ranges' => [], 'confidence' => 'low'];
        }
    }
}


