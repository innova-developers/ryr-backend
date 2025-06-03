<?php

namespace App\Contexts\Branchs\Infrastructure\Http\Controllers;

use App\Contexts\Branchs\Application\CreateBranchUseCase;
use App\Contexts\Branchs\Application\DeleteBranchUseCase;
use App\Contexts\Branchs\Application\DTO\CreateBranchDTO;
use App\Contexts\Branchs\Application\DTO\UpdateBranchDTO;
use App\Contexts\Branchs\Application\GetBranchsUseCase;
use App\Contexts\Branchs\Application\GetBranchUseCase;
use App\Contexts\Branchs\Application\UpdateBranchUseCase;
use App\Contexts\Branchs\Domain\Repositories\BranchRepository;
use App\Contexts\Branchs\Infrastructure\Http\Requests\CreateBranchRequest;
use App\Contexts\Branchs\Infrastructure\Http\Requests\UpdateBranchRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class BranchController extends Controller
{
    private BranchRepository $repository;

    public function __construct()
    {
        $this->repository = app(BranchRepository::class);
    }
    public function index(): JsonResponse
    {
        $useCase = new GetBranchsUseCase(
            $this->repository
        );
        $branches = $useCase();

        return response()->json($branches);
    }

    public function store(CreateBranchRequest $request): JsonResponse
    {
        $dto = new CreateBranchDTO(
            $request->input('name'),
            $request->input('address'),
            $request->input('phone'),
            $request->input('schedule')
        );
        $useCase = new CreateBranchUseCase(
            $this->repository
        );
        $branch = $useCase($dto);

        return response()->json($branch, 201);
    }

    public function show($id): JsonResponse
    {
        $useCase = new GetBranchUseCase(
            $this->repository
        );
        $branch = $useCase($id);

        return response()->json($branch);
    }

    public function update(UpdateBranchRequest $request, int $id): JsonResponse
    {
        $dto = new UpdateBranchDTO(
            $id,
            $request->input('name'),
            $request->input('address'),
            $request->input('phone'),
            $request->input('schedule')
        );
        $useCase = new UpdateBranchUseCase(
            $this->repository
        );
        $branch = $useCase($dto);

        return response()->json($branch);
    }

    public function destroy($id): JsonResponse
    {
        $useCase = new DeleteBranchUseCase(
            $this->repository
        );
        $useCase($id);

        return response()->json(['message' => 'Sucursal eliminada']);
    }
}
