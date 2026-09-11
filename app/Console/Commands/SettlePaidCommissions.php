<?php

namespace App\Console\Commands;

use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Models\Commission;
use App\Shared\Models\CurrentAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Saneo de comisiones que quedaron colgadas en PAGO_VALIDACION.
 *
 * RC-522: hasta este sprint, el pase a PAGO_CONFIRMADO se evaluaba únicamente al
 * confirmar un crédito y exigía saldo exactamente 0. Un pago confirmado antes de que
 * la comisión generara su débito —o un cliente que quedaba con saldo a favor— dejaba
 * la comisión en PAGO_VALIDACION para siempre, y el cliente seguía apareciendo con
 * deuda en el pool de cobranzas. El fix corrige el flujo hacia adelante; este comando
 * limpia lo que ya quedó mal.
 *
 * Es idempotente: sólo toca clientes cuyo saldo de cuenta corriente ya no es deudor.
 *
 * Correr primero en seco:
 *   php artisan commissions:settle-paid --dry-run
 */
class SettlePaidCommissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:settle-paid {--dry-run : Muestra lo que haría sin escribir nada}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra las comisiones en PAGO_VALIDACION de clientes que ya no tienen deuda (RC-522)';

    public function __construct(
        private readonly CurrentAccountRepository $currentAccountRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $customerIds = Commission::query()
            ->where('status', CommissionStatus::PAGO_VALIDACION->value)
            ->where('total', '>', 0)
            ->distinct()
            ->pluck('client_id')
            ->filter()
            ->all();

        $this->info(sprintf('Clientes con comisiones en PAGO_VALIDACION: %d', count($customerIds)));

        $clientesTocados = 0;
        $comisionesCerradas = 0;

        foreach ($customerIds as $customerId) {
            $cerradas = $this->cerrarComisiones($customerId, $dryRun);

            if ($cerradas === 0) {
                continue;
            }

            $clientesTocados++;
            $comisionesCerradas += $cerradas;

            $this->line(sprintf(
                '  cliente %d · saldo %s · %d comisión(es)',
                $customerId,
                number_format($this->currentAccountRepository->getCustomerBalance($customerId), 2, ',', '.'),
                $cerradas
            ));
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d comisión(es) de %d cliente(s).',
            $dryRun ? '[dry-run] Se cerrarían' : 'Cerradas',
            $comisionesCerradas,
            $clientesTocados
        ));

        // Dato de contexto: movimientos de crédito sin confirmar. No los toca el comando
        // —confirmar un pago es decisión humana— pero explican parte de lo que se ve en
        // el pool.
        $creditosPendientes = CurrentAccount::where('type', 'credit')
            ->where('status', CurrentAccountStatus::PENDIENTE->value)
            ->count();

        if ($creditosPendientes > 0) {
            $this->warn(sprintf(
                'Quedan %d pago(s) en estado PENDIENTE sin confirmar: no impactan el saldo hasta que alguien los confirme.',
                $creditosPendientes
            ));
        }

        return self::SUCCESS;
    }

    /**
     * En seco se corre la misma imputación y se deshace, así el conteo del dry-run es
     * exactamente lo que haría la corrida real y no una regla escrita dos veces.
     */
    private function cerrarComisiones(int $customerId, bool $dryRun): int
    {
        if (! $dryRun) {
            return $this->currentAccountRepository->settleCustomerIfPaid($customerId);
        }

        DB::beginTransaction();

        try {
            return $this->currentAccountRepository->settleCustomerIfPaid($customerId);
        } finally {
            DB::rollBack();
        }
    }
}
