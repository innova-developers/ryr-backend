<?php

namespace App\Services;

use App\Mail\FeedbackSurveyMail;
use App\Shared\Models\Commission;
use App\Shared\Models\FeedbackSurvey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class FeedbackService
{
    public function __construct(private WhatsAppService $whatsAppService)
    {
    }

    public function createSurveyForCommission(Commission $commission): ?FeedbackSurvey
    {
        $customer = $commission->client;
        if (! $customer) {
            return null;
        }

        $existing = FeedbackSurvey::where('commission_id', $commission->id)->first();
        if ($existing) {
            return $existing;
        }

        $token = Str::random(64);

        $survey = FeedbackSurvey::create([
            'commission_id' => $commission->id,
            'customer_id' => $customer->id,
            'franchise_id' => $commission->franchise_id,
            'status' => 'pending',
            'token' => $token,
            'sent_at' => now(),
        ]);

        // Al entregar se manda por los dos canales disponibles: si el cliente no
        // tiene alguno, sendSurvey() lo omite sin romper.
        $this->sendSurvey($survey, ['whatsapp', 'email']);

        return $survey;
    }

    public function respondToSurvey(string $token, int $rating, ?string $comment = null): ?FeedbackSurvey
    {
        $survey = FeedbackSurvey::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (! $survey) {
            return null;
        }

        $survey->update([
            'rating' => $rating,
            'comment' => $comment,
            'status' => 'responded',
            'responded_at' => now(),
        ]);

        return $survey;
    }

    public function getDashboardStats(?int $franchiseId = null): array
    {
        $query = FeedbackSurvey::where('status', 'responded');

        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }

        $surveys = $query->get();

        $totalSentAll = FeedbackSurvey::when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId))->count();

        if ($surveys->isEmpty()) {
            return [
                'total_sent' => $totalSentAll,
                'total_responses' => 0,
                'average_rating' => 0,
                'rating_distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                'pending_count' => FeedbackSurvey::where('status', 'pending')->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId))->count(),
                'response_rate' => 0,
                'recent_feedback' => [],
            ];
        }

        $totalSent = $totalSentAll;
        $pendingCount = FeedbackSurvey::where('status', 'pending')->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId))->count();

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($surveys as $s) {
            if ($s->rating >= 1 && $s->rating <= 5) {
                $distribution[$s->rating]++;
            }
        }

        $recent = FeedbackSurvey::where('status', 'responded')
            ->when($franchiseId, fn ($q) => $q->where('franchise_id', $franchiseId))
            ->with('customer:id,name,last_name')
            ->orderByDesc('responded_at')
            ->limit(10)
            ->get()
            ->map(fn ($f) => [
                'id' => $f->id,
                'rating' => $f->rating,
                'comment' => $f->comment,
                'customer_name' => ($f->customer->name ?? '') . ' ' . ($f->customer->last_name ?? ''),
                'responded_at' => $f->responded_at?->toDateTimeString(),
            ]);

        return [
            'total_sent' => $totalSent,
            'total_responses' => $surveys->count(),
            'average_rating' => round($surveys->avg('rating'), 1),
            'rating_distribution' => $distribution,
            'pending_count' => $pendingCount,
            'response_rate' => $totalSent > 0 ? round(($surveys->count() / $totalSent) * 100, 1) : 0,
            'recent_feedback' => $recent,
        ];
    }

    /**
     * RC-507 — Envía (o reenvía) la encuesta por los canales pedidos.
     *
     * Devuelve el resultado por canal para que la pantalla pueda avisar cuándo el
     * cliente no tiene el dato necesario, en vez de fallar en silencio.
     *
     * @param  array<int, string>  $canales  whatsapp | email
     * @return array{enviados: array<int, string>, omitidos: array<string, string>}
     */
    public function sendSurvey(FeedbackSurvey $survey, array $canales = ['whatsapp']): array
    {
        $customer = $survey->customer;
        $commissionId = $survey->commission_id;

        $enviados = [];
        $omitidos = [];

        if (! $customer) {
            return ['enviados' => [], 'omitidos' => ['cliente' => 'La encuesta no tiene cliente asociado']];
        }

        $feedbackUrl = rtrim(config('app.frontend_url'), '/') . "/feedback/{$survey->token}";
        $nombre = trim($customer->name . ' ' . ($customer->last_name ?? '')) ?: 'cliente';

        if (in_array('whatsapp', $canales, true)) {
            // Mismo criterio que el resto de las notificaciones: mobile y si no, phone.
            $telefono = $customer->mobile ?: $customer->phone;

            if (empty($telefono)) {
                $omitidos['whatsapp'] = 'El cliente no tiene teléfono cargado';
            } else {
                try {
                    $this->whatsAppService->sendMessage($telefono, $this->mensajeWhatsApp($nombre, $commissionId, $feedbackUrl));
                    $enviados[] = 'whatsapp';
                } catch (\Throwable $e) {
                    $omitidos['whatsapp'] = 'No se pudo enviar el WhatsApp';
                    Log::warning('Fallo el envío de la encuesta por WhatsApp', [
                        'survey_id' => $survey->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        if (in_array('email', $canales, true)) {
            if (empty($customer->email)) {
                $omitidos['email'] = 'El cliente no tiene email cargado';
            } else {
                try {
                    Mail::to($customer->email)->send(new FeedbackSurveyMail($nombre, $commissionId, $feedbackUrl));
                    $enviados[] = 'email';
                } catch (\Throwable $e) {
                    $omitidos['email'] = 'No se pudo enviar el email';
                    Log::warning('Fallo el envío de la encuesta por email', [
                        'survey_id' => $survey->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return ['enviados' => $enviados, 'omitidos' => $omitidos];
    }

    /**
     * Reenvío manual desde el dashboard. No toca el token: el link anterior sigue valiendo.
     *
     * @param  array<int, string>  $canales
     * @return array{enviados: array<int, string>, omitidos: array<string, string>}
     */
    public function resendSurvey(FeedbackSurvey $survey, array $canales): array
    {
        $resultado = $this->sendSurvey($survey, $canales);

        if (! empty($resultado['enviados'])) {
            $survey->update([
                'resend_count' => (int) $survey->resend_count + 1,
                'last_resent_at' => now(),
                'last_resent_channels' => implode(',', $resultado['enviados']),
            ]);
        }

        return $resultado;
    }

    private function mensajeWhatsApp(string $nombre, int $commissionId, string $feedbackUrl): string
    {
        $mensaje = "🚚 *RYR Comisiones - Tu opinión nos importa*\n\n";
        $mensaje .= "Hola {$nombre},\n\n";
        $mensaje .= "Tu envío #{$commissionId} fue entregado.\n";
        $mensaje .= "¿Cómo fue tu experiencia?\n\n";
        $mensaje .= "📝 Dejanos tu opinión acá:\n";
        $mensaje .= "{$feedbackUrl}\n\n";
        $mensaje .= "¡Gracias por confiar en RYR! 🙏";

        return $mensaje;
    }
}
