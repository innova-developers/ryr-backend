<?php

namespace App\Contexts\Commissions\Application;

use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;

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
            $this->commissionsRepository->delete($id);
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
