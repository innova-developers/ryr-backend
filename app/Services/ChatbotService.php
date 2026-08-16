<?php

namespace App\Services;

use App\Shared\Models\ChatbotFaq;
use App\Shared\Models\Commission;

/**
 * RC-490 — Chatbot básico.
 *
 * Dos capacidades, las que pide la card:
 *   1. Estado de una comisión por número de envío.
 *   2. Respuestas a preguntas frecuentes, configurables desde la base.
 *
 * El matcheo es por palabras clave, sin IA: es un chatbot básico y determinístico,
 * fácil de auditar y de corregir por el admin.
 *
 * Importante: la respuesta de estado NO expone datos personales (direcciones,
 * teléfonos, notas ni nombres de empleados). El canal es público y anónimo.
 */
class ChatbotService
{
    private const MAX_SUGERENCIAS = 4;

    /**
     * @return array<string, mixed>
     */
    public function handle(string $message): array
    {
        $texto = trim($message);

        if ($texto === '') {
            return $this->respuesta(
                'No te llegué a leer. ¿Me repetís la consulta?',
                'vacio'
            );
        }

        // El número de envío tiene prioridad: si el mensaje trae uno, se responde el
        // estado aunque además haya texto alrededor ("hola, estado del 53620?").
        $numero = $this->extraerNumeroEnvio($texto);

        if ($numero !== null) {
            return $this->estadoDeEnvio($numero);
        }

        $faq = $this->buscarFaq($texto);

        if ($faq) {
            $faq->increment('hits');

            return $this->respuesta($faq->answer, 'faq', ['faq_id' => $faq->id]);
        }

        return $this->respuesta(
            'No estoy seguro de haber entendido. Puedo ayudarte con el estado de un envío '
            . '(mandame el número) o con estas consultas:',
            'sin_coincidencia'
        );
    }

    /**
     * Preguntas sugeridas para arrancar la conversación.
     *
     * @return array<int, array<string, mixed>>
     */
    public function sugerencias(int $limit = self::MAX_SUGERENCIAS): array
    {
        return ChatbotFaq::where('is_active', true)
            ->orderBy('sort_order')
            ->limit($limit)
            ->get(['id', 'question'])
            ->map(fn (ChatbotFaq $f) => ['id' => $f->id, 'question' => $f->question])
            ->all();
    }

    /**
     * Un número de envío es una secuencia de 3 a 10 dígitos. Se descartan los mensajes
     * que son sólo texto con algún número suelto corto (por ejemplo "8 a 12").
     */
    private function extraerNumeroEnvio(string $texto): ?int
    {
        if (! preg_match('/\b(\d{3,10})\b/', $texto, $m)) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoDeEnvio(int $numero): array
    {
        $commission = Commission::with(['originLocation:id,origin', 'destinationLocation:id,origin'])
            ->find($numero);

        if (! $commission) {
            return $this->respuesta(
                "No encontré ningún envío con el número {$numero}. Revisá el número y probá de nuevo.",
                'envio_no_encontrado'
            );
        }

        $estado = $commission->status?->getClienteStatus() ?? 'En proceso';
        $origen = $commission->originLocation?->origin;
        $destino = $commission->destinationLocation?->origin;

        $texto = "Envío #{$commission->id}: {$estado}.";

        if ($origen && $destino) {
            $texto .= " Recorrido: {$origen} → {$destino}.";
        }

        if ($commission->date) {
            $texto .= ' Fecha: ' . $commission->date->format('d/m/Y') . '.';
        }

        $texto .= ' Para ver el detalle completo necesitás validar tu identidad desde la web.';

        return $this->respuesta($texto, 'estado_envio', [
            'commission_id' => $commission->id,
            'status' => $commission->status?->value,
            'status_label' => $estado,
        ]);
    }

    /**
     * Elige la FAQ con más palabras clave coincidentes. Empate: la de menor sort_order.
     */
    private function buscarFaq(string $texto): ?ChatbotFaq
    {
        $normalizado = $this->normalizar($texto);

        $mejor = null;
        $mejorPuntaje = 0;

        foreach (ChatbotFaq::where('is_active', true)->orderBy('sort_order')->get() as $faq) {
            $puntaje = 0;

            foreach ($faq->keywordList() as $keyword) {
                $k = $this->normalizar($keyword);

                if ($k !== '' && str_contains($normalizado, $k)) {
                    // Las claves más largas son señales más fuertes que una palabra suelta.
                    $puntaje += mb_strlen($k);
                }
            }

            if ($puntaje > $mejorPuntaje) {
                $mejorPuntaje = $puntaje;
                $mejor = $faq;
            }
        }

        return $mejor;
    }

    /**
     * Minúsculas y sin acentos, para que "cuánto" matchee "cuanto".
     */
    private function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));

        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function respuesta(string $texto, string $tipo, array $extra = []): array
    {
        return array_merge([
            'reply' => $texto,
            'type' => $tipo,
            'suggestions' => $this->sugerencias(),
        ], $extra);
    }
}
