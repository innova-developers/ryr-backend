<?php

namespace App\Http\Controllers\Admin;

use App\Services\FeedbackService;
use App\Shared\Enums\UserRole;
use App\Shared\Models\FeedbackSurvey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FeedbackController extends Controller
{
    public function __construct(private FeedbackService $feedbackService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $franchiseId = $user->role === UserRole::ADMIN_FRANQUICIA ? $user->franchise_id : null;

        if ($request->has('franchise_id') && $user->role !== UserRole::ADMIN_FRANQUICIA) {
            $franchiseId = $request->input('franchise_id');
        }

        $stats = $this->feedbackService->getDashboardStats($franchiseId);

        return response()->json($stats);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = FeedbackSurvey::with(['customer:id,name,last_name,mobile', 'commission:id'])
            ->orderByDesc('created_at');

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $query->where('franchise_id', $user->franchise_id);
        } elseif ($request->filled('franchise_id')) {
            $query->where('franchise_id', $request->input('franchise_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('rating')) {
            $query->where('rating', $request->input('rating'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->input('min_rating'));
        }

        if ($request->filled('max_rating')) {
            $query->where('rating', '<=', $request->input('max_rating'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('customer', function ($cq) use ($search) {
                    $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })->orWhere('commission_id', $search);
            });
        }

        if ($request->filled('sort_by')) {
            $dir = $request->input('sort_dir', 'desc');
            $query->reorder($request->input('sort_by'), $dir);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return response()->json($query->paginate($perPage));
    }

    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string|size:64',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        $survey = $this->feedbackService->respondToSurvey(
            $validated['token'],
            $validated['rating'],
            $validated['comment'] ?? null
        );

        if (!$survey) {
            return response()->json(['message' => 'Encuesta no encontrada o ya respondida'], 404);
        }

        return response()->json([
            'message' => '¡Gracias por tu opinión!',
            'survey' => $survey,
        ]);
    }

    public function show(string $token): JsonResponse
    {
        $survey = FeedbackSurvey::where('token', $token)
            ->with('customer:id,name,last_name')
            ->first();

        if (!$survey) {
            return response()->json(['message' => 'Encuesta no encontrada'], 404);
        }

        return response()->json([
            'status' => $survey->status,
            'commission_id' => $survey->commission_id,
            'customer_name' => ($survey->customer->name ?? '') . ' ' . ($survey->customer->last_name ?? ''),
            'rating' => $survey->rating,
            'comment' => $survey->comment,
        ]);
    }
}
