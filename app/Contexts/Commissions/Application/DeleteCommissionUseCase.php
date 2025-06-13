<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Shared\Models\CommissionLog;
use Illuminate\Support\Facades\Auth;

class DeleteCommissionUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(int $id): void
    {
        try {
            $commission = $this->commissionsRepository->findById($id);

            $dto = new CreateCommissionLogDTO(
                commissionId: $id,
                userId: Auth::id(),
                previousStatus: $commission->status,
                newStatus: 'ELIMINADO',
                details: 'Comisión eliminada'
            );

            $this->commissionsRepository->createLog($dto);

            $this->commissionsRepository->delete($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
