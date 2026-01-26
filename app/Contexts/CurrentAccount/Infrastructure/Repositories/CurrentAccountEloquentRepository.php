<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Repositories;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Domain\Repositories\CurrentAccountRepository;
use App\Shared\Enums\CurrentAccountStatus;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\CurrentAccount;
use App\Shared\Models\Customer;
use App\Shared\Models\Commission;
use Illuminate\Pagination\LengthAwarePaginator;

class CurrentAccountEloquentRepository implements CurrentAccountRepository
{
    public function create(CreateCurrentAccountDTO $dto): CurrentAccount
    {
        // Obtener el saldo actual del cliente (solo transacciones con estado OK)
        $currentBalance = $this->getCustomerBalance($dto->customerId);

        // Asignar estado según el tipo: debit = OK, credit = PENDIENTE
        $status = match ($dto->type) {
            'debit' => CurrentAccountStatus::OK,
            'credit' => CurrentAccountStatus::PENDIENTE,
            default => CurrentAccountStatus::PENDIENTE,
        };

        // Calcular el nuevo saldo
        // Solo los débitos (OK) afectan el saldo inmediatamente
        // Los créditos pendientes mantienen el mismo saldo hasta confirmarse
        $newBalance = match ($status) {
            CurrentAccountStatus::OK => match ($dto->type) {
                'debit' => $currentBalance - $dto->amount,
                default => $currentBalance,
            },
            CurrentAccountStatus::PENDIENTE => $currentBalance, // Los créditos pendientes no afectan el saldo
        };

        return CurrentAccount::create([
            'customer_id' => $dto->customerId,
            'type' => $dto->type,
            'status' => $status,
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
        return CurrentAccount::with(['customer', 'user', 'verifiedBy'])->find($id);
    }

    public function findByCustomerId(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator
    {
        $query = CurrentAccount::with(['customer', 'user', 'verifiedBy'])
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
        // Solo considerar transacciones con estado OK para el cálculo del saldo
        $lastTransaction = CurrentAccount::where('customer_id', $customerId)
            ->where('status', CurrentAccountStatus::OK->value)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    public function getCustomerTransactions(int $customerId, CurrentAccountFilterDTO $filter): LengthAwarePaginator
    {
        return $this->findByCustomerId($customerId, $filter);
    }

    public function confirmTransaction(int $id): CurrentAccount
    {
        $transaction = $this->findById($id);

        if (!$transaction) {
            throw new \Exception('Transacción no encontrada');
        }

        // Verificar que la transacción esté en estado PENDIENTE
        if ($transaction->status !== CurrentAccountStatus::PENDIENTE) {
            throw new \Exception('Solo se pueden confirmar transacciones pendientes');
        }

        // Verificar que sea un crédito (ingreso)
        if ($transaction->type !== 'credit') {
            throw new \Exception('Solo se pueden confirmar transacciones de tipo crédito (ingreso)');
        }

        // Cambiar el estado a OK y guardar quién verificó
        $transaction->status = CurrentAccountStatus::OK;
        $transaction->verified_by_user_id = auth()->id();
        $transaction->verified_at = now();
        $transaction->save();

        // Recalcular todos los balances del cliente porque ahora este crédito afecta el saldo
        $this->recalculateBalances($transaction->customer_id);

        // Verificar si el saldo del cliente quedó en 0 y limpiar internal_user_id si es así
        // También marcar todas las comisiones del cliente como PAGO_CONFIRMADO
        $customerBalance = $this->getCustomerBalance($transaction->customer_id);
        if ($customerBalance == 0) {
            Customer::where('id', $transaction->customer_id)->update(['internal_user_id' => null]);
            
            // Marcar todas las comisiones del cliente como PAGO_CONFIRMADO
            // Excluir las que ya están confirmadas o canceladas
            Commission::where('client_id', $transaction->customer_id)
                ->where('status', '!=', CommissionStatus::PAGO_CONFIRMADO->value)
                ->where('status', '!=', CommissionStatus::CANCELADO->value)
                ->update(['status' => CommissionStatus::PAGO_CONFIRMADO->value]);
        }

        return $transaction->fresh();
    }

    private function recalculateBalances(int $customerId): void
    {
        // Solo recalcular balances de transacciones con estado OK
        $transactions = CurrentAccount::where('customer_id', $customerId)
            ->where('status', CurrentAccountStatus::OK->value)
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
