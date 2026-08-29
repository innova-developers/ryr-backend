<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\CurrentAccount;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeleteCommissionUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository,
        private readonly CurrentAccountRepository $currentAccountRepository
    ) {}

    /**
     * @throws \Exception
     */
    public function __invoke(int $id): void
    {
        try {
            DB::transaction(function () use ($id) {
                $commission = $this->commissionsRepository->findById($id);

                $dto = new CreateCommissionLogDTO(
                    commissionId: $id,
                    userId: Auth::id(),
                    previousStatus: $commission->status->value,
                    newStatus: 'ELIMINADO',
                    details: 'Comisión eliminada'
                );

                $this->commissionsRepository->createLog($dto);

                // RC-512/RC-516: el débito de cuenta corriente quedaba vivo cuando se
                // borraba la comisión, así que el cliente seguía debiendo una comisión
                // que ya no existe. En producción esto dejó 11 movimientos huérfanos por
                // $190.001 en 10 clientes: Paola Rasadore arrastraba $17.500 de deuda
                // fantasma, y a Norma Blanc le impedía llegar a saldo 0 —con lo cual sus
                // comisiones nunca pasaban a PAGO_CONFIRMADO y no salía nunca del pool.
                $this->deleteCurrentAccountTransaction($id);

                $this->commissionsRepository->delete($id);
            });
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * Da de baja el movimiento de cuenta corriente de la comisión, si existe.
     *
     * Se borra vía repositorio y no con un delete suelto porque ese camino recalcula
     * los saldos acumulados del cliente; borrando a mano quedarían desfasados.
     */
    private function deleteCurrentAccountTransaction(int $commissionId): void
    {
        $movimiento = CurrentAccount::where('reference', "COM-{$commissionId}")->first();

        if (! $movimiento) {
            return;
        }

        $this->currentAccountRepository->delete($movimiento->id);

        Log::info('Movimiento de cuenta corriente dado de baja junto con la comisión', [
            'commission_id' => $commissionId,
            'current_account_id' => $movimiento->id,
            'amount' => $movimiento->amount,
        ]);
    }
}
