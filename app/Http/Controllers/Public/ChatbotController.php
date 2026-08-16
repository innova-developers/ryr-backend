<?php

namespace App\Http\Controllers\Public;

use App\Services\ChatbotService;
use App\Shared\Models\ChatbotFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * RC-490 — Chatbot básico (endpoints públicos y ABM de FAQs).
 *
 * El canal público es anónimo, así que las respuestas no incluyen datos personales.
 */
class ChatbotController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot)
    {
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->chatbot->handle($validated['message']),
        ]);
    }

    /**
     * Preguntas sugeridas para abrir la conversación.
     */
    public function suggestions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->chatbot->sugerencias(8),
        ]);
    }

    // --- ABM (admin) ---

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ChatbotFaq::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $faq = ChatbotFaq::create($this->validated($request));

        return response()->json(['success' => true, 'data' => $faq], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $faq = ChatbotFaq::find($id);

        if (! $faq) {
            return response()->json(['success' => false, 'message' => 'FAQ no encontrada'], 404);
        }

        $faq->update($this->validated($request));

        return response()->json(['success' => true, 'data' => $faq->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $faq = ChatbotFaq::find($id);

        if (! $faq) {
            return response()->json(['success' => false, 'message' => 'FAQ no encontrada'], 404);
        }

        $faq->delete();

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:2000',
            'keywords' => 'required|string|max:1000',
            'category' => 'nullable|string|max:60',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
