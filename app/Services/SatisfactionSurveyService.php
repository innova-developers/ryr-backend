<?php

namespace App\Services;

use App\SatisfactionSurvey;
use App\Mail\SatisfactionSurveyMail;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SatisfactionSurveyService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Enviar encuesta de satisfacción cuando una comisión se marca como ENTREGADO
     */
    public function sendSurveyForCommission(Commission $commission): ?SatisfactionSurvey
    {
        try {
            // Verificar que la comisión tenga un cliente asociado
            if (!$commission->client_id) {
                Log::warning('Commission does not have a client', [
                    'commission_id' => $commission->id,
                ]);
                return null;
            }

            // Cargar el cliente
            $customer = $commission->client;
            if (!$customer) {
                Log::warning('Customer not found for commission', [
                    'commission_id' => $commission->id,
                    'client_id' => $commission->client_id,
                ]);
                return null;
            }

            // Verificar si ya existe una encuesta para esta comisión
            $existingSurvey = SatisfactionSurvey::where('commission_id', $commission->id)->first();
            if ($existingSurvey) {
                Log::info('Survey already exists for commission', [
                    'commission_id' => $commission->id,
                    'survey_id' => $existingSurvey->id,
                ]);
                return $existingSurvey;
            }

            // Crear la encuesta
            $survey = SatisfactionSurvey::create([
                'commission_id' => $commission->id,
                'customer_id' => $customer->id,
                'token' => SatisfactionSurvey::generateToken(),
                'sent_at' => now(),
            ]);

            // Enviar por email si el cliente tiene email
            if ($customer->email) {
                try {
                    Mail::to($customer->email)->send(new SatisfactionSurveyMail($commission, $customer, $survey));
                    Log::info('Satisfaction survey email sent', [
                        'commission_id' => $commission->id,
                        'customer_id' => $customer->id,
                        'survey_id' => $survey->id,
                        'email' => $customer->email,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error sending satisfaction survey email', [
                        'commission_id' => $commission->id,
                        'customer_id' => $customer->id,
                        'survey_id' => $survey->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Enviar por WhatsApp si el cliente tiene teléfono
            if ($customer->mobile) {
                try {
                    $surveyUrl = config('app.url', 'http://localhost:8000') . '/survey/' . $survey->token;
                    $message = "¡Hola {$customer->name}! Tu envío #{$commission->id} ha sido entregado exitosamente. " .
                               "Nos encantaría conocer tu opinión. Por favor, completa nuestra breve encuesta: {$surveyUrl}";
                    
                    $this->whatsAppService->sendMessage($customer->mobile, $message);
                    Log::info('Satisfaction survey WhatsApp sent', [
                        'commission_id' => $commission->id,
                        'customer_id' => $customer->id,
                        'survey_id' => $survey->id,
                        'phone' => $customer->mobile,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error sending satisfaction survey WhatsApp', [
                        'commission_id' => $commission->id,
                        'customer_id' => $customer->id,
                        'survey_id' => $survey->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $survey;

        } catch (\Exception $e) {
            Log::error('Error creating satisfaction survey', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Obtener estadísticas de satisfacción
     */
    public function getSatisfactionStats(?int $franchiseId = null): array
    {
        $baseQuery = SatisfactionSurvey::whereNotNull('rating');

        // Si se especifica una franquicia, filtrar por comisiones de esa franquicia
        // Nota: Esto requeriría una relación commission -> branch -> franchise
        // Por ahora, retornamos estadísticas globales

        $totalResponses = $baseQuery->count();
        $averageRating = $totalResponses > 0 ? $baseQuery->avg('rating') : 0;
        
        // Consulta separada para la distribución de ratings (sin ordenar por responded_at)
        $ratingDistribution = SatisfactionSurvey::whereNotNull('rating')
            ->selectRaw('rating, COUNT(*) as count')
            ->groupBy('rating')
            ->orderBy('rating', 'asc')
            ->pluck('count', 'rating')
            ->toArray();

        // Consulta separada para las encuestas recientes
        $recentSurveys = SatisfactionSurvey::whereNotNull('rating')
            ->with(['commission', 'customer'])
            ->orderBy('responded_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($survey) {
                return [
                    'id' => $survey->id,
                    'commission_id' => $survey->commission_id,
                    'customer_name' => $survey->customer->name ?? 'N/A',
                    'rating' => $survey->rating,
                    'comment' => $survey->comment,
                    'responded_at' => $survey->responded_at->toISOString(),
                ];
            })
            ->toArray();

        return [
            'total_responses' => $totalResponses,
            'average_rating' => round($averageRating, 2),
            'rating_distribution' => $ratingDistribution,
            'recent_surveys' => $recentSurveys,
        ];
    }
}
