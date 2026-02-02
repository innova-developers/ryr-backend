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
        // Asignar estado según el tipo: debit = OK, credit = PENDIENTE
        $status = match ($dto->type) {
            'debit' => CurrentAccountStatus::OK,
            'credit' => CurrentAccountStatus::PENDIENTE,
            default => CurrentAccountStatus::PENDIENTE,
        };

        // Crear la transacción primero
        $transaction = CurrentAccount::create([
            'customer_id' => $dto->customerId,
            'type' => $dto->type,
            'status' => $status,
            'amount' => $dto->amount,
            'description' => $dto->description,
            'reference' => $dto->reference,
            'transaction_date' => $dto->transactionDate,
            'balance' => 0, // Se calculará después
            'payment_method' => $dto->paymentMethod,
            'observations' => $dto->observations,
            'user_id' => $dto->userId,
        ]);

        // Recalcular todos los balances del cliente en orden temporal
        // Esto asegura que todas las transacciones (OK y PENDIENTE) tengan balances correctos
        $this->recalculateAllBalances($dto->customerId);

        return $transaction->fresh();
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

        // Si se modificó el monto, tipo o fecha, recalcular todos los saldos
        if ($dto->amount !== null || $dto->type !== null || $dto->transactionDate !== null) {
            $this->recalculateAllBalances($currentAccount->customer_id);
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
            // Recalcular todos los balances después de eliminar una transacción
            $this->recalculateAllBalances($customerId);
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
        // Obtener el saldo definitivo (solo transacciones con estado OK)
        // Este es el saldo confirmado que se usa para decisiones de negocio
        $lastTransaction = CurrentAccount::where('customer_id', $customerId)
            ->where('status', CurrentAccountStatus::OK->value)
            ->orderBy('transaction_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        return $lastTransaction ? $lastTransaction->balance : 0;
    }

    /**
     * Obtiene el saldo operativo incluyendo transacciones PENDIENTE
     * Este saldo refleja la situación real considerando pagos aún no confirmados
     */
    public function getCustomerOperationalBalance(int $customerId): float
    {
        // Obtener la última transacción (OK o PENDIENTE) para el saldo operativo
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

        // Recalcular todos los balances del cliente (OK y PENDIENTE) en orden temporal
        // Esto asegura que el saldo se calcule correctamente después de confirmar
        $this->recalculateAllBalances($transaction->customer_id);

        // Verificar si el saldo del cliente quedó en 0 y limpiar internal_user_id si es así
        // También marcar solo las comisiones en PAGO_VALIDACION como PAGO_CONFIRMADO
        $customerBalance = $this->getCustomerBalance($transaction->customer_id);
        if ($customerBalance == 0) {
            Customer::where('id', $transaction->customer_id)->update(['internal_user_id' => null]);
            
            // Marcar solo las comisiones en estado PAGO_VALIDACION como PAGO_CONFIRMADO
            // Esto asegura que solo se confirmen las comisiones que están listas para ser pagadas
            Commission::where('client_id', $transaction->customer_id)
                ->where('status', CommissionStatus::PAGO_VALIDACION->value)
                ->update(['status' => CommissionStatus::PAGO_CONFIRMADO->value]);
        }

        return $transaction->fresh();
    }

    /**
     * Recalcula los balances de todas las transacciones (OK y PENDIENTE) en orden temporal
     * Esto asegura que el saldo se calcule secuencialmente respetando el orden real
     */
    private function recalculateAllBalances(int $customerId): void
    {
        // Obtener TODAS las transacciones ordenadas por fecha e ID
        // Esto incluye tanto OK como PENDIENTE para calcular el saldo operativo correctamente
        $transactions = CurrentAccount::where('customer_id', $customerId)
            ->orderBy('transaction_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $balance = 0;

        foreach ($transactions as $transaction) {
            // Calcular el saldo acumulado respetando el orden temporal
            $balance = match ($transaction->type) {
                'credit' => $balance + $transaction->amount,
                'debit' => $balance - $transaction->amount,
                default => $balance,
            };

            $transaction->update(['balance' => $balance]);
        }
    }

    /**
     * Recalcula solo los balances de transacciones con estado OK
     * Usado cuando se confirma una transacción PENDIENTE
     */
    private function recalculateBalances(int $customerId): void
    {
        // Recalcular todos los balances (incluyendo PENDIENTE) para mantener consistencia
        $this->recalculateAllBalances($customerId);
    }
}
