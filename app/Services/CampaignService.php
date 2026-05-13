<?php

namespace App\Services;

use App\Shared\Enums\CampaignStatus;
use App\Shared\Models\Customer;
use App\Shared\Models\WhatsAppCampaign;
use App\Shared\Models\WhatsAppCampaignMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class CampaignService
{
    public function __construct(private WhatsAppService $whatsAppService)
    {
    }

    public function getSegmentedCustomers(array $filters, ?int $franchiseId = null): Collection
    {
        $query = Customer::query();

        if ($franchiseId) {
            $query->where('franchise_id', $franchiseId);
        }

        if (! empty($filters['city'])) {
            $query->where('city', 'LIKE', '%' . $filters['city'] . '%');
        }

        if (isset($filters['is_premium']) && $filters['is_premium'] !== '') {
            $query->where('is_premium', $filters['is_premium'] === 'true' || $filters['is_premium'] === true);
        }

        if (! empty($filters['iva_status'])) {
            $query->where('iva_status', $filters['iva_status']);
        }

        if (! empty($filters['has_mobile'])) {
            $query->where(function ($q) {
                $q->where(fn ($q2) => $q2->whereNotNull('mobile')->where('mobile', '!=', ''))
                  ->orWhere(fn ($q2) => $q2->whereNotNull('phone')->where('phone', '!=', ''));
            });
        }

        if (! empty($filters['has_email'])) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['internal_user_id'])) {
            $query->where('internal_user_id', (int) $filters['internal_user_id']);
        }

        if (! empty($filters['min_commissions'])) {
            $minCount = (int) $filters['min_commissions'];
            $query->whereHas('commissions', null, '>=', $minCount);
        }

        if (! empty($filters['min_balance'])) {
            $query->whereHas('currentAccounts', function ($q) use ($filters) {
                $q->havingRaw('SUM(amount) >= ?', [(float) $filters['min_balance']]);
            });
        }

        if (! empty($filters['max_balance'])) {
            $query->whereHas('currentAccounts', function ($q) use ($filters) {
                $q->havingRaw('SUM(amount) <= ?', [(float) $filters['max_balance']]);
            });
        }

        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        return $query->get();
    }

    public function previewCampaign(WhatsAppCampaign $campaign): array
    {
        $customers = $this->getSegmentedCustomers(
            $campaign->segment_filters ?? [],
            $campaign->franchise_id
        );

        $eligible = $customers->filter(fn ($c) => ! empty($c->mobile) || ! empty($c->phone));

        return [
            'total_customers' => $customers->count(),
            'eligible_with_phone' => $eligible->count(),
            'sample_message' => $this->renderMessage($campaign->message_template, $eligible->first()),
            'customers' => $eligible->take(10)->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'last_name' => $c->last_name,
                'mobile' => $c->mobile,
                'city' => $c->city,
            ])->values(),
        ];
    }

    public function executeCampaign(WhatsAppCampaign $campaign): WhatsAppCampaign
    {
        $customers = $this->getSegmentedCustomers(
            $campaign->segment_filters ?? [],
            $campaign->franchise_id
        );

        $eligible = $customers->filter(fn ($c) => ! empty($c->mobile) || ! empty($c->phone));
        $delayMs = max(500, $campaign->message_delay_ms ?? 1000);
        $hasImage = ! empty($campaign->image_url);

        $campaign->update([
            'status' => CampaignStatus::SENDING,
            'started_at' => now(),
            'total_recipients' => $eligible->count(),
        ]);

        $sentCount = 0;
        $failedCount = 0;
        $isFirst = true;

        foreach ($eligible as $customer) {
            if (! $isFirst) {
                usleep($delayMs * 1000);
            }
            $isFirst = false;

            if (! $this->isWithinSendWindow($campaign)) {
                Log::info('Campaign paused: outside send window', ['campaign_id' => $campaign->id]);

                break;
            }

            $message = $this->renderMessage($campaign->message_template, $customer);
            $sendPhone = ! empty($customer->mobile) ? $customer->mobile : $customer->phone;

            if ($hasImage) {
                $sent = $this->whatsAppService->sendFileByUrl($sendPhone, $campaign->image_url, $message);
            } else {
                $sent = $this->whatsAppService->sendMessage($sendPhone, $message);
            }

            WhatsAppCampaignMessage::create([
                'campaign_id' => $campaign->id,
                'customer_id' => $customer->id,
                'phone' => $sendPhone,
                'message_sent' => $message,
                'status' => $sent ? 'sent' : 'failed',
                'error_message' => $sent ? null : ($this->whatsAppService->getLastError() ?? 'Error desconocido'),
                'sent_at' => $sent ? now() : null,
            ]);

            if ($sent) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        $campaign->update([
            'status' => CampaignStatus::COMPLETED,
            'completed_at' => now(),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        Log::info('Campaign completed', [
            'campaign_id' => $campaign->id,
            'sent' => $sentCount,
            'failed' => $failedCount,
        ]);

        return $campaign->refresh();
    }

    private function isWithinSendWindow(WhatsAppCampaign $campaign): bool
    {
        if (! $campaign->send_time_start && ! $campaign->send_time_end) {
            return true;
        }

        $now = now()->format('H:i:s');

        if ($campaign->send_time_start && $now < $campaign->send_time_start) {
            return false;
        }

        if ($campaign->send_time_end && $now > $campaign->send_time_end) {
            return false;
        }

        return true;
    }

    public function renderMessage(string $template, ?Customer $customer): string
    {
        if (! $customer) {
            return $template;
        }

        return str_replace(
            ['{nombre}', '{apellido}', '{ciudad}', '{dni}', '{email}', '{direccion}', '{saldo}', '{horario_comercial}'],
            [
                $customer->name ?? '',
                $customer->last_name ?? '',
                $customer->city ?? '',
                $customer->dni ?? '',
                $customer->email ?? '',
                $customer->address ?? '',
                number_format($customer->current_balance ?? 0, 2, ',', '.'),
                $customer->business_hours ?? '',
            ],
            $template
        );
    }
}
