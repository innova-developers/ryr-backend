<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Shared\Enums\CommissionStatus;

class UpdateCommissionStatusUseCase
{
    public function __construct(
        private readonly CommissionsRepository $commissionsRepository
    ) {
    }

    /**
     * @throws \Exception
     */
    public function __invoke(int $id, CommissionStatus $status): void
    {
        try {
            $this->commissionsRepository->updateStatus($id, $status);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
