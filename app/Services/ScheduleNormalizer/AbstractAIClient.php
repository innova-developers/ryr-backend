<?php

namespace App\Services\ScheduleNormalizer;

/**
 * Implementación abstracta del cliente de IA
 *
 * Esta clase puede ser extendida para implementar clientes concretos
 * de diferentes proveedores de IA (OpenAI, Anthropic, etc.)
 */
abstract class AbstractAIClient implements AIClientInterface
{
    /**
     * Parsea un horario comercial usando IA
     *
     * La implementación concreta debe devolver un array con estructura:
     * [
     *   'ranges' => [
     *     ['start' => '08:00', 'end' => '12:00'],
     *     ['start' => '15:00', 'end' => '20:00']
     *   ],
     *   'confidence' => 'medium' | 'low'
     * ]
     *
     * @param string $rawSchedule Horario en formato libre
     * @return array Array estructurado con rangos horarios
     */
    abstract public function parseSchedule(string $rawSchedule): array;

    /**
     * Construye el prompt para la IA
     *
     * @param string $rawSchedule Horario original
     * @return string Prompt formateado
     */
    protected function buildPrompt(string $rawSchedule): string
    {
        return "Parsea el siguiente horario comercial y devuélvelo en formato JSON válido. " .
               "El JSON debe tener esta estructura exacta: " .
               '{"ranges": [{"start": "HH:MM", "end": "HH:MM"}, ...], "confidence": "medium|low"}. ' .
               "Horario a parsear: {$rawSchedule}";
    }

    /**
     * Valida y normaliza la respuesta de la IA
     *
     * @param string $response Respuesta de la IA
     * @return array Array estructurado o array vacío si es inválido
     */
    protected function validateResponse(string $response): array
    {
        // Intentar extraer JSON de la respuesta
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $json = json_decode($matches[0], true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['ranges'])) {
                return $json;
            }
        }

        return ['ranges' => [], 'confidence' => 'low'];
    }
}
