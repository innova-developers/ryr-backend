<?php

namespace App\Services;

use App\Shared\Models\Commission;
use App\Shared\Models\FeedbackSurvey;
use Illuminate\Support\Str;

class FeedbackService
{
    public function __construct(private WhatsAppService $whatsAppService) {}

    public function createSurveyForCommission(Commission $commission): ?FeedbackSurvey
    {
        $customer = $commission->client;
        if (!$customer) return null;

        $existing = FeedbackSurvey::where('commission_id', $commission->id)->first();
        if ($existing) return $existing;

        $token = Str::random(64);

        $survey = FeedbackSurvey::create([
            'commission_id' => $commission->id,
            'customer_id' => $customer->id,
            'franchise_id' => $commission->franchise_id,
            'status' => 'pending',
            'token' => $token,
            'sent_at' => now(),
        ]);

        $this->sendSurveyWhatsApp($survey, $customer, $commission);

        return $survey;
    }

    public function respondToSurvey(string $token, int $rating, ?string $comment = null): ?FeedbackSurvey
    {
        $survey = FeedbackSurvey::where('token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$survey) return null;

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

    private function sendSurveyWhatsApp(FeedbackSurvey $survey, $customer, Commission $commission): void
    {
        if (empty($customer->mobile)) return;

        $feedbackUrl = config('app.url') . "/feedback/{$survey->token}";

        $message = "🚚 *RYR Comisiones - Tu opinión nos importa*\n\n";
        $message .= "Hola {$customer->name},\n\n";
        $message .= "Tu envío #{$commission->id} fue entregado.\n";
        $message .= "¿Cómo fue tu experiencia?\n\n";
        $message .= "📝 Dejanos tu opinión acá:\n";
        $message .= "{$feedbackUrl}\n\n";
        $message .= "¡Gracias por confiar en RYR! 🙏";

        $this->whatsAppService->sendMessage($customer->mobile, $message);
    }
}
