<?php

namespace App\Contexts\Commissions\Application\DTOs;

readonly class CreateCommissionLogDTO
{
    public int $commissionId;
    public int $userId;
    public string $previousStatus;
    public string $newStatus;
    public ?string $details;

    public function __construct(
        int $commissionId,
        int $userId,
        string $previousStatus,
        string $newStatus,
        ?string $details = null
    ) {
        $this->commissionId = $commissionId;
        $this->userId = $userId;
        $this->previousStatus = $previousStatus;
        $this->newStatus = $newStatus;
        $this->details = $details;
    }


}
