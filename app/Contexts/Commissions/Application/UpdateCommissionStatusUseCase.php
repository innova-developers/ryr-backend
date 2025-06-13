<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Application\DTOs\CreateCommissionLogDTO;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\CommissionLog;
use Illuminate\Support\Facades\Auth;

class UpdateCommissionStatusUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(int $id, CommissionStatus $status, ?string $details = null): void
    {
        try {
            $commission = $this->commissionsRepository->findById($id);
            $previousStatus = $commission->status;

            $this->commissionsRepository->updateStatus($id, $status);

            $dto = new CreateCommissionLogDTO(
                commissionId: $id,
                userId: Auth::id(),
                previousStatus: $previousStatus->value,
                newStatus: $status->value,
                details: $details
            );
            $this->commissionsRepository->createLog($dto);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
