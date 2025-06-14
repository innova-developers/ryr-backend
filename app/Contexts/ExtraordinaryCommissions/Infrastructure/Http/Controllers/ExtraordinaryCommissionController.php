<?php

namespace App\Contexts\ExtraordinaryCommissions\Infrastructure\Http\Controllers;

use App\Contexts\ExtraordinaryCommissions\Application\CreateExtraordinaryCommissionUseCase;
use App\Contexts\ExtraordinaryCommissions\Application\DeleteExtraordinaryCommissionUseCase;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\CreateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\GetExtraordinaryCommissionByOriginAndDestinationDTO;
use App\Contexts\ExtraordinaryCommissions\Application\DTO\UpdateExtraordinaryCommissionDTO;
use App\Contexts\ExtraordinaryCommissions\Application\GetExtraordinaryCommissionByOriginAndDestinationUseCase;
use App\Contexts\ExtraordinaryCommissions\Application\GetExtraordinaryCommissionsUseCase;
use App\Contexts\ExtraordinaryCommissions\Application\GetExtraordinaryCommissionUseCase;
use App\Contexts\ExtraordinaryCommissions\Application\UpdateExtraordinaryCommissionUseCase;
use App\Contexts\ExtraordinaryCommissions\Domain\Repositories\ExtraordinaryCommissionRepository;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Http\Requests\CreateExtraordinaryCommissionRequest;
use App\Contexts\ExtraordinaryCommissions\Infrastructure\Http\Requests\UpdateExtraordinaryCommissionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraordinaryCommissionController
{
    public function __construct(
        private readonly ExtraordinaryCommissionRepository $repository,
    ) {}

    public function index(): JsonResponse
    {
        $useCase = new GetExtraordinaryCommissionsUseCase($this->repository);
        $extraordinaryCommissions = $useCase();
        return response()->json($extraordinaryCommissions);
    }

    public function store(CreateExtraordinaryCommissionRequest $request): JsonResponse
    {
        $useCase = new CreateExtraordinaryCommissionUseCase($this->repository);
        $dto = new CreateExtraordinaryCommissionDTO(
            $request->input('origin'),
            $request->input('destination'),
            $request->input('detail'),
            $request->input('price'),
            $request->input('observations'),

        );
        $commission = $useCase($dto);
        return response()->json($commission, 201);
    }

    public function show(int $id): JsonResponse
    {
        $useCase = new GetExtraordinaryCommissionUseCase($this->repository);
        $extraordinaryCommission = $useCase($id);
        return response()->json($extraordinaryCommission);
    }

    public function update(UpdateExtraordinaryCommissionRequest $request, int $id): JsonResponse
    {
        $useCase = new UpdateExtraordinaryCommissionUseCase($this->repository);
        $dto = new UpdateExtraordinaryCommissionDTO(
            $id,
            $request->input('origin'),
            $request->input('destination'),
            $request->input('detail'),
            $request->input('price'),
            $request->input('observations'),
        );
        $commission = $useCase($dto);
        return response()->json($commission, 204);
    }

    public function destroy(int $id): JsonResponse
    {
        $useCase = new DeleteExtraordinaryCommissionUseCase($this->repository);
        $useCase($id);
        return response()->json([], 204);
    }

    public function getByOriginAndDestination(string $origin, string $destination): JsonResponse
    {
        $useCase = new GetExtraordinaryCommissionByOriginAndDestinationUseCase($this->repository);
        $dto = new GetExtraordinaryCommissionByOriginAndDestinationDTO(
            $origin,
            $destination
        );
        $extraordinaryCommission = $useCase($dto);
        return response()->json($extraordinaryCommission);
    }
}
