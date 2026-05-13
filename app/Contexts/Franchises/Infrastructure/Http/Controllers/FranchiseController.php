<?php

namespace App\Contexts\Franchises\Infrastructure\Http\Controllers;

use App\Contexts\Franchises\Application\CreateFranchiseUseCase;
use App\Contexts\Franchises\Application\DeleteFranchiseUseCase;
use App\Contexts\Franchises\Application\DTO\CreateFranchiseDTO;
use App\Contexts\Franchises\Application\DTO\GetFranchisesFiltersDTO;
use App\Contexts\Franchises\Application\DTO\UpdateFranchiseDTO;
use App\Contexts\Franchises\Application\GetFranchiseDashboardUseCase;
use App\Contexts\Franchises\Application\GetFranchisesUseCase;
use App\Contexts\Franchises\Application\GetFranchiseUseCase;
use App\Contexts\Franchises\Application\GetSettlementReportUseCase;
use App\Contexts\Franchises\Application\UpdateFranchiseUseCase;
use App\Contexts\Franchises\Domain\Repositories\FranchiseRepository;
use App\Contexts\Franchises\Infrastructure\Http\Requests\CreateFranchiseRequest;
use App\Contexts\Franchises\Infrastructure\Http\Requests\UpdateFranchiseRequest;
use App\Shared\Enums\UserRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FranchiseController extends Controller
{
    private FranchiseRepository $repository;

    public function __construct()
    {
        $this->repository = app(FranchiseRepository::class);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === UserRole::ADMIN_FRANQUICIA) {
            $franchise = $this->repository->findById($user->franchise_id);
            return response()->json(['data' => [$franchise], 'pagination' => ['total' => 1]]);
        }

        $filters = GetFranchisesFiltersDTO::fromArray($request->all());
        $useCase = new GetFranchisesUseCase($this->repository);

        return response()->json($useCase($filters));
    }

    public function store(CreateFranchiseRequest $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden crear franquicias'], 403);
        }

        $dto = CreateFranchiseDTO::fromArray($request->all());
        $useCase = new CreateFranchiseUseCase($this->repository);

        return response()->json($useCase($dto), 201);
    }

    public function show(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role === UserRole::ADMIN_FRANQUICIA && $user->franchise_id !== $id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $useCase = new GetFranchiseUseCase($this->repository);
        $franchise = $useCase($id);

        if (!$franchise) {
            return response()->json(['message' => 'Franquicia no encontrada'], 404);
        }

        return response()->json($franchise);
    }

    public function update(UpdateFranchiseRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden editar franquicias'], 403);
        }

        $dto = UpdateFranchiseDTO::fromArray($id, $request->all());
        $useCase = new UpdateFranchiseUseCase($this->repository);

        return response()->json($useCase($dto));
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->role->isMatrixAdmin()) {
            return response()->json(['message' => 'Solo administradores de matriz pueden eliminar franquicias'], 403);
        }

        $useCase = new DeleteFranchiseUseCase($this->repository);
        $useCase($id);

        return response()->json(['message' => 'Franquicia eliminada']);
    }

    public function dashboard(int $id, Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role === UserRole::ADMIN_FRANQUICIA && $user->franchise_id !== $id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $useCase = new GetFranchiseDashboardUseCase($this->repository);
        $stats = $useCase($id, $request->input('date_from'), $request->input('date_to'));

        return response()->json($stats);
    }

    public function settlement(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        $user = $request->user();
        if ($user->role === UserRole::ADMIN_FRANQUICIA && $user->franchise_id !== $id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $useCase = new GetSettlementReportUseCase($this->repository);
        $report = $useCase($id, $request->input('date_from'), $request->input('date_to'));

        return response()->json($report);
    }
}
