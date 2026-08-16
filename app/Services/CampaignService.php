<?php

namespace App\Services;

use App\Shared\Enums\CampaignStatus;
use App\Shared\Enums\CurrentAccountStatus;
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

        // RC-485: la categoría de cliente que se ve en la ficha es customers.type
        // (individual = "Cliente común", company = "Empresa"). Campañas filtraba por
        // is_premium, que es otro campo y está en 0 para todos los clientes, así que
        // el filtro "Premium" no podía matchear nada. Ahora se usa el mismo dato.
        if (! empty($filters['type'])) {
            $types = is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $query->whereIn('type', $types);
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

        // Ojo: acá se usa isset() y no empty(), porque 0 es un valor válido de corte
        // (p. ej. "clientes con 0 comisiones") y empty() lo descartaba en silencio.
        if (isset($filters['min_commissions']) && $filters['min_commissions'] !== '') {
            $minCount = (int) $filters['min_commissions'];
            $query->whereHas('commissions', null, '>=', $minCount);
        }

        // RC-488: "monto mínimo" es el total facturado al cliente, que antes no existía
        // como filtro (sólo estaba el saldo de cuenta corriente, que es otra cosa).
        if (isset($filters['min_commission_amount']) && $filters['min_commission_amount'] !== '') {
            $this->whereAmount($query, $this->billedAmountSql(), '>=', $filters['min_commission_amount']);
        }

        if (isset($filters['max_commission_amount']) && $filters['max_commission_amount'] !== '') {
            $this->whereAmount($query, $this->billedAmountSql(), '<=', $filters['max_commission_amount']);
        }

        // El saldo se compara contra la suma de movimientos confirmados. Se usa una
        // subconsulta con COALESCE en vez de whereHas + havingRaw sin groupBy: así los
        // clientes sin movimientos cuentan como saldo 0 en lugar de quedar afuera.
        if (isset($filters['min_balance']) && $filters['min_balance'] !== '') {
            $this->whereAmount($query, $this->balanceSql(), '>=', $filters['min_balance']);
        }

        if (isset($filters['max_balance']) && $filters['max_balance'] !== '') {
            $this->whereAmount($query, $this->balanceSql(), '<=', $filters['max_balance']);
        }

        if (! empty($filters['created_after'])) {
            $query->where('created_at', '>=', $filters['created_after']);
        }

        if (! empty($filters['created_before'])) {
            $query->where('created_at', '<=', $filters['created_before']);
        }

        return $query->get();
    }

    /**
     * Compara una subconsulta de importe contra un valor.
     *
     * El valor se interpola como literal numérico en vez de ligarse como parámetro:
     * PDO liga los bindings como texto y SQLite, que ordena por tipo, considera que
     * cualquier número es menor que cualquier texto. Con binding, "12000 <= '5000'"
     * daba verdadero y el filtro no descartaba a nadie. El cast a float previo hace
     * que la interpolación sea segura.
     */
    private function whereAmount($query, string $sql, string $operator, mixed $value): void
    {
        $query->whereRaw("({$sql}) {$operator} " . (float) $value);
    }

    /**
     * Saldo del cliente: créditos menos débitos sobre los movimientos confirmados.
     * Devuelve 0 (no NULL) para clientes sin movimientos.
     *
     * Las cadenas van entre comillas SIMPLES a propósito: en SQLite las dobles son
     * identificadores y 'credit' se interpretaría como un nombre de columna.
     */
    private function balanceSql(): string
    {
        $ok = CurrentAccountStatus::OK->value;

        return "SELECT COALESCE(SUM(CASE WHEN ca.type = 'credit' THEN ca.amount
                                         WHEN ca.type = 'debit' THEN -ca.amount
                                         ELSE 0 END), 0)
                FROM current_accounts ca
                WHERE ca.customer_id = customers.id AND ca.status = '{$ok}'";
    }

    /**
     * Total facturado al cliente (suma de totales de sus comisiones). 0 si no tiene.
     */
    private function billedAmountSql(): string
    {
        return 'SELECT COALESCE(SUM(c.total), 0) FROM commissions c
                WHERE c.client_id = customers.id AND c.deleted_at IS NULL';
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
