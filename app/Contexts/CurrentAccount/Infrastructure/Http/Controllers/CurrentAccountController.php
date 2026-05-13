<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Http\Controllers;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\UseCases\ConfirmTransactionUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\CreateCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\DeleteCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCustomerBalanceUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCustomerTransactionsUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\UpdateCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Infrastructure\Http\Requests\CreateCurrentAccountRequest;
use App\Contexts\CurrentAccount\Infrastructure\Http\Requests\UpdateCurrentAccountRequest;
use App\Contexts\CurrentAccount\Infrastructure\Http\Resources\CurrentAccountResource;
use App\Shared\Models\Commission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CurrentAccountController extends Controller
{
    public function __construct(
        private readonly CreateCurrentAccountUseCase $createUseCase,
        private readonly UpdateCurrentAccountUseCase $updateUseCase,
        private readonly DeleteCurrentAccountUseCase $deleteUseCase,
        private readonly GetCurrentAccountUseCase $getUseCase,
        private readonly GetCustomerTransactionsUseCase $getTransactionsUseCase,
        private readonly GetCustomerBalanceUseCase $getBalanceUseCase,
        private readonly ConfirmTransactionUseCase $confirmTransactionUseCase,
    ) {
    }

    public function store(CreateCurrentAccountRequest $request): JsonResponse
    {
        try {
            $dto = CreateCurrentAccountDTO::fromArray($request->validated());
            $dto = new CreateCurrentAccountDTO(
                customerId: $dto->customerId,
                type: $dto->type,
                amount: $dto->amount,
                description: $dto->description,
                reference: $dto->reference,
                transactionDate: $dto->transactionDate,
                paymentMethod: $dto->paymentMethod,
                observations: $dto->observations,
                userId: auth()->id(),
            );

            $currentAccount = ($this->createUseCase)($dto);

            return response()->json($currentAccount, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la transacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $currentAccount = ($this->getUseCase)($id);

            if (! $currentAccount) {
                return response()->json([
                    'message' => 'Transacción no encontrada',
                ], 404);
            }

            return response()->json($currentAccount);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener la transacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateCurrentAccountRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['id'] = $id;

            $dto = UpdateCurrentAccountDTO::fromArray($data);
            $currentAccount = ($this->updateUseCase)($dto);

            return response()->json($currentAccount);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar la transacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $deleted = ($this->deleteUseCase)($id);

            if (! $deleted) {
                return response()->json([
                    'message' => 'Transacción no encontrada',
                ], 404);
            }

            return response()->json([
                'message' => 'Transacción eliminada exitosamente',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar la transacción: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerTransactions(Request $request, int $customerId): JsonResponse
    {
        try {
            $filter = CurrentAccountFilterDTO::fromArray($request->all());
            $transactions = ($this->getTransactionsUseCase)($customerId, $filter);

            // Para transacciones tipo "debit" con referencia "COM-XXX", usar la fecha de la comisión
            $debitTransactionsWithCommission = $transactions->getCollection()->filter(function ($transaction) {
                return $transaction->type === 'debit'
                    && $transaction->reference
                    && preg_match('/^COM-(\d+)$/', $transaction->reference);
            });

            if ($debitTransactionsWithCommission->isNotEmpty()) {
                // Extraer IDs de comisiones
                $commissionIds = $debitTransactionsWithCommission->map(function ($transaction) {
                    if (preg_match('/^COM-(\d+)$/', $transaction->reference, $matches)) {
                        return (int) $matches[1];
                    }

                    return null;
                })->filter()->unique()->values()->toArray();

                // Obtener las comisiones y crear un mapa de ID -> fecha
                $commissions = Commission::whereIn('id', $commissionIds)
                    ->pluck('date', 'id');

                // Actualizar transaction_date en las transacciones tipo debit
                $transactions->getCollection()->transform(function ($transaction) use ($commissions) {
                    if ($transaction->type === 'debit'
                        && $transaction->reference
                        && preg_match('/^COM-(\d+)$/', $transaction->reference, $matches)) {
                        $commissionId = (int) $matches[1];
                        if ($commissions->has($commissionId)) {
                            $transaction->transaction_date = $commissions->get($commissionId);
                        }
                    }

                    return $transaction;
                });
            }

            // Usar Resource para asegurar que verified_by y verified_at se incluyan
            $transactions->getCollection()->transform(function ($transaction) {
                return new CurrentAccountResource($transaction);
            });

            return response()->json($transactions);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las transacciones: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCustomerBalance(int $customerId): JsonResponse
    {
        try {
            $balance = ($this->getBalanceUseCase)($customerId);

            return response()->json([
                'customer_id' => $customerId,
                'balance' => $balance,
                'formatted_balance' => '$' . number_format($balance, 2, ',', '.'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el saldo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirma un ingreso pendiente (cambia el estado de PENDIENTE a OK)
     */
    public function confirmTransaction(int $id): JsonResponse
    {
        try {
            $transaction = ($this->confirmTransactionUseCase)($id);

            return response()->json([
                'success' => true,
                'message' => 'Ingreso confirmado correctamente',
                'data' => $transaction,
            ], 200);
        } catch (\Exception $e) {
            $statusCode = str_contains($e->getMessage(), 'no encontrada') ? 404 : 400;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
