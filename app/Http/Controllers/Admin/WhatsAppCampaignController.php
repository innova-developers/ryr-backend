<?php

namespace App\Http\Controllers\Admin;

use App\Services\CampaignService;
use App\Shared\Enums\CampaignStatus;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Customer;
use App\Shared\Models\WhatsAppCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

class WhatsAppCampaignController extends Controller
{
    public function __construct(private CampaignService $campaignService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = WhatsAppCampaign::with('creator:id,name')
            ->orderByDesc('created_at');

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $query->where('franchise_id', $user->franchise_id);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'message_template' => 'required|string|max:2000',
            'image' => 'nullable|file|image|max:5120',
            'segment_filters' => 'nullable',
            'message_delay_ms' => 'nullable|integer|min:500|max:30000',
            'send_time_start' => 'nullable|date_format:H:i',
            'send_time_end' => 'nullable|date_format:H:i',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        $user = $request->user();
        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('campaigns', 'public');
            $imageUrl = Storage::disk('public')->url($path);
        }

        $segmentFilters = $validated['segment_filters'] ?? null;
        if (is_string($segmentFilters)) {
            $segmentFilters = json_decode($segmentFilters, true);
        }

        $campaign = WhatsAppCampaign::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'message_template' => $validated['message_template'],
            'image_url' => $imageUrl,
            'status' => isset($validated['scheduled_at']) ? CampaignStatus::SCHEDULED : CampaignStatus::DRAFT,
            'segment_filters' => $segmentFilters,
            'message_delay_ms' => $validated['message_delay_ms'] ?? 1000,
            'send_time_start' => $validated['send_time_start'] ?? null,
            'send_time_end' => $validated['send_time_end'] ?? null,
            'franchise_id' => $user->role === UserRole::ADMIN_FRANQUICIA ? $user->franchise_id : null,
            'created_by' => $user->id,
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return response()->json($campaign, 201);
    }

    public function show(int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::with(['creator:id,name', 'messages.customer:id,name,last_name'])
            ->findOrFail($id);

        return response()->json($campaign);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::findOrFail($id);

        if (! in_array($campaign->status, [CampaignStatus::DRAFT, CampaignStatus::SCHEDULED])) {
            return response()->json(['message' => 'Solo se pueden editar campañas en borrador o programadas'], 422);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'message_template' => 'sometimes|string|max:2000',
            'image' => 'nullable|file|image|max:5120',
            'remove_image' => 'nullable|boolean',
            'segment_filters' => 'nullable',
            'message_delay_ms' => 'nullable|integer|min:500|max:30000',
            'send_time_start' => 'nullable|date_format:H:i',
            'send_time_end' => 'nullable|date_format:H:i',
            'scheduled_at' => 'nullable|date|after:now',
        ]);

        if ($request->hasFile('image')) {
            if ($campaign->image_url) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $campaign->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('campaigns', 'public');
            $validated['image_url'] = Storage::disk('public')->url($path);
        } elseif ($request->boolean('remove_image')) {
            if ($campaign->image_url) {
                $oldPath = str_replace(Storage::disk('public')->url(''), '', $campaign->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $validated['image_url'] = null;
        }
        unset($validated['image'], $validated['remove_image']);

        if (isset($validated['segment_filters']) && is_string($validated['segment_filters'])) {
            $validated['segment_filters'] = json_decode($validated['segment_filters'], true);
        }

        $campaign->update($validated);

        return response()->json($campaign);
    }

    public function destroy(int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::findOrFail($id);

        if ($campaign->status === CampaignStatus::SENDING) {
            return response()->json(['message' => 'No se puede eliminar una campaña en envío'], 422);
        }

        $campaign->delete();

        return response()->json(null, 200);
    }

    public function preview(int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::findOrFail($id);
        $preview = $this->campaignService->previewCampaign($campaign);

        return response()->json($preview);
    }

    public function send(int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::findOrFail($id);

        if (! in_array($campaign->status, [CampaignStatus::DRAFT, CampaignStatus::SCHEDULED])) {
            return response()->json(['message' => 'Solo se pueden enviar campañas en borrador o programadas'], 422);
        }

        $result = $this->campaignService->executeCampaign($campaign);

        return response()->json([
            'message' => 'Campaña enviada',
            'campaign' => $result,
        ]);
    }

    public function cancel(int $id): JsonResponse
    {
        $campaign = WhatsAppCampaign::findOrFail($id);

        if ($campaign->status === CampaignStatus::COMPLETED) {
            return response()->json(['message' => 'No se puede cancelar una campaña completada'], 422);
        }

        $campaign->update(['status' => CampaignStatus::CANCELLED]);

        return response()->json($campaign);
    }

    public function segmentPreview(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'city' => 'nullable|string',
            'is_premium' => 'nullable',
            'iva_status' => 'nullable|in:auto,always,exempt',
            'has_mobile' => 'nullable',
            'has_email' => 'nullable',
            'branch_id' => 'nullable|integer',
            'internal_user_id' => 'nullable|integer',
            'min_commissions' => 'nullable|integer|min:0',
            'min_balance' => 'nullable|numeric',
            'max_balance' => 'nullable|numeric',
            'created_after' => 'nullable|date',
            'created_before' => 'nullable|date',
        ]);

        $user = $request->user();
        $franchiseId = $user->role === UserRole::ADMIN_FRANQUICIA ? $user->franchise_id : null;

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(10, (int) $request->query('per_page', 20)));

        $filtersWithoutMobile = $filters;
        unset($filtersWithoutMobile['has_mobile']);
        $allCustomers = $this->campaignService->getSegmentedCustomers($filtersWithoutMobile, $franchiseId);
        $eligible = $allCustomers->filter(fn ($c) => ! empty($c->mobile) || ! empty($c->phone));

        $paginated = $allCustomers->slice(($page - 1) * $perPage, $perPage);

        return response()->json([
            'total' => $allCustomers->count(),
            'eligible_with_phone' => $eligible->count(),
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => max(1, (int) ceil($allCustomers->count() / $perPage)),
            'customers' => $paginated->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'last_name' => $c->last_name,
                'mobile' => $c->mobile,
                'phone' => $c->phone,
                'city' => $c->city,
                'email' => $c->email,
                'is_premium' => $c->is_premium,
                'has_phone' => ! empty($c->mobile) || ! empty($c->phone),
            ])->values(),
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $search = $request->query('q', '');

        $query = Customer::query()
            ->whereNotNull('city')
            ->where('city', '!=', '');

        if ($search) {
            $query->where('city', 'LIKE', '%' . $search . '%');
        }

        $cities = $query->select('city')
            ->distinct()
            ->orderBy('city')
            ->limit(30)
            ->pluck('city');

        return response()->json($cities);
    }
}
