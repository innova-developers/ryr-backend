<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Repositories;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Models\CurrentAccount;
use Illuminate\Pagination\LengthAwarePaginator;

class CurrentAccountEloquentRepository implements CurrentAccountRepository
{
    public function create(CreateCurrentAccountDTO $dto): CurrentAccount
    {
        // Obtener el saldo actual del cliente
        $currentBalance = $this->getCustomerBalance($dto->customerId);

        // Calcular el nuevo saldo
        $newBalance = match ($dto->type) {
            'credit' => $currentBalance + $dto->amount,
            'debit' => $currentBalance - $dto->amount,
            default => $currentBalance,
        };

        return CurrentAccount::create([
            'customer_id' => $dto->customerId,
            'type' => $dto->type,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'reference' => $dto->reference,
            'transaction_date' => $dto->transactionDate,
            'balance' => $newBalance,
            'payment_method' => $dto->paymentMethod,
            'observations' => $dto->observations,
            'user_id' => $dto->userId,
        ]);
    }

    public function update(UpdateCurrentAccountDTO $dto): CurrentAccount
    {
        $currentAccount = $this->findById($dto->id);

        if (! $currentAccount) {
            throw new \Exception('Transacción no encontrada');
        }

        $updateData = [];

        if ($dto->type !== null) {
            $updateData['type'] = $dto->type;
        }
        if ($dto->amount !== null) {
            $updateData['amount'] = $dto->amount;
        }
        if ($dto->description !== null) {
            $updateData['description'] = $dto->description;
        }
        if ($dto->reference !== null) {
            $updateData['reference'] = $dto->reference;
        }
        if ($dto->transactionDate !== null) {
            $updateData['transaction_date'] = $dto->transactionDate;
        }
        if ($dto->paymentMethod !== null) {
            $updateData['payment_method'] = $dto->paymentMethod;
        }
        if ($dto->observations !== null) {
            $updateData['observations'] = $dto->observations;
        }

        $currentAccount->update($updateData);

        // Si se modificó el monto o tipo, recalcular saldos
        if ($dto->amount !== null || $dto->type !== null) {
            $this->recalculateBalances($currentAccount->customer_id);
        }

        return $currentAccount->fresh();
    }

    public function delete(int $id): bool
    {
        $currentAccount = $this->findById($id);

        if (! $currentAccount) {
            return false;
        }

        $customerId = $currentAccount->customer_id;
        $deleted = $currentAccount->delete();

        if ($deleted) {
            $this->recalculateBalances($customerId);
        }

        return $deleted;
    }

    public function findById(int $id): ?CurrentAccount
    {
        return CurrentAccount::with(['customer', 'user'])->find($id);
    }

    public function findByCustomerId(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator
    {
        $query = CurrentAccount::with(['customer', 'user'])
            ->where('customer_id', $customerId);

        if ($filter->type) {
            $query->where('type', $filter->type);
        }

        if ($filter->startDate) {
            $query->where('transaction_date', '>=', $filter->startDate);
        }

        if ($filter->endDate) {
            $query->where('transaction_date', '<=', $filter->endDate);
        }

        if ($filter->paymentMethod) {
            $query->where('payment_method', $filter->paymentMethod);
        }

        if ($filter->search) {
            $query->where(function ($q) use ($filter) {
                $q->where('description', 'like', "%{$filter->search}%")
                  ->orWhere('reference', 'like', "%{$filter->search}%")
                  ->orWhere('observations', 'like', "%{$filter->search}%");
            });
        }

        return $query->orderBy('transaction_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->paginate($filter->perPage, ['*'], 'page', $filter->page);
    }

    public function getCustomerBalance(int $customerId): float
    {
        $lastTransaction = CurrentAccount::where('customer_id', $customerId)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    public function getCustomerTransactions(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator
    {
        return $this->findByCustomerId($customerId, $filter);
    }

    private function recalculateBalances(int $customerId): void
    {
        $transactions = CurrentAccount::where('customer_id', $customerId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $balance = 0;

        foreach ($transactions as $transaction) {
            $balance = match ($transaction->type) {
                'credit' => $balance + $transaction->amount,
                'debit' => $balance - $transaction->amount,
                default => $balance,
            };

            $transaction->update(['balance' => $balance]);
        }
    }
}
