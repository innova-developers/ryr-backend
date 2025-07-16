<?php

namespace App\Contexts\CurrentAccount\Infrastructure\Http\Controllers;

use App\Contexts\CurrentAccount\Application\DTO\CreateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\DTO\CurrentAccountFilterDTO;
use App\Contexts\CurrentAccount\Application\DTO\UpdateCurrentAccountDTO;
use App\Contexts\CurrentAccount\Application\UseCases\CreateCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\DeleteCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCustomerBalanceUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\GetCustomerTransactionsUseCase;
use App\Contexts\CurrentAccount\Application\UseCases\UpdateCurrentAccountUseCase;
use App\Contexts\CurrentAccount\Infrastructure\Http\Requests\CreateCurrentAccountRequest;
use App\Contexts\CurrentAccount\Infrastructure\Http\Requests\UpdateCurrentAccountRequest;
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
}
