<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Services\CommissionNotificationService;
use App\Shared\Enums\CommissionStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UpdateCommissionStatusUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository,
        private readonly CurrentAccountRepository $currentAccountRepository,
        private readonly CommissionNotificationService $notificationService
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(int $id, CommissionStatus $status, ?string $details = null, bool $aCuenta = false): void
    {
        DB::transaction(function () use ($id, $status, $details, $aCuenta) {
            try {
                $commission = $this->commissionsRepository->findById($id);
                $previousStatus = $commission->status;

                $this->commissionsRepository->updateStatus($id, $status);

                // Si a_cuenta es true, crear transacción en cuenta corriente
                if ($aCuenta) {
                    $this->createCurrentAccountTransaction($commission);
                }

                $dto = new CreateCommissionLogDTO(
                    commissionId: $id,
                    userId: Auth::id(),
                    previousStatus: $previousStatus->value,
                    newStatus: $status->value,
                    details: $details
                );
                $this->commissionsRepository->createLog($dto);

                // Enviar notificaciones al cliente
                $this->notificationService->notifyStatusChange(
                    $commission,
                    $previousStatus->value,
                    $status,
                    $details
                );
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage());
            }
        });
    }

    /**
     * Crea una transacción en cuenta corriente por el monto de la comisión
     */
    private function createCurrentAccountTransaction($commission): void
    {
        $currentAccountDTO = new CreateCurrentAccountDTO(
            customerId: $commission->client_id,
            type: 'debit', // Saldo negativo (deuda)
            amount: $commission->total,
            description: "Comisión #{$commission->id} - Actualización de estado",
            reference: "COM-{$commission->id}",
            transactionDate: now()->format('Y-m-d'),
            paymentMethod: null,
            observations: "Comisión registrada a cuenta corriente por actualización de estado",
            userId: Auth::id(),
        );

        $this->currentAccountRepository->create($currentAccountDTO);
    }
}
