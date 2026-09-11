<?php

namespace App\Console\Commands;

use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\Commission;
use App\Shared\Models\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audita que cada comisión facturada coincida con su débito de cuenta corriente.
 *
 * RC-520: "según cliente, no está sumando el IVA al monto (...) ¿hay forma de revisar
 * esto y confirmarlo sin ir sumando una por una?". El caso que reportó AGRO SAN GENARO
 * eran comisiones de $16.940 —$14.000 más IVA— cuyo movimiento de cuenta corriente
 * había quedado en $14.000: el IVA se aplicaba después de calcular el total y el
 * movimiento guardaba el neto. El flujo se corrigió en RC-512, pero los movimientos
 * que ya habían quedado mal siguen mal, y hasta ahora la única forma de encontrarlos
 * era sumar comisión por comisión.
 *
 *   php artisan commissions:audit-current-account
 *   php artisan commissions:audit-current-account --customer=3365
 *   php artisan commissions:audit-current-account --fix
 */
class AuditCurrentAccountAmounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'commissions:audit-current-account
        {--customer= : Auditar un solo cliente}
        {--fix : Corregir los movimientos desfasados dejándolos en el total de la comisión}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compara cada comisión facturada contra su movimiento de cuenta corriente y reporta las diferencias (RC-520)';

    public function __construct(
        private readonly CurrentAccountRepository $currentAccountRepository
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $customerId = $this->option('customer');
        $fix = (bool) $this->option('fix');

        $query = DB::table('commissions as cm')
            ->join('current_accounts as ca', function ($join) {
                $join->on('ca.reference', '=', DB::raw("CONCAT('COM-', cm.id)"))
                    ->whereNull('ca.deleted_at');
            })
            ->whereNull('cm.deleted_at')
            ->whereRaw('ABS(ca.amount - cm.total) > 0.01')
            ->select([
                'cm.id as commission_id',
                'cm.client_id',
                'cm.date',
                'cm.total',
                'cm.iva_amount',
                'cm.iva_applied',
                'ca.id as movement_id',
                'ca.amount as movement_amount',
            ])
            ->orderBy('cm.date');

        if ($customerId) {
            $query->where('cm.client_id', (int) $customerId);
        }

        $filas = $query->get();
        $huerfanas = $this->sinMovimiento($customerId ? (int) $customerId : null);

        if ($filas->isEmpty()) {
            $this->info('Sin diferencias: todos los movimientos coinciden con el total de su comisión.');
            $this->avisarHuerfanas($huerfanas);

            return self::SUCCESS;
        }

        $nombres = Customer::whereIn('id', $filas->pluck('client_id')->unique())
            ->get(['id', 'name', 'last_name'])
            ->keyBy('id');

        $this->table(
            ['Comisión', 'Fecha', 'Cliente', 'Total comisión', 'Movimiento', 'Diferencia', 'IVA'],
            $filas->map(function ($fila) use ($nombres) {
                $cliente = $nombres->get($fila->client_id);

                return [
                    $fila->commission_id,
                    $fila->date,
                    trim(($cliente->name ?? '') . ' ' . ($cliente->last_name ?? '')) ?: $fila->client_id,
                    number_format((float) $fila->total, 2, ',', '.'),
                    number_format((float) $fila->movement_amount, 2, ',', '.'),
                    number_format((float) $fila->total - (float) $fila->movement_amount, 2, ',', '.'),
                    $fila->iva_applied ? number_format((float) $fila->iva_amount, 2, ',', '.') : '-',
                ];
            })->all()
        );

        $diferencia = $filas->sum(fn ($f) => (float) $f->total - (float) $f->movement_amount);

        $this->newLine();
        $this->warn(sprintf(
            '%d movimiento(s) desfasado(s). Diferencia total a favor de R&R: $%s',
            $filas->count(),
            number_format($diferencia, 2, ',', '.')
        ));

        $this->avisarHuerfanas($huerfanas);

        if (! $fix) {
            $this->line('Correr con --fix para dejar cada movimiento en el total de su comisión.');

            return self::SUCCESS;
        }

        // Se corrige por el repositorio y no con un update suelto porque ese camino
        // recalcula los saldos acumulados del cliente; a mano quedarían desfasados.
        foreach ($filas as $fila) {
            $this->currentAccountRepository->update(new UpdateCurrentAccountDTO(
                id: (int) $fila->movement_id,
                type: null,
                amount: (float) $fila->total,
                description: null,
                reference: null,
                transactionDate: null,
                paymentMethod: null,
                observations: null,
            ));
        }

        $this->info(sprintf('%d movimiento(s) corregido(s) y saldos recalculados.', $filas->count()));

        // El total de la comisión pudo cambiar la deuda del cliente: revisar si con eso
        // queda alguna comisión saldada.
        foreach ($filas->pluck('client_id')->unique() as $clientId) {
            $this->currentAccountRepository->settleCustomerIfPaid((int) $clientId);
        }

        return self::SUCCESS;
    }

    private function avisarHuerfanas(int $huerfanas): void
    {
        if ($huerfanas > 0) {
            $this->warn(sprintf(
                '%d comisión(es) facturada(s) sin movimiento de cuenta corriente: no le figuran al cliente en la cuenta.',
                $huerfanas
            ));
        }
    }

    /**
     * Comisiones facturadas que ni siquiera tienen movimiento de cuenta corriente.
     * No las corrige el comando —crear el débito es decisión de quien factura— pero
     * conviene verlas al auditar.
     */
    private function sinMovimiento(?int $customerId): int
    {
        $query = Commission::query()
            ->whereIn('status', ['PAGO_VALIDACION', 'PAGO_CONFIRMADO'])
            ->where('total', '>', 0)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('current_accounts')
                    ->whereColumn('current_accounts.reference', DB::raw("CONCAT('COM-', commissions.id)"))
                    ->whereNull('current_accounts.deleted_at');
            });

        if ($customerId) {
            $query->where('client_id', $customerId);
        }

        return $query->count();
    }
}
