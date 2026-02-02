<?php

namespace App\Http\Controllers\Public;

use App\SatisfactionSurvey;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SatisfactionSurveyController
{
    /**
     * Mostrar formulario de encuesta (página pública)
     */
    public function show(string $token)
    {
        $survey = SatisfactionSurvey::where('token', $token)
            ->with(['commission', 'customer'])
            ->first();

        if (!$survey) {
            return response()->json([
                'success' => false,
                'message' => 'Encuesta no encontrada',
            ], 404);
        }

        if ($survey->isResponded()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta encuesta ya ha sido completada',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'survey' => [
                    'id' => $survey->id,
                    'token' => $survey->token,
                    'commission_id' => $survey->commission_id,
                    'customer_name' => $survey->customer->name ?? 'Cliente',
                ],
            ],
        ]);
    }

    /**
     * Enviar respuesta de la encuesta
     */
    public function submit(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $survey = SatisfactionSurvey::where('token', $token)->first();

        if (!$survey) {
            return response()->json([
                'success' => false,
                'message' => 'Encuesta no encontrada',
            ], 404);
        }

        if ($survey->isResponded()) {
            return response()->json([
                'success' => false,
                'message' => 'Esta encuesta ya ha sido completada',
            ], 400);
        }

        $survey->update([
            'rating' => $request->rating,
            'comment' => $request->comment,
            'responded_at' => now(),
        ]);

        Log::info('Satisfaction survey completed', [
            'survey_id' => $survey->id,
            'commission_id' => $survey->commission_id,
            'rating' => $survey->rating,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Gracias por tu feedback. Tu opinión es muy valiosa para nosotros.',
        ]);
    }
}
