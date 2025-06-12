<?php

namespace App\Contexts\Commissions\Infrastructure\Http\Controllers;

use App\Contexts\Commissions\Application\CreateCommissionUseCase;
use App\Contexts\Commissions\Application\DTOs\CreateCommissionDTO;
use App\Contexts\Commissions\Application\DTOs\ListCommissionsFiltersDTO;
use App\Contexts\Commissions\Application\GetCommissionUseCase;
use App\Contexts\Commissions\Application\ListCommissionsUseCase;
use App\Contexts\Commissions\Domain\Exceptions\CommissionNotFoundException;
use App\Contexts\Commissions\Domain\Repositories\CommissionsRepository;
use App\Contexts\Commissions\Infrastructure\Http\Requests\CreateCommissionRequest;
use App\Contexts\Customers\Domain\Repositories\CustomerRepository;
use App\Contexts\Destinations\Domain\Repositories\DestinationRepository;
use App\Contexts\Users\Domain\Repositories\UserRepository;
use App\Shared\Enums\CommissionStatus;
use App\Shared\Models\Rate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CommissionController extends Controller
{
    private CommissionsRepository $repository;
    private CustomerRepository $customerRepository;
    private DestinationRepository $destinationRepository;
    public function __construct()
    {
        $this->repository = app(CommissionsRepository::class);
        $this->customerRepository = app(CustomerRepository::class);
        $this->destinationRepository = app(DestinationRepository::class);
    }

    public function store(CreateCommissionRequest $request): JsonResponse
    {
        try {
            $dto = CreateCommissionDTO::fromArray($request->validated());
            $useCase = new CreateCommissionUseCase(
                $this->repository,
                $this->customerRepository,
                $this->destinationRepository
            );
            $commission = $useCase($dto);
            return response()->json($commission, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear la comisión',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function getStatuses(): JsonResponse
    {
        $statuses = array_map(fn($status) => [
            'value' => $status->value,
            'label' => $status->name
        ], CommissionStatus::cases());

        return response()->json($statuses, 200);
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $filters = ListCommissionsFiltersDTO::fromArray($request->all());
            $useCase = new ListCommissionsUseCase($this->repository);
            $result = $useCase($filters);
            return response()->json($result, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las comisiones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $useCase = new GetCommissionUseCase($this->repository);
            $commission = $useCase($id);
            return response()->json([
                'data' => $commission
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 404);
        }
    }
}
