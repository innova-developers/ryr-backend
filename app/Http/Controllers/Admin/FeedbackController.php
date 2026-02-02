<?php

namespace App\Http\Controllers\Admin;

use App\Services\SatisfactionSurveyService;
use App\SatisfactionSurvey;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FeedbackController
{
    protected $satisfactionSurveyService;

    public function __construct(SatisfactionSurveyService $satisfactionSurveyService)
    {
        $this->satisfactionSurveyService = $satisfactionSurveyService;
    }

    /**
     * Obtener estadísticas de feedback
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $stats = $this->satisfactionSurveyService->getSatisfactionStats();

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading feedback stats: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtener todas las encuestas con filtros
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = SatisfactionSurvey::with(['commission', 'customer'])
                ->orderBy('created_at', 'desc');

            // Filtrar solo las respondidas
            if ($request->has('responded_only') && $request->boolean('responded_only')) {
                $query->whereNotNull('responded_at');
            }

            // Filtrar por rango de fechas
            if ($request->has('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Filtrar por rating
            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            $surveys = $query->paginate(20);

            // Agregar información adicional a cada encuesta
            $surveys->getCollection()->transform(function ($survey) {
                return [
                    'id' => $survey->id,
                    'commission_id' => $survey->commission_id,
                    'customer_name' => $survey->customer->name ?? 'N/A',
                    'customer_email' => $survey->customer->email ?? null,
                    'rating' => $survey->rating,
                    'comment' => $survey->comment,
                    'sent_at' => $survey->sent_at?->toISOString(),
                    'responded_at' => $survey->responded_at?->toISOString(),
                    'is_responded' => $survey->isResponded(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $surveys,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading surveys: ' . $e->getMessage(),
            ], 500);
        }
    }
}
